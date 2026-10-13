<?php
/** @var array $requests */
/** @var array $paginator */
/** @var array $filters */
/** @var array $leaveTypes */
$query = http_build_query(array_filter($filters ?? [], static fn ($v) => $v !== null && $v !== '' && $v !== 1));
$forceDeleteModals = [];
?>
<div class="page-header d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h1>Archived Leave</h1>
        <p class="subtitle">Browse archived leave requests</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/admin/leave" class="btn btn-soft btn-sm">Active Leave</a>
    </div>
</div>
<?php include config('app.paths.views') . '/partials/alerts.php'; ?>

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" action="/admin/leave/archived" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small mb-0" for="leave_type_id">Leave Type</label>
                <select name="leave_type_id" class="form-select form-select-sm" id="leave_type_id">
                    <option value="">All</option>
                    <?php foreach ($leaveTypes as $lt): ?>
                        <option value="<?= (int)$lt['id'] ?>" <?= (int)($filters['leave_type_id'] ?? 0) === (int)$lt['id'] ? 'selected' : '' ?>><?= e($lt['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-0" for="from_date">From</label>
                <input type="date" name="from_date" class="form-control form-control-sm" value="<?= e($filters['from_date'] ?? '') ?>" id="from_date">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-0" for="to_date">To</label>
                <input type="date" name="to_date" class="form-control form-control-sm" value="<?= e($filters['to_date'] ?? '') ?>" id="to_date">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-0" for="q">Search</label>
                <input type="text" name="q" class="form-control form-control-sm" value="<?= e($filters['q'] ?? '') ?>" placeholder="Employee, code, reason…" id="q">
            </div>
            <div class="col-md-2"><button class="btn btn-secondary btn-sm w-100">Filter</button></div>
        </form>
    </div>
</div>

<?php if (can('leave.restore') || can('leave.permanently_delete')): ?>
<form method="POST" action="" id="bulkArchivedForm">
    <?= csrf_field() ?>
    <input type="hidden" name="reason" id="bulkDeleteReasonHidden">

    <div id="bulkArchivedBar" class="card mb-2 border-warning" style="display:none">
        <div class="card-body py-2 d-flex align-items-center gap-2 flex-wrap">
            <span class="fw-semibold small"><span id="bulkArchivedCount">0</span> selected</span>
            <?php if (can('leave.restore')): ?>
            <button type="button" class="btn btn-success btn-sm" id="bulkRestoreBtn">
                <i data-lucide="rotate-ccw" style="width:13px;height:13px" class="me-1"></i>Restore Selected
            </button>
            <?php endif; ?>
            <?php if (can('leave.permanently_delete')): ?>
            <input type="text" id="bulkDeleteReasonInput" class="form-control form-control-sm" style="max-width:220px" placeholder="Delete reason (required)">
            <button type="button" class="btn btn-danger btn-sm" id="bulkDeleteBtn">
                <i data-lucide="trash-2" style="width:13px;height:13px" class="me-1"></i>Delete Selected
            </button>
            <?php endif; ?>
            <button type="button" class="btn btn-soft btn-sm ms-auto" id="bulkArchivedClear">Clear selection</button>
        </div>
    </div>
<?php endif; ?>

<div class="card">
    <div class="table-responsive table-actions-visible">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
            <tr>
                <?php if (can('leave.restore') || can('leave.permanently_delete')): ?>
                <th style="width:32px"><input type="checkbox" class="form-check-input" id="selectAllRestore" title="Select all"></th>
                <?php endif; ?>
                <th>#</th>
                <th>Employee</th>
                <th>Type</th>
                <th>Dates</th>
                <th>Days</th>
                <th>Status</th>
                <th>Archived by</th>
                <th>Archived</th>
                <th>Reason</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($requests)): ?>
                <tr><td colspan="<?= (can('leave.restore') || can('leave.permanently_delete')) ? 11 : 10 ?>" class="text-center text-muted py-4">No archived leave records found.</td></tr>
            <?php else:
                foreach ($requests as $row):
                    $rid = (int) $row['id'];
                    $deleteModalId = 'forceDeleteLeaveModal' . $rid;
                    $deleteLabel = trim(($row['employee_name'] ?? '') . ' · ' . ($row['leave_type_name'] ?? '') . ' · ' . ($row['start_date'] ?? ''));
            ?>
                <tr>
                    <?php if (can('leave.restore') || can('leave.permanently_delete')): ?>
                    <td><input type="checkbox" class="form-check-input restore-check" name="ids[]" value="<?= $rid ?>"></td>
                    <?php endif; ?>
                    <td><?= $rid ?></td>
                    <td>
                        <div class="fw-semibold"><?= e($row['employee_name'] ?? '') ?></div>
                        <div class="text-muted small"><?= e($row['employee_code'] ?? '') ?></div>
                    </td>
                    <td><?= e($row['leave_type_name'] ?? '') ?></td>
                    <td><?= e(format_date($row['start_date'] ?? null)) ?> → <?= e(format_date($row['end_date'] ?? null)) ?></td>
                    <td><?= e((string) ($row['chargeable_days'] ?? '')) ?></td>
                    <td><?= status_badge($row['status'] ?? '') ?> <span class="badge text-bg-secondary">Archived</span></td>
                    <td><?= e($row['archived_by_name'] ?? '—') ?></td>
                    <td><?= e(format_datetime($row['deleted_at'] ?? null)) ?></td>
                    <td class="small"><?= e($row['deletion_reason'] ?? '—') ?></td>
                    <td class="text-end text-nowrap">
                        <?php
                        $actions = [
                            ['type' => 'link', 'icon' => 'eye', 'label' => 'View', 'href' => '/admin/leave/' . $rid],
                        ];
                        if (can('leave.view_history')) {
                            $actions[] = ['type' => 'link', 'icon' => 'history', 'label' => 'History', 'href' => '/admin/leave/' . $rid . '#history'];
                        }
                        if (can('leave.restore')) {
                            $actions[] = [
                                'type' => 'form',
                                'icon' => 'rotate-ccw',
                                'label' => 'Restore',
                                'variant' => 'success',
                                'action' => '/admin/leave/' . $rid . '/restore',
                                'confirm' => 'Restore this leave request to the active list?',
                            ];
                        }
                        if (can('leave.permanently_delete')) {
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
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (!empty($paginator)): ?>
        <div class="card-footer py-2"><?= paginate_links($paginator, '/admin/leave/archived?' . $query) ?></div>
    <?php endif; ?>
</div>

<?php if (can('leave.restore') || can('leave.permanently_delete')): ?>
</form>
<script>
(function () {
    var form       = document.getElementById('bulkArchivedForm');
    var bar        = document.getElementById('bulkArchivedBar');
    var counter    = document.getElementById('bulkArchivedCount');
    var selectAll  = document.getElementById('selectAllRestore');
    var restoreBtn = document.getElementById('bulkRestoreBtn');
    var deleteBtn  = document.getElementById('bulkDeleteBtn');
    var clearBtn   = document.getElementById('bulkArchivedClear');
    var reasonInp  = document.getElementById('bulkDeleteReasonInput');

    function getChecked() {
        return Array.from(document.querySelectorAll('.restore-check:checked'));
    }

    function update() {
        var checked = getChecked();
        counter.textContent = checked.length;
        bar.style.display = checked.length > 0 ? '' : 'none';
        if (selectAll) {
            var all = document.querySelectorAll('.restore-check');
            selectAll.indeterminate = checked.length > 0 && checked.length < all.length;
            selectAll.checked = all.length > 0 && checked.length === all.length;
        }
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    document.querySelectorAll('.restore-check').forEach(function (cb) {
        cb.addEventListener('change', update);
    });

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.restore-check').forEach(function (cb) { cb.checked = selectAll.checked; });
            update();
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            document.querySelectorAll('.restore-check').forEach(function (cb) { cb.checked = false; });
            if (selectAll) selectAll.checked = false;
            update();
        });
    }

    if (restoreBtn) {
        restoreBtn.addEventListener('click', function () {
            if (!confirm('Restore ' + getChecked().length + ' leave request(s) to active?')) return;
            form.action = '/admin/leave/bulk-restore';
            form.submit();
        });
    }

    if (deleteBtn) {
        deleteBtn.addEventListener('click', function () {
            var reason = reasonInp ? reasonInp.value.trim() : '';
            if (!reason) { if (reasonInp) { reasonInp.focus(); reasonInp.classList.add('is-invalid'); } return; }
            if (reasonInp) reasonInp.classList.remove('is-invalid');
            if (!confirm('Permanently delete ' + getChecked().length + ' leave request(s)? This cannot be undone.')) return;
            document.getElementById('bulkDeleteReasonHidden').value = reason;
            form.action = '/admin/leave/bulk-force-delete';
            form.submit();
        });
    }
})();
</script>
<?php endif; ?>

<?php if (!empty($forceDeleteModals)): ?>
<?php foreach ($forceDeleteModals as $modal): ?>
<div class="modal fade" id="<?= e($modal['modal_id']) ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" action="/admin/leave/<?= (int) $modal['id'] ?>/force-delete" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="leave_id" value="<?= (int) $modal['id'] ?>">
            <div class="modal-header">
                <h5 class="modal-title">Permanently delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2">This cannot be undone. Related approvals, extensions, and attachments will also be removed.</p>
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
