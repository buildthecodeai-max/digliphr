<?php
/** @var array $employees */
/** @var array $paginator */
/** @var array $filters */
/** @var array $departments */
/** @var array $branches */
/** @var array $companies */
/** @var array $statusCounts */

$statusCounts = $statusCounts ?? [];
$currentStatus = $filters['employment_status'] ?? '';

$avPalettes = [
    ['#DBEAFE','#1E40AF'],['#D1FAE5','#065F46'],['#FEF3C7','#92400E'],
    ['#FCE7F3','#9D174D'],['#EDE9FE','#5B21B6'],['#FFEDD5','#9A3412'],
    ['#CFFAFE','#164E63'],['#F0FDF4','#14532D'],['#FEE2E2','#7F1D1D'],
];
function pe_avatar(string $name, array $pal): array {
    $idx = 0;
    foreach (str_split($name) as $ch) { $idx = ($idx * 31 + ord($ch)) & 0x7fffffff; }
    [$bg, $fg] = $pal[$idx % count($pal)];
    $ini = implode('', array_map(fn($p) => strtoupper($p[0] ?? ''), array_slice(explode(' ', trim($name)), 0, 2)));
    return [$bg, $fg, $ini ?: '?'];
}

$baseFilters = array_filter(array_diff_key($filters ?? [], ['employment_status' => 1, 'page' => 1]));
$baseQuery   = $baseFilters ? '&' . http_build_query($baseFilters) : '';
?>
<style>
/* ── People / Employees directory ────────────────────────── */
.pe-stats{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px;margin-bottom:18px}
.pe-stat{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:13px 15px}
.pe-stat-n{font-size:26px;font-weight:700;line-height:1;font-variant-numeric:tabular-nums;margin-bottom:2px}
.pe-stat-l{font-size:11px;color:#6b7280;font-weight:500}
.pe-c-green .pe-stat-n{color:#16a34a}
.pe-c-amber .pe-stat-n{color:#d97706}
.pe-c-slate .pe-stat-n{color:#475569}
.pe-c-red   .pe-stat-n{color:#dc2626}

/* Filter card */
.pe-filter-card{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:12px 14px;margin-bottom:14px}
.pe-filter-card .form-control,.pe-filter-card .form-select{font-size:12.5px;border-radius:7px}
.pe-filter-card label{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;margin-bottom:3px;display:block}

/* Search wrap */
.pe-srch-wrap{position:relative}
.pe-srch-wrap input{padding-left:32px}
.pe-srch-ico{position:absolute;left:9px;top:50%;transform:translateY(-50%);color:#9ca3af;pointer-events:none;display:flex;align-items:center}

/* Status chips */
.pe-chips{display:flex;gap:5px;flex-wrap:wrap;margin-top:10px}
.pe-chip{padding:4px 10px;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:20px;font-size:12px;font-weight:500;text-decoration:none;color:#6b7280;transition:all .12s;white-space:nowrap;display:inline-flex;align-items:center;gap:5px}
.pe-chip:hover{border-color:#6366f1;color:#6366f1}
.pe-chip.is-active{background:rgba(99,102,241,.1);border-color:#6366f1;color:#6366f1;font-weight:600}
.pe-chip strong{font-variant-numeric:tabular-nums;font-size:11px;background:rgba(0,0,0,.08);border-radius:20px;padding:0 5px;margin-left:2px}
.pe-chip.is-active strong{background:rgba(99,102,241,.15)}

/* Directory header */
.pe-dir-header{display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:#f9fafb;border-bottom:1px solid #e5e7eb;border-radius:10px 10px 0 0}
.pe-dir-meta{font-size:12.5px;color:#6b7280}
.pe-dir-actions{display:flex;gap:8px;align-items:center}

/* Card grid */
.pe-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px;padding:14px}

/* Employee card */
.pe-card{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:16px 14px 12px;display:flex;flex-direction:column;gap:10px;position:relative;transition:box-shadow .12s,border-color .12s}
.pe-card:hover{box-shadow:0 4px 16px rgba(0,0,0,.07);border-color:#d1d5db}
.pe-card-chk{position:absolute;top:10px;left:10px}
.pe-card-chk input{cursor:pointer}
.pe-card-top{display:flex;flex-direction:column;align-items:center;text-align:center;gap:8px;padding-top:6px}
.pe-card-av{width:52px;height:52px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:800;flex-shrink:0;letter-spacing:-.5px}
.pe-card-name{font-weight:700;font-size:14px;line-height:1.25}
.pe-card-code{font-size:11.5px;color:#6b7280;font-variant-numeric:tabular-nums}
.pe-card-meta{font-size:12px;color:#6b7280;line-height:1.5;text-align:center}
.pe-card-dept{font-weight:600;color:#374151}

/* Status badge on card */
.pe-card-status{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600}
.pe-card-status-dot{width:5px;height:5px;border-radius:50%}
.pe-cs-active     {background:#DCFCE7;color:#15803D} .pe-cs-active      .pe-card-status-dot{background:#16A34A}
.pe-cs-probation  {background:#FFFBEB;color:#B45309} .pe-cs-probation   .pe-card-status-dot{background:#D97706}
.pe-cs-inactive   {background:#F3F4F6;color:#374151} .pe-cs-inactive    .pe-card-status-dot{background:#6B7280}
.pe-cs-terminated {background:#FEF2F2;color:#B91C1C} .pe-cs-terminated  .pe-card-status-dot{background:#DC2626}
.pe-cs-notice_period{background:#FEF3C7;color:#92400E} .pe-cs-notice_period .pe-card-status-dot{background:#D97706}
.pe-cs-resigned   {background:#FEF2F2;color:#B91C1C} .pe-cs-resigned    .pe-card-status-dot{background:#DC2626}
.pe-cs-suspended  {background:#FEF2F2;color:#B91C1C} .pe-cs-suspended   .pe-card-status-dot{background:#DC2626}
.pe-cs-retired    {background:#F3F4F6;color:#374151} .pe-cs-retired     .pe-card-status-dot{background:#6B7280}

/* Card footer actions */
.pe-card-acts{display:flex;gap:6px;border-top:1px solid #f3f4f6;padding-top:10px;margin-top:2px}
.pe-card-act{flex:1;display:flex;align-items:center;justify-content:center;gap:4px;padding:5px 8px;border-radius:7px;font-size:11.5px;font-weight:600;text-decoration:none;transition:all .12s;border:none;cursor:pointer;font-family:inherit}
.pe-card-act i[data-lucide]{width:12px;height:12px;stroke-width:2.5}
.pe-ca-view{background:#f3f4f6;color:#374151}
.pe-ca-view:hover{background:#e5e7eb;color:#111}
.pe-ca-edit{background:#FFFBEB;color:#92400E}
.pe-ca-edit:hover{background:#F59E0B;color:#fff}

/* Empty */
.pe-empty{padding:52px 20px;text-align:center}
.pe-empty i[data-lucide]{width:40px;height:40px;opacity:.2;display:block;margin:0 auto 12px}
.pe-empty h3{font-size:15px;font-weight:600;margin-bottom:4px}
.pe-empty p{font-size:13px;color:#6b7280}

/* ── Dark mode ─────────────────────────────────────────────── */
html[data-theme="dark"] .pe-stat{background:#0d1b2a;border-color:rgba(96,165,250,.13)}
html[data-theme="dark"] .pe-stat-l{color:#64748b}
html[data-theme="dark"] .pe-c-slate .pe-stat-n{color:#94a3b8}
html[data-theme="dark"] .pe-filter-card{background:#0d1b2a;border-color:rgba(96,165,250,.13)}
html[data-theme="dark"] .pe-filter-card label{color:#64748b}
html[data-theme="dark"] .pe-srch-ico{color:#64748b}
html[data-theme="dark"] .pe-chip{background:rgba(255,255,255,.05);border-color:rgba(96,165,250,.15);color:#94a3b8}
html[data-theme="dark"] .pe-chip:hover{border-color:rgba(32,212,131,.3);color:#20d483}
html[data-theme="dark"] .pe-chip.is-active{background:rgba(32,212,131,.12);border-color:rgba(32,212,131,.3);color:#20d483}
html[data-theme="dark"] .pe-chip strong{background:rgba(255,255,255,.08)}
html[data-theme="dark"] .pe-chip.is-active strong{background:rgba(32,212,131,.2)}
html[data-theme="dark"] .pe-dir-header{background:rgba(255,255,255,.03);border-color:rgba(96,165,250,.1)}
html[data-theme="dark"] .pe-dir-meta{color:#64748b}
html[data-theme="dark"] .pe-card{background:#0d1b2a;border-color:rgba(96,165,250,.13)}
html[data-theme="dark"] .pe-card:hover{box-shadow:0 4px 20px rgba(0,0,0,.35);border-color:rgba(96,165,250,.25)}
html[data-theme="dark"] .pe-card-code{color:#64748b}
html[data-theme="dark"] .pe-card-meta{color:#64748b}
html[data-theme="dark"] .pe-card-dept{color:#94a3b8}
html[data-theme="dark"] .pe-card-acts{border-top-color:rgba(96,165,250,.08)}
html[data-theme="dark"] .pe-ca-view{background:rgba(255,255,255,.06);color:#94a3b8}
html[data-theme="dark"] .pe-ca-view:hover{background:rgba(255,255,255,.1);color:#e2e8f0}
html[data-theme="dark"] .pe-ca-edit{background:rgba(217,119,6,.15);color:#fcd34d}
html[data-theme="dark"] .pe-ca-edit:hover{background:#d97706;color:#fff}
html[data-theme="dark"] .pe-cs-active    {background:rgba(22,163,74,.15);color:#86efac}
html[data-theme="dark"] .pe-cs-probation {background:rgba(217,119,6,.15);color:#fcd34d}
html[data-theme="dark"] .pe-cs-inactive  {background:rgba(255,255,255,.06);color:#94a3b8}
html[data-theme="dark"] .pe-cs-terminated,.pe-cs-resigned,.pe-cs-suspended{background:rgba(220,38,38,.15)!important;color:#fca5a5!important}
html[data-theme="dark"] .pe-cs-notice_period{background:rgba(217,119,6,.15);color:#fcd34d}
html[data-theme="dark"] .pe-cs-retired   {background:rgba(255,255,255,.06);color:#94a3b8}
html[data-theme="dark"] .pe-empty p{color:#64748b}

@media(max-width:640px){
    .pe-stats{grid-template-columns:1fr 1fr}
    .pe-grid{grid-template-columns:1fr 1fr;gap:10px;padding:10px}
    .pe-card-top{padding-top:4px}
}
@media(max-width:400px){
    .pe-grid{grid-template-columns:1fr}
}
</style>

<!-- Header -->
<div class="page-header d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h1>People</h1>
        <p class="subtitle">Your employee directory — search, view, and manage your team</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if (can('employees.create')): ?>
        <a href="/admin/employees/import<?= !empty($filters['company_id']) ? '?company_id=' . (int)$filters['company_id'] : '' ?>" class="btn btn-soft btn-sm">
            <i data-lucide="file-up" style="width:13px;height:13px" class="me-1"></i>Import
        </a>
        <a href="/admin/employees/create" class="btn btn-primary btn-sm">
            <i data-lucide="user-plus" style="width:13px;height:13px" class="me-1"></i>Add Employee
        </a>
        <?php endif; ?>
    </div>
</div>

<?php include config('app.paths.views') . '/partials/alerts.php'; ?>
<?php if (!empty($filters['company_id'])): $savedModule='employees'; $currentFilters=$filters; include config('app.paths.views') . '/partials/saved-filters.php'; endif; ?>

<!-- Stat tiles -->
<div class="pe-stats">
    <div class="pe-stat pe-c-green">
        <div class="pe-stat-n"><?= number_format($statusCounts['active'] ?? 0) ?></div>
        <div class="pe-stat-l">Active</div>
    </div>
    <div class="pe-stat pe-c-amber">
        <div class="pe-stat-n"><?= number_format($statusCounts['probation'] ?? 0) ?></div>
        <div class="pe-stat-l">Probation</div>
    </div>
    <div class="pe-stat pe-c-slate">
        <div class="pe-stat-n"><?= number_format($statusCounts['inactive'] ?? 0) ?></div>
        <div class="pe-stat-l">Inactive</div>
    </div>
    <div class="pe-stat pe-c-red">
        <div class="pe-stat-n"><?= number_format($statusCounts['terminated'] ?? 0) ?></div>
        <div class="pe-stat-l">Terminated</div>
    </div>
</div>

<!-- Filter card -->
<div class="pe-filter-card">
    <form method="GET" action="/admin/employees" class="row g-2 align-items-end" id="peFilterForm">
        <?php if (count($companies ?? []) > 1): ?>
        <div class="col-md-2">
            <label>Company</label>
            <select name="company_id" class="form-select form-select-sm">
                <option value="">All companies</option>
                <?php foreach ($companies as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= (int)($filters['company_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="col-md-3">
            <label>Search</label>
            <div class="pe-srch-wrap">
                <div class="pe-srch-ico"><i data-lucide="search" style="width:14px;height:14px"></i></div>
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Name, code, email…" value="<?= e($filters['q'] ?? '') ?>">
            </div>
        </div>
        <div class="col-md-2">
            <label>Department</label>
            <select name="department_id" class="form-select form-select-sm">
                <option value="">All Departments</option>
                <?php foreach ($departments as $d): ?>
                <option value="<?= (int)$d['id'] ?>" <?= (int)($filters['department_id'] ?? 0) === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label>Branch</label>
            <select name="branch_id" class="form-select form-select-sm">
                <option value="">All Branches</option>
                <?php foreach ($branches as $b): ?>
                <option value="<?= (int)$b['id'] ?>" <?= (int)($filters['branch_id'] ?? 0) === (int)$b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <input type="hidden" name="employment_status" id="statusHidden" value="<?= e($currentStatus) ?>">
        <div class="col-md-2">
            <label>&nbsp;</label>
            <button type="submit" class="btn btn-secondary btn-sm w-100">Apply</button>
        </div>
        <?php if (array_filter(array_diff_key($filters ?? [], ['employment_status' => 1]))): ?>
        <div class="col-md-1">
            <label>&nbsp;</label>
            <a href="/admin/employees<?= $currentStatus ? '?employment_status=' . urlencode($currentStatus) : '' ?>" class="btn btn-soft btn-sm w-100">Clear</a>
        </div>
        <?php endif; ?>
    </form>

    <!-- Status chips -->
    <div class="pe-chips">
        <?php
        $chips = [
            '' => ['All', null],
            'active' => ['Active', $statusCounts['active'] ?? null],
            'probation' => ['Probation', $statusCounts['probation'] ?? null],
            'inactive' => ['Inactive', $statusCounts['inactive'] ?? null],
            'terminated' => ['Terminated', $statusCounts['terminated'] ?? null],
        ];
        foreach ($chips as $val => [$label, $cnt]):
            $isActive = $currentStatus === $val;
            $href = '/admin/employees?' . ($val ? 'employment_status=' . urlencode($val) . $baseQuery : ltrim($baseQuery, '&'));
        ?>
        <a href="<?= e($href) ?>" class="pe-chip<?= $isActive ? ' is-active' : '' ?>">
            <?= e($label) ?>
            <?php if ($cnt !== null && $val !== ''): ?><strong><?= $cnt ?></strong><?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Directory card -->
<form method="GET" action="/admin/employees/export" id="employeeExportForm">
    <?php foreach (array_filter($filters ?? []) as $fk => $fv): ?>
    <input type="hidden" name="<?= e($fk) ?>" value="<?= e((string)$fv) ?>">
    <?php endforeach; ?>

    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden">
        <div class="pe-dir-header">
            <div class="pe-dir-meta">
                <?php $total = $paginator['total'] ?? count($employees); ?>
                <?= number_format($total) ?> employee<?= $total != 1 ? 's' : '' ?>
                <?php if (($paginator['last_page'] ?? 1) > 1): ?>
                · Page <?= (int)($paginator['current_page'] ?? 1) ?> of <?= (int)($paginator['last_page'] ?? 1) ?>
                <?php endif; ?>
            </div>
            <div class="pe-dir-actions">
                <?php if (can('reports.export')): ?>
                <button type="submit" class="btn btn-soft btn-sm" id="employeeExportBtn" disabled style="font-size:12px">
                    <i data-lucide="download" style="width:12px;height:12px" class="me-1"></i>Export <span id="employeeSelectedCount">0</span>
                </button>
                <?php endif; ?>
                <?php if (can('employees.create')): ?>
                <a href="/admin/employees/create" class="btn btn-primary btn-sm" style="font-size:12px">
                    <i data-lucide="user-plus" style="width:12px;height:12px" class="me-1"></i>Add
                </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (empty($employees)): ?>
        <div class="pe-empty">
            <i data-lucide="users"></i>
            <h3>No employees found</h3>
            <p>Try clearing your filters or add a new employee</p>
            <?php if (can('employees.create')): ?>
            <div class="mt-3 d-flex gap-2 justify-content-center">
                <a href="/admin/employees/create" class="btn btn-primary btn-sm">Add Employee</a>
                <a href="/admin/employees/import" class="btn btn-soft btn-sm">Import CSV</a>
            </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="pe-grid">
            <?php foreach ($employees as $row):
                $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                [$avBg, $avFg, $ini] = pe_avatar($name, $avPalettes);
                $empId = (int)$row['id'];
                $statusKey = 'pe-cs-' . str_replace([' ', '/'], '_', strtolower($row['employment_status'] ?? 'inactive'));
                $statusLabel = ucwords(str_replace('_', ' ', $row['employment_status'] ?? 'inactive'));
            ?>
            <div class="pe-card">
                <div class="pe-card-chk">
                    <input type="checkbox" class="form-check-input employee-select" name="ids[]" value="<?= $empId ?>" aria-label="Select <?= e($name) ?>">
                </div>
                <div class="pe-card-top">
                    <div class="pe-card-av" style="background:<?= e($avBg) ?>;color:<?= e($avFg) ?>"><?= e($ini) ?></div>
                    <div>
                        <div class="pe-card-name"><?= e($name) ?></div>
                        <div class="pe-card-code"><?= e($row['employee_code'] ?? '') ?></div>
                    </div>
                    <span class="pe-card-status <?= e($statusKey) ?>">
                        <span class="pe-card-status-dot"></span><?= e($statusLabel) ?>
                    </span>
                </div>
                <div class="pe-card-meta">
                    <?php if (!empty($row['department_name'])): ?>
                    <div class="pe-card-dept"><?= e($row['department_name']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($row['designation_name'])): ?>
                    <div><?= e($row['designation_name']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($row['branch_name'])): ?>
                    <div><?= e($row['branch_name']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="pe-card-acts">
                    <a href="/admin/employees/<?= $empId ?>" class="pe-card-act pe-ca-view">
                        <i data-lucide="eye"></i> View
                    </a>
                    <?php if (can('employees.update')): ?>
                    <a href="/admin/employees/<?= $empId ?>/edit" class="pe-card-act pe-ca-edit">
                        <i data-lucide="pencil"></i> Edit
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($paginator) && ($paginator['last_page'] ?? 1) > 1): ?>
        <div class="card-footer py-2 d-flex align-items-center justify-content-between" style="font-size:12px;color:#6b7280;border-top:1px solid #e5e7eb;padding:8px 14px">
            <span>Showing <?= count($employees) ?> of <?= $paginator['total'] ?? '?' ?></span>
            <?= paginate_links($paginator, '/admin/employees?' . http_build_query(array_filter($filters ?? []))) ?>
        </div>
        <?php endif; ?>
    </div>
</form>

<script>
(function(){
    var boxes   = Array.from(document.querySelectorAll('.employee-select'));
    var btn     = document.getElementById('employeeExportBtn');
    var counter = document.getElementById('employeeSelectedCount');
    function sync(){
        var n = boxes.filter(function(b){ return b.checked; }).length;
        if(counter) counter.textContent = n;
        if(btn) btn.disabled = n === 0;
    }
    boxes.forEach(function(b){ b.addEventListener('change', sync); });
    sync();
})();
</script>
