<?php

declare(strict_types=1);

namespace App\Controllers\Employee;

use App\Core\Controller;
use App\Core\Database;
use App\Services\AuditService;

class AdvanceController extends Controller
{
    public function index(): void
    {
        $employee = $this->employee();
        if (!$employee) {
            throw new \App\Exceptions\HttpException('Employee profile not found.', 404);
        }

        $rows = Database::getInstance()->fetchAll(
            'SELECT * FROM salary_advances WHERE employee_id = :eid AND deleted_at IS NULL ORDER BY id DESC',
            ['eid' => $employee['id']]
        );

        $this->view('employee/loans/index', [
            'title' => 'My Salary Advances',
            'rows'  => $rows,
            'type'  => 'advances',
        ], 'layouts/employee');
    }

    public function create(): void
    {
        $employee = $this->employee();
        if (!$employee) {
            throw new \App\Exceptions\HttpException('Employee profile not found.', 404);
        }

        $this->view('employee/loans/apply_advance', [
            'title'    => 'Apply for Salary Advance',
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
            'amount'             => 'required|numeric|min:1',
            'installments_count' => 'nullable|integer|min:1',
            'reason'             => 'required|min:3|max:1000',
        ]);

        $amount = (float) $data['amount'];
        $uuid   = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
        $advNumber = 'ADV-' . date('Ymd') . '-' . strtoupper(substr(str_replace('-', '', $uuid), 0, 6));

        $db = Database::getInstance();
        $id = $db->insert('salary_advances', [
            'uuid'               => $uuid,
            'company_id'         => (int) $employee['company_id'],
            'employee_id'        => (int) $employee['id'],
            'advance_number'     => $advNumber,
            'amount'             => $amount,
            'request_date'       => date('Y-m-d'),
            'repayment_method'   => 'payroll_deduction',
            'installments_count' => (int) ($data['installments_count'] ?? 1),
            'remaining_amount'   => $amount,
            'reason'             => $data['reason'],
            'status'             => 'pending',
            'created_by'         => $this->user()['id'] ?? null,
        ]);

        (new AuditService())->log('create', 'salary_advances', $id, null, $data);
        flash('success', 'Salary advance request submitted successfully. It is pending approval.');
        $this->redirect('/employee/advances');
    }
}
