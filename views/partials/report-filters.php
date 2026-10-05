<?php
/** Shared report filter bar */
$filters = $filters ?? [];
$options = $options ?? [];
$action = $action ?? '/admin/reports';
$viewMode = $viewMode ?? 'table';
$showPeriod = !empty($showPeriod);
?>
<form class="filter-bar" method="get" action="<?= e($action) ?>" id="reportFilterForm">
    <?php
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
    if (!empty($filters['compare'])) { $activeChips[] = 'Compare prior period'; }
    if ($activeChips): ?>
    <div class="d-flex flex-wrap gap-2 mb-2">
        <?php foreach ($activeChips as $chip): ?><span class="filter-chip"><?= e($chip) ?></span><?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="row g-2 align-items-end">
        <div class="col-12 col-md-2">
            <label class="form-label small mb-1" for="period">Reporting Period</label>
            <select name="period" class="form-select form-select-sm" id="period">
                <?php foreach (['this_month' => 'This month', 'last_month' => 'Last month', 'this_quarter' => 'This quarter', 'last_quarter' => 'Last quarter', 'this_year' => 'This year', 'last_year' => 'Last year', 'custom' => 'Custom range'] as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= ($filters['period'] ?? 'this_month') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1" for="from">From</label>
            <input type="date" name="from" class="form-control form-control-sm" value="<?= e($filters['from'] ?? date('Y-m-01')) ?>" id="from">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1" for="to">To</label>
            <input type="date" name="to" class="form-control form-control-sm" value="<?= e($filters['to'] ?? date('Y-m-d')) ?>" id="to">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1" for="branch_id">Branch</label>
            <select name="branch_id" class="form-select form-select-sm" id="branch_id">
                <option value="">All</option>
                <?php foreach (($options['branches'] ?? []) as $b): ?>
                    <option value="<?= (int) $b['id'] ?>" <?= (int) ($filters['branch_id'] ?? 0) === (int) $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1" for="department_id">Department</label>
            <select name="department_id" class="form-select form-select-sm" id="department_id">
                <option value="">All</option>
                <?php foreach (($options['departments'] ?? []) as $d): ?>
                    <option value="<?= (int) $d['id'] ?>" <?= (int) ($filters['department_id'] ?? 0) === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if (!empty($showShiftFilter)): ?>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1" for="shift_id">Shift</label>
            <select name="shift_id" class="form-select form-select-sm" id="shift_id">
                <option value="">All</option>
                <?php foreach (($options['shifts'] ?? []) as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= (int) ($filters['shift_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <?php if ($showPeriod): ?>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1" for="period_id">Payroll Period</label>
            <select name="period_id" class="form-select form-select-sm" id="period_id">
                <option value="">Latest</option>
                <?php foreach (($options['periods'] ?? []) as $p): ?>
                    <option value="<?= (int) $p['id'] ?>" <?= (int) ($periodId ?? 0) === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <?php
        $selectedEmployeeName = '';
        foreach (($options['employees'] ?? []) as $emp) {
            if ((int) $emp['id'] === (int) ($filters['employee_id'] ?? 0)) {
                $selectedEmployeeName = $emp['name'];
                break;
            }
        }
        ?>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1" for="employee_search">Employee</label>
            <input type="text" class="form-control form-control-sm" id="employee_search"
                   list="employee_options" placeholder="All" autocomplete="off"
                   value="<?= e($selectedEmployeeName) ?>">
            <input type="hidden" name="employee_id" id="employee_id" value="<?= e((string) ($filters['employee_id'] ?? '')) ?>">
            <datalist id="employee_options">
                <?php foreach (($options['employees'] ?? []) as $emp): ?>
                    <option data-id="<?= (int) $emp['id'] ?>" value="<?= e($emp['name']) ?>"></option>
                <?php endforeach; ?>
            </datalist>
        </div>
        <div class="col-12 col-md-auto d-flex flex-wrap gap-2">
            <input type="hidden" name="view" value="<?= e($viewMode) ?>">
            <label class="btn btn-soft btn-sm mb-0">
                <input type="checkbox" name="compare" value="1" class="form-check-input me-1" <?= !empty($filters['compare']) ? 'checked' : '' ?>> Compare
            </label>
            <button class="btn btn-primary btn-sm" type="submit" data-ds-loading><i class="bi bi-funnel me-1"></i>Apply</button>
            <a class="btn btn-ghost btn-sm" href="<?= e($action) ?>">Reset</a>
        </div>
    </div>
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
