<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class PayrollPeriod extends Model
{
    protected string $table = 'payroll_periods';
    protected array $fillable = [
        'company_id', 'name', 'code', 'period_year', 'period_month',
        'start_date', 'end_date', 'pay_date', 'status',
        'total_employees', 'total_gross', 'total_deductions', 'total_net',
        'locked_at', 'locked_by', 'approved_at', 'approved_by', 'notes', 'created_by',
        'deleted_at', 'deleted_by', 'deletion_reason',
    ];
    protected bool $softDeletes = true;

    public function findByCompanyMonth(int $companyId, int $year, int $month): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM payroll_periods
             WHERE company_id = :company_id AND period_year = :year AND period_month = :month
             AND deleted_at IS NULL LIMIT 1',
            ['company_id' => $companyId, 'year' => $year, 'month' => $month]
        );
    }

    public function listForCompany(int $companyId, int $page = 1, int $perPage = 15, array $filters = []): array
    {
        if (!empty($filters['archived'])) {
            $clauses = ['pp.deleted_at IS NOT NULL', 'pp.company_id = :company_id'];
        } else {
            $clauses = ['pp.deleted_at IS NULL', 'pp.company_id = :company_id'];
        }
        $params = ['company_id' => $companyId];

        if (!empty($filters['status'])) {
            $clauses[] = 'pp.status = :status';
            $params['status'] = $filters['status'];
        }

        $where = implode(' AND ', $clauses);
        $offset = (max(1, $page) - 1) * $perPage;
        $total = (int) $this->db->fetchColumn("SELECT COUNT(*) FROM payroll_periods pp WHERE {$where}", $params);
        $rows = $this->db->fetchAll(
            "SELECT pp.*, u.name AS archived_by_name
             FROM payroll_periods pp
             LEFT JOIN users u ON u.id = pp.deleted_by
             WHERE {$where}
             ORDER BY pp.period_year DESC, pp.period_month DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [
            'data' => $rows,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => max(1, $page),
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public function findIncludingArchived(int $id): ?array
    {
        return $this->db->fetch(
            'SELECT pp.*, u.name AS archived_by_name
             FROM payroll_periods pp
             LEFT JOIN users u ON u.id = pp.deleted_by
             WHERE pp.id = :id LIMIT 1',
            ['id' => $id]
        );
    }

    public function canProcess(array $period): bool
    {
        return in_array($period['status'], ['draft', 'reopened', 'calculated'], true);
    }

    public function isLocked(array $period): bool
    {
        return in_array($period['status'], ['locked', 'paid'], true);
    }
}
