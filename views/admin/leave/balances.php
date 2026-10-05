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
                    <thead class="table-light"><tr><th>Type</th><th>Opening</th><th>Accrued</th><th>Used</th><th>Pending</th><th>Adjusted</th><th>Closing</th><th></th></tr></thead>
                    <tbody>
                    <?php if (empty($balances)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-3">No balances for this employee/year.</td></tr>
                    <?php else: foreach ($balances as $b): ?>
                        <tr>
                            <td><?= e($b['leave_type_name'] ?? '') ?></td>
                            <td><?= e((string)$b['opening_balance']) ?></td>
                            <td><?= e((string)$b['accrued']) ?></td>
                            <td><?= e((string)$b['used']) ?></td>
                            <td><?= e((string)$b['pending']) ?></td>
                            <td><?= e((string)$b['adjusted']) ?></td>
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
            <div class="card-header py-2 fw-semibold">Adjust Balance</div>
            <div class="card-body">
                <form method="POST" action="/admin/leave/balances">
                    <?= csrf_field() ?>
                    <input type="hidden" name="employee_id" value="<?= (int)$employeeId ?>">
                    <input type="hidden" name="year" value="<?= (int)$year ?>">
                    <div class="mb-2">
                        <label class="form-label small" for="leave_type_id">Leave Type</label>
                        <select name="leave_type_id" class="form-select form-select-sm" required id="leave_type_id">
                            <?php foreach ($leaveTypes as $lt): ?>
                                <option value="<?= (int)$lt['id'] ?>"><?= e($lt['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2">
                        <div class="col-6"><label class="form-label small" for="opening_balance">Opening</label><input type="number" step="0.5" name="opening_balance" class="form-control form-control-sm" id="opening_balance"></div>
                        <div class="col-6"><label class="form-label small" for="accrued">Accrued</label><input type="number" step="0.5" name="accrued" class="form-control form-control-sm" id="accrued"></div>
                        <div class="col-6"><label class="form-label small" for="used">Used (days taken)</label><input type="number" step="0.5" min="0" name="used" class="form-control form-control-sm" id="used" placeholder="Leave blank to keep"></div>
                        <div class="col-6"><label class="form-label small" for="adjusted">Adjusted</label><input type="number" step="0.5" name="adjusted" class="form-control form-control-sm" id="adjusted"></div>
                    </div>
                    <div class="form-text mt-1">Set <strong>Used</strong> to override leave days taken (e.g. when employee did not apply through the system).</div>
                    <div class="mt-2"><label class="form-label small" for="notes">Notes</label><input type="text" name="notes" class="form-control form-control-sm" id="notes"></div>
                    <button class="btn btn-primary btn-sm mt-3">Save Balance</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
