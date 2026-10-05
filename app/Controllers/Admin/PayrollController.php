<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Models\PayrollPeriod;
use App\Models\PayrollRecord;
use App\Services\PayrollService;
use App\Services\PayrollVarianceService;
use App\Services\PayslipService;
use RuntimeException;

class PayrollController extends Controller
{
    private PayrollPeriod $periods;
    private PayrollRecord $records;
    private PayrollService $payrollService;
    private PayslipService $payslipService;

    public function __construct($request, $response)
    {
        parent::__construct($request, $response);
        $this->periods = new PayrollPeriod();
        $this->records = new PayrollRecord();
        $this->payrollService = new PayrollService();
        $this->payslipService = new PayslipService();
    }

    public function index(): void
    {
        $this->authorize('payroll.view');
        $page = max(1, (int) $this->request->input('page', 1));
        $companyId = $this->resolveCompanyId();
        $status = $this->request->input('status');
        $filters = array_filter(['status' => $status]);
        $periods = $this->periods->listForCompany($companyId, $page, 15, $filters);

        $this->view('admin/payroll/index', [
            'title' => 'Payroll Periods',
            'periods' => $periods,
            'filters' => $filters,
            'companyId' => $companyId,
            'savedFilters' => (new \App\Services\SavedFilterService())->forUser($companyId, (int) $this->auth->id(), 'payroll'),
        ]);
    }

    public function archived(): void
    {
        $this->authorize('payroll.view_archived');
        $page = max(1, (int) $this->request->input('page', 1));
        $companyId = $this->resolveCompanyId();
        $periods = $this->periods->listForCompany($companyId, $page, 15, ['archived' => 1]);

        $this->view('admin/payroll/archived', [
            'title' => 'Archived Payroll',
            'periods' => $periods,
        ]);
    }

    public function create(): void
    {
        $this->authorize('payroll.process');
        $this->view('admin/payroll/create', ['title' => 'Create Payroll Period']);
    }

