<?php
/** Shared report filter bar */
$filters = $filters ?? [];
$options = $options ?? [];
$action = $action ?? '/admin/reports';
$viewMode = $viewMode ?? 'table';
$showPeriod = !empty($showPeriod);
?>
<?php
$selectedEmployeeName = '';
foreach (($options['employees'] ?? []) as $emp) {
    if ((int) $emp['id'] === (int) ($filters['employee_id'] ?? 0)) {
        $selectedEmployeeName = $emp['name'];
        break;
    }
}
$activeChips = [];
if (!empty($filters['department_id'])) {
    foreach (($options['departments'] ?? []) as $d) {
        if ((int) $d['id'] === (int) $filters['department_id']) { $activeChips[] = 'Dept: ' . $d['name']; break; }
    }
}
if (!empty($filters['branch_id'])) {
    foreach (($options['branches'] ?? []) as $b) {
        if ((int) $b['id'] === (int) $filters['branch_id']) { $activeChips[] = 'Branch: ' . $b['name']; break; }
    }
}
if (!empty($filters['compare'])) { $activeChips[] = 'Compare'; }
?>
<form class="filter-bar filter-bar-compact" method="get" action="<?= e($action) ?>" id="reportFilterForm">
    <div class="filter-bar-inner">
        <!-- Period -->
        <div class="filter-field filter-field-period">
            <span class="filter-label">Period</span>
            <select name="period" class="form-select form-select-sm filter-select" id="period">
                <?php foreach (['this_month' => 'This month', 'last_month' => 'Last month', 'this_quarter' => 'This quarter', 'last_quarter' => 'Last quarter', 'this_year' => 'This year', 'last_year' => 'Last year', 'custom' => 'Custom range'] as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= ($filters['period'] ?? 'this_month') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-sep" aria-hidden="true"></div>

        <!-- Date range -->
        <div class="filter-field filter-field-dates" id="filterDateRange">
            <span class="filter-label">From</span>
            <input type="date" name="from" class="form-control form-control-sm filter-input-date" value="<?= e($filters['from'] ?? date('Y-m-01')) ?>" id="from" aria-label="From">
            <span class="filter-label-mid">—</span>
            <input type="date" name="to" class="form-control form-control-sm filter-input-date" value="<?= e($filters['to'] ?? date('Y-m-d')) ?>" id="to" aria-label="To">
        </div>

        <div class="filter-sep" aria-hidden="true"></div>

        <!-- Branch -->
        <div class="filter-field">
            <span class="filter-label">Branch</span>
            <select name="branch_id" class="form-select form-select-sm filter-select" id="branch_id">
                <option value="">All</option>
                <?php foreach (($options['branches'] ?? []) as $b): ?>
                    <option value="<?= (int) $b['id'] ?>" <?= (int) ($filters['branch_id'] ?? 0) === (int) $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Department -->
        <div class="filter-field">
            <span class="filter-label">Dept</span>
            <select name="department_id" class="form-select form-select-sm filter-select" id="department_id">
                <option value="">All</option>
                <?php foreach (($options['departments'] ?? []) as $d): ?>
                    <option value="<?= (int) $d['id'] ?>" <?= (int) ($filters['department_id'] ?? 0) === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if (!empty($showShiftFilter)): ?>
        <!-- Shift -->
        <div class="filter-field">
            <span class="filter-label">Shift</span>
            <select name="shift_id" class="form-select form-select-sm filter-select" id="shift_id">
                <option value="">All</option>
                <?php foreach (($options['shifts'] ?? []) as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= (int) ($filters['shift_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <?php if ($showPeriod): ?>
        <div class="filter-field">
            <span class="filter-label">Pay Period</span>
            <select name="period_id" class="form-select form-select-sm filter-select" id="period_id">
                <option value="">Latest</option>
                <?php foreach (($options['periods'] ?? []) as $p): ?>
                    <option value="<?= (int) $p['id'] ?>" <?= (int) ($periodId ?? 0) === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <!-- Employee -->
        <div class="filter-field filter-field-employee">
            <span class="filter-label">Employee</span>
            <input type="text" class="form-control form-control-sm filter-select" id="employee_search"
                   list="employee_options" placeholder="All employees" autocomplete="off"
                   value="<?= e($selectedEmployeeName) ?>">
            <input type="hidden" name="employee_id" id="employee_id" value="<?= e((string) ($filters['employee_id'] ?? '')) ?>">
            <datalist id="employee_options">
                <?php foreach (($options['employees'] ?? []) as $emp): ?>
                    <option data-id="<?= (int) $emp['id'] ?>" value="<?= e($emp['name']) ?>"></option>
                <?php endforeach; ?>
            </datalist>
        </div>

        <div class="filter-sep" aria-hidden="true"></div>

        <!-- Actions -->
        <div class="filter-actions">
            <input type="hidden" name="view" value="<?= e($viewMode) ?>">
            <label class="filter-compare-label">
                <input type="checkbox" name="compare" value="1" class="form-check-input" <?= !empty($filters['compare']) ? 'checked' : '' ?>>
                <span>Compare</span>
            </label>
            <button class="btn btn-primary btn-sm filter-apply-btn" type="submit" data-ds-loading>
                <i data-lucide="sliders-horizontal" style="width:13px;height:13px"></i> Apply
            </button>
            <a class="btn btn-ghost btn-sm filter-reset-btn" href="<?= e($action) ?>">Reset</a>
        </div>
    </div>
    <?php if ($activeChips): ?>
    <div class="filter-chips-row">
        <?php foreach ($activeChips as $chip): ?><span class="filter-chip"><?= e($chip) ?></span><?php endforeach; ?>
    </div>
    <?php endif; ?>
</form>
<script>
(function () {
    // Resolve the free-text employee search box back to a hidden employee_id
    // on selection/typing; native <datalist> so no extra dependency.
    var search = document.getElementById('employee_search');
    var hidden = document.getElementById('employee_id');
    var list = document.getElementById('employee_options');
    if (!search || !hidden || !list) return;
    var byName = {};
    Array.prototype.forEach.call(list.options, function (opt) {
        byName[opt.value] = opt.getAttribute('data-id');
    });
    search.addEventListener('input', function () {
        hidden.value = byName[search.value] || '';
    });

    var period = document.getElementById('period');
    var from = document.getElementById('from');
    var to = document.getElementById('to');
    if (period && from && to) {
        var toggleDates = function () {
            var custom = period.value === 'custom';
            from.disabled = !custom;
            to.disabled = !custom;
            from.closest('.col-6').style.opacity = custom ? '1' : '.6';
            to.closest('.col-6').style.opacity = custom ? '1' : '.6';
        };
        period.addEventListener('change', toggleDates);
        toggleDates();
    }
})();
</script>
