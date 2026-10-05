<?php
$isEdit = !empty($employee);
$shifts = $shifts ?? [];
?>
<div class="container-fluid py-3">
    <div class="mb-3">
        <h1 class="h4 mb-1"><?= e($title ?? ($isEdit ? 'Edit Employee' : 'Add Employee')) ?></h1>
        <p class="text-muted small mb-0"><?= $isEdit ? 'Update employee profile details' : 'Create a new employee record' ?></p>
    </div>

    <?php include config('app.paths.views') . '/partials/alerts.php'; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="<?= $isEdit ? '/admin/employees/' . (int) $employee['id'] : '/admin/employees' ?>">
                <?= csrf_field() ?>
                <div class="row g-2">
                    <div class="col-md-3">
                        <label class="form-label" for="company_id">Company *</label>
                        <select name="company_id" class="form-select form-select-sm" required id="company_id">
                            <?php foreach ($companies as $c): ?>
                                <option value="<?= (int) $c['id'] ?>" <?= (int) old('company_id', $employee['company_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="branch_id">Branch</label>
                        <select name="branch_id" class="form-select form-select-sm" id="branch_id">
                            <option value="">—</option>
                            <?php foreach ($branches as $b): ?>
                                <option value="<?= (int) $b['id'] ?>" <?= (int) old('branch_id', $employee['branch_id'] ?? 0) === (int) $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="department_id">Department</label>
                        <select name="department_id" class="form-select form-select-sm" id="department_id">
                            <option value="">—</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= (int) $d['id'] ?>" <?= (int) old('department_id', $employee['department_id'] ?? 0) === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="designation_id">Designation</label>
                        <select name="designation_id" class="form-select form-select-sm" id="designation_id">
                            <option value="">—</option>
                            <?php foreach ($designations as $d): ?>
                                <option value="<?= (int) $d['id'] ?>" <?= (int) old('designation_id', $employee['designation_id'] ?? 0) === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label" for="employee_code">Employee Code</label>
                        <input type="text" name="employee_code" class="form-control form-control-sm" value="<?= e(old('employee_code', $employee['employee_code'] ?? '')) ?>" placeholder="Auto" id="employee_code">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="first_name">First Name *</label>
                        <input type="text" name="first_name" class="form-control form-control-sm" value="<?= e(old('first_name', $employee['first_name'] ?? '')) ?>" required id="first_name">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="last_name">Last Name *</label>
                        <input type="text" name="last_name" class="form-control form-control-sm" value="<?= e(old('last_name', $employee['last_name'] ?? '')) ?>" required id="last_name">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="gender">Gender</label>
                        <select name="gender" class="form-select form-select-sm" id="gender">
                            <option value="">—</option>
                            <?php foreach (['male', 'female', 'other'] as $g): ?>
                                <option value="<?= $g ?>" <?= old('gender', $employee['gender'] ?? '') === $g ? 'selected' : '' ?>><?= ucfirst($g) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="date_of_birth">Date of Birth</label>
                        <input type="date" name="date_of_birth" class="form-control form-control-sm" value="<?= e(old('date_of_birth', $employee['date_of_birth'] ?? '')) ?>" id="date_of_birth">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="national_id">CNIC / ID Card Number</label>
                        <input type="text" name="national_id" class="form-control form-control-sm" value="<?= e(old('national_id', $employee['national_id'] ?? '')) ?>" maxlength="100" autocomplete="off" id="national_id">
                        <div class="form-text">Stored securely and must be unique within the company when provided.</div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="joining_date">Joining Date *</label>
                        <input type="date" name="joining_date" class="form-control form-control-sm" value="<?= e(old('joining_date', $employee['joining_date'] ?? '')) ?>" required id="joining_date">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="company_email">Company Email</label>
                        <input type="email" name="company_email" class="form-control form-control-sm" value="<?= e(old('company_email', $employee['company_email'] ?? '')) ?>" id="company_email">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="personal_email">Personal Email</label>
                        <input type="email" name="personal_email" class="form-control form-control-sm" value="<?= e(old('personal_email', $employee['personal_email'] ?? '')) ?>" id="personal_email">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="phone">Phone</label>
                        <input type="text" name="phone" class="form-control form-control-sm" value="<?= e(old('phone', $employee['phone'] ?? '')) ?>" id="phone">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="employment_type">Employment Type</label>
                        <select name="employment_type" class="form-select form-select-sm" id="employment_type">
                            <?php foreach (['full_time', 'part_time', 'contract', 'intern', 'temporary', 'consultant'] as $t): ?>
                                <option value="<?= $t ?>" <?= old('employment_type', $employee['employment_type'] ?? 'full_time') === $t ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $t)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Controls contract expectations and eligibility reporting; it does not automatically change payroll.</div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="employment_status">Status</label>
                        <select name="employment_status" class="form-select form-select-sm" id="employment_status">
                            <?php foreach (['active', 'probation', 'notice_period', 'suspended', 'terminated', 'resigned', 'retired', 'inactive'] as $s): ?>
                                <option value="<?= $s ?>" <?= old('employment_status', $employee['employment_status'] ?? 'active') === $s ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $s)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="reporting_manager_id">Reporting Manager</label>
                        <select name="reporting_manager_id" class="form-select form-select-sm" id="reporting_manager_id">
                            <option value="">—</option>
                            <?php foreach ($managers as $m): ?>
                                <?php if ($isEdit && (int) $m['id'] === (int) $employee['id']) {
                                    continue;
                                } ?>
                                <option value="<?= (int) $m['id'] ?>" <?= (int) old('reporting_manager_id', $employee['reporting_manager_id'] ?? 0) === (int) $m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Used by manager approval-chain steps and lifecycle handovers.</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="shift_id">Shift</label>
                        <select name="shift_id" class="form-select form-select-sm" id="shift_id">
                            <option value="">—</option>
                            <?php foreach ($shifts as $shift): ?>
                                <option value="<?= (int) $shift['id'] ?>" <?= (int) old('shift_id', $employee['shift_id'] ?? 0) === (int) $shift['id'] ? 'selected' : '' ?>>
                                    <?= e($shift['name'] . (!empty($shift['code']) ? ' (' . $shift['code'] . ')' : '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="basic_salary">Basic Salary</label>
                        <input type="number" step="0.01" name="basic_salary" class="form-control form-control-sm" value="<?= e((string) old('basic_salary', $employee['basic_salary'] ?? 0)) ?>" id="basic_salary">
                        <div class="form-text">Base contractual salary before earnings, overtime, tax, loans, and other deductions.</div>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label" for="current_address">Current Address</label>
                        <textarea name="current_address" class="form-control form-control-sm" rows="2" id="current_address"><?= e(old('current_address', $employee['current_address'] ?? '')) ?></textarea>
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="remote_attendance_allowed" value="1" <?= old('remote_attendance_allowed', $employee['remote_attendance_allowed'] ?? 0) ? 'checked' : '' ?> id="remote_attendance_allowed">
                            <label class="form-check-label" for="remote_attendance_allowed">Remote Attendance Allowed</label>
                        </div>
                        <div class="form-text">Allows remote check-in while still recording GPS accuracy and verification flags.</div>
                    </div>
                </div>
                <div class="mt-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">Save</button>
                    <a href="<?= $isEdit ? '/admin/employees/' . (int) $employee['id'] : '/admin/employees' ?>" class="btn btn-secondary btn-sm">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
