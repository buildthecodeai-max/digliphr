<?php $isEdit = !empty($designation); ?>
<div class="card"><div class="card-body">
<form method="POST" action="<?= $isEdit ? '/admin/designations/' . (int)$designation['id'] : '/admin/designations' ?>">
    <?= csrf_field() ?>
    <div class="row g-2">
        <div class="col-md-4"><label class="form-label" for="company_id">Company *</label><select name="company_id" class="form-select form-select-sm" required id="company_id"><?php foreach ($companies as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)old('company_id', $designation['company_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label" for="department_id">Department</label><select name="department_id" class="form-select form-select-sm" id="department_id"><option value="">—</option><?php foreach ($departments as $d): ?><option value="<?= (int)$d['id'] ?>" <?= (int)old('department_id', $designation['department_id'] ?? 0) === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label" for="level">Level</label><input type="number" name="level" class="form-control form-control-sm" value="<?= e(old('level', $designation['level'] ?? '')) ?>" id="level"></div>
        <div class="col-md-4"><label class="form-label" for="name">Name *</label><input type="text" name="name" class="form-control form-control-sm" value="<?= e(old('name', $designation['name'] ?? '')) ?>" required id="name"></div>
        <div class="col-md-4"><label class="form-label" for="code">Code</label><input type="text" name="code" class="form-control form-control-sm" value="<?= e(old('code', $designation['code'] ?? '')) ?>" id="code"></div>
        <div class="col-md-12"><label class="form-label" for="description">Description</label><textarea name="description" class="form-control form-control-sm" rows="2" id="description"><?= e(old('description', $designation['description'] ?? '')) ?></textarea></div>
        <div class="col-12">
            <div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" <?= old('is_active', $designation['is_active'] ?? 1) ? 'checked' : '' ?> id="is_active"><label class="form-check-label" for="is_active">Active</label></div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_manager_or_above" value="1" <?= old('is_manager_or_above', $designation['is_manager_or_above'] ?? 0) ? 'checked' : '' ?> id="is_manager_or_above">
                <label class="form-check-label" for="is_manager_or_above">Manager or above</label>
                <div class="form-text">Employees with this designation get the company's manager weekly schedule (e.g. Saturday and Sunday off) instead of their department's schedule.</div>
            </div>
        </div>
    </div>
    <div class="mt-3"><button type="submit" class="btn btn-primary btn-sm">Save</button> <a href="/admin/designations" class="btn btn-secondary btn-sm">Cancel</a></div>
</form></div></div>
