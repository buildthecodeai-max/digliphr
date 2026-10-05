<?php $h = $holiday ?? []; $edit = !empty($h); ?>
<div class="row justify-content-center"><div class="col-lg-7">
<div class="card"><div class="card-body">
<form method="POST" action="<?= $edit ? '/admin/leave/holidays/' . (int)$h['id'] : '/admin/leave/holidays' ?>">
<?= csrf_field() ?>
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="company_id">Company</label>
        <select name="company_id" class="form-select" required id="company_id">
            <?php foreach ($companies as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= (int)($h['company_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="branch_id">Branch (optional)</label>
        <select name="branch_id" class="form-select" id="branch_id">
            <option value="">All branches</option>
            <?php foreach ($branches as $b): ?>
                <option value="<?= (int)$b['id'] ?>" <?= (int)($h['branch_id'] ?? 0) === (int)$b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-6"><label class="form-label" for="name">Name</label><input name="name" class="form-control" value="<?= e($h['name'] ?? '') ?>" required id="name"></div>
    <div class="col-md-6"><label class="form-label" for="holiday_date">Date</label><input type="date" name="holiday_date" class="form-control" value="<?= e($h['holiday_date'] ?? $h['date'] ?? '') ?>" required id="holiday_date"></div>
    <div class="col-md-6">
        <label class="form-label" for="type">Type</label>
        <select name="type" class="form-select" id="type">
            <?php foreach (['public','company','optional','restricted'] as $t): ?>
                <option value="<?= $t ?>" <?= ($h['type'] ?? 'public') === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3"><div class="form-check mt-4"><input class="form-check-input" type="checkbox" name="is_paid" value="1" id="hp" <?= !isset($h['is_paid']) || !empty($h['is_paid']) ? 'checked' : '' ?>><label class="form-check-label" for="hp">Paid</label></div></div>
    <div class="col-md-3"><div class="form-check mt-4"><input class="form-check-input" type="checkbox" name="is_recurring" value="1" id="hr" <?= !empty($h['is_recurring']) ? 'checked' : '' ?>><label class="form-check-label" for="hr">Recurring</label></div></div>
    <div class="col-12"><label class="form-label" for="description">Description</label><textarea name="description" class="form-control" rows="2" id="description"><?= e($h['description'] ?? '') ?></textarea></div>
</div>
<div class="mt-3 d-flex gap-2">
    <button class="btn btn-primary"><?= $edit ? 'Update' : 'Create' ?></button>
    <a href="/admin/leave/holidays" class="btn btn-light">Cancel</a>
</div>
</form>
</div></div></div></div>
