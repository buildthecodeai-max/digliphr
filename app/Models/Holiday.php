<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Holiday extends Model
{
    protected string $table = 'holidays';
    protected array $fillable = [
        'company_id', 'branch_id', 'name', 'holiday_date', 'end_date', 'type',
        'is_paid', 'is_recurring', 'description', 'created_by', 'deleted_at',
    ];
    protected bool $softDeletes = true;

    public function forCompany(int $companyId, ?int $branchId = null, ?string $from = null, ?string $to = null): array
    {
        $clauses = ['h.deleted_at IS NULL', 'h.company_id = :company_id'];
        $params = ['company_id' => $companyId];

        if ($branchId !== null) {
            $clauses[] = '(h.branch_id IS NULL OR h.branch_id = :branch_id)';
            $params['branch_id'] = $branchId;
        }

        if ($from) {
            $clauses[] = 'h.holiday_date >= :from';
            $params['from'] = $from;
        }
        if ($to) {
            $clauses[] = 'COALESCE(h.end_date, h.holiday_date) <= :to';
            $params['to'] = $to;
        }

        $where = implode(' AND ', $clauses);

        return $this->db->fetchAll(
            "SELECT h.*, b.name AS branch_name
             FROM holidays h
             LEFT JOIN branches b ON b.id = h.branch_id
             WHERE {$where}
             ORDER BY h.holiday_date ASC",
            $params
        );
    }

    public function datesInRange(int $companyId, string $startDate, string $endDate, ?int $branchId = null): array
    {
        $holidays = $this->forCompany($companyId, $branchId, $startDate, $endDate);
        $dates = [];

        foreach ($holidays as $holiday) {
            $from = new \DateTime($holiday['holiday_date']);
            $to = new \DateTime($holiday['end_date'] ?? $holiday['holiday_date']);
            while ($from <= $to) {
                $dates[] = $from->format('Y-m-d');
                $from->modify('+1 day');
            }
        }

        return array_values(array_unique($dates));
    }
}
