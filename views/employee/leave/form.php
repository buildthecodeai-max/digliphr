<div class="page-header">
    <div>
        <h1>Apply for Leave</h1>
        <p class="subtitle">Submit a new leave request for approval</p>
    </div>
    <div>
        <a href="/employee/leave" class="btn btn-sm btn-soft">
            <i data-lucide="arrow-left" class="me-1" style="width:14px;height:14px"></i>Back
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card ems-card">
            <div class="card-header">Leave Request</div>
            <div class="card-body">
                <form method="POST" action="/employee/leave" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="leave_type_id">Leave Type <span class="text-danger">*</span></label>
                            <select name="leave_type_id" class="form-select form-select-sm" required id="leave_type_id">
                                <option value="">Select type</option>
                                <?php foreach ($leaveTypes as $lt): ?>
                                    <option value="<?= (int) $lt['id'] ?>" <?= (string) old('leave_type_id') === (string) $lt['id'] ? 'selected' : '' ?>>
                                        <?= e($lt['name']) ?><?= !empty($lt['is_paid']) ? '' : ' (Unpaid)' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="start_date">Start Date <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" class="form-control form-control-sm" value="<?= e((string) old('start_date')) ?>" required id="start_date">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="end_date">End Date <span class="text-danger">*</span></label>
                            <input type="date" name="end_date" class="form-control form-control-sm" value="<?= e((string) old('end_date')) ?>" required id="end_date">
                        </div>
                        <div class="col-md-4">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" name="is_half_day" value="1" id="halfDay" <?= old('is_half_day') ? 'checked' : '' ?>>
                                <label class="form-check-label" for="halfDay">Half day leave</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="half_day_type">Half Day Type</label>
                            <select name="half_day_type" class="form-select form-select-sm" id="half_day_type">
                                <option value="">—</option>
                                <option value="first_half">First Half</option>
                                <option value="second_half">Second Half</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="contact_during_leave">Contact During Leave</label>
                            <input type="text" name="contact_during_leave" class="form-control form-control-sm" value="<?= e((string) old('contact_during_leave')) ?>" id="contact_during_leave">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="reason">Reason <span class="text-danger">*</span></label>
                            <textarea name="reason" class="form-control form-control-sm" rows="3" required minlength="5" id="reason"><?= e((string) old('reason')) ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="emergency_contact">Emergency Contact</label>
                            <input type="text" name="emergency_contact" class="form-control form-control-sm" value="<?= e((string) old('emergency_contact')) ?>" id="emergency_contact">
                        </div>
                    </div>

                    <?php if (!empty($balances)): ?>
                    <div class="alert alert-light border mt-3 mb-0 small">
                        <strong>Your balances:</strong>
                        <?php foreach ($balances as $b): ?>
                            <span class="me-3"><?= e($b['leave_type_name'] ?? '') ?>: <?= e((string) $b['closing_balance']) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <div class="d-flex gap-2 mt-3">
                        <button class="btn btn-primary btn-sm">Submit Request</button>
                        <a href="/employee/leave" class="btn btn-soft btn-sm">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
