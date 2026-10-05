<?php $s = $structure ?? []; $edit = !empty($s); $itemsByComponent = [];
foreach ($items ?? [] as $item) {
    $itemsByComponent[(int) $item['salary_component_id']] = $item;
}
?>
<div class="page-header">
    <div>
        <h1><?= $edit ? 'Edit Salary Structure' : 'Create Salary Structure' ?></h1>
        <p class="subtitle">Define earnings and deductions used in payroll</p>
    </div>
</div>

<div class="row justify-content-center">
<div class="col-lg-9">
<div class="card ems-card">
<div class="card-body">
<form method="POST" action="<?= $edit ? '/admin/salary-structures/' . (int) $s['id'] : '/admin/salary-structures' ?>">
<?= csrf_field() ?>
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="company_id">Company</label>
        <select name="company_id" class="form-select" required id="company_id">
            <?php foreach ($companies as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= (int) ($s['company_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="name">Name</label>
        <input name="name" class="form-control" value="<?= e($s['name'] ?? '') ?>" required id="name">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="code">Code</label>
        <input name="code" class="form-control" value="<?= e($s['code'] ?? '') ?>" required id="code">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="effective_from">Effective from</label>
        <input type="date" name="effective_from" class="form-control" value="<?= e($s['effective_from'] ?? date('Y-01-01')) ?>" required id="effective_from">
    </div>
    <div class="col-md-4 d-flex align-items-end gap-3">
        <div class="form-check"><input class="form-check-input" type="checkbox" name="is_default" value="1" id="def" <?= !empty($s['is_default']) ? 'checked' : '' ?>><label class="form-check-label" for="def">Default</label></div>
        <div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="act" <?= !isset($s['is_active']) || !empty($s['is_active']) ? 'checked' : '' ?>><label class="form-check-label" for="act">Active</label></div>
    </div>
    <div class="col-12">
        <label class="form-label" for="description">Description</label>
        <textarea name="description" class="form-control" rows="2" id="description"><?= e($s['description'] ?? '') ?></textarea>
    </div>
</div>

<hr class="my-4">
<h2 class="h6 fw-bold mb-3">Structure Components</h2>
<?php if (empty($components)): ?>
    <div class="empty-state py-3">No salary components configured yet.</div>
<?php else: ?>
<div class="table-responsive">
<table class="table table-sm align-middle">
<thead><tr><th>Include</th><th>Component</th><th>Type</th><th>Amount</th><th>Percentage</th></tr></thead>
<tbody>
<?php foreach ($components as $i => $comp):
    $item = $itemsByComponent[(int) $comp['id']] ?? null;
    $checked = $item !== null;
?>
<tr>
    <td>
        <input type="checkbox" class="form-check-input" name="components[<?= $i ?>][enabled]" value="1" <?= $checked ? 'checked' : '' ?>>
        <input type="hidden" name="components[<?= $i ?>][salary_component_id]" value="<?= (int) $comp['id'] ?>">
    </td>
    <td>
        <div class="fw-semibold"><?= e($comp['name']) ?></div>
        <div class="small text-secondary"><?= e($comp['code']) ?></div>
    </td>
    <td><?= status_badge($comp['type']) ?></td>
    <td><input type="number" step="0.01" min="0" class="form-control form-control-sm" name="components[<?= $i ?>][amount]" value="<?= e((string) ($item['amount'] ?? $comp['default_amount'] ?? '0')) ?>"></td>
    <td><input type="number" step="0.0001" min="0" class="form-control form-control-sm" name="components[<?= $i ?>][percentage]" value="<?= e((string) ($item['percentage'] ?? '')) ?>"></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>

<div class="mt-3 d-flex gap-2">
    <button class="btn btn-primary"><?= $edit ? 'Update Structure' : 'Create Structure' ?></button>
    <a href="/admin/salary-structures" class="btn btn-soft">Cancel</a>
</div>
</form>
</div>
</div>
</div>
</div>
