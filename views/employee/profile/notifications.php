<div class="page-header">
    <div>
        <h1>Notifications</h1>
        <p class="subtitle">Latest updates from HR and the system</p>
    </div>
</div>

<div class="card ems-card">
    <div class="list-group list-group-flush">
        <?php if (empty($rows)): ?>
            <div class="empty-state py-5 mb-0">You're all caught up.</div>
        <?php else: ?>
            <?php foreach ($rows as $n): ?>
            <div class="list-group-item <?= empty($n['is_read']) ? 'bg-light' : '' ?>">
                <div class="d-flex justify-content-between gap-3">
                    <strong class="small"><?= e($n['title']) ?></strong>
                    <span class="small text-muted text-nowrap"><?= e(format_datetime($n['created_at'])) ?></span>
                </div>
                <div class="small mt-1 text-secondary"><?= e($n['message']) ?></div>
                <?php if (!empty($n['action_url'])): ?>
                    <a href="<?= e($n['action_url']) ?>" class="small mt-1 d-inline-flex align-items-center gap-1">
                        Open <i data-lucide="arrow-right" style="width:12px;height:12px"></i>
                    </a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
