<?php
$u = $user ?? [];
$edit = !empty($u);
$assigned = $assignedRoleIds ?? [];
?>
<div class="page-header">
    <div>
        <h1><?= $edit ? 'Edit User' : 'Add User' ?></h1>
        <p class="subtitle"><?= $edit ? 'Update account details and roles' : 'Create a new login account' ?></p>
    </div>
</div>

<div class="row justify-content-center">
<div class="col-lg-8">
<div class="card ems-card"><div class="card-body">
<form method="POST" action="<?= $edit ? '/admin/users/' . (int) $u['id'] : '/admin/users' ?>">
<?= csrf_field() ?>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <label class="form-label" for="company_id">Company</label>
        <select name="company_id" class="form-select" id="company_id" required>
            <option value="">Select company</option>
            <?php foreach (($companies ?? []) as $company): ?>
                <option value="<?= (int) $company['id'] ?>" <?= (int) ($companyId ?? 0) === (int) $company['id'] ? 'selected' : '' ?>><?= e($company['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="name">Name</label>
        <input name="name" class="form-control" required value="<?= e($u['name'] ?? '') ?>" id="name">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="email">Email</label>
        <input type="email" name="email" class="form-control" required value="<?= e($u['email'] ?? '') ?>" id="email">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="username">Username</label>
        <input name="username" class="form-control" value="<?= e($u['username'] ?? '') ?>" id="username">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="phone">Phone</label>
        <input name="phone" class="form-control" value="<?= e($u['phone'] ?? '') ?>" id="phone">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="password"><?= $edit ? 'New password (optional)' : 'Password' ?></label>
        <input type="password" name="password" class="form-control" <?= $edit ? '' : 'required' ?> minlength="8" autocomplete="new-password" id="password">
    </div>
</div>

<div class="mb-3">
    <div class="form-label d-block">Roles</div>
    <div class="row g-2">
        <?php foreach ($roles as $role): ?>
            <?php $rid = (int) $role['id']; ?>
            <div class="col-md-6">
                <label class="form-check">
                    <input class="form-check-input" type="checkbox" name="role_ids[]" value="<?= $rid ?>"
                        <?= in_array($rid, $assigned, true) ? 'checked' : '' ?>
                        <?= !empty($u['is_super_admin']) ? 'disabled' : '' ?>>
                    <span class="form-check-label"><?= e($role['name']) ?> <code class="small"><?= e($role['slug']) ?></code></span>
                </label>
            </div>
        <?php endforeach; ?>
    </div>
    <?php if (!empty($u['is_super_admin'])): ?>
        <div class="form-text">Super admin role assignment is locked.</div>
    <?php endif; ?>
</div>

<div class="d-flex flex-wrap gap-3 mb-3">
    <label class="form-check">
        <input class="form-check-input" type="checkbox" name="is_active" value="1"
               <?= !isset($u['is_active']) || (int) ($u['is_active'] ?? 0) === 1 ? 'checked' : '' ?>>
        <span class="form-check-label">Active</span>
    </label>
    <label class="form-check">
        <input class="form-check-input" type="checkbox" name="force_password_reset" value="1"
               <?= !empty($u['force_password_reset']) ? 'checked' : '' ?>>
        <span class="form-check-label">Force password reset on next login</span>
    </label>
</div>

<div class="d-flex gap-2">
    <button class="btn btn-primary"><?= $edit ? 'Save changes' : 'Create user' ?></button>
    <a href="/admin/users" class="btn btn-soft">Cancel</a>
</div>
</form>
</div></div>
</div>
</div>
