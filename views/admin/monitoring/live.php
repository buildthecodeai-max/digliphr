<?php /** @var array $records */ ?>
<div class="container-fluid py-3">
    <h1 class="h4 mb-3">Live Employees</h1>
    <?php include config('app.paths.views') . '/partials/alerts.php'; ?>
    <div class="table-responsive border rounded-3 ems-surface monitoring-panel">
        <table class="table table-sm mb-0 align-middle">
            <thead><tr><th>Employee</th><th>Status</th><th>Device</th><th>Started</th><th>Heartbeat</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($records['data'])): ?>
                <tr><td colspan="6" class="text-muted text-center py-4">No live sessions.</td></tr>
            <?php else: foreach ($records['data'] as $row): ?>
                <tr>
                    <td><?= e(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?></td>
                    <td><?= e($row['status']) ?></td>
                    <td><?= e($row['hostname'] ?? '—') ?></td>
                    <td><?= e($row['started_at'] ?? '—') ?></td>
                    <td><?= e($row['last_heartbeat_at'] ?? '—') ?></td>
                    <td>
                        <?php if (can('monitoring.stop_session')): ?>
                        <form method="post" action="/admin/monitoring/sessions/<?= (int) $row['id'] ?>/stop" class="d-inline"><?= csrf_field() ?>
                            <button class="btn btn-outline-danger btn-sm" type="submit">Stop</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <p class="small text-muted mt-2">Total: <?= (int) ($records['total'] ?? 0) ?></p>
</div>
