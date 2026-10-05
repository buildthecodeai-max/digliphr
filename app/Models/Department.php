<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Department extends Model
{
    protected string $table = 'departments';
    protected array $fillable = [
        'company_id', 'branch_id', 'name', 'code', 'head_employee_id',
        'description', 'week_pattern_id', 'is_active', 'deleted_at',
    ];
    protected bool $softDeletes = true;

    public function withRelations(?int $companyId = null): array
    {
        $where = $companyId ? ' AND d.company_id = :company_id' : '';
        return $this->db->fetchAll(
            'SELECT d.*, b.name AS branch_name,
                    CONCAT(e.first_name, " ", e.last_name) AS head_name,
                    wp.name AS week_pattern_name
             FROM departments d
             LEFT JOIN branches b ON b.id = d.branch_id
             LEFT JOIN employees e ON e.id = d.head_employee_id
             LEFT JOIN attendance_week_patterns wp ON wp.id = d.week_pattern_id AND wp.deleted_at IS NULL
             WHERE d.deleted_at IS NULL' . $where . '
             ORDER BY d.name ASC',
            $companyId ? ['company_id' => $companyId] : []
        );
    }
}
