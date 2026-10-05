<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Branch extends Model
{
    protected string $table = 'branches';
    protected array $fillable = [
        'company_id', 'name', 'code', 'address', 'city', 'state', 'postal_code', 'country',
        'latitude', 'longitude', 'attendance_radius', 'timezone',
        'contact_person', 'contact_phone', 'contact_email', 'is_head_office',
        'is_active', 'deleted_at',
    ];
    protected bool $softDeletes = true;

    public function withCompany(?int $companyId = null): array
    {
        $where = $companyId ? ' AND b.company_id = :company_id' : '';
        return $this->db->fetchAll(
            'SELECT b.*, c.name AS company_name
             FROM branches b
             LEFT JOIN companies c ON c.id = b.company_id
             WHERE b.deleted_at IS NULL' . $where . '
             ORDER BY b.name ASC',
            $companyId ? ['company_id' => $companyId] : []
        );
    }
}
