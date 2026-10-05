<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class ProductSetupService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** @return array{company:array,items:list<array>,completed:int,total:int,percent:int,is_complete:bool,dismissed:bool} */
    public function checklist(int $companyId): array
    {
        $company = $this->db->fetch('SELECT * FROM companies WHERE id = :id AND deleted_at IS NULL', ['id' => $companyId]) ?? [];
        $counts = [
            'branch' => (int) $this->db->fetchColumn('SELECT COUNT(*) FROM branches WHERE company_id = :id AND deleted_at IS NULL', ['id' => $companyId]),
            'department' => (int) $this->db->fetchColumn('SELECT COUNT(*) FROM departments WHERE company_id = :id AND deleted_at IS NULL', ['id' => $companyId]),
            'shift' => (int) $this->db->fetchColumn('SELECT COUNT(*) FROM shifts WHERE company_id = :id AND deleted_at IS NULL', ['id' => $companyId]),
            'leave' => (int) $this->db->fetchColumn('SELECT COUNT(*) FROM leave_types WHERE company_id = :id AND deleted_at IS NULL', ['id' => $companyId]),
            'salary' => (int) $this->db->fetchColumn('SELECT COUNT(*) FROM salary_components WHERE company_id = :id AND deleted_at IS NULL', ['id' => $companyId]),
            'employee' => (int) $this->db->fetchColumn('SELECT COUNT(*) FROM employees WHERE company_id = :id AND deleted_at IS NULL', ['id' => $companyId]),
            'admin' => (int) $this->db->fetchColumn('SELECT COUNT(*) FROM user_roles WHERE company_id = :id', ['id' => $companyId]),
        ];
        $profileReady = !empty($company['name']) && !empty($company['code']) && !empty($company['timezone']) && !empty($company['currency']);
        $items = [
            ['key' => 'profile', 'label' => 'Complete company profile', 'description' => 'Confirm code, timezone, currency, address, logo, and contact details.', 'done' => $profileReady, 'href' => '/admin/companies/' . $companyId . '/edit', 'icon' => 'building-2'],
            ['key' => 'branch', 'label' => 'Add your first workplace', 'description' => 'Configure branch location and attendance radius.', 'done' => $counts['branch'] > 0, 'href' => '/admin/branches/create?company_id=' . $companyId, 'icon' => 'map-pin'],
            ['key' => 'department', 'label' => 'Create departments', 'description' => 'Build the reporting structure employees will use.', 'done' => $counts['department'] > 0, 'href' => '/admin/departments/create?company_id=' . $companyId, 'icon' => 'network'],
            ['key' => 'shift', 'label' => 'Configure work shifts', 'description' => 'Set work hours, grace periods, and overtime rules.', 'done' => $counts['shift'] > 0, 'href' => '/admin/shifts/create?company_id=' . $companyId, 'icon' => 'clock-3'],
            ['key' => 'leave', 'label' => 'Configure leave types', 'description' => 'Add annual, sick, casual, and company-specific leave.', 'done' => $counts['leave'] > 0, 'href' => '/admin/leave/types/create?company_id=' . $companyId, 'icon' => 'calendar-days'],
            ['key' => 'salary', 'label' => 'Review payroll configuration', 'description' => 'Add salary components and structures before the first payroll.', 'done' => $counts['salary'] > 0, 'href' => '/admin/salary-structures?company_id=' . $companyId, 'icon' => 'wallet-cards'],
            ['key' => 'admin', 'label' => 'Assign company administrators', 'description' => 'Make sure HR and payroll owners have company-scoped roles.', 'done' => $counts['admin'] > 0, 'href' => '/admin/users/create?company_id=' . $companyId, 'icon' => 'shield-check'],
            ['key' => 'employee', 'label' => 'Add or import employees', 'description' => 'Create one employee or import a validated CSV in bulk.', 'done' => $counts['employee'] > 0, 'href' => '/admin/employees/import?company_id=' . $companyId, 'icon' => 'users'],
        ];
        $completed = count(array_filter($items, static fn (array $item): bool => $item['done']));
        $progress = $this->db->fetch('SELECT * FROM company_setup_progress WHERE company_id = :id', ['id' => $companyId]);
        return [
            'company' => $company,
            'items' => $items,
            'completed' => $completed,
            'total' => count($items),
            'percent' => (int) round(($completed / max(1, count($items))) * 100),
            'is_complete' => $completed === count($items),
            'dismissed' => !empty($progress['dismissed_at']),
        ];
    }
}
