<div class="card"><div class="list-group list-group-flush">
<?php if (empty($rows)): ?><div class="p-4 text-muted text-center">No notifications.</div>
<?php else: foreach ($rows as $n): ?>
<div class="list-group-item <?= empty($n['is_read']) ? 'bg-light' : '' ?>">
<div class="d-flex justify-content-between"><strong><?= e($n['title']) ?></strong><span class="small text-muted"><?= e(format_datetime($n['created_at'])) ?></span></div>
<div class="small"><?= e($n['message']) ?></div>
</div>
<?php endforeach; endif; ?>
</div></div>
