<?php /** @var array $rows */ /** @var array $filters */ ?>
<div class="container-fluid py-3">
    <h1 class="h4 mb-3">Applications</h1>
    <form class="row g-2 mb-3" method="get">
        <div class="col-auto"><input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($filters['date_from'] ?? '') ?>"></div>
        <div class="col-auto"><input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($filters['date_to'] ?? '') ?>"></div>
        <div class="col-auto"><button class="btn btn-sm btn-primary">Filter</button></div>
    </form>
    <div class="table-responsive border rounded-3 ems-surface monitoring-panel">
        <table class="table table-sm mb-0">
            <thead><tr><th>Application</th><th>Active (sec)</th><th>Idle (sec)</th><th>Segments</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= e($row['application_name']) ?></td>
                    <td><?= e((string) ((int)$row['total_seconds'] - (int)$row['idle_seconds'])) ?></td>
                    <td><?= e((string) $row['idle_seconds']) ?></td>
                    <td><?= e((string) $row['segment_count']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?><tr><td colspan="4" class="text-center text-muted py-4">No data.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
