<?php
/** @var array $records */
/** @var array $filters */
?>
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Correction Requests</h1>
        <a href="/admin/attendance" class="btn btn-outline-secondary btn-sm">Back to Attendance</a>
    </div>
    <?php include config('app.paths.views') . '/partials/alerts.php'; ?>
    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small mb-0" for="status">Status</label>
                    <select name="status" class="form-select form-select-sm" id="status">
                        <option value="">All</option>
                        <?php foreach (['pending','approved','rejected','cancelled'] as $s): ?>
                        <option value="<?= e($s) ?>" <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2"><button class="btn btn-secondary btn-sm w-100">Filter</button></div>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr><th>Employee</th><th>Date</th><th>Reason</th><th>Status</th><th>Requested</th><th></th></tr>
                </thead>
                <tbody>
                <?php if (empty($records['data'])): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No correction requests found.</td></tr>
                <?php else: foreach ($records['data'] as $row): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= e($row['employee_name'] ?? '') ?></div>
                            <div class="text-muted small"><?= e($row['employee_code'] ?? '') ?></div>
                        </td>
                        <td><?= e(format_date($row['attendance_date'] ?? null)) ?></td>
                        <td class="small"><?= e($row['reason'] ?? '') ?></td>
                        <td><?= status_badge($row['status'] ?? '') ?></td>
                        <td><?= e(format_datetime($row['created_at'] ?? null)) ?></td>
                        <td class="text-end text-nowrap">
                            <?php
                            $cid = (int) $row['id'];
                            $actions = [
                                ['type' => 'link', 'icon' => 'eye', 'label' => 'Open', 'href' => '/admin/attendance/' . (int) $row['attendance_id']],
                            ];
                            if (($row['status'] ?? '') === 'pending' && can('attendance.correct')) {
                                $actions[] = ['type' => 'form', 'icon' => 'check', 'label' => 'Approve',
                                    'variant' => 'success', 'action' => '/admin/attendance/corrections/' . $cid . '/approve'];
                                $actions[] = ['type' => 'form', 'icon' => 'x', 'label' => 'Reject',
                                    'variant' => 'warning', 'action' => '/admin/attendance/corrections/' . $cid . '/reject'];
                            }
                            if (can('attendance.correct')) {
                                $actions[] = ['type' => 'form', 'icon' => 'trash-2', 'label' => 'Delete',
                                    'variant' => 'danger', 'action' => '/admin/attendance/corrections/' . $cid . '/delete',
                                    'confirm' => 'Delete this correction request?'];
                            }
                            include config('app.paths.views') . '/partials/table-actions.php';
                            ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php if (($records['last_page'] ?? 1) > 1): ?>
        <div class="card-footer py-2"><?= paginate_links($records, '/admin/attendance/corrections?' . http_build_query(array_filter($filters))) ?></div>
        <?php endif; ?>
    </div>
</div>
