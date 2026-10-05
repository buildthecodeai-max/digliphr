<div class="page-header">
    <div>
        <h1>Employee Assets</h1>
        <p class="subtitle">Assign and track company assets</p>
    </div>
</div>

<?php include config('app.paths.views') . '/partials/alerts.php'; ?>

<div class="row g-3">
<div class="col-lg-8">
<div class="card ems-card"><div class="table-responsive"><table class="table table-sm table-hover mb-0">
<thead><tr><th>Tag</th><th>Asset</th><th>Employee</th><th>Type</th><th>Status</th><th>Issued</th><th></th></tr></thead>
<tbody>
<?php if (empty($rows)): ?><tr><td colspan="7"><div class="empty-state py-4 mb-0">No assets found.</div></td></tr>
<?php else: foreach ($rows as $r): ?>
<tr>
<td class="fw-semibold"><?= e($r['asset_tag']) ?></td>
<td><?= e($r['asset_name']) ?></td>
<td><?= e($r['employee_name'] ?? '—') ?></td>
<td><?= e($r['asset_type']) ?></td>
<td><?= status_badge($r['status']) ?></td>
<td><?= e(format_date($r['assigned_date'])) ?></td>
<td class="text-end text-nowrap">
<?php if (can('assets.manage')): ?>
<form method="POST" action="/admin/assets/<?= (int) $r['id'] ?>" class="d-inline-flex gap-1 align-items-center me-1">
<?= csrf_field() ?>
<select name="status" class="form-select form-select-sm" style="width:110px">
<?php foreach (['assigned','returned','lost','damaged','retired'] as $st): ?>
<option value="<?= $st ?>" <?= $r['status'] === $st ? 'selected' : '' ?>><?= e(ucfirst($st)) ?></option>
<?php endforeach; ?>
</select>
<input type="date" name="return_date" class="form-control form-control-sm" style="width:140px" value="<?= e($r['return_date'] ?? '') ?>">
<button class="action-btn" type="submit" title="Update" aria-label="Update" data-bs-toggle="tooltip" data-bs-placement="top">
    <i data-lucide="check"></i>
</button>
</form>
<?php
$actions = [[
    'type' => 'form',
    'icon' => 'archive',
    'label' => 'Archive',
    'variant' => 'danger',
    'method' => 'POST',
    'action' => '/admin/assets/' . (int) $r['id'] . '/delete',
    'confirm' => 'Archive this asset record?',
]];
include config('app.paths.views') . '/partials/table-actions.php';
?>
<?php endif; ?>
</td>
</tr>
<?php endforeach; endif; ?>
</tbody></table></div></div>
</div>
<?php if (can('assets.manage')): ?>
<div class="col-lg-4"><div class="card ems-card"><div class="card-header">Assign Asset</div><div class="card-body">
<form method="POST" action="/admin/assets"><?= csrf_field() ?>
<div class="mb-2"><label class="form-label small" for="employee_id">Employee</label><select name="employee_id" class="form-select form-select-sm" required id="employee_id"><option value="">Select…</option><?php foreach ($employees as $e): ?><option value="<?= (int)$e['id'] ?>"><?= e($e['name']) ?></option><?php endforeach; ?></select></div>
<div class="mb-2"><label class="form-label small" for="asset_name">Asset name</label><input name="asset_name" class="form-control form-control-sm" required id="asset_name"></div>
<div class="mb-2"><label class="form-label small" for="asset_tag">Asset tag</label><input name="asset_tag" class="form-control form-control-sm" required id="asset_tag"></div>
<div class="mb-2"><label class="form-label small" for="asset_type">Type</label><select name="asset_type" class="form-select form-select-sm" id="asset_type"><option>laptop</option><option>mobile</option><option>sim</option><option>access_card</option><option>vehicle</option><option>equipment</option><option>other</option></select></div>
<div class="mb-2"><label class="form-label small" for="serial_number">Serial</label><input name="serial_number" class="form-control form-control-sm" id="serial_number"></div>
<div class="mb-2"><label class="form-label small" for="assigned_date">Issue date</label><input type="date" name="assigned_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required id="assigned_date"></div>
<button class="btn btn-primary btn-sm w-100">Assign</button>
</form></div></div></div>
<?php endif; ?>
</div>
