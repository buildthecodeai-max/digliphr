<div class="d-flex justify-content-end mb-2"><?php if (can('salary.manage')): ?><a href="/admin/salary-structures/create" class="btn btn-primary btn-sm">Add Structure</a><?php endif; ?></div>
<div class="card"><div class="table-responsive"><table class="table table-sm mb-0">
<thead class="table-light"><tr><th>Name</th><th>Code</th><th>Company</th><th>Items</th><th>Status</th><th></th></tr></thead>
<tbody>
<?php if (empty($structures)): ?><tr><td colspan="6" class="text-center text-muted py-4">No structures.</td></tr>
<?php else: foreach ($structures as $s): ?>
<tr>
<td><?= e($s['name']) ?></td>
<td><?= e($s['code']) ?></td>
<td><?= e($s['company_name'] ?? '—') ?></td>
<td><?= (int)($s['item_count'] ?? 0) ?></td>
<td><?= status_badge(!empty($s['is_active'])?'active':'inactive') ?></td>
<td class="text-end text-nowrap">
<?php
$actions = [];
if (can('salary.manage')) {
    $actions[] = ['type' => 'link', 'icon' => 'pencil', 'label' => 'Edit', 'variant' => 'warning', 'href' => '/admin/salary-structures/' . (int) $s['id'] . '/edit'];
    $actions[] = [
        'type' => 'form',
        'icon' => 'archive',
        'label' => 'Archive',
        'variant' => 'danger',
        'method' => 'POST',
        'action' => '/admin/salary-structures/' . (int) $s['id'] . '/delete',
        'confirm' => 'Archive this salary structure?',
    ];
}
include config('app.paths.views') . '/partials/table-actions.php';
?>
</td>
</tr>
<?php endforeach; endif; ?>
</tbody></table></div></div>
