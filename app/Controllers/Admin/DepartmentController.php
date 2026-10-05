<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\AttendanceWeekPattern;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Services\AuditService;
use PDOException;

class DepartmentController extends Controller
{
    private Department $departments;
    private Company $companies;
    private Branch $branches;
    private Employee $employees;
    private AttendanceWeekPattern $weekPatterns;

    public function __construct($request, $response)
    {
        parent::__construct($request, $response);
        $this->departments = new Department();
        $this->companies = new Company();
        $this->branches = new Branch();
        $this->employees = new Employee();
        $this->weekPatterns = new AttendanceWeekPattern();
    }

    public function index(): void
    {
        $this->authorize('departments.view');
        $companyId = $this->tenant->resolveCompanyId($this->request->input('company_id'));

        $this->view('admin.departments.index', [
            'title' => 'Departments',
            'departments' => $this->departments->withRelations($companyId),
            'companies' => $this->tenant->companies(),
        ]);
    }

    public function create(): void
    {
        $this->authorize('departments.create');
        $companyId = $this->tenant->resolveCompanyId($this->request->input('company_id'));

        $this->view('admin.departments.form', [
            'title' => 'Add Department',
            'department' => null,
            'companies' => $this->tenant->companies(true),
            'branches' => $companyId ? $this->branches->where(['company_id' => $companyId], 'name') : $this->branches->all('name'),
            'employees' => $companyId ? $this->employees->where(['company_id' => $companyId], 'first_name') : $this->employees->all('first_name'),
            'weekPatterns' => $companyId ? $this->weekPatterns->forCompany($companyId) : [],
        ]);
    }

    public function store(): void
    {
        $this->authorize('departments.create');

        $companyId = (int) $this->request->input('company_id', 0);
        $this->tenant->assertCompany($companyId);
        $data = $this->validate([
            'company_id' => 'required|exists:companies,id',
            'branch_id' => 'nullable|exists:branches,id',
            'name' => 'required|min:2|max:150',
            'code' => 'nullable|max:50|unique:departments,code,,company_id,' . $companyId,
            'description' => 'nullable|max:1000',
            'head_employee_id' => 'nullable|exists:employees,id',
            'week_pattern_id' => 'nullable|exists:attendance_week_patterns,id',
            'is_active' => 'nullable|boolean',
        ]);

        $data = $this->normalizeDepartmentPayload($data);

        try {
            $id = $this->departments->create($data);
        } catch (PDOException $e) {
            $this->failOnDuplicateCode($e);
        }

        (new AuditService())->log('create', 'departments', $id, null, $data);

        flash('success', 'Department created successfully.');
        $this->redirect('/admin/departments');
    }

    public function edit(int $id): void
    {
        $this->authorize('departments.update');

        $department = $this->departments->find($id);
        if (!$department) {
            throw new \App\Exceptions\HttpException('Department not found.', 404);
        }
        $this->tenant->assertCompany((int) $department['company_id']);

        $this->view('admin.departments.form', [
            'title' => 'Edit Department',
            'department' => $department,
            'companies' => $this->tenant->companies(true),
            'branches' => $this->branches->where(['company_id' => (int) $department['company_id']], 'name'),
            'employees' => $this->employees->where(['company_id' => (int) $department['company_id']], 'first_name'),
            'weekPatterns' => $this->weekPatterns->forCompany((int) $department['company_id']),
        ]);
    }

    public function update(int $id): void
    {
        $this->authorize('departments.update');

        $department = $this->departments->find($id);
        if (!$department) {
            throw new \App\Exceptions\HttpException('Department not found.', 404);
        }

        $companyId = (int) $this->request->input('company_id', 0);
        $this->tenant->assertCompany((int) $department['company_id']);
        $this->tenant->assertCompany($companyId);
        $data = $this->validate([
            'company_id' => 'required|exists:companies,id',
            'branch_id' => 'nullable|exists:branches,id',
            'name' => 'required|min:2|max:150',
            'code' => 'nullable|max:50|unique:departments,code,' . $id . ',company_id,' . $companyId,
            'description' => 'nullable|max:1000',
            'head_employee_id' => 'nullable|exists:employees,id',
            'week_pattern_id' => 'nullable|exists:attendance_week_patterns,id',
            'is_active' => 'nullable|boolean',
        ]);

        $data = $this->normalizeDepartmentPayload($data);

        try {
            $this->departments->update($id, $data);
        } catch (PDOException $e) {
            $this->failOnDuplicateCode($e);
        }

        (new AuditService())->log('update', 'departments', $id, $department, $data);

        flash('success', 'Department updated successfully.');
        $this->redirect('/admin/departments');
    }

    public function destroy(int $id): void
    {
        $this->authorize('departments.delete');

        $department = $this->departments->find($id);
        if (!$department) {
            throw new \App\Exceptions\HttpException('Department not found.', 404);
        }
        $this->tenant->assertCompany((int) $department['company_id']);

        $this->departments->delete($id);
        (new AuditService())->log('delete', 'departments', $id, $department);

        flash('success', 'Department deleted successfully.');
        $this->redirect('/admin/departments');
    }

    /**
     * Normalize checkboxes / empty optionals.
     * Empty code must be null so UNIQUE(company_id, code) allows multiple blank codes.
     */
    private function normalizeDepartmentPayload(array $data): array
    {
        $data['is_active'] = isset($data['is_active']) ? 1 : 0;
        $data['branch_id'] = !empty($data['branch_id']) ? (int) $data['branch_id'] : null;
        $data['head_employee_id'] = !empty($data['head_employee_id']) ? (int) $data['head_employee_id'] : null;
        $data['week_pattern_id'] = !empty($data['week_pattern_id']) ? (int) $data['week_pattern_id'] : null;
        $data['code'] = ($data['code'] ?? '') !== '' ? trim((string) $data['code']) : null;
        $data['description'] = ($data['description'] ?? '') !== '' ? $data['description'] : null;

        return $data;
    }

    private function failOnDuplicateCode(PDOException $e): never
    {
        if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
            \App\Core\Session::flash('errors', ['code' => ['Code has already been taken for this company.']]);
            \App\Core\Session::flash('old', $this->request->all());
            $this->back();
        }

        throw $e;
    }
}
