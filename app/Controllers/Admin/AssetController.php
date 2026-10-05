<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Services\AuditService;

class AssetController extends Controller
{
    public function index(): void
    {
        $this->authorize('assets.view');
        $scope = $this->tenant->sql('a.company_id', 'asset_company');
        $employeeScope = $this->tenant->sql('company_id', 'asset_employee_company');
        $rows = Database::getInstance()->fetchAll(
            'SELECT a.*, CONCAT(e.first_name," ",e.last_name) AS employee_name, e.employee_code
             FROM employee_assets a
             LEFT JOIN employees e ON e.id = a.employee_id
             WHERE a.deleted_at IS NULL AND (' . $scope['sql'] . ')
             ORDER BY a.id DESC LIMIT 500',
            $scope['params']
        );
        $employees = Database::getInstance()->fetchAll(
            'SELECT id, CONCAT(first_name," ",last_name) AS name, employee_code
             FROM employees WHERE deleted_at IS NULL AND employment_status = "active"
               AND (' . $employeeScope['sql'] . ')
             ORDER BY first_name LIMIT 500',
            $employeeScope['params']
        );
        $this->view('admin/assets/index', [
            'title' => 'Employee Assets',
            'rows' => $rows,
            'employees' => $employees,
        ]);
    }

    public function create(): void
    {
        $this->authorize('assets.manage');
        $this->redirect('/admin/assets');
    }

    public function store(): void
    {
        $this->authorize('assets.manage');
        $data = $this->validate([
            'employee_id' => 'required|exists:employees,id',
            'asset_name' => 'required|min:2|max:200',
            'asset_tag' => 'required|max:100',
            'serial_number' => 'nullable|max:150',
            'asset_type' => 'required|max:100',
            'assigned_date' => 'required|date',
            'condition_on_assign' => 'nullable|in:new,good,fair,poor',
            'notes' => 'nullable|max:1000',
        ]);

        $employee = $this->tenant->employee((int) $data['employee_id']);
        $id = Database::getInstance()->insert('employee_assets', [
            'company_id' => (int) $employee['company_id'],
            'employee_id' => (int) $data['employee_id'],
            'asset_name' => $data['asset_name'],
            'asset_tag' => $data['asset_tag'],
            'serial_number' => $data['serial_number'] ?? null,
            'asset_type' => $data['asset_type'],
            'assigned_date' => $data['assigned_date'],
            'condition_on_assign' => $data['condition_on_assign'] ?? 'good',
            'status' => 'assigned',
            'notes' => $data['notes'] ?? null,
            'assigned_by' => $this->user()['id'] ?? null,
        ]);

        (new AuditService())->log('create', 'employee_assets', $id, null, $data);
        flash('success', 'Asset assigned.');
        $this->redirect('/admin/assets');
    }

    public function update(int $id): void
    {
        $this->authorize('assets.manage');
        $asset = $this->tenant->record('employee_assets', $id);
        $data = $this->validate([
            'status' => 'required|in:assigned,returned,lost,damaged,retired',
            'return_date' => 'nullable|date',
            'condition_on_return' => 'nullable|in:new,good,fair,poor,damaged,lost',
            'notes' => 'nullable|max:1000',
        ]);

        Database::getInstance()->update('employee_assets', [
            'status' => $data['status'],
            'return_date' => $data['return_date'] ?: null,
            'condition_on_return' => $data['condition_on_return'] ?? null,
            'notes' => $data['notes'] ?? null,
            'returned_to' => $this->user()['id'] ?? null,
        ], 'id = :id AND company_id = :company_id', [
            'id' => $id,
            'company_id' => $asset['company_id'],
        ]);

        flash('success', 'Asset updated.');
        $this->redirect('/admin/assets');
    }

    public function destroy(int $id): void
    {
        $this->authorize('assets.manage');
        $db = Database::getInstance();
        $row = $this->tenant->record('employee_assets', $id);
        if (!empty($row['deleted_at'])) {
            throw new \App\Exceptions\HttpException('Asset not found.', 404);
        }
        $db->update('employee_assets', [
            'deleted_at' => date('Y-m-d H:i:s'),
            'status' => 'retired',
        ], 'id = :id AND company_id = :company_id', [
            'id' => $id,
            'company_id' => $row['company_id'],
        ]);
        (new AuditService())->log('delete', 'employee_assets', $id, $row);
        flash('success', 'Asset archived.');
        $this->redirect('/admin/assets');
    }
}
