<?php
/** @var array $records */
/** @var array $filters */
/** @var array $departments */
/** @var array $branches */
/** @var array $archivists */

$query = http_build_query(array_filter($filters, static fn ($v, $k) => $k !== 'archived' && $v !== null && $v !== '', ARRAY_FILTER_USE_BOTH));
$forceDeleteModals = [];
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h1 class="h4 mb-0">Archived Attendance</h1>
            <p class="text-muted small mb-0">Soft-deleted attendance records retained for audit</p>
        </div>
        <div class="d-flex gap-2">
            <a href="/admin/attendance" class="btn btn-outline-secondary btn-sm">Active Attendance</a>
            <?php if (can('attendance.export')): ?>
            <a href="/admin/attendance/export?archived=1&amp;<?= e($query) ?>" class="btn btn-outline-secondary btn-sm">Export CSV</a>
            <?php endif; ?>
        </div>
    </div>

    <?php include config('app.paths.views') . '/partials/alerts.php'; ?>

    <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
            <form method="get" action="/admin/attendance/archived" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small mb-0" for="date_from">From</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($filters['date_from'] ?? '') ?>" id="date_from">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0" for="date_to">To</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($filters['date_to'] ?? '') ?>" id="date_to">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0" for="department_id">Department</label>
                    <select name="department_id" class="form-select form-select-sm" id="department_id">
                        <option value="">All</option>
                        <?php foreach ($departments as $d): ?>
                        <option value="<?= (int) $d['id'] ?>" <?= (($filters['department_id'] ?? '') == $d['id']) ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0" for="branch_id">Branch</label>
                    <select name="branch_id" class="form-select form-select-sm" id="branch_id">
                        <option value="">All</option>
                        <?php foreach ($branches as $b): ?>
                        <option value="<?= (int) $b['id'] ?>" <?= (($filters['branch_id'] ?? '') == $b['id']) ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0" for="status">Status</label>
                    <select name="status" class="form-select form-select-sm" id="status">
                        <option value="">All</option>
                        <?php foreach (['present','late','absent','remote','half_day','on_leave','manual'] as $st): ?>
                        <option value="<?= e($st) ?>" <?= (($filters['status'] ?? '') === $st) ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $st))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0" for="archived_by">Archived by</label>
                    <select name="archived_by" class="form-select form-select-sm" id="archived_by">
                        <option value="">All</option>
                        <?php foreach ($archivists as $u): ?>
                        <option value="<?= (int) $u['id'] ?>" <?= (($filters['archived_by'] ?? '') == $u['id']) ? 'selected' : '' ?>><?= e($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0" for="q">Search</label>
                    <input type="text" name="q" class="form-control form-control-sm" value="<?= e($filters['q'] ?? '') ?>" placeholder="Employee, code, reason…" id="q">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-secondary btn-sm w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive table-actions-visible">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Employee</th>
                        <th>Code</th>
                        <th>Company</th>
                        <th>Branch</th>
                        <th>Department</th>
                        <th>Date</th>
                        <th>Check-in</th>
                        <th>Checkout</th>
                        <th>Status</th>
                        <th>Hours</th>
                        <th>Archived by</th>
                        <th>Archived date</th>
                        <th>Reason</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($records['data'])): ?>
                    <tr><td colspan="14" class="text-center text-muted py-4">No archived attendance records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($records['data'] as $row):
                        $rid = (int) $row['id'];
                        $deleteModalId = 'forceDeleteAttendanceModal' . $rid;
                        $deleteLabel = trim(($row['employee_name'] ?? '') . ' · ' . ($row['attendance_date'] ?? ''));
                    ?>
                    <tr>
                        <td class="fw-semibold"><?= e($row['employee_name'] ?? '') ?></td>
                        <td><?= e($row['employee_code'] ?? '') ?></td>
                        <td><?= e($row['company_name'] ?? '—') ?></td>
                        <td><?= e($row['branch_name'] ?? '—') ?></td>
                        <td><?= e($row['department_name'] ?? '—') ?></td>
                        <td><?= e(format_date($row['attendance_date'] ?? null)) ?></td>
                        <td><?= e(format_datetime($row['check_in_at'] ?? null, config('app.time_format', 'g:i A'))) ?></td>
                        <td><?= e(format_datetime($row['check_out_at'] ?? null, config('app.time_format', 'g:i A'))) ?></td>
                        <td><?= status_badge($row['status'] ?? '') ?> <span class="badge text-bg-secondary">Archived</span></td>
                        <td><?= e(format_minutes((int) ($row['work_minutes'] ?? 0))) ?></td>
                        <td><?= e($row['archived_by_name'] ?? '—') ?></td>
                        <td><?= e(format_datetime($row['deleted_at'] ?? null)) ?></td>
                        <td class="small"><?= e($row['deletion_reason'] ?? '—') ?></td>
                        <td class="text-end text-nowrap">
                            <?php
                            $actions = [
                                ['type' => 'link', 'icon' => 'eye', 'label' => 'View', 'href' => '/admin/attendance/' . $rid],
                            ];
                            if (can('attendance.view_history')) {
                                $actions[] = ['type' => 'link', 'icon' => 'history', 'label' => 'History', 'href' => '/admin/attendance/' . $rid . '#audit'];
                            }
                            if (can('attendance.restore')) {
                                $actions[] = [
                                    'type' => 'form',
                                    'icon' => 'rotate-ccw',
                                    'label' => 'Restore',
                                    'variant' => 'success',
                                    'action' => '/admin/attendance/' . $rid . '/restore',
                                    'confirm' => 'Restore this attendance record to the active list?',
                                ];
                            }
                            if (can('attendance.permanently_delete')) {
                                $actions[] = [
                                    'type' => 'modal',
                                    'icon' => 'trash-2',
                                    'label' => 'Permanently delete',
                                    'variant' => 'danger',
                                    'target' => '#' . $deleteModalId,
                                ];
                                $forceDeleteModals[] = [
                                    'id' => $rid,
                                    'modal_id' => $deleteModalId,
                                    'label' => $deleteLabel,
                                ];
                            }
                            include config('app.paths.views') . '/partials/table-actions.php';
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if (($records['last_page'] ?? 1) > 1): ?>
        <div class="card-footer bg-white py-2">
            <?= paginate_links($records, '/admin/attendance/archived?' . $query) ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($forceDeleteModals)): ?>
<?php foreach ($forceDeleteModals as $modal): ?>
<div class="modal fade" id="<?= e($modal['modal_id']) ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" action="/admin/attendance/<?= (int) $modal['id'] ?>/force-delete" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="attendance_id" value="<?= (int) $modal['id'] ?>">
            <div class="modal-header">
                <h5 class="modal-title">Permanently delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2">This cannot be undone. Evidence linked to this record will also be removed.</p>
                <p class="small mb-3"><strong><?= e($modal['label']) ?></strong></p>
                <label class="form-label" for="reason">Reason <span class="text-danger">*</span></label>
                <textarea name="reason" class="form-control" rows="3" required minlength="3" placeholder="Permanent deletion reason" id="reason"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger btn-sm">Permanently delete</button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>
