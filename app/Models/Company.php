<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Company extends Model
{
    protected string $table = 'companies';
    protected array $fillable = [
        'uuid', 'name', 'code', 'legal_name', 'logo', 'email', 'phone', 'website',
        'tax_number', 'registration_number', 'address_line1', 'address_line2',
        'city', 'state', 'postal_code', 'country', 'timezone', 'currency',
        'date_format', 'time_format', 'fiscal_year_start_month', 'is_active',
        'settings', 'deleted_at',
    ];
    protected bool $softDeletes = true;
}
