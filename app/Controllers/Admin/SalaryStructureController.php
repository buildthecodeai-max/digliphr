<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\SalaryStructure;
use App\Services\AuditService;

class SalaryStructureController extends Controller
{
    private SalaryStructure $structures;

    public function __construct($request, $response)
    {
        parent::__construct($request, $response);
        $this->structures = new SalaryStructure();
    }

    public function index(): void
    {
        $this->authorize('salary.view');
        $scope = $this->tenant->sql('ss.company_id', 'salary_structure_company');
        $this->view('admin/payroll/structures', [
            'title' => 'Salary Structures',
            'structures' => $this->structures->raw(
                'SELECT ss.*, c.name AS company_name,
                        (SELECT COUNT(*) FROM salary_structure_items si WHERE si.salary_structure_id = ss.id) AS item_count
                 FROM salary_structures ss
                 LEFT JOIN companies c ON c.id = ss.company_id
                 WHERE ss.deleted_at IS NULL AND (' . $scope['sql'] . ')
                 ORDER BY ss.name',
                $scope['params']
            ),
        ]);
    }

    public function create(): void
    {
        $this->authorize('salary.manage');
        $this->view('admin/payroll/structure_form', [
            'title' => 'Create Salary Structure',
            'structure' => null,
            'companies' => $this->tenant->companies(true),
            'components' => $this->tenantComponents(),
        ]);
        $this->tenant->assertCompany((int) $data['company_id']);
    }

    public function store(): void
    {
        $this->authorize('salary.manage');
        $data = $this->validate([
            'company_id' => 'required|exists:companies,id',
            'name' => 'required|min:2|max:150',
            'code' => 'required|max:50',
            'description' => 'nullable|max:1000',
            'effective_from' => 'required|date',
        ]);
        $data['is_active'] = $this->request->input('is_active') ? 1 : 0;
        $data['is_default'] = $this->request->input('is_default') ? 1 : 0;
        $data['currency'] = config('app.currency', 'PKR');

        $id = $this->structures->create($data);
        $this->syncItems($id, (int) $data['company_id']);
        (new AuditService())->log('create', 'salary_structures', $id, null, $data);
        flash('success', 'Salary structure created.');
        $this->redirect('/admin/salary-structures');
    }

    public function edit(int $id): void
    {
        $this->authorize('salary.manage');
        $structure = $this->structures->find((int) $id);
        if (!$structure) {
            throw new \App\Exceptions\HttpException('Structure not found.', 404);
        }
        $this->tenant->assertCompany((int) $structure['company_id']);
        $this->view('admin/payroll/structure_form', [
            'title' => 'Edit Salary Structure',
            'structure' => $structure,
            'companies' => $this->tenant->companies(true),
            'components' => $this->tenantComponents(),
            'items' => $this->structures->db()->fetchAll(
                'SELECT * FROM salary_structure_items WHERE salary_structure_id = :id ORDER BY sort_order',
                ['id' => $id]
            ),
        ]);
    }

    public function update(int $id): void
    {
        $this->authorize('salary.manage');
        $structure = $this->structures->find((int) $id);
        if (!$structure) {
            throw new \App\Exceptions\HttpException('Structure not found.', 404);
        }
        $this->tenant->assertCompany((int) $structure['company_id']);
        $data = $this->validate([
            'company_id' => 'required|exists:companies,id',
            'name' => 'required|min:2|max:150',
            'code' => 'required|max:50',
            'description' => 'nullable|max:1000',
            'effective_from' => 'required|date',
        ]);
        $this->tenant->assertCompany((int) $data['company_id']);
        $data['is_active'] = $this->request->input('is_active') ? 1 : 0;
        $data['is_default'] = $this->request->input('is_default') ? 1 : 0;
        $this->structures->update((int) $id, $data);
        $this->syncItems((int) $id, (int) $data['company_id']);
        (new AuditService())->log('update', 'salary_structures', (int) $id, $structure, $data);
        flash('success', 'Salary structure updated.');
        $this->redirect('/admin/salary-structures');
    }

    public function destroy(int $id): void
    {
        $this->authorize('salary.manage');
        $structure = $this->structures->find($id);
        if (!$structure) {
            flash('error', 'Structure not found.');
            $this->redirect('/admin/salary-structures');
            return;
        }
        $this->tenant->assertCompany((int) $structure['company_id']);

        $assigned = (int) $this->structures->db()->fetchColumn(
            'SELECT COUNT(*) FROM employee_salary_structures WHERE salary_structure_id = :id AND deleted_at IS NULL',
            ['id' => $id]
        );
        if ($assigned > 0) {
            $this->structures->update($id, ['is_active' => 0]);
            flash('warning', 'Structure is assigned to employees and was deactivated instead of deleted.');
        } else {
            $this->structures->delete($id);
            flash('success', 'Salary structure archived.');
        }
        (new AuditService())->log('delete', 'salary_structures', $id, $structure);
        $this->redirect('/admin/salary-structures');
    }

    private function syncItems(int $structureId, int $companyId): void
    {
        $rows = $this->request->input('components', []);
        if (!is_array($rows)) {
            return;
        }

        $db = $this->structures->db();
        $db->query('DELETE FROM salary_structure_items WHERE salary_structure_id = :id', ['id' => $structureId]);

        $sort = 0;
        foreach ($rows as $row) {
            if (empty($row['enabled']) || empty($row['salary_component_id'])) {
                continue;
            }
            $componentId = (int) $row['salary_component_id'];
            $component = $this->tenant->record('salary_components', $componentId);
            if ((int) $component['company_id'] !== $companyId) {
                throw new \App\Exceptions\HttpException('Salary component must belong to the structure company.', 422);
            }
            $amount = isset($row['amount']) && $row['amount'] !== '' ? (float) $row['amount'] : null;
            $percentage = isset($row['percentage']) && $row['percentage'] !== '' ? (float) $row['percentage'] : null;
            $calc = $percentage !== null && $percentage > 0 ? 'percentage' : 'fixed';

            $db->insert('salary_structure_items', [
                'salary_structure_id' => $structureId,
                'salary_component_id' => $componentId,
                'amount' => $amount,
                'percentage' => $percentage,
                'calculation_type' => $calc,
                'sort_order' => $sort++,
            ]);
        }
    }

    private function tenantComponents(): array
    {
        $scope = $this->tenant->sql('company_id', 'salary_component_company');
        return $this->structures->db()->fetchAll(
            'SELECT * FROM salary_components
             WHERE deleted_at IS NULL AND is_active = 1 AND (' . $scope['sql'] . ')
             ORDER BY sort_order, name',
            $scope['params']
        );
    }
}
