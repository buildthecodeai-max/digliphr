<?php
$action = '/admin/reports/loans';
$exportType = 'loans';
include config('app.paths.views') . '/partials/report-header.php';
include config('app.paths.views') . '/partials/report-filters.php';
$L = $analytics['loans'] ?? [];
$A = $analytics['advances'] ?? [];
$showCharts = in_array($viewMode, ['graphical', 'combined'], true);
$showTables = in_array($viewMode, ['table', 'combined'], true);
?>
<?php if ($showCharts): ?>
<div class="row g-3 mb-3">
    <div class="col-md-6"><div class="card ems-card"><div class="card-header">Loan Status</div><div class="card-body chart-panel"><canvas id="loanStatus"></canvas></div></div></div>
    <div class="col-md-6"><div class="card ems-card"><div class="card-header">Loan Types</div><div class="card-body chart-panel"><canvas id="loanTypes"></canvas></div></div></div>
</div>
<?php endif; ?>

<?php if ($showTables): ?>
<div class="card ems-card">
    <div class="card-header">Employee Loans</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr><th>Employee</th><th>Number</th><th>Type</th><th>Principal</th><th>Paid</th><th>Outstanding</th><th>EMI</th><th>Status</th></tr></thead>
            <tbody>
            <?php if (empty($analytics['rows'])): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No loan records found.</td></tr>
            <?php else: foreach ($analytics['rows'] as $r): ?>
                <tr>
                    <td><?= e($r['employee_name']) ?></td>
                    <td><?= e($r['loan_number']) ?></td>
                    <td><?= e(ucfirst((string)$r['loan_type'])) ?></td>
                    <td><?= e(format_money($r['principal_amount'])) ?></td>
                    <td><?= e(format_money($r['paid_amount'])) ?></td>
                    <td><?= e(format_money($r['remaining_amount'])) ?></td>
                    <td><?= e(format_money($r['installment_amount'])) ?></td>
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
    EMSCharts.doughnut('loanStatus', (a.status||[]).map(r=>r.status), (a.status||[]).map(r=>Number(r.total)));
    EMSCharts.bar('loanTypes', (a.types||[]).map(r=>r.loan_type), [{label:'Amount', data:(a.types||[]).map(r=>Number(r.amount))}]);
});
</script>
