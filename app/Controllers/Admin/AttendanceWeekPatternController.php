<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Exceptions\HttpException;
use App\Models\AttendanceWeekPattern;
use App\Services\AuditService;

/**
 * Admin CRUD for weekly schedule patterns (full/half/off per weekday),
 * resolved per employee by AttendanceCalculationService::resolveWeekPattern()
 * with precedence: designation.is_manager_or_above > department.week_pattern_id
 * > company default. See database/migrations/2026_09_18_weekly_schedule_patterns.sql.
 */
class AttendanceWeekPatternController extends Controller
{
    private AttendanceWeekPattern $patterns;
    private const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    private const TYPES = ['full', 'half', 'off'];

    public function __construct($request, $response)
    {
        parent::__construct($request, $response);
        $this->patterns = new AttendanceWeekPattern();
    }

    public function index(): void
    {
        $this->authorize('attendance.pattern.view');
        $companyId = $this->tenant->resolveCompanyId($this->request->input('company_id'));

        $this->view('admin.week-patterns.index', [
            'title' => 'Weekly Schedule Patterns',
            'patterns' => $companyId ? $this->patterns->forCompany($companyId) : [],
            'companies' => $this->tenant->companies(),
            'companyId' => $companyId,
        ]);
    }

    public function create(): void
    {
        $this->authorize('attendance.pattern.manage');
        $this->view('admin.week-patterns.form', [
            'title' => 'Add Weekly Pattern',
            'pattern' => null,
            'companies' => $this->tenant->companies(true),
        ]);
    }

    public function store(): void
    {
        $this->authorize('attendance.pattern.manage');
        $companyId = (int) $this->request->input('company_id', 0);
        $this->tenant->assertCompany($companyId);

        $data = $this->validate([
            'company_id' => 'required|exists:companies,id',
            'name' => 'required|min:2|max:150',
        ]);
        $data = array_merge($data, $this->dayTypePayload());
        $data['created_by'] = $this->auth->id();

        $id = $this->patterns->create($data);
        $this->applyFlags($id, $companyId);

        (new AuditService())->log('create', 'attendance_week_patterns', $id, null, $data);
        flash('success', 'Weekly pattern created successfully.');
        $this->redirect('/admin/week-patterns?company_id=' . $companyId);
    }

    public function edit(int $id): void
    {
        $this->authorize('attendance.pattern.manage');
        $pattern = $this->patterns->find($id);
        if (!$pattern) {
            throw new HttpException('Weekly pattern not found.', 404);
        }
        $this->tenant->assertCompany((int) $pattern['company_id']);

        $this->view('admin.week-patterns.form', [
            'title' => 'Edit Weekly Pattern',
            'pattern' => $pattern,
            'companies' => $this->tenant->companies(true),
        ]);
    }

    public function update(int $id): void
    {
        $this->authorize('attendance.pattern.manage');
        $pattern = $this->patterns->find($id);
        if (!$pattern) {
            throw new HttpException('Weekly pattern not found.', 404);
        }
        $companyId = (int) $pattern['company_id'];
        $this->tenant->assertCompany($companyId);

        $data = $this->validate([
            'name' => 'required|min:2|max:150',
        ]);
        $data = array_merge($data, $this->dayTypePayload());

        $this->patterns->update($id, $data);
        $this->applyFlags($id, $companyId);

        (new AuditService())->log('update', 'attendance_week_patterns', $id, $pattern, $data);
        flash('success', 'Weekly pattern updated successfully.');
        $this->redirect('/admin/week-patterns?company_id=' . $companyId);
    }

    public function destroy(int $id): void
    {
        $this->authorize('attendance.pattern.manage');
        $pattern = $this->patterns->find($id);
        if (!$pattern) {
            throw new HttpException('Weekly pattern not found.', 404);
        }
        $this->tenant->assertCompany((int) $pattern['company_id']);

        if ((int) $pattern['is_default'] === 1) {
            flash('error', 'The company default pattern cannot be deleted — set a different pattern as default first.');
            $this->redirect('/admin/week-patterns?company_id=' . (int) $pattern['company_id']);
            return;
        }

        $inUse = (int) $this->db()->fetchColumn(
            'SELECT COUNT(*) FROM departments WHERE week_pattern_id = :id AND deleted_at IS NULL',
            ['id' => $id]
        );
        if ($inUse > 0) {
            flash('error', "This pattern is assigned to {$inUse} department(s) — reassign them first.");
            $this->redirect('/admin/week-patterns?company_id=' . (int) $pattern['company_id']);
            return;
        }

        $this->patterns->delete($id);
        (new AuditService())->log('delete', 'attendance_week_patterns', $id, $pattern);
        flash('success', 'Weekly pattern deleted successfully.');
        $this->redirect('/admin/week-patterns?company_id=' . (int) $pattern['company_id']);
    }

    private function dayTypePayload(): array
    {
        $data = [];
        foreach (self::DAYS as $day) {
            $value = (string) $this->request->input("{$day}_type", 'full');
            $data["{$day}_type"] = in_array($value, self::TYPES, true) ? $value : 'full';
        }
        return $data;
    }

    /**
     * is_default/is_manager_pattern are single-select-per-company flags
     * (enforced by AttendanceWeekPattern::setDefault()/setManagerPattern(),
     * which clear any previous holder), never plain boolean columns a form
     * can just toggle independently — otherwise resolveWeekPattern()'s
     * "LIMIT 1" lookups could silently pick whichever row MySQL returns
     * first among several candidates.
     */
    private function applyFlags(int $id, int $companyId): void
    {
        if ($this->request->input('is_default')) {
            $this->patterns->setDefault($id, $companyId);
        }
        if ($this->request->input('is_manager_pattern')) {
            $this->patterns->setManagerPattern($id, $companyId);
        }
    }

    private function db(): \App\Core\Database
    {
        return \App\Core\Database::getInstance();
    }
}
