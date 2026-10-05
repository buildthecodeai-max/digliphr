<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Aggregate reporting queries — all filters applied server-side.
 */
class ReportService
{
    private Database $db;
    private TenantContext $tenant;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->tenant = new TenantContext(new AuthService());
    }

    public function normalizeFilters(array $input): array
    {
        $period = (string) ($input['period'] ?? 'this_month');
        $allowedPeriods = ['this_month', 'last_month', 'this_quarter', 'last_quarter', 'this_year', 'last_year', 'custom'];
        if (!in_array($period, $allowedPeriods, true)) {
            $period = 'this_month';
        }

        [$presetFrom, $presetTo] = $this->periodBounds($period);
        $from = $period === 'custom' ? ($input['from'] ?? $input['from_date'] ?? $presetFrom) : $presetFrom;
        $to = $period === 'custom' ? ($input['to'] ?? $input['to_date'] ?? $presetTo) : $presetTo;
        if (strtotime((string) $from) === false) {
            $from = date('Y-m-01');
        }
        if (strtotime((string) $to) === false) {
            $to = date('Y-m-d');
        }
        if (strtotime((string) $to) < strtotime((string) $from)) {
            [$from, $to] = [$to, $from];
        }

        $days = max(1, (int) ((strtotime((string) $to) - strtotime((string) $from)) / 86400) + 1);
        $prevTo = date('Y-m-d', strtotime((string) $from . ' -1 day'));
        $prevFrom = date('Y-m-d', strtotime($prevTo . ' -' . ($days - 1) . ' days'));

        return [
            'period' => $period,
            'period_label' => $this->periodLabel($period, $from, $to),
            'from' => $from,
            'to' => $to,
            'prev_from' => $prevFrom,
            'prev_to' => $prevTo,
            'branch_id' => $this->nullableInt($input['branch_id'] ?? null),
            'department_id' => $this->nullableInt($input['department_id'] ?? null),
            'employee_id' => $this->nullableInt($input['employee_id'] ?? null),
            'shift_id' => $this->nullableInt($input['shift_id'] ?? null),
            'employment_status' => $input['employment_status'] ?? null,
            'status' => $input['status'] ?? null,
            'company_id' => $this->tenant->resolveCompanyId($input['company_id'] ?? null),
            'compare' => !empty($input['compare']),
            'group' => in_array(($group = $input['group'] ?? 'day'), ['day', 'week', 'month'], true) ? $group : 'day',
        ];
    }

    public function filterOptions(): array
    {
        $companyId = $this->tenant->resolveCompanyId($_GET['company_id'] ?? null);
        $where = $companyId ? ' AND company_id = :company_id' : '';
        $params = $companyId ? ['company_id' => $companyId] : [];
        return [
            'branches' => $this->db->fetchAll('SELECT id, name FROM branches WHERE deleted_at IS NULL' . $where . ' ORDER BY name', $params),
            'departments' => $this->db->fetchAll('SELECT id, name FROM departments WHERE deleted_at IS NULL' . $where . ' ORDER BY name', $params),
            'shifts' => $this->db->fetchAll('SELECT id, name FROM shifts WHERE deleted_at IS NULL AND is_active = 1' . $where . ' ORDER BY name', $params),
            'employees' => $this->db->fetchAll(
                'SELECT id, CONCAT(first_name, " ", last_name) AS name, employee_code
                 FROM employees WHERE deleted_at IS NULL AND employment_status IN ("active","probation")
                 ' . $where . ' ORDER BY first_name LIMIT 500',
                $params
            ),
            'periods' => $this->db->fetchAll(
                'SELECT id, name, period_year, period_month, status FROM payroll_periods
                 WHERE deleted_at IS NULL' . $where . ' ORDER BY period_year DESC, period_month DESC LIMIT 24',
                $params
            ),
        ];
    }

    public function executiveSummary(array $filters): array
    {
        $current = $this->summaryForRange($filters['from'], $filters['to'], $filters);
        $previous = $this->summaryForRange($filters['prev_from'], $filters['prev_to'], $filters);

        $cards = [];
        foreach ($current as $key => $value) {
            $prev = (float) ($previous[$key] ?? 0);
            $curr = (float) $value;
            $cards[$key] = [
                'value' => $curr,
                'previous' => $prev,
                'change_pct' => $this->pctChange($prev, $curr),
            ];
        }

        return $cards;
    }

    public function attendanceTrend(array $filters): array
    {
        $group = $filters['group'];
        $labelExpr = match ($group) {
            'week' => "DATE_FORMAT(a.attendance_date, '%x-W%v')",
            'month' => "DATE_FORMAT(a.attendance_date, '%Y-%m')",
            default => 'a.attendance_date',
        };

        [$where, $params] = $this->attendanceWhere($filters);

        $rows = $this->db->fetchAll(
            "SELECT {$labelExpr} AS label,
                    SUM(a.status IN ('present','manual')) AS present,
                    SUM(a.status = 'absent') AS absent,
                    SUM(a.status = 'on_leave') AS on_leave,
                    SUM(a.status = 'late') AS late,
                    SUM(a.status = 'half_day') AS half_day,
                    SUM(a.status = 'remote' OR a.is_remote = 1) AS remote
             FROM attendance a
             INNER JOIN employees e ON e.id = a.employee_id
             WHERE {$where}
             GROUP BY label
             ORDER BY label ASC",
            $params
        );

        return [
            'labels' => array_column($rows, 'label'),
            'present' => array_map('intval', array_column($rows, 'present')),
            'absent' => array_map('intval', array_column($rows, 'absent')),
            'on_leave' => array_map('intval', array_column($rows, 'on_leave')),
            'late' => array_map('intval', array_column($rows, 'late')),
            'half_day' => array_map('intval', array_column($rows, 'half_day')),
            'remote' => array_map('intval', array_column($rows, 'remote')),
        ];
    }

    public function attendanceStatusDistribution(array $filters): array
    {
        [$where, $params] = $this->attendanceWhere($filters);
        $rows = $this->db->fetchAll(
            "SELECT a.status, COUNT(*) AS total
             FROM attendance a
             INNER JOIN employees e ON e.id = a.employee_id
             WHERE {$where}
             GROUP BY a.status
             ORDER BY total DESC",
            $params
        );

        return [
            'labels' => array_map(static fn ($r) => ucwords(str_replace('_', ' ', (string) $r['status'])), $rows),
            'values' => array_map(static fn ($r) => (int) $r['total'], $rows),
            'raw' => $rows,
        ];
    }

    public function attendanceByDepartment(array $filters): array
    {
        [$where, $params] = $this->attendanceWhere($filters);
        $rows = $this->db->fetchAll(
            "SELECT COALESCE(d.name, 'Unassigned') AS department,
                    COUNT(*) AS total,
                    SUM(a.status IN ('present','remote','manual','late','half_day')) AS present,
                    SUM(a.status = 'absent') AS absent,
                    SUM(a.status = 'late') AS late
             FROM attendance a
             INNER JOIN employees e ON e.id = a.employee_id
             LEFT JOIN departments d ON d.id = e.department_id
             WHERE {$where}
             GROUP BY d.id, d.name
             ORDER BY present DESC
             LIMIT 12",
            $params
        );

        foreach ($rows as &$row) {
            $total = max(1, (int) $row['total']);
            $row['attendance_pct'] = round(((int) $row['present'] / $total) * 100, 1);
        }
        unset($row);

        return $rows;
    }

    public function workHoursAnalysis(array $filters): array
    {
        [$where, $params] = $this->attendanceWhere($filters);
        $row = $this->db->fetch(
            "SELECT
                ROUND(AVG(NULLIF(a.work_minutes, 0)), 0) AS avg_work_minutes,
                ROUND(AVG(a.overtime_minutes), 0) AS avg_overtime_minutes,
                SUM(a.overtime_minutes) AS total_overtime_minutes,
                SUM(CASE WHEN a.work_minutes > 0 AND a.work_minutes < 420 THEN 420 - a.work_minutes ELSE 0 END) AS missing_minutes
             FROM attendance a
             INNER JOIN employees e ON e.id = a.employee_id
             WHERE {$where}",
            $params
        ) ?: [];

        return [
            'avg_work_minutes' => (int) ($row['avg_work_minutes'] ?? 0),
            'required_minutes' => 480,
            'avg_overtime_minutes' => (int) ($row['avg_overtime_minutes'] ?? 0),
            'total_overtime_minutes' => (int) ($row['total_overtime_minutes'] ?? 0),
            'missing_minutes' => (int) ($row['missing_minutes'] ?? 0),
        ];
    }

    public function attendanceHeatmap(array $filters): array
    {
        [$where, $params] = $this->attendanceWhere($filters);
        $params['limit_emp'] = 25;

        $employees = $this->db->fetchAll(
            "SELECT e.id, CONCAT(e.first_name, ' ', e.last_name) AS name, e.employee_code
             FROM attendance a
             INNER JOIN employees e ON e.id = a.employee_id
             WHERE {$where}
             GROUP BY e.id, e.first_name, e.last_name, e.employee_code
             ORDER BY e.first_name
             LIMIT " . (int) $params['limit_emp'],
            array_diff_key($params, ['limit_emp' => true])
        );

        if (!$employees) {
            return ['employees' => [], 'dates' => [], 'matrix' => []];
        }

        $ids = array_column($employees, 'id');
        $in = implode(',', array_map('intval', $ids));
        $rows = $this->db->fetchAll(
            "SELECT employee_id, attendance_date, status
             FROM attendance
             WHERE deleted_at IS NULL
               AND attendance_date BETWEEN :from AND :to
               AND employee_id IN ({$in})",
            ['from' => $filters['from'], 'to' => $filters['to']]
        );

        $dates = [];
        $cursor = strtotime($filters['from']);
        $end = strtotime($filters['to']);
        while ($cursor <= $end) {
            $dates[] = date('Y-m-d', $cursor);
            $cursor = strtotime('+1 day', $cursor);
        }

        $lookup = [];
        foreach ($rows as $row) {
            $lookup[$row['employee_id']][$row['attendance_date']] = $row['status'];
        }

        $matrix = [];
        foreach ($employees as $emp) {
            $row = [];
            foreach ($dates as $date) {
                $row[] = $lookup[$emp['id']][$date] ?? null;
            }
            $matrix[] = $row;
        }

        return ['employees' => $employees, 'dates' => $dates, 'matrix' => $matrix];
    }

    public function attendanceTable(array $filters, int $page = 1, int $perPage = 25): array
    {
        [$where, $params] = $this->attendanceWhere($filters);
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $total = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM attendance a INNER JOIN employees e ON e.id = a.employee_id WHERE {$where}",
            $params
        );

        $rows = $this->db->fetchAll(
            "SELECT a.id, a.attendance_date, a.status, a.verification_status, a.check_in_at, a.check_out_at,
                    a.work_minutes, a.late_minutes, a.overtime_minutes, a.is_remote, a.source,
                    e.employee_code, CONCAT(e.first_name,' ',e.last_name) AS employee_name,
                    d.name AS department_name, b.name AS branch_name, s.name AS shift_name
             FROM attendance a
             INNER JOIN employees e ON e.id = a.employee_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN branches b ON b.id = e.branch_id
             LEFT JOIN shifts s ON s.id = a.shift_id
             WHERE {$where}
             ORDER BY a.attendance_date DESC, e.first_name ASC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [
            'data' => $rows,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /**
     * Shared WHERE builder for leave analytics queries. Filters applied:
     * branch, department, employee (all optional). Kept private so
     * leaveAnalytics(), leaveStatusCounts() and leaveStatusTrend() all stay
     * in sync instead of drifting like leaveAnalytics() used to (branch_id
     * was silently ignored before this refactor).
     */
    private function leaveWhere(array $filters): array
    {
        $params = ['from' => $filters['from'], 'to' => $filters['to']];
        $clauses = [
            'lr.deleted_at IS NULL',
            'lr.start_date <= :to',
            'lr.end_date >= :from',
        ];
        [$companySql, $companyParams] = $this->companyFilter($filters, 'lr.company_id', 'leave_company');
        $clauses[] = $companySql;
        $params = array_merge($params, $companyParams);
        if (!empty($filters['branch_id'])) {
            $clauses[] = 'e.branch_id = :branch_id';
            $params['branch_id'] = $filters['branch_id'];
        }
        if (!empty($filters['department_id'])) {
            $clauses[] = 'e.department_id = :department_id';
            $params['department_id'] = $filters['department_id'];
        }
        if (!empty($filters['employee_id'])) {
            $clauses[] = 'lr.employee_id = :employee_id';
            $params['employee_id'] = $filters['employee_id'];
        }
        return [implode(' AND ', $clauses), $params];
    }

    private function leaveStatusCounts(array $filters): array
    {
        [$where, $params] = $this->leaveWhere($filters);
        return $this->db->fetchAll(
            "SELECT lr.status, COUNT(*) AS total, COALESCE(SUM(lr.chargeable_days),0) AS days
             FROM leave_requests lr
             INNER JOIN employees e ON e.id = lr.employee_id
             WHERE {$where}
             GROUP BY lr.status",
            $params
        );
    }

    /** Monthly count per status — powers the KPI card sparklines. */
    public function leaveStatusTrend(array $filters): array
    {
        [$where, $params] = $this->leaveWhere($filters);
        return $this->db->fetchAll(
            "SELECT DATE_FORMAT(lr.start_date, '%Y-%m') AS label, lr.status, COUNT(*) AS total
             FROM leave_requests lr
             INNER JOIN employees e ON e.id = lr.employee_id
             WHERE {$where}
             GROUP BY label, lr.status
             ORDER BY label",
            $params
        );
    }

    /**
     * Shapes the 4 leave-status KPI cards (pending/approved/rejected/cancelled)
     * into the $metricMeta/$summary pair report-metrics.php expects. Reuses
     * the byStatus rows leaveAnalytics() already computed for the current
     * period (no duplicate query); only queries again for the prior period
     * when Compare is active, and once for the sparkline trend.
     */
    public function leaveKpiSummary(array $currentByStatus, array $filters): array
    {
        $meta = [
            'pending' => ['label' => 'Pending', 'tone' => 'purple', 'icon' => 'hourglass', 'href' => '/admin/leave/pending', 'fmt' => 'int'],
            'approved' => ['label' => 'Approved', 'tone' => 'mint', 'icon' => 'check-circle-2', 'href' => '/admin/leave/approved', 'fmt' => 'int'],
            'rejected' => ['label' => 'Rejected', 'tone' => 'red', 'icon' => 'x-circle', 'href' => '/admin/leave?status=rejected', 'fmt' => 'int'],
            'cancelled' => ['label' => 'Cancelled', 'tone' => 'blue', 'icon' => 'calendar-x', 'href' => '/admin/leave?status=cancelled', 'fmt' => 'int'],
        ];

        $current = [];
        foreach ($currentByStatus as $row) {
            $current[$row['status']] = $row;
        }

        $previous = [];
        if (!empty($filters['compare'])) {
            $prevFilters = $filters;
            $prevFilters['from'] = $filters['prev_from'];
            $prevFilters['to'] = $filters['prev_to'];
            foreach ($this->leaveStatusCounts($prevFilters) as $row) {
                $previous[$row['status']] = $row;
            }
        }

        $sparkByStatus = [];
        foreach ($this->leaveStatusTrend($filters) as $row) {
            $sparkByStatus[$row['status']][] = (float) $row['total'];
        }

        $summary = [];
        foreach (array_keys($meta) as $key) {
            $total = (float) ($current[$key]['total'] ?? 0);
            $days = (float) ($current[$key]['days'] ?? 0);
            $item = [
                'value' => $total,
                'sub' => number_format($days, 1) . ' days',
            ];
            if (!empty($filters['compare'])) {
                $item['change_pct'] = $this->pctChange((float) ($previous[$key]['total'] ?? 0), $total);
            }
            if (!empty($sparkByStatus[$key]) && count($sparkByStatus[$key]) >= 2) {
                $item['spark'] = $sparkByStatus[$key];
            }
            $summary[$key] = $item;
        }

        return ['meta' => $meta, 'summary' => $summary];
    }

    public function leaveAnalytics(array $filters): array
    {
        [$where, $params] = $this->leaveWhere($filters);

        $byStatus = $this->leaveStatusCounts($filters);

        $byType = $this->db->fetchAll(
            "SELECT lt.name AS leave_type, COUNT(*) AS total, COALESCE(SUM(lr.chargeable_days),0) AS days
             FROM leave_requests lr
             INNER JOIN leave_types lt ON lt.id = lr.leave_type_id
             INNER JOIN employees e ON e.id = lr.employee_id
             WHERE {$where}
             GROUP BY lt.id, lt.name
             ORDER BY days DESC",
            $params
        );

        $trend = $this->db->fetchAll(
            "SELECT DATE_FORMAT(lr.start_date, '%Y-%m') AS label,
                    COUNT(*) AS applications,
                    COALESCE(SUM(CASE WHEN lr.status='approved' THEN lr.chargeable_days ELSE 0 END),0) AS approved_days
             FROM leave_requests lr
             INNER JOIN employees e ON e.id = lr.employee_id
             WHERE {$where}
             GROUP BY label
             ORDER BY label",
            $params
        );

        $byDepartment = $this->db->fetchAll(
            "SELECT COALESCE(d.name,'Unassigned') AS department,
                    COUNT(*) AS total,
                    SUM(lr.status='approved') AS approved,
                    SUM(lr.status='pending') AS pending,
                    COALESCE(SUM(lr.chargeable_days),0) AS days
             FROM leave_requests lr
             INNER JOIN employees e ON e.id = lr.employee_id
             LEFT JOIN departments d ON d.id = e.department_id
             WHERE {$where}
             GROUP BY d.id, d.name
             ORDER BY days DESC
             LIMIT 12",
            $params
        );

        [$balanceCompanySql, $balanceCompanyParams] = $this->companyFilter(
            $filters,
            'e.company_id',
            'balance_company'
        );
        $balances = $this->db->fetchAll(
            "SELECT CONCAT(e.first_name,' ',e.last_name) AS employee_name, e.employee_code,
                    lt.name AS leave_type,
                    elb.opening_balance, elb.accrued, elb.used, elb.pending, elb.carried_forward, elb.closing_balance
             FROM employee_leave_balances elb
             INNER JOIN employees e ON e.id = elb.employee_id
             INNER JOIN leave_types lt ON lt.id = elb.leave_type_id
             WHERE elb.year = :year AND e.deleted_at IS NULL
               AND ({$balanceCompanySql})
             ORDER BY e.first_name, lt.name
             LIMIT 200",
            array_merge(
                ['year' => (int) date('Y', strtotime($filters['to']))],
                $balanceCompanyParams
            )
        );

        return compact('byStatus', 'byType', 'trend', 'byDepartment', 'balances');
    }

    public function payrollAnalytics(array $filters, ?int $periodId = null): array
    {
        [$periodCompanySql, $periodCompanyParams] = $this->companyFilter($filters, 'pp.company_id', 'payroll_period_company');
        [$recordCompanySql, $recordCompanyParams] = $this->companyFilter($filters, 'pr.company_id', 'payroll_record_company');
        if (!$periodId) {
            $latest = $this->db->fetch(
                'SELECT pp.id FROM payroll_periods pp WHERE pp.deleted_at IS NULL
                   AND (' . $periodCompanySql . ')
                 ORDER BY pp.period_year DESC, pp.period_month DESC LIMIT 1',
                $periodCompanyParams
            );
            $periodId = $latest ? (int) $latest['id'] : 0;
        } elseif (!$this->db->fetchColumn(
            'SELECT COUNT(*) FROM payroll_periods pp WHERE pp.id = :pid AND pp.deleted_at IS NULL
               AND (' . $periodCompanySql . ')',
            array_merge(['pid' => $periodId], $periodCompanyParams)
        )) {
            throw new \App\Exceptions\HttpException('Payroll period not found.', 404);
        }

        $summary = [
            'gross' => 0, 'net' => 0, 'basic' => 0, 'deductions' => 0,
            'overtime' => 0, 'loan' => 0, 'advance' => 0, 'tax' => 0,
        ];
        $byDepartment = [];
        $rows = [];

        if ($periodId) {
            $agg = $this->db->fetch(
                'SELECT
                    COALESCE(SUM(pr.gross_earnings),0) AS gross,
                    COALESCE(SUM(pr.net_salary),0) AS net,
                    COALESCE(SUM(pr.basic_salary),0) AS basic,
                    COALESCE(SUM(pr.total_deductions),0) AS deductions,
                    COALESCE(SUM(pr.overtime_amount),0) AS overtime,
                    COALESCE(SUM(pr.loan_deduction),0) AS loan,
                    COALESCE(SUM(pr.advance_deduction),0) AS advance,
                    COALESCE(SUM(pr.tax_amount),0) AS tax
                 FROM payroll_records pr
                 WHERE pr.payroll_period_id = :pid AND pr.deleted_at IS NULL
                   AND (' . $recordCompanySql . ')',
                array_merge(['pid' => $periodId], $recordCompanyParams)
            ) ?: [];
            $summary = array_map('floatval', $agg);

            $byDepartment = $this->db->fetchAll(
                'SELECT COALESCE(d.name,"Unassigned") AS department,
                        COALESCE(SUM(pr.net_salary),0) AS net,
                        COALESCE(SUM(pr.gross_earnings),0) AS gross
                 FROM payroll_records pr
                 INNER JOIN employees e ON e.id = pr.employee_id
                 LEFT JOIN departments d ON d.id = e.department_id
                 WHERE pr.payroll_period_id = :pid AND pr.deleted_at IS NULL
                   AND (' . $recordCompanySql . ')
                 GROUP BY d.id, d.name
                 ORDER BY net DESC',
                array_merge(['pid' => $periodId], $recordCompanyParams)
            );

            $rows = $this->db->fetchAll(
                'SELECT pr.*, CONCAT(e.first_name," ",e.last_name) AS employee_name, e.employee_code,
                        d.name AS department_name
                 FROM payroll_records pr
                 INNER JOIN employees e ON e.id = pr.employee_id
                 LEFT JOIN departments d ON d.id = e.department_id
                 WHERE pr.payroll_period_id = :pid AND pr.deleted_at IS NULL
                   AND (' . $recordCompanySql . ')
                 ORDER BY e.first_name
                 LIMIT 500',
                array_merge(['pid' => $periodId], $recordCompanyParams)
            );
        }

        $trend = $this->db->fetchAll(
            'SELECT CONCAT(pp.period_year,"-",LPAD(pp.period_month,2,"0")) AS label,
                    COALESCE(SUM(pr.gross_earnings),0) AS gross,
                    COALESCE(SUM(pr.net_salary),0) AS net,
                    COALESCE(SUM(pr.total_deductions),0) AS deductions
             FROM payroll_periods pp
             LEFT JOIN payroll_records pr ON pr.payroll_period_id = pp.id AND pr.deleted_at IS NULL
             WHERE pp.deleted_at IS NULL AND (' . $periodCompanySql . ')
               AND STR_TO_DATE(CONCAT(pp.period_year,"-",LPAD(pp.period_month,2,"0"),"-01"), "%Y-%m-%d")
                   BETWEEN :trend_from AND :trend_to
             GROUP BY pp.id, pp.period_year, pp.period_month
             ORDER BY pp.period_year, pp.period_month
             LIMIT 24',
            array_merge($periodCompanyParams, ['trend_from' => $filters['from'], 'trend_to' => $filters['to']])
        );

        return [
            'period_id' => $periodId,
            'summary' => $summary,
            'by_department' => $byDepartment,
            'trend' => $trend,
            'rows' => $rows,
            'composition' => [
                'labels' => ['Basic', 'Overtime', 'Deductions', 'Loan', 'Advance', 'Tax'],
                'values' => [
                    $summary['basic'],
                    $summary['overtime'],
                    max(0, $summary['deductions'] - $summary['loan'] - $summary['advance'] - $summary['tax']),
                    $summary['loan'],
                    $summary['advance'],
                    $summary['tax'],
                ],
            ],
        ];
    }

    public function loanAnalytics(array $filters): array
    {
        [$loanCompanySql, $loanCompanyParams] = $this->companyFilter($filters, 'company_id', 'loan_report_company');
        $loanSummary = $this->db->fetch(
            'SELECT COUNT(*) AS total,
                    SUM(status="pending") AS pending,
                    COALESCE(SUM(CASE WHEN status IN ("approved","active","completed") THEN principal_amount ELSE 0 END),0) AS approved_amount,
                    COALESCE(SUM(paid_amount),0) AS recovered,
                    COALESCE(SUM(remaining_amount),0) AS outstanding
             FROM loans WHERE deleted_at IS NULL AND (' . $loanCompanySql . ')',
            $loanCompanyParams
        ) ?: [];

        $advanceSummary = $this->db->fetch(
            'SELECT COUNT(*) AS total,
                    SUM(status="pending") AS pending,
                    COALESCE(SUM(amount),0) AS requested,
                    COALESCE(SUM(repaid_amount),0) AS recovered,
                    COALESCE(SUM(remaining_amount),0) AS outstanding
             FROM salary_advances WHERE deleted_at IS NULL AND (' . $loanCompanySql . ')',
            $loanCompanyParams
        ) ?: [];

        $loanStatus = $this->db->fetchAll(
            'SELECT status, COUNT(*) AS total FROM loans WHERE deleted_at IS NULL AND (' . $loanCompanySql . ') GROUP BY status',
            $loanCompanyParams
        );
        $loanTypes = $this->db->fetchAll(
            'SELECT loan_type, COUNT(*) AS total, COALESCE(SUM(principal_amount),0) AS amount
             FROM loans WHERE deleted_at IS NULL AND (' . $loanCompanySql . ') GROUP BY loan_type ORDER BY amount DESC',
            $loanCompanyParams
        );

        $rows = $this->db->fetchAll(
            'SELECT l.*, CONCAT(e.first_name," ",e.last_name) AS employee_name, e.employee_code
             FROM loans l
             INNER JOIN employees e ON e.id = l.employee_id
             WHERE l.deleted_at IS NULL AND (' . str_replace('company_id', 'l.company_id', $loanCompanySql) . ')
             ORDER BY l.created_at DESC
             LIMIT 200',
            $loanCompanyParams
        );

        $overdue = (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM loan_installments li
             INNER JOIN loans l ON l.id = li.loan_id
             WHERE li.status IN ("pending","partial","overdue") AND li.due_date < CURDATE()
               AND (' . str_replace('company_id', 'l.company_id', $loanCompanySql) . ')',
            $loanCompanyParams
        );

        return [
            'loans' => array_map('floatval', $loanSummary) + ['overdue_installments' => $overdue],
            'advances' => array_map('floatval', $advanceSummary),
            'status' => $loanStatus,
            'types' => $loanTypes,
            'rows' => $rows,
        ];
    }

    public function workforceAnalytics(array $filters = []): array
    {
        [$scopeSql, $scopeParams] = $this->companyFilter($filters, 'company_id', 'workforce_company');
        [$aliasedScopeSql, $aliasedScopeParams] = $this->companyFilter($filters, 'e.company_id', 'workforce_alias_company');
        $byStatus = $this->db->fetchAll(
            'SELECT employment_status AS label, COUNT(*) AS total FROM employees
             WHERE deleted_at IS NULL AND (' . $scopeSql . ') GROUP BY employment_status',
            $scopeParams
        );
        $byDepartment = $this->db->fetchAll(
            'SELECT COALESCE(d.name,"Unassigned") AS label, COUNT(*) AS total
             FROM employees e
             LEFT JOIN departments d ON d.id = e.department_id
             WHERE e.deleted_at IS NULL AND (' . $aliasedScopeSql . ')
             GROUP BY d.id, d.name ORDER BY total DESC',
            $aliasedScopeParams
        );
        $byType = $this->db->fetchAll(
            'SELECT employment_type AS label, COUNT(*) AS total
             FROM employees WHERE deleted_at IS NULL AND (' . $scopeSql . ') GROUP BY employment_type',
            $scopeParams
        );
        $byDesignation = $this->db->fetchAll(
            'SELECT COALESCE(des.name,"Unassigned") AS label, COUNT(*) AS total
             FROM employees e
             LEFT JOIN designations des ON des.id = e.designation_id
             WHERE e.deleted_at IS NULL AND (' . $aliasedScopeSql . ')
             GROUP BY des.id, des.name ORDER BY total DESC LIMIT 10',
            $aliasedScopeParams
        );
        $growth = $this->db->fetchAll(
            'SELECT DATE_FORMAT(joining_date, "%Y-%m") AS label, COUNT(*) AS total
             FROM employees
             WHERE deleted_at IS NULL AND joining_date IS NOT NULL AND (' . $scopeSql . ')
             GROUP BY label ORDER BY label DESC LIMIT 12',
            $scopeParams
        );
        $growth = array_reverse($growth);

        return compact('byStatus', 'byDepartment', 'byType', 'byDesignation', 'growth');
    }

    /** Workforce changes, data completeness, and upcoming people events for the selected reporting period. */
    public function workforcePeriodAnalytics(array $filters): array
    {
        [$where, $params] = $this->employeeWhere($filters, 'e', 'workforce_period');
        $changes = $this->db->fetch(
            'SELECT
                SUM(e.joining_date >= :join_from AND e.joining_date <= :join_to) AS new_hires,
                SUM(COALESCE(e.resignation_date, e.last_working_date) >= :exit_from
                    AND COALESCE(e.resignation_date, e.last_working_date) <= :exit_to) AS exits,
                SUM(e.employment_status IN ("active", "probation")) AS active
             FROM employees e WHERE ' . $where,
            array_merge($params, [
                'join_from' => $filters['from'],
                'join_to' => $filters['to'],
                'exit_from' => $filters['from'],
                'exit_to' => $filters['to'],
            ])
        ) ?: [];

        $compliance = $this->db->fetch(
            'SELECT
                SUM(e.national_id IS NULL OR TRIM(e.national_id) = "") AS missing_id,
                SUM(e.date_of_birth IS NULL) AS missing_birth_date,
                SUM(e.company_email IS NULL OR TRIM(e.company_email) = "") AS missing_company_email
             FROM employees e WHERE ' . $where,
            $params
        ) ?: [];

        if ($this->db->tableExists('employment_agreements')) {
            $agreement = $this->db->fetchColumn(
                'SELECT COUNT(*) FROM employees e
                 LEFT JOIN employment_agreements ea ON ea.employee_id = e.id AND ea.status = "accepted"
                 WHERE ' . $where . ' AND ea.id IS NULL',
                $params
            );
            $compliance['pending_agreements'] = (int) $agreement;
        } else {
            $compliance['pending_agreements'] = 0;
        }

        $eventRows = $this->db->fetchAll(
            'SELECT e.id, e.first_name, e.last_name, e.date_of_birth, e.joining_date
             FROM employees e WHERE ' . $where . '
               AND e.employment_status IN ("active", "probation")
               AND (e.date_of_birth IS NOT NULL OR e.joining_date IS NOT NULL)',
            $params
        );
        $events = $this->upcomingPeopleEvents($eventRows);

        return [
            'new_hires' => (int) ($changes['new_hires'] ?? 0),
            'exits' => (int) ($changes['exits'] ?? 0),
            'active' => (int) ($changes['active'] ?? 0),
            'compliance' => array_map('intval', $compliance),
            'events' => $events,
        ];
    }

    public function documentAnalytics(): array
    {
        $scope = $this->tenant->sql('e.company_id', 'document_company');
        $summary = $this->db->fetch(
            'SELECT COUNT(*) AS total,
                    SUM(d.expiry_date IS NOT NULL AND d.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)) AS expiring,
                    SUM(d.expiry_date IS NOT NULL AND d.expiry_date < CURDATE()) AS expired
             FROM employee_documents d INNER JOIN employees e ON e.id = d.employee_id
             WHERE d.deleted_at IS NULL AND (' . $scope['sql'] . ')',
            $scope['params']
        ) ?: [];

        $byType = $this->db->fetchAll(
            'SELECT d.document_type AS label, COUNT(*) AS total
             FROM employee_documents d INNER JOIN employees e ON e.id = d.employee_id
             WHERE d.deleted_at IS NULL AND (' . $scope['sql'] . ')
             GROUP BY d.document_type ORDER BY total DESC',
            $scope['params']
        );

        $rows = $this->db->fetchAll(
            'SELECT d.*, CONCAT(e.first_name," ",e.last_name) AS employee_name, e.employee_code
             FROM employee_documents d
             INNER JOIN employees e ON e.id = d.employee_id
             WHERE d.deleted_at IS NULL AND (' . $scope['sql'] . ')
             ORDER BY d.created_at DESC
             LIMIT 200',
            $scope['params']
        );

        return [
            'summary' => array_map('intval', $summary),
            'by_type' => $byType,
            'rows' => $rows,
        ];
    }

    private function summaryForRange(string $from, string $to, array $filters): array
    {
        $f = $filters;
        $f['from'] = $from;
        $f['to'] = $to;
        [$where, $params] = $this->attendanceWhere($f);

        $att = $this->db->fetch(
            "SELECT
                COUNT(DISTINCT e.id) AS employees_in_scope,
                SUM(a.status IN ('present','remote','manual','late','half_day')) AS present,
                SUM(a.status = 'absent') AS absent,
                SUM(a.status = 'on_leave') AS on_leave,
                SUM(a.status = 'late') AS late,
                SUM(a.status = 'remote' OR a.is_remote = 1) AS remote,
                SUM(a.early_leave_minutes > 0) AS early_departures,
                COALESCE(SUM(a.overtime_minutes),0) AS overtime_minutes
             FROM attendance a
             INNER JOIN employees e ON e.id = a.employee_id
             WHERE {$where}",
            $params
        ) ?: [];

        $employees = (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM employees e WHERE e.deleted_at IS NULL AND ('
            . $this->companyFilter($filters, 'e.company_id', 'summary_employee_company')[0] . ')' .
            ($filters['department_id'] ? ' AND e.department_id = ' . (int) $filters['department_id'] : '') .
            ($filters['branch_id'] ? ' AND e.branch_id = ' . (int) $filters['branch_id'] : '') .
            ($filters['employment_status'] ? ' AND e.employment_status = ' . $this->db->pdo()->quote((string) $filters['employment_status']) : ''),
            $this->companyFilter($filters, 'e.company_id', 'summary_employee_company')[1]
        );

        [$leaveCompanySql, $leaveCompanyParams] = $this->companyFilter($filters, 'company_id', 'summary_leave_company');
        $pendingLeaves = (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM leave_requests WHERE deleted_at IS NULL AND status = "pending"
               AND (' . $leaveCompanySql . ')',
            $leaveCompanyParams
        );

        [$payrollCompanySql, $payrollCompanyParams] = $this->companyFilter($filters, 'pr.company_id', 'summary_payroll_company');
        $payroll = $this->db->fetch(
            'SELECT COALESCE(SUM(pr.gross_earnings),0) AS gross, COALESCE(SUM(pr.total_deductions),0) AS deductions
             FROM payroll_records pr
             INNER JOIN payroll_periods pp ON pp.id = pr.payroll_period_id
             WHERE pr.deleted_at IS NULL AND pp.deleted_at IS NULL
               AND (' . $payrollCompanySql . ')
               AND STR_TO_DATE(CONCAT(pp.period_year,"-",LPAD(pp.period_month,2,"0"),"-01"), "%Y-%m-%d")
                   BETWEEN :from AND :to',
            array_merge(['from' => $from, 'to' => $to], $payrollCompanyParams)
        ) ?: ['gross' => 0, 'deductions' => 0];

        [$financeCompanySql, $financeCompanyParams] = $this->companyFilter($filters, 'company_id', 'summary_finance_company');
        $loansOutstanding = (float) $this->db->fetchColumn(
            'SELECT COALESCE(SUM(remaining_amount),0) FROM loans WHERE deleted_at IS NULL AND status IN ("approved","active")
               AND (' . $financeCompanySql . ')',
            $financeCompanyParams
        );
        $advancesOutstanding = (float) $this->db->fetchColumn(
            'SELECT COALESCE(SUM(remaining_amount),0) FROM salary_advances WHERE deleted_at IS NULL AND status IN ("approved","disbursed","repaying")
               AND (' . $financeCompanySql . ')',
            $financeCompanyParams
        );

        $present = (int) ($att['present'] ?? 0);
        $absent = (int) ($att['absent'] ?? 0);
        $den = max(1, $present + $absent);

        return [
            'total_employees' => $employees,
            'present' => $present,
            'absent' => $absent,
            'on_leave' => (int) ($att['on_leave'] ?? 0),
            'late' => (int) ($att['late'] ?? 0),
            'remote' => (int) ($att['remote'] ?? 0),
            'early_departures' => (int) ($att['early_departures'] ?? 0),
            'overtime_hours' => round(((int) ($att['overtime_minutes'] ?? 0)) / 60, 1),
            'attendance_pct' => round(($present / $den) * 100, 1),
            'payroll_gross' => (float) ($payroll['gross'] ?? 0),
            'payroll_deductions' => (float) ($payroll['deductions'] ?? 0),
            'pending_leaves' => $pendingLeaves,
            'outstanding_loans_advances' => $loansOutstanding + $advancesOutstanding,
        ];
    }

    private function attendanceWhere(array $filters): array
    {
        $clauses = ['a.deleted_at IS NULL', 'a.attendance_date BETWEEN :from AND :to', 'e.deleted_at IS NULL'];
        $params = ['from' => $filters['from'], 'to' => $filters['to']];
        [$companySql, $companyParams] = $this->companyFilter($filters, 'a.company_id', 'attendance_company');
        $clauses[] = $companySql;
        $params = array_merge($params, $companyParams);

        if (!empty($filters['branch_id'])) {
            $clauses[] = 'e.branch_id = :branch_id';
            $params['branch_id'] = $filters['branch_id'];
        }
        if (!empty($filters['department_id'])) {
            $clauses[] = 'e.department_id = :department_id';
            $params['department_id'] = $filters['department_id'];
        }
        if (!empty($filters['employee_id'])) {
            $clauses[] = 'a.employee_id = :employee_id';
            $params['employee_id'] = $filters['employee_id'];
        }
        if (!empty($filters['shift_id'])) {
            $clauses[] = '(a.shift_id = :shift_id OR e.shift_id = :shift_id2)';
            $params['shift_id'] = $filters['shift_id'];
            $params['shift_id2'] = $filters['shift_id'];
        }
        if (!empty($filters['employment_status'])) {
            $clauses[] = 'e.employment_status = :employment_status';
            $params['employment_status'] = $filters['employment_status'];
        }
        if (!empty($filters['status'])) {
            $clauses[] = 'a.status = :att_status';
            $params['att_status'] = $filters['status'];
        }

        return [implode(' AND ', $clauses), $params];
    }

    /** @return array{0:string,1:array<string,int>} */
    private function companyFilter(array $filters, string $column, string $prefix): array
    {
        $companyId = isset($filters['company_id']) && $filters['company_id'] !== null
            ? (int) $filters['company_id']
            : null;
        if ($companyId) {
            return ["{$column} = :{$prefix}", [$prefix => $companyId]];
        }
        $scope = $this->tenant->sql($column, $prefix);
        return [$scope['sql'], $scope['params']];
    }

    private function pctChange(float $previous, float $current): ?float
    {
        if (abs($previous) < 0.00001) {
            return $current > 0 ? 100.0 : ($current < 0 ? -100.0 : 0.0);
        }
        return round((($current - $previous) / abs($previous)) * 100, 1);
    }

    /** @return array{0:string,1:string} */
    private function periodBounds(string $period): array
    {
        $today = new \DateTimeImmutable('today');
        return match ($period) {
            'last_month' => [
                $today->modify('first day of last month')->format('Y-m-d'),
                $today->modify('last day of last month')->format('Y-m-d'),
            ],
            'this_quarter' => $this->quarterBounds($today),
            'last_quarter' => $this->quarterBounds($today->modify('-3 months')),
            'this_year' => [$today->format('Y-01-01'), $today->format('Y-12-31')],
            'last_year' => [$today->modify('-1 year')->format('Y-01-01'), $today->modify('-1 year')->format('Y-12-31')],
            'custom' => [$today->modify('first day of this month')->format('Y-m-d'), $today->format('Y-m-d')],
            default => [$today->modify('first day of this month')->format('Y-m-d'), $today->modify('last day of this month')->format('Y-m-d')],
        };
    }

    /** @return array{0:string,1:string} */
    private function quarterBounds(\DateTimeImmutable $date): array
    {
        $quarterStartMonth = ((int) floor(((int) $date->format('n') - 1) / 3) * 3) + 1;
        $start = $date->setDate((int) $date->format('Y'), $quarterStartMonth, 1);
        return [$start->format('Y-m-d'), $start->modify('+2 months')->modify('last day of this month')->format('Y-m-d')];
    }

    private function periodLabel(string $period, string $from, string $to): string
    {
        return match ($period) {
            'this_month' => date('F Y', strtotime($from)),
            'last_month' => date('F Y', strtotime($from)),
            'this_quarter', 'last_quarter' => 'Q' . (int) ceil((int) date('n', strtotime($from)) / 3) . ' ' . date('Y', strtotime($from)),
            'this_year', 'last_year' => date('Y', strtotime($from)),
            default => date('j M Y', strtotime($from)) . ' - ' . date('j M Y', strtotime($to)),
        };
    }

    /** @return array{0:string,1:array<string,int>} */
    private function employeeWhere(array $filters, string $alias, string $prefix): array
    {
        [$companySql, $params] = $this->companyFilter($filters, $alias . '.company_id', $prefix . '_company');
        $clauses = [$alias . '.deleted_at IS NULL', $companySql];
        foreach (['branch_id', 'department_id'] as $field) {
            if (!empty($filters[$field])) {
                $clauses[] = $alias . '.' . $field . ' = :' . $prefix . '_' . $field;
                $params[$prefix . '_' . $field] = (int) $filters[$field];
            }
        }
        if (!empty($filters['employee_id'])) {
            $clauses[] = $alias . '.id = :' . $prefix . '_employee_id';
            $params[$prefix . '_employee_id'] = (int) $filters['employee_id'];
        }
        return [implode(' AND ', $clauses), $params];
    }

    /** @param list<array<string,mixed>> $employees @return list<array<string,mixed>> */
    private function upcomingPeopleEvents(array $employees): array
    {
        $today = new \DateTimeImmutable('today');
        $limit = $today->modify('+90 days');
        $events = [];
        foreach ($employees as $employee) {
            foreach (['date_of_birth' => 'Birthday', 'joining_date' => 'Work anniversary'] as $field => $type) {
                if (empty($employee[$field])) {
                    continue;
                }
                $source = new \DateTimeImmutable((string) $employee[$field]);
                $event = $source->setDate((int) $today->format('Y'), (int) $source->format('m'), (int) $source->format('d'));
                if ($event < $today) {
                    $event = $event->modify('+1 year');
                }
                if ($event > $limit) {
                    continue;
                }
                $events[] = [
                    'employee_id' => (int) $employee['id'],
                    'employee_name' => trim((string) $employee['first_name'] . ' ' . (string) $employee['last_name']),
                    'type' => $type,
                    'date' => $event->format('Y-m-d'),
                    'days_away' => (int) $today->diff($event)->days,
                ];
            }
        }
        usort($events, static fn (array $a, array $b): int => $a['date'] <=> $b['date']);
        return array_slice($events, 0, 12);
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (int) $value;
    }

    /**
     * Per-employee attendance summary for a date range, used by the monthly
     * attendance report CSV export. Returns one row per employee with counts
     * for each status including remote/WFH days.
     */
    public function attendanceEmployeeSummary(array $filters): array
    {
        [$where, $params] = $this->attendanceWhere($filters);

        return $this->db->fetchAll(
            "SELECT
                e.employee_code,
                CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
                d.name AS department,
                b.name AS branch,
                COUNT(*) AS total_days,
                SUM(a.status IN ('present','manual')) AS present_days,
                SUM(a.status = 'late') AS late_days,
                SUM(a.status = 'half_day') AS half_days,
                SUM(a.status = 'remote' OR a.is_remote = 1) AS remote_days,
                SUM(a.status = 'absent') AS absent_days,
                SUM(a.status = 'on_leave') AS leave_days,
                ROUND(COALESCE(SUM(a.work_minutes), 0) / 60, 1) AS total_work_hours,
                ROUND(COALESCE(SUM(a.overtime_minutes), 0) / 60, 1) AS overtime_hours
             FROM attendance a
             INNER JOIN employees e ON e.id = a.employee_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN branches b ON b.id = e.branch_id
             WHERE {$where}
             GROUP BY e.id, e.employee_code, e.first_name, e.last_name, d.name, b.name
             ORDER BY e.first_name ASC, e.last_name ASC",
            $params
        );
    }
}
