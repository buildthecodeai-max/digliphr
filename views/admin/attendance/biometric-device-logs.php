<div class="page-header">
    <div>
        <h1><?= e($title) ?></h1>
        <p class="subtitle">Last 200 punches from this device</p>
    </div>
    <a href="/admin/attendance/devices" class="btn btn-sm btn-soft">
        <i data-lucide="arrow-left" class="me-1" style="width:14px;height:14px"></i>Back to Devices
    </a>
</div>

<div class="card ems-card">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>Punch Time</th>
                    <th>Employee</th>
                    <th>PIN</th>
                    <th>Type</th>
                    <th>Verify</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($logs)): ?>
                <tr><td colspan="6"><div class="empty-state py-4">No logs yet.</div></td></tr>
            <?php else: ?>
                <?php foreach ($logs as $l): ?>
                <tr>
                    <td class="small"><?= e(format_datetime($l['punch_time'])) ?></td>
                    <td><?= $l['employee_name'] ? e($l['employee_name']) : '<span class="text-muted">—</span>' ?></td>
                    <td><code><?= e($l['device_pin']) ?></code></td>
                    <td><?= status_badge($l['punch_type']) ?></td>
                    <td class="small text-muted"><?= e(ucfirst((string) $l['verify_type'])) ?></td>
                    <td><?= status_badge($l['status']) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
