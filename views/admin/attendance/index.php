<?php
/** @var array $records */
/** @var array $stats */
/** @var array $filters */
/** @var array $thumbnails */
/** @var array $departments */
/** @var array $branches */
/** @var array $shifts */
/** @var bool $manualMode */
/** @var array $employees */

$manualMode = $manualMode ?? false;
$query = http_build_query(array_filter($filters));
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h1 class="h4 mb-0"><?= e($manualMode ? 'Manual Attendance Entry' : ($title ?? 'Attendance')) ?></h1>
        <div class="d-flex gap-2">
            <?php if (can('attendance.view_archived')): ?>
            <a href="/admin/attendance/archived" class="btn btn-outline-secondary btn-sm">Archived</a>
            <?php endif; ?>
            <?php if (can('attendance.correct')): ?>
            <a href="/admin/attendance/corrections" class="btn btn-outline-secondary btn-sm">Corrections</a>
            <?php endif; ?>
            <?php if (can('attendance.manual')): ?>
            <a href="/admin/attendance/manual" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-plus-circle"></i> Manual Entry
            </a>
            <?php endif; ?>
            <?php if (can('attendance.export')): ?>
            <a href="/admin/attendance/export?<?= e($query) ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-download"></i> Export CSV
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php include config('app.paths.views') . '/partials/alerts.php'; ?>
    <?php if(!$manualMode && !empty($filters['company_id'])): $savedModule='attendance';$currentFilters=$filters;include config('app.paths.views').'/partials/saved-filters.php';endif; ?>

    <?php if ($manualMode): ?>
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form method="post" action="/admin/attendance/manual" class="row g-3">
                <?= csrf_field() ?>
                <div class="col-md-4">
                    <label class="form-label small" for="employee_id">Employee</label>
                    <select name="employee_id" class="form-select form-select-sm" required id="employee_id">
                        <option value="">Select employee…</option>
                        <?php foreach ($employees ?? [] as $emp): ?>
                        <option value="<?= (int) $emp['id'] ?>">
                            <?= e($emp['employee_code'] . ' — ' . trim($emp['first_name'] . ' ' . $emp['last_name'])) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small" for="attendance_date">Date</label>
                    <input type="date" name="attendance_date" class="form-control form-control-sm" value="<?= e(date('Y-m-d')) ?>" required id="attendance_date">
                </div>
                <div class="col-md-2">
                    <label class="form-label small" for="check_in_at">Check In</label>
                    <input type="datetime-local" name="check_in_at" class="form-control form-control-sm" id="check_in_at">
                </div>
                <div class="col-md-2">
                    <label class="form-label small" for="check_out_at">Check Out</label>
                    <input type="datetime-local" name="check_out_at" class="form-control form-control-sm" id="check_out_at">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_remote" value="1" id="manual-remote">
                        <label class="form-check-label small" for="manual-remote">Remote</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label small" for="status-2">Status</label>
                    <select name="status" class="form-select form-select-sm" id="status-2">
                        <?php foreach (['manual', 'present', 'late', 'remote', 'half_day'] as $st): ?>
                        <option value="<?= e($st) ?>"><?= e(ucwords(str_replace('_', ' ', $st))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small" for="remarks">Remarks</label>
                    <input type="text" name="remarks" class="form-control form-control-sm" id="remarks">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-sm">Save Entry</button>
                </div>
            </form>
        </div>
    </div>
    <?php else: ?>

    <div class="row g-2 mb-3">
        <div class="col"><div class="card shadow-sm"><div class="card-body py-2 text-center"><div class="small text-muted">Present</div><div class="fw-bold text-success"><?= (int) ($stats['present_count'] ?? 0) ?></div></div></div></div>
        <div class="col"><div class="card shadow-sm"><div class="card-body py-2 text-center"><div class="small text-muted">Late</div><div class="fw-bold text-warning"><?= (int) ($stats['late_count'] ?? 0) ?></div></div></div></div>
        <div class="col"><div class="card shadow-sm"><div class="card-body py-2 text-center"><div class="small text-muted">Remote</div><div class="fw-bold text-info"><?= (int) ($stats['remote_count'] ?? 0) ?></div></div></div></div>
        <div class="col"><div class="card shadow-sm"><div class="card-body py-2 text-center"><div class="small text-muted">Flagged</div><div class="fw-bold text-danger"><?= (int) ($stats['flagged_count'] ?? 0) ?></div></div></div></div>
        <div class="col"><div class="card shadow-sm"><div class="card-body py-2 text-center"><div class="small text-muted">Work Hrs</div><div class="fw-bold"><?= e(format_minutes((int) ($stats['total_work_minutes'] ?? 0))) ?></div></div></div></div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
            <form method="get" action="/admin/attendance" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small mb-0" for="date_from">From</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($filters['date_from'] ?? date('Y-m-01')) ?>" id="date_from">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0" for="date_to">To</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($filters['date_to'] ?? date('Y-m-d')) ?>" id="date_to">
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
                        <?php foreach (['present','late','absent','remote','half_day','manual','missing_checkout'] as $st): ?>
                        <option value="<?= e($st) ?>" <?= (($filters['status'] ?? '') === $st) ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $st))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0" for="verification_status">Verification</label>
                    <select name="verification_status" class="form-select form-select-sm" id="verification_status">
                        <option value="">All</option>
                        <?php foreach (['pending','verified','flagged','rejected','auto_verified'] as $vs): ?>
                        <option value="<?= e($vs) ?>" <?= (($filters['verification_status'] ?? '') === $vs) ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $vs))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0" for="q">Search</label>
                    <input type="text" name="q" class="form-control form-control-sm" value="<?= e($filters['q'] ?? '') ?>" placeholder="Name or code" id="q">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:56px">Photo</th>
                        <th>Date</th>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Check In</th>
                        <th>Check Out</th>
                        <th>Status</th>
                        <th>Verification</th>
                        <th>Work</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($records['data'])): ?>
                    <tr><td colspan="10" class="text-center text-muted py-4">No attendance records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($records['data'] as $row):
                        $thumb = $thumbnails[(int) $row['id']] ?? null;
                        $imgUrl = $thumb ? '/admin/attendance/images/' . (int) $thumb['id'] : null;
                    ?>
                    <tr>
                        <td>
                            <?php if ($imgUrl && can('attendance.images')): ?>
                            <a href="<?= e($imgUrl) ?>" target="_blank" rel="noopener">
                                <img src="<?= e($imgUrl) ?>" alt="Check-in" class="rounded border" width="44" height="44" style="object-fit:cover">
                            </a>
                            <?php else: ?>
                            <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e(format_date($row['attendance_date'])) ?></td>
                        <td>
                            <div class="fw-semibold"><?= e($row['employee_name']) ?></div>
                            <div class="text-muted small"><?= e($row['employee_code']) ?></div>
                        </td>
                        <td><?= e($row['department_name'] ?? '—') ?></td>
                        <td><?= e(format_datetime($row['check_in_at'] ?? null, config('app.time_format', 'g:i A'))) ?></td>
                        <td><?= e(format_datetime($row['check_out_at'] ?? null, config('app.time_format', 'g:i A'))) ?></td>
                        <td><?= status_badge($row['status']) ?></td>
                        <td><?= status_badge($row['verification_status']) ?></td>
                        <td><?= e(format_minutes((int) ($row['work_minutes'] ?? 0))) ?></td>
                        <td class="text-end text-nowrap">
                            <?php
                            $aid = (int) $row['id'];
                            $actions = [
                                ['type' => 'link', 'icon' => 'eye', 'label' => 'View', 'href' => '/admin/attendance/' . $aid],
                            ];
                            if (can('attendance.edit')) {
                                $actions[] = ['type' => 'link', 'icon' => 'pencil', 'label' => 'Edit', 'variant' => 'warning', 'href' => '/admin/attendance/' . $aid . '#edit'];
                            }
                            if (can('attendance.view_history')) {
                                $actions[] = ['type' => 'link', 'icon' => 'history', 'label' => 'History', 'href' => '/admin/attendance/' . $aid . '#audit'];
                            }
                            if (can('attendance.archive')) {
                                $actions[] = ['type' => 'link', 'icon' => 'archive', 'label' => 'Archive', 'variant' => 'danger', 'href' => '/admin/attendance/' . $aid . '#archive'];
                            }
                            if (can('attendance.edit')) {
                                $actions[] = ['type' => 'form', 'icon' => 'trash-2', 'label' => 'Delete', 'variant' => 'danger',
                                    'action' => '/admin/attendance/' . $aid . '/delete',
                                    'confirm' => 'Delete this attendance record permanently?'];
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
            <?= paginate_links($records, '/admin/attendance?' . $query) ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
