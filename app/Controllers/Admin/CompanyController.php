<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Company;
use App\Services\AuditService;

class CompanyController extends Controller
{
    private Company $companies;

    public function __construct($request, $response)
    {
        parent::__construct($request, $response);
        $this->companies = new Company();
    }

    public function index(): void
    {
        $this->authorize('companies.view');
        $companies = $this->tenant->companies();
        $paginator = [
            'data' => $companies,
            'total' => count($companies),
            'per_page' => max(1, count($companies)),
            'current_page' => 1,
            'last_page' => 1,
        ];

        $this->view('admin.companies.index', [
            'title' => 'Companies',
            'companies' => $paginator['data'],
            'paginator' => $paginator,
        ]);
    }

    public function create(): void
    {
        $this->authorize('companies.create');
        if (!$this->tenant->isGlobal()) {
            throw new \App\Exceptions\HttpException('Only a super administrator can create companies.', 403);
        }
        $this->view('admin.companies.form', [
            'title' => 'Add Company',
            'company' => null,
        ]);
    }

    public function store(): void
    {
        $this->authorize('companies.create');
        if (!$this->tenant->isGlobal()) {
            throw new \App\Exceptions\HttpException('Only a super administrator can create companies.', 403);
        }

        $data = $this->validate([
            'name' => 'required|min:2|max:200',
            'code' => 'nullable|max:50|unique:companies,code',
            'legal_name' => 'nullable|max:255',
            'email' => 'nullable|email|max:191',
            'phone' => 'nullable|max:30',
            'website' => 'nullable|url|max:255',
            'tax_number' => 'nullable|max:100',
            'registration_number' => 'nullable|max:100',
            'address_line1' => 'nullable|max:255',
            'city' => 'nullable|max:100',
            'country' => 'nullable|max:100',
            'timezone' => 'nullable|max:64',
            'currency' => 'nullable|max:3',
            'is_active' => 'nullable|boolean',
        ]);

        $data['uuid'] = $this->uuid();
        $data['is_active'] = isset($data['is_active']) ? 1 : 0;
        $data['timezone'] = $data['timezone'] ?? 'UTC';
        $data['currency'] = $data['currency'] ?? 'PKR';

        $id = $this->companies->create($data);
        (new AuditService())->log('create', 'companies', $id, null, $data);

        flash('success', 'Company created successfully.');
        $this->redirect('/admin/companies');
    }

    public function edit(int $id): void
    {
        $this->authorize('companies.update');

        $company = $this->companies->find($id);
        if (!$company) {
            throw new \App\Exceptions\HttpException('Company not found.', 404);
        }
        $this->tenant->assertCompany($id);

        $this->view('admin.companies.form', [
            'title' => 'Edit Company',
            'company' => $company,
        ]);
    }

    public function update(int $id): void
    {
        $this->authorize('companies.update');

        $company = $this->companies->find($id);
        if (!$company) {
            throw new \App\Exceptions\HttpException('Company not found.', 404);
        }
        $this->tenant->assertCompany($id);

        $data = $this->validate([
            'name' => 'required|min:2|max:200',
            'code' => 'nullable|max:50|unique:companies,code,' . $id,
            'legal_name' => 'nullable|max:255',
            'email' => 'nullable|email|max:191',
            'phone' => 'nullable|max:30',
            'website' => 'nullable|url|max:255',
            'tax_number' => 'nullable|max:100',
            'registration_number' => 'nullable|max:100',
            'address_line1' => 'nullable|max:255',
            'city' => 'nullable|max:100',
            'country' => 'nullable|max:100',
            'timezone' => 'nullable|max:64',
            'currency' => 'nullable|max:3',
            'is_active' => 'nullable|boolean',
        ]);

        $data['is_active'] = isset($data['is_active']) ? 1 : 0;
        $data['timezone'] = !empty($data['timezone']) ? $data['timezone'] : 'UTC';
        $data['currency'] = !empty($data['currency']) ? $data['currency'] : 'PKR';

        $this->companies->update($id, $data);
        (new AuditService())->log('update', 'companies', $id, $company, $data);

        flash('success', 'Company updated successfully.');
        $this->redirect('/admin/companies');
    }

    public function destroy(int $id): void
    {
        $this->authorize('companies.delete');

        $company = $this->companies->find($id);
        if (!$company) {
            throw new \App\Exceptions\HttpException('Company not found.', 404);
        }
        $this->tenant->assertCompany($id);

        $this->companies->delete($id);
        (new AuditService())->log('delete', 'companies', $id, $company);

        flash('success', 'Company deleted successfully.');
        $this->redirect('/admin/companies');
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
