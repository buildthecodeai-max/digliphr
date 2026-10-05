<form method="POST" action="/admin/settings">
<?= csrf_field() ?>
<?php if (!empty($companyId)): ?><input type="hidden" name="company_id" value="<?= (int) $companyId ?>"><?php endif; ?>
<?php if (empty($grouped)): ?>
<div class="alert alert-info">No settings found. Run the database seeder to create defaults.</div>
<?php else: foreach ($grouped as $group => $items): ?>
<div class="card mb-3">
    <div class="card-header py-2 fw-semibold text-capitalize"><?= e(str_replace('_',' ',$group)) ?></div>
    <div class="card-body">
        <div class="row g-3">
        <?php foreach ($items as $item): ?>
            <?php $settingId = 'setting-' . preg_replace('/[^a-z0-9_-]/i', '-', (string) $item['setting_key']); ?>
            <div class="col-md-6">
                <label class="form-label small" for="<?= e($settingId) ?>"><?= e(ucwords(str_replace('_', ' ', (string) $item['setting_key']))) ?></label>
                <?php if (($item['setting_key'] ?? '') === 'attendance_security_mode'): ?>
                    <select name="settings[<?= e($item['setting_key']) ?>]" class="form-select form-select-sm" id="<?= e($settingId) ?>">
                        <?php foreach (['disabled' => 'Off', 'device_only' => 'Device only', 'ip_only' => 'IP only', 'device_and_ip' => 'Device + IP'] as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= ($item['setting_value'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php elseif (($item['setting_key'] ?? '') === 'attendance_device_registration_policy'): ?>
                    <select name="settings[<?= e($item['setting_key']) ?>]" class="form-select form-select-sm" id="<?= e($settingId) ?>">
                        <option value="auto_first" <?= ($item['setting_value'] ?? '') === 'auto_first' ? 'selected' : '' ?>>Automatically approve first device</option>
                        <option value="admin_approval" <?= ($item['setting_value'] ?? '') === 'admin_approval' ? 'selected' : '' ?>>Require admin approval</option>
                    </select>
                <?php elseif (($item['setting_key'] ?? '') === 'attendance_ip_source'): ?>
                    <select name="settings[<?= e($item['setting_key']) ?>]" class="form-select form-select-sm" id="<?= e($settingId) ?>">
                        <option value="office" <?= ($item['setting_value'] ?? '') === 'office' ? 'selected' : '' ?>>Office IP list</option>
                        <option value="approved" <?= ($item['setting_value'] ?? '') === 'approved' ? 'selected' : '' ?>>Approved IP list</option>
                    </select>
                <?php elseif (($item['value_type'] ?? '') === 'boolean'): ?>
                    <select name="settings[<?= e($item['setting_key']) ?>]" class="form-select form-select-sm" id="<?= e($settingId) ?>">
                        <option value="1" <?= ($item['setting_value'] ?? '') == '1' ? 'selected' : '' ?>>Yes</option>
                        <option value="0" <?= ($item['setting_value'] ?? '') == '0' ? 'selected' : '' ?>>No</option>
                    </select>
                <?php elseif (($item['value_type'] ?? '') === 'text'): ?>
                    <textarea name="settings[<?= e($item['setting_key']) ?>]" class="form-control form-control-sm" id="<?= e($settingId) ?>" rows="3" placeholder="203.0.113.10&#10;198.51.100.0/24"><?= e($item['setting_value'] ?? '') ?></textarea>
                <?php else: ?>
                    <input type="<?= ($item['setting_key'] ?? '') === 'sso_client_secret' ? 'password' : 'text' ?>" name="settings[<?= e($item['setting_key']) ?>]" class="form-control form-control-sm" value="<?= e($item['setting_value'] ?? '') ?>" id="<?= e($settingId) ?>" <?= ($item['setting_key'] ?? '') === 'sso_client_secret' ? 'autocomplete="new-password"' : '' ?>>
                <?php endif; ?>
                <?php if (!empty($item['description'])): ?><div class="form-text"><?= e($item['description']) ?></div><?php endif; ?>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endforeach; endif; ?>
<button class="btn btn-primary">Save Settings</button>
</form>
