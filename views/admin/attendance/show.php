<?php
/** @var array $record */
/** @var array $images */
/** @var array $locations */
/** @var array $corrections */
/** @var array $auditLogs */
/** @var bool $correctMode */

$correctMode = $correctMode ?? false;
$id = (int) $record['id'];
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h1 class="h4 mb-1">Attendance Details</h1>
            <p class="text-muted small mb-0">
                <?= e($record['employee_name'] ?? '') ?> · <?= e(format_date($record['attendance_date'])) ?>
            </p>
        </div>
        <a href="/admin/attendance" class="btn btn-outline-secondary btn-sm">&larr; Back</a>
    </div>

    <?php include config('app.paths.views') . '/partials/alerts.php'; ?>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                    <strong>Summary</strong>
                    <div class="d-flex gap-1">
                        <?= status_badge($record['status']) ?>
                        <?= status_badge($record['verification_status']) ?>
                        <?php if (!empty($record['is_remote'])): ?><span class="badge text-bg-info">Remote</span><?php endif; ?>
                        <?php if (!empty($record['is_manual'])): ?><span class="badge text-bg-dark">Manual</span><?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3"><div class="small text-muted">Check In</div><div class="fw-semibold"><?= e(format_datetime($record['check_in_at'] ?? null)) ?></div></div>
                        <div class="col-md-3"><div class="small text-muted">Check Out</div><div class="fw-semibold"><?= e(format_datetime($record['check_out_at'] ?? null)) ?></div></div>
                        <div class="col-md-2"><div class="small text-muted">Work</div><div class="fw-semibold"><?= e(format_minutes((int) ($record['work_minutes'] ?? 0))) ?></div></div>
                        <div class="col-md-2"><div class="small text-muted">Late</div><div class="fw-semibold"><?= e(format_minutes((int) ($record['late_minutes'] ?? 0))) ?></div></div>
                        <div class="col-md-2"><div class="small text-muted">Overtime</div><div class="fw-semibold"><?= e(format_minutes((int) ($record['overtime_minutes'] ?? 0))) ?></div></div>
                        <div class="col-md-4"><div class="small text-muted">Department</div><div><?= e($record['department_name'] ?? '—') ?></div></div>
                        <div class="col-md-4"><div class="small text-muted">Branch</div><div><?= e($record['branch_name'] ?? '—') ?></div></div>
                        <div class="col-md-4"><div class="small text-muted">Shift</div><div><?= e($record['shift_name'] ?? '—') ?></div></div>
                        <?php if (!empty($record['remarks'])): ?>
                        <div class="col-12"><div class="small text-muted">Remarks</div><div><?= e($record['remarks']) ?></div></div>
                        <?php endif; ?>
                        <?php if (!empty($record['admin_notes'])): ?>
                        <div class="col-12"><div class="small text-muted">Admin Notes</div><div><?= e($record['admin_notes']) ?></div></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white py-2"><strong>Captured Images</strong></div>
                <div class="card-body">
                    <?php if (empty($images)): ?>
                        <p class="text-muted mb-0">No images captured.</p>
                    <?php else: ?>
                        <div class="row g-2">
                            <?php foreach ($images as $img): ?>
                            <div class="col-md-4">
                                <div class="border rounded p-2 h-100">
                                    <div class="small text-muted mb-1"><?= e(ucwords(str_replace('_', ' ', $img['type']))) ?></div>
                                    <?php if (can('attendance.images')): ?>
                                    <a href="/admin/attendance/images/<?= (int) $img['id'] ?>" target="_blank" rel="noopener">
                                        <img src="/admin/attendance/images/<?= (int) $img['id'] ?>" alt="<?= e($img['type']) ?>" class="img-fluid rounded">
                                    </a>
                                    <?php else: ?>
                                    <span class="text-muted small">No permission to view</span>
                                    <?php endif; ?>
                                    <div class="small text-muted mt-1"><?= e(format_datetime($img['captured_at'] ?? null)) ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white py-2"><strong>Locations</strong></div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Type</th>
                                <th>Coordinates</th>
                                <th>Accuracy</th>
                                <th>Distance</th>
                                <th>Within Radius</th>
                                <th>Map</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($locations)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-3">No location data.</td></tr>
                        <?php else: ?>
                            <?php foreach ($locations as $loc): ?>
                            <tr>
                                <td><?= e(ucwords(str_replace('_', ' ', $loc['type']))) ?></td>
                                <td><?= e($loc['latitude']) ?>, <?= e($loc['longitude']) ?></td>
                                <td><?= e($loc['accuracy'] ?? '—') ?> m</td>
                                <td>
                                    <?php if ($loc['distance_meters'] !== null): ?>
                                        <?= e(number_format((float) $loc['distance_meters'], 1)) ?> m
                                        <?php if (!empty($loc['distance_computed'])): ?>
                                            <span class="text-muted small" title="Computed from current branch coordinates">*</span>
                                        <?php endif; ?>
                                    <?php elseif ($loc['branch_latitude'] === null): ?>
                                        <span class="text-muted small" title="Set GPS coordinates on the branch to enable distance tracking">Branch GPS not set</span>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($loc['is_within_radius'] === null && $loc['branch_latitude'] === null): ?>
                                        <span class="text-muted small">Branch GPS not set</span>
                                    <?php elseif ($loc['is_within_radius'] === null): ?>—
                                    <?php elseif ($loc['is_within_radius']): ?><span class="badge text-bg-success">Yes</span>
                                    <?php else: ?><span class="badge text-bg-danger">No</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="https://maps.google.com/?q=<?= e($loc['latitude']) ?>,<?= e($loc['longitude']) ?>" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">Open</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <?php $isArchived = $isArchived ?? !empty($record['deleted_at']); ?>
            <?php if (!$isArchived && ($correctMode || can('attendance.correct') || can('attendance.edit') || can('attendance.archive') || can('attendance.approve') || can('attendance.reject'))): ?>
            <div class="card shadow-sm mb-3" id="edit">
                <div class="card-header bg-white py-2"><strong><?= $correctMode ? 'Correct Attendance' : 'Edit Attendance' ?></strong></div>
                <div class="card-body">
                    <?php if (can('attendance.correct') || can('attendance.edit')): ?>
                    <form method="post" action="/admin/attendance/<?= $id ?>/<?= can('attendance.edit') ? 'update' : 'correct' ?>" class="mb-3">
                        <?= csrf_field() ?>
                        <div class="mb-2">
                            <label class="form-label small" for="attendance_date">Attendance Date</label>
                            <input type="date" name="attendance_date" class="form-control form-control-sm"
                                   value="<?= e($record['attendance_date'] ?? '') ?>" required id="attendance_date">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small" for="shift_id">Shift</label>
                            <select name="shift_id" class="form-select form-select-sm" id="shift_id">
                                <option value="">—</option>
                                <?php foreach ($shifts ?? [] as $shift): ?>
                                <option value="<?= (int) $shift['id'] ?>" <?= (int) ($record['shift_id'] ?? 0) === (int) $shift['id'] ? 'selected' : '' ?>><?= e($shift['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small" for="branch_id">Branch</label>
                            <select name="branch_id" class="form-select form-select-sm" id="branch_id">
                                <option value="">—</option>
                                <?php foreach ($branches ?? [] as $branch): ?>
                                <option value="<?= (int) $branch['id'] ?>" <?= (int) ($record['branch_id'] ?? 0) === (int) $branch['id'] ? 'selected' : '' ?>><?= e($branch['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small" for="check_in_at">Check In</label>
                            <input type="datetime-local" name="check_in_at" class="form-control form-control-sm"
                                   value="<?= e($record['check_in_at'] ? date('Y-m-d\TH:i', strtotime($record['check_in_at'])) : '') ?>" id="check_in_at">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small" for="check_out_at">Check Out</label>
                            <input type="datetime-local" name="check_out_at" class="form-control form-control-sm"
                                   value="<?= e($record['check_out_at'] ? date('Y-m-d\TH:i', strtotime($record['check_out_at'])) : '') ?>" id="check_out_at">
                        </div>
                        <div class="row g-2">
                            <div class="col-6 mb-2">
                                <label class="form-label small" for="status">Status</label>
                                <select name="status" class="form-select form-select-sm" id="status">
                                    <?php foreach (['present','absent','late','remote','half_day','manual','on_leave','missing_checkout'] as $st): ?>
                                    <option value="<?= e($st) ?>" <?= ($record['status'] ?? '') === $st ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $st))) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6 mb-2">
                                <label class="form-label small" for="verification_status">Verification</label>
                                <select name="verification_status" class="form-select form-select-sm" id="verification_status">
                                    <?php foreach (['pending','pending_review','verified','rejected','outside_radius','low_gps_accuracy'] as $st): ?>
                                    <option value="<?= e($st) ?>" <?= ($record['verification_status'] ?? '') === $st ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $st))) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-6 mb-2">
                                <label class="form-label small" for="late_minutes">Late (min)</label>
                                <input type="number" min="0" name="late_minutes" class="form-control form-control-sm" value="<?= (int) ($record['late_minutes'] ?? 0) ?>" id="late_minutes">
                            </div>
                            <div class="col-6 mb-2">
                                <label class="form-label small" for="early_leave_minutes">Early leave (min)</label>
                                <input type="number" min="0" name="early_leave_minutes" class="form-control form-control-sm" value="<?= (int) ($record['early_leave_minutes'] ?? 0) ?>" id="early_leave_minutes">
                            </div>
                            <div class="col-6 mb-2">
                                <label class="form-label small" for="break_minutes">Break (min)</label>
                                <input type="number" min="0" name="break_minutes" class="form-control form-control-sm" value="<?= (int) ($record['break_minutes'] ?? 0) ?>" id="break_minutes">
                            </div>
                            <div class="col-6 mb-2">
                                <label class="form-label small" for="overtime_minutes">Overtime (min)</label>
                                <input type="number" min="0" name="overtime_minutes" class="form-control form-control-sm" value="<?= (int) ($record['overtime_minutes'] ?? 0) ?>" id="overtime_minutes">
                            </div>
                            <div class="col-6 mb-2">
                                <label class="form-label small" for="work_minutes">Work (min)</label>
                                <input type="number" min="0" name="work_minutes" class="form-control form-control-sm" value="<?= (int) ($record['work_minutes'] ?? 0) ?>" id="work_minutes">
                            </div>
                            <div class="col-6 mb-2 d-flex align-items-end">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_remote" value="1" id="edit-remote" <?= !empty($record['is_remote']) ? 'checked' : '' ?>>
                                    <label class="form-check-label small" for="edit-remote">Remote</label>
                                </div>
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small" for="reason">Reason <span class="text-danger">*</span></label>
                            <input type="text" name="reason" class="form-control form-control-sm" value="Admin update" required id="reason">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small" for="admin_notes">Admin Notes</label>
                            <textarea name="admin_notes" class="form-control form-control-sm" rows="2" id="admin_notes"><?= e($record['admin_notes'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small" for="remarks">Remarks</label>
                            <input type="text" name="remarks" class="form-control form-control-sm" value="<?= e($record['remarks'] ?? '') ?>" id="remarks">
                        </div>
                        <button type="submit" class="btn btn-warning btn-sm w-100">Save Changes</button>
                    </form>
                    <?php endif; ?>

                    <?php if (can('attendance.approve') && ($record['verification_status'] ?? '') !== 'verified'): ?>
                    <form method="post" action="/admin/attendance/<?= $id ?>/approve" class="mb-2">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-success btn-sm w-100">Approve</button>
                    </form>
                    <?php endif; ?>

                    <?php if (can('attendance.reject') && ($record['verification_status'] ?? '') !== 'rejected'): ?>
                    <form method="post" action="/admin/attendance/<?= $id ?>/reject" class="mb-2">
                        <?= csrf_field() ?>
                        <input type="text" name="admin_notes" class="form-control form-control-sm mb-2" placeholder="Rejection reason">
                        <button type="submit" class="btn btn-outline-danger btn-sm w-100">Reject</button>
                    </form>
                    <?php endif; ?>

                    <?php if (can('attendance.archive')): ?>
                    <form method="post" action="/admin/attendance/<?= $id ?>/archive" class="mb-0" id="archive" data-confirm="Archive this attendance record? Evidence is retained.">
                        <?= csrf_field() ?>
                        <input type="text" name="reason" class="form-control form-control-sm mb-2" placeholder="Archive reason (required)" required>
                        <button type="submit" class="btn btn-danger btn-sm w-100">Archive Attendance</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($isArchived && (can('attendance.restore') || can('attendance.permanently_delete'))): ?>
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white py-2"><strong>Archived Actions</strong></div>
                <div class="card-body">
                    <div class="alert alert-secondary small mb-2">This record is archived.</div>
                    <?php if (can('attendance.restore')): ?>
                    <form method="post" action="/admin/attendance/<?= $id ?>/restore" class="mb-2" data-confirm="Restore this attendance record?">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-success btn-sm w-100">Restore</button>
                    </form>
                    <?php endif; ?>
                    <?php if (can('attendance.permanently_delete')): ?>
                    <form method="post" action="/admin/attendance/<?= $id ?>/force-delete" data-confirm="Permanently delete this archived record? This cannot be undone.">
                        <?= csrf_field() ?>
                        <input type="text" name="reason" class="form-control form-control-sm mb-2" placeholder="Permanent deletion reason" required>
                        <button type="submit" class="btn btn-outline-danger btn-sm w-100">Permanently Delete</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white py-2"><strong>Correction Requests</strong></div>
                <div class="list-group list-group-flush">
                    <?php if (empty($corrections)): ?>
                        <div class="list-group-item text-muted small">None</div>
                    <?php else: ?>
                        <?php foreach ($corrections as $c): ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <?= status_badge($c['status']) ?>
                                <span class="small text-muted"><?= e(format_datetime($c['created_at'])) ?></span>
                            </div>
                            <div class="small mt-1"><?= e($c['reason']) ?></div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow-sm" id="audit">
                <div class="card-header bg-white py-2"><strong>Audit Trail</strong></div>
                <div class="list-group list-group-flush" style="max-height:320px;overflow:auto">
                    <?php if (empty($auditLogs)): ?>
                        <div class="list-group-item text-muted small">No audit entries.</div>
                    <?php else: ?>
                        <?php foreach ($auditLogs as $log): ?>
                        <div class="list-group-item py-2">
                            <div class="small fw-semibold"><?= e($log['action']) ?></div>
                            <div class="text-muted" style="font-size:.75rem">
                                <?= e(format_datetime($log['performed_at'])) ?>
                                <?= $log['performer_name'] ? ' · ' . e($log['performer_name']) : '' ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
