<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Services\ReportService;

class ReportApiController extends Controller
{
    private ReportService $reports;

    public function __construct($request, $response)
    {
        parent::__construct($request, $response);
        $this->reports = new ReportService();
    }

    public function summary(): void
    {
        $this->authorize('reports.view');
        $filters = $this->reports->normalizeFilters($this->request->all());
        $this->jsonSuccess('OK', $this->reports->executiveSummary($filters));
    }

    public function attendance(): void
    {
        $this->authorize('reports.view');
        $filters = $this->reports->normalizeFilters($this->request->all());
        $this->jsonSuccess('OK', [
            'trend' => $this->reports->attendanceTrend($filters),
            'distribution' => $this->reports->attendanceStatusDistribution($filters),
            'by_department' => $this->reports->attendanceByDepartment($filters),
            'work_hours' => $this->reports->workHoursAnalysis($filters),
            'heatmap' => $this->reports->attendanceHeatmap($filters),
            'table' => $this->reports->attendanceTable($filters, (int) $this->request->input('page', 1)),
        ]);
    }

    public function leave(): void
    {
        $this->authorize('reports.view');
        $filters = $this->reports->normalizeFilters($this->request->all());
        $this->jsonSuccess('OK', $this->reports->leaveAnalytics($filters));
    }

    public function payroll(): void
    {
        $this->authorize('reports.view');
        $filters = $this->reports->normalizeFilters($this->request->all());
        $periodId = (int) ($this->request->input('period_id') ?: 0) ?: null;
        $this->jsonSuccess('OK', $this->reports->payrollAnalytics($filters, $periodId));
    }

    public function loans(): void
    {
        $this->authorize('reports.view');
        $filters = $this->reports->normalizeFilters($this->request->all());
        $this->jsonSuccess('OK', $this->reports->loanAnalytics($filters));
    }

    public function workforce(): void
    {
        $this->authorize('reports.view');
        $this->jsonSuccess('OK', $this->reports->workforceAnalytics());
    }

    public function documents(): void
    {
        $this->authorize('reports.view');
        $this->jsonSuccess('OK', $this->reports->documentAnalytics());
    }
}
