<?php /** @var array $records */ /** @var array $filters */ ?>
<div class="container-fluid py-3">
    <h1 class="h4 mb-3">Devices</h1>
    <?php include config('app.paths.views') . '/partials/alerts.php'; ?>
    <form class="row g-2 mb-3" method="get">
        <div class="col-auto">
            <select name="status" class="form-select form-select-sm">
                <option value="">All statuses</option>
                <?php foreach (['pending','approved','revoked','blocked'] as $st): ?>
                <option value="<?= e($st) ?>" <?= ($filters['status'] ?? '') === $st ? 'selected' : '' ?>><?= e($st) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto"><input type="search" name="q" class="form-control form-control-sm" placeholder="Search" value="<?= e($filters['q'] ?? '') ?>"></div>
        <div class="col-auto"><button class="btn btn-sm btn-primary">Filter</button></div>
    </form>
    <div class="table-responsive border rounded-3 ems-surface monitoring-panel">
        <table class="table table-sm mb-0 align-middle">
            <thead><tr><th>Employee</th><th>Host</th><th>OS</th><th>Agent</th><th>Status</th><th>Last seen</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($records['data'] as $row): ?>
                <tr>
                    <td><?= e(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?></td>
                    <td><?= e($row['hostname'] ?? $row['device_uid']) ?></td>
                    <td><?= e(trim(($row['os_name'] ?? '') . ' ' . ($row['os_version'] ?? ''))) ?></td>
                    <td><?= e($row['agent_version'] ?? '—') ?></td>
                    <td><?= e($row['status']) ?></td>
                    <td><?= e($row['last_seen_at'] ?? '—') ?></td>
                    <td class="text-nowrap">
                        <?php if ($row['status'] === 'pending'): ?>
                        <form method="post" action="/admin/monitoring/devices/<?= (int) $row['id'] ?>/approve" class="d-inline"><?= csrf_field() ?><button class="btn btn-sm btn-outline-success">Approve</button></form>
                        <?php endif; ?>
                        <?php if ($row['status'] !== 'revoked'): ?>
                        <form method="post" action="/admin/monitoring/devices/<?= (int) $row['id'] ?>/revoke" class="d-inline"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger">Revoke</button></form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($records['data'])): ?><tr><td colspan="7" class="text-center text-muted py-4">No devices.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
