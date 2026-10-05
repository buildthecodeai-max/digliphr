<?php

declare(strict_types=1);

namespace App\Controllers\Employee;

use App\Core\Controller;
use App\Core\Database;
use App\Services\AuditService;

class LoanController extends Controller
{
    public function index(): void
    {
        $employee = $this->employee();
        if (!$employee) {
            throw new \App\Exceptions\HttpException('Employee profile not found.', 404);
        }

        $loans = Database::getInstance()->fetchAll(
            'SELECT * FROM loans WHERE employee_id = :eid AND deleted_at IS NULL ORDER BY id DESC',
            ['eid' => $employee['id']]
        );

        $this->view('employee/loans/index', [
            'title' => 'My Loans',
            'rows'  => $loans,
            'type'  => 'loans',
        ], 'layouts/employee');
    }

    public function create(): void
    {
        $employee = $this->employee();
        if (!$employee) {
            throw new \App\Exceptions\HttpException('Employee profile not found.', 404);
        }

        $this->view('employee/loans/apply_loan', [
            'title'    => 'Apply for Loan',
            'employee' => $employee,
        ], 'layouts/employee');
    }

    public function store(): void
    {
        $employee = $this->employee();
        if (!$employee) {
            throw new \App\Exceptions\HttpException('Employee profile not found.', 404);
        }

        $data = $this->validate([
            'loan_type'         => 'required|in:personal,salary,emergency,housing,vehicle,other',
            'principal_amount'  => 'required|numeric|min:1',
            'total_installments'=> 'required|integer|min:1',
            'start_date'        => 'required|date',
            'reason'            => 'nullable|max:1000',
        ]);

        $amount       = (float) $data['principal_amount'];
        $installments = (int)   $data['total_installments'];
        $monthly      = round($amount / $installments, 2);
        $uuid         = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
        $loanNumber = 'LN-' . date('Ymd') . '-' . strtoupper(substr(str_replace('-', '', $uuid), 0, 6));

        $db = Database::getInstance();
        $id = $db->insert('loans', [
            'uuid'               => $uuid,
            'company_id'         => (int) $employee['company_id'],
            'employee_id'        => (int) $employee['id'],
            'loan_number'        => $loanNumber,
            'loan_type'          => $data['loan_type'],
            'principal_amount'   => $amount,
            'interest_rate'      => 0,
            'interest_amount'    => 0,
            'total_amount'       => $amount,
            'installment_amount' => $monthly,
            'total_installments' => $installments,
            'remaining_amount'   => $amount,
            'start_date'         => $data['start_date'],
            'reason'             => $data['reason'] ?? null,
            'status'             => 'pending',
            'created_by'         => $this->user()['id'] ?? null,
        ]);

        (new AuditService())->log('create', 'loans', $id, null, $data);
        flash('success', 'Loan application submitted successfully. It is pending approval.');
        $this->redirect('/employee/loans');
    }
}
