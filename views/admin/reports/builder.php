<?php
$reportCards = [
    'attendance' => ['title' => 'Attendance & time', 'text' => 'Daily attendance, late arrivals, work hours, overtime, and attendance status.', 'icon' => 'calendar-check'],
    'leave' => ['title' => 'Leave & balances', 'text' => 'Leave requests, balances, approvals, and usage by department.', 'icon' => 'calendar-off'],
    'payroll' => ['title' => 'Payroll cost', 'text' => 'Gross pay, deductions, overtime, tax, loans, and net salary.', 'icon' => 'wallet'],
    'workforce' => ['title' => 'Workforce & compliance', 'text' => 'Headcount, joiners, exits, profile completeness, birthdays, and anniversaries.', 'icon' => 'users'],
    'loans' => ['title' => 'Loans & advances', 'text' => 'Outstanding balances, repayments, installments, and employee advances.', 'icon' => 'landmark'],
    'documents' => ['title' => 'Documents', 'text' => 'Document register, categories, expiry status, and compliance records.', 'icon' => 'folder-check'],
];
?>
<div class="report-builder-hero mb-3">
    <div>
        <span class="report-kicker"><i data-lucide="sparkles"></i> HR reporting workspace</span>
        <h1>Create a report from live HR data</h1>
        <p>Choose the report, reporting period, and people filters. The result opens as a live table that you can export to CSV.</p>
    </div>
    <a href="/admin/reports" class="btn btn-soft"><i data-lucide="layout-dashboard" class="me-1"></i>Report overview</a>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <form method="get" action="/admin/reports/builder" class="card ems-card report-builder-form">
            <div class="card-header"><strong>1. Choose a report</strong><span class="small text-muted">Every report uses existing EMS records</span></div>
            <div class="card-body">
                <div class="report-type-grid">
                    <?php foreach ($reportCards as $key => $card): ?>
                        <label class="report-type-card">
                            <input type="radio" name="report" value="<?= e($key) ?>" <?= $key === 'attendance' ? 'checked' : '' ?> required>
                            <span class="report-type-icon"><i data-lucide="<?= e($card['icon']) ?>"></i></span>
                            <span><strong><?= e($card['title']) ?></strong><small><?= e($card['text']) ?></small></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="card-header"><strong>2. Set the scope</strong><span class="small text-muted">Use a preset or a custom range</span></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label" for="period">Reporting period</label><select name="period" class="form-select" id="period"><?php foreach (['this_month' => 'This month', 'last_month' => 'Last month', 'this_quarter' => 'This quarter', 'last_quarter' => 'Last quarter', 'this_year' => 'This year', 'last_year' => 'Last year', 'custom' => 'Custom range'] as $key => $label): ?><option value="<?= e($key) ?>" <?= ($filters['period'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-4"><label class="form-label" for="from">From</label><input type="date" class="form-control" name="from" id="from" value="<?= e($filters['from'] ?? '') ?>"></div>
                    <div class="col-md-4"><label class="form-label" for="to">To</label><input type="date" class="form-control" name="to" id="to" value="<?= e($filters['to'] ?? '') ?>"></div>
                    <div class="col-md-4"><label class="form-label" for="branch_id">Branch</label><select name="branch_id" class="form-select" id="branch_id"><option value="">All branches</option><?php foreach ($options['branches'] as $branch): ?><option value="<?= (int) $branch['id'] ?>"><?= e($branch['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-4"><label class="form-label" for="department_id">Department</label><select name="department_id" class="form-select" id="department_id"><option value="">All departments</option><?php foreach ($options['departments'] as $department): ?><option value="<?= (int) $department['id'] ?>"><?= e($department['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-4"><label class="form-label" for="employee_id">Employee</label><select name="employee_id" class="form-select" id="employee_id"><option value="">All employees</option><?php foreach ($options['employees'] as $employee): ?><option value="<?= (int) $employee['id'] ?>"><?= e($employee['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-4"><label class="form-label" for="view">Initial view</label><select name="view" class="form-select" id="view"><option value="table">Data table</option><option value="combined">Table and charts</option><option value="graphical">Charts</option></select></div>
                </div>
            </div>
            <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span class="small text-muted"><i data-lucide="shield-check" class="me-1"></i>CNIC/ID numbers are never included in report exports.</span>
                <button class="btn btn-primary" type="submit"><i data-lucide="file-bar-chart-2" class="me-1"></i>Generate report</button>
            </div>
        </form>
    </div>
    <div class="col-lg-4">
        <div class="card ems-card h-100">
            <div class="card-header"><strong>Saved report views</strong></div>
            <div class="card-body">
                <?php if (empty($savedReports)): ?>
                    <div class="empty-state py-4"><i data-lucide="bookmark"></i><strong>No saved report views yet</strong><span>Save a filtered report from the overview to reuse it here.</span></div>
                <?php else: foreach ($savedReports as $saved): $savedFilters = json_decode((string) $saved['filters'], true) ?: []; ?>
                    <a class="saved-report-link" href="/admin/reports?<?= e(http_build_query($savedFilters)) ?>"><span class="saved-report-icon"><i data-lucide="bookmark"></i></span><span><strong><?= e($saved['name']) ?></strong><small><?= e($savedFilters['period'] ?? 'Custom view') ?></small></span><i data-lucide="arrow-up-right"></i></a>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var period = document.getElementById('period'), from = document.getElementById('from'), to = document.getElementById('to');
    function syncDates() { var custom = period.value === 'custom'; from.disabled = !custom; to.disabled = !custom; }
    period.addEventListener('change', syncDates); syncDates();
    if (window.lucide) lucide.createIcons();
});
</script>
