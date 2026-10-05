<?php /** @var array $rows */ /** @var array $filters */ ?>
<div class="container-fluid py-3">
    <div class="page-header"><div><h1>Monitoring reports</h1><p class="subtitle">Daily work activity, focus trends, and website distraction signals.</p></div><a href="/admin/monitoring/web-activity" class="btn btn-primary">Open website activity</a></div>
    <?php if (!empty($siteSummary)): ?><div class="row g-3 mb-3"><?php foreach ([['Focus time', $siteSummary['productive_minutes'] ?? 0], ['Neutral time', $siteSummary['neutral_minutes'] ?? 0], ['Distraction time', $siteSummary['distracting_minutes'] ?? 0], ['Policy actions', $siteSummary['warnings'] ?? 0]] as [$label, $value]): ?><div class="col-6 col-xl-3"><div class="metric-card"><div class="metric-label"><?= e($label) ?></div><div class="metric-value"><?= e((string) $value) ?><?= $label === 'Policy actions' ? '' : ' min' ?></div></div></div><?php endforeach; ?></div><?php endif; ?>
    <form class="row g-2 mb-3" method="get">
        <div class="col-auto"><input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($filters['date_from'] ?? '') ?>"></div>
        <div class="col-auto"><input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($filters['date_to'] ?? '') ?>"></div>
        <div class="col-auto"><button class="btn btn-sm btn-primary">Run</button></div>
    </form>
    <div class="table-responsive border rounded-3 ems-surface monitoring-panel">
        <table class="table table-sm mb-0">
            <thead><tr><th>Employee</th><th>Sessions</th><th>Monitored (min)</th><th>Screenshots</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= e(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) . ' (' . ($row['employee_code'] ?? '') . ')') ?></td>
                    <td><?= e((string) $row['session_count']) ?></td>
                    <td><?= e((string) $row['monitored_minutes']) ?></td>
                    <td><?= e((string) $row['screenshot_count']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?><tr><td colspan="4" class="text-center text-muted py-4">No report data.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (!empty($topDomains)): ?><div class="card mt-3"><div class="card-header">Top distracting domains</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Domain</th><th>Category</th><th>Visits</th><th>Total time</th></tr></thead><tbody><?php foreach ($topDomains as $domain): ?><tr><td><?= e($domain['domain']) ?></td><td><?= status_badge($domain['category']) ?></td><td><?= e((string) $domain['visits']) ?></td><td><?= e((string) round(((int) $domain['total_seconds']) / 60)) ?> min</td></tr><?php endforeach; ?></tbody></table></div></div><?php endif; ?>
</div>
