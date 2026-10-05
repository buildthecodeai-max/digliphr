<?php
/** @var array $groups */
$groups = $groups ?? [];
$labelHeading = match ($section) {
    'branch' => 'Branch',
    'shift' => 'Shift',
    default => 'Department',
};
$icon = match ($section) {
    'branch' => 'map-pin',
    'shift' => 'clock',
    default => 'building-2',
};
$pctColor = static fn (float $pct) => $pct >= 90 ? '#22C55E' : ($pct >= 75 ? '#F59E0B' : '#EF4444');
?>
<div class="card ems-card">
    <div class="card-header report-card-title"><span class="rct-icon"><i data-lucide="<?= e($icon) ?>"></i></span><?= e($labelHeading) ?> Attendance <span class="small text-muted fw-normal ms-1">— factual totals, no ranking or performance judgment</span></div>
    <div class="report-table-shell">
        <table class="table table-sm mb-0 align-middle">
            <thead>
            <tr>
                <th><?= e($labelHeading) ?></th><th class="text-end">Employees</th><th class="text-end">Working Days</th>
                <th class="text-end">Present</th><th class="text-end">Absent</th><th class="text-end">Leave</th>
                <th class="text-end">Late</th><th style="min-width:130px">Attendance %</th>
                <th class="text-end">Worked Hrs</th><th class="text-end">OT Hrs</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$groups): ?>
                <tr><td colspan="10"><div class="empty-state mb-0 py-4">No data for the selected filters.</div></td></tr>
            <?php else: foreach ($groups as $g): ?>
                <tr>
                    <td class="fw-semibold"><?= e($g['label']) ?></td>
                    <td class="text-end"><?= (int) $g['employees'] ?></td>
                    <td class="text-end"><?= number_format($g['scheduled_working_days'], 1) ?></td>
                    <td class="text-end"><?= number_format($g['present_days'], 1) ?></td>
                    <td class="text-end"><?= number_format($g['absent_days'], 1) ?></td>
                    <td class="text-end"><?= number_format($g['leave_days'], 1) ?></td>
                    <td class="text-end"><?= number_format($g['late_days'], 0) ?></td>
                    <td>
                        <div class="pct-bar-wrap" style="--pct-color: <?= e($pctColor((float) $g['attendance_pct'])) ?>">
                            <div class="pct-bar-track"><div class="pct-bar-fill" style="width: <?= max(0, min(100, (float) $g['attendance_pct'])) ?>%"></div></div>
                            <div class="pct-bar-label"><?= number_format($g['attendance_pct'], 0) ?>%</div>
                        </div>
                    </td>
                    <td class="text-end"><?= number_format($g['worked_minutes'] / 60, 1) ?></td>
                    <td class="text-end"><?= number_format($g['overtime_minutes'] / 60, 1) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
