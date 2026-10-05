<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Company;
use App\Models\Department;
use App\Models\Designation;
use App\Services\AuditService;
use PDOException;

class DesignationController extends Controller
{
    private Designation $designations;
    private Company $companies;
    private Department $departments;

    public function __construct($request, $response)
    {
        parent::__construct($request, $response);
        $this->designations = new Designation();
        $this->companies = new Company();
        $this->departments = new Department();
    }

    public function index(): void
    {
        $this->authorize('designations.view');
        $companyId = $this->tenant->resolveCompanyId($this->request->input('company_id'));
        $companyWhere = $companyId ? ' AND des.company_id = :company_id' : '';

        $rows = $this->designations->raw(
            'SELECT des.*, c.name AS company_name, d.name AS department_name
             FROM designations des
             LEFT JOIN companies c ON c.id = des.company_id
             LEFT JOIN departments d ON d.id = des.department_id
             WHERE des.deleted_at IS NULL' . $companyWhere . '
             ORDER BY des.name ASC',
            $companyId ? ['company_id' => $companyId] : []
        );

        $this->view('admin.designations.index', [
            'title' => 'Designations',
            'designations' => $rows,
        ]);
    }

    public function create(): void
    {
        $this->authorize('designations.create');
        $companyId = $this->tenant->resolveCompanyId($this->request->input('company_id'));

        $this->view('admin.designations.form', [
            'title' => 'Add Designation',
            'designation' => null,
            'companies' => $this->tenant->companies(true),
            'departments' => $companyId ? $this->departments->where(['company_id' => $companyId], 'name') : $this->departments->all('name'),
        ]);
    }

    public function store(): void
    {
        $this->authorize('designations.create');

        $companyId = (int) $this->request->input('company_id', 0);
        $this->tenant->assertCompany($companyId);
        $data = $this->validate([
            'company_id' => 'required|exists:companies,id',
            'department_id' => 'nullable|exists:departments,id',
            'name' => 'required|min:2|max:150',
            'code' => 'nullable|max:50|unique:designations,code,,company_id,' . $companyId,
            'description' => 'nullable|max:1000',
            'level' => 'nullable|integer',
            'is_manager_or_above' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $data = $this->normalizeDesignationPayload($data);

        try {
            $id = $this->designations->create($data);
        } catch (PDOException $e) {
            $this->failOnDuplicateCode($e);
        }

        (new AuditService())->log('create', 'designations', $id, null, $data);

        flash('success', 'Designation created successfully.');
        $this->redirect('/admin/designations');
    }

    public function edit(int $id): void
    {
        $this->authorize('designations.update');

        $designation = $this->designations->find($id);
        if (!$designation) {
            throw new \App\Exceptions\HttpException('Designation not found.', 404);
        }
        $this->tenant->assertCompany((int) $designation['company_id']);

        $this->view('admin.designations.form', [
            'title' => 'Edit Designation',
            'designation' => $designation,
            'companies' => $this->tenant->companies(true),
            'departments' => $this->departments->where(['company_id' => (int) $designation['company_id']], 'name'),
        ]);
    }

    public function update(int $id): void
    {
        $this->authorize('designations.update');

        $designation = $this->designations->find($id);
        if (!$designation) {
            throw new \App\Exceptions\HttpException('Designation not found.', 404);
        }

        $companyId = (int) $this->request->input('company_id', 0);
        $this->tenant->assertCompany((int) $designation['company_id']);
        $this->tenant->assertCompany($companyId);
        $data = $this->validate([
            'company_id' => 'required|exists:companies,id',
            'department_id' => 'nullable|exists:departments,id',
            'name' => 'required|min:2|max:150',
            'code' => 'nullable|max:50|unique:designations,code,' . $id . ',company_id,' . $companyId,
            'description' => 'nullable|max:1000',
            'level' => 'nullable|integer',
            'is_manager_or_above' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $data = $this->normalizeDesignationPayload($data);

        try {
            $this->designations->update($id, $data);
        } catch (PDOException $e) {
            $this->failOnDuplicateCode($e);
        }

        (new AuditService())->log('update', 'designations', $id, $designation, $data);

        flash('success', 'Designation updated successfully.');
        $this->redirect('/admin/designations');
    }

    public function destroy(int $id): void
    {
        $this->authorize('designations.delete');

        $designation = $this->designations->find($id);
        if (!$designation) {
            throw new \App\Exceptions\HttpException('Designation not found.', 404);
        }
        $this->tenant->assertCompany((int) $designation['company_id']);

        $this->designations->delete($id);
        (new AuditService())->log('delete', 'designations', $id, $designation);

        flash('success', 'Designation deleted successfully.');
        $this->redirect('/admin/designations');
    }

    /**
     * Empty code must be null so UNIQUE(company_id, code) allows multiple blank codes.
     */
    private function normalizeDesignationPayload(array $data): array
    {
        $data['is_active'] = isset($data['is_active']) ? 1 : 0;
        $data['is_manager_or_above'] = isset($data['is_manager_or_above']) ? 1 : 0;
        $data['department_id'] = !empty($data['department_id']) ? (int) $data['department_id'] : null;
        $data['level'] = ($data['level'] ?? '') !== '' && $data['level'] !== null ? (int) $data['level'] : null;
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
