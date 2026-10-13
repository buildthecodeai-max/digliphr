<div class="page-header d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h1><?= e($title ?? 'Leave Requests') ?></h1>
        <p class="subtitle">Review, approve and manage employee leave applications</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if (can('leave.view_archived')): ?>
        <a href="/admin/leave/archived" class="btn btn-soft btn-sm">Archived</a>
        <?php endif; ?>
        <?php if (can('leave.edit')): ?>
        <a href="/admin/leave/allocate" class="btn btn-soft btn-sm">
            <i data-lucide="calendar-check" class="me-1" style="width:14px;height:14px"></i>Allocate Leaves
        </a>
        <?php endif; ?>
        <?php if (can('leave.create')): ?>
        <a href="/admin/leave/create" class="btn btn-primary btn-sm">
            <i data-lucide="plus" class="me-1" style="width:14px;height:14px"></i>Record Leave
        </a>
        <?php endif; ?>
    </div>
</div>

<?php include config('app.paths.views') . '/partials/alerts.php'; ?>
<?php if(!empty($filters['company_id'])): $savedModule='leave';$currentFilters=$filters;include config('app.paths.views').'/partials/saved-filters.php';endif; ?>

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small mb-0" for="status">Status</label>
                <select name="status" class="form-select form-select-sm" id="status">
                    <option value="">All</option>
                    <?php foreach (['draft','pending','approved','rejected','cancelled'] as $s): ?>
                        <option value="<?= $s ?>" <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
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
            <div class="col-md-2"><button class="btn btn-secondary btn-sm w-100">Filter</button></div>
        </form>
    </div>
</div>

<?php if (can('leave.archive')): ?>
<form method="POST" action="/admin/leave/bulk-archive" id="bulkForm">
    <?= csrf_field() ?>
    <input type="hidden" name="reason" id="bulkReason">

    <div id="bulkBar" class="card mb-2 border-warning" style="display:none">
        <div class="card-body py-2 d-flex align-items-center gap-2 flex-wrap">
            <span class="fw-semibold small text-warning"><span id="bulkCount">0</span> selected</span>
            <input type="text" id="bulkReasonInput" class="form-control form-control-sm" style="max-width:220px" placeholder="Archive reason (required)">
            <button type="button" class="btn btn-danger btn-sm" id="bulkArchiveBtn">
                <i data-lucide="archive" style="width:13px;height:13px" class="me-1"></i>Archive Selected
            </button>
            <button type="button" class="btn btn-soft btn-sm ms-auto" id="bulkClear">Clear selection</button>
        </div>
    </div>
<?php endif; ?>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
            <tr>
                <?php if (can('leave.archive')): ?>
                <th style="width:32px"><input type="checkbox" class="form-check-input" id="selectAll" title="Select all"></th>
                <?php endif; ?>
                <th>#</th><th>Employee</th><th>Type</th><th>Dates</th><th>Days</th><th>Status</th><th></th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($requests)): ?>
                <tr><td colspan="<?= can('leave.archive') ? 8 : 7 ?>" class="text-center text-muted py-4">No leave requests found.</td></tr>
            <?php else: foreach ($requests as $row): ?>
                <tr>
                    <?php if (can('leave.archive')): ?>
                    <td><input type="checkbox" class="form-check-input row-check" name="ids[]" value="<?= (int)$row['id'] ?>"></td>
                    <?php endif; ?>
                    <td><?= (int)$row['id'] ?></td>
                    <td>
                        <div class="fw-semibold"><?= e($row['employee_name'] ?? '') ?></div>
                        <div class="text-muted small"><?= e($row['employee_code'] ?? '') ?></div>
                    </td>
                    <td><?= e($row['leave_type_name'] ?? '') ?></td>
                    <td><?= e(format_date($row['start_date'])) ?> → <?= e(format_date($row['end_date'])) ?></td>
                    <td><?= e((string)$row['chargeable_days']) ?></td>
                    <td><?= status_badge($row['status']) ?></td>
                    <td class="text-end text-nowrap">
                        <?php
                        $lid = (int) $row['id'];
                        $actions = [
                            ['type' => 'link', 'icon' => 'eye', 'label' => 'View', 'href' => '/admin/leave/' . $lid],
                        ];
                        if (can('leave.edit') && in_array($row['status'], ['draft', 'pending', 'approved'], true)) {
                            $actions[] = ['type' => 'link', 'icon' => 'pencil', 'label' => 'Edit', 'variant' => 'warning', 'href' => '/admin/leave/' . $lid . '/edit'];
                        }
                        if (can('leave.cancel') && in_array($row['status'], ['pending', 'approved'], true)) {
                            $actions[] = ['type' => 'link', 'icon' => 'ban', 'label' => 'Cancel', 'variant' => 'danger', 'href' => '/admin/leave/' . $lid . '#cancel'];
                        }
                        if (can('leave.archive')) {
                            $actions[] = ['type' => 'link', 'icon' => 'archive', 'label' => 'Archive', 'variant' => 'danger', 'href' => '/admin/leave/' . $lid . '#archive'];
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
        <div class="card-footer py-2"><?= paginate_links($paginator, '/admin/leave?' . http_build_query(array_filter($filters ?? []))) ?></div>
    <?php endif; ?>
</div>

<?php if (can('leave.archive')): ?>
</form>
<script>
(function () {
    var form    = document.getElementById('bulkForm');
    var bar     = document.getElementById('bulkBar');
    var counter = document.getElementById('bulkCount');
    var selectAll  = document.getElementById('selectAll');
    var archiveBtn = document.getElementById('bulkArchiveBtn');
    var clearBtn   = document.getElementById('bulkClear');
    var reasonInp  = document.getElementById('bulkReasonInput');

    function getChecked() {
        return Array.from(document.querySelectorAll('.row-check:checked'));
    }

    function update() {
        var checked = getChecked();
        counter.textContent = checked.length;
        bar.style.display = checked.length > 0 ? '' : 'none';
        if (selectAll) {
            var all = document.querySelectorAll('.row-check');
            selectAll.indeterminate = checked.length > 0 && checked.length < all.length;
            selectAll.checked = all.length > 0 && checked.length === all.length;
        }
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    document.querySelectorAll('.row-check').forEach(function (cb) {
        cb.addEventListener('change', update);
    });

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.row-check').forEach(function (cb) { cb.checked = selectAll.checked; });
            update();
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            document.querySelectorAll('.row-check').forEach(function (cb) { cb.checked = false; });
            if (selectAll) selectAll.checked = false;
            update();
        });
    }

    if (archiveBtn) {
        archiveBtn.addEventListener('click', function () {
            var reason = reasonInp.value.trim();
            if (!reason) { reasonInp.focus(); reasonInp.classList.add('is-invalid'); return; }
            reasonInp.classList.remove('is-invalid');
            if (!confirm('Archive ' + getChecked().length + ' leave request(s)?')) return;
            document.getElementById('bulkReason').value = reason;
            form.submit();
        });
    }
})();
</script>
<?php endif; ?>
