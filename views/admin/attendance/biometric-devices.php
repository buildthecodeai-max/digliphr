<div class="page-header">
    <div>
        <h1>Biometric Devices</h1>
        <p class="subtitle">Manage fingerprint & RFID readers connected via ZKTeco ADMS protocol</p>
    </div>
    <div class="d-flex gap-2">
        <?php if ($unmatched > 0): ?>
        <a href="/admin/attendance/devices/unmatched" class="btn btn-warning btn-sm">
            <i data-lucide="alert-triangle" class="me-1" style="width:14px;height:14px"></i>
            <?= $unmatched ?> Unmatched Punches
        </a>
        <?php endif; ?>
        <a href="/admin/attendance/qr-codes" class="btn btn-soft btn-sm">
            <i data-lucide="qr-code" class="me-1" style="width:14px;height:14px"></i>QR Codes
        </a>
        <a href="/admin/attendance/devices/create" class="btn btn-primary btn-sm">
            <i data-lucide="plus" class="me-1" style="width:14px;height:14px"></i>Register Device
        </a>
    </div>
</div>

<?php include config('app.paths.views') . '/partials/alerts.php'; ?>

<!-- Setup guide -->
<div class="alert alert-info d-flex gap-3 align-items-start mb-4">
    <i data-lucide="info" style="width:18px;height:18px;flex-shrink:0;margin-top:2px"></i>
    <div class="small">
        <strong>How to connect a ZKTeco / BioTime device:</strong>
        On the device, go to <em>Communication → ADMS</em> and set:<br>
        &nbsp;&nbsp;• Server address: <code><?= e(rtrim((string) config('app.url', 'https://hr.diglip.com'), '/')) ?></code><br>
        &nbsp;&nbsp;• Port: <code>80</code> (or <code>443</code> for HTTPS)<br>
        &nbsp;&nbsp;• Path: <code>/iclock/cdata</code><br>
        Then enter each employee's PIN on the device and set the same value as <strong>Device PIN</strong> on their employee profile.
    </div>
</div>

<div class="card ems-card">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Serial Number</th>
                    <th>Type</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th>Last Seen</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($devices)): ?>
                <tr><td colspan="7">
                    <div class="empty-state py-4">No devices registered yet.</div>
                </td></tr>
            <?php else: ?>
                <?php foreach ($devices as $d): ?>
                <tr>
                    <td class="fw-semibold"><?= e($d['name']) ?></td>
                    <td><code><?= e($d['serial_number']) ?></code></td>
                    <td><?= e(ucfirst((string) $d['device_type'])) ?></td>
                    <td class="text-muted"><?= e($d['location'] ?? '—') ?></td>
                    <td><?= status_badge($d['status']) ?></td>
                    <td class="small text-muted">
                        <?= $d['last_seen_at'] ? e(format_datetime($d['last_seen_at'])) : 'Never' ?>
                    </td>
                    <td class="text-end">
                        <a href="/admin/attendance/devices/<?= $d['id'] ?>/logs" class="btn btn-xs btn-soft me-1">Logs</a>
                        <a href="/admin/attendance/devices/<?= $d['id'] ?>/edit" class="btn btn-xs btn-soft me-1">Edit</a>
                        <form method="POST" action="/admin/attendance/devices/<?= $d['id'] ?>/delete" class="d-inline"
                              onsubmit="return confirm('Remove this device?')">
                            <?= csrf_field() ?>
                            <button class="btn btn-xs btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
