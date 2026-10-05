<div class="card mb-3"><div class="card-body py-2">
<form method="GET" class="row g-2">
<div class="col-md-3"><input name="module" class="form-control form-control-sm" placeholder="Module" value="<?= e($filters['module'] ?? '') ?>"></div>
<div class="col-md-3"><input name="action" class="form-control form-control-sm" placeholder="Action" value="<?= e($filters['action'] ?? '') ?>"></div>
<div class="col-md-2"><button class="btn btn-secondary btn-sm">Filter</button></div>
</form></div></div>
<div class="card"><div class="table-responsive"><table class="table table-sm mb-0">
<thead class="table-light"><tr><th>When</th><th>User</th><th>Action</th><th>Module</th><th>Record</th><th>IP</th></tr></thead>
<tbody>
<?php if (empty($rows)): ?><tr><td colspan="6" class="text-center text-muted py-4">No audit logs.</td></tr>
<?php else: foreach ($rows as $r): ?>
<tr>
<td><?= e(format_datetime($r['created_at'])) ?></td>
<td><?= e($r['user_name'] ?? 'System') ?></td>
<td><?= e($r['action']) ?></td>
<td><?= e($r['module']) ?></td>
<td><?= e((string)($r['record_id'] ?? '—')) ?></td>
<td class="small"><?= e($r['ip_address'] ?? '—') ?></td>
</tr>
<?php endforeach; endif; ?>
</tbody></table></div>
<?php if (!empty($paginator)): ?><div class="card-footer py-2"><?= paginate_links($paginator, '/admin/audit?'.http_build_query(array_filter($filters??[]))) ?></div><?php endif; ?>
</div>
