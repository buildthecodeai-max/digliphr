<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Services\ProductSetupService;

class ProductSetupController extends Controller
{
    public function index(): void
    {
        $this->authorize('settings.manage');
        $companyId = $this->companyId();
        $this->view('admin/setup/index', ['title' => 'Company Setup', 'setup' => (new ProductSetupService())->checklist($companyId), 'companies' => $this->tenant->companies(true)]);
    }

    public function dismiss(): void
    {
        $this->authorize('settings.manage');
        $companyId = $this->companyId();
        $db = Database::getInstance();
        $exists = $db->fetch('SELECT id FROM company_setup_progress WHERE company_id=:id', ['id' => $companyId]);
        if ($exists) $db->update('company_setup_progress', ['dismissed_at' => date('Y-m-d H:i:s')], 'id=:id', ['id' => $exists['id']]);
        else $db->insert('company_setup_progress', ['company_id' => $companyId, 'dismissed_at' => date('Y-m-d H:i:s')]);
        flash('success', 'Setup checklist hidden. You can reopen it from Administration.');
        $this->redirect('/admin/dashboard');
    }

    private function companyId(): int
    {
        $id = $this->tenant->resolveCompanyId($this->request->input('company_id'));
        if ($id) return $id;
        $companies = $this->tenant->companies(true);
        if (!$companies) throw new \App\Exceptions\HttpException('Create a company before starting setup.', 422);
        return (int) $companies[0]['id'];
    }
}
