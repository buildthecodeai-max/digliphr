<div class="page-header">
    <div>
        <h1 class="h4 mb-1">Roles &amp; Permissions</h1>
    </div>
    <?php if (can('roles.manage')): ?>
        <a href="/admin/roles/create" class="btn btn-primary btn-sm"><i data-lucide="plus" class="me-1" style="width:14px;height:14px"></i>Add Role</a>
    <?php endif; ?>
</div>
<div class="card"><div class="table-responsive"><table class="table table-sm mb-0">
<thead class="table-light"><tr><th>Role</th><th>Slug</th><th>Permissions</th><th>Users</th><th></th></tr></thead>
<tbody>
<?php foreach ($roles as $r): ?>
<tr>
<td><?= e($r['name']) ?><?php if ((int) $r['is_system'] === 1): ?> <span class="badge bg-secondary">System</span><?php endif; ?></td>
<td><code><?= e($r['slug']) ?></code></td>
<td><?= (int)($r['permission_count'] ?? 0) ?></td>
<td><?= (int)($r['user_count'] ?? 0) ?></td>
<td class="text-end text-nowrap"><?php
$actions = [];
if (can('roles.manage')) {
    $actions[] = ['type' => 'link', 'icon' => 'shield', 'label' => 'Permissions', 'href' => '/admin/roles/' . (int) $r['id'] . '/edit'];
}
if (can('roles.manage') && (int) $r['is_system'] === 0) {
    $actions[] = [
        'type' => 'form',
        'icon' => 'trash-2',
        'label' => 'Delete',
        'variant' => 'danger',
        'action' => '/admin/roles/' . (int) $r['id'] . '/delete',
        'confirm' => (int) ($r['user_count'] ?? 0) > 0
            ? 'This role still has ' . (int) $r['user_count'] . ' user(s) assigned — reassign them first. Delete anyway?'
            : 'Delete this role? This cannot be undone.',
    ];
}
include config('app.paths.views') . '/partials/table-actions.php';
?></td>
</tr>
<?php endforeach; ?>
</tbody></table></div></div>
<script>if (window.lucide) lucide.createIcons();</script>
