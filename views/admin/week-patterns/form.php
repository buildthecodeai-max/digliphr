<?php
/** @var array|null $pattern */
$isEdit = !empty($pattern);
$days = [
    'monday_type' => 'Monday', 'tuesday_type' => 'Tuesday', 'wednesday_type' => 'Wednesday',
    'thursday_type' => 'Thursday', 'friday_type' => 'Friday', 'saturday_type' => 'Saturday', 'sunday_type' => 'Sunday',
];
?>
<div class="card ems-card"><div class="card-body">
<form method="POST" action="<?= $isEdit ? '/admin/week-patterns/' . (int) $pattern['id'] : '/admin/week-patterns' ?>">
    <?= csrf_field() ?>
    <div class="row g-2 mb-3">
        <?php if (!$isEdit): ?>
        <div class="col-md-4">
            <label class="form-label" for="company_id">Company *</label>
            <select name="company_id" class="form-select form-select-sm" required id="company_id">
                <?php foreach ($companies as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= (int) old('company_id', $_GET['company_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="col-md-6">
            <label class="form-label" for="name">Name *</label>
            <input type="text" name="name" class="form-control form-control-sm" required id="name"
                   value="<?= e(old('name', $pattern['name'] ?? '')) ?>" placeholder="e.g. Result &amp; Data Entry Team">
        </div>
    </div>

    <div class="mb-3">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_default" value="1" id="is_default" <?= old('is_default', $pattern['is_default'] ?? 0) ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_default">Company default pattern</label>
            <div class="form-text">Used for any employee whose department has no pattern assigned and who is not a manager. Setting this clears the default flag from any other pattern in this company.</div>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_manager_pattern" value="1" id="is_manager_pattern" <?= old('is_manager_pattern', $pattern['is_manager_pattern'] ?? 0) ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_manager_pattern">Manager pattern</label>
            <div class="form-text">Used for any employee whose designation is flagged "Manager or above", regardless of department. Setting this clears the flag from any other pattern in this company.</div>
        </div>
    </div>

    <label class="form-label small fw-semibold">Weekly schedule</label>
    <div class="table-responsive mb-3">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Day</th><th>Full</th><th>Half</th><th>Off</th></tr></thead>
            <tbody>
            <?php foreach ($days as $key => $label): $current = old($key, $pattern[$key] ?? ($key === 'saturday_type' ? 'half' : ($key === 'sunday_type' ? 'off' : 'full'))); ?>
                <tr>
                    <td><?= e($label) ?></td>
                    <?php foreach (['full', 'half', 'off'] as $type): ?>
                        <td>
                            <input type="radio" name="<?= $key ?>" value="<?= $type ?>" class="form-check-input" <?= $current === $type ? 'checked' : '' ?>>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <button type="submit" class="btn btn-primary btn-sm">Save</button>
    <a href="/admin/week-patterns<?= $isEdit ? '?company_id=' . (int) $pattern['company_id'] : '' ?>" class="btn btn-secondary btn-sm">Cancel</a>
</form>
</div></div>
