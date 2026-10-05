<?php
/** @var array $early */
$rows = $early ?? [];
?>
<div class="card ems-card">
    <div class="card-header d-flex justify-content-between">
        <span class="report-card-title"><span class="rct-icon"><i data-lucide="log-out"></i></span>Early Departure Report</span>
        <span class="small text-muted"><?= count($rows) ?> occurrences</span>
    </div>
    <div class="report-table-shell">
        <table class="table table-sm mb-0 align-middle">
            <thead><tr><th>Employee</th><th>Department</th><th>Branch</th><th>Date</th><th>Scheduled Out</th><th>Actual Out</th><th>Early By</th><th>Worked Hours</th><th>Verification</th></tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="9"><div class="empty-state mb-0 py-4">No early departures in the selected period.</div></td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td><div class="fw-semibold"><?= e($r['employee_name']) ?></div><div class="small text-muted"><?= e($r['employee_code']) ?></div></td>
                    <td><?= e($r['department_name'] ?? '—') ?></td>
                    <td><?= e($r['branch_name'] ?? '—') ?></td>
                    <td><?= e(format_date($r['attendance_date'])) ?></td>
                    <td><?= e($r['scheduled_out'] ? date(config('app.time_format', 'g:i A'), strtotime($r['scheduled_out'])) : '—') ?></td>
                    <td><?= e($r['check_out_at'] ? date(config('app.time_format', 'g:i A'), strtotime($r['check_out_at'])) : '—') ?></td>
                    <td><?= (int) $r['early_leave_minutes'] ?>m</td>
                    <td><?= e(format_minutes((int) $r['work_minutes'])) ?></td>
                    <td><?= status_badge($r['verification_status'] ?? 'pending') ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
