<?php
/** @var array $overview */
/** @var array $live */
/** @var array $siteSummary */
/** @var array $topDomains */
?>
<div class="container-fluid py-3">
    <h1 class="h4 mb-3">Work Monitoring</h1>
    <?php include config('app.paths.views') . '/partials/alerts.php'; ?>
    <div class="row g-3 mb-4">
        <?php
        $cards = [
            ['Active sessions', $overview['active_sessions'] ?? 0, '/admin/monitoring/live'],
            ['Screenshots today', $overview['screenshots_today'] ?? 0, '/admin/monitoring/screenshots'],
            ['Pending devices', $overview['pending_devices'] ?? 0, '/admin/monitoring/devices?status=pending'],
            ['Open alerts', $overview['open_alerts'] ?? 0, '/admin/monitoring/alerts'],
        ];
        foreach ($cards as [$label, $value, $href]): ?>
        <div class="col-6 col-md-3">
            <a href="<?= e($href) ?>" class="text-decoration-none">
                <div class="border rounded-3 p-3 ems-surface monitoring-card h-100">
                    <div class="text-muted small"><?= e($label) ?></div>
                    <div class="fs-3 fw-semibold"><?= e((string) $value) ?></div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php if (!empty($siteSummary)): ?>
    <div class="row g-3 mb-4">
        <?php foreach ([['Website focus', $siteSummary['productive_minutes'] ?? 0, 'success'], ['Website distraction', $siteSummary['distracting_minutes'] ?? 0, 'warning'], ['Blocked time', $siteSummary['blocked_minutes'] ?? 0, 'danger'], ['Policy actions', $siteSummary['warnings'] ?? 0, 'info']] as [$label, $value, $tone]): ?>
        <div class="col-6 col-md-3"><a href="/admin/monitoring/web-activity" class="text-decoration-none"><div class="border rounded-3 p-3 ems-surface monitoring-card h-100"><div class="text-muted small"><?= e($label) ?></div><div class="fs-4 fw-semibold text-<?= e($tone) ?>"><?= e((string) $value) ?><?= $label === 'Policy actions' ? '' : ' min' ?></div></div></a></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <h2 class="h6">Live now</h2>
    <div class="table-responsive border rounded-3 ems-surface monitoring-panel">
        <table class="table table-sm mb-0 align-middle">
            <thead><tr><th>Employee</th><th>Status</th><th>Started</th><th>Last heartbeat</th><th>Interval</th></tr></thead>
            <tbody>
            <?php if (empty($live)): ?>
                <tr><td colspan="5" class="text-muted text-center py-4">No active monitoring sessions.</td></tr>
            <?php else: foreach ($live as $row): ?>
                <tr>
                    <td><?= e(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) . ' (' . ($row['employee_code'] ?? '') . ')') ?></td>
                    <td><span class="badge text-bg-success"><?= e($row['status']) ?></span></td>
                    <td><?= e($row['started_at'] ?? '—') ?></td>
                    <td><?= e($row['last_heartbeat_at'] ?? '—') ?></td>
                    <td><?= e((string) ($row['screenshot_interval_minutes'] ?? '')) ?> min</td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (!empty($topDomains)): ?>
    <div class="card mt-4 ems-surface monitoring-panel"><div class="card-header d-flex justify-content-between align-items-center"><span>Top distracting domains today</span><a href="/admin/monitoring/web-activity" class="small">View history</a></div><div class="list-group list-group-flush"><?php foreach ($topDomains as $domain): ?><div class="list-group-item d-flex justify-content-between"><span><?= e($domain['domain']) ?></span><span class="text-muted"><?= e((string) round(((int) $domain['total_seconds']) / 60)) ?> min · <?= e((string) $domain['visits']) ?> visits</span></div><?php endforeach; ?></div></div>
    <?php endif; ?>
</div>
