<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\LeaveBalance;
use App\Models\LeaveType;

class LeaveAllocationService
{
    private Database $db;
    private LeaveBalance $balances;
    private LeaveType $leaveTypes;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->balances = new LeaveBalance();
        $this->leaveTypes = new LeaveType();
    }

    /**
     * Allocate yearly leave types for an employee for the given year.
     * Skips types that already have a balance row.
     * Returns count of new balances created.
     */
    public function allocateYearlyForEmployee(int $employeeId, int $year): int
    {
        $employee = $this->db->fetch(
            'SELECT id, company_id FROM employees WHERE id = :id AND deleted_at IS NULL LIMIT 1',
            ['id' => $employeeId]
        );
        if (!$employee) {
            return 0;
        }

        $types = $this->db->fetchAll(
            "SELECT * FROM leave_types
             WHERE company_id = :cid AND is_active = 1 AND deleted_at IS NULL
               AND default_days > 0 AND accrual_type = 'yearly'",
            ['cid' => $employee['company_id']]
        );

        $created = 0;
        foreach ($types as $lt) {
            $existing = $this->balances->findForEmployee($employeeId, (int) $lt['id'], $year);
            if ($existing) {
                continue;
            }
            $this->balances->create([
                'employee_id'      => $employeeId,
                'leave_type_id'    => (int) $lt['id'],
                'year'             => $year,
                'opening_balance'  => (float) $lt['default_days'],
                'accrued'          => 0,
                'used'             => 0,
                'pending'          => 0,
                'carried_forward'  => 0,
                'adjusted'         => 0,
                'encashed'         => 0,
                'closing_balance'  => (float) $lt['default_days'],
            ]);
            $created++;
        }

        return $created;
    }

    /**
     * Allocate all leave types (yearly + monthly) for an employee for the given year.
     * Monthly types get opening_balance = default_days * 12 (full year upfront).
     * Skips existing rows.
     */
    public function allocateAllForEmployee(int $employeeId, int $year): int
    {
        $employee = $this->db->fetch(
            'SELECT id, company_id FROM employees WHERE id = :id AND deleted_at IS NULL LIMIT 1',
            ['id' => $employeeId]
        );
        if (!$employee) {
            return 0;
        }

        $types = $this->db->fetchAll(
            'SELECT * FROM leave_types
             WHERE company_id = :cid AND is_active = 1 AND deleted_at IS NULL AND default_days > 0',
            ['cid' => $employee['company_id']]
        );

        $created = 0;
        foreach ($types as $lt) {
            $existing = $this->balances->findForEmployee($employeeId, (int) $lt['id'], $year);
            if ($existing) {
                continue;
            }
            // Monthly: opening = days * months_remaining_in_year (or full year for past months)
            $days = (float) $lt['default_days'];
            if ($lt['accrual_type'] === 'monthly') {
                $monthsLeft = 12 - (int) date('n') + 1;
                $opening = 0;
                $accrued = round($days * min($monthsLeft, 12), 2);
            } else {
                $opening = $days;
                $accrued = 0;
            }

            $this->balances->create([
                'employee_id'      => $employeeId,
                'leave_type_id'    => (int) $lt['id'],
                'year'             => $year,
                'opening_balance'  => $opening,
                'accrued'          => $accrued,
                'used'             => 0,
                'pending'          => 0,
                'carried_forward'  => 0,
                'adjusted'         => 0,
                'encashed'         => 0,
                'closing_balance'  => round($opening + $accrued, 2),
            ]);
            $created++;
        }

        return $created;
    }

    /**
     * Run monthly accrual for all active employees of a company.
     * Adds default_days to `accrued` for every monthly-type leave.
     * Skips employees who already received accrual this month (notes check).
     * Returns count of rows updated.
     */
    public function runMonthlyAccrual(int $companyId, int $year, int $month): int
    {
        $label = sprintf('accrual:%04d-%02d', $year, $month);

        $types = $this->db->fetchAll(
            "SELECT * FROM leave_types
             WHERE company_id = :cid AND is_active = 1 AND deleted_at IS NULL
               AND default_days > 0 AND accrual_type = 'monthly'",
            ['cid' => $companyId]
        );

        if (empty($types)) {
            return 0;
        }

        $employees = $this->db->fetchAll(
            "SELECT id FROM employees
             WHERE company_id = :cid AND employment_status = 'active' AND deleted_at IS NULL",
            ['cid' => $companyId]
        );

        $updated = 0;
        foreach ($employees as $emp) {
            foreach ($types as $lt) {
                $balance = $this->balances->ensureBalance((int) $emp['id'], (int) $lt['id'], $year);

                // Skip if already accrued this month
                if (str_contains((string) ($balance['notes'] ?? ''), $label)) {
                    continue;
                }

                $newAccrued  = round((float) $balance['accrued'] + (float) $lt['default_days'], 2);
                $newClosing  = round(
                    (float) $balance['opening_balance'] + $newAccrued
                    + (float) $balance['carried_forward'] + (float) $balance['adjusted']
                    - (float) $balance['used'] - (float) $balance['pending'] - (float) $balance['encashed'],
                    2
                );
                $notes = trim(($balance['notes'] ?? '') . ' ' . $label);

                $this->balances->update((int) $balance['id'], [
                    'accrued'         => $newAccrued,
                    'closing_balance' => $newClosing,
                    'notes'           => $notes,
                ]);
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * Bulk-allocate all leave types for ALL active employees of a company for the given year.
     * Skips employees/types that already have a balance row.
     */
    public function allocateAllEmployees(int $companyId, int $year): int
    {
        $employees = $this->db->fetchAll(
            "SELECT id FROM employees
             WHERE company_id = :cid AND employment_status = 'active' AND deleted_at IS NULL",
            ['cid' => $companyId]
        );

        $total = 0;
        foreach ($employees as $emp) {
            $total += $this->allocateAllForEmployee((int) $emp['id'], $year);
        }

        return $total;
    }
}
