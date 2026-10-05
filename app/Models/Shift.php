<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Shift extends Model
{
    protected string $table = 'shifts';
    protected array $fillable = [
        'company_id', 'name', 'code', 'start_time', 'end_time', 'break_minutes',
        'grace_minutes', 'late_mark_after_minutes', 'half_day_after_minutes',
        'early_leave_grace_minutes', 'overtime_after_minutes', 'expected_work_minutes',
        'is_overnight', 'is_flexible', 'color', 'description', 'is_active', 'deleted_at',
    ];
    protected bool $softDeletes = true;

    public function forCompany(int $companyId, bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM shifts WHERE company_id = :company_id AND deleted_at IS NULL';
        $params = ['company_id' => $companyId];

        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }

        $sql .= ' ORDER BY name ASC';

        return $this->db->fetchAll($sql, $params);
    }

    public function activeForCompany(int $companyId): array
    {
        return $this->forCompany($companyId, true);
    }

    public function findByCode(int $companyId, string $code): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM shifts
             WHERE company_id = :company_id AND code = :code AND deleted_at IS NULL
             LIMIT 1',
            ['company_id' => $companyId, 'code' => $code]
        );
    }

    public function findForEmployee(int $employeeId, ?string $date = null): ?array
    {
        $date = $date ?? date('Y-m-d');

        $roster = $this->db->fetch(
            'SELECT s.* FROM shift_rosters sr
             INNER JOIN shifts s ON s.id = sr.shift_id AND s.deleted_at IS NULL
             WHERE sr.employee_id = ? AND sr.roster_date = ? AND sr.deleted_at IS NULL
             LIMIT 1',
            [$employeeId, $date]
        );

        if ($roster) {
            return $roster;
        }

        $assignment = $this->db->fetch(
            'SELECT s.* FROM shift_assignments sa
             INNER JOIN shifts s ON s.id = sa.shift_id AND s.deleted_at IS NULL
             WHERE sa.employee_id = ?
               AND sa.effective_from <= ?
               AND (sa.effective_to IS NULL OR sa.effective_to >= ?)
               AND sa.deleted_at IS NULL
             ORDER BY sa.effective_from DESC
             LIMIT 1',
            [$employeeId, $date, $date]
        );

        if ($assignment) {
            return $assignment;
        }

        $employee = $this->db->fetch(
            'SELECT shift_id FROM employees WHERE id = ? AND deleted_at IS NULL LIMIT 1',
            [$employeeId]
        );

        if (!$employee || empty($employee['shift_id'])) {
            return null;
        }

        return $this->find((int) $employee['shift_id']);
    }
}
