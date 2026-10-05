<div class="card"><div class="card-body">
<form method="POST" action="/admin/roles">
    <?= csrf_field() ?>
    <div class="row g-2">
        <?php if (!empty($isGlobalViewer)): ?>
        <div class="col-md-6">
            <label class="form-label" for="company_id">Company</label>
            <select name="company_id" class="form-select form-select-sm" id="company_id">
                <option value="">Global (all companies)</option>
                <?php foreach ($companies as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= (int) old('company_id', 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">Leave as Global to make this role available to every company.</div>
        </div>
        <?php endif; ?>
        <div class="col-md-6">
            <label class="form-label" for="name">Role Name *</label>
            <input type="text" name="name" class="form-control form-control-sm" value="<?= e(old('name', '')) ?>" required id="name" placeholder="e.g. Team Lead">
        </div>
        <div class="col-md-12">
            <label class="form-label" for="description">Description</label>
            <textarea name="description" class="form-control form-control-sm" rows="2" id="description"><?= e(old('description', '')) ?></textarea>
        </div>
    </div>
    <div class="mt-3">
        <button type="submit" class="btn btn-primary btn-sm">Create Role</button>
        <a href="/admin/roles" class="btn btn-secondary btn-sm">Cancel</a>
    </div>
    <div class="form-text mt-2">After creating the role, you'll be taken to its permissions screen to choose what it can access.</div>
</form>
</div></div>
