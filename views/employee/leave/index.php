<?php
$total = 0; $used = 0; $pending = 0; $remaining = 0;
foreach ($balances as $b) {
    $total += (float)($b['opening_balance'] ?? 0) + (float)($b['accrued'] ?? 0) + (float)($b['carried_forward'] ?? 0);
    $used += (float)($b['used'] ?? 0);
    $pending += (float)($b['pending'] ?? 0);
    $remaining += (float)($b['closing_balance'] ?? 0);
}
?>
<div class="page-header">
    <div>
        <h1>My Leave</h1>
        <p class="subtitle">Balances, history, and leave requests</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="/employee/leave/create" class="btn btn-sm btn-primary">
            <i data-lucide="calendar-plus" class="me-1" style="width:14px;height:14px"></i>Apply for Leave
        </a>
    </div>
</div>

<div class="row g-3 metrics-row mb-3">
    <div class="col-6 col-xl-3 stagger-item">
        <div class="metric-card tone-purple">
            <div class="metric-icon"><i data-lucide="calendar-days"></i></div>
            <div class="metric-label">Allowance</div>
            <div class="metric-value"><?= number_format($total, 1) ?></div>
        </div>
    </div>
    <div class="col-6 col-xl-3 stagger-item">
        <div class="metric-card tone-orange">
            <div class="metric-icon"><i data-lucide="calendar-minus"></i></div>
            <div class="metric-label">Used</div>
            <div class="metric-value"><?= number_format($used, 1) ?></div>
        </div>
    </div>
    <div class="col-6 col-xl-3 stagger-item">
        <div class="metric-card tone-blue">
            <div class="metric-icon"><i data-lucide="clock-3"></i></div>
            <div class="metric-label">Pending</div>
            <div class="metric-value"><?= number_format($pending, 1) ?></div>
        </div>
    </div>
    <div class="col-6 col-xl-3 stagger-item">
        <div class="metric-card tone-mint">
            <div class="metric-icon"><i data-lucide="calendar-check"></i></div>
            <div class="metric-label">Remaining</div>
            <div class="metric-value"><?= number_format($remaining, 1) ?></div>
        </div>
    </div>
</div>

<div class="card ems-card mb-3">
    <div class="card-header">Leave History</div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead><tr><th>Type</th><th>Dates</th><th>Days</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($requests)): ?>
                <tr><td colspan="5"><div class="empty-state py-4 mb-0">No leave requests yet.</div></td></tr>
            <?php else: foreach ($requests as $row): ?>
                <tr>
                    <td><?= e($row['leave_type_name'] ?? '') ?></td>
                    <td><?= e(format_date($row['start_date'])) ?> → <?= e(format_date($row['end_date'])) ?></td>
                    <td><?= e((string)$row['chargeable_days']) ?></td>
                    <td><?= status_badge($row['status']) ?></td>
                    <td class="text-end text-nowrap">
                        <?php
                        $actions = [
                            ['type' => 'link', 'icon' => 'eye', 'label' => 'View', 'href' => '/employee/leave/' . (int) $row['id']],
                        ];
                        include config('app.paths.views') . '/partials/table-actions.php';
                        ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (!empty($paginator)): ?>
        <div class="card-footer py-2"><?= paginate_links($paginator, '/employee/leave') ?></div>
    <?php endif; ?>
</div>

<?php if (!empty($balances)): ?>
<div class="card ems-card">
    <div class="card-header">Balances by Type</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr><th>Type</th><th>Opening</th><th>Used</th><th>Pending</th><th>Closing</th></tr></thead>
            <tbody>
            <?php foreach ($balances as $b): ?>
                <tr>
                    <td><?= e($b['leave_type_name'] ?? '') ?></td>
                    <td><?= e((string)$b['opening_balance']) ?></td>
                    <td><?= e((string)$b['used']) ?></td>
                    <td><?= e((string)$b['pending']) ?></td>
                    <td class="fw-semibold"><?= e((string)$b['closing_balance']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
