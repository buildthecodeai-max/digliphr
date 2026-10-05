<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Services\EmployeeImportService;
use RuntimeException;

class EmployeeImportController extends Controller
{
    private EmployeeImportService $imports;

    public function __construct($request, $response)
    {
        parent::__construct($request, $response);
        $this->imports = new EmployeeImportService();
    }

    public function index(): void
    {
        $this->authorize('employees.create');
        $companyId = $this->companyId();
        $batchId = (int) $this->request->input('batch_id', 0);
        $batch = null;
        if ($batchId) {
            try { $batch = $this->imports->batch($batchId, $companyId); } catch (RuntimeException) {}
        }
        $this->view('admin/employees/import', ['title' => 'Import Employees', 'companyId' => $companyId, 'companies' => $this->tenant->companies(true), 'batch' => $batch, 'headers' => EmployeeImportService::HEADERS]);
    }

    public function preview(): void
    {
        $this->authorize('employees.create');
        $companyId = $this->companyId();
        try {
            $batch = $this->imports->preview($this->request->file('csv') ?? [], $companyId, (int) $this->auth->id());
            flash('success', 'Preview ready. Review every warning before importing.');
            $this->redirect('/admin/employees/import?company_id=' . $companyId . '&batch_id=' . $batch['id']);
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
            $this->redirect('/admin/employees/import?company_id=' . $companyId);
        }
    }

    public function confirm(int $id): void
    {
        $this->authorize('employees.create');
        $companyId = $this->companyId();
        try {
            $batch = $this->imports->import($id, $companyId, (int) $this->auth->id());
            flash('success', $batch['imported_rows'] . ' employees imported.');
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        }
        $this->redirect('/admin/employees/import?company_id=' . $companyId . '&batch_id=' . $id);
    }

    public function template(): void
    {
        $this->authorize('employees.create');
        $path = rtrim((string) config('app.paths.exports'), '/') . '/employee_import_template.csv';
        if (!is_dir(dirname($path))) mkdir(dirname($path), 0755, true);
        $fp = fopen($path, 'wb');
        fputcsv($fp, EmployeeImportService::HEADERS);
        fputcsv($fp, ['EMP0001','Ayesha','Khan','ayesha@example.com','03001234567',date('Y-m-d'),'full_time','active','75000','HQ','ENG','SE','DAY']);
        fclose($fp);
        $this->response->download($path, 'employee_import_template.csv', 'text/csv');
    }

    public function errors(int $id): void
    {
        $this->authorize('employees.create');
        $batch = $this->imports->batch($id, $this->companyId());
        $path = rtrim((string) config('app.paths.exports'), '/') . '/employee_import_errors_' . $id . '.csv';
        if (!is_dir(dirname($path))) mkdir(dirname($path), 0755, true);
        $fp = fopen($path, 'wb');
        fputcsv($fp, ['Row','Employee Code','Error']);
        foreach ($batch['errors'] as $error) fputcsv($fp, [$error['row'] ?? '', csv_safe($error['employee_code'] ?? ''), csv_safe($error['message'] ?? '')]);
        fclose($fp);
        $this->response->download($path, basename($path), 'text/csv');
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
