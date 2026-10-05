<?php $lt = $leaveType ?? []; $edit = !empty($lt); ?>
<div class="row justify-content-center">
<div class="col-lg-8">
<div class="card">
<div class="card-body">
<form method="POST" action="<?= $edit ? '/admin/leave/types/' . (int)$lt['id'] : '/admin/leave/types' ?>">
<?= csrf_field() ?>
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="company_id">Company</label>
        <select name="company_id" class="form-select" required id="company_id">
            <?php foreach ($companies as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= (int)($lt['company_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-6"><label class="form-label" for="name">Name</label><input name="name" class="form-control" value="<?= e($lt['name'] ?? '') ?>" required id="name"></div>
    <div class="col-md-4"><label class="form-label" for="code">Code</label><input name="code" class="form-control" value="<?= e($lt['code'] ?? '') ?>" required id="code"></div>
    <div class="col-md-4"><label class="form-label" for="color">Color</label><input type="color" name="color" class="form-control form-control-color" value="<?= e($lt['color'] ?? '#2563eb') ?>" id="color"></div>
    <div class="col-md-4"><label class="form-label" for="max_days_per_request">Max consecutive days</label><input type="number" step="0.5" name="max_days_per_request" class="form-control" value="<?= e((string)($lt['max_days_per_request'] ?? '')) ?>" id="max_days_per_request"></div>
    <div class="col-12"><label class="form-label" for="description">Description</label><textarea name="description" class="form-control" rows="2" id="description"><?= e($lt['description'] ?? '') ?></textarea></div>
    <div class="col-12"><hr class="my-1"><p class="fw-semibold mb-1 small text-muted">Default Allocation (auto-credited to new employees)</p></div>
    <div class="col-md-4">
        <label class="form-label" for="default_days">Default Days</label>
        <input type="number" step="0.5" min="0" name="default_days" class="form-control" value="<?= e((string)($lt['default_days'] ?? '0')) ?>" id="default_days" placeholder="0 = no auto-allocation">
        <div class="form-text">0 disables auto-allocation for this type.</div>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="accrual_type">Accrual Period</label>
        <select name="accrual_type" class="form-select" id="accrual_type">
            <option value="yearly"  <?= ($lt['accrual_type'] ?? 'yearly') === 'yearly'  ? 'selected' : '' ?>>Yearly (credited once per year)</option>
            <option value="monthly" <?= ($lt['accrual_type'] ?? '') === 'monthly' ? 'selected' : '' ?>>Monthly (credited each month)</option>
        </select>
    </div>
    <div class="col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_paid" value="1" id="paid" <?= !isset($lt['is_paid']) || !empty($lt['is_paid']) ? 'checked' : '' ?>><label class="form-check-label" for="paid">Paid</label></div></div>
    <div class="col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="allow_half_day" value="1" id="hd" <?= !isset($lt['allow_half_day']) || !empty($lt['allow_half_day']) ? 'checked' : '' ?>><label class="form-check-label" for="hd">Half day</label></div></div>
    <div class="col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="requires_attachment" value="1" id="att" <?= !empty($lt['requires_attachment']) ? 'checked' : '' ?>><label class="form-check-label" for="att">Attachment required</label></div></div>
    <div class="col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="act" <?= !isset($lt['is_active']) || !empty($lt['is_active']) ? 'checked' : '' ?>><label class="form-check-label" for="act">Active</label></div></div>
</div>
<div class="mt-3 d-flex gap-2">
    <button class="btn btn-primary"><?= $edit ? 'Update' : 'Create' ?></button>
    <a href="/admin/leave/types" class="btn btn-light">Cancel</a>
</div>
</form>
</div>
</div>
</div>
</div>
