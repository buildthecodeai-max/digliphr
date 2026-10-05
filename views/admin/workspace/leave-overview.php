<?php
/** @var array $metrics */
/** @var array $recent */
?>
<div class="page-header">
    <div>
        <h1>Leave Overview</h1>
        <p class="subtitle">Approvals, active leave, and type configuration</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-sm btn-primary" href="/admin/leave/pending"><i data-lucide="clipboard-check" class="me-1" style="width:14px;height:14px"></i>Pending approvals</a>
        <a class="btn btn-sm btn-soft" href="/admin/leave"><i data-lucide="list" class="me-1" style="width:14px;height:14px"></i>All requests</a>
    </div>
</div>

<?php include config('app.paths.views') . '/partials/workspace-overview-strip.php'; ?>

<div class="card">
    <div class="card-header">Recent leave requests</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead>
                <tr>
                    <th>Employee</th>
                    <th>Type</th>
                    <th>Dates</th>
                    <th>Days</th>
                    <th>Status</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($recent)): ?>
                    <tr><td colspan="6" class="text-muted p-3">No recent leave requests.</td></tr>
                <?php else: ?>
                    <?php foreach ($recent as $row): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= e(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?></div>
                                <div class="small text-muted"><?= e($row['employee_code'] ?? '') ?></div>
                            </td>
                            <td><?= e($row['leave_type_name'] ?? '') ?></td>
                            <td class="small"><?= e(format_date($row['start_date'] ?? null)) ?> – <?= e(format_date($row['end_date'] ?? null)) ?></td>
                            <td><?= e((string) ($row['chargeable_days'] ?? '')) ?></td>
                            <td><?= status_badge((string) ($row['status'] ?? '')) ?></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-soft" href="/admin/leave/<?= (int) $row['id'] ?>">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
