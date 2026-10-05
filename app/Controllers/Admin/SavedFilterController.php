<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Services\SavedFilterService;

class SavedFilterController extends Controller
{
    public function store(string $module): void
    {
        $companyId = $this->tenant->resolveCompanyId($this->request->input('company_id'));
        if (!$companyId) throw new \App\Exceptions\HttpException('Choose a company before saving this view.', 422);
        $name = trim((string) $this->request->input('name'));
        if ($name === '' || mb_strlen($name) > 120) throw new \App\Exceptions\HttpException('Saved view name is required and must be under 120 characters.', 422);
        $raw = (string) $this->request->input('filters_json', '{}');
        $filters = json_decode($raw, true);
        if (!is_array($filters)) $filters = [];
        (new SavedFilterService())->save($companyId, (int) $this->auth->id(), $module, $name, $filters, (bool) $this->request->input('is_default'));
        flash('success', 'Saved view created.');
        $this->safeBack('/admin/dashboard');
    }

    public function destroy(int $id): void
    {
        $companyId = $this->tenant->resolveCompanyId($this->request->input('company_id'));
        if ($companyId) (new SavedFilterService())->delete($id, $companyId, (int) $this->auth->id());
        flash('success', 'Saved view removed.');
        $this->safeBack('/admin/dashboard');
    }

    private function safeBack(string $fallback): void
    {
        $referer = (string) $this->request->header('Referer', $fallback);
        $path = parse_url($referer, PHP_URL_PATH);
        $query = parse_url($referer, PHP_URL_QUERY);
        $this->redirect(is_string($path) && str_starts_with($path, '/') ? $path . ($query ? '?' . $query : '') : $fallback);
    }
}
