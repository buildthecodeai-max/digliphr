<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class LeaveBalance extends Model
{
    protected string $table = 'employee_leave_balances';
    protected bool $timestamps = true;
    protected array $fillable = [
        'employee_id', 'leave_type_id', 'year', 'opening_balance', 'accrued',
        'used', 'pending', 'carried_forward', 'adjusted', 'encashed',
        'closing_balance', 'notes',
    ];

    public function findForEmployee(int $employeeId, int $leaveTypeId, int $year): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM employee_leave_balances
             WHERE employee_id = :employee_id AND leave_type_id = :leave_type_id AND year = :year
             LIMIT 1',
            [
                'employee_id' => $employeeId,
                'leave_type_id' => $leaveTypeId,
                'year' => $year,
            ]
        );
    }

    public function forEmployee(int $employeeId, ?int $year = null): array
    {
        $year = $year ?? (int) date('Y');

        return $this->db->fetchAll(
            'SELECT elb.*, lt.name AS leave_type_name, lt.code AS leave_type_code, lt.color AS leave_type_color
             FROM employee_leave_balances elb
             INNER JOIN leave_types lt ON lt.id = elb.leave_type_id
             WHERE elb.employee_id = :employee_id AND elb.year = :year
             ORDER BY lt.sort_order ASC, lt.name ASC',
            ['employee_id' => $employeeId, 'year' => $year]
        );
    }

    public function recalculateClosing(int $id): void
    {
        $row = $this->find($id);
        if (!$row) {
            return;
        }

        $closing = (float) $row['opening_balance']
            + (float) $row['accrued']
            + (float) $row['carried_forward']
            + (float) $row['adjusted']
            - (float) $row['used']
            - (float) $row['pending']
            - (float) $row['encashed'];

        $this->update($id, ['closing_balance' => round($closing, 2)]);
    }

    public function ensureBalance(int $employeeId, int $leaveTypeId, int $year): array
    {
        $existing = $this->findForEmployee($employeeId, $leaveTypeId, $year);
        if ($existing) {
            return $existing;
        }

        $id = $this->create([
            'employee_id' => $employeeId,
            'leave_type_id' => $leaveTypeId,
            'year' => $year,
            'opening_balance' => 0,
            'accrued' => 0,
            'used' => 0,
            'pending' => 0,
            'carried_forward' => 0,
            'adjusted' => 0,
            'encashed' => 0,
            'closing_balance' => 0,
        ]);

        return $this->find($id) ?? [];
    }
}
