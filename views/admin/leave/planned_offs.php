<?php
/** @var array $rows */
/** @var array $filters */
/** @var int   $companyId */
/** @var array $companies */
/** @var array $employeeList */
?>
<!-- flatpickr -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.js"></script>

<div class="page-header d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h1>Planned Off Days</h1>
        <p class="subtitle">Schedule employee off days and notify them automatically</p>
    </div>
</div>

<?php include config('app.paths.views') . '/partials/alerts.php'; ?>

<div class="row g-3">
    <!-- Add Form -->
    <div class="col-lg-4">
        <div class="card ems-card mb-3">
            <div class="card-header"><strong>Add Off Day(s)</strong></div>
            <div class="card-body">
                <form method="post" action="/admin/leave/planned-offs" id="podForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="company_id" value="<?= (int) $companyId ?>">

                    <div class="mb-3">
                        <label class="form-label small" for="pod-employee">Employee <span class="text-danger">*</span></label>
                        <select name="employee_id" id="pod-employee" class="form-select form-select-sm" required>
                            <option value="">Select employee…</option>
                            <?php foreach ($employeeList as $emp): ?>
                            <option value="<?= (int) $emp['id'] ?>">
                                <?= e($emp['employee_code'] . ' — ' . trim($emp['first_name'] . ' ' . $emp['last_name'])) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Calendar date picker -->
                    <div class="mb-1">
                        <label class="form-label small">Off Date(s) <span class="text-danger">*</span></label>
                        <div class="pod-cal-wrap position-relative">
                            <div id="pod-cal-inline"></div>
                        </div>
                        <!-- hidden field submitted to server -->
                        <input type="hidden" name="off_dates" id="pod-dates" required>
                    </div>

                    <!-- Selected dates chips -->
                    <div id="pod-chips" class="d-flex flex-wrap gap-1 mb-3" style="min-height:1.5rem"></div>

                    <div class="mb-3">
                        <label class="form-label small" for="pod-reason">Reason <span class="text-danger">*</span></label>
                        <input type="text" name="reason" id="pod-reason" class="form-control form-control-sm"
                               placeholder="e.g. Compensatory off, Weekly off, Sick leave…" required maxlength="255">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small" for="pod-note">Note <span class="text-muted">(optional)</span></label>
                        <textarea name="note" id="pod-note" class="form-control form-control-sm" rows="2"
                                  placeholder="Additional details…" maxlength="1000"></textarea>
                    </div>

                    <!-- Leave balance deduction (past dates only) -->
                    <div id="podLeaveSection" style="display:none">
                        <hr class="my-2">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" id="podLeaveToggle" role="switch">
                                <label class="form-check-label small fw-semibold" for="podLeaveToggle">Deduct from leave balance</label>
                            </div>
                        </div>
                        <div id="podLeaveTypeRow" style="display:none">
                            <select name="leave_type_id" id="pod-leave-type" class="form-select form-select-sm mb-1">
                                <option value="">Select leave type…</option>
                                <?php foreach ($leaveTypes ?? [] as $lt): ?>
                                <option value="<?= (int) $lt['id'] ?>"><?= e($lt['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text text-warning" style="font-size:.7rem">
                                Past dates only. Each date creates an approved leave record and deducts 1 day from the selected type.
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-sm w-100 mt-3" id="podSubmitBtn" disabled>
                        <i data-lucide="calendar-plus" style="width:14px;height:14px" class="me-1"></i>
                        <span id="podSubmitLabel">Select date(s) above</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- List -->
    <div class="col-lg-8">
        <!-- Filters -->
        <div class="card ems-card mb-3">
            <div class="card-body py-2">
                <form class="row g-2 align-items-end" method="GET">
                    <input type="hidden" name="company_id" value="<?= (int) $companyId ?>">
                    <?php if (count($companies) > 1): ?>
                    <div class="col-md-3">
                        <label class="form-label small mb-0">Company</label>
                        <select name="company_id" class="form-select form-select-sm" onchange="this.form.submit()">
                            <?php foreach ($companies as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= (int) $companyId === (int) $c['id'] ? 'selected' : '' ?>>
                                <?= e($c['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-4">
                        <label class="form-label small mb-0">Employee</label>
                        <select name="employee_id" class="form-select form-select-sm">
                            <option value="">All employees</option>
                            <?php foreach ($employeeList as $emp): ?>
                            <option value="<?= (int) $emp['id'] ?>" <?= (string) ($filters['employee_id'] ?? '') === (string) $emp['id'] ? 'selected' : '' ?>>
                                <?= e(trim($emp['first_name'] . ' ' . $emp['last_name'])) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-0">From</label>
                        <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($filters['date_from'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-0">To</label>
                        <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($filters['date_to'] ?? '') ?>">
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-sm btn-soft w-100">Filter</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card ems-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Scheduled Off Days</strong>
                <span class="badge bg-secondary"><?= count($rows) ?></span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Reason</th>
                            <th>Notified</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="6"><div class="empty-state py-4 mb-0">No planned off days found.</div></td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                        <?php
                            $isPast  = $row['off_date'] < date('Y-m-d');
                            $isToday = $row['off_date'] === date('Y-m-d');
                        ?>
                        <tr class="<?= $isPast ? 'text-muted' : '' ?>">
                            <td class="text-nowrap">
                                <?php if ($isToday): ?>
                                    <span class="badge bg-warning text-dark me-1">Today</span>
                                <?php elseif (!$isPast): ?>
                                    <span class="badge bg-success-subtle text-success me-1">Upcoming</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary me-1">Past</span>
                                <?php endif; ?>
                                <?= e(date('D, d M Y', strtotime($row['off_date']))) ?>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= e($row['employee_name']) ?></div>
                                <div class="small text-muted"><?= e($row['employee_code']) ?></div>
                            </td>
                            <td><?= e($row['department_name'] ?? '—') ?></td>
                            <td>
                                <?= e($row['reason']) ?>
                                <?php if (!empty($row['note'])): ?>
                                    <div class="small text-muted"><?= e($row['note']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap">
                                <?php if ($row['notified_at']): ?>
                                    <span class="badge bg-success-subtle text-success">
                                        <i data-lucide="bell-ring" style="width:11px;height:11px" class="me-1"></i>Sent
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary">No account</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <?php if ($isPast): ?>
                                <button type="button"
                                        class="btn btn-xs btn-soft text-primary me-1 pod-log-btn"
                                        data-id="<?= (int) $row['id'] ?>"
                                        data-date="<?= e(date('d M Y', strtotime($row['off_date']))) ?>"
                                        data-emp="<?= e($row['employee_name']) ?>"
                                        title="Record this off day against leave balance">
                                    <i data-lucide="wallet" style="width:12px;height:12px"></i>
                                </button>
                                <?php endif; ?>
                                <?php
                                $actions = [];
                                $actions[] = [
                                    'type'    => 'form',
                                    'icon'    => 'trash-2',
                                    'label'   => 'Delete',
                                    'variant' => 'danger',
                                    'action'  => '/admin/leave/planned-offs/' . (int) $row['id'] . '/delete',
                                    'confirm' => 'Remove this planned off day for ' . e($row['employee_name']) . ' on ' . e(date('d M Y', strtotime($row['off_date']))) . '?',
                                ];
                                include config('app.paths.views') . '/partials/table-actions.php';
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
/* Inline calendar wrapper */
.pod-cal-wrap .flatpickr-calendar {
    position: static !important;
    box-shadow: none !important;
    border: 1px solid var(--ems-border, #e2e8f0);
    border-radius: .5rem;
    width: 100% !important;
    margin-bottom: .5rem;
}
.pod-cal-wrap .flatpickr-calendar .flatpickr-innerContainer,
.pod-cal-wrap .flatpickr-calendar .flatpickr-days,
.pod-cal-wrap .flatpickr-calendar .dayContainer {
    width: 100% !important;
    min-width: 0 !important;
    max-width: 100% !important;
}
.pod-cal-wrap .flatpickr-day { flex: 1; max-width: none; }

/* Dark mode overrides */
html[data-theme="dark"] .pod-cal-wrap .flatpickr-calendar {
    background: #1e2a3a;
    border-color: rgba(255,255,255,.1);
    color: #e2e8f0;
}
html[data-theme="dark"] .flatpickr-months,
html[data-theme="dark"] .flatpickr-month { background: #1e2a3a; color: #e2e8f0; fill: #e2e8f0; }
html[data-theme="dark"] .flatpickr-current-month,
html[data-theme="dark"] .flatpickr-current-month .flatpickr-monthDropdown-months,
html[data-theme="dark"] .numInputWrapper span { color: #e2e8f0; }
html[data-theme="dark"] .flatpickr-weekday { color: #94a3b8; }
html[data-theme="dark"] .flatpickr-day { color: #e2e8f0; }
html[data-theme="dark"] .flatpickr-day:hover { background: rgba(255,255,255,.08); }
html[data-theme="dark"] .flatpickr-day.selected,
html[data-theme="dark"] .flatpickr-day.selected:hover { background: var(--ws-accent, #6366f1); border-color: var(--ws-accent, #6366f1); color: #fff; }
html[data-theme="dark"] .flatpickr-day.today:not(.selected) { border-color: var(--ws-accent, #6366f1); color: var(--ws-accent, #6366f1); }
html[data-theme="dark"] .flatpickr-day.flatpickr-disabled { color: rgba(255,255,255,.2); }
html[data-theme="dark"] .flatpickr-prev-month svg,
html[data-theme="dark"] .flatpickr-next-month svg { fill: #e2e8f0; }
html[data-theme="dark"] .numInputWrapper:hover,
html[data-theme="dark"] .flatpickr-monthDropdown-months { background: transparent; color: #e2e8f0; }

/* Chips */
.pod-chip {
    display: inline-flex; align-items: center; gap: .3rem;
    background: var(--ws-accent, #6366f1); color: #fff;
    padding: .15rem .5rem; border-radius: 1rem; font-size: .75rem; font-weight: 500;
}
.pod-chip button { background: none; border: none; color: rgba(255,255,255,.8); cursor: pointer; padding: 0; line-height: 1; font-size: .85rem; }
.pod-chip button:hover { color: #fff; }
</style>

<!-- Log as Leave modal -->
<div class="modal fade" id="podLogModal" tabindex="-1" aria-labelledby="podLogModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title" id="podLogModalLabel">Record against leave balance</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" id="podLogForm" action="">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <p class="small mb-2" id="podLogDesc"></p>
                    <label class="form-label small" for="log-leave-type">Leave Type <span class="text-danger">*</span></label>
                    <select name="leave_type_id" id="log-leave-type" class="form-select form-select-sm" required>
                        <option value="">Select leave type…</option>
                        <?php foreach ($leaveTypes ?? [] as $lt): ?>
                        <option value="<?= (int) $lt['id'] ?>"><?= e($lt['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text mt-1">This records the off day as an approved leave request and deducts 1 day from the balance.</div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-soft" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Deduct from Balance</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    var hiddenInput  = document.getElementById('pod-dates');
    var chipsEl      = document.getElementById('pod-chips');
    var submitBtn    = document.getElementById('podSubmitBtn');
    var submitLabel  = document.getElementById('podSubmitLabel');
    var leaveSection = document.getElementById('podLeaveSection');
    var leaveToggle  = document.getElementById('podLeaveToggle');
    var leaveTypeRow = document.getElementById('podLeaveTypeRow');
    var leaveSel     = document.getElementById('pod-leave-type');
    var selectedDates = [];
    var TODAY = new Date(); TODAY.setHours(0,0,0,0);

    function fmt(dateObj) {
        var y = dateObj.getFullYear();
        var m = String(dateObj.getMonth() + 1).padStart(2, '0');
        var d = String(dateObj.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + d;
    }

    function fmtDisplay(dateObj) {
        return dateObj.toLocaleDateString('en-US', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
    }

    function hasPastDates() {
        return selectedDates.some(function (d) {
            var parts = d.split('-');
            var dt = new Date(+parts[0], +parts[1] - 1, +parts[2]);
            return dt <= TODAY;
        });
    }

    function syncState() {
        hiddenInput.value = selectedDates.join(', ');
        var n = selectedDates.length;
        submitBtn.disabled = n === 0;
        submitLabel.textContent = n === 0
            ? 'Select date(s) above'
            : 'Add & Notify — ' + n + ' day' + (n > 1 ? 's' : '') + ' selected';

        // Show leave deduction section only when at least one past date is selected
        if (leaveSection) {
            leaveSection.style.display = (n > 0 && hasPastDates()) ? '' : 'none';
            if (!hasPastDates() && leaveToggle) {
                leaveToggle.checked = false;
                if (leaveTypeRow) leaveTypeRow.style.display = 'none';
                if (leaveSel) leaveSel.removeAttribute('required');
            }
        }
    }

    // Toggle leave type row visibility
    if (leaveToggle) {
        leaveToggle.addEventListener('change', function () {
            if (leaveTypeRow) leaveTypeRow.style.display = leaveToggle.checked ? '' : 'none';
            if (leaveSel) {
                if (leaveToggle.checked) leaveSel.setAttribute('required', '');
                else leaveSel.removeAttribute('required');
            }
        });
    }

    function renderChips() {
        chipsEl.innerHTML = '';
        selectedDates.forEach(function (d) {
            var parts = d.split('-');
            var dateObj = new Date(+parts[0], +parts[1] - 1, +parts[2]);
            var chip = document.createElement('span');
            chip.className = 'pod-chip';
            chip.innerHTML = fmtDisplay(dateObj)
                + '<button type="button" aria-label="Remove" data-d="' + d + '">&#x2715;</button>';
            chipsEl.appendChild(chip);
        });
        chipsEl.querySelectorAll('button').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var d = btn.getAttribute('data-d');
                selectedDates = selectedDates.filter(function (x) { return x !== d; });
                // deselect in calendar
                var parts = d.split('-');
                fp.deselect(new Date(+parts[0], +parts[1] - 1, +parts[2]));
                renderChips();
                syncState();
            });
        });
    }

    var fp = flatpickr(document.getElementById('pod-cal-inline'), {
        inline: true,
        mode: 'multiple',
        dateFormat: 'Y-m-d',
        // no maxDate, no minDate — past dates allowed
        onChange: function (dates) {
            selectedDates = dates.map(fmt);
            selectedDates.sort();
            renderChips();
            syncState();
        },
    });

    syncState();

    // "Log as Leave" wallet-icon buttons on past rows
    document.querySelectorAll('.pod-log-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id   = btn.getAttribute('data-id');
            var date = btn.getAttribute('data-date');
            var emp  = btn.getAttribute('data-emp');
            var form = document.getElementById('podLogForm');
            var desc = document.getElementById('podLogDesc');
            if (form) form.action = '/admin/leave/planned-offs/' + id + '/log-leave';
            if (desc) desc.textContent = 'Recording ' + date + ' for ' + emp + ' against leave balance.';
            var modal = new bootstrap.Modal(document.getElementById('podLogModal'));
            modal.show();
        });
    });
})();
</script>
