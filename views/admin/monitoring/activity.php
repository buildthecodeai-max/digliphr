<?php /** @var array $records */ /** @var array $filters */ ?>
<div class="container-fluid py-3">
    <h1 class="h4 mb-3">Activity</h1>
    <form class="row g-2 mb-3" method="get">
        <div class="col-auto"><input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($filters['date_from'] ?? '') ?>"></div>
        <div class="col-auto"><input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($filters['date_to'] ?? '') ?>"></div>
        <div class="col-auto"><input type="text" name="application_name" class="form-control form-control-sm" placeholder="Application" value="<?= e($filters['application_name'] ?? '') ?>"></div>
        <div class="col-auto"><button class="btn btn-sm btn-primary">Filter</button></div>
    </form>
    <div class="table-responsive border rounded-3 ems-surface monitoring-panel">
        <table class="table table-sm mb-0">
            <thead><tr><th>Employee</th><th>App</th><th>Window</th><th>Status</th><th>Start</th><th>Duration</th></tr></thead>
            <tbody>
            <?php foreach ($records['data'] as $row): ?>
                <tr>
                    <td><?= e(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?></td>
                    <td><?= e($row['application_name'] ?? '—') ?></td>
                    <td><?= e($row['window_title'] ?? '—') ?></td>
                    <td><?= e($row['activity_status']) ?></td>
                    <td><?= e($row['started_at']) ?></td>
                    <td><?= e((string) $row['duration_seconds']) ?>s</td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($records['data'])): ?><tr><td colspan="6" class="text-center text-muted py-4">No activity.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
