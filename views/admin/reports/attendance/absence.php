<?php
/** @var array $absences */
$absences = $absences ?? [];
?>
<div class="card ems-card">
    <div class="card-header d-flex justify-content-between">
        <span class="report-card-title"><span class="rct-icon"><i data-lucide="user-x"></i></span>Absence Report</span>
        <span class="small text-muted"><?= count($absences) ?> absence days · consecutive-day streaks shown for HR review</span>
    </div>
    <?php
    $absenceByDate = [];
    foreach ($absences as $r) { $absenceByDate[$r['date']][] = $r; }
    ?>
    <div class="report-table-shell">
        <table class="table table-sm mb-0 align-middle">
            <thead><tr><th>Employee</th><th>Department</th><th>Branch</th><th>Scheduled Shift</th><th>Absence Type</th><th>Consecutive Days</th><th>Leave Request</th></tr></thead>
            <tbody>
            <?php if (!$absences): ?>
                <tr><td colspan="7"><div class="empty-state mb-0 py-4">No absences in the selected period.</div></td></tr>
            <?php else: foreach ($absenceByDate as $attDate => $dateRows): ?>
                <tr class="att-date-group-row">
                    <td colspan="7">
                        <span class="att-date-label"><?= e(date('l, j F Y', strtotime($attDate))) ?></span>
                        <span class="att-date-count"><?= count($dateRows) ?> absence<?= count($dateRows) !== 1 ? 's' : '' ?></span>
                    </td>
                </tr>
                <?php foreach ($dateRows as $r): ?>
                <tr class="<?= $r['consecutive_days'] >= 3 ? 'table-warning' : '' ?>">
                    <td><div class="fw-semibold"><?= e($r['employee_name']) ?></div><div class="small text-muted"><?= e($r['employee_code']) ?></div></td>
                    <td><?= e($r['department'] ?? '—') ?></td>
                    <td><?= e($r['branch'] ?? '—') ?></td>
                    <td><?= e($r['shift'] ?? '—') ?></td>
                    <td><?= e($r['absence_type']) ?></td>
                    <td><?= $r['consecutive_days'] > 1 ? '<span class="badge bg-warning">' . (int) $r['consecutive_days'] . ' days</span>' : '1 day' ?></td>
                    <td><?= e($r['leave_request_status']) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
