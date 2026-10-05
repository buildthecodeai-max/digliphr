<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Services\ApprovalChainService;
use App\Services\ApprovalInboxService;
use App\Services\SavedFilterService;

class ApprovalInboxController extends Controller
{
    public function index(): void
    {
        $companyId = $this->companyId();
        $module = trim((string) $this->request->input('module', '')) ?: null;
        $items = (new ApprovalInboxService())->pending($companyId, $module);
        $permissions = ['leave' => 'leave.approve','attendance' => 'attendance.approve','overtime' => 'overtime.approve','loan' => 'loans.approve','payroll' => 'payroll.approve'];
        $items = array_values(array_filter($items, fn (array $item): bool => $this->auth->can($permissions[$item['module']] ?? '')));
        $counts = array_fill_keys(array_keys($permissions), 0);
        foreach ($items as $item) $counts[$item['module']]++;
        $this->view('admin/approvals/index', ['title' => 'Approval Inbox', 'items' => $items, 'counts' => $counts, 'module' => $module, 'companyId' => $companyId, 'companies' => $this->tenant->companies(true), 'savedFilters' => (new SavedFilterService())->forUser($companyId, (int) $this->auth->id(), 'approvals')]);
    }

    public function remind(): void
    {
        $companyId = $this->companyId();
        $items = (new ApprovalInboxService())->pending($companyId);
        $sent = (new ApprovalChainService())->sendReminders($companyId, $items);
        flash('success', $sent . ' escalation reminder' . ($sent === 1 ? '' : 's') . ' sent.');
        $this->redirect('/admin/approvals?company_id=' . $companyId);
    }

    private function companyId(): int
    {
        $id = $this->tenant->resolveCompanyId($this->request->input('company_id'));
        if ($id) return $id;
        $companies = $this->tenant->companies(true);
        if (!$companies) throw new \App\Exceptions\HttpException('No company is available.', 422);
        return (int) $companies[0]['id'];
    }
}
