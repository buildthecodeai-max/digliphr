<?php
/** @var array $leaveRequest */
/** @var array $leaveTypes */
/** @var array $employees */
$lr = $leaveRequest;
$isApproved = ($lr['status'] ?? '') === 'approved';
?>
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Edit Leave #<?= (int) $lr['id'] ?></h1>
        <a href="/admin/leave/<?= (int) $lr['id'] ?>" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>
    <?php include config('app.paths.views') . '/partials/alerts.php'; ?>
    <?php if ($isApproved): ?>
    <div class="alert alert-warning small">This leave is approved. Saving creates an amendment record and recalculates balances/attendance.</div>
    <?php endif; ?>
    <div class="card">
        <div class="card-body">
            <form method="post" action="/admin/leave/<?= (int) $lr['id'] ?>/update" class="row g-3">
                <?= csrf_field() ?>
                <div class="col-md-4">
                    <label class="form-label" for="leave_type_id">Leave type</label>
                    <select name="leave_type_id" class="form-select" required id="leave_type_id">
                        <?php foreach ($leaveTypes as $lt): ?>
                        <option value="<?= (int) $lt['id'] ?>" <?= (int) $lr['leave_type_id'] === (int) $lt['id'] ? 'selected' : '' ?>><?= e($lt['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="start_date">Start date</label>
                    <input type="date" name="start_date" class="form-control" value="<?= e($lr['start_date']) ?>" required id="start_date">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="end_date">End date</label>
                    <input type="date" name="end_date" class="form-control" value="<?= e($lr['end_date']) ?>" required id="end_date">
                </div>
                <div class="col-md-3">
                    <div class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" name="is_half_day" value="1" id="halfDay" <?= !empty($lr['is_half_day']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="halfDay">Half day</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="half_day_type">Half-day option</label>
                    <select name="half_day_type" class="form-select" id="half_day_type">
                        <option value="first_half" <?= ($lr['half_day_type'] ?? '') === 'first_half' ? 'selected' : '' ?>>First half</option>
                        <option value="second_half" <?= ($lr['half_day_type'] ?? '') === 'second_half' ? 'selected' : '' ?>>Second half</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="handover_employee_id">Handover employee</label>
                    <select name="handover_employee_id" class="form-select" id="handover_employee_id">
                        <option value="">None</option>
                        <?php foreach ($employees as $emp): ?>
                        <option value="<?= (int) $emp['id'] ?>" <?= (int) ($lr['handover_employee_id'] ?? 0) === (int) $emp['id'] ? 'selected' : '' ?>>
                            <?= e(trim($emp['first_name'] . ' ' . $emp['last_name'])) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label" for="reason">Reason</label>
                    <textarea name="reason" class="form-control" rows="3" required id="reason"><?= e($lr['reason']) ?></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label" for="handover_notes">Handover notes</label>
                    <textarea name="handover_notes" class="form-control" rows="2" id="handover_notes"><?= e($lr['handover_notes'] ?? '') ?></textarea>
                </div>
                <?php if ($isApproved): ?>
                <div class="col-12">
                    <label class="form-label" for="amendment_reason">Amendment reason <span class="text-danger">*</span></label>
                    <textarea name="amendment_reason" class="form-control" rows="2" required id="amendment_reason"></textarea>
                </div>
                <?php endif; ?>
                <div class="col-12">
                    <button class="btn btn-primary">Save changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
