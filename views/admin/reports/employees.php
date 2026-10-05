<?php
$action = '/admin/reports/employees';
$exportType = 'employees';
include config('app.paths.views') . '/partials/report-header.php';
include config('app.paths.views') . '/partials/report-filters.php';
$showCharts = in_array($viewMode, ['graphical', 'combined'], true);
$showTables = in_array($viewMode, ['table', 'combined'], true);
$periodAnalytics = $periodAnalytics ?? [];
$compliance = $periodAnalytics['compliance'] ?? [];
?>
<?php if ($showCharts): ?>
<div class="row g-3 mb-3">
    <div class="col-md-6"><div class="card ems-card"><div class="card-header">Active vs Status</div><div class="card-body chart-panel"><canvas id="wfStatus"></canvas></div></div></div>
    <div class="col-md-6"><div class="card ems-card"><div class="card-header">Employment Type</div><div class="card-body chart-panel"><canvas id="wfType"></canvas></div></div></div>
    <div class="col-md-6"><div class="card ems-card"><div class="card-header">By Department</div><div class="card-body chart-panel"><canvas id="wfDept"></canvas></div></div></div>
    <div class="col-md-6"><div class="card ems-card"><div class="card-header">Joiner Growth</div><div class="card-body chart-panel"><canvas id="wfGrowth"></canvas></div></div></div>
</div>
<?php endif; ?>

<?php if ($showTables): ?>
<div class="card ems-card mb-3">
    <div class="card-header">Upcoming birthdays & work anniversaries</div>
    <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Employee</th><th>Event</th><th>Date</th><th>In</th></tr></thead><tbody>
    <?php if (empty($periodAnalytics['events'])): ?><tr><td colspan="4" class="text-center text-muted py-3">No upcoming employee events in the next 90 days.</td></tr>
    <?php else: foreach ($periodAnalytics['events'] as $event): ?><tr><td><a href="/admin/employees/<?= (int) $event['employee_id'] ?>"><?= e($event['employee_name']) ?></a></td><td><?= e($event['type']) ?></td><td><?= e(format_date($event['date'])) ?></td><td><?= (int) $event['days_away'] ?> day<?= (int) $event['days_away'] === 1 ? '' : 's' ?></td></tr><?php endforeach; endif; ?>
    </tbody></table></div>
</div>
<div class="card ems-card">
    <div class="card-header">Department Headcount</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr><th>Department</th><th>Employees</th></tr></thead>
            <tbody>
            <?php foreach ($analytics['byDepartment'] as $r): ?>
                <tr><td><?= e($r['label']) ?></td><td><?= (int)$r['total'] ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function(){
    const a = <?= json_encode($analytics, JSON_UNESCAPED_UNICODE) ?>;
    if (!window.EMSCharts) return;
    EMSCharts.doughnut('wfStatus', (a.byStatus||[]).map(r=>r.label), (a.byStatus||[]).map(r=>Number(r.total)));
    EMSCharts.doughnut('wfType', (a.byType||[]).map(r=>r.label), (a.byType||[]).map(r=>Number(r.total)));
    EMSCharts.bar('wfDept', (a.byDepartment||[]).map(r=>r.label), [{label:'Headcount', data:(a.byDepartment||[]).map(r=>Number(r.total))}], true);
    EMSCharts.line('wfGrowth', (a.growth||[]).map(r=>r.label), [{label:'Joiners', data:(a.growth||[]).map(r=>Number(r.total))}]);
});
</script>
