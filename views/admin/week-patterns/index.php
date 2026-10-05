<?php
/** @var array $patterns */
/** @var array $companies */
/** @var int|null $companyId */
$dayLabels = ['monday_type' => 'Mon', 'tuesday_type' => 'Tue', 'wednesday_type' => 'Wed', 'thursday_type' => 'Thu', 'friday_type' => 'Fri', 'saturday_type' => 'Sat', 'sunday_type' => 'Sun'];
$typeBadge = static function (string $type): string {
    return match ($type) {
        'full' => '<span class="badge bg-success">Full</span>',
        'half' => '<span class="badge bg-warning">Half</span>',
        default => '<span class="badge bg-secondary">Off</span>',
    };
};
?>
<div class="page-header">
    <div>
        <h1>Weekly Schedule Patterns</h1>
        <p class="subtitle">Full/half/off per weekday — assigned to departments as team overrides, or to the company's manager tier via each designation's "Manager or above" flag.</p>
    </div>
    <?php if (can('attendance.pattern.manage')): ?>
        <a class="btn btn-primary btn-sm" href="/admin/week-patterns/create<?= $companyId ? '?company_id=' . $companyId : '' ?>"><i data-lucide="plus" class="me-1" style="width:14px;height:14px"></i>Add Pattern</a>
    <?php endif; ?>
</div>

<?php if (count($companies) > 1): ?>
<form method="get" class="mb-3">
    <select name="company_id" class="form-select form-select-sm" style="max-width:280px" onchange="this.form.submit()">
        <?php foreach ($companies as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= $companyId === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
    </select>
</form>
<?php endif; ?>

<div class="card ems-card">
    <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
            <thead><tr><th>Name</th><th>Flags</th><?php foreach ($dayLabels as $lbl): ?><th class="text-center"><?= $lbl ?></th><?php endforeach; ?><th></th></tr></thead>
            <tbody>
            <?php if (!$patterns): ?>
                <tr><td colspan="10"><div class="empty-state mb-0 py-4">No weekly patterns configured for this company yet.</div></td></tr>
            <?php else: foreach ($patterns as $p): ?>
                <tr>
                    <td class="fw-semibold"><?= e($p['name']) ?></td>
                    <td>
                        <?php if ($p['is_default']): ?><span class="badge bg-primary">Default</span><?php endif; ?>
                        <?php if ($p['is_manager_pattern']): ?><span class="badge bg-dark">Manager</span><?php endif; ?>
                    </td>
                    <?php foreach ($dayLabels as $key => $lbl): ?>
                        <td class="text-center"><?= $typeBadge($p[$key]) ?></td>
                    <?php endforeach; ?>
                    <td class="text-end text-nowrap">
                        <?php if (can('attendance.pattern.manage')): ?>
                        <a href="/admin/week-patterns/<?= (int) $p['id'] ?>/edit" class="btn btn-sm btn-soft">Edit</a>
                        <form method="post" action="/admin/week-patterns/<?= (int) $p['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Delete this pattern?');">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-danger">Delete</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>if (window.lucide) lucide.createIcons();</script>
