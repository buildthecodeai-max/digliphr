<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Exceptions\HttpException;

final class PayrollVarianceService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** @return array{period:array,previous:?array,summary:array,rows:list<array>} */
    public function compare(int $periodId): array
    {
        $period = $this->db->fetch('SELECT * FROM payroll_periods WHERE id = :id AND deleted_at IS NULL', ['id' => $periodId]);
        if (!$period) {
            throw new HttpException('Payroll period not found.', 404);
        }
        $previous = $this->db->fetch(
            'SELECT * FROM payroll_periods
             WHERE company_id = :cid AND deleted_at IS NULL AND id <> :id
               AND (period_year < :year OR (period_year = :year2 AND period_month < :month))
             ORDER BY period_year DESC, period_month DESC LIMIT 1',
            ['cid' => $period['company_id'], 'id' => $periodId, 'year' => $period['period_year'], 'year2' => $period['period_year'], 'month' => $period['period_month']]
        );
        $rows = $this->db->fetchAll(
            'SELECT pr.employee_id, e.employee_code, e.first_name, e.last_name,
                    pr.gross_earnings, pr.total_deductions, pr.net_salary,
                    prev.gross_earnings previous_gross, prev.total_deductions previous_deductions,
                    prev.net_salary previous_net
             FROM payroll_records pr
             INNER JOIN employees e ON e.id = pr.employee_id
             LEFT JOIN payroll_records prev ON prev.employee_id = pr.employee_id AND prev.payroll_period_id = :previous_id
             WHERE pr.payroll_period_id = :period_id AND pr.deleted_at IS NULL
             ORDER BY ABS(pr.net_salary - COALESCE(prev.net_salary, 0)) DESC, e.first_name',
            ['previous_id' => (int) ($previous['id'] ?? 0), 'period_id' => $periodId]
        );
        foreach ($rows as &$row) {
            $row['net_change'] = (float) $row['net_salary'] - (float) ($row['previous_net'] ?? 0);
            $row['net_change_percent'] = (float) ($row['previous_net'] ?? 0) > 0
                ? round(($row['net_change'] / (float) $row['previous_net']) * 100, 2)
                : null;
            $row['is_new'] = $row['previous_net'] === null;
            $row['is_exception'] = $row['is_new'] || abs((float) ($row['net_change_percent'] ?? 0)) >= 10;
        }
        unset($row);
        $previousNet = (float) ($previous['total_net'] ?? 0);
        $netChange = (float) $period['total_net'] - $previousNet;
        return [
            'period' => $period,
            'previous' => $previous,
            'summary' => [
                'net_change' => $netChange,
                'net_change_percent' => $previousNet > 0 ? round(($netChange / $previousNet) * 100, 2) : null,
                'headcount_change' => (int) $period['total_employees'] - (int) ($previous['total_employees'] ?? 0),
                'exceptions' => count(array_filter($rows, static fn (array $row): bool => $row['is_exception'])),
            ],
            'rows' => $rows,
        ];
    }
}
