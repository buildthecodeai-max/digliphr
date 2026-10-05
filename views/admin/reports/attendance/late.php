<?php
/** @var array $late */
$rows = $late['rows'] ?? [];
$s = $late['summary'] ?? [];
?>
<div class="row g-2 metrics-row mb-3">
    <?php foreach ([
        ['Total Late Employees', $s['total_late_employees'] ?? 0, 'red', 'user-x'],
        ['Total Late Occurrences', $s['total_occurrences'] ?? 0, 'yellow', 'alarm-clock'],
        ['Total Late Minutes', $s['total_late_minutes'] ?? 0, 'orange', 'timer'],
    ] as [$label, $value, $tone, $icon]): ?>
        <div class="col-6 col-md-4 stagger-item">
            <div class="metric-card tone-<?= e($tone) ?>">
                <div class="metric-icon"><i data-lucide="<?= e($icon) ?>"></i></div>
                <div class="metric-label"><?= e($label) ?></div>
                <div class="metric-value"><?= (int) $value ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<script>if (window.lucide) lucide.createIcons();</script>

<div class="card ems-card">
    <div class="card-header report-card-title"><span class="rct-icon"><i data-lucide="alarm-clock"></i></span>Late Arrivals <span class="small text-muted fw-normal ms-1">— using each shift's configured grace period</span></div>
    <div class="report-table-shell">
        <table class="table table-sm mb-0 align-middle">
            <thead><tr><th>Employee</th><th>Date</th><th>Department</th><th>Branch</th><th>Shift</th><th>Scheduled In</th><th>Actual In</th><th>Late By</th><th>Grace</th><th>Effective Late</th></tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="10"><div class="empty-state mb-0 py-4">No late arrivals in the selected period.</div></td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td><div class="fw-semibold"><?= e($r['employee_name']) ?></div><div class="small text-muted"><?= e($r['employee_code']) ?></div></td>
                    <td><?= e(format_date($r['attendance_date'])) ?></td>
                    <td><?= e($r['department_name'] ?? '—') ?></td>
                    <td><?= e($r['branch_name'] ?? '—') ?></td>
                    <td><?= e($r['shift_name'] ?? '—') ?></td>
                    <td><?= e($r['scheduled_in'] ? date(config('app.time_format', 'g:i A'), strtotime($r['scheduled_in'])) : '—') ?></td>
                    <td><?= e($r['check_in_at'] ? date(config('app.time_format', 'g:i A'), strtotime($r['check_in_at'])) : '—') ?></td>
                    <td><?= (int) $r['late_minutes'] ?>m</td>
                    <td><?= (int) $r['grace_minutes'] ?>m</td>
                    <td class="fw-semibold"><?= (int) $r['effective_late_minutes'] ?>m</td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
