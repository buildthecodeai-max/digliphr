<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Designation extends Model
{
    protected string $table = 'designations';
    protected array $fillable = [
        'company_id', 'department_id', 'name', 'code', 'description', 'level',
        'is_manager_or_above', 'is_active', 'deleted_at',
    ];
    protected bool $softDeletes = true;
}
