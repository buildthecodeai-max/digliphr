<?php
$approved = null;
$pending = [];
foreach ($devices as $device) {
    if (($device['status'] ?? '') === 'approved') $approved = $device;
    if (($device['status'] ?? '') === 'pending') $pending[] = $device;
}
$mode = (string) ($securitySettings['attendance_security_mode'] ?? 'device_only');
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <a class="small text-decoration-none" href="/admin/employees/<?= (int) $employee['id'] ?>">← Back to employee</a>
            <h1 class="h4 mb-1 mt-2">Attendance Device</h1>
            <p class="text-secondary small mb-0"><?= e($employee['employee_name']) ?> · <?= e($employee['employee_code']) ?></p>
        </div>
        <?php if (can('attendance.security.settings')): ?>
            <a class="btn btn-soft btn-sm" href="/admin/settings"><i data-lucide="shield-check" class="me-1"></i>Security Settings</a>
        <?php endif; ?>
    </div>

    <?php include config('app.paths.views') . '/partials/alerts.php'; ?>

    <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="metric-card tone-blue"><div class="metric-label">Security Mode</div><div class="metric-value fs-6 text-capitalize"><?= e(str_replace('_', ' + ', $mode)) ?></div></div></div>
        <div class="col-md-4"><div class="metric-card tone-mint"><div class="metric-label">Approved Device</div><div class="metric-value fs-6"><?= $approved ? e($approved['device_name']) : 'None' ?></div></div></div>
        <div class="col-md-4"><div class="metric-card tone-orange"><div class="metric-label">Pending Requests</div><div class="metric-value"><?= count($pending) ?></div></div></div>
    </div>

    <?php if ($approved): ?>
    <div class="card ems-card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center"><strong>Approved Device</strong><span class="badge text-bg-success">Approved</span></div>
        <div class="card-body">
            <div class="row g-3 small">
                <div class="col-md-4"><span class="text-secondary d-block">Device Name</span><strong><?= e($approved['device_name']) ?></strong></div>
                <div class="col-md-2"><span class="text-secondary d-block">Browser</span><strong><?= e($approved['browser'] ?: '—') ?></strong></div>
                <div class="col-md-2"><span class="text-secondary d-block">Operating System</span><strong><?= e($approved['operating_system'] ?: '—') ?></strong></div>
                <div class="col-md-2"><span class="text-secondary d-block">Registered</span><strong><?= e(format_datetime($approved['first_registered_at'])) ?></strong></div>
                <div class="col-md-2"><span class="text-secondary d-block">Last Used</span><strong><?= e(format_datetime($approved['last_seen_at'])) ?></strong></div>
                <div class="col-md-4"><span class="text-secondary d-block">Last IP</span><strong><?= e($approved['last_ip'] ?: '—') ?></strong></div>
                <div class="col-md-4"><span class="text-secondary d-block">Approved By</span><strong><?= e($approved['approved_by_name'] ?: 'Automatic first-device approval') ?></strong></div>
            </div>
            <?php if (can('attendance.device.manage')): ?>
            <form method="POST" action="/admin/employees/<?= (int) $employee['id'] ?>/attendance-device/reset" class="row g-2 mt-3" data-confirm="Reset this employee's approved attendance device?">
                <?= csrf_field() ?>
                <div class="col-md-8"><input name="reason" class="form-control form-control-sm" maxlength="500" placeholder="Reason for reset" required></div>
                <div class="col-md-4"><button class="btn btn-outline-danger btn-sm w-100">Reset Attendance Device</button></div>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
        <div class="alert alert-info">No approved attendance device. The employee’s next device will follow the configured registration policy.</div>
    <?php endif; ?>

    <div class="card ems-card">
        <div class="card-header"><strong>Device Requests &amp; History</strong></div>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead><tr><th>Device</th><th>Browser / OS</th><th>Registered</th><th>Last Used</th><th>Last IP</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                <?php if (!$devices): ?><tr><td colspan="7"><div class="empty-state py-4">No attendance devices recorded.</div></td></tr><?php endif; ?>
                <?php foreach ($devices as $device): ?>
                <tr>
                    <td><strong><?= e($device['device_name']) ?></strong></td>
                    <td><?= e($device['browser'] ?: '—') ?><span class="text-secondary"> / <?= e($device['operating_system'] ?: '—') ?></span></td>
                    <td><?= e(format_datetime($device['first_registered_at'])) ?></td>
                    <td><?= e(format_datetime($device['last_seen_at'])) ?></td>
                    <td><?= e($device['last_ip'] ?: '—') ?></td>
                    <td><?= status_badge($device['status']) ?></td>
                    <td class="text-end">
                        <?php if ($device['status'] === 'pending' && can('attendance.device.approve')): ?>
                            <div class="d-flex justify-content-end gap-1">
                                <form method="POST" action="/admin/employees/<?= (int) $employee['id'] ?>/attendance-device/<?= (int) $device['id'] ?>/approve" data-confirm="Approve this new device and revoke the previous approved device?">
                                    <?= csrf_field() ?><button class="btn btn-success btn-sm">Approve</button>
                                </form>
                                <form method="POST" action="/admin/employees/<?= (int) $employee['id'] ?>/attendance-device/<?= (int) $device['id'] ?>/reject" data-confirm="Reject this attendance device request?">
                                    <?= csrf_field() ?><input type="hidden" name="reason" value="Rejected by administrator"><button class="btn btn-outline-danger btn-sm">Reject</button>
                                </form>
                            </div>
                        <?php elseif ($device['status'] === 'approved' && can('attendance.device.manage')): ?>
                            <form method="POST" action="/admin/employees/<?= (int) $employee['id'] ?>/attendance-device/<?= (int) $device['id'] ?>/revoke" data-confirm="Revoke this attendance device?">
                                <?= csrf_field() ?><input type="hidden" name="reason" value="Revoked by administrator"><button class="btn btn-outline-danger btn-sm">Revoke</button>
                            </form>
                        <?php else: ?><span class="text-secondary">—</span><?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
