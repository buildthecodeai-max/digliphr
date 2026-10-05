<?php $e = $employee; ?>
<div class="page-header">
    <div>
        <h1>My Profile</h1>
        <p class="subtitle">Employment details and contact information</p>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card ems-card">
            <div class="card-header">Employment Information</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4"><div class="text-muted small">Employee Code</div><div class="fw-semibold"><?= e($e['employee_code']) ?></div></div>
                    <div class="col-md-4"><div class="text-muted small">Name</div><div class="fw-semibold"><?= e(trim(($e['first_name'] ?? '') . ' ' . ($e['last_name'] ?? ''))) ?></div></div>
                    <div class="col-md-4"><div class="text-muted small">Status</div><div><?= status_badge($e['employment_status'] ?? 'active') ?></div></div>
                    <div class="col-md-4"><div class="text-muted small">Department</div><div><?= e($e['department_name'] ?? '—') ?></div></div>
                    <div class="col-md-4"><div class="text-muted small">Designation</div><div><?= e($e['designation_name'] ?? '—') ?></div></div>
                    <div class="col-md-4"><div class="text-muted small">Branch</div><div><?= e($e['branch_name'] ?? '—') ?></div></div>
                    <div class="col-md-4"><div class="text-muted small">Shift</div><div><?= e($e['shift_name'] ?? '—') ?></div></div>
                    <div class="col-md-4"><div class="text-muted small">Joining Date</div><div><?= e(format_date($e['joining_date'] ?? null)) ?></div></div>
                    <div class="col-md-4"><div class="text-muted small">Company Email</div><div><?= e($e['company_email'] ?? '—') ?></div></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card ems-card">
            <div class="card-header">Update Contact Info</div>
            <div class="card-body">
                <form method="POST" action="/employee/profile">
                    <?= csrf_field() ?>
                    <div class="mb-2"><label class="form-label small" for="phone">Phone</label><input name="phone" class="form-control form-control-sm" value="<?= e($e['phone'] ?? '') ?>" id="phone"></div>
                    <div class="mb-2"><label class="form-label small" for="alternate_phone">Alternate Phone</label><input name="alternate_phone" class="form-control form-control-sm" value="<?= e($e['alternate_phone'] ?? '') ?>" id="alternate_phone"></div>
                    <div class="mb-2"><label class="form-label small" for="personal_email">Personal Email</label><input type="email" name="personal_email" class="form-control form-control-sm" value="<?= e($e['personal_email'] ?? '') ?>" id="personal_email"></div>
                    <div class="mb-2"><label class="form-label small" for="current_address">Current Address</label><textarea name="current_address" class="form-control form-control-sm" rows="2" id="current_address"><?= e($e['current_address'] ?? '') ?></textarea></div>
                    <div class="mb-2"><label class="form-label small" for="permanent_address">Permanent Address</label><textarea name="permanent_address" class="form-control form-control-sm" rows="2" id="permanent_address"><?= e($e['permanent_address'] ?? '') ?></textarea></div>
                    <button class="btn btn-primary btn-sm w-100">Save</button>
                </form>
            </div>
        </div>
    </div>
</div>
