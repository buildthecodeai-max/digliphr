<?php
$action = '/admin/reports/leave';
$exportType = 'leave';
include config('app.paths.views') . '/partials/report-header.php';
include config('app.paths.views') . '/partials/report-filters.php';

$showCharts = in_array($viewMode, ['graphical', 'combined'], true);
$showTables = in_array($viewMode, ['table', 'combined'], true);

$byStatus = $analytics['byStatus'] ?? [];
$byType = $analytics['byType'] ?? [];
$trend = $analytics['trend'] ?? [];
$byDepartment = $analytics['byDepartment'] ?? [];
$balances = $analytics['balances'] ?? [];

/** Renders a chart panel: canvas when data exists, a centered empty-state otherwise. */
if (!function_exists('leave_chart_panel')) {
function leave_chart_panel(string $title, string $canvasId, array $data, string $emptyText): void
{
    ?>
    <div class="card">
        <div class="card-header"><?= e($title) ?></div>
        <div class="card-body chart-panel">
            <?php if (empty($data)): ?>
                <div class="h-100 d-flex align-items-center justify-content-center">
                    <?php ui('empty-state', [
                        'icon' => 'inbox',
                        'title' => 'No leave data available',
                        'text' => $emptyText,
                        'actionLabel' => 'Adjust filters',
                        'actionHref' => '#reportFilterForm',
                    ]); ?>
                </div>
            <?php else: ?>
                <canvas id="<?= e($canvasId) ?>"></canvas>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
}
?>

<?php if ($showCharts): ?>
<div class="row g-3 mb-3">
    <div class="col-12 col-md-6 col-lg-4">
        <?php leave_chart_panel('Status Overview', 'leaveStatus', $byStatus, 'No records were found for the selected period.'); ?>
    </div>
    <div class="col-12 col-md-6 col-lg-4">
        <?php leave_chart_panel('Leave Types', 'leaveTypes', $byType, 'No leave requests match the selected filters.'); ?>
    </div>
    <div class="col-12 col-lg-4">
        <?php leave_chart_panel('Monthly Trend', 'leaveTrend', $trend, 'No applications were recorded in this range.'); ?>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12">
        <?php leave_chart_panel('Leave by Department', 'leaveDept', $byDepartment, 'No department breakdown is available for this range.'); ?>
    </div>
</div>
<?php endif; ?>

<?php if ($showTables): ?>
<div class="card mb-3">
    <div class="card-header">Leave Balance Overview</div>
    <?php if (empty($balances)): ?>
        <div class="p-2">
            <?php ui('empty-state', [
                'icon' => 'wallet',
                'title' => 'No leave balances found',
                'text' => 'Balances for the selected year will appear here once accrual runs.',
            ]); ?>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr><th>Employee</th><th>Type</th><th>Opening</th><th>Accrued</th><th>Used</th><th>Pending</th><th>Carried</th><th>Closing</th></tr></thead>
            <tbody>
            <?php foreach ($balances as $b): ?>
                <tr>
                    <td><?= e($b['employee_name']) ?></td>
                    <td><?= e($b['leave_type']) ?></td>
                    <td><?= e(number_format((float) $b['opening_balance'], 1)) ?></td>
                    <td><?= e(number_format((float) $b['accrued'], 1)) ?></td>
                    <td><?= e(number_format((float) $b['used'], 1)) ?></td>
                    <td><?= e(number_format((float) $b['pending'], 1)) ?></td>
                    <td><?= e(number_format((float) $b['carried_forward'], 1)) ?></td>
                    <td class="fw-semibold"><?= e(number_format((float) $b['closing_balance'], 1)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!window.EMSCharts) return;
    var a = <?= json_encode($analytics, JSON_UNESCAPED_UNICODE) ?>;
    var compareTrend = <?= json_encode($compareTrend ?? null, JSON_UNESCAPED_UNICODE) ?>;

    if ((a.byStatus || []).length) {
        EMSCharts.doughnut('leaveStatus', a.byStatus.map(function (r) { return r.status; }), a.byStatus.map(function (r) { return Number(r.total); }));
    }
    if ((a.byType || []).length) {
        EMSCharts.bar('leaveTypes', a.byType.map(function (r) { return r.leave_type; }), [
            { label: 'Days', data: a.byType.map(function (r) { return Number(r.days); }) },
        ]);
    }
    if ((a.trend || []).length) {
        var datasets = [
            { label: 'Applications', data: a.trend.map(function (r) { return Number(r.applications); }) },
            { label: 'Approved days', data: a.trend.map(function (r) { return Number(r.approved_days); }) },
        ];
        // Compare mode: overlay the prior period by relative position (month 1
        // vs month 1, etc) — the standard "this period vs last period" pattern,
        // not aligned by calendar label since the two ranges don't share one.
        if (compareTrend && compareTrend.length) {
            datasets.push({
                label: 'Applications (prior period)',
                data: compareTrend.map(function (r) { return Number(r.applications); }),
                borderDash: [4, 4], borderWidth: 1.5, pointRadius: 0,
                borderColor: 'rgba(152,162,179,.75)', backgroundColor: 'transparent', fill: false,
            });
            datasets.push({
                label: 'Approved days (prior period)',
                data: compareTrend.map(function (r) { return Number(r.approved_days); }),
                borderDash: [4, 4], borderWidth: 1.5, pointRadius: 0,
                borderColor: 'rgba(152,162,179,.45)', backgroundColor: 'transparent', fill: false,
            });
        }
        EMSCharts.line('leaveTrend', a.trend.map(function (r) { return r.label; }), datasets);
    }
    if ((a.byDepartment || []).length) {
        EMSCharts.bar('leaveDept', a.byDepartment.map(function (r) { return r.department; }), [
            { label: 'Days', data: a.byDepartment.map(function (r) { return Number(r.days); }) },
        ], true);
    }
});
</script>
