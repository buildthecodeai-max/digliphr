<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Services\AuditService;

class LoanController extends Controller
{
    public function index(): void
    {
        $this->authorize('loans.view');
        $scope = $this->tenant->sql('l.company_id', 'loan_company');
        $employeeScope = $this->tenant->sql('company_id', 'loan_employee_company');
        $rows = Database::getInstance()->fetchAll(
            'SELECT l.*, CONCAT(e.first_name," ",e.last_name) AS employee_name, e.employee_code
             FROM loans l
             INNER JOIN employees e ON e.id = l.employee_id
             WHERE l.deleted_at IS NULL AND (' . $scope['sql'] . ')
             ORDER BY l.id DESC LIMIT 300',
            $scope['params']
        );
        $employees = Database::getInstance()->fetchAll(
            'SELECT id, CONCAT(first_name," ",last_name) AS name, employee_code FROM employees
             WHERE deleted_at IS NULL AND employment_status="active"
               AND (' . $employeeScope['sql'] . ')
             ORDER BY first_name LIMIT 500',
            $employeeScope['params']
        );
        $this->view('admin/loans/index', ['title' => 'Employee Loans', 'rows' => $rows, 'employees' => $employees]);
    }

    public function store(): void
    {
        $this->authorize('loans.manage');
        $data = $this->validate([
            'employee_id' => 'required|exists:employees,id',
            'loan_type' => 'required|in:personal,salary,emergency,housing,vehicle,other',
            'principal_amount' => 'required|numeric|min:1',
            'total_installments' => 'required|integer|min:1',
            'start_date' => 'required|date',
            'reason' => 'nullable|max:1000',
        ]);

        $amount = (float) $data['principal_amount'];
        $installments = (int) $data['total_installments'];
        $monthly = round($amount / $installments, 2);
        $uuid = $this->uuid();
        $loanNumber = 'LN-' . date('Ymd') . '-' . strtoupper(substr($uuid, 0, 6));
        $employee = $this->tenant->employee((int) $data['employee_id']);

        $id = Database::getInstance()->insert('loans', [
            'uuid' => $uuid,
            'company_id' => (int) $employee['company_id'],
            'employee_id' => (int) $data['employee_id'],
            'loan_number' => $loanNumber,
            'loan_type' => $data['loan_type'],
            'principal_amount' => $amount,
            'interest_rate' => 0,
            'interest_amount' => 0,
            'total_amount' => $amount,
            'installment_amount' => $monthly,
            'total_installments' => $installments,
            'remaining_amount' => $amount,
            'start_date' => $data['start_date'],
            'reason' => $data['reason'] ?? null,
            'status' => 'pending',
            'created_by' => $this->user()['id'] ?? null,
        ]);

        (new AuditService())->log('create', 'loans', $id, null, $data);
        flash('success', 'Loan request created.');
        $this->redirect('/admin/loans');
    }

    public function approve(int $id): void
    {
        $this->authorize('loans.approve');
        $db = Database::getInstance();
        $loan = $this->tenant->record('loans', $id);

        $db->beginTransaction();
        try {
            $db->update('loans', [
                'status' => 'active',
                'approved_by' => $this->user()['id'] ?? null,
                'approved_at' => date('Y-m-d H:i:s'),
                'disbursed_at' => date('Y-m-d H:i:s'),
            ], 'id = :id AND company_id = :company_id', [
                'id' => $id,
                'company_id' => $loan['company_id'],
            ]);

            $start = new \DateTime($loan['start_date']);
            for ($i = 1; $i <= (int) $loan['total_installments']; $i++) {
                $db->insert('loan_installments', [
                    'loan_id' => (int) $id,
                    'employee_id' => (int) $loan['employee_id'],
                    'installment_number' => $i,
                    'due_date' => $start->format('Y-m-d'),
                    'amount' => $loan['installment_amount'],
                    'principal_portion' => $loan['installment_amount'],
                    'status' => 'pending',
                ]);
                $start->modify('+1 month');
            }
            $db->commit();
            (new AuditService())->log('approve', 'loans', (int) $id);
            flash('success', 'Loan approved and installments generated.');
        } catch (\Throwable $e) {
            $db->rollBack();
            flash('error', 'Failed to approve loan: ' . $e->getMessage());
        }
        $this->redirect('/admin/loans');
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
