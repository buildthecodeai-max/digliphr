<?php
$action = '/admin/reports';
$exportType = 'attendance';
include config('app.paths.views') . '/partials/report-header.php';
if (!empty($filters['company_id'])) {
    $savedModule = 'reports';
    $currentFilters = $filters;
    include config('app.paths.views') . '/partials/saved-filters.php';
}
?>

<nav class="report-quick-links mb-3" aria-label="Quick report links">
    <a href="/admin/reports/attendance"><i data-lucide="calendar-check"></i>Attendance</a>
    <a href="/admin/reports/leave"><i data-lucide="calendar-off"></i>Leave</a>
    <a href="/admin/reports/payroll"><i data-lucide="wallet"></i>Payroll</a>
    <a href="/admin/reports/employees"><i data-lucide="users"></i>Workforce</a>
    <a href="/admin/reports/loans"><i data-lucide="landmark"></i>Loans</a>
    <a href="/admin/reports/documents"><i data-lucide="folder-check"></i>Documents</a>
</nav>
<?php
include config('app.paths.views') . '/partials/report-filters.php';

$showCharts = in_array($viewMode, ['graphical', 'combined'], true);
$showTables = in_array($viewMode, ['table', 'combined'], true);
?>

<?php if ($showCharts): ?>
<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card ems-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Attendance Trend</span>
                <span class="small text-muted">Present / Absent / Leave / Late</span>
            </div>
            <div class="card-body chart-panel"><canvas id="chartAttendanceTrend" aria-label="Attendance trend chart"></canvas></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card ems-card h-100">
            <div class="card-header">Status Distribution</div>
            <div class="card-body chart-panel"><canvas id="chartStatusDonut" aria-label="Attendance status distribution"></canvas></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card ems-card h-100">
            <div class="card-header">Attendance by Department</div>
            <div class="card-body chart-panel"><canvas id="chartDeptBars" aria-label="Department attendance"></canvas></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card ems-card h-100">
            <div class="card-header">Leave Type Distribution</div>
            <div class="card-body chart-panel"><canvas id="chartLeaveTypes" aria-label="Leave types"></canvas></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card ems-card h-100">
            <div class="card-header">Payroll Trend</div>
            <div class="card-body chart-panel"><canvas id="chartPayrollTrend" aria-label="Payroll trend"></canvas></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card ems-card h-100">
            <div class="card-header">Workforce by Department</div>
            <div class="card-body chart-panel"><canvas id="chartWorkforce" aria-label="Workforce headcount"></canvas></div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($showTables): ?>
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card ems-card">
            <div class="card-header">Workforce & compliance · <?= e($filters['period_label'] ?? '') ?></div>
            <div class="card-body small">
                <div class="d-flex justify-content-between py-1 border-bottom"><span>New hires</span><strong><?= (int) ($workforcePeriod['new_hires'] ?? 0) ?></strong></div>
                <div class="d-flex justify-content-between py-1 border-bottom"><span>Exits</span><strong><?= (int) ($workforcePeriod['exits'] ?? 0) ?></strong></div>
                <div class="d-flex justify-content-between py-1 border-bottom"><span>Missing CNIC / ID</span><strong><?= (int) ($workforcePeriod['compliance']['missing_id'] ?? 0) ?></strong></div>
                <div class="d-flex justify-content-between py-1 border-bottom"><span>Missing date of birth</span><strong><?= (int) ($workforcePeriod['compliance']['missing_birth_date'] ?? 0) ?></strong></div>
                <div class="d-flex justify-content-between py-1"><span>Pending agreements</span><strong><?= (int) ($workforcePeriod['compliance']['pending_agreements'] ?? 0) ?></strong></div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card ems-card">
            <div class="card-header">Work Hours Snapshot</div>
            <div class="card-body small">
                <div class="d-flex justify-content-between py-1 border-bottom"><span>Avg working time</span><strong><?= e(format_minutes((int) ($workHours['avg_work_minutes'] ?? 0))) ?></strong></div>
                <div class="d-flex justify-content-between py-1 border-bottom"><span>Required hours</span><strong><?= e(format_minutes((int) ($workHours['required_minutes'] ?? 480))) ?></strong></div>
                <div class="d-flex justify-content-between py-1 border-bottom"><span>Total overtime</span><strong><?= e(format_minutes((int) ($workHours['total_overtime_minutes'] ?? 0))) ?></strong></div>
                <div class="d-flex justify-content-between py-1"><span>Missing hours</span><strong><?= e(format_minutes((int) ($workHours['missing_minutes'] ?? 0))) ?></strong></div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card ems-card">
            <div class="card-header">Loan & Advance Snapshot</div>
            <div class="card-body small">
                <div class="d-flex justify-content-between py-1 border-bottom"><span>Loan applications</span><strong><?= (int) ($loans['loans']['total'] ?? 0) ?></strong></div>
                <div class="d-flex justify-content-between py-1 border-bottom"><span>Outstanding loans</span><strong><?= e(format_money($loans['loans']['outstanding'] ?? 0)) ?></strong></div>
                <div class="d-flex justify-content-between py-1 border-bottom"><span>Advance outstanding</span><strong><?= e(format_money($loans['advances']['outstanding'] ?? 0)) ?></strong></div>
                <div class="d-flex justify-content-between py-1"><span>Overdue installments</span><strong><?= (int) ($loans['loans']['overdue_installments'] ?? 0) ?></strong></div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.lucide) lucide.createIcons();
    const trend = <?= json_encode($attendanceTrend ?? [], JSON_UNESCAPED_UNICODE) ?>;
    const dist = <?= json_encode($statusDistribution ?? [], JSON_UNESCAPED_UNICODE) ?>;
    const dept = <?= json_encode($byDepartment ?? [], JSON_UNESCAPED_UNICODE) ?>;
    const leave = <?= json_encode($leave['byType'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
    const payrollTrend = <?= json_encode($payroll['trend'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
    const workforce = <?= json_encode($workforce['byDepartment'] ?? [], JSON_UNESCAPED_UNICODE) ?>;

    if (!window.EMSCharts) return;

    if (trend.labels && trend.labels.length) {
        EMSCharts.line('chartAttendanceTrend', trend.labels, [
            { label: 'Present', data: trend.present, borderColor: EMSCharts.palette.present, backgroundColor: 'rgba(47,158,68,.12)' },
            { label: 'Absent', data: trend.absent, borderColor: EMSCharts.palette.absent, backgroundColor: 'rgba(224,49,49,.08)' },
            { label: 'On Leave', data: trend.on_leave, borderColor: EMSCharts.palette.leave, backgroundColor: 'rgba(59,91,219,.08)' },
            { label: 'Late', data: trend.late, borderColor: EMSCharts.palette.late, backgroundColor: 'rgba(230,119,0,.08)' },
        ]);
    }
    if (dist.labels && dist.labels.length) {
        EMSCharts.doughnut('chartStatusDonut', dist.labels, dist.values);
    }
    if (dept.length) {
        EMSCharts.bar('chartDeptBars', dept.map(r => r.department), [{ label: 'Attendance %', data: dept.map(r => r.attendance_pct) }], true);
    }
    if (leave.length) {
        EMSCharts.doughnut('chartLeaveTypes', leave.map(r => r.leave_type), leave.map(r => Number(r.days)));
    }
    if (payrollTrend.length) {
        EMSCharts.line('chartPayrollTrend', payrollTrend.map(r => r.label), [
            { label: 'Gross', data: payrollTrend.map(r => Number(r.gross)) },
            { label: 'Net', data: payrollTrend.map(r => Number(r.net)) },
            { label: 'Deductions', data: payrollTrend.map(r => Number(r.deductions)) },
        ]);
    }
    if (workforce.length) {
        EMSCharts.bar('chartWorkforce', workforce.map(r => r.label), [{ label: 'Employees', data: workforce.map(r => Number(r.total)) }], true);
    }
});
</script>
