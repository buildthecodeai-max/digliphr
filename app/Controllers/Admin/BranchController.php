<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Branch;
use App\Models\Company;
use App\Services\AuditService;
use PDOException;

class BranchController extends Controller
{
    private Branch $branches;
    private Company $companies;

    public function __construct($request, $response)
    {
        parent::__construct($request, $response);
        $this->branches = new Branch();
        $this->companies = new Company();
    }

    public function index(): void
    {
        $this->authorize('branches.view');

        $page = max(1, (int) $this->request->input('page', 1));
        $companyId = $this->tenant->resolveCompanyId($this->request->input('company_id'));
        $conditions = $companyId ? ['company_id' => $companyId] : [];

        $paginator = $this->branches->paginate($page, 15, $conditions, 'name', 'ASC');
        $rows = $this->branches->withCompany($companyId);

        $this->view('admin.branches.index', [
            'title' => 'Branches',
            'branches' => $rows,
            'paginator' => $paginator,
            'companies' => $this->tenant->companies(),
            'filters' => ['company_id' => $companyId],
        ]);
    }

    public function create(): void
    {
        $this->authorize('branches.create');

        $this->view('admin.branches.form', [
            'title' => 'Add Branch',
            'branch' => null,
            'companies' => $this->tenant->companies(true),
        ]);
    }

    public function store(): void
    {
        $this->authorize('branches.create');

        $companyId = (int) $this->request->input('company_id', 0);
        $this->tenant->assertCompany($companyId);
        $data = $this->validate([
            'company_id' => 'required|exists:companies,id',
            'name' => 'required|min:2|max:150',
            'code' => 'nullable|max:50|unique:branches,code,,company_id,' . $companyId,
            'address' => 'nullable|max:500',
            'city' => 'nullable|max:100',
            'state' => 'nullable|max:100',
            'postal_code' => 'nullable|max:30',
            'country' => 'nullable|max:100',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'attendance_radius' => 'nullable|numeric|min:10',
            'timezone' => 'nullable|max:64',
            'contact_person' => 'nullable|max:150',
            'contact_phone' => 'nullable|max:30',
            'contact_email' => 'nullable|email|max:191',
            'is_head_office' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $data = $this->normalizeBranchPayload($data);

        try {
            $id = $this->branches->create($data);
        } catch (PDOException $e) {
            $this->failOnDuplicateCode($e);
        }

        (new AuditService())->log('create', 'branches', $id, null, $data);

        flash('success', 'Branch created successfully.');
        $this->redirect('/admin/branches');
    }

    public function edit(int $id): void
    {
        $this->authorize('branches.update');

        $branch = $this->branches->find($id);
        if (!$branch) {
            throw new \App\Exceptions\HttpException('Branch not found.', 404);
        }
        $this->tenant->assertCompany((int) $branch['company_id']);

        $this->view('admin.branches.form', [
            'title' => 'Edit Branch',
            'branch' => $branch,
            'companies' => $this->tenant->companies(true),
        ]);
    }

    public function update(int $id): void
    {
        $this->authorize('branches.update');

        $branch = $this->branches->find($id);
        if (!$branch) {
            throw new \App\Exceptions\HttpException('Branch not found.', 404);
        }

        $companyId = (int) $this->request->input('company_id', 0);
        $this->tenant->assertCompany((int) $branch['company_id']);
        $this->tenant->assertCompany($companyId);
        $data = $this->validate([
            'company_id' => 'required|exists:companies,id',
            'name' => 'required|min:2|max:150',
            'code' => 'nullable|max:50|unique:branches,code,' . $id . ',company_id,' . $companyId,
            'address' => 'nullable|max:500',
            'city' => 'nullable|max:100',
            'state' => 'nullable|max:100',
            'postal_code' => 'nullable|max:30',
            'country' => 'nullable|max:100',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'attendance_radius' => 'nullable|numeric|min:10',
            'timezone' => 'nullable|max:64',
            'contact_person' => 'nullable|max:150',
            'contact_phone' => 'nullable|max:30',
            'contact_email' => 'nullable|email|max:191',
            'is_head_office' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $data = $this->normalizeBranchPayload($data);

        try {
            $this->branches->update($id, $data);
        } catch (PDOException $e) {
            $this->failOnDuplicateCode($e);
        }

        (new AuditService())->log('update', 'branches', $id, $branch, $data);

        flash('success', 'Branch updated successfully.');
        $this->redirect('/admin/branches');
    }

    public function destroy(int $id): void
    {
        $this->authorize('branches.delete');

        $branch = $this->branches->find($id);
        if (!$branch) {
            throw new \App\Exceptions\HttpException('Branch not found.', 404);
        }
        $this->tenant->assertCompany((int) $branch['company_id']);

        $this->branches->delete($id);
        (new AuditService())->log('delete', 'branches', $id, $branch);

        flash('success', 'Branch deleted successfully.');
        $this->redirect('/admin/branches');
    }

    /**
     * Normalize checkboxes, empty optional strings → null, and defaults.
     * Empty code must be null so UNIQUE(company_id, code) allows multiple blank codes.
     */
    private function normalizeBranchPayload(array $data): array
    {
        $data['is_active'] = isset($data['is_active']) ? 1 : 0;
        $data['is_head_office'] = isset($data['is_head_office']) ? 1 : 0;

        foreach (['code', 'address', 'city', 'state', 'postal_code', 'country', 'latitude', 'longitude', 'contact_person', 'contact_phone', 'contact_email'] as $field) {
            if (!array_key_exists($field, $data) || $data[$field] === '') {
                $data[$field] = null;
            }
        }

        if ($data['code'] !== null) {
            $data['code'] = trim((string) $data['code']);
            if ($data['code'] === '') {
                $data['code'] = null;
            }
        }

        $radius = $data['attendance_radius'] ?? null;
        $data['attendance_radius'] = ($radius === null || $radius === '') ? 100 : $radius;
        $data['timezone'] = !empty($data['timezone']) ? $data['timezone'] : 'UTC';

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
