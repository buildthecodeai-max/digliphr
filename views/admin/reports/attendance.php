<?php
$action = '/admin/reports/attendance';
$exportType = 'attendance';
$section = $section ?? 'overview';
$showShiftFilter = true;
include config('app.paths.views') . '/partials/report-header.php';
include config('app.paths.views') . '/partials/report-filters.php';

$sectionTabs = [
    'overview' => ['label' => 'Overview', 'icon' => 'layout-dashboard'],
    'employee_summary' => ['label' => 'Employee Summary', 'icon' => 'users'],
    'daily' => ['label' => 'Daily Attendance', 'icon' => 'calendar-days'],
    'monthly_sheet' => ['label' => 'Monthly Sheet', 'icon' => 'table'],
    'department' => ['label' => 'Department', 'icon' => 'building-2'],
    'branch' => ['label' => 'Branch', 'icon' => 'map-pin'],
    'shift' => ['label' => 'Shift', 'icon' => 'clock'],
    'absence' => ['label' => 'Absence', 'icon' => 'user-x'],
    'late' => ['label' => 'Late Arrivals', 'icon' => 'alarm-clock'],
    'early' => ['label' => 'Early Departures', 'icon' => 'log-out'],
    'overtime' => ['label' => 'Overtime', 'icon' => 'timer'],
    'missing' => ['label' => 'Missing / Exceptions', 'icon' => 'alert-triangle'],
    'corrections' => ['label' => 'Corrections', 'icon' => 'pencil-line'],
];
$sectionQs = http_build_query(array_filter([
    'from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null, 'period' => $filters['period'] ?? null,
    'branch_id' => $filters['branch_id'] ?? null, 'department_id' => $filters['department_id'] ?? null,
    'employee_id' => $filters['employee_id'] ?? null, 'view' => $viewMode ?? 'table',
]));

$primaryKeys  = ['overview', 'employee_summary', 'daily', 'monthly_sheet', 'department', 'branch', 'shift'];
$overflowKeys = ['absence', 'late', 'early', 'overtime', 'missing', 'corrections'];
$activeInOverflow = in_array($section, $overflowKeys, true);
?>
<div class="report-subtabs-scroll">
    <ul class="nav nav-pills report-subtabs">
        <?php foreach ($primaryKeys as $key):
            $tab = $sectionTabs[$key]; ?>
            <li class="nav-item">
                <a class="nav-link<?= $section === $key ? ' active' : '' ?>"
                   href="<?= e($action) ?>?section=<?= e($key) ?>&<?= e($sectionQs) ?>">
                    <i data-lucide="<?= e($tab['icon']) ?>"></i><?= e($tab['label']) ?>
                </a>
            </li>
        <?php endforeach; ?>

        <!-- More dropdown -->
        <li class="nav-item subtabs-more-wrap">
            <button class="nav-link subtabs-more-btn<?= $activeInOverflow ? ' active' : '' ?>"
                    type="button" id="subtabsMoreBtn" aria-haspopup="true" aria-expanded="false">
                <?php if ($activeInOverflow): ?>
                    <i data-lucide="<?= e($sectionTabs[$section]['icon']) ?>"></i><?= e($sectionTabs[$section]['label']) ?>
                <?php else: ?>
                    More
                <?php endif; ?>
                <i data-lucide="chevron-down" class="subtabs-more-caret"></i>
            </button>
            <ul class="subtabs-dropdown" id="subtabsDropdown" role="menu">
                <?php foreach ($overflowKeys as $key):
                    $tab = $sectionTabs[$key]; ?>
                    <li role="none">
                        <a class="subtabs-dropdown-item<?= $section === $key ? ' active' : '' ?>"
                           href="<?= e($action) ?>?section=<?= e($key) ?>&<?= e($sectionQs) ?>"
                           role="menuitem">
                            <i data-lucide="<?= e($tab['icon']) ?>"></i><?= e($tab['label']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </li>
    </ul>
</div>
<script>
(function () {
    var btn = document.getElementById('subtabsMoreBtn');
    var menu = document.getElementById('subtabsDropdown');
    if (!btn || !menu) return;
    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        var open = btn.getAttribute('aria-expanded') === 'true';
        btn.setAttribute('aria-expanded', open ? 'false' : 'true');
        menu.classList.toggle('is-open', !open);
    });
    document.addEventListener('click', function () {
        btn.setAttribute('aria-expanded', 'false');
        menu.classList.remove('is-open');
    });
    if (window.lucide) lucide.createIcons();
})();
</script>

<?php
$partials = config('app.paths.views') . '/admin/reports/attendance/';
$partialMap = [
    'overview' => 'overview.php',
    'employee_summary' => 'employee-summary.php',
    'employee_detail' => 'employee-detail.php',
    'daily' => 'daily.php',
    'monthly_sheet' => 'monthly-sheet.php',
    'department' => 'group.php',
    'branch' => 'group.php',
    'shift' => 'group.php',
    'absence' => 'absence.php',
    'late' => 'late.php',
    'early' => 'early.php',
    'overtime' => 'overtime.php',
    'missing' => 'missing.php',
    'corrections' => 'corrections.php',
];
$partial = $partialMap[$section] ?? 'overview.php';
include $partials . $partial;
?>
