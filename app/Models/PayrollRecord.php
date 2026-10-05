<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class PayrollRecord extends Model
{
    protected string $table = 'payroll_records';
    protected array $fillable = [
        'uuid', 'payroll_period_id', 'employee_id', 'company_id',
        'basic_salary', 'gross_earnings', 'total_deductions', 'net_salary',
        'working_days', 'present_days', 'absent_days', 'leave_days',
        'holiday_days', 'weekend_days', 'overtime_minutes', 'overtime_amount',
        'loan_deduction', 'advance_deduction', 'tax_amount', 'currency', 'status',
        'payment_method', 'payment_reference', 'paid_at', 'bank_account_id',
        'remarks', 'calculated_at', 'approved_by', 'approved_at', 'created_by', 'deleted_at',
    ];
    protected bool $softDeletes = true;

    public function findByPeriodEmployee(int $periodId, int $employeeId): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM payroll_records
             WHERE payroll_period_id = :period_id AND employee_id = :employee_id AND deleted_at IS NULL
             LIMIT 1',
            ['period_id' => $periodId, 'employee_id' => $employeeId]
        );
    }

    public function listByPeriod(int $periodId): array
    {
        return $this->db->fetchAll(
            'SELECT pr.*, e.employee_code, e.first_name, e.last_name, d.name AS department_name
             FROM payroll_records pr
             INNER JOIN employees e ON e.id = pr.employee_id
             LEFT JOIN departments d ON d.id = e.department_id
             WHERE pr.payroll_period_id = :period_id AND pr.deleted_at IS NULL
             ORDER BY e.first_name, e.last_name',
            ['period_id' => $periodId]
        );
    }

    public function listForEmployee(int $employeeId, int $page = 1, int $perPage = 12): array
    {
        $offset = (max(1, $page) - 1) * $perPage;
        $total = (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM payroll_records WHERE employee_id = :eid AND deleted_at IS NULL',
            ['eid' => $employeeId]
        );
        $rows = $this->db->fetchAll(
            'SELECT pr.*, pp.name AS period_name, pp.period_year, pp.period_month, pp.start_date, pp.end_date
             FROM payroll_records pr
             INNER JOIN payroll_periods pp ON pp.id = pr.payroll_period_id
             WHERE pr.employee_id = :eid AND pr.deleted_at IS NULL
             ORDER BY pp.period_year DESC, pp.period_month DESC
             LIMIT ' . $perPage . ' OFFSET ' . $offset,
            ['eid' => $employeeId]
        );

        return [
            'data' => $rows,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => max(1, $page),
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public function findDetailed(int $id): ?array
    {
        return $this->db->fetch(
            'SELECT pr.*, e.employee_code, e.first_name, e.last_name, e.company_email,
                    pp.name AS period_name, pp.period_year, pp.period_month,
                    pp.start_date AS period_start, pp.end_date AS period_end,
                    c.name AS company_name
             FROM payroll_records pr
             INNER JOIN employees e ON e.id = pr.employee_id
             INNER JOIN payroll_periods pp ON pp.id = pr.payroll_period_id
             INNER JOIN companies c ON c.id = pr.company_id
             WHERE pr.id = :id AND pr.deleted_at IS NULL LIMIT 1',
            ['id' => $id]
        );
    }
}
