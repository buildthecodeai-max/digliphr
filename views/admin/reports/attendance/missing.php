<?php
/** @var array $missing */
$incomplete = $missing['incomplete'] ?? [];
$duplicates = $missing['duplicates'] ?? [];
$pending = $missing['pending_corrections'] ?? [];
?>
<div class="card ems-card mb-3">
    <div class="card-header d-flex justify-content-between">
        <span class="report-card-title"><span class="rct-icon"><i data-lucide="alert-triangle"></i></span>Incomplete / Flagged Attendance</span>
        <span class="small text-muted"><?= count($incomplete) ?> records need attention</span>
    </div>
    <div class="report-table-shell">
        <table class="table table-sm mb-0 align-middle">
            <thead><tr><th>Employee</th><th>Department</th><th>Date</th><th>Check-In</th><th>Check-Out</th><th>Status</th><th>Issue</th></tr></thead>
            <tbody>
            <?php if (!$incomplete): ?>
                <tr><td colspan="7"><div class="empty-state mb-0 py-4">No incomplete or flagged records.</div></td></tr>
            <?php else: foreach ($incomplete as $r): ?>
                <tr>
                    <td><?= e($r['employee_name']) ?></td>
                    <td><?= e($r['department_name'] ?? '—') ?></td>
                    <td><?= e(format_date($r['attendance_date'])) ?></td>
                    <td><?= e($r['check_in_at'] ? date(config('app.time_format', 'g:i A'), strtotime($r['check_in_at'])) : '—') ?></td>
                    <td><?= e($r['check_out_at'] ? date(config('app.time_format', 'g:i A'), strtotime($r['check_out_at'])) : '—') ?></td>
                    <td><?= status_badge($r['status']) ?></td>
                    <td><span class="badge bg-warning"><?= e($r['issue_type']) ?></span></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card ems-card">
            <div class="card-header report-card-title"><span class="rct-icon"><i data-lucide="copy"></i></span>Duplicate Attendance Records</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0"><thead><tr><th>Employee</th><th>Date</th><th>Records</th></tr></thead><tbody>
                <?php if (!$duplicates): ?>
                    <tr><td colspan="3"><div class="empty-state mb-0 py-3">None found.</div></td></tr>
                <?php else: foreach ($duplicates as $d): ?>
                    <tr><td><?= e($d['employee_name']) ?></td><td><?= e(format_date($d['attendance_date'])) ?></td><td><span class="badge bg-danger"><?= (int) $d['record_count'] ?></span></td></tr>
                <?php endforeach; endif; ?>
                </tbody></table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card ems-card">
            <div class="card-header report-card-title"><span class="rct-icon"><i data-lucide="pencil-line"></i></span>Pending Attendance Corrections</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0"><thead><tr><th>Employee</th><th>Date</th><th>Requested</th></tr></thead><tbody>
                <?php if (!$pending): ?>
                    <tr><td colspan="3"><div class="empty-state mb-0 py-3">None pending.</div></td></tr>
                <?php else: foreach ($pending as $p): ?>
                    <tr><td><?= e($p['employee_name']) ?></td><td><?= e(format_date($p['attendance_date'])) ?></td><td><?= e(format_date($p['requested_at'])) ?></td></tr>
                <?php endforeach; endif; ?>
                </tbody></table>
            </div>
            <div class="card-footer bg-white small"><a href="/admin/reports/attendance?section=corrections&<?= e(http_build_query(array_filter(['from' => $filters['from'], 'to' => $filters['to']]))) ?>">Manage in Corrections tab →</a></div>
        </div>
    </div>
</div>
