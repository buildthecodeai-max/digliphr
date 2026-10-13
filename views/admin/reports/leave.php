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
<?php
/* Group balances by employee */
$grouped = [];
foreach ($balances as $b) {
    $key = $b['employee_name'];
    $grouped[$key][] = $b;
}
?>
<div class="d-flex align-items-center justify-content-between mb-2 mt-1">
    <h6 class="mb-0 fw-semibold" style="font-size:.95rem;letter-spacing:.01em">
        <i data-lucide="wallet" style="width:15px;height:15px" class="me-1 text-primary"></i>
        Leave Balance Overview
    </h6>
    <span class="badge text-bg-secondary"><?= count($grouped) ?> employee<?= count($grouped) !== 1 ? 's' : '' ?></span>
</div>

<?php if (empty($grouped)): ?>
<div class="card mb-3">
    <div class="p-2">
        <?php ui('empty-state', [
            'icon' => 'wallet',
            'title' => 'No leave balances found',
            'text' => 'Balances for the selected year will appear here once accrual runs.',
        ]); ?>
    </div>
</div>
<?php else: ?>
<div class="row g-3 mb-3">
<?php foreach ($grouped as $empName => $rows):
    $initials   = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(explode(' ', trim($empName)), 0, 2)));
    $empId      = (int) ($rows[0]['employee_id'] ?? 0);
    $empYear    = (int) ($rows[0]['year'] ?? date('Y'));
    $balanceUrl = '/admin/leave/balances?employee_id=' . $empId . '&year=' . $empYear;
    $totalUsed    = array_sum(array_column($rows, 'used'));
    $totalOpening = array_sum(array_column($rows, 'opening_balance'));
    $totalClosing = array_sum(array_column($rows, 'closing_balance'));
    $closingClass = $totalClosing < 0 ? 'text-danger' : ($totalClosing > 0 ? 'text-success' : 'text-muted');
?>
<div class="col-lg-6 col-xl-4">
    <a href="<?= e($balanceUrl) ?>" class="text-decoration-none d-block h-100">
    <div class="card h-100 shadow-sm leave-balance-card" style="border-top:3px solid var(--ems-primary,#3b82f6);transition:box-shadow .15s,transform .15s;cursor:pointer">
        <!-- Employee header -->
        <div class="card-body pb-2 pt-3">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-white flex-shrink-0"
                     style="width:42px;height:42px;font-size:.85rem;background:var(--ems-primary,#3b82f6)">
                    <?= e($initials) ?>
                </div>
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-semibold text-truncate" style="font-size:.95rem"><?= e($empName) ?></div>
                    <?php if (!empty($rows[0]['employee_code'])): ?>
                    <div class="text-muted small"><?= e($rows[0]['employee_code']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="text-end flex-shrink-0">
                    <div class="small text-muted">Closing</div>
                    <div class="fw-bold fs-6 <?= $closingClass ?>"><?= number_format($totalClosing, 1) ?></div>
                </div>
            </div>

            <!-- Leave type rows -->
            <div style="border-top:1px solid var(--ems-border,#e2e8f0)">
            <?php foreach ($rows as $b):
                $opening = (float)$b['opening_balance'];
                $used    = (float)$b['used'];
                $pending = (float)$b['pending'];
                $closing = (float)$b['closing_balance'];
                $usedPct = $opening > 0 ? min(100, round($used / $opening * 100)) : 0;
                $closingColor = $closing < 0 ? '#ef4444' : ($closing > 0 ? '#10b981' : 'var(--ems-text-muted,#6b7280)');
            ?>
            <div class="py-2" style="border-bottom:1px solid var(--ems-border,#e2e8f0)">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="small fw-semibold"><?= e($b['leave_type']) ?></span>
                    <span class="small fw-bold" style="color:<?= $closingColor ?>"><?= number_format($closing, 1) ?> left</span>
                </div>
                <div class="d-flex gap-3 small text-muted mb-1" style="font-size:.75rem">
                    <span>Opening <strong class="text-body"><?= number_format($opening, 1) ?></strong></span>
                    <span>Used <strong class="text-danger"><?= number_format($used, 1) ?></strong></span>
                    <?php if ($pending > 0): ?>
                    <span>Pending <strong class="text-warning"><?= number_format($pending, 1) ?></strong></span>
                    <?php endif; ?>
                </div>
                <?php if ($opening > 0): ?>
                <div class="progress" style="height:4px;border-radius:2px;background:var(--ems-border,#e2e8f0)">
                    <div class="progress-bar <?= $usedPct >= 100 ? 'bg-danger' : ($usedPct >= 75 ? 'bg-warning' : 'bg-primary') ?>"
                         style="width:<?= $usedPct ?>%;border-radius:2px"></div>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            </div>

            <!-- Footer totals -->
            <div class="d-flex justify-content-between mt-2 pt-1 small text-muted">
                <span>Total opening: <strong class="text-body"><?= number_format($totalOpening, 1) ?></strong></span>
                <span>Total used: <strong class="text-danger"><?= number_format($totalUsed, 1) ?></strong></span>
            </div>
        </div>
    </div>
    </a>
</div>
<?php endforeach; ?>
</div>
<style>
.leave-balance-card:hover {
    box-shadow: 0 6px 20px rgba(0,0,0,.12) !important;
    transform: translateY(-2px);
}
a:hover .leave-balance-card .fw-semibold { color: var(--ems-primary, #3b82f6); }
</style>
<?php endif; ?>
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
