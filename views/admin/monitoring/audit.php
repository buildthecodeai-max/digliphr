<?php /** @var array $records */ ?>
<div class="container-fluid py-3">
    <h1 class="h4 mb-3">Monitoring Audit Logs</h1>
    <div class="table-responsive border rounded-3 ems-surface monitoring-panel">
        <table class="table table-sm mb-0">
            <thead><tr><th>When</th><th>User</th><th>Action</th><th>Table</th><th>Record</th></tr></thead>
            <tbody>
            <?php foreach ($records['data'] as $row): ?>
                <tr>
                    <td><?= e($row['created_at']) ?></td>
                    <td><?= e((string) ($row['user_id'] ?? '—')) ?></td>
                    <td><?= e($row['action']) ?></td>
                    <td><?= e($row['table_name']) ?></td>
                    <td><?= e((string) ($row['record_id'] ?? '—')) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($records['data'])): ?><tr><td colspan="5" class="text-center text-muted py-4">No audit entries.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
