<?php
/** @var array $daily */
/** @var array $dailyTable */
$date = $date ?? date('Y-m-d');
$t = $daily['totals'] ?? [];
?>
<div class="card ems-card mb-3">
    <div class="card-body">
        <form method="get" class="d-flex align-items-end gap-2 flex-wrap">
            <input type="hidden" name="section" value="daily">
            <?php foreach (['branch_id', 'department_id', 'employee_id'] as $k): if (!empty($filters[$k])): ?>
                <input type="hidden" name="<?= $k ?>" value="<?= e((string) $filters[$k]) ?>">
            <?php endif; endforeach; ?>
            <div>
                <label class="form-label small mb-1">Date</label>
                <input type="date" name="date" value="<?= e($date) ?>" class="form-control form-control-sm">
            </div>
            <button class="btn btn-sm btn-primary">View Day</button>
        </form>
    </div>
</div>

<div class="row g-2 metrics-row mb-3">
    <?php foreach ([
        ['Total Employees', $t['employee_count'] ?? 0, 'purple', 'users'],
        ['Present', $t['present_days'] ?? 0, 'mint', 'user-check'],
        ['Absent', $t['absent_days'] ?? 0, 'red', 'user-x'],
        ['Leave', $t['leave_days'] ?? 0, 'orange', 'calendar-off'],
        ['Late', $t['late_days'] ?? 0, 'yellow', 'alarm-clock'],
        ['Half Day', $t['half_days'] ?? 0, 'cyan', 'sun-half'],
        ['Not Checked In', $t['not_checked_in_today'] ?? 0, 'pink', 'user-round-x'],
    ] as [$label, $value, $tone, $icon]): ?>
        <div class="col-6 col-md-3 col-xl-auto stagger-item" style="min-width:150px">
            <div class="metric-card tone-<?= e($tone) ?>">
                <div class="metric-icon"><i data-lucide="<?= e($icon) ?>"></i></div>
                <div class="metric-label"><?= e($label) ?></div>
                <div class="metric-value"><?= number_format((float) $value, 1) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<script>if (window.lucide) lucide.createIcons();</script>

<div class="card ems-card">
    <div class="card-header d-flex justify-content-between">
        <span class="report-card-title"><span class="rct-icon"><i data-lucide="calendar-days"></i></span>Employees — <?= e(format_date($date)) ?></span>
        <span class="small text-muted"><?= (int) ($dailyTable['total'] ?? 0) ?> records</span>
    </div>
    <div class="report-table-shell">
        <table class="table table-sm mb-0 align-middle">
            <thead><tr><th>Employee</th><th>Department</th><th>Branch</th><th>Shift</th><th>Check-In</th><th>Check-Out</th><th>Status</th><th>Late By</th><th>Worked</th><th>OT</th><th>Source</th></tr></thead>
            <tbody>
            <?php if (empty($dailyTable['data'])): ?>
                <tr><td colspan="11"><div class="empty-state mb-0 py-4">No attendance records for this date.</div></td></tr>
            <?php else: foreach ($dailyTable['data'] as $r): ?>
                <tr>
                    <td><div class="fw-semibold"><?= e($r['employee_name']) ?></div><div class="small text-muted"><?= e($r['employee_code']) ?></div></td>
                    <td><?= e($r['department_name'] ?? '—') ?></td>
                    <td><?= e($r['branch_name'] ?? '—') ?></td>
                    <td><?= e($r['shift_name'] ?? '—') ?></td>
                    <td><?= e($r['check_in_at'] ? date(config('app.time_format', 'g:i A'), strtotime($r['check_in_at'])) : '—') ?></td>
                    <td><?= e($r['check_out_at'] ? date(config('app.time_format', 'g:i A'), strtotime($r['check_out_at'])) : '—') ?></td>
                    <td><?= status_badge($r['status']) ?></td>
                    <td><?= (int) $r['late_minutes'] > 0 ? $r['late_minutes'] . 'm' : '—' ?></td>
                    <td><?= e(format_minutes((int) $r['work_minutes'])) ?></td>
                    <td><?= e(format_minutes((int) $r['overtime_minutes'])) ?></td>
                    <td><?= e($r['source'] ?? '—') ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
