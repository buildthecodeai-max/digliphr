<?php

declare(strict_types=1);

namespace App\Controllers\Employee;

use App\Core\Controller;
use App\Core\Database;
use App\Models\PayrollRecord;
use App\Services\PayslipService;

class PayslipController extends Controller
{
    private PayrollRecord $records;

    public function __construct($request, $response)
    {
        parent::__construct($request, $response);
        $this->records = new PayrollRecord();
    }

    public function index(): void
    {
        // Self-service: EmployeeMiddleware already gates this area (same pattern as leave/documents).
        $employee = $this->employee();
        if (!$employee) {
            throw new \App\Exceptions\HttpException('Employee profile not found.', 404);
        }

        $page = max(1, (int) $this->request->input('page', 1));
        $payslips = $this->records->listForEmployee((int) $employee['id'], $page);

        $this->view('employee/payslips/index', [
            'title' => 'My Payslips',
            'payslips' => $payslips,
        ], 'layouts/employee');
    }

    public function show(int $id): void
    {
        $employee = $this->employee();
        if (!$employee) {
            throw new \App\Exceptions\HttpException('Employee profile not found.', 404);
        }

        $record = $this->records->findDetailed($id);

        if (!$record || (int) $record['employee_id'] !== (int) $employee['id']) {
            flash('error', 'Payslip not found.');
            $this->redirect('/employee/payslips');
            return;
        }

        $db = Database::getInstance();
        $earnings = $db->fetchAll(
            'SELECT * FROM payroll_earnings WHERE payroll_record_id = :id ORDER BY id',
            ['id' => $id]
        );
        $deductions = $db->fetchAll(
            'SELECT * FROM payroll_deductions WHERE payroll_record_id = :id ORDER BY id',
            ['id' => $id]
        );
        $payslip = $db->fetch(
            'SELECT * FROM payslips WHERE payroll_record_id = :id LIMIT 1',
            ['id' => $id]
        );

        $this->view('employee/payslips/show', [
            'title' => 'Payslip — ' . $record['period_name'],
            'record' => $record,
            'earnings' => $earnings,
            'deductions' => $deductions,
            'payslip' => $payslip,
        ], 'layouts/employee');
    }

    public function download(int $id): void
    {
        $employee = $this->employee();
        if (!$employee) {
            throw new \App\Exceptions\HttpException('Employee profile not found.', 404);
        }

        $record = $this->records->find($id);

        if (!$record || (int) $record['employee_id'] !== (int) $employee['id']) {
            flash('error', 'Payslip not found.');
            $this->redirect('/employee/payslips');
            return;
        }

        $db = Database::getInstance();
        $payslip = $db->fetch(
            'SELECT * FROM payslips WHERE payroll_record_id = :id LIMIT 1',
            ['id' => $id]
        );

        $path = $payslip['file_path'] ?? '';
        if (!$payslip || $path === '' || !is_file($path)) {
            try {
                (new PayslipService())->generate($id, $this->auth->id());
                $payslip = $db->fetch(
                    'SELECT * FROM payslips WHERE payroll_record_id = :id LIMIT 1',
                    ['id' => $id]
                );
                $path = $payslip['file_path'] ?? '';
            } catch (\Throwable $e) {
                flash('error', 'Unable to generate payslip PDF. Please try again or contact HR.');
                $this->redirect('/employee/payslips/' . $id);
                return;
            }
        }

        if ($payslip && $path !== '' && is_file($path)) {
            $filename = ($payslip['payslip_number'] ?? ('payslip-' . $id)) . '.pdf';
            $this->response->download($path, $filename, 'application/pdf');
            return;
        }

        flash('error', 'Payslip PDF is not available yet.');
        $this->redirect('/employee/payslips/' . $id);
    }
}
