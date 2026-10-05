<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class AttendanceWeekPattern extends Model
{
    protected string $table = 'attendance_week_patterns';
    protected array $fillable = [
        'company_id', 'name', 'is_default', 'is_manager_pattern',
        'monday_type', 'tuesday_type', 'wednesday_type', 'thursday_type',
        'friday_type', 'saturday_type', 'sunday_type', 'created_by', 'deleted_at',
    ];
    protected bool $softDeletes = true;

    public function forCompany(int $companyId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM attendance_week_patterns WHERE company_id = :cid AND deleted_at IS NULL ORDER BY is_default DESC, name ASC',
            ['cid' => $companyId]
        );
    }

    /**
     * Exactly one default and one manager pattern per company — clearing any
     * previous holder of that flag when a new one is set, so
     * resolveWeekPattern()'s "LIMIT 1" lookups never see two candidates.
     */
    public function setDefault(int $id, int $companyId): void
    {
        $this->db->update('attendance_week_patterns', ['is_default' => 0], 'company_id = :cid', ['cid' => $companyId]);
        $this->db->update('attendance_week_patterns', ['is_default' => 1], 'id = :id', ['id' => $id]);
    }

    public function setManagerPattern(int $id, int $companyId): void
    {
        $this->db->update('attendance_week_patterns', ['is_manager_pattern' => 0], 'company_id = :cid', ['cid' => $companyId]);
        $this->db->update('attendance_week_patterns', ['is_manager_pattern' => 1], 'id = :id', ['id' => $id]);
    }
}
