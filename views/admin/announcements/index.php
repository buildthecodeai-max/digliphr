<div class="page-header">
    <div>
        <h1>Announcements</h1>
        <p class="subtitle">Company-wide communications</p>
    </div>
    <?php if (can('announcements.manage')): ?>
    <a href="/admin/announcements/create" class="btn btn-primary btn-sm">New Announcement</a>
    <?php endif; ?>
</div>

<?php include config('app.paths.views') . '/partials/alerts.php'; ?>

<div class="card ems-card"><div class="table-responsive"><table class="table table-sm table-hover mb-0">
<thead><tr><th>Title</th><th>Audience</th><th>Publish</th><th>Status</th><th></th></tr></thead>
<tbody>
<?php if (empty($rows)): ?><tr><td colspan="5"><div class="empty-state py-4 mb-0">No announcements found.</div></td></tr>
<?php else: foreach ($rows as $r): ?>
<tr>
<td class="fw-semibold"><?= e($r['title']) ?></td>
<td><?= e($r['audience'] ?? 'all') ?></td>
<td><?= e(format_datetime($r['publish_at'] ?? null)) ?></td>
<td><?= !empty($r['is_published']) ? status_badge('approved') : status_badge('draft') ?></td>
<td class="text-end text-nowrap">
<?php
$aid = (int) $r['id'];
$actions = [];
if (can('announcements.manage')) {
    $actions[] = ['type' => 'link', 'icon' => 'pencil', 'label' => 'Edit', 'variant' => 'warning', 'href' => '/admin/announcements/' . $aid . '/edit'];
    $actions[] = [
        'type' => 'form',
        'icon' => 'trash-2',
        'label' => 'Delete',
        'variant' => 'danger',
        'action' => '/admin/announcements/' . $aid . '/delete',
        'confirm' => 'Delete this announcement?',
    ];
}
include config('app.paths.views') . '/partials/table-actions.php';
?>
</td>
</tr>
<?php endforeach; endif; ?>
</tbody></table></div></div>
