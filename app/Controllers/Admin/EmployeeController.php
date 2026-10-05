<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Core\Validator;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Services\AuditService;
use PDOException;

class EmployeeController extends Controller
{
    private Employee $employees;
    private Company $companies;
    private Branch $branches;
    private Department $departments;
    private Designation $designations;

    public function __construct($request, $response)
    {
        parent::__construct($request, $response);
        $this->employees = new Employee();
        $this->companies = new Company();
        $this->branches = new Branch();
        $this->departments = new Department();
        $this->designations = new Designation();
    }

    public function index(): void
    {
        $this->authorize('employees.view');

        $filters = [
            'q' => $this->request->input('q'),
            'department_id' => $this->request->input('department_id'),
            'branch_id' => $this->request->input('branch_id'),
            'employment_status' => $this->request->input('employment_status'),
            'company_id' => $this->tenant->resolveCompanyId($this->request->input('company_id')),
        ];
        $page = max(1, (int) $this->request->input('page', 1));
        $paginator = $this->employees->search(array_filter($filters), $page);
        $companyId = $filters['company_id'];

        $this->view('admin.employees.index', [
            'title' => 'Employees',
            'employees' => $paginator['data'],
            'paginator' => $paginator,
            'filters' => $filters,
            'departments' => $companyId ? $this->departments->where(['company_id' => $companyId], 'name') : $this->departments->all('name'),
            'branches' => $companyId ? $this->branches->where(['company_id' => $companyId], 'name') : $this->branches->all('name'),
            'companies' => $this->tenant->companies(true),
            'savedFilters' => $companyId ? (new \App\Services\SavedFilterService())->forUser((int) $companyId, (int) $this->auth->id(), 'employees') : [],
        ]);
    }

    public function export(): void
    {
        $this->authorize('reports.export');
        $ids = array_values(array_filter(array_map('intval', (array) $this->request->input('ids', []))));
        if ($ids === []) {
            flash('warning', 'Select at least one employee to export.');
            $this->redirect('/admin/employees');
        }
        $scope = $this->tenant->sql('e.company_id', 'employee_export_company');
        $namedIds = [];
        $idPlaceholders = [];
        foreach ($ids as $index => $employeeId) {
            $key = 'employee_id_' . $index;
            $idPlaceholders[] = ':' . $key;
            $namedIds[$key] = $employeeId;
        }
        $rows = Database::getInstance()->fetchAll(
            "SELECT e.employee_code, e.first_name, e.last_name, e.company_email, e.phone,
                    d.name AS department_name, b.name AS branch_name, des.name AS designation_name,
                    e.employment_status, e.joining_date
             FROM employees e
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN branches b ON b.id = e.branch_id
             LEFT JOIN designations des ON des.id = e.designation_id
             WHERE e.deleted_at IS NULL AND e.id IN (" . implode(',', $idPlaceholders) . ")
               AND ({$scope['sql']}) ORDER BY e.first_name, e.last_name",
            array_merge($namedIds, $scope['params'])
        );
        $dir = config('app.paths.exports');
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $filename = 'employees_' . date('Ymd_His') . '.csv';
        $path = rtrim($dir, '/') . '/' . $filename;
        $fp = fopen($path, 'w');
        fputcsv($fp, ['Employee Code', 'Name', 'Company Email', 'Phone', 'Department', 'Branch', 'Designation', 'Status', 'Joining Date']);
        foreach ($rows as $row) {
            fputcsv($fp, [csv_safe($row['employee_code']), csv_safe(trim($row['first_name'] . ' ' . $row['last_name'])), csv_safe($row['company_email']), csv_safe($row['phone']), csv_safe($row['department_name']), csv_safe($row['branch_name']), csv_safe($row['designation_name']), csv_safe($row['employment_status']), csv_safe($row['joining_date'])]);
        }
        fclose($fp);
        $this->response->download($path, $filename, 'text/csv');
    }

    public function create(): void
    {
        $this->authorize('employees.create');
        $this->renderForm('Add Employee');
    }

