<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Services\AuditService;

class AdvanceController extends Controller
{
    public function index(): void
    {
        $this->authorize('advances.view');
        $scope = $this->tenant->sql('a.company_id', 'advance_company');
        $employeeScope = $this->tenant->sql('company_id', 'advance_employee_company');
        $rows = Database::getInstance()->fetchAll(
            'SELECT a.*, CONCAT(e.first_name," ",e.last_name) AS employee_name, e.employee_code
             FROM salary_advances a
             INNER JOIN employees e ON e.id = a.employee_id
             WHERE a.deleted_at IS NULL AND (' . $scope['sql'] . ')
             ORDER BY a.id DESC LIMIT 300',
            $scope['params']
        );
        $employees = Database::getInstance()->fetchAll(
            'SELECT id, CONCAT(first_name," ",last_name) AS name, employee_code FROM employees
             WHERE deleted_at IS NULL AND employment_status="active"
               AND (' . $employeeScope['sql'] . ')
             ORDER BY first_name LIMIT 500',
            $employeeScope['params']
        );
        $this->view('admin/loans/advances', ['title' => 'Salary Advances', 'rows' => $rows, 'employees' => $employees]);
    }

    public function store(): void
    {
        $this->authorize('advances.manage');
        $data = $this->validate([
            'employee_id' => 'required|exists:employees,id',
            'amount' => 'required|numeric|min:1',
            'installments_count' => 'nullable|integer|min:1',
            'reason' => 'required|min:3|max:1000',
        ]);

        $uuid = $this->uuid();
        $amount = (float) $data['amount'];
        $employee = $this->tenant->employee((int) $data['employee_id']);

        $id = Database::getInstance()->insert('salary_advances', [
            'uuid' => $uuid,
            'company_id' => (int) $employee['company_id'],
            'employee_id' => (int) $data['employee_id'],
            'advance_number' => 'ADV-' . date('Ymd') . '-' . strtoupper(substr($uuid, 0, 6)),
            'amount' => $amount,
            'request_date' => date('Y-m-d'),
            'repayment_method' => 'payroll_deduction',
            'installments_count' => (int) ($data['installments_count'] ?? 1),
            'remaining_amount' => $amount,
            'reason' => $data['reason'],
            'status' => 'pending',
            'created_by' => $this->user()['id'] ?? null,
        ]);

        (new AuditService())->log('create', 'salary_advances', $id, null, $data);
        flash('success', 'Salary advance created.');
        $this->redirect('/admin/advances');
    }

    public function approve(int $id): void
    {
        $this->authorize('advances.approve');
        $advance = $this->tenant->record('salary_advances', $id);
        Database::getInstance()->update('salary_advances', [
            'status' => 'approved',
            'approved_by' => $this->user()['id'] ?? null,
            'approved_at' => date('Y-m-d H:i:s'),
        ], 'id = :id AND company_id = :company_id', [
            'id' => $id,
            'company_id' => $advance['company_id'],
        ]);
        (new AuditService())->log('approve', 'salary_advances', (int) $id);
        flash('success', 'Salary advance approved.');
        $this->redirect('/admin/advances');
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
