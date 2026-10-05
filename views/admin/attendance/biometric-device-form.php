<div class="page-header">
    <div>
        <h1><?= e($title) ?></h1>
        <p class="subtitle"><?= $device ? 'Update device information.' : 'Register a new fingerprint or RFID reader.' ?></p>
    </div>
    <a href="/admin/attendance/devices" class="btn btn-sm btn-soft">
        <i data-lucide="arrow-left" class="me-1" style="width:14px;height:14px"></i>Back
    </a>
</div>

<?php include config('app.paths.views') . '/partials/alerts.php'; ?>

<div class="row justify-content-center">
<div class="col-lg-6">
<div class="card ems-card">
    <div class="card-body">
        <?php
            $action = $device
                ? '/admin/attendance/devices/' . (int) $device['id'] . '/update'
                : '/admin/attendance/devices';
        ?>
        <form method="POST" action="<?= $action ?>">
            <?= csrf_field() ?>

            <?php if (!$device): ?>
            <div class="mb-3">
                <label class="form-label" for="serial_number">Serial Number <span class="text-danger">*</span></label>
                <input type="text" name="serial_number" id="serial_number" class="form-control"
                       value="<?= e($_POST['serial_number'] ?? '') ?>" required
                       placeholder="e.g. CGXH202360001">
                <div class="form-text">Found on the device or its settings menu.</div>
            </div>
            <?php endif; ?>

            <div class="mb-3">
                <label class="form-label" for="name">Device Name <span class="text-danger">*</span></label>
                <input type="text" name="name" id="name" class="form-control"
                       value="<?= e($_POST['name'] ?? $device['name'] ?? '') ?>" required
                       placeholder="e.g. Main Entrance">
            </div>

            <div class="mb-3">
                <label class="form-label" for="device_type">Type <span class="text-danger">*</span></label>
                <select name="device_type" id="device_type" class="form-select" required>
                    <?php foreach (['fingerprint'=>'Fingerprint','rfid'=>'RFID / Card','face'=>'Face Recognition','multi'=>'Multi-mode'] as $v => $l): ?>
                    <option value="<?= $v ?>" <?= (($_POST['device_type'] ?? $device['device_type'] ?? '') === $v) ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label" for="location">Location</label>
                <input type="text" name="location" id="location" class="form-control"
                       value="<?= e($_POST['location'] ?? $device['location'] ?? '') ?>"
                       placeholder="e.g. Ground Floor Lobby">
            </div>

            <?php if ($device): ?>
            <div class="mb-4">
                <label class="form-label" for="status">Status</label>
                <select name="status" id="status" class="form-select">
                    <option value="active" <?= ($device['status'] === 'active') ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= ($device['status'] === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <?php endif; ?>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="save" class="me-1" style="width:14px;height:14px"></i>
                    <?= $device ? 'Save Changes' : 'Register Device' ?>
                </button>
                <a href="/admin/attendance/devices" class="btn btn-soft">Cancel</a>
            </div>
        </form>
    </div>
</div>
</div>
</div>