    public function store(): void
    {
        $this->authorize('employees.create');

        $data = $this->validateEmployeeRules();
        $data['uuid'] = $this->uuid();
        $data['employee_code'] = $data['employee_code'] ?: $this->employees->nextCode('EMP', (int) $data['company_id']);
        $data['remote_attendance_allowed'] = isset($data['remote_attendance_allowed']) ? 1 : 0;

        try {
            $id = $this->employees->create($data);
        } catch (PDOException $e) {
            $this->failOnConstraintViolation($e);
        }

        (new AuditService())->log('create', 'employees', $id, null, $data);
        (new \App\Services\EmploymentAgreementService())->ensureForEmployee($id);
        try {
            (new \App\Services\LeaveAllocationService())->allocateAllForEmployee($id, (int) date('Y'));
        } catch (\Throwable) {
        }

        $created = $this->employees->find($id);
        if ($created && !empty($created['user_id'])) {
            try {
                (new \App\Services\Chat\AutoChannelService())->syncEmployee($created);
            } catch (\Throwable) {
            }
        }

        flash('success', 'Employee created successfully.');
        $this->redirect('/admin/employees/' . $id);
    }

    public function show(int $id): void
    {
        $this->authorize('employees.view');

        $employee = $this->employees->findDetailed($id);
        if (!$employee) {
            throw new \App\Exceptions\HttpException('Employee not found.', 404);
        }
        $this->tenant->assertCompany((int) $employee['company_id']);

        $this->view('admin.employees.show', [
            'title' => $this->employees->fullName($employee),
            'employee' => $employee,
        ]);
    }

    public function edit(int $id): void
    {
        $this->authorize('employees.update');

        $employee = $this->employees->find($id);
        if (!$employee) {
            throw new \App\Exceptions\HttpException('Employee not found.', 404);
        }
        $this->tenant->assertCompany((int) $employee['company_id']);

        $this->renderForm('Edit Employee', $employee);
    }

    public function update(int $id): void
    {
        $this->authorize('employees.update');

        $employee = $this->employees->find($id);
        if (!$employee) {
            throw new \App\Exceptions\HttpException('Employee not found.', 404);
        }
        $this->tenant->assertCompany((int) $employee['company_id']);

        $data = $this->validateEmployeeRules($id);
        $data['remote_attendance_allowed'] = isset($data['remote_attendance_allowed']) ? 1 : 0;

        try {
            $this->employees->update($id, $data);
        } catch (PDOException $e) {
            $this->failOnConstraintViolation($e, $id);
        }

        (new AuditService())->log('update', 'employees', $id, $employee, $data);

        $updated = $this->employees->find($id);
        if ($updated && !empty($updated['user_id'])) {
            try {
                (new \App\Services\Chat\AutoChannelService())->syncEmployee($updated);
            } catch (\Throwable) {
            }
        }

        flash('success', 'Employee updated successfully.');
        $this->redirect('/admin/employees/' . $id);
    }

    public function destroy(int $id): void
    {
        $this->authorize('employees.delete');

        $employee = $this->employees->find($id);
        if (!$employee) {
            throw new \App\Exceptions\HttpException('Employee not found.', 404);
        }
        $this->tenant->assertCompany((int) $employee['company_id']);

        $this->employees->delete($id);
        (new AuditService())->log('delete', 'employees', $id, $employee);

        flash('success', 'Employee deactivated successfully.');
        $this->redirect('/admin/employees');
    }

    public function reactivate(int $id): void
    {
        $this->authorize('employees.update');

        $employee = Database::getInstance()->fetch(
            'SELECT * FROM employees WHERE id = :id',
            ['id' => $id]
        );

        if (!$employee) {
            throw new \App\Exceptions\HttpException('Employee not found.', 404);
        }
        $this->tenant->assertCompany((int) $employee['company_id']);

        $this->employees->restore($id);
        $this->employees->update($id, ['employment_status' => 'active']);
        (new AuditService())->log('reactivate', 'employees', $id);

        flash('success', 'Employee reactivated successfully.');
        $this->redirect('/admin/employees/' . $id);
    }

