<div class="page-header">
    <div>
        <h1>Unmatched Punches</h1>
        <p class="subtitle">Device PINs that could not be matched to any employee. Assign PIN to fix.</p>
    </div>
    <a href="/admin/attendance/devices" class="btn btn-sm btn-soft">
        <i data-lucide="arrow-left" class="me-1" style="width:14px;height:14px"></i>Back to Devices
    </a>
</div>

<?php include config('app.paths.views') . '/partials/alerts.php'; ?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card ems-card">
            <div class="card-header fw-semibold py-2">Unmatched Log Entries</div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Device PIN</th>
                            <th>Device</th>
                            <th>Type</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="4"><div class="empty-state py-4">No unmatched punches.</div></td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $l): ?>
                        <tr>
                            <td class="small"><?= e(format_datetime($l['punch_time'])) ?></td>
                            <td><code><?= e($l['device_pin']) ?></code></td>
                            <td class="small text-muted"><?= e($l['device_name'] ?? $l['serial_number']) ?></td>
                            <td><?= status_badge($l['punch_type']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card ems-card">
            <div class="card-header fw-semibold py-2">Assign PIN to Employee</div>
            <div class="card-body">
                <p class="small text-muted mb-3">
                    Enter a Device PIN (from the unmatched list) and assign it to an employee.
                    All past unmatched punches with that PIN will be linked automatically.
                </p>
                <form method="POST" action="/admin/attendance/devices/assign-pin">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label small" for="device_pin">Device PIN</label>
                        <input type="text" name="device_pin" id="device_pin" class="form-control form-control-sm"
                               placeholder="e.g. 9999" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small" for="employee_id">Employee</label>
                        <select name="employee_id" id="employee_id" class="form-select form-select-sm" required>
                            <option value="">Select employee…</option>
                            <?php foreach ($employees as $e): ?>
                            <option value="<?= (int) $e['id'] ?>">
                                <?= htmlspecialchars((string) $e['name']) ?> (<?= e($e['employee_code'] ?? '') ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm w-100">Assign PIN</button>
                </form>
            </div>
        </div>
    </div>
</div>
