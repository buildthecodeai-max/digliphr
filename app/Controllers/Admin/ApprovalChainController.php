<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Services\ApprovalChainService;

class ApprovalChainController extends Controller
{
    public function index(): void
    {
        $this->authorize('settings.manage');
        $companyId = $this->companyId();
        $users = Database::getInstance()->fetchAll('SELECT DISTINCT u.id,u.name FROM users u LEFT JOIN employees e ON e.user_id=u.id LEFT JOIN user_roles ur ON ur.user_id=u.id WHERE u.is_active=1 AND u.deleted_at IS NULL AND (e.company_id=:cid OR ur.company_id=:cid2) ORDER BY u.name', ['cid' => $companyId, 'cid2' => $companyId]);
        $roles = Database::getInstance()->fetchAll('SELECT DISTINCT r.slug,r.name FROM roles r WHERE r.deleted_at IS NULL AND r.is_active=1 AND (r.company_id IS NULL OR r.company_id=:cid) AND r.slug<>"super_admin" ORDER BY r.name', ['cid' => $companyId]);
        $this->view('admin/approvals/chains', ['title' => 'Approval Chains', 'companyId' => $companyId, 'companies' => $this->tenant->companies(true), 'chains' => (new ApprovalChainService())->all($companyId), 'users' => $users, 'roles' => $roles]);
    }

    public function store(): void
    {
        $this->authorize('settings.manage');
        $companyId = $this->companyId();
        (new ApprovalChainService())->save($companyId, null, $this->request->all(), (int) $this->auth->id());
        flash('success', 'Approval chain created.');
        $this->redirect('/admin/approval-chains?company_id=' . $companyId);
    }

    public function update(int $id): void
    {
        $this->authorize('settings.manage');
        $companyId = $this->companyId();
        (new ApprovalChainService())->save($companyId, $id, $this->request->all(), (int) $this->auth->id());
        flash('success', 'Approval chain updated.');
        $this->redirect('/admin/approval-chains?company_id=' . $companyId);
    }

    public function destroy(int $id): void
    {
        $this->authorize('settings.manage');
        $companyId = $this->companyId();
        (new ApprovalChainService())->delete($id, $companyId);
        flash('success', 'Approval chain deleted.');
        $this->redirect('/admin/approval-chains?company_id=' . $companyId);
    }

    private function companyId(): int
    {
        $id = $this->tenant->resolveCompanyId($this->request->input('company_id'));
        if ($id) return $id;
        $companies = $this->tenant->companies(true);
        if (!$companies) throw new \App\Exceptions\HttpException('No active company is available.', 422);
        return (int) $companies[0]['id'];
    }
}
