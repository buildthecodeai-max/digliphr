<?php $isEdit = !empty($department); ?>
<div class="card"><div class="card-body">
<form method="POST" action="<?= $isEdit ? '/admin/departments/' . (int)$department['id'] : '/admin/departments' ?>">
    <?= csrf_field() ?>
    <div class="row g-2">
        <div class="col-md-4"><label class="form-label" for="company_id">Company *</label><select name="company_id" class="form-select form-select-sm" required id="company_id"><?php foreach ($companies as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)old('company_id', $department['company_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label" for="branch_id">Branch</label><select name="branch_id" class="form-select form-select-sm" id="branch_id"><option value="">—</option><?php foreach ($branches as $b): ?><option value="<?= (int)$b['id'] ?>" <?= (int)old('branch_id', $department['branch_id'] ?? 0) === (int)$b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label" for="head_employee_id">Head Employee</label><select name="head_employee_id" class="form-select form-select-sm" id="head_employee_id"><option value="">—</option><?php foreach ($employees as $e): ?><option value="<?= (int)$e['id'] ?>" <?= (int)old('head_employee_id', $department['head_employee_id'] ?? 0) === (int)$e['id'] ? 'selected' : '' ?>><?= e(($e['first_name'] ?? '') . ' ' . ($e['last_name'] ?? '')) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label" for="name">Name *</label><input type="text" name="name" class="form-control form-control-sm" value="<?= e(old('name', $department['name'] ?? '')) ?>" required id="name"></div>
        <div class="col-md-4"><label class="form-label" for="code">Code</label><input type="text" name="code" class="form-control form-control-sm" value="<?= e(old('code', $department['code'] ?? '')) ?>" id="code"></div>
        <div class="col-md-4">
            <label class="form-label" for="week_pattern_id">Weekly schedule</label>
            <select name="week_pattern_id" class="form-select form-select-sm" id="week_pattern_id">
                <option value="">Company default</option>
                <?php foreach (($weekPatterns ?? []) as $wp): ?>
                    <option value="<?= (int) $wp['id'] ?>" <?= (int) old('week_pattern_id', $department['week_pattern_id'] ?? 0) === (int) $wp['id'] ? 'selected' : '' ?>><?= e($wp['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">Overrides the company default for non-manager staff in this department. <a href="/admin/week-patterns" target="_blank">Manage patterns</a></div>
        </div>
        <div class="col-md-12"><label class="form-label" for="description">Description</label><textarea name="description" class="form-control form-control-sm" rows="2" id="description"><?= e(old('description', $department['description'] ?? '')) ?></textarea></div>
        <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" <?= old('is_active', $department['is_active'] ?? 1) ? 'checked' : '' ?> id="is_active"><label class="form-check-label" for="is_active">Active</label></div></div>
    </div>
    <div class="mt-3"><button type="submit" class="btn btn-primary btn-sm">Save</button> <a href="/admin/departments" class="btn btn-secondary btn-sm">Cancel</a></div>
</form></div></div>