    public function store(): void
    {
        $this->authorize('payroll.process');
        $year = (int) $this->request->input('period_year', (int) date('Y'));
        $month = (int) $this->request->input('period_month', (int) date('n'));
        $companyId = $this->resolveCompanyId();

        try {
            $this->payrollService->createPeriod($companyId, $year, $month, $this->auth->id());
            Session::flash('success', 'Payroll period created.');
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect('/admin/payroll');
    }

    public function show(int $id): void
    {
        $this->authorize('payroll.view');
        $period = $this->periods->findIncludingArchived((int) $id);
        if (!$period) {
            $this->redirect('/admin/payroll');
            return;
        }
        $this->tenant->assertCompany((int) $period['company_id']);
        if (!empty($period['deleted_at']) && !can('payroll.view_archived')) {
            Session::flash('error', 'You cannot view archived payroll.');
            $this->redirect('/admin/payroll');
            return;
        }

        $records = empty($period['deleted_at'])
            ? $this->records->listByPeriod((int) $id)
            : Database::getInstance()->fetchAll(
                'SELECT pr.*, e.employee_code, e.first_name, e.last_name, d.name AS department_name
                 FROM payroll_records pr
                 INNER JOIN employees e ON e.id = pr.employee_id
                 LEFT JOIN departments d ON d.id = e.department_id
                 WHERE pr.payroll_period_id = :period_id
                 ORDER BY e.first_name, e.last_name',
                ['period_id' => $id]
            );

        $this->view('admin/payroll/show', [
            'title' => 'Payroll — ' . $period['name'],
            'period' => $period,
            'records' => $records,
            'isArchived' => !empty($period['deleted_at']),
        ]);
    }

    public function process(int $id): void
    {
        $this->authorize('payroll.process');
        $this->assertPeriodAccess($id);
        try {
            $result = $this->payrollService->processPeriod((int) $id, $this->auth->id());
            Session::flash('success', 'Processed ' . $result['employees_processed'] . ' employees. Net total: ' . format_money($result['total_net']));
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/admin/payroll/' . $id);
    }

    public function approve(int $id): void
    {
        $this->authorize('payroll.approve');
        $this->assertPeriodAccess($id);
        if (!$this->request->input('variance_confirmed')) {
            Session::flash('warning', 'Review payroll variance before approval.');
            $this->redirect('/admin/payroll/' . $id . '/variance');
        }
        try {
            $variance = (new PayrollVarianceService())->compare($id);
            Database::getInstance()->insert('payroll_approval_reviews', [
                'payroll_period_id' => $id,
                'reviewed_by' => (int) $this->auth->id(),
                'variance_snapshot' => json_encode($variance['summary'], JSON_UNESCAPED_UNICODE),
                'acknowledged_at' => date('Y-m-d H:i:s'),
            ]);
            $this->payrollService->approvePeriod((int) $id, (int) $this->auth->id());
            Session::flash('success', 'Payroll period approved.');
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/admin/payroll/' . $id);
    }

    public function variance(int $id): void
    {
        $this->authorize('payroll.approve');
        $this->assertPeriodAccess($id);
        $this->view('admin/payroll/variance', [
            'title' => 'Payroll Variance Review',
            'variance' => (new PayrollVarianceService())->compare($id),
        ]);
    }

    public function lock(int $id): void
    {
        $this->authorize('payroll.lock');
        $this->assertPeriodAccess($id);
        try {
            $this->payrollService->lockPeriod((int) $id, (int) $this->auth->id());
            Session::flash('success', 'Payroll period locked.');
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/admin/payroll/' . $id);
    }

    public function reopen(int $id): void
    {
        $this->authorize('payroll.reopen');
        $this->assertPeriodAccess($id);
        try {
            $this->payrollService->reopenPeriod((int) $id, (int) $this->auth->id(), (string) $this->request->input('reason', ''));
            Session::flash('success', 'Payroll period reopened.');
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/admin/payroll/' . $id);
    }

    public function cancel(int $id): void
    {
        $this->authorize('payroll.cancel');
        $this->assertPeriodAccess($id);
        try {
            $this->payrollService->cancelPeriod((int) $id, (int) $this->auth->id(), (string) $this->request->input('reason', ''));
            Session::flash('success', 'Payroll period cancelled.');
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/admin/payroll/' . $id);
    }

    public function archive(int $id): void
    {
        $this->authorize('payroll.archive');
        $this->assertPeriodAccess($id);
        try {
            $this->payrollService->archivePeriod((int) $id, (int) $this->auth->id(), (string) $this->request->input('reason', ''));
            Session::flash('success', 'Payroll archived successfully.');
            $this->redirect('/admin/payroll/archived');
            return;
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/admin/payroll/' . $id);
    }

    public function restore(int $id): void
    {
        $this->authorize('payroll.restore');
        $this->assertPeriodAccess($id, true);
        try {
            $this->payrollService->restorePeriod((int) $id, (int) $this->auth->id());
            Session::flash('success', 'Payroll restored successfully.');
            $this->redirect('/admin/payroll/' . $id);
            return;
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/admin/payroll/archived');
    }

    public function forceDestroy(int $id): void
    {
        $this->authorize('payroll.permanently_delete');
        $this->assertPeriodAccess($id, true);
        try {
            $this->payrollService->permanentlyDeletePeriod((int) $id, (int) $this->auth->id(), (string) $this->request->input('reason', ''));
            Session::flash('success', 'Payroll permanently deleted.');
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/admin/payroll/archived');
    }

    public function generatePayslips(int $id): void
    {
        $this->authorize('payslips.generate');
        $this->assertPeriodAccess($id);
        $count = $this->payslipService->generateForPeriod((int) $id, $this->auth->id());
        Session::flash('success', "Generated {$count} payslips.");
        $this->redirect('/admin/payroll/' . $id);
    }

    public function record(int $periodId, int $recordId): void
    {
        $this->authorize('payroll.view');
        $record = $this->records->findDetailed((int) $recordId);
        if (!$record) {
            $this->redirect('/admin/payroll/' . $periodId);
            return;
        }
        $this->tenant->assertCompany((int) $record['company_id']);

        $db = Database::getInstance();
        $earnings = $db->fetchAll('SELECT * FROM payroll_earnings WHERE payroll_record_id = :id', ['id' => $recordId]);
        $deductions = $db->fetchAll('SELECT * FROM payroll_deductions WHERE payroll_record_id = :id', ['id' => $recordId]);

        $this->view('admin/payroll/record', [
            'title' => 'Payroll Record',
            'record' => $record,
            'earnings' => $earnings,
            'deductions' => $deductions,
            'periodId' => $periodId,
        ]);
    }

    public function editRecord(int $periodId, int $recordId): void
    {
        $this->authorize('payroll.edit');
        $record = $this->records->findDetailed((int) $recordId);
        $period = $this->periods->find((int) $periodId);
        if (!$record || !$period) {
            $this->redirect('/admin/payroll/' . $periodId);
            return;
        }
        $this->tenant->assertCompany((int) $record['company_id']);
        $this->tenant->assertCompany((int) $period['company_id']);
        if ((int) $record['payroll_period_id'] !== (int) $period['id']) {
            throw new \App\Exceptions\HttpException('Payroll record not found.', 404);
        }
        if (!in_array($period['status'], ['draft', 'reopened', 'calculated'], true)) {
            Session::flash('error', 'Only draft or reopened payroll can be edited.');
            $this->redirect('/admin/payroll/' . $periodId . '/records/' . $recordId);
            return;
        }

        $this->view('admin/payroll/record_edit', [
            'title' => 'Edit Payroll Record',
            'record' => $record,
            'period' => $period,
            'periodId' => $periodId,
        ]);
    }

    public function updateRecord(int $periodId, int $recordId): void
    {
        $this->authorize('payroll.edit');
        $period = $this->assertPeriodAccess($periodId);
        $record = $this->tenant->record('payroll_records', $recordId);
        if ((int) $record['payroll_period_id'] !== (int) $period['id']) {
            throw new \App\Exceptions\HttpException('Payroll record not found.', 404);
        }
        try {
            $this->payrollService->updateDraftRecord((int) $recordId, $this->request->all(), (int) $this->auth->id());
            Session::flash('success', 'Payroll record updated and totals recalculated.');
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('/admin/payroll/' . $periodId . '/records/' . $recordId . '/edit');
            return;
        }
        $this->redirect('/admin/payroll/' . $periodId . '/records/' . $recordId);
    }

    private function resolveCompanyId(): int
    {
        $requested = $this->request->input('company_id');
        $companyId = $this->tenant->resolveCompanyId($requested);
        if ($companyId) {
            return $companyId;
        }
        $companies = $this->tenant->companies(true);
        if ($companies === []) {
            throw new \App\Exceptions\HttpException('No active company is available.', 422);
        }
        return (int) $companies[0]['id'];
    }

    private function assertPeriodAccess(int $id, bool $includingArchived = false): array
    {
        $period = $includingArchived
            ? $this->periods->findIncludingArchived($id)
            : $this->periods->find($id);
        if (!$period) {
            throw new \App\Exceptions\HttpException('Payroll period not found.', 404);
        }
        $this->tenant->assertCompany((int) $period['company_id']);
        return $period;
    }
}
