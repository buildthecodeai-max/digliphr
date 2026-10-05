<?php
/**
 * Shared KPI metrics row. Callers pass $summary (required) keyed by metric
 * key => ['value'=>, 'change_pct'=>?, 'sub'=>?, 'spark'=>?]. $metricMeta is
 * optional — pass a custom map to reuse this component for any KPI set
 * (see views/admin/reports/leave.php for a non-default example); omit it to
 * get the original executive-summary card set unchanged.
 *
 * Per item:
 *   change_pct — omit/null to hide the delta chip entirely (no "— vs prior"
 *                placeholder); a real prior-period comparison must exist.
 *   sub        — optional secondary line under the value (e.g. "42.5 days").
 *   spark      — optional array of numeric points; rendered via
 *                sparkline_svg(), silently skipped when <2 points.
 *
 * @var array $summary
 */
$defaultMetricMeta = [
    'total_employees' => ['label' => 'Total Employees', 'href' => '/admin/reports/employees', 'fmt' => 'int', 'tone' => 'purple', 'icon' => 'users'],
    'present' => ['label' => 'Present', 'href' => '/admin/reports/attendance?status=present', 'fmt' => 'int', 'tone' => 'mint', 'icon' => 'user-check'],
    'absent' => ['label' => 'Absent', 'href' => '/admin/reports/attendance', 'fmt' => 'int', 'tone' => 'red', 'icon' => 'user-x'],
    'on_leave' => ['label' => 'On Leave', 'href' => '/admin/reports/leave', 'fmt' => 'int', 'tone' => 'orange', 'icon' => 'calendar-off'],
    'late' => ['label' => 'Late Arrivals', 'href' => '/admin/reports/attendance', 'fmt' => 'int', 'tone' => 'yellow', 'icon' => 'alarm-clock'],
    'remote' => ['label' => 'Remote / WFH', 'href' => '/admin/reports/attendance?status=remote', 'fmt' => 'int', 'tone' => 'blue', 'icon' => 'wifi'],
    'early_departures' => ['label' => 'Early Departures', 'href' => '/admin/reports/attendance', 'fmt' => 'int', 'tone' => 'pink', 'icon' => 'log-out'],
    'overtime_hours' => ['label' => 'Overtime Hours', 'href' => '/admin/overtime', 'fmt' => 'decimal', 'tone' => 'cyan', 'icon' => 'timer'],
    'attendance_pct' => ['label' => 'Attendance %', 'href' => '/admin/reports/attendance', 'fmt' => 'pct', 'tone' => 'mint', 'icon' => 'percent'],
    'payroll_gross' => ['label' => 'Payroll Gross', 'href' => '/admin/reports/payroll', 'fmt' => 'money', 'tone' => 'purple', 'icon' => 'wallet'],
    'payroll_deductions' => ['label' => 'Deductions', 'href' => '/admin/reports/payroll', 'fmt' => 'money', 'tone' => 'blue', 'icon' => 'minus-circle'],
    'pending_leaves' => ['label' => 'Pending Leave', 'href' => '/admin/leave', 'fmt' => 'int', 'tone' => 'orange', 'icon' => 'hourglass'],
    'outstanding_loans_advances' => ['label' => 'Outstanding Loans/Advances', 'href' => '/admin/reports/loans', 'fmt' => 'money', 'tone' => 'blue', 'icon' => 'landmark'],
];
$metricMeta = $metricMeta ?? $defaultMetricMeta;
// Column classes: default keeps the existing executive-summary grid (up to
// 12 items) unchanged. Pass e.g. 'col-12 col-md-6 col-lg-3' for an exact
// 1/2/4-column responsive grid when the caller has a fixed, small item count.
$metricColClass = $metricColClass ?? 'col-6 col-md-4 col-xl-3';

if (!function_exists('ems_format_metric')) {
    function ems_format_metric($value, string $fmt): string
    {
        return match ($fmt) {
            'money' => format_money((float) $value),
            'pct' => number_format((float) $value, 1) . '%',
            'decimal' => number_format((float) $value, 1),
            default => number_format((float) $value),
        };
    }
}
?>
<div class="row g-2 metrics-row report-metrics-row mb-3">
    <?php foreach ($metricMeta as $key => $meta): ?>
        <?php if (!isset($summary[$key])) {
            continue;
        } ?>
        <?php
        $item = $summary[$key];
        $hasChange = isset($item['change_pct']);
        $change = $hasChange ? (float) $item['change_pct'] : null;
        $dir = $change === null ? 'flat' : ($change > 0 ? 'up' : ($change < 0 ? 'down' : 'flat'));
        $href = null;
        if (!empty($meta['href'])) {
            $qs = http_build_query(array_filter([
                'from' => $filters['from'] ?? null,
                'to' => $filters['to'] ?? null,
                'branch_id' => $filters['branch_id'] ?? null,
                'department_id' => $filters['department_id'] ?? null,
            ]));
            $href = $meta['href'] . (str_contains($meta['href'], '?') ? '&' : '?') . $qs;
        }
        $tag = $href ? 'a' : 'div';
        $spark = !empty($item['spark']) ? sparkline_svg($item['spark']) : '';
        ?>
        <div class="<?= e($metricColClass) ?> stagger-item">
            <<?= $tag ?> class="metric-card tone-<?= e($meta['tone']) ?><?= $href ? ' clickable' : '' ?> text-decoration-none d-block" <?= $href ? 'href="' . e($href) . '"' : '' ?> title="<?= e($meta['label']) ?>">
                <div class="metric-icon"><i data-lucide="<?= e($meta['icon']) ?>"></i></div>
                <div class="metric-label"><?= e($meta['label']) ?></div>
                <div class="metric-value" data-count-to="<?= e((string) (int) $item['value']) ?>"><?= e(ems_format_metric($item['value'], $meta['fmt'])) ?></div>
                <?php if (!empty($item['sub'])): ?>
                    <div class="metric-sub"><?= e($item['sub']) ?></div>
                <?php endif; ?>
                <?php if ($hasChange): ?>
                <div class="metric-delta <?= e($dir) ?>">
                    <?php if ($dir === 'up'): ?><i data-lucide="trending-up" style="width:12px;height:12px"></i>
                    <?php elseif ($dir === 'down'): ?><i data-lucide="trending-down" style="width:12px;height:12px"></i>
                    <?php else: ?><i data-lucide="minus" style="width:12px;height:12px"></i><?php endif; ?>
                    <?= e(abs((float) $change) . '%') ?> vs prior
                </div>
                <?php endif; ?>
                <?php if ($spark): ?>
                    <div class="metric-spark"><?= $spark ?></div>
                <?php endif; ?>
            </<?= $tag ?>>
        </div>
    <?php endforeach; ?>
</div>
<script>if (window.lucide) lucide.createIcons();</script>
