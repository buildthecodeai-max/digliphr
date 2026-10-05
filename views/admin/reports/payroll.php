<?php
$action = '/admin/reports/payroll';
$exportType = 'payroll';
$showPeriod = true;
$periodId = $analytics['period_id'] ?? null;
include config('app.paths.views') . '/partials/report-header.php';
include config('app.paths.views') . '/partials/report-filters.php';
$s = $analytics['summary'] ?? [];
$showCharts = in_array($viewMode, ['graphical', 'combined'], true);
$showTables = in_array($viewMode, ['table', 'combined'], true);
?>
<?php if ($showCharts): ?>
<div class="row g-3 mb-3">
    <div class="col-lg-7"><div class="card ems-card"><div class="card-header">Monthly Payroll Trend</div><div class="card-body chart-panel"><canvas id="payTrend"></canvas></div></div></div>
    <div class="col-lg-5"><div class="card ems-card"><div class="card-header">Salary Composition</div><div class="card-body chart-panel"><canvas id="payComp"></canvas></div></div></div>
    <div class="col-12"><div class="card ems-card"><div class="card-header">Payroll by Department</div><div class="card-body chart-panel"><canvas id="payDept"></canvas></div></div></div>
</div>
<?php endif; ?>

<?php if ($showTables): ?>
<div class="card ems-card">
    <div class="card-header">Payroll Detail</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr><th>Employee</th><th>Dept</th><th>Basic</th><th>OT</th><th>Gross</th><th>Deductions</th><th>Loan</th><th>Advance</th><th>Tax</th><th>Net</th><th>Status</th></tr></thead>
            <tbody>
            <?php if (empty($analytics['rows'])): ?>
                <tr><td colspan="11" class="text-center text-muted py-4">No payroll records for the selected period.</td></tr>
            <?php else: foreach ($analytics['rows'] as $r): ?>
                <tr>
                    <td><div class="fw-semibold"><?= e($r['employee_name']) ?></div><div class="small text-muted"><?= e($r['employee_code']) ?></div></td>
                    <td><?= e($r['department_name'] ?? '—') ?></td>
                    <td><?= e(format_money($r['basic_salary'])) ?></td>
                    <td><?= e(format_money($r['overtime_amount'])) ?></td>
                    <td><?= e(format_money($r['gross_earnings'])) ?></td>
                    <td><?= e(format_money($r['total_deductions'])) ?></td>
                    <td><?= e(format_money($r['loan_deduction'])) ?></td>
                    <td><?= e(format_money($r['advance_deduction'])) ?></td>
                    <td><?= e(format_money($r['tax_amount'])) ?></td>
                    <td class="fw-semibold"><?= e(format_money($r['net_salary'])) ?></td>
                    <td><?= status_badge($r['status']) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function(){
    const a = <?= json_encode($analytics, JSON_UNESCAPED_UNICODE) ?>;
    if (!window.EMSCharts) return;
    EMSCharts.line('payTrend', (a.trend||[]).map(r=>r.label), [
        {label:'Gross', data:(a.trend||[]).map(r=>Number(r.gross))},
        {label:'Net', data:(a.trend||[]).map(r=>Number(r.net))},
        {label:'Deductions', data:(a.trend||[]).map(r=>Number(r.deductions))},
    ]);
    EMSCharts.doughnut('payComp', a.composition?.labels||[], a.composition?.values||[]);
    EMSCharts.bar('payDept', (a.by_department||[]).map(r=>r.department), [{label:'Net', data:(a.by_department||[]).map(r=>Number(r.net))}], true);
});
</script>
