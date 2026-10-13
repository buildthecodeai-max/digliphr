<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class PlannedOffDay extends Model
{
    protected string $table = 'planned_off_days';
    protected array $fillable = [
        'company_id', 'employee_id', 'off_date', 'reason', 'note', 'created_by', 'notified_at',
    ];
    protected bool $softDeletes = true;

    public function forEmployee(int $employeeId, bool $upcomingOnly = false): array
    {
        $clause = $upcomingOnly ? 'AND p.off_date >= CURDATE()' : '';
        return $this->db->fetchAll(
            "SELECT p.*, CONCAT(e.first_name,' ',e.last_name) AS employee_name, e.employee_code
             FROM planned_off_days p
             INNER JOIN employees e ON e.id = p.employee_id
             WHERE p.employee_id = :eid AND p.deleted_at IS NULL {$clause}
             ORDER BY p.off_date ASC",
            ['eid' => $employeeId]
        );
    }

    public function search(int $companyId, array $filters = []): array
    {
        $clauses = ['p.company_id = :cid', 'p.deleted_at IS NULL'];
        $params  = ['cid' => $companyId];

        if (!empty($filters['employee_id'])) {
            $clauses[] = 'p.employee_id = :eid';
            $params['eid'] = (int) $filters['employee_id'];
        }
        if (!empty($filters['date_from'])) {
            $clauses[] = 'p.off_date >= :df';
            $params['df'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $clauses[] = 'p.off_date <= :dt';
            $params['dt'] = $filters['date_to'];
        }

        $where = implode(' AND ', $clauses);
        return $this->db->fetchAll(
            "SELECT p.*, CONCAT(e.first_name,' ',e.last_name) AS employee_name,
                    e.employee_code, d.name AS department_name
             FROM planned_off_days p
             INNER JOIN employees e ON e.id = p.employee_id
             LEFT JOIN departments d ON d.id = e.department_id
             WHERE {$where}
             ORDER BY p.off_date DESC, e.last_name ASC
             LIMIT 300",
            $params
        );
    }
}