    public function changeStatus(int $id): void
    {
        $this->authorize('employees.update');

        $employee = $this->employees->find($id);
        if (!$employee) {
            throw new \App\Exceptions\HttpException('Employee not found.', 404);
        }
        $this->tenant->assertCompany((int) $employee['company_id']);

        $data = $this->validate([
            'employment_status' => 'required|in:active,probation,notice_period,suspended,terminated,resigned,retired,inactive',
            'notes' => 'nullable|max:1000',
        ]);

        $previous = $employee['employment_status'];
        $this->employees->update($id, ['employment_status' => $data['employment_status']]);

        Database::getInstance()->insert('employee_status_history', [
            'employee_id' => $id,
            'from_status' => $previous,
            'to_status' => $data['employment_status'],
            'effective_date' => date('Y-m-d'),
            'reason' => $data['notes'] ?? null,
            'notes' => $data['notes'] ?? null,
            'changed_by' => $this->user()['id'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        (new AuditService())->log('status_change', 'employees', $id, ['status' => $previous], $data);
        flash('success', 'Employee status updated.');
        $this->redirect('/admin/employees/' . $id);
    }

    public function createLogin(int $id): void
    {
        $this->authorize('employees.update');

        $employee = $this->employees->find($id);
        if (!$employee) {
            throw new \App\Exceptions\HttpException('Employee not found.', 404);
        }
        $this->tenant->assertCompany((int) $employee['company_id']);

        if (!empty($employee['user_id'])) {
            flash('warning', 'This employee already has a login account.');
            $this->redirect('/admin/employees/' . $id);
        }

        $email = trim((string) ($this->request->input('email') ?: $employee['company_email'] ?: $employee['personal_email']));
        $password = (string) ($this->request->input('password') ?: 'Employee@123');

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'A valid email is required to create login.');
            $this->redirect('/admin/employees/' . $id);
        }

        $db = Database::getInstance();
        $exists = $db->fetch('SELECT id FROM users WHERE email = :email AND deleted_at IS NULL', ['email' => $email]);
        if ($exists) {
            flash('error', 'A user with this email already exists.');
            $this->redirect('/admin/employees/' . $id);
        }

        $userId = $db->insert('users', [
            'uuid' => $this->uuid(),
            'name' => trim($employee['first_name'] . ' ' . $employee['last_name']),
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'is_active' => 1,
            'timezone' => config('app.timezone', 'UTC'),
        ]);

        $role = $db->fetch('SELECT id FROM roles WHERE slug = :slug LIMIT 1', ['slug' => 'employee']);
        if ($role) {
            $db->insert('user_roles', [
                'user_id' => $userId,
                'role_id' => (int) $role['id'],
                'company_id' => $employee['company_id'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $this->employees->update($id, [
            'user_id' => $userId,
            'company_email' => $employee['company_email'] ?: $email,
        ]);

        (new \App\Services\EmploymentAgreementService())->ensureForEmployee($id, $userId);

        (new AuditService())->log('create_login', 'employees', $id, null, ['user_id' => $userId, 'email' => $email]);
        flash('success', 'Login created. Email: ' . $email . ' / Password: ' . $password);
        $this->redirect('/admin/employees/' . $id);
    }

    private function renderForm(string $title, ?array $employee = null): void
    {
        $companyId = $employee
            ? $this->tenant->assertCompany((int) $employee['company_id'])
            : $this->tenant->resolveCompanyId($this->request->input('company_id'));
        $companyWhere = $companyId ? ' AND company_id = :company_id' : '';
        $companyParams = $companyId ? ['company_id' => $companyId] : [];
        $managers = $this->employees->raw(
            'SELECT id, CONCAT(first_name, " ", last_name) AS name FROM employees
             WHERE deleted_at IS NULL AND employment_status IN ("active", "probation")' . $companyWhere . '
             ORDER BY first_name ASC',
            $companyParams
        );

        $shifts = Database::getInstance()->fetchAll(
            'SELECT id, name, code FROM shifts WHERE deleted_at IS NULL AND is_active = 1' . $companyWhere . ' ORDER BY name',
            $companyParams
        );

        $this->view('admin.employees.form', [
            'title' => $title,
            'employee' => $employee,
            'companies' => $this->tenant->companies(true),
            'branches' => $companyId ? $this->branches->where(['company_id' => $companyId], 'name') : $this->branches->all('name'),
            'departments' => $companyId ? $this->departments->where(['company_id' => $companyId], 'name') : $this->departments->all('name'),
            'designations' => $companyId ? $this->designations->where(['company_id' => $companyId], 'name') : $this->designations->all('name'),
            'managers' => $managers,
            'shifts' => $shifts,
        ]);
    }

    private function validateEmployeeRules(?int $exceptId = null): array
    {
        $companyId = (int) $this->request->input('company_id', 0);
        $this->tenant->assertCompany($companyId);
        // UNIQUE is (company_id, employee_code) — scope uniqueness per company.
        $uniqueCode = 'unique:employees,employee_code,' . ($exceptId ?: '') . ',company_id,' . $companyId;

        $rules = [
            'company_id' => 'required|exists:companies,id',
            'branch_id' => 'nullable|exists:branches,id',
            'department_id' => 'nullable|exists:departments,id',
            'designation_id' => 'nullable|exists:designations,id',
            'reporting_manager_id' => 'nullable|exists:employees,id',
            'shift_id' => 'nullable|exists:shifts,id',
            'employee_code' => 'nullable|max:50|' . $uniqueCode,
            'first_name' => 'required|min:2|max:100',
            'last_name' => 'required|min:2|max:100',
            'company_email' => 'nullable|email|max:191',
            'personal_email' => 'nullable|email|max:191',
            'phone' => 'nullable|max:30',
            'gender' => 'nullable|in:male,female,other,prefer_not_to_say',
            'date_of_birth' => 'nullable|date',
            'national_id' => 'nullable|max:100',
            'joining_date' => 'required|date',
            'employment_type' => 'required|in:full_time,part_time,contract,intern,temporary,consultant',
            'employment_status' => 'required|in:active,probation,notice_period,suspended,terminated,resigned,retired,inactive',
            'basic_salary' => 'nullable|numeric|min:0',
            'current_address' => 'nullable|max:1000',
            'remote_attendance_allowed' => 'nullable|boolean',
        ];

        $validator = new Validator($this->request->all(), $rules);
        if (!$validator->passes()) {
            Session::flash('errors', $validator->errors());
            Session::flash('old', $this->request->all());
            $this->redirect($exceptId ? '/admin/employees/' . $exceptId . '/edit' : '/admin/employees/create');
        }

        $data = $validator->validated();
        $this->assertRelationsBelongToCompany($companyId, $data);

        foreach (['branch_id', 'department_id', 'designation_id', 'reporting_manager_id', 'shift_id'] as $nullableId) {
            if (($data[$nullableId] ?? '') === '' || $data[$nullableId] === null) {
                $data[$nullableId] = null;
            } else {
                $data[$nullableId] = (int) $data[$nullableId];
            }
        }

        // Empty optional strings → NULL (avoids UNIQUE '' collisions e.g. national_id).
        foreach (['gender', 'date_of_birth', 'company_email', 'personal_email', 'phone', 'current_address', 'national_id'] as $nullable) {
            if (($data[$nullable] ?? '') === '') {
                $data[$nullable] = null;
            }
        }

        // Keep existing employee_code on update when the form leaves it blank.
        if (($data['employee_code'] ?? '') === '') {
            if ($exceptId) {
                unset($data['employee_code']);
            } else {
                $data['employee_code'] = null;
            }
        }

        if (($data['basic_salary'] ?? '') === '' || $data['basic_salary'] === null) {
            $data['basic_salary'] = 0;
        }

        return $data;
    }

    private function assertRelationsBelongToCompany(int $companyId, array $data): void
    {
        $relations = [
            'branch_id' => ['branches', 'Branch'],
            'department_id' => ['departments', 'Department'],
            'designation_id' => ['designations', 'Designation'],
            'reporting_manager_id' => ['employees', 'Reporting manager'],
            'shift_id' => ['shifts', 'Shift'],
        ];
        foreach ($relations as $field => [$table, $label]) {
            $id = (int) ($data[$field] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $belongs = Database::getInstance()->fetchColumn(
                "SELECT COUNT(*) FROM `{$table}` WHERE id = :id AND company_id = :company_id",
                ['id' => $id, 'company_id' => $companyId]
            );
            if (!(int) $belongs) {
                throw new \App\Exceptions\HttpException("{$label} does not belong to the selected company.", 422);
            }
        }
    }

    /**
     * Turn DB unique/constraint failures into form errors + old input (not HTTP 500).
     */
    private function failOnConstraintViolation(PDOException $e, ?int $employeeId = null): never
    {
        if ((int) ($e->errorInfo[1] ?? 0) !== 1062) {
            throw $e;
        }

        $message = (string) ($e->errorInfo[2] ?? $e->getMessage());
        $errors = ['form' => ['Could not save employee due to a duplicate value.']];

        if (str_contains($message, 'uk_employees_company_code') || str_contains($message, 'employee_code')) {
            $errors = ['employee_code' => ['Employee code has already been taken for this company.']];
        } elseif (str_contains($message, 'uk_employees_company_national_id') || str_contains($message, 'national_id')) {
            $errors = ['national_id' => ['National ID has already been taken for this company.']];
        } elseif (str_contains($message, 'uk_employees_uuid') || str_contains($message, 'uuid')) {
            $errors = ['form' => ['Could not create employee. Please try again.']];
        } elseif (str_contains($message, 'uk_employees_user_id') || str_contains($message, 'user_id')) {
            $errors = ['form' => ['This login account is already linked to another employee.']];
        }

        Session::flash('errors', $errors);
        Session::flash('old', $this->request->all());
        $this->redirect($employeeId ? '/admin/employees/' . $employeeId . '/edit' : '/admin/employees/create');
    }

    private function uuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }
}
