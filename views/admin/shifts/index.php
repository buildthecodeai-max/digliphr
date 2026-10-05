<div class="d-flex justify-content-between mb-2">
    <div></div>
    <?php if (can('shifts.create')): ?><a href="/admin/shifts/create" class="btn btn-primary btn-sm">Add Shift</a><?php endif; ?>
</div>
<div class="card">
<div class="table-responsive">
<table class="table table-sm table-hover mb-0 align-middle">
<thead class="table-light"><tr><th>Name</th><th>Code</th><th>Time</th><th>Grace</th><th>Break</th><th>Flags</th><th>Status</th><th></th></tr></thead>
<tbody>
<?php if (empty($shifts)): ?>
<tr><td colspan="8" class="text-center text-muted py-4">No shifts configured.</td></tr>
<?php else: foreach ($shifts as $s): ?>
<tr>
    <td><?= e($s['name']) ?><div class="text-muted small"><?= e($s['company_name'] ?? '') ?></div></td>
    <td><?= e($s['code'] ?? '—') ?></td>
    <td><?= e(substr((string)$s['start_time'], 0, 5)) ?> – <?= e(substr((string)$s['end_time'], 0, 5)) ?></td>
    <td><?= (int)$s['grace_minutes'] ?>m</td>
    <td><?= (int)$s['break_minutes'] ?>m</td>
    <td class="small">
        <?= !empty($s['is_overnight']) ? '<span class="badge text-bg-secondary">Overnight</span> ' : '' ?>
        <?= !empty($s['is_flexible']) ? '<span class="badge text-bg-info">Flexible</span>' : '' ?>
    </td>
    <td><?= status_badge(!empty($s['is_active']) ? 'active' : 'inactive') ?></td>
    <td class="text-end text-nowrap">
        <?php
        $actions = [];
        if (can('shifts.update')) {
            $actions[] = ['type' => 'link', 'icon' => 'pencil', 'label' => 'Edit', 'variant' => 'warning', 'href' => '/admin/shifts/' . (int) $s['id'] . '/edit'];
        }
        if (can('shifts.delete')) {
            $actions[] = ['type' => 'form', 'icon' => 'trash-2', 'label' => 'Delete', 'variant' => 'danger',
                'action' => '/admin/shifts/' . (int) $s['id'] . '/delete',
                'confirm' => 'Delete shift "' . e($s['name']) . '"?'];
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
