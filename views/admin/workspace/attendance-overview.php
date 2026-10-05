<?php
/** @var array $metrics */
/** @var array $recent */
?>
<div class="page-header">
    <div>
        <h1>Attendance Overview</h1>
        <p class="subtitle">Today’s workforce presence and correction queue</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if (can('attendance.view')): ?>
            <a class="btn btn-sm btn-primary" href="/admin/attendance"><i data-lucide="list" class="me-1" style="width:14px;height:14px"></i>All records</a>
        <?php endif; ?>
        <?php if (can('attendance.manual')): ?>
            <a class="btn btn-sm btn-soft" href="/admin/attendance/manual"><i data-lucide="plus" class="me-1" style="width:14px;height:14px"></i>Manual entry</a>
        <?php endif; ?>
    </div>
</div>

<?php include config('app.paths.views') . '/partials/workspace-overview-strip.php'; ?>

<div class="card">
    <div class="card-header">Recent attendance</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead>
                <tr>
                    <th>Employee</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Check-in</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($recent)): ?>
                    <tr><td colspan="5" class="text-muted p-3">No recent records.</td></tr>
                <?php else: ?>
                    <?php foreach ($recent as $row): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= e(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?></div>
                                <div class="small text-muted"><?= e($row['employee_code'] ?? '') ?></div>
                            </td>
                            <td><?= e(format_date($row['attendance_date'] ?? null)) ?></td>
                            <td><?= status_badge((string) ($row['status'] ?? '')) ?></td>
                            <td class="small"><?= e($row['check_in_at'] ? format_datetime($row['check_in_at']) : '—') ?></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-soft" href="/admin/attendance/<?= (int) $row['id'] ?>">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
