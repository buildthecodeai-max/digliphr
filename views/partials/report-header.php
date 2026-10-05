<?php
$exportType = $exportType ?? 'attendance';
$viewMode = $viewMode ?? 'table';
$qs = http_build_query(array_filter([
    'from' => $filters['from'] ?? null,
    'to' => $filters['to'] ?? null,
    'period' => $filters['period'] ?? null,
    'branch_id' => $filters['branch_id'] ?? null,
    'department_id' => $filters['department_id'] ?? null,
    'employee_id' => $filters['employee_id'] ?? null,
    'period_id' => $periodId ?? ($filters['period_id'] ?? null),
    'compare' => !empty($filters['compare']) ? 1 : null,
]));
?>
<?php
$periodLabel = '';
if (!empty($filters['from']) && strtotime($filters['from']) !== false) {
    $periodLabel = date('j M Y', strtotime($filters['from']));
    if (!empty($filters['to']) && strtotime($filters['to']) !== false) {
        $periodLabel .= ' — ' . date('j M Y', strtotime($filters['to']));
    }
}
?>
<div class="page-header">
    <div>
        <h1><?= e($title ?? 'Reports') ?></h1>
        <p class="subtitle">Analytics from live HR data<?= $periodLabel ? ' · ' . e($periodLabel) : '' ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <a class="btn btn-sm btn-primary" href="/admin/reports/builder"><i data-lucide="plus" class="me-1" style="width:14px;height:14px"></i>Create report</a>
        <div class="btn-group segmented-control" role="group" aria-label="View mode">
            <?php foreach (['graphical' => 'Graphical', 'table' => 'Table', 'combined' => 'Combined'] as $mode => $label): ?>
                <a class="btn btn-sm <?= $viewMode === $mode ? 'active' : '' ?>"
                   href="?<?= e($qs . ($qs ? '&' : '') . 'view=' . $mode) ?>" aria-current="<?= $viewMode === $mode ? 'true' : 'false' ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </div>
        <?php if (can('reports.export')): ?>
            <a class="btn btn-sm btn-soft" href="/admin/reports/export/<?= e($exportType) ?>?<?= e($qs) ?>&format=csv"><i data-lucide="download" class="me-1" style="width:14px;height:14px"></i>CSV</a>
            <button type="button" class="btn btn-sm btn-soft" id="exportPdfBtn"><i data-lucide="file-text" class="me-1" style="width:14px;height:14px"></i>PDF</button>
            <button type="button" class="btn btn-sm btn-soft" onclick="window.print()"><i data-lucide="printer" class="me-1" style="width:14px;height:14px"></i>Print</button>
        <?php endif; ?>
    </div>
</div>

<ul class="nav report-tabs mb-3">
    <li class="nav-item"><a class="nav-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/reports') && !preg_match('#/reports/(attendance|leave|payroll|employees|loans|documents|builder)#', $_SERVER['REQUEST_URI'] ?? '') ? 'active' : '' ?>" href="/admin/reports?<?= e($qs) ?>">Overview</a></li>
    <li class="nav-item"><a class="nav-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/reports/attendance') ? 'active' : '' ?>" href="/admin/reports/attendance?<?= e($qs) ?>">Attendance</a></li>
    <li class="nav-item"><a class="nav-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/reports/leave') ? 'active' : '' ?>" href="/admin/reports/leave?<?= e($qs) ?>">Leave</a></li>
    <li class="nav-item"><a class="nav-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/reports/payroll') ? 'active' : '' ?>" href="/admin/reports/payroll?<?= e($qs) ?>">Payroll</a></li>
    <li class="nav-item"><a class="nav-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/reports/loans') ? 'active' : '' ?>" href="/admin/reports/loans?<?= e($qs) ?>">Loans</a></li>
    <li class="nav-item"><a class="nav-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/reports/employees') ? 'active' : '' ?>" href="/admin/reports/employees?<?= e($qs) ?>">Workforce</a></li>
    <li class="nav-item"><a class="nav-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/reports/documents') ? 'active' : '' ?>" href="/admin/reports/documents?<?= e($qs) ?>">Documents</a></li>
</ul>
<!-- Hidden print-only header; shown via @media print -->
<div id="printHeader" style="display:none">
    <div>
        <div id="printHeader-logo">DIGLIP HR</div>
    </div>
    <div id="printHeader-meta">
        <div id="printHeader-title"><?= e($title ?? 'Report') ?></div>
        <div><?= e($periodLabel ?: date('d M Y')) ?></div>
        <div>Printed: <?= date('d M Y, g:i A') ?></div>
    </div>
</div>
<script>
(function () {
    var btn = document.getElementById('exportPdfBtn');
    if (!btn) return;
    btn.addEventListener('click', function () {
        document.title = <?= json_encode(($title ?? 'Report') . ($periodLabel ? ' · ' . $periodLabel : '')) ?>;
        window.print();
    });
})();
</script>
<script>if (window.lucide) lucide.createIcons();</script>
