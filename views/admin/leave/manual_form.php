<?php /** @var array $employees */ /** @var array $leaveTypes */ /** @var array $companies */ /** @var int|null $companyId */ /** @var int $preEmployee */ ?>
<div class="page-header">
    <div>
        <h1>Record Leave for Employee</h1>
        <p class="subtitle">Admin-recorded leave is automatically approved and deducted from the employee's balance.</p>
    </div>
    <a href="/admin/leave" class="btn btn-sm btn-soft">
        <i data-lucide="arrow-left" class="me-1" style="width:14px;height:14px"></i>Back to Leave
    </a>
</div>

<?php include config('app.paths.views') . '/partials/alerts.php'; ?>

<?php if (count($companies) > 1): ?>
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="d-flex align-items-center gap-2">
            <label class="form-label small mb-0 text-nowrap">Company:</label>
            <select name="company_id" class="form-select form-select-sm" style="max-width:220px" onchange="this.form.submit()">
                <option value="">Select company</option>
                <?php foreach ($companies as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= (int)$companyId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header fw-semibold">Leave Details</div>
            <div class="card-body">
                <form method="POST" action="/admin/leave">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label" for="employee_id">Employee <span class="text-danger">*</span></label>
                        <select name="employee_id" id="employee_id" class="form-select" required>
                            <option value="">Select employee…</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?= (int)$emp['id'] ?>" <?= (int)$preEmployee === (int)$emp['id'] ? 'selected' : '' ?>>
                                    <?= e($emp['name'] ?? (($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''))) ?>
                                    (<?= e($emp['employee_code'] ?? '') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($employees)): ?>
                            <div class="form-text text-warning">No employees found. <?= count($companies) > 1 ? 'Select a company above first.' : '' ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="leave_type_id">Leave Type <span class="text-danger">*</span></label>
                        <select name="leave_type_id" id="leave_type_id" class="form-select" required>
                            <option value="">Select leave type…</option>
                            <?php foreach ($leaveTypes as $lt): ?>
                                <option value="<?= (int)$lt['id'] ?>"><?= e($lt['name']) ?> (<?= e($lt['code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label" for="start_date">Start Date <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" id="start_date" class="form-control" required
                                   value="<?= e($_POST['start_date'] ?? '') ?>">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="end_date">End Date <span class="text-danger">*</span></label>
                            <input type="date" name="end_date" id="end_date" class="form-control" required
                                   value="<?= e($_POST['end_date'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_half_day" id="is_half_day" value="1"
                                   <?= !empty($_POST['is_half_day']) ? 'checked' : '' ?>
                                   onchange="document.getElementById('halfDayTypeRow').style.display=this.checked?'block':'none'">
                            <label class="form-check-label" for="is_half_day">Half Day</label>
                        </div>
                    </div>

                    <div class="mb-3" id="halfDayTypeRow" style="display:<?= !empty($_POST['is_half_day']) ? 'block' : 'none' ?>">
                        <label class="form-label" for="half_day_type">Half Day Type</label>
                        <select name="half_day_type" id="half_day_type" class="form-select">
                            <option value="first_half" <?= ($_POST['half_day_type'] ?? '') === 'first_half' ? 'selected' : '' ?>>First Half</option>
                            <option value="second_half" <?= ($_POST['half_day_type'] ?? '') === 'second_half' ? 'selected' : '' ?>>Second Half</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="reason">Reason / Notes <span class="text-danger">*</span></label>
                        <textarea name="reason" id="reason" class="form-control" rows="3" required
                                  placeholder="e.g. Employee was absent, sick, attended a family event…"><?= e($_POST['reason'] ?? '') ?></textarea>
                    </div>

                    <div class="alert alert-info py-2 small mb-4">
                        <i data-lucide="info" class="me-1" style="width:14px;height:14px"></i>
                        This leave will be recorded as <strong>Approved</strong> immediately and deducted from the employee's balance.
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i data-lucide="check" class="me-1" style="width:14px;height:14px"></i>Record Leave
                        </button>
                        <a href="/admin/leave" class="btn btn-soft">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
