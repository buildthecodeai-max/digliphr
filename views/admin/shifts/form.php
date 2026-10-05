<?php $s = $shift ?? []; $edit = !empty($s); ?>
<div class="row g-3">
<div class="col-lg-8">
<div class="card"><div class="card-body">
<form method="POST" action="<?= $edit ? '/admin/shifts/' . (int)$s['id'] : '/admin/shifts' ?>">
<?= csrf_field() ?>
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="company_id">Company</label>
        <select name="company_id" class="form-select" required id="company_id">
            <?php foreach ($companies as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= (int)($s['company_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-6"><label class="form-label" for="name">Name</label><input name="name" class="form-control" value="<?= e($s['name'] ?? '') ?>" required id="name"></div>
    <div class="col-md-4"><label class="form-label" for="code">Code</label><input name="code" class="form-control" value="<?= e($s['code'] ?? '') ?>" id="code"></div>
    <div class="col-md-4"><label class="form-label" for="start_time">Start</label><input type="time" name="start_time" class="form-control" value="<?= e(substr((string)($s['start_time'] ?? '09:00'), 0, 5)) ?>" required id="start_time"></div>
    <div class="col-md-4"><label class="form-label" for="end_time">End</label><input type="time" name="end_time" class="form-control" value="<?= e(substr((string)($s['end_time'] ?? '18:00'), 0, 5)) ?>" required id="end_time"></div>
    <div class="col-md-3"><label class="form-label" for="grace_minutes">Grace (min)</label><input type="number" name="grace_minutes" class="form-control" value="<?= e((string)($s['grace_minutes'] ?? 15)) ?>" id="grace_minutes"></div>
    <div class="col-md-3"><label class="form-label" for="break_minutes">Break (min)</label><input type="number" name="break_minutes" class="form-control" value="<?= e((string)($s['break_minutes'] ?? 60)) ?>" id="break_minutes"></div>
    <div class="col-md-3"><label class="form-label" for="late_mark_after_minutes">Late after (min)</label><input type="number" name="late_mark_after_minutes" class="form-control" value="<?= e((string)($s['late_mark_after_minutes'] ?? 15)) ?>" id="late_mark_after_minutes"></div>
    <div class="col-md-3"><label class="form-label" for="overtime_after_minutes">OT after (min)</label><input type="number" name="overtime_after_minutes" class="form-control" value="<?= e((string)($s['overtime_after_minutes'] ?? 0)) ?>" id="overtime_after_minutes"></div>
    <div class="col-md-4"><label class="form-label" for="half_day_after_minutes">Half-day after (min)</label><input type="number" name="half_day_after_minutes" class="form-control" value="<?= e((string)($s['half_day_after_minutes'] ?? '')) ?>" id="half_day_after_minutes"></div>
    <div class="col-md-4"><label class="form-label" for="early_leave_grace_minutes">Early leave grace</label><input type="number" name="early_leave_grace_minutes" class="form-control" value="<?= e((string)($s['early_leave_grace_minutes'] ?? 0)) ?>" id="early_leave_grace_minutes"></div>
    <div class="col-md-4"><label class="form-label" for="expected_work_minutes">Expected work (min)</label><input type="number" name="expected_work_minutes" class="form-control" value="<?= e((string)($s['expected_work_minutes'] ?? 480)) ?>" id="expected_work_minutes"></div>
    <div class="col-12"><label class="form-label" for="description">Description</label><textarea name="description" class="form-control" rows="2" id="description"><?= e($s['description'] ?? '') ?></textarea></div>
    <div class="col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_overnight" value="1" id="on" <?= !empty($s['is_overnight']) ? 'checked' : '' ?>><label class="form-check-label" for="on">Overnight</label></div></div>
    <div class="col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_flexible" value="1" id="fl" <?= !empty($s['is_flexible']) ? 'checked' : '' ?>><label class="form-check-label" for="fl">Flexible</label></div></div>
    <div class="col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="ac" <?= !isset($s['is_active']) || !empty($s['is_active']) ? 'checked' : '' ?>><label class="form-check-label" for="ac">Active</label></div></div>
</div>
<div class="mt-3 d-flex gap-2">
    <button class="btn btn-primary"><?= $edit ? 'Update' : 'Create' ?></button>
    <a href="/admin/shifts" class="btn btn-light">Cancel</a>
    <?php if ($edit && can('shifts.delete')): ?>
    <button formaction="/admin/shifts/<?= (int)$s['id'] ?>/delete" class="btn btn-outline-danger ms-auto" onclick="return confirm('Delete this shift?')">Delete</button>
    <?php endif; ?>
</div>
</form>
</div></div>
</div>

<?php if ($edit && !empty($employees) && can('shifts.assign')): ?>
<div class="col-lg-4">
<div class="card">
<div class="card-header py-2 fw-semibold">Assign to Employee</div>
<div class="card-body">
<form method="POST" action="/admin/shifts/<?= (int)$s['id'] ?>/assign">
<?= csrf_field() ?>
<div class="mb-2">
    <label class="form-label small" for="employee_id">Employee</label>
    <select name="employee_id" class="form-select form-select-sm" required id="employee_id">
        <option value="">Select…</option>
        <?php foreach ($employees as $emp): ?>
            <option value="<?= (int)$emp['id'] ?>"><?= e($emp['name']) ?> (<?= e($emp['employee_code']) ?>)</option>
        <?php endforeach; ?>
    </select>
</div>
<div class="mb-2"><label class="form-label small" for="effective_from">Effective from</label><input type="date" name="effective_from" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required id="effective_from"></div>
<div class="mb-2"><label class="form-label small" for="effective_to">Effective to</label><input type="date" name="effective_to" class="form-control form-control-sm" id="effective_to"></div>
<button class="btn btn-secondary btn-sm w-100">Assign</button>
</form>
</div></div>
</div>
<?php endif; ?>
</div>
