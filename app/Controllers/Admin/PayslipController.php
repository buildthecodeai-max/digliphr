<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Services\AuditService;

class PayslipController extends Controller
{
    private Database $db;

    public function __construct($request, $response)
    {
        parent::__construct($request, $response);
        $this->db = Database::getInstance();
    }

    public function index(): void
    {
        $this->authorize('payslips.view');

        $month = (int) ($this->request->input('month') ?: date('n'));
        $year  = (int) ($this->request->input('year')  ?: date('Y'));

        $scope = $this->tenant->sql('e.company_id', 'payslip_company');

        $rows = $this->db->fetchAll(
            'SELECT p.*, CONCAT(e.first_name, " ", e.last_name) AS employee_name, e.employee_code,
                    pp.name AS period_name
             FROM payslips p
             INNER JOIN employees e ON e.id = p.employee_id
             LEFT JOIN payroll_periods pp ON pp.id = p.payroll_period_id
             WHERE p.deleted_at IS NULL
               AND MONTH(p.issue_date) = :month AND YEAR(p.issue_date) = :year
               AND (' . $scope['sql'] . ')
             ORDER BY p.issue_date DESC, p.id DESC',
            array_merge(['month' => $month, 'year' => $year], $scope['params'])
        );

        $this->view('admin/payroll/payslips', [
            'title'  => 'Payslips',
            'rows'   => $rows,
            'month'  => $month,
            'year'   => $year,
        ]);
    }

    public function show(int $id): void
    {
        $this->authorize('payslips.view');

        $row = $this->fetchPayslip($id);
        $this->view('admin/payroll/payslip-show', [
            'title' => 'Payslip ' . ($row['payslip_number'] ?? ''),
            'row'   => $row,
        ]);
    }

    public function edit(int $id): void
    {
        $this->authorize('payslips.edit');

        $row = $this->fetchPayslip($id);
        $this->view('admin/payroll/payslip-edit', [
            'title' => 'Edit Payslip ' . ($row['payslip_number'] ?? ''),
            'row'   => $row,
        ]);
    }

    public function update(int $id): void
    {
        $this->authorize('payslips.edit');

        $row = $this->fetchPayslip($id);

        $data = $this->validate([
            'gross_earnings'   => 'required|numeric|min:0',
            'total_deductions' => 'required|numeric|min:0',
            'net_salary'       => 'required|numeric',
            'issue_date'       => 'required|date',
            'status'           => 'required|in:generated,sent,viewed,downloaded,void',
        ]);

        $old = $row;
        $this->db->query(
            'UPDATE payslips SET gross_earnings=:gross, total_deductions=:ded, net_salary=:net,
             issue_date=:dt, status=:st, updated_at=NOW()
             WHERE id=:id AND deleted_at IS NULL',
            [
                'gross' => (float) $data['gross_earnings'],
                'ded'   => (float) $data['total_deductions'],
                'net'   => (float) $data['net_salary'],
                'dt'    => $data['issue_date'],
                'st'    => $data['status'],
                'id'    => $id,
            ]
        );

        (new AuditService())->log('update', 'payslips', $id, $old, $data);
        flash('success', 'Payslip updated successfully.');
        $this->redirect('/admin/payslips/' . $id);
    }

    public function destroy(int $id): void
    {
        $this->authorize('payslips.delete');

        $row = $this->fetchPayslip($id);

        $this->db->query(
            'UPDATE payslips SET deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id]
        );

        (new AuditService())->log('delete', 'payslips', $id, $row, null);
        flash('success', 'Payslip deleted.');

        $month = (int) date('n', strtotime($row['issue_date']));
        $year  = (int) date('Y', strtotime($row['issue_date']));
        $this->redirect('/admin/payslips?month=' . $month . '&year=' . $year);
    }

    // ─── helpers ────────────────────────────────────────────────

    private function fetchPayslip(int $id): array
    {
        $row = $this->db->fetch(
            'SELECT p.*, CONCAT(e.first_name, " ", e.last_name) AS employee_name, e.employee_code,
                    pp.name AS period_name
             FROM payslips p
             INNER JOIN employees e ON e.id = p.employee_id
             LEFT JOIN payroll_periods pp ON pp.id = p.payroll_period_id
             WHERE p.id = :id AND p.deleted_at IS NULL',
            ['id' => $id]
        );

        if (!$row) {
            flash('error', 'Payslip not found.');
            $this->redirect('/admin/payslips');
        }

        $this->tenant->employee((int) $row['employee_id']);
        return $row;
    }
}
