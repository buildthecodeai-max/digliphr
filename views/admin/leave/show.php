<?php
$lr = $leaveRequest;
$isArchived = $isArchived ?? !empty($lr['deleted_at']);
?>
<?php include config('app.paths.views') . '/partials/alerts.php'; ?>
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header py-2 fw-semibold">Leave Details</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6"><div class="text-muted small">Employee</div><div><?= e($lr['employee_name']) ?> (<?= e($lr['employee_code']) ?>)</div></div>
                    <div class="col-md-6"><div class="text-muted small">Department</div><div><?= e($lr['department_name'] ?? '—') ?></div></div>
                    <div class="col-md-6"><div class="text-muted small">Leave Type</div><div><?= e($lr['leave_type_name']) ?></div></div>
                    <div class="col-md-6"><div class="text-muted small">Status</div><div><?= status_badge($lr['status']) ?></div></div>
                    <div class="col-md-6"><div class="text-muted small">Period</div><div><?= e(format_date($lr['start_date'])) ?> → <?= e(format_date($lr['end_date'])) ?><?= !empty($lr['is_half_day']) ? ' (Half day)' : '' ?></div></div>
                    <div class="col-md-6"><div class="text-muted small">Chargeable Days</div><div><?= e((string)$lr['chargeable_days']) ?> <span class="text-muted small">(cal <?= e((string)$lr['calendar_days']) ?>, WE <?= e((string)$lr['weekend_days']) ?>, Hol <?= e((string)$lr['holiday_days']) ?>)</span></div></div>
                    <div class="col-12"><div class="text-muted small">Reason</div><div><?= nl2br(e($lr['reason'])) ?></div></div>
                    <?php if (!empty($lr['handover_name'])): ?>
                        <div class="col-md-6"><div class="text-muted small">Handover</div><div><?= e($lr['handover_name']) ?></div></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (!empty($approvals)): ?>
        <div class="card mt-3">
            <div class="card-header py-2 fw-semibold">Approval History</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Level</th><th>Approver</th><th>Status</th><th>Comments</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach ($approvals as $a): ?>
                        <tr>
                            <td><?= (int)$a['level'] ?></td>
                            <td><?= e($a['approver_name'] ?? '—') ?></td>
                            <td><?= status_badge($a['status']) ?></td>
                            <td><?= e($a['comments'] ?? '—') ?></td>
                            <td><?= e(format_datetime($a['actioned_at'] ?? $a['created_at'] ?? null)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($extensions)): ?>
        <div class="card mt-3">
            <div class="card-header py-2 fw-semibold">Extensions</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Original End</th><th>New End</th><th>Days</th><th>Reason</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($extensions as $ex): ?>
                        <tr>
                            <td><?= e(format_date($ex['original_end_date'] ?? null)) ?></td>
                            <td><?= e(format_date($ex['new_end_date'] ?? null)) ?></td>
                            <td><?= e((string)($ex['additional_days'] ?? '')) ?></td>
                            <td><?= e($ex['reason'] ?? '') ?></td>
                            <td><?= status_badge($ex['status'] ?? 'approved') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <?php if (!$isArchived && ($lr['status'] ?? '') === 'pending' && can('leave.approve')): ?>
        <div class="card mb-3">
            <div class="card-header py-2 fw-semibold">Actions</div>
            <div class="card-body">
                <form method="POST" action="/admin/leave/<?= (int)$lr['id'] ?>/approve" class="mb-3">
                    <?= csrf_field() ?>
                    <label class="form-label small" for="comments-2">Approval comments</label>
                    <textarea name="comments" class="form-control form-control-sm mb-2" rows="2" id="comments-2"></textarea>
                    <button class="btn btn-success btn-sm w-100">Approve</button>
                </form>
                <form method="POST" action="/admin/leave/<?= (int)$lr['id'] ?>/reject">
                    <?= csrf_field() ?>
                    <label class="form-label small" for="comments">Rejection reason</label>
                    <textarea name="comments" class="form-control form-control-sm mb-2" rows="2" required id="comments"></textarea>
                    <button class="btn btn-outline-danger btn-sm w-100">Reject</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!$isArchived && can('leave.edit') && in_array($lr['status'] ?? '', ['draft','pending','approved'], true)): ?>
        <a href="/admin/leave/<?= (int)$lr['id'] ?>/edit" class="btn btn-warning btn-sm w-100 mb-2">Edit / Amend</a>
        <?php endif; ?>

        <?php if (!$isArchived && can('leave.cancel') && in_array($lr['status'] ?? '', ['pending','approved'], true)): ?>
        <div class="card mb-3" id="cancel">
            <div class="card-header py-2 fw-semibold">Cancel Leave</div>
            <div class="card-body">
                <form method="POST" action="/admin/leave/<?= (int)$lr['id'] ?>/cancel" data-confirm="Cancel this leave request?">
                    <?= csrf_field() ?>
                    <textarea name="reason" class="form-control form-control-sm mb-2" rows="2" placeholder="Cancellation reason" required></textarea>
                    <button class="btn btn-outline-danger btn-sm w-100">Cancel Leave</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!$isArchived && ($lr['status'] ?? '') === 'approved' && can('leave.extend')): ?>
        <div class="card mb-3">
            <div class="card-header py-2 fw-semibold">Extend Leave</div>
            <div class="card-body">
                <form method="POST" action="/admin/leave/<?= (int)$lr['id'] ?>/extend">
                    <?= csrf_field() ?>
                    <div class="mb-2">
                        <label class="form-label small" for="input-field">Current end</label>
                        <input type="text" class="form-control form-control-sm" value="<?= e($lr['end_date']) ?>" disabled id="input-field">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small" for="new_end_date">New end date</label>
                        <input type="date" name="new_end_date" class="form-control form-control-sm" min="<?= e($lr['end_date']) ?>" required id="new_end_date">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small" for="reason">Reason</label>
                        <textarea name="reason" class="form-control form-control-sm" rows="2" required id="reason"></textarea>
                    </div>
                    <button class="btn btn-primary btn-sm w-100">Extend Leave</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!$isArchived && can('leave.archive')): ?>
        <div class="card mb-3" id="archive">
            <div class="card-header py-2 fw-semibold">Archive</div>
            <div class="card-body">
                <form method="POST" action="/admin/leave/<?= (int)$lr['id'] ?>/archive" data-confirm="Archive this leave request?">
                    <?= csrf_field() ?>
                    <textarea name="reason" class="form-control form-control-sm mb-2" rows="2" placeholder="Archive reason" required></textarea>
                    <button class="btn btn-danger btn-sm w-100">Archive Leave</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($isArchived): ?>
        <div class="card mb-3">
            <div class="card-header py-2 fw-semibold">Archived Actions</div>
            <div class="card-body">
                <?php if (can('leave.restore')): ?>
                <form method="POST" action="/admin/leave/<?= (int)$lr['id'] ?>/restore" class="mb-2" data-confirm="Restore this leave?">
                    <?= csrf_field() ?>
                    <button class="btn btn-success btn-sm w-100">Restore</button>
                </form>
                <?php endif; ?>
                <?php if (can('leave.permanently_delete')): ?>
                <form method="POST" action="/admin/leave/<?= (int)$lr['id'] ?>/force-delete" data-confirm="Permanently delete this leave?">
                    <?= csrf_field() ?>
                    <textarea name="reason" class="form-control form-control-sm mb-2" required placeholder="Permanent deletion reason"></textarea>
                    <button class="btn btn-outline-danger btn-sm w-100">Permanently Delete</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($amendments)): ?>
        <div class="card mb-3" id="history">
            <div class="card-header py-2 fw-semibold">Amendment History</div>
            <div class="list-group list-group-flush">
                <?php foreach ($amendments as $am): ?>
                <div class="list-group-item py-2">
                    <div class="small fw-semibold"><?= e($am['reason'] ?? 'Amendment') ?></div>
                    <div class="text-muted" style="font-size:.75rem">
                        <?= e(format_datetime($am['created_at'] ?? null)) ?>
                        <?= !empty($am['changed_by_name']) ? ' · ' . e($am['changed_by_name']) : '' ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <a href="<?= !empty($isArchived) ? '/admin/leave/archived' : '/admin/leave' ?>" class="btn btn-light btn-sm w-100 mt-1">Back to list</a>
    </div>
</div>
