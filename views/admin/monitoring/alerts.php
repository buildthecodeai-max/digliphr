<?php /** @var array $records */ /** @var array $filters */ ?>
<div class="container-fluid py-3">
    <h1 class="h4 mb-3">Monitoring Alerts</h1>
    <?php include config('app.paths.views') . '/partials/alerts.php'; ?>
    <form method="get" class="mb-3">
        <select name="is_resolved" class="form-select form-select-sm w-auto d-inline-block" onchange="this.form.submit()">
            <option value="0" <?= ($filters['is_resolved'] ?? '') === '0' ? 'selected' : '' ?>>Open</option>
            <option value="1" <?= ($filters['is_resolved'] ?? '') === '1' ? 'selected' : '' ?>>Resolved</option>
            <option value="" <?= ($filters['is_resolved'] ?? '') === '' ? 'selected' : '' ?>>All</option>
        </select>
    </form>
    <div class="table-responsive border rounded-3 ems-surface monitoring-panel">
        <table class="table table-sm mb-0">
            <thead><tr><th>When</th><th>Type</th><th>Title</th><th>Employee</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($records['data'] as $row): ?>
                <tr>
                    <td><?= e($row['created_at']) ?></td>
                    <td><?= e($row['alert_type']) ?></td>
                    <td><?= e($row['title']) ?><div class="small text-muted"><?= e($row['message'] ?? '') ?></div></td>
                    <td><?= e(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: '—') ?></td>
                    <td>
                        <?php if (empty($row['is_resolved'])): ?>
                        <form method="post" action="/admin/monitoring/alerts/<?= (int) $row['id'] ?>/resolve"><?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-secondary">Resolve</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($records['data'])): ?><tr><td colspan="5" class="text-center text-muted py-4">No alerts.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
