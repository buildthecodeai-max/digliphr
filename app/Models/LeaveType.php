<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class LeaveType extends Model
{
    protected string $table = 'leave_types';
    protected array $fillable = [
        'company_id', 'name', 'code', 'description', 'is_paid', 'requires_approval',
        'requires_attachment', 'allow_half_day', 'allow_negative_balance',
        'max_days_per_request', 'min_days_per_request', 'max_consecutive_days',
        'notice_days', 'gender_restriction', 'color', 'sort_order', 'is_active',
        'default_days', 'accrual_type', 'deleted_at',
    ];
    protected bool $softDeletes = true;

    public function forCompany(int $companyId): array
    {
        return $this->where(['company_id' => $companyId, 'is_active' => 1], 'sort_order', 'ASC');
    }
}
