<?php
/** @var array $bulk */
/** @var array $rows */
$rows = $rows ?? [];
$totals = $bulk['totals'] ?? [];
$page = max(1, (int) ($page ?? 1));
$perPage = 25;
$totalRows = count($rows);
$lastPage = max(1, (int) ceil($totalRows / $perPage));
$page = min($page, $lastPage);
$pageRows = array_slice($rows, ($page - 1) * $perPage, $perPage);

$sort = $sort ?? 'employee_name';
$dir = $dir ?? 'asc';
$baseQs = array_filter([
    'section' => 'employee_summary', 'from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null,
    'branch_id' => $filters['branch_id'] ?? null, 'department_id' => $filters['department_id'] ?? null,
]);
$sortLink = static function (string $field, string $label) use ($sort, $dir, $baseQs) {
    $newDir = ($sort === $field && $dir === 'asc') ? 'desc' : 'asc';
    $qs = http_build_query(array_merge($baseQs, ['sort' => $field, 'dir' => $newDir]));
    $icon = $sort === $field ? ($dir === 'asc' ? ' ▲' : ' ▼') : '';
    return '<a href="?' . e($qs) . '" class="text-decoration-none text-reset">' . e($label) . $icon . '</a>';
};
$avatarPairs = [['#6C63FF', '#4338CA'], ['#00C896', '#0E9F6E'], ['#3BA4FF', '#1D4ED8'], ['#F59E0B', '#B45309'], ['#F35BA6', '#BE185D'], ['#14B8A6', '#0F766E']];
$avatarFor = static function (string $seed) use ($avatarPairs) {
    $idx = crc32($seed) % count($avatarPairs);
    return $avatarPairs[$idx];
};
$initials = static function (string $name) {
    $parts = preg_split('/\s+/', trim($name));
    $chars = array_map(static fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)), array_slice($parts, 0, 2));
    return implode('', $chars) ?: '?';
};
$pctColor = static fn (float $pct) => $pct >= 90 ? '#22C55E' : ($pct >= 75 ? '#F59E0B' : '#EF4444');
?>
<div class="card ems-card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="report-card-title"><span class="rct-icon"><i data-lucide="users"></i></span>Employee Attendance Summary</span>
        <span class="small text-muted"><?= $totalRows ?> employees · click a row for the daily breakdown</span>
    </div>
    <div class="report-table-shell">
        <table class="table table-sm mb-0 align-middle">
            <thead>
            <tr>
                <th><?= $sortLink('employee_name', 'Employee') ?></th>
                <th><?= $sortLink('department', 'Department') ?></th>
                <th>Designation</th>
                <th><?= $sortLink('branch', 'Branch') ?></th>
                <th>Shift</th>
                <th class="text-end"><?= $sortLink('scheduled_working_days', 'Working Days') ?></th>
                <th class="text-end"><?= $sortLink('present_days', 'Present') ?></th>
                <th class="text-end"><?= $sortLink('absent_days', 'Absent') ?></th>
                <th class="text-end">Paid Leave</th>
                <th class="text-end">Unpaid Leave</th>
                <th class="text-end">Half Days</th>
                <th class="text-end"><?= $sortLink('late_days', 'Late') ?></th>
                <th class="text-end">Early Dep.</th>
                <th class="text-end">Holidays</th>
                <th class="text-end">Rest Days</th>
                <th class="text-end">Missing</th>
                <th class="text-end">Worked Hrs</th>
                <th class="text-end">Required Hrs</th>
                <th class="text-end">OT Hrs</th>
                <th class="text-end">UT Hrs</th>
                <th class="text-end"><?= $sortLink('attendance_pct', 'Attendance %') ?></th>
                <th>Flags</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$pageRows): ?>
                <tr><td colspan="21"><div class="empty-state mb-0 py-4">No employees match the selected filters.</div></td></tr>
            <?php else: foreach ($pageRows as $r):
                $detailQs = http_build_query(array_merge($baseQs, ['section' => 'employee_detail', 'employee_id' => $r['employee_id']]));
            ?>
                <?php [$avatarA, $avatarB] = $avatarFor($r['employee_code'] ?? $r['employee_name']); ?>
                <tr class="js-emp-row" style="cursor:pointer" data-href="/admin/reports/attendance?<?= e($detailQs) ?>">
                    <td>
                        <div class="emp-chip">
                            <div class="emp-chip-avatar" style="--avatar-a: <?= e($avatarA) ?>; --avatar-b: <?= e($avatarB) ?>"><?= e($initials($r['employee_name'])) ?></div>
                            <div class="emp-chip-text">
                                <div class="emp-chip-name"><?= e($r['employee_name']) ?></div>
                                <div class="emp-chip-code"><?= e($r['employee_code']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td><?= e($r['department'] ?? '—') ?></td>
                    <td><?= e($r['designation'] ?? '—') ?></td>
                    <td><?= e($r['branch'] ?? '—') ?></td>
                    <td><?= e($r['shift'] ?? '—') ?></td>
                    <td class="text-end"><?= number_format((float) $r['scheduled_working_days'], 1) ?></td>
                    <td class="text-end"><?= number_format((float) $r['present_days'], 1) ?></td>
                    <td class="text-end"><?= number_format((float) $r['absent_days'], 1) ?></td>
                    <td class="text-end"><?= number_format((float) $r['paid_leave_days'], 1) ?></td>
                    <td class="text-end"><?= number_format((float) $r['unpaid_leave_days'], 1) ?></td>
                    <td class="text-end"><?= number_format((float) $r['half_days'], 1) ?></td>
                    <td class="text-end"><?= number_format((float) $r['late_days'], 0) ?></td>
                    <td class="text-end"><?= number_format((float) $r['early_departures'], 0) ?></td>
                    <td class="text-end"><?= number_format((float) $r['holiday_days'], 1) ?></td>
                    <td class="text-end"><?= number_format((float) $r['rest_days'], 1) ?></td>
                    <td class="text-end"><?= (int) $r['missing_attendance'] ?></td>
                    <td class="text-end"><?= number_format($r['worked_minutes'] / 60, 1) ?></td>
                    <td class="text-end"><?= number_format($r['required_minutes'] / 60, 1) ?></td>
                    <td class="text-end"><?= number_format($r['overtime_minutes'] / 60, 1) ?></td>
                    <td class="text-end"><?= number_format($r['undertime_minutes'] / 60, 1) ?></td>
                    <td>
                        <div class="pct-bar-wrap" style="--pct-color: <?= e($pctColor((float) $r['attendance_pct'])) ?>">
                            <div class="pct-bar-track"><div class="pct-bar-fill" style="width: <?= max(0, min(100, (float) $r['attendance_pct'])) ?>%"></div></div>
                            <div class="pct-bar-label"><?= number_format((float) $r['attendance_pct'], 0) ?>%</div>
                        </div>
                    </td>
                    <td>
                        <?php if (!empty($r['not_checked_in_today'])): ?><span class="badge bg-warning">Not In Today</span><?php endif; ?>
                        <?php if ((int) $r['missing_attendance'] > 0): ?><span class="badge bg-warning">Missing</span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
            <?php if ($pageRows): ?>
            <tfoot>
                <tr class="fw-semibold table-light">
                    <td colspan="5">Totals (<?= (int) ($totals['employee_count'] ?? 0) ?> employees)</td>
                    <td class="text-end"><?= number_format((float) ($totals['scheduled_working_days'] ?? 0), 1) ?></td>
                    <td class="text-end"><?= number_format((float) ($totals['present_days'] ?? 0), 1) ?></td>
                    <td class="text-end"><?= number_format((float) ($totals['absent_days'] ?? 0), 1) ?></td>
                    <td class="text-end"><?= number_format((float) ($totals['paid_leave_days'] ?? 0), 1) ?></td>
                    <td class="text-end"><?= number_format((float) ($totals['unpaid_leave_days'] ?? 0), 1) ?></td>
                    <td class="text-end"><?= number_format((float) ($totals['half_days'] ?? 0), 1) ?></td>
                    <td class="text-end"><?= number_format((float) ($totals['late_days'] ?? 0), 0) ?></td>
                    <td class="text-end"><?= number_format((float) ($totals['early_departures'] ?? 0), 0) ?></td>
                    <td class="text-end"><?= number_format((float) ($totals['holiday_days'] ?? 0), 1) ?></td>
                    <td class="text-end"><?= number_format((float) ($totals['rest_days'] ?? 0), 1) ?></td>
                    <td class="text-end"><?= (int) ($totals['missing_attendance'] ?? 0) ?></td>
                    <td class="text-end"><?= number_format(($totals['worked_minutes'] ?? 0) / 60, 1) ?></td>
                    <td class="text-end"><?= number_format(($totals['required_minutes'] ?? 0) / 60, 1) ?></td>
                    <td class="text-end"><?= number_format(($totals['overtime_minutes'] ?? 0) / 60, 1) ?></td>
                    <td class="text-end"><?= number_format(($totals['undertime_minutes'] ?? 0) / 60, 1) ?></td>
                    <td class="text-end"><?= number_format((float) ($totals['attendance_pct'] ?? 0), 1) ?>%</td>
                    <td></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
    <?php if ($lastPage > 1): ?>
    <div class="card-footer bg-white d-flex justify-content-end">
        <?= paginate_links(['current_page' => $page, 'last_page' => $lastPage], '/admin/reports/attendance?' . http_build_query(array_merge($_GET, ['page' => null]))) ?>
    </div>
    <?php endif; ?>
</div>
<script>
document.querySelectorAll('.js-emp-row').forEach(function (row) {
    row.addEventListener('click', function () { window.location.href = row.dataset.href; });
});
if (window.lucide) lucide.createIcons();
</script>
