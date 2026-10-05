<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Exceptions\HttpException;
use App\Models\Employee;
use App\Models\Shift;
use App\Services\AuditService;

class ShiftController extends Controller
{
    private Shift $shifts;

    public function __construct($request, $response)
    {
        parent::__construct($request, $response);
        $this->shifts = new Shift();
    }

    public function index(): void
    {
        $this->authorize('shifts.view');
        $scope = $this->tenant->sql('s.company_id', 'shift_company');
        $this->view('admin/shifts/index', [
            'title' => 'Shifts',
            'shifts' => $this->shifts->raw(
                'SELECT s.*, c.name AS company_name
                 FROM shifts s
                 LEFT JOIN companies c ON c.id = s.company_id
                 WHERE s.deleted_at IS NULL AND (' . $scope['sql'] . ')
                 ORDER BY s.name ASC',
                $scope['params']
            ),
        ]);
    }

    public function create(): void
    {
        $this->authorize('shifts.create');
        $this->view('admin/shifts/form', [
            'title' => 'Add Shift',
            'shift' => null,
            'companies' => $this->tenant->companies(true),
        ]);
    }

    public function store(): void
    {
        $this->authorize('shifts.create');
        $data = $this->validatedShift();
        $id = $this->shifts->create($data);
        (new AuditService())->log('create', 'shifts', $id, null, $data);
        flash('success', 'Shift created.');
        $this->redirect('/admin/shifts');
    }

    public function edit(int $id): void
    {
        $this->authorize('shifts.update');
        $shift = $this->shifts->find($id);
        if (!$shift) {
            throw new HttpException('Shift not found.', 404);
        }
        $this->tenant->assertCompany((int) $shift['company_id']);
        $employeeScope = $this->tenant->sql('company_id', 'shift_employee_company');
        $this->view('admin/shifts/form', [
            'title' => 'Edit Shift',
            'shift' => $shift,
            'companies' => $this->tenant->companies(true),
            'employees' => (new Employee())->raw(
                'SELECT id, employee_code, CONCAT(first_name, " ", last_name) AS name
                 FROM employees WHERE deleted_at IS NULL AND employment_status = "active"
                   AND (' . $employeeScope['sql'] . ')
                 ORDER BY first_name LIMIT 500',
                $employeeScope['params']
            ),
        ]);
    }

    public function update(int $id): void
    {
        $this->authorize('shifts.update');
        $shift = $this->shifts->find($id);
        if (!$shift) {
            throw new HttpException('Shift not found.', 404);
        }
        $this->tenant->assertCompany((int) $shift['company_id']);
        $data = $this->validatedShift();
        $this->shifts->update($id, $data);
        (new AuditService())->log('update', 'shifts', $id, $shift, $data);
        flash('success', 'Shift updated.');
        $this->redirect('/admin/shifts');
    }

    public function destroy(int $id): void
    {
        $this->authorize('shifts.delete');
        $shift = $this->shifts->find($id);
        if (!$shift) {
            throw new HttpException('Shift not found.', 404);
        }
        $this->tenant->assertCompany((int) $shift['company_id']);
        $this->shifts->delete($id);
        (new AuditService())->log('delete', 'shifts', $id);
        flash('success', 'Shift deleted.');
        $this->redirect('/admin/shifts');
    }

    public function assign(int $id): void
    {
        $this->authorize('shifts.assign');
        $shift = $this->shifts->find($id);
        if (!$shift) {
            throw new HttpException('Shift not found.', 404);
        }
        $this->tenant->assertCompany((int) $shift['company_id']);

        $data = $this->validate([
            'employee_id' => 'required|exists:employees,id',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date',
        ]);
        $employee = $this->tenant->employee((int) $data['employee_id']);
        if ((int) $employee['company_id'] !== (int) $shift['company_id']) {
            throw new HttpException('Employee and shift must belong to the same company.', 422);
        }

        $this->shifts->db()->insert('shift_assignments', [
            'employee_id' => (int) $data['employee_id'],
            'shift_id' => $id,
            'effective_from' => $data['effective_from'],
            'effective_to' => $data['effective_to'] ?: null,
            'is_primary' => 1,
            'assigned_by' => $this->user()['id'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->tenant->assertCompany((int) $data['company_id']);

        // Also update employee's default shift
        (new Employee())->update((int) $data['employee_id'], ['shift_id' => $id]);
        (new AuditService())->log('assign', 'shifts', $id, null, $data);

        flash('success', 'Shift assigned to employee.');
        $this->redirect('/admin/shifts/' . $id . '/edit');
    }

    private function validatedShift(): array
    {
        $data = $this->validate([
            'company_id' => 'required|exists:companies,id',
            'name' => 'required|min:2|max:150',
            'code' => 'nullable|max:50',
            'start_time' => 'required',
            'end_time' => 'required',
            'break_minutes' => 'nullable|integer|min:0',
            'grace_minutes' => 'nullable|integer|min:0',
            'late_mark_after_minutes' => 'nullable|integer|min:0',
            'half_day_after_minutes' => 'nullable|integer|min:0',
            'early_leave_grace_minutes' => 'nullable|integer|min:0',
            'overtime_after_minutes' => 'nullable|integer|min:0',
            'expected_work_minutes' => 'nullable|integer|min:0',
            'description' => 'nullable|max:1000',
        ]);

        $data['break_minutes'] = (int) ($data['break_minutes'] ?? 0);
        $data['grace_minutes'] = (int) ($data['grace_minutes'] ?? 0);
        $data['late_mark_after_minutes'] = (int) ($data['late_mark_after_minutes'] ?? 15);
        $data['early_leave_grace_minutes'] = (int) ($data['early_leave_grace_minutes'] ?? 0);
        $data['overtime_after_minutes'] = (int) ($data['overtime_after_minutes'] ?? 0);
        $data['half_day_after_minutes'] = ($data['half_day_after_minutes'] ?? '') === '' || $data['half_day_after_minutes'] === null
            ? null
            : (int) $data['half_day_after_minutes'];
        $data['expected_work_minutes'] = ($data['expected_work_minutes'] ?? '') === '' || $data['expected_work_minutes'] === null
            ? null
            : (int) $data['expected_work_minutes'];
        $data['is_overnight'] = $this->request->input('is_overnight') ? 1 : 0;
        $data['is_flexible'] = $this->request->input('is_flexible') ? 1 : 0;
        $data['is_active'] = $this->request->input('is_active') ? 1 : 0;
        $data['color'] = $this->request->input('color') ?: '#2563eb';
        $data['code'] = ($data['code'] ?? '') !== '' ? $data['code'] : null;
        $data['description'] = ($data['description'] ?? '') !== '' ? $data['description'] : null;

        return $data;
    }
}
