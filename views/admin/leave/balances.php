<div class="card mb-3"><div class="card-body py-2">
<form method="GET" class="row g-2 align-items-end">
    <div class="col-md-5">
        <label class="form-label small mb-0" for="employee_id">Employee</label>
        <select name="employee_id" class="form-select form-select-sm" required id="employee_id">
            <option value="">Select employee</option>
            <?php foreach ($employees as $emp): ?>
                <option value="<?= (int)$emp['id'] ?>" <?= (int)$employeeId === (int)$emp['id'] ? 'selected' : '' ?>><?= e($emp['name']) ?> (<?= e($emp['employee_code']) ?>)</option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <label class="form-label small mb-0" for="year">Year</label>
        <input type="number" name="year" class="form-control form-control-sm" value="<?= (int)$year ?>" id="year">
    </div>
    <div class="col-md-2"><button class="btn btn-secondary btn-sm">Load</button></div>
    <div class="col-md-3 ms-auto text-end">
        <a href="/admin/leave/create<?= $employeeId ? '?employee_id=' . (int)$employeeId : '' ?>" class="btn btn-primary btn-sm">
            <i data-lucide="plus" style="width:14px;height:14px"></i> Record Leave
        </a>
    </div>
</form>
</div></div>

<?php if ($employeeId): ?>
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Type</th><th>Opening</th><th>Used <small class="text-muted fw-normal">(balance / actual)</small></th><th>Pending</th><th>Closing</th><th></th></tr></thead>
                    <tbody>
                    <?php if (empty($balances)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">No balances for this employee/year.</td></tr>
                    <?php else: foreach ($balances as $b):
                        $ltId   = (int) $b['leave_type_id'];
                        $balUsed = (float) $b['used'];
                        $actUsed = $actualUsed[$ltId] ?? null;
                        $mismatch = $actUsed !== null && $actUsed !== $balUsed;
                    ?>
                        <tr <?= $mismatch ? 'class="table-warning"' : '' ?>>
                            <td><?= e($b['leave_type_name'] ?? '') ?></td>
                            <td><?= e((string)$b['opening_balance']) ?></td>
                            <td>
                                <?= e((string)$balUsed) ?>
                                <?php if ($actUsed !== null): ?>
                                    <span class="text-muted">/</span>
                                    <span class="<?= $mismatch ? 'text-danger fw-semibold' : 'text-success' ?>">
                                        <?= e((string)$actUsed) ?>
                                    </span>
                                    <?php if ($mismatch): ?>
                                        <form method="POST" action="/admin/leave/balances/sync-used" class="d-inline ms-1">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="employee_id" value="<?= (int)$employeeId ?>">
                                            <input type="hidden" name="leave_type_id" value="<?= $ltId ?>">
                                            <input type="hidden" name="year" value="<?= (int)$year ?>">
                                            <button type="submit" class="btn btn-xs btn-warning py-0 px-1" title="Sync used days from actual approved leaves" style="font-size:.7rem">
                                                Sync
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td><?= e((string)$b['pending']) ?></td>
                            <td class="fw-semibold"><?= e((string)$b['closing_balance']) ?></td>
                            <td class="text-end text-nowrap">
                                <a href="/admin/leave/create?employee_id=<?= (int)$employeeId ?>" class="btn btn-xs btn-soft" title="Record leave">
                                    <i data-lucide="plus" style="width:12px;height:12px"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header py-2">
                <div class="mb-2 fw-semibold">Manage Leave Balance</div>
                <div class="mb-1">
                    <label class="form-label small mb-1" for="sharedLeaveType">Leave Type</label>
                    <select class="form-select form-select-sm" id="sharedLeaveType">
                        <?php foreach ($leaveTypes as $lt): ?>
                            <option value="<?= (int)$lt['id'] ?>"><?= e($lt['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <ul class="nav nav-tabs card-header-tabs mt-2" id="balanceTabs">
                    <li class="nav-item">
                        <a class="nav-link active small py-1 px-2" href="#" data-tab="adjust">Adjust Balance</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link small py-1 px-2" href="#" data-tab="dates">
                            <i data-lucide="calendar-check" style="width:13px;height:13px" class="me-1"></i>Add Leave Dates
                        </a>
                    </li>
                </ul>
            </div>
            <div class="card-body">

                <?php /* Tab: Adjust Balance */ ?>
                <div id="tabAdjust">
                    <form method="POST" action="/admin/leave/balances" id="adjustForm">
                        <?= csrf_field() ?>
                        <input type="hidden" name="employee_id" value="<?= (int)$employeeId ?>">
                        <input type="hidden" name="year" value="<?= (int)$year ?>">
                        <input type="hidden" name="leave_type_id" id="adjustLeaveTypeId">
                        <div class="row g-2">
                            <div class="col-6"><label class="form-label small" for="opening_balance">Opening Balance</label><input type="number" step="0.5" min="0" name="opening_balance" class="form-control form-control-sm" id="opening_balance" placeholder="Leave blank to keep"></div>
                            <div class="col-6"><label class="form-label small" for="used">Used (days taken)</label><input type="number" step="0.5" min="0" name="used" class="form-control form-control-sm" id="used" placeholder="Leave blank to keep"></div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold" for="closing_balance_override">
                                    Set Closing Balance <span class="text-muted fw-normal">(optional)</span>
                                </label>
                                <input type="number" step="0.5" name="closing_balance_override" class="form-control form-control-sm" id="closing_balance_override" placeholder="Leave blank to auto-calculate">
                                <div class="form-text" style="font-size:.72rem">If set, closing is forced to this value. Leave blank to auto-calculate.</div>
                            </div>
                        </div>
                        <div class="form-text mt-1">Leave any field blank to keep its current value.</div>
                        <div class="mt-2"><label class="form-label small" for="notes">Notes</label><input type="text" name="notes" class="form-control form-control-sm" id="notes"></div>
                        <button class="btn btn-primary btn-sm mt-3">Save Balance</button>
                    </form>
                </div>

                <?php /* Tab: Add Leave Dates */ ?>
                <div id="tabDates" style="display:none">
                    <form method="POST" action="/admin/leave/log-past-dates" id="logPastForm">
                        <?= csrf_field() ?>
                        <input type="hidden" name="employee_id" value="<?= (int)$employeeId ?>">
                        <input type="hidden" name="year" value="<?= (int)$year ?>">
                        <input type="hidden" name="leave_type_id" id="pastLeaveTypeId">
                        <input type="hidden" name="dates" id="pastDatesHidden">

                        <div class="mb-2">
                            <label class="form-label small">Select Dates</label>
                            <div id="pastDatePicker" class="border rounded p-2" style="background:var(--ems-surface,#fff)"></div>
                            <div id="pastChips" class="d-flex flex-wrap gap-1 mt-2"></div>
                        </div>

                        <div id="verifyBar" class="mb-2 p-2 rounded small" style="display:none;border:1px solid var(--ems-border,#e2e8f0);background:var(--ems-surface-alt,#f8fafc)">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Used in balance: <strong id="usedCount">—</strong></span>
                                <span>Dates selected: <strong id="selectedCount">0</strong></span>
                                <span id="matchBadge"></span>
                            </div>
                        </div>

                        <div class="mb-2">
                            <label class="form-label small" for="pastReasonSelect">Reason <span class="text-danger">*</span></label>
                            <select id="pastReasonSelect" class="form-select form-select-sm mb-1">
                                <option value="">— Select reason —</option>
                                <option value="Absent">Absent</option>
                                <option value="Sick Leave">Sick Leave</option>
                                <option value="Personal">Personal</option>
                                <option value="Medical">Medical</option>
                                <option value="Annual Leave">Annual Leave</option>
                                <option value="Emergency">Emergency</option>
                                <option value="Family Matter">Family Matter</option>
                                <option value="Work from Home">Work from Home</option>
                                <option value="Unpaid Leave">Unpaid Leave</option>
                                <option value="other">Other (type your own)…</option>
                            </select>
                            <input type="text" name="reason" id="pastReason" class="form-control form-control-sm" required placeholder="Type reason…" style="display:none">
                        </div>

                        <div class="mb-3 p-2 rounded" style="background:var(--ems-surface-alt,#f8fafc);border:1px solid var(--ems-border,#e2e8f0)">
                            <div class="form-check mb-0">
                                <input class="form-check-input" type="checkbox" name="already_counted" id="alreadyCounted" value="1">
                                <label class="form-check-label small fw-semibold" for="alreadyCounted">
                                    Already counted in balance (date tracking only)
                                </label>
                            </div>
                            <div class="form-text mt-1" style="font-size:.72rem">
                                Check this when the "Used" number already includes these days.
                                Leave records are created so dates appear in history, but <strong>Used and closing balance are not changed at all</strong>.
                            </div>
                        </div>

                        <button type="submit" class="btn btn-warning btn-sm" id="logPastBtn" disabled>
                            <i data-lucide="check" style="width:13px;height:13px" class="me-1"></i>
                            Add Leave Dates
                        </button>
                    </form>
                </div>

            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.js"></script>
<style>
#pastDatePicker .flatpickr-calendar { box-shadow: none; border: none; }
[data-theme="dark"] #pastDatePicker { background: var(--ems-surface-alt, #1e293b); }
[data-theme="dark"] #pastDatePicker .flatpickr-calendar,
[data-theme="dark"] #pastDatePicker .flatpickr-months,
[data-theme="dark"] #pastDatePicker .flatpickr-weekdays,
[data-theme="dark"] #pastDatePicker .flatpickr-days { background: var(--ems-surface-alt, #1e293b); color: var(--ems-text, #f1f5f9); }
[data-theme="dark"] #pastDatePicker .flatpickr-day { color: var(--ems-text, #f1f5f9); }
[data-theme="dark"] #pastDatePicker .flatpickr-day:hover { background: var(--ems-primary, #3b82f6); }
[data-theme="dark"] #pastDatePicker .flatpickr-day.selected { background: var(--ems-primary, #3b82f6); border-color: var(--ems-primary, #3b82f6); }
[data-theme="dark"] #pastDatePicker .flatpickr-day.flatpickr-disabled { color: #4b5563; }
</style>
<script>
(function () {
    var usedMap = <?= json_encode(
        array_column(
            array_map(fn($b) => ['id' => (int)$b['leave_type_id'], 'used' => (float)$b['used']], $balances),
            'used', 'id'
        )
    ) ?>;

    // --- Flatpickr (lazy init — must be visible before init) ---
    var pastDates = [];
    var fp = null;

    function initFlatpickr() {
        if (fp) { fp.redraw(); return; }
        fp = flatpickr('#pastDatePicker', {
            inline: true,
            mode: 'multiple',
            onChange: function (selectedDates) {
                pastDates = selectedDates.map(function (d) { return d.toISOString().slice(0, 10); });
                renderChips();
                syncVerifyBar();
            }
        });
    }

    // --- Tab switching ---
    var tabs = document.querySelectorAll('#balanceTabs .nav-link');
    tabs.forEach(function (tab) {
        tab.addEventListener('click', function (e) {
            e.preventDefault();
            tabs.forEach(function (t) { t.classList.remove('active'); });
            tab.classList.add('active');
            var which = tab.dataset.tab;
            document.getElementById('tabAdjust').style.display = which === 'adjust' ? '' : 'none';
            document.getElementById('tabDates').style.display  = which === 'dates'  ? '' : 'none';
            if (which === 'dates') initFlatpickr();
            if (typeof lucide !== 'undefined') lucide.createIcons();
        });
    });

    // --- Reason dropdown → hidden text input ---
    var reasonSelect = document.getElementById('pastReasonSelect');
    var reasonInput  = document.getElementById('pastReason');
    function syncReason() {
        var val = reasonSelect.value;
        if (val === 'other') {
            reasonInput.style.display = '';
            reasonInput.required = true;
            reasonInput.value = '';
            reasonInput.focus();
        } else if (val === '') {
            reasonInput.style.display = 'none';
            reasonInput.required = true;
            reasonInput.value = '';
        } else {
            reasonInput.style.display = 'none';
            reasonInput.required = false;
            reasonInput.value = val;
        }
    }
    reasonSelect.addEventListener('change', syncReason);
    syncReason();

    // Validate on submit: dropdown must have a selection
    document.getElementById('logPastForm').addEventListener('submit', function (e) {
        if (reasonSelect.value === '') {
            e.preventDefault();
            reasonSelect.classList.add('is-invalid');
            reasonSelect.focus();
            return;
        }
        reasonSelect.classList.remove('is-invalid');
        if (reasonSelect.value === 'other' && reasonInput.value.trim() === '') {
            e.preventDefault();
            reasonInput.classList.add('is-invalid');
            reasonInput.focus();
        } else {
            reasonInput.classList.remove('is-invalid');
        }
    });

    // --- Shared leave type → sync hidden inputs ---
    var sharedSel = document.getElementById('sharedLeaveType');
    function syncLeaveType() {
        var v = sharedSel.value;
        document.getElementById('adjustLeaveTypeId').value = v;
        document.getElementById('pastLeaveTypeId').value   = v;
        syncVerifyBar();
    }
    sharedSel.addEventListener('change', syncLeaveType);
    syncLeaveType(); // init

    function renderChips() {
        var c = document.getElementById('pastChips');
        c.innerHTML = '';
        pastDates.forEach(function (d) {
            var chip = document.createElement('span');
            chip.className = 'badge bg-warning text-dark d-flex align-items-center gap-1';
            chip.style.cursor = 'default';
            var label = new Date(d + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
            chip.innerHTML = label + ' <span style="cursor:pointer;font-size:.9em" data-d="' + d + '">×</span>';
            chip.querySelector('span').addEventListener('click', function () {
                fp.deselect(new Date(this.dataset.d + 'T00:00:00'));
            });
            c.appendChild(chip);
        });
    }

    function syncVerifyBar() {
        document.getElementById('pastDatesHidden').value = pastDates.join(',');
        document.getElementById('logPastBtn').disabled = pastDates.length === 0;

        var typeId = parseInt(sharedSel.value, 10);
        var bar = document.getElementById('verifyBar');
        if (!typeId || !(typeId in usedMap)) { bar.style.display = 'none'; return; }

        var used     = usedMap[typeId];
        var selected = pastDates.length;
        bar.style.display = '';
        document.getElementById('usedCount').textContent     = used;
        document.getElementById('selectedCount').textContent = selected;

        var badge = document.getElementById('matchBadge');
        if (selected === 0) {
            badge.innerHTML = '';
        } else if (selected === used) {
            badge.innerHTML = '<span class="badge bg-success">&#10003; Match</span>';
        } else {
            badge.innerHTML = '<span class="badge bg-danger">&#9888; Mismatch (' + (selected > used ? '+' : '') + (selected - used) + ')</span>';
        }
    }
})();
</script>
<?php endif; ?>
