<div class="card mb-3"><div class="card-body py-2">
<form class="row g-2 align-items-end" method="GET">
    <div class="col-md-4">
        <label class="form-label small mb-0" for="company_id">Company</label>
        <select name="company_id" class="form-select form-select-sm" onchange="this.form.submit()" id="company_id">
            <?php foreach ($companies as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= (int)$companyId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-8 text-end">
        <?php if (can('holidays.create')): ?><a href="/admin/leave/holidays/create" class="btn btn-primary btn-sm">Add Holiday</a><?php endif; ?>
    </div>
</form>
</div></div>
<div class="card">
<div class="table-responsive">
<table class="table table-sm table-hover mb-0">
<thead class="table-light"><tr><th>Name</th><th>Date</th><th>Type</th><th>Branch</th><th>Status</th><th></th></tr></thead>
<tbody>
<?php if (empty($holidays)): ?>
<tr><td colspan="6" class="text-center text-muted py-4">No holidays found.</td></tr>
<?php else: foreach ($holidays as $h): ?>
<tr>
    <td><?= e($h['name']) ?></td>
    <td><?= e(format_date($h['holiday_date'] ?? $h['date'] ?? null)) ?></td>
    <td><?= e($h['type'] ?? '—') ?></td>
    <td><?= e($h['branch_name'] ?? 'All') ?></td>
    <td><?= !empty($h['is_paid']) ? 'Paid' : 'Unpaid' ?></td>
    <td class="text-end text-nowrap">
        <?php
        $actions = [];
        if (can('holidays.update')) {
            $actions[] = ['type' => 'link', 'icon' => 'pencil', 'label' => 'Edit', 'variant' => 'warning', 'href' => '/admin/leave/holidays/' . (int) $h['id'] . '/edit'];
        }
        if (can('holidays.manage')) {
            $actions[] = ['type' => 'form', 'icon' => 'trash-2', 'label' => 'Delete', 'variant' => 'danger',
                'action' => '/admin/leave/holidays/' . (int) $h['id'] . '/delete',
                'confirm' => 'Delete holiday "' . e($h['name']) . '"?'];
        }
        include config('app.paths.views') . '/partials/table-actions.php';
        ?>
    </td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div>
</div>
