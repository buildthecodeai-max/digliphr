<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\PayrollPeriod;
use App\Models\PayrollRecord;
use App\Models\SalaryStructure;
use App\Models\Shift;
use App\Services\AttendanceCalculationService;
use RuntimeException;

class PayrollService
{
    private Database $db;
    private PayrollPeriod $periods;
    private PayrollRecord $records;
    private SalaryStructure $salaryStructures;
    private Shift $shifts;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->periods = new PayrollPeriod();
        $this->records = new PayrollRecord();
        $this->salaryStructures = new SalaryStructure();
        $this->shifts = new Shift();
    }

    public function createPeriod(int $companyId, int $year, int $month, ?int $userId = null): int
    {
        $existing = $this->periods->findByCompanyMonth($companyId, $year, $month);
        if ($existing) {
            throw new RuntimeException('Payroll period already exists for this month.');
        }

        // Also check archived (soft-deleted) periods — the unique key covers all rows
        $archived = $this->db->fetch(
            'SELECT id FROM payroll_periods WHERE company_id = :cid AND period_year = :y AND period_month = :m AND deleted_at IS NOT NULL LIMIT 1',
            ['cid' => $companyId, 'y' => $year, 'm' => $month]
        );
        if ($archived) {
            throw new RuntimeException(
                'A payroll period for ' . date('F Y', mktime(0, 0, 0, $month, 1, $year)) .
                ' exists but is archived. Go to Archived Payroll and restore it instead of creating a new one.'
            );
        }

        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));
        $name = date('F Y', strtotime($startDate));

        return $this->periods->create([
            'company_id' => $companyId,
            'name' => $name,
            'code' => sprintf('PAY-%04d-%02d', $year, $month),
            'period_year' => $year,
            'period_month' => $month,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'pay_date' => date('Y-m-d', strtotime($endDate . ' +5 days')),
            'status' => 'draft',
            'created_by' => $userId,
        ]);
    }

    public function processPeriod(int $periodId, ?int $userId = null): array
    {
        $period = $this->periods->find($periodId);
        if (!$period) {
            throw new RuntimeException('Payroll period not found.');
        }
        if (!$this->periods->canProcess($period)) {
            throw new RuntimeException('This payroll period cannot be processed.');
        }

        $this->db->beginTransaction();
        try {
            $this->periods->update($periodId, ['status' => 'processing']);

            $employees = $this->loadEligibleEmployees((int) $period['company_id'], $period['start_date'], $period['end_date']);
            $totalGross = 0.0;
            $totalDeductions = 0.0;
            $totalNet = 0.0;
            $processed = 0;

            foreach ($employees as $employee) {
                $result = $this->processEmployee($period, $employee, $userId);
                if ($result) {
                    $totalGross += $result['gross_earnings'];
                    $totalDeductions += $result['total_deductions'];
                    $totalNet += $result['net_salary'];
                    $processed++;
                }
            }

            $this->periods->update($periodId, [
                'status' => 'calculated',
                'total_employees' => $processed,
                'total_gross' => round($totalGross, 2),
                'total_deductions' => round($totalDeductions, 2),
                'total_net' => round($totalNet, 2),
            ]);

            $this->db->commit();
            (new AuditService())->log('process', 'payroll', $periodId, null, ['employees' => $processed]);

            return [
                'period_id' => $periodId,
                'employees_processed' => $processed,
                'total_gross' => round($totalGross, 2),
                'total_deductions' => round($totalDeductions, 2),
                'total_net' => round($totalNet, 2),
            ];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            $this->periods->update($periodId, ['status' => 'draft']);
            throw $e;
        }
    }

    private function loadEligibleEmployees(int $companyId, string $startDate, string $endDate): array
    {
        return $this->db->fetchAll(
            'SELECT e.* FROM employees e
             WHERE e.company_id = :cid
               AND e.deleted_at IS NULL
               AND e.employment_status IN ("active", "probation", "notice_period")
               AND e.joining_date <= :end
               AND (e.last_working_date IS NULL OR e.last_working_date >= :start)
             ORDER BY e.id',
            ['cid' => $companyId, 'start' => $startDate, 'end' => $endDate]
        );
    }

    private function processEmployee(array $period, array $employee, ?int $userId): ?array
    {
        $periodId = (int) $period['id'];
        $employeeId = (int) $employee['id'];
        $startDate = $period['start_date'];
        $endDate = $period['end_date'];

        $shift = !empty($employee['shift_id']) ? $this->shifts->find((int) $employee['shift_id']) : null;
        $attendance = $this->finalizeAttendance($employeeId, $startDate, $endDate, $shift);
        $leaveStats = $this->reconcileLeave($employeeId, $startDate, $endDate);
        $proration = $this->calculateProration($employee, $startDate, $endDate);

        $salaryAssignment = $this->salaryStructures->getEmployeeAssignment($employeeId, $endDate);
        $basicSalary = (float) ($salaryAssignment['basic_salary'] ?? $employee['basic_salary'] ?? 0);
        $basicSalary = round($basicSalary * $proration['factor'], 2);

        $components = $this->buildComponentAmounts($employeeId, $basicSalary, $endDate, $salaryAssignment);
        $overtime = $this->calculateOvertime($employeeId, (int) $period['company_id'], $startDate, $endDate, $basicSalary);
        $unpaidLeaveDeduction = $this->calculateUnpaidLeaveDeduction($basicSalary, $attendance['working_days'], $leaveStats['unpaid_days']);
        $loanDeduction = $this->calculateLoanInstallments($employeeId, $periodId, $startDate, $endDate);
        $advanceDeduction = $this->calculateAdvanceDeductions($employeeId, $periodId);

        $grossEarnings = $basicSalary + $components['earnings_total'] + $overtime['amount'];
        $totalDeductions = $components['deductions_total'] + $unpaidLeaveDeduction + $loanDeduction + $advanceDeduction;
        $netSalary = max(0, round($grossEarnings - $totalDeductions, 2));

        $existing = $this->records->findByPeriodEmployee($periodId, $employeeId);
        $recordData = [
            'payroll_period_id' => $periodId,
            'employee_id' => $employeeId,
            'company_id' => (int) $period['company_id'],
            'basic_salary' => $basicSalary,
            'gross_earnings' => round($grossEarnings, 2),
            'total_deductions' => round($totalDeductions, 2),
            'net_salary' => $netSalary,
            'working_days' => $attendance['working_days'],
            'present_days' => $attendance['present_days'],
            'absent_days' => $attendance['absent_days'],
            'leave_days' => $leaveStats['paid_days'] + $leaveStats['unpaid_days'],
            'holiday_days' => $attendance['holiday_days'],
            'weekend_days' => $attendance['weekend_days'],
            'overtime_minutes' => $overtime['minutes'],
            'overtime_amount' => $overtime['amount'],
            'loan_deduction' => $loanDeduction,
            'advance_deduction' => $advanceDeduction,
            'tax_amount' => $components['tax_amount'],
            'currency' => $employee['currency'] ?? 'PKR',
            'status' => 'calculated',
            'calculated_at' => date('Y-m-d H:i:s'),
            'created_by' => $userId,
        ];

        if ($existing) {
            $this->records->update((int) $existing['id'], $recordData);
            $recordId = (int) $existing['id'];
            $this->clearLineItems($recordId);
        } else {
            $recordData['uuid'] = $this->uuid();
            $recordId = $this->records->create($recordData);
        }

        $this->saveEarnings($recordId, $basicSalary, $components['earnings'], $overtime);
        $this->saveDeductions($recordId, $components['deductions'], $unpaidLeaveDeduction, $loanDeduction, $advanceDeduction);

        return [
            'gross_earnings' => round($grossEarnings, 2),
            'total_deductions' => round($totalDeductions, 2),
            'net_salary' => $netSalary,
        ];
    }

    private function finalizeAttendance(int $employeeId, string $startDate, string $endDate, ?array $shift = null): array
    {
        $rows = $this->db->fetchAll(
            'SELECT status, COUNT(*) AS cnt FROM attendance
             WHERE employee_id = :eid AND attendance_date BETWEEN :start AND :end AND deleted_at IS NULL
             GROUP BY status',
            ['eid' => $employeeId, 'start' => $startDate, 'end' => $endDate]
        );

        $counts = array_column($rows, 'cnt', 'status');
        $present = (float) (($counts['present'] ?? 0) + ($counts['late'] ?? 0) + ($counts['remote'] ?? 0) + ($counts['manual'] ?? 0));
        $absent = (float) ($counts['absent'] ?? 0);
        $onLeave = (float) ($counts['on_leave'] ?? 0);
        $holidays = (float) ($counts['holiday'] ?? 0);
        $weekends = (float) ($counts['weekend'] ?? 0);
        $halfDays = (float) ($counts['half_day'] ?? 0);
        $present += $halfDays * 0.5;
        $absent += $halfDays * 0.5;

        $workingDays = $this->countWorkingDays($startDate, $endDate, $shift);

        return [
            'working_days' => $workingDays,
            'present_days' => $present,
            'absent_days' => $absent,
            'leave_days' => $onLeave,
            'holiday_days' => $holidays,
            'weekend_days' => $weekends,
        ];
    }

    private function reconcileLeave(int $employeeId, string $startDate, string $endDate): array
    {
        $rows = $this->db->fetchAll(
            'SELECT lr.chargeable_days, lt.is_paid
             FROM leave_requests lr
             INNER JOIN leave_types lt ON lt.id = lr.leave_type_id
             WHERE lr.employee_id = :eid
               AND lr.status = "approved"
               AND lr.start_date <= :end AND lr.end_date >= :start
               AND lr.deleted_at IS NULL',
            ['eid' => $employeeId, 'start' => $startDate, 'end' => $endDate]
        );

        $paid = 0.0;
        $unpaid = 0.0;
        foreach ($rows as $row) {
            $days = (float) $row['chargeable_days'];
            if ((int) $row['is_paid']) {
                $paid += $days;
            } else {
                $unpaid += $days;
            }
        }

        return ['paid_days' => $paid, 'unpaid_days' => $unpaid];
    }

    private function calculateProration(array $employee, string $startDate, string $endDate): array
    {
        $periodStart = strtotime($startDate);
        $periodEnd = strtotime($endDate);
        $joining = strtotime($employee['joining_date']);
        $lastWorking = !empty($employee['last_working_date']) ? strtotime($employee['last_working_date']) : $periodEnd;

        $effectiveStart = max($periodStart, $joining);
        $effectiveEnd = min($periodEnd, $lastWorking);

        if ($effectiveEnd < $effectiveStart) {
            return ['factor' => 0.0, 'days' => 0];
        }

        $totalDays = (int) ((strtotime($endDate) - strtotime($startDate)) / 86400) + 1;
        $activeDays = (int) (($effectiveEnd - $effectiveStart) / 86400) + 1;
        $factor = $totalDays > 0 ? $activeDays / $totalDays : 0;

        return ['factor' => round($factor, 4), 'days' => $activeDays];
    }

    private function buildComponentAmounts(int $employeeId, float $basicSalary, string $asOfDate, ?array $assignment): array
    {
        $items = [];
        if ($assignment) {
            $items = $this->salaryStructures->getItems((int) $assignment['salary_structure_id']);
        }
        $overrides = $this->salaryStructures->getEmployeeComponents($employeeId, $asOfDate);

        $earnings = [];
        $deductions = [];
        $earningsTotal = 0.0;
        $deductionsTotal = 0.0;
        $taxAmount = 0.0;

        foreach ($items as $item) {
            $amount = $this->resolveComponentAmount($item, $basicSalary);
            $entry = [
                'salary_component_id' => (int) $item['salary_component_id'],
                'component_code' => $item['component_code'],
                'component_name' => $item['component_name'],
                'amount' => $amount,
                'is_taxable' => (int) ($item['is_taxable'] ?? 1),
            ];
            if ($item['component_type'] === 'earning') {
                $earnings[] = $entry;
                $earningsTotal += $amount;
            } else {
                $deductions[] = $entry;
                $deductionsTotal += $amount;
                if ((int) ($item['is_statutory'] ?? 0)) {
                    $taxAmount += $amount;
                }
            }
        }

        foreach ($overrides as $override) {
            $amount = $this->resolveComponentAmount($override, $basicSalary);
            $entry = [
                'salary_component_id' => (int) $override['salary_component_id'],
                'component_code' => $override['component_code'],
                'component_name' => $override['component_name'],
                'amount' => $amount,
                'is_taxable' => (int) ($override['is_taxable'] ?? 1),
            ];
            if ($override['component_type'] === 'earning') {
                $earnings[] = $entry;
                $earningsTotal += $amount;
            } else {
                $deductions[] = $entry;
                $deductionsTotal += $amount;
                if ((int) ($override['is_statutory'] ?? 0)) {
                    $taxAmount += $amount;
                }
            }
        }

        return [
            'earnings' => $earnings,
            'deductions' => $deductions,
            'earnings_total' => round($earningsTotal, 2),
            'deductions_total' => round($deductionsTotal, 2),
            'tax_amount' => round($taxAmount, 2),
        ];
    }

    private function resolveComponentAmount(array $item, float $basicSalary): float
    {
        $calcType = $item['calculation_type'] ?? $item['default_calculation_type'] ?? 'fixed';
        if ($calcType === 'percentage') {
            $pct = (float) ($item['percentage'] ?? $item['default_amount'] ?? 0);
            $base = ($item['percentage_of'] ?? 'basic') === 'basic' ? $basicSalary : $basicSalary;
            return round($base * ($pct / 100), 2);
        }
        return round((float) ($item['amount'] ?? $item['default_amount'] ?? 0), 2);
    }

    private function calculateOvertime(int $employeeId, int $companyId, string $startDate, string $endDate, float $basicSalary): array
    {
        $row = $this->db->fetch(
            'SELECT COALESCE(SUM(COALESCE(approved_minutes, requested_minutes)), 0) AS minutes,
                    COALESCE(SUM(amount), 0) AS amount
             FROM overtime_requests
             WHERE employee_id = :eid AND company_id = :cid
               AND status = "approved"
               AND overtime_date BETWEEN :start AND :end
               AND deleted_at IS NULL',
            ['eid' => $employeeId, 'cid' => $companyId, 'start' => $startDate, 'end' => $endDate]
        );

        $minutes = (int) ($row['minutes'] ?? 0);
        $amount = (float) ($row['amount'] ?? 0);

        if ($minutes > 0 && $amount <= 0) {
            $hourlyRate = $basicSalary / 176;
            $amount = round($hourlyRate * ($minutes / 60) * 1.5, 2);
        }

        return ['minutes' => $minutes, 'amount' => round($amount, 2)];
    }

    private function calculateUnpaidLeaveDeduction(float $basicSalary, float $workingDays, float $unpaidDays): float
    {
        if ($unpaidDays <= 0 || $workingDays <= 0) {
            return 0.0;
        }
        $dailyRate = $basicSalary / $workingDays;
        return round($dailyRate * $unpaidDays, 2);
    }

    private function calculateLoanInstallments(int $employeeId, int $periodId, string $startDate, string $endDate): float
    {
        $rows = $this->db->fetchAll(
            'SELECT li.* FROM loan_installments li
             INNER JOIN loans l ON l.id = li.loan_id
             WHERE li.employee_id = :eid
               AND li.status IN ("pending", "overdue")
               AND li.due_date BETWEEN :start AND :end
               AND l.status = "active"',
            ['eid' => $employeeId, 'start' => $startDate, 'end' => $endDate]
        );

        $total = 0.0;
        foreach ($rows as $row) {
            $total += (float) $row['amount'];
            $this->db->update('loan_installments', [
                'status' => 'paid',
                'paid_at' => date('Y-m-d H:i:s'),
                'payroll_period_id' => $periodId,
            ], 'id = :id', ['id' => $row['id']]);
        }

        return round($total, 2);
    }

    private function calculateAdvanceDeductions(int $employeeId, int $periodId): float
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM salary_advances
             WHERE employee_id = :eid
               AND status IN ("disbursed", "repaying")
               AND remaining_amount > 0
               AND deleted_at IS NULL',
            ['eid' => $employeeId]
        );

        $total = 0.0;
        foreach ($rows as $row) {
            $installment = round((float) $row['remaining_amount'] / max(1, (int) $row['installments_count']), 2);
            $total += $installment;
            $newRemaining = max(0, (float) $row['remaining_amount'] - $installment);
            $this->db->update('salary_advances', [
                'repaid_amount' => (float) $row['repaid_amount'] + $installment,
                'remaining_amount' => $newRemaining,
                'status' => $newRemaining <= 0 ? 'completed' : 'repaying',
                'payroll_period_id' => $periodId,
            ], 'id = :id', ['id' => $row['id']]);
        }

        return round($total, 2);
    }

    /**
     * $shift is optional and, when its working_days column is unset (the
     * default for every shift today), this is byte-for-byte the same
     * Mon-Fri count this method always computed — see
     * AttendanceCalculationService::isWorkingDay() for the shared rule this
     * now delegates to instead of hardcoding "weekday < 6" a third time
     * (LeaveService and cron/run.php each had their own copy of this exact
     * check before the Attendance Intelligence & Reporting module).
     */
    private function countWorkingDays(string $startDate, string $endDate, ?array $shift = null): float
    {
        $days = 0;
        $current = strtotime($startDate);
        $end = strtotime($endDate);
        while ($current <= $end) {
            if (AttendanceCalculationService::isWorkingDay($shift, date('Y-m-d', $current))) {
                $days++;
            }
            $current = strtotime('+1 day', $current);
        }
        return (float) $days;
    }

    private function clearLineItems(int $recordId): void
    {
        $this->db->delete('payroll_earnings', 'payroll_record_id = :id', ['id' => $recordId]);
        $this->db->delete('payroll_deductions', 'payroll_record_id = :id', ['id' => $recordId]);
    }

    private function saveEarnings(int $recordId, float $basicSalary, array $earnings, array $overtime): void
    {
        $this->db->insert('payroll_earnings', [
            'payroll_record_id' => $recordId,
            'component_code' => 'BASIC',
            'component_name' => 'Basic Salary',
            'amount' => $basicSalary,
            'is_taxable' => 1,
        ]);

        foreach ($earnings as $item) {
            $this->db->insert('payroll_earnings', [
                'payroll_record_id' => $recordId,
                'salary_component_id' => $item['salary_component_id'] ?? null,
                'component_code' => $item['component_code'],
                'component_name' => $item['component_name'],
                'amount' => $item['amount'],
                'is_taxable' => $item['is_taxable'] ?? 1,
            ]);
        }

        if ($overtime['amount'] > 0) {
            $this->db->insert('payroll_earnings', [
                'payroll_record_id' => $recordId,
                'component_code' => 'OT',
                'component_name' => 'Overtime',
                'amount' => $overtime['amount'],
                'is_taxable' => 1,
                'calculation_notes' => $overtime['minutes'] . ' minutes',
            ]);
        }
    }

    private function saveDeductions(int $recordId, array $deductions, float $unpaidLeave, float $loan, float $advance): void
    {
        foreach ($deductions as $item) {
            $this->db->insert('payroll_deductions', [
                'payroll_record_id' => $recordId,
                'salary_component_id' => $item['salary_component_id'] ?? null,
                'component_code' => $item['component_code'],
                'component_name' => $item['component_name'],
                'amount' => $item['amount'],
            ]);
        }

        if ($unpaidLeave > 0) {
            $this->db->insert('payroll_deductions', [
                'payroll_record_id' => $recordId,
                'component_code' => 'UNPAID_LEAVE',
                'component_name' => 'Unpaid Leave Deduction',
                'amount' => $unpaidLeave,
            ]);
        }
        if ($loan > 0) {
            $this->db->insert('payroll_deductions', [
                'payroll_record_id' => $recordId,
                'component_code' => 'LOAN',
                'component_name' => 'Loan Installment',
                'amount' => $loan,
            ]);
        }
        if ($advance > 0) {
            $this->db->insert('payroll_deductions', [
                'payroll_record_id' => $recordId,
                'component_code' => 'ADVANCE',
                'component_name' => 'Salary Advance Recovery',
                'amount' => $advance,
            ]);
        }
    }

    public function approvePeriod(int $periodId, int $userId): void
    {
        $period = $this->periods->find($periodId);
        if (!$period || $period['status'] !== 'calculated') {
            throw new RuntimeException('Period must be calculated before approval.');
        }

        $this->db->beginTransaction();
        try {
            $this->periods->update($periodId, [
                'status' => 'approved',
                'approved_at' => date('Y-m-d H:i:s'),
                'approved_by' => $userId,
            ]);
            $this->db->query(
                'UPDATE payroll_records SET status = "approved", approved_at = NOW(), approved_by = :uid
                 WHERE payroll_period_id = :pid',
                ['uid' => $userId, 'pid' => $periodId]
            );
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function lockPeriod(int $periodId, int $userId): void
    {
        $period = $this->periods->find($periodId);
        if (!$period || !in_array($period['status'], ['approved', 'calculated'], true)) {
            throw new RuntimeException('Only approved or calculated payroll can be locked.');
        }

        $this->periods->update($periodId, [
            'status' => 'locked',
            'locked_at' => date('Y-m-d H:i:s'),
            'locked_by' => $userId,
        ]);
        (new AuditService())->log('update', 'payroll_periods', $periodId, $period, ['status' => 'locked'], $userId);
    }

    public function reopenPeriod(int $periodId, int $userId, string $reason): void
    {
        $period = $this->periods->find($periodId);
        if (!$period) {
            throw new RuntimeException('Payroll period not found.');
        }
        if (!in_array($period['status'], ['approved', 'locked', 'calculated'], true)) {
            throw new RuntimeException('Payroll cannot be reopened in its current status.');
        }
        if ($period['status'] === 'paid') {
            throw new RuntimeException('Paid payroll cannot be reopened directly. Create an adjustment instead.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('Reopen reason is required.');
        }

        $this->db->beginTransaction();
        try {
            $this->db->update('payroll_periods', [
                'status' => 'reopened',
                'locked_at' => null,
                'locked_by' => null,
                'notes' => trim(($period['notes'] ?? '') . "\n[REOPENED] " . $reason),
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => $periodId]);
            $this->db->query(
                'UPDATE payroll_records SET status = "draft", updated_at = NOW()
                 WHERE payroll_period_id = :pid AND status IN ("approved","locked")',
                ['pid' => $periodId]
            );
            (new AuditService())->log('update', 'payroll_periods', $periodId, $period, [
                'status' => 'reopened',
                'reason' => $reason,
            ], $userId);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function cancelPeriod(int $periodId, int $userId, string $reason): void
    {
        $period = $this->periods->find($periodId);
        if (!$period) {
            throw new RuntimeException('Payroll period not found.');
        }
        if (in_array($period['status'], ['locked', 'paid'], true)) {
            throw new RuntimeException('Locked or paid payroll cannot be cancelled. Reopen first.');
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('Cancellation reason is required.');
        }

        $this->periods->update($periodId, [
            'status' => 'cancelled',
            'notes' => trim(($period['notes'] ?? '') . "\n[CANCELLED] " . $reason),
        ]);
        (new AuditService())->log('update', 'payroll_periods', $periodId, $period, [
            'status' => 'cancelled',
            'reason' => $reason,
        ], $userId);
    }

    public function archivePeriod(int $periodId, int $userId, string $reason): void
    {
        $period = $this->periods->find($periodId);
        if (!$period) {
            throw new RuntimeException('Payroll period not found.');
        }
        if (!empty($period['deleted_at'])) {
            throw new RuntimeException('Payroll is already archived.');
        }
        if (in_array($period['status'], ['locked', 'paid'], true)) {
            throw new RuntimeException('Locked or paid payroll cannot be archived directly. Reopen or use Super Admin correction workflow.');
        }
        if ($period['status'] === 'approved') {
            throw new RuntimeException('Approved payroll must be cancelled or reopened before archive.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('Archive reason is required.');
        }

        $now = date('Y-m-d H:i:s');
        $this->db->update('payroll_periods', [
            'deleted_at' => $now,
            'deleted_by' => $userId,
            'deletion_reason' => $reason,
            'updated_at' => $now,
            'notes' => trim(($period['notes'] ?? '') . "\n[ARCHIVED] " . $reason),
        ], 'id = :id', ['id' => $periodId]);

        (new AuditService())->log('delete', 'payroll_periods', $periodId, $period, ['reason' => $reason], $userId);
    }

    public function restorePeriod(int $periodId, int $userId): void
    {
        $period = $this->periods->findIncludingArchived($periodId);
        if (!$period || empty($period['deleted_at'])) {
            throw new RuntimeException('Archived payroll period not found.');
        }

        $duplicate = $this->findByCompanyMonthSafe(
            (int) $period['company_id'],
            (int) $period['period_year'],
            (int) $period['period_month'],
            $periodId
        );
        if ($duplicate) {
            throw new RuntimeException('Cannot restore payroll because another period already exists for this company and month.');
        }

        $this->db->update('payroll_periods', [
            'deleted_at' => null,
            'deleted_by' => null,
            'deletion_reason' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $periodId]);

        (new AuditService())->log('restore', 'payroll_periods', $periodId, $period, null, $userId);
    }

    public function permanentlyDeletePeriod(int $periodId, int $userId, string $reason): void
    {
        $period = $this->periods->findIncludingArchived($periodId);
        if (!$period || empty($period['deleted_at'])) {
            throw new RuntimeException('Only archived payroll can be permanently deleted.');
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('Permanent deletion reason is required.');
        }

        $recordIds = $this->db->fetchAll(
            'SELECT id FROM payroll_records WHERE payroll_period_id = :id',
            ['id' => $periodId]
        );
        $ids = array_map(static fn ($r) => (int) $r['id'], $recordIds);

        $this->db->beginTransaction();
        try {
            (new AuditService())->log('delete', 'payroll_periods', $periodId, $period, [
                'permanent' => true,
                'reason' => $reason,
            ], $userId);

            if ($ids) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $this->db->query("DELETE FROM payroll_earnings WHERE payroll_record_id IN ({$placeholders})", $ids);
                $this->db->query("DELETE FROM payroll_deductions WHERE payroll_record_id IN ({$placeholders})", $ids);
                $this->db->query("DELETE FROM payroll_records WHERE id IN ({$placeholders})", $ids);
            }
            $this->db->delete('payroll_periods', 'id = :id', ['id' => $periodId]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function updateDraftRecord(int $recordId, array $data, int $userId): void
    {
        $record = $this->records->findDetailed($recordId);
        if (!$record) {
            throw new RuntimeException('Payroll record not found.');
        }

        $period = $this->periods->find((int) $record['payroll_period_id']);
        if (!$period || !in_array($period['status'], ['draft', 'reopened', 'calculated'], true)) {
            throw new RuntimeException('Only draft or reopened payroll records can be edited.');
        }

        $gross = (float) ($data['gross_earnings'] ?? $record['gross_earnings']);
        $deductions = (float) ($data['total_deductions'] ?? $record['total_deductions']);
        $net = round($gross - $deductions, 2);

        $payload = [
            'gross_earnings' => $gross,
            'total_deductions' => $deductions,
            'net_salary' => $net,
            'remarks' => $data['remarks'] ?? $data['notes'] ?? $record['remarks'] ?? null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $this->db->update('payroll_records', $payload, 'id = :id', ['id' => $recordId]);
        $this->recalculatePeriodTotals((int) $period['id']);
        (new AuditService())->log('update', 'payroll_records', $recordId, $record, $payload, $userId);
    }

    private function recalculatePeriodTotals(int $periodId): void
    {
        $totals = $this->db->fetch(
            'SELECT COUNT(*) AS total_employees,
                    COALESCE(SUM(gross_earnings),0) AS total_gross,
                    COALESCE(SUM(total_deductions),0) AS total_deductions,
                    COALESCE(SUM(net_salary),0) AS total_net
             FROM payroll_records WHERE payroll_period_id = :id',
            ['id' => $periodId]
        );
        if ($totals) {
            $this->periods->update($periodId, [
                'total_employees' => (int) $totals['total_employees'],
                'total_gross' => $totals['total_gross'],
                'total_deductions' => $totals['total_deductions'],
                'total_net' => $totals['total_net'],
            ]);
        }
    }

    private function findByCompanyMonthSafe(int $companyId, int $year, int $month, int $excludeId): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM payroll_periods
             WHERE company_id = :company_id AND period_year = :year AND period_month = :month
               AND deleted_at IS NULL AND id <> :id
             LIMIT 1',
            ['company_id' => $companyId, 'year' => $year, 'month' => $month, 'id' => $excludeId]
        );
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
