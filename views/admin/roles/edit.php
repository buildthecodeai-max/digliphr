<form method="POST" action="/admin/roles/<?= (int)$role['id'] ?>">
<?= csrf_field() ?>
<?php if (($role['slug'] ?? '') === 'super_admin'): ?>
<div class="alert alert-warning">Super Admin always has full access. Permission changes are not applied.</div>
<?php endif; ?>
<?php foreach ($grouped as $module => $perms): ?>
<div class="card mb-2"><div class="card-header py-2 fw-semibold text-capitalize"><?= e($module) ?></div>
<div class="card-body py-2"><div class="row">
<?php foreach ($perms as $p): ?>
<div class="col-md-4"><div class="form-check">
<input class="form-check-input" type="checkbox" name="permissions[]" value="<?= (int)$p['id'] ?>" id="p<?= (int)$p['id'] ?>" <?= in_array((int)$p['id'], $assignedIds, true) ? 'checked' : '' ?>>
<label class="form-check-label small" for="p<?= (int)$p['id'] ?>"><?= e($p['name'] ?? $p['slug']) ?></label>
</div></div>
<?php endforeach; ?>
</div></div></div>
<?php endforeach; ?>
<button class="btn btn-primary">Save Permissions</button>
<a href="/admin/roles" class="btn btn-light">Back</a>
</form>
