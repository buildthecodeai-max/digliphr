<?php
/** @var array $corrections */
$rows = $corrections ?? [];
?>
<div class="card ems-card">
    <div class="card-header d-flex justify-content-between">
        <span class="report-card-title"><span class="rct-icon"><i data-lucide="pencil-line"></i></span>Attendance Corrections / Regularization</span>
        <span class="small text-muted"><?= count($rows) ?> requests</span>
    </div>
    <div class="report-table-shell">
        <table class="table table-sm mb-0 align-middle">
            <thead><tr><th>Employee</th><th>Date</th><th>Original</th><th>Requested</th><th>Reason</th><th>Requested By</th><th>Status</th><th>Reviewed</th><?php if (can('attendance.correct')): ?><th>Actions</th><?php endif; ?></tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="9"><div class="empty-state mb-0 py-4">No correction requests in the selected period.</div></td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td><?= e($r['employee_name']) ?><div class="small text-muted"><?= e($r['employee_code']) ?></div></td>
                    <td><?= e(format_date($r['attendance_date'])) ?></td>
                    <td class="small">
                        In: <?= e($r['previous_check_in_at'] ? date(config('app.time_format', 'g:i A'), strtotime($r['previous_check_in_at'])) : '—') ?><br>
                        Out: <?= e($r['previous_check_out_at'] ? date(config('app.time_format', 'g:i A'), strtotime($r['previous_check_out_at'])) : '—') ?>
                    </td>
                    <td class="small">
                        In: <?= e($r['requested_check_in_at'] ? date(config('app.time_format', 'g:i A'), strtotime($r['requested_check_in_at'])) : '—') ?><br>
                        Out: <?= e($r['requested_check_out_at'] ? date(config('app.time_format', 'g:i A'), strtotime($r['requested_check_out_at'])) : '—') ?>
                    </td>
                    <td class="small"><?= e($r['reason']) ?></td>
                    <td><?= e($r['requested_by_name'] ?? '—') ?></td>
                    <td><?= status_badge($r['status']) ?></td>
                    <td class="small text-muted">
                        <?= $r['reviewed_by_name'] ? e($r['reviewed_by_name']) . ' · ' . e(format_date($r['reviewed_at'])) : '—' ?>
                    </td>
                    <?php if (can('attendance.correct')): ?>
                    <td>
                        <?php if ($r['status'] === 'pending'): ?>
                        <div class="d-flex gap-1">
                            <form method="post" action="/admin/reports/attendance/corrections/<?= (int) $r['id'] ?>/approve" onsubmit="return confirm('Apply this correction to the attendance record?');">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-success">Approve</button>
                            </form>
                            <form method="post" action="/admin/reports/attendance/corrections/<?= (int) $r['id'] ?>/reject" onsubmit="return confirm('Reject this correction request?');">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-danger">Reject</button>
                            </form>
                        </div>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
