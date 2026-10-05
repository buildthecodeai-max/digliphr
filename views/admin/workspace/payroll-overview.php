<?php
/** @var array $metrics */
/** @var array $recent */
?>
<div class="page-header">
    <div>
        <h1>Payroll Overview</h1>
        <p class="subtitle">Period status, payslips, loans, and advances</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if (can('payroll.process')): ?>
            <a class="btn btn-sm btn-primary" href="/admin/payroll/create"><i data-lucide="plus" class="me-1" style="width:14px;height:14px"></i>Create period</a>
        <?php endif; ?>
        <a class="btn btn-sm btn-soft" href="/admin/payroll"><i data-lucide="list" class="me-1" style="width:14px;height:14px"></i>All periods</a>
    </div>
</div>

<?php include config('app.paths.views') . '/partials/workspace-overview-strip.php'; ?>

<div class="card">
    <div class="card-header">Recent payroll periods</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead>
                <tr>
                    <th>Period</th>
                    <th>Status</th>
                    <th>Net total</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($recent)): ?>
                    <tr><td colspan="4" class="text-muted p-3">No payroll periods yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($recent as $row): ?>
                        <tr>
                            <td class="fw-semibold"><?= e(sprintf('%04d-%02d', (int) $row['period_year'], (int) $row['period_month'])) ?></td>
                            <td><?= status_badge((string) ($row['status'] ?? '')) ?></td>
                            <td><?= e(format_money($row['total_net'] ?? 0)) ?></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-soft" href="/admin/payroll/<?= (int) $row['id'] ?>">Open</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
