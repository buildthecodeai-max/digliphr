<?php /** @var array $records */ /** @var array $filters */ ?>
<div class="container-fluid py-3">
    <h1 class="h4 mb-3">Screenshots</h1>
    <?php include config('app.paths.views') . '/partials/alerts.php'; ?>
    <form class="row g-2 mb-3" method="get">
        <div class="col-auto"><input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($filters['date_from'] ?? '') ?>"></div>
        <div class="col-auto"><input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($filters['date_to'] ?? '') ?>"></div>
        <div class="col-auto"><button class="btn btn-sm btn-primary">Filter</button></div>
    </form>
    <div class="row g-3">
        <?php foreach ($records['data'] as $row): ?>
        <div class="col-6 col-md-3 col-lg-2">
            <div class="border rounded-3 overflow-hidden ems-surface monitoring-panel">
                <a href="/files/monitoring-screenshot/<?= (int) $row['id'] ?>" target="_blank">
                    <img src="/files/monitoring-screenshot/<?= (int) $row['id'] ?>?thumb=1" alt="" class="w-100 monitoring-shot-thumb">
                </a>
                <div class="p-2 small">
                    <div><?= e(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?></div>
                    <div class="text-muted"><?= e($row['captured_at'] ?? '') ?></div>
                    <?php if (can('monitoring.delete_screenshots')): ?>
                    <form method="post" action="/admin/monitoring/screenshots/<?= (int) $row['id'] ?>/delete" onsubmit="return confirm('Delete screenshot?');"><?= csrf_field() ?>
                        <button class="btn btn-link btn-sm text-danger p-0">Delete</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($records['data'])): ?><div class="col-12 text-muted">No screenshots.</div><?php endif; ?>
    </div>
</div>
