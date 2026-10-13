<?php
/** @var array $requests */
/** @var array $paginator */
/** @var array $filters */
/** @var array $leaveTypes */
/** @var array $statusCounts */

$statusCounts = $statusCounts ?? [];
$currentStatus = $filters['status'] ?? '';

$avPalettes = [
    ['#DBEAFE','#1E40AF'],['#D1FAE5','#065F46'],['#FEF3C7','#92400E'],
    ['#FCE7F3','#9D174D'],['#EDE9FE','#5B21B6'],['#FFEDD5','#9A3412'],
    ['#CFFAFE','#164E63'],['#F0FDF4','#14532D'],
];
function lv_avatar(string $name, array $pal): string {
    $idx = 0;
    foreach (str_split($name) as $ch) { $idx = ($idx * 31 + ord($ch)) & 0x7fffffff; }
    [$bg, $fg] = $pal[$idx % count($pal)];
    $ini = implode('', array_map(fn($p) => strtoupper($p[0] ?? ''), array_slice(explode(' ', trim($name)), 0, 2)));
    return '<div class="lv-av" style="background:' . e($bg) . ';color:' . e($fg) . '">' . e($ini ?: '?') . '</div>';
}

// Build a base query string that preserves filters except status
$baseFilters = array_filter(array_diff_key($filters ?? [], ['status' => 1, 'page' => 1]));
$baseQuery = $baseFilters ? '&' . http_build_query($baseFilters) : '';
?>
<style>
/* ── Leave index ──────────────────────────────────────────── */
.lv-stats{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px;margin-bottom:18px}
.lv-stat{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:13px 15px}
.lv-stat-n{font-size:26px;font-weight:700;line-height:1;font-variant-numeric:tabular-nums;margin-bottom:2px}
.lv-stat-l{font-size:11px;color:#6b7280;font-weight:500}
.lv-c-amber .lv-stat-n{color:#d97706}
.lv-c-green .lv-stat-n{color:#16a34a}
.lv-c-red   .lv-stat-n{color:#dc2626}
.lv-c-slate .lv-stat-n{color:#475569}

/* Filter card */
.lv-filter-card{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:12px 14px;margin-bottom:14px}
.lv-filter-card .form-control,.lv-filter-card .form-select{font-size:12.5px;border-radius:7px}
.lv-filter-card label{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;margin-bottom:3px}

/* Status chips */
.lv-chips{display:flex;gap:5px;flex-wrap:wrap;margin-top:10px}
.lv-chip{padding:4px 10px;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:20px;font-size:12px;font-weight:500;text-decoration:none;color:#6b7280;transition:all .12s;white-space:nowrap;display:inline-flex;align-items:center;gap:5px}
.lv-chip:hover{border-color:#6366f1;color:#6366f1}
.lv-chip.is-active{background:rgba(99,102,241,.1);border-color:#6366f1;color:#6366f1;font-weight:600}
.lv-chip strong{font-variant-numeric:tabular-nums;font-size:11px;background:rgba(0,0,0,.08);border-radius:20px;padding:0 5px;margin-left:2px}
.lv-chip.is-active strong{background:rgba(99,102,241,.15)}

/* Table */
.lv-table{width:100%;border-collapse:collapse;min-width:680px}
.lv-table thead th{background:#f9fafb;padding:9px 14px;text-align:left;font-size:10.5px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:#6b7280;border-bottom:1px solid #e5e7eb;white-space:nowrap}
.lv-table tbody tr{border-bottom:1px solid #e5e7eb;transition:background .1s}
.lv-table tbody tr:last-child{border-bottom:none}
.lv-table tbody tr:hover{background:#f9fafb}
.lv-table td{padding:11px 14px;font-size:13px;vertical-align:middle}

/* Avatar */
.lv-av{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0;letter-spacing:-.3px}
.lv-ecell{display:flex;align-items:center;gap:9px}
.lv-ename{font-weight:600;font-size:13.5px;line-height:1.2}
.lv-esub{font-size:11.5px;color:#6b7280;margin-top:1px}

/* Status badges */
.lv-bdg{display:inline-flex;align-items:center;gap:5px;padding:3px 8px;border-radius:20px;font-size:11.5px;font-weight:600;white-space:nowrap}
.lv-bdg-dot{width:5px;height:5px;border-radius:50%;flex-shrink:0}
.lv-bdg.pending   {background:#FFFBEB;color:#B45309} .lv-bdg.pending  .lv-bdg-dot{background:#D97706}
.lv-bdg.approved  {background:#F0FDF4;color:#15803D} .lv-bdg.approved .lv-bdg-dot{background:#16A34A}
.lv-bdg.rejected  {background:#FEF2F2;color:#B91C1C} .lv-bdg.rejected .lv-bdg-dot{background:#DC2626}
.lv-bdg.cancelled {background:#F3F4F6;color:#374151} .lv-bdg.cancelled .lv-bdg-dot{background:#6B7280}
.lv-bdg.draft     {background:#EEF2FF;color:#3730A3} .lv-bdg.draft    .lv-bdg-dot{background:#6366F1}
.lv-bdg.withdrawn {background:#FEF3C7;color:#92400E} .lv-bdg.withdrawn .lv-bdg-dot{background:#D97706}

/* Leave type pill */
.lv-type{display:inline-block;padding:2px 8px;border-radius:6px;font-size:11.5px;font-weight:600;background:#EEF2FF;color:#4338CA}

/* Days chip */
.lv-days{display:inline-block;padding:2px 8px;border-radius:6px;font-size:12px;font-weight:700;background:#F0FDF4;color:#15803D;font-variant-numeric:tabular-nums;white-space:nowrap}

/* Action buttons */
.lv-acts{display:flex;align-items:center;gap:5px;justify-content:flex-end}
.lv-acts form{display:inline}
.ba-lv{display:inline-flex;align-items:center;gap:4px;padding:5px 10px;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;border:none;transition:all .12s;white-space:nowrap;font-family:inherit;text-decoration:none}
.ba-lv i[data-lucide]{width:11px;height:11px;stroke-width:2.5}
.ba-lv-view{background:#f3f4f6;color:#374151;border:1px solid #e5e7eb}
.ba-lv-view:hover{background:#e5e7eb;color:#111}
.ba-lv-ok  {background:#DCFCE7;color:#15803D}
.ba-lv-ok:hover{background:#16a34a;color:#fff}
.ba-lv-rej {background:#FEE2E2;color:#B91C1C}
.ba-lv-rej:hover{background:#dc2626;color:#fff}

/* Empty */
.lv-empty{padding:52px 20px;text-align:center}
.lv-empty i[data-lucide]{width:40px;height:40px;opacity:.2;display:block;margin:0 auto 12px}
.lv-empty h3{font-size:15px;font-weight:600;margin-bottom:4px}
.lv-empty p{font-size:13px;color:#6b7280}

/* Bulk bar */
.lv-bulk-bar{background:#fff;border:1px solid #f59e0b;border-radius:10px;padding:10px 14px;margin-bottom:12px;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.lv-bulk-bar .lv-bulk-count{font-weight:700;font-size:13px;color:#b45309}

/* ── Dark mode ─────────────────────────────────────────────── */
html[data-theme="dark"] .lv-stat{background:#0d1b2a;border-color:rgba(96,165,250,.13)}
html[data-theme="dark"] .lv-stat-l{color:#64748b}
html[data-theme="dark"] .lv-c-slate .lv-stat-n{color:#94a3b8}
html[data-theme="dark"] .lv-filter-card{background:#0d1b2a;border-color:rgba(96,165,250,.13)}
html[data-theme="dark"] .lv-filter-card label{color:#64748b}
html[data-theme="dark"] .lv-chip{background:rgba(255,255,255,.05);border-color:rgba(96,165,250,.15);color:#94a3b8}
html[data-theme="dark"] .lv-chip:hover{border-color:rgba(32,212,131,.3);color:#20d483}
html[data-theme="dark"] .lv-chip.is-active{background:rgba(32,212,131,.12);border-color:rgba(32,212,131,.3);color:#20d483}
html[data-theme="dark"] .lv-chip strong{background:rgba(255,255,255,.08)}
html[data-theme="dark"] .lv-chip.is-active strong{background:rgba(32,212,131,.2)}
html[data-theme="dark"] .lv-table thead th{background:rgba(255,255,255,.03);color:#64748b;border-bottom-color:rgba(96,165,250,.1)}
html[data-theme="dark"] .lv-table tbody tr{border-bottom-color:rgba(96,165,250,.08)}
html[data-theme="dark"] .lv-table tbody tr:hover{background:rgba(32,212,131,.05)}
html[data-theme="dark"] .lv-esub{color:#64748b}
html[data-theme="dark"] .lv-bdg.pending  {background:rgba(217,119,6,.15);color:#fcd34d}
html[data-theme="dark"] .lv-bdg.approved {background:rgba(22,163,74,.15);color:#86efac}
html[data-theme="dark"] .lv-bdg.rejected {background:rgba(220,38,38,.15);color:#fca5a5}
html[data-theme="dark"] .lv-bdg.cancelled{background:rgba(255,255,255,.06);color:#94a3b8}
html[data-theme="dark"] .lv-bdg.draft    {background:rgba(99,102,241,.15);color:#a5b4fc}
html[data-theme="dark"] .lv-bdg.withdrawn{background:rgba(217,119,6,.15);color:#fcd34d}
html[data-theme="dark"] .lv-type{background:rgba(99,102,241,.15);color:#a5b4fc}
html[data-theme="dark"] .lv-days{background:rgba(22,163,74,.15);color:#86efac}
html[data-theme="dark"] .ba-lv-view{background:rgba(255,255,255,.06);color:#94a3b8;border-color:rgba(96,165,250,.15)}
html[data-theme="dark"] .ba-lv-view:hover{background:rgba(255,255,255,.1);color:#e2e8f0}
html[data-theme="dark"] .ba-lv-ok {background:rgba(22,163,74,.18);color:#86efac}
html[data-theme="dark"] .ba-lv-ok:hover{background:#16a34a;color:#fff}
html[data-theme="dark"] .ba-lv-rej{background:rgba(220,38,38,.18);color:#fca5a5}
html[data-theme="dark"] .ba-lv-rej:hover{background:#dc2626;color:#fff}
html[data-theme="dark"] .lv-empty p{color:#64748b}
html[data-theme="dark"] .lv-bulk-bar{background:#0d1b2a;border-color:rgba(217,119,6,.3)}
html[data-theme="dark"] .lv-bulk-count{color:#fcd34d}

@media(max-width:640px){
    .lv-stats{grid-template-columns:1fr 1fr}
}
</style>

<!-- Header -->
<div class="page-header d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h1><?= e($title ?? 'Leave Requests') ?></h1>
        <p class="subtitle">Review, approve and manage employee leave applications</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if (can('leave.view_archived')): ?>
        <a href="/admin/leave/archived" class="btn btn-soft btn-sm">
            <i data-lucide="archive" class="me-1" style="width:13px;height:13px"></i>Archived
        </a>
        <?php endif; ?>
        <?php if (can('leave.edit')): ?>
        <a href="/admin/leave/allocate" class="btn btn-soft btn-sm">
            <i data-lucide="calendar-check" class="me-1" style="width:13px;height:13px"></i>Allocate
        </a>
        <?php endif; ?>
        <?php if (can('leave.create')): ?>
        <a href="/admin/leave/create" class="btn btn-primary btn-sm">
            <i data-lucide="plus" class="me-1" style="width:13px;height:13px"></i>Record Leave
        </a>
        <?php endif; ?>
    </div>
</div>

<?php include config('app.paths.views') . '/partials/alerts.php'; ?>
<?php if(!empty($filters['company_id'])): $savedModule='leave';$currentFilters=$filters;include config('app.paths.views').'/partials/saved-filters.php';endif; ?>

<!-- Stat tiles -->
<?php if (!empty($statusCounts)): ?>
<div class="lv-stats">
    <div class="lv-stat lv-c-amber">
        <div class="lv-stat-n"><?= number_format($statusCounts['pending'] ?? 0) ?></div>
        <div class="lv-stat-l">Pending</div>
    </div>
    <div class="lv-stat lv-c-green">
        <div class="lv-stat-n"><?= number_format($statusCounts['approved'] ?? 0) ?></div>
        <div class="lv-stat-l">Approved</div>
    </div>
    <div class="lv-stat lv-c-red">
        <div class="lv-stat-n"><?= number_format($statusCounts['rejected'] ?? 0) ?></div>
        <div class="lv-stat-l">Rejected</div>
    </div>
    <div class="lv-stat lv-c-slate">
        <div class="lv-stat-n"><?= number_format($statusCounts['cancelled'] ?? 0) ?></div>
        <div class="lv-stat-l">Cancelled</div>
    </div>
</div>
<?php endif; ?>

<!-- Filter card -->
<div class="lv-filter-card">
    <form method="GET" action="/admin/leave" class="row g-2 align-items-end" id="lvFilterForm">
        <?php if (!empty($filters['company_id'])): ?>
        <input type="hidden" name="company_id" value="<?= (int)$filters['company_id'] ?>">
        <?php endif; ?>
        <div class="col-md-3">
            <label>Employee</label>
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Name or code…" value="<?= e($filters['q'] ?? '') ?>">
        </div>
        <div class="col-md-2">
            <label>Leave Type</label>
            <select name="leave_type_id" class="form-select form-select-sm">
                <option value="">All Types</option>
                <?php foreach ($leaveTypes as $lt): ?>
                    <option value="<?= (int)$lt['id'] ?>" <?= (int)($filters['leave_type_id'] ?? 0) === (int)$lt['id'] ? 'selected' : '' ?>><?= e($lt['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label>From</label>
            <input type="date" name="from_date" class="form-control form-control-sm" value="<?= e($filters['from_date'] ?? '') ?>">
        </div>
        <div class="col-md-2">
            <label>To</label>
            <input type="date" name="to_date" class="form-control form-control-sm" value="<?= e($filters['to_date'] ?? '') ?>">
        </div>
        <input type="hidden" name="status" id="statusHidden" value="<?= e($currentStatus) ?>">
        <div class="col-md-2">
            <button type="submit" class="btn btn-secondary btn-sm w-100">Apply</button>
        </div>
        <?php if (array_filter(array_diff_key($filters ?? [], ['status' => 1]))): ?>
        <div class="col-md-1">
            <a href="/admin/leave<?= $currentStatus ? '?status=' . urlencode($currentStatus) : '' ?>" class="btn btn-soft btn-sm w-100">Clear</a>
        </div>
        <?php endif; ?>
    </form>

    <!-- Status quick chips -->
    <div class="lv-chips">
        <?php
        $chipDefs = [
            '' => 'All',
            'pending' => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'cancelled' => 'Cancelled',
            'draft' => 'Draft',
        ];
        foreach ($chipDefs as $val => $label):
            $isActive = $currentStatus === $val;
            $cnt = $statusCounts[$val] ?? null;
            $href = '/admin/leave?' . ($val ? 'status=' . urlencode($val) . $baseQuery : ltrim($baseQuery, '&'));
        ?>
        <a href="<?= e($href) ?>" class="lv-chip<?= $isActive ? ' is-active' : '' ?>" onclick="document.getElementById('statusHidden').value='<?= e($val) ?>'">
            <?= e($label) ?>
            <?php if ($cnt !== null && $val !== ''): ?>
            <strong><?= $cnt ?></strong>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<?php if (can('leave.archive')): ?>
<form method="POST" action="/admin/leave/bulk-archive" id="bulkForm">
    <?= csrf_field() ?>
    <input type="hidden" name="reason" id="bulkReason">

    <div id="bulkBar" class="lv-bulk-bar" style="display:none">
        <span class="lv-bulk-count"><span id="bulkCount">0</span> selected</span>
        <input type="text" id="bulkReasonInput" class="form-control form-control-sm" style="max-width:200px;font-size:12px" placeholder="Archive reason (required)">
        <button type="button" class="btn btn-danger btn-sm" id="bulkArchiveBtn">
            <i data-lucide="archive" style="width:12px;height:12px" class="me-1"></i>Archive Selected
        </button>
        <button type="button" class="btn btn-soft btn-sm ms-auto" id="bulkClear">Clear</button>
    </div>
<?php endif; ?>

<!-- Table card -->
<div class="card">
    <div class="table-responsive">
        <table class="lv-table">
            <thead>
            <tr>
                <?php if (can('leave.archive')): ?>
                <th style="width:36px;padding-left:16px"><input type="checkbox" class="form-check-input" id="selectAll"></th>
                <?php endif; ?>
                <th>Employee</th>
                <th>Leave Type</th>
                <th>Period</th>
                <th>Days</th>
                <th>Status</th>
                <th style="text-align:right;padding-right:16px">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($requests)): ?>
                <tr>
                    <td colspan="<?= can('leave.archive') ? 7 : 6 ?>">
                        <div class="lv-empty">
                            <i data-lucide="calendar-x-2"></i>
                            <h3>No leave requests found</h3>
                            <p>Try adjusting your filters or date range</p>
                        </div>
                    </td>
                </tr>
            <?php else: foreach ($requests as $row):
                $lid = (int)$row['id'];
                $name = $row['employee_name'] ?? '';
                $statusClass = strtolower(str_replace(' ', '_', $row['status'] ?? 'draft'));
                $startFmt = format_date($row['start_date'] ?? null);
                $endFmt   = format_date($row['end_date'] ?? null);
                $sameDay  = ($row['start_date'] ?? '') === ($row['end_date'] ?? '');
            ?>
                <tr>
                    <?php if (can('leave.archive')): ?>
                    <td style="padding-left:16px"><input type="checkbox" class="form-check-input row-check" name="ids[]" value="<?= $lid ?>"></td>
                    <?php endif; ?>
                    <td>
                        <div class="lv-ecell">
                            <?= lv_avatar($name, $avPalettes) ?>
                            <div>
                                <div class="lv-ename"><?= e($name) ?></div>
                                <div class="lv-esub"><?= e($row['employee_code'] ?? '') ?><?= !empty($row['department_name']) ? ' · ' . e($row['department_name']) : '' ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="lv-type"><?= e($row['leave_type_name'] ?? '—') ?></span>
                        <?php if (!empty($row['is_half_day'])): ?>
                        <div style="font-size:11px;color:#6b7280;margin-top:2px">Half day</div>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:12.5px;white-space:nowrap;font-variant-numeric:tabular-nums">
                        <?= e($startFmt) ?>
                        <?php if (!$sameDay): ?>
                        <span style="opacity:.5;margin:0 3px">→</span><?= e($endFmt) ?>
                        <?php endif; ?>
                    </td>
                    <td><span class="lv-days"><?= e((string)($row['chargeable_days'] ?? 0)) ?>d</span></td>
                    <td>
                        <span class="lv-bdg <?= e($statusClass) ?>">
                            <span class="lv-bdg-dot"></span><?= ucfirst($statusClass) ?>
                        </span>
                    </td>
                    <td>
                        <div class="lv-acts">
                            <a href="/admin/leave/<?= $lid ?>" class="ba-lv ba-lv-view">
                                <i data-lucide="eye"></i> View
                            </a>
                            <?php if (can('leave.approve') && ($row['status'] ?? '') === 'pending'): ?>
                            <form method="POST" action="/admin/leave/<?= $lid ?>/approve">
                                <?= csrf_field() ?>
                                <input type="hidden" name="_back" value="/admin/leave<?= $currentStatus ? '?status=' . urlencode($currentStatus) : '' ?>">
                                <button type="submit" class="ba-lv ba-lv-ok">
                                    <i data-lucide="check"></i> Approve
                                </button>
                            </form>
                            <?php endif; ?>
                            <?php if (can('leave.reject') && ($row['status'] ?? '') === 'pending'): ?>
                            <a href="/admin/leave/<?= $lid ?>#reject" class="ba-lv ba-lv-rej">
                                <i data-lucide="x"></i> Reject
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (!empty($paginator) && ($paginator['last_page'] ?? 1) > 1): ?>
    <div class="card-footer py-2 d-flex align-items-center justify-content-between" style="font-size:12px;color:#6b7280">
        <span>Showing <?= count($requests) ?> of <?= $paginator['total'] ?? '?' ?> results</span>
        <?= paginate_links($paginator, '/admin/leave?' . http_build_query(array_filter($filters ?? []))) ?>
    </div>
    <?php endif; ?>
</div>

<?php if (can('leave.archive')): ?>
</form>
<script>
(function(){
    var form = document.getElementById('bulkForm');
    var bar  = document.getElementById('bulkBar');
    var counter = document.getElementById('bulkCount');
    var selectAll = document.getElementById('selectAll');
    var archiveBtn = document.getElementById('bulkArchiveBtn');
    var clearBtn   = document.getElementById('bulkClear');
    var reasonInp  = document.getElementById('bulkReasonInput');

    function getChecked(){ return Array.from(document.querySelectorAll('.row-check:checked')); }

    function update(){
        var checked = getChecked();
        counter.textContent = checked.length;
        bar.style.display = checked.length > 0 ? '' : 'none';
        if(selectAll){
            var all = document.querySelectorAll('.row-check');
            selectAll.indeterminate = checked.length > 0 && checked.length < all.length;
            selectAll.checked = all.length > 0 && checked.length === all.length;
        }
        if(typeof lucide !== 'undefined') lucide.createIcons();
    }

    document.querySelectorAll('.row-check').forEach(function(cb){ cb.addEventListener('change', update); });
    if(selectAll) selectAll.addEventListener('change', function(){
        document.querySelectorAll('.row-check').forEach(function(cb){ cb.checked = selectAll.checked; });
        update();
    });
    if(clearBtn) clearBtn.addEventListener('click', function(){
        document.querySelectorAll('.row-check').forEach(function(cb){ cb.checked = false; });
        if(selectAll) selectAll.checked = false;
        update();
    });
    if(archiveBtn) archiveBtn.addEventListener('click', function(){
        var reason = reasonInp.value.trim();
        if(!reason){ reasonInp.focus(); reasonInp.classList.add('is-invalid'); return; }
        reasonInp.classList.remove('is-invalid');
        if(!confirm('Archive ' + getChecked().length + ' leave request(s)?')) return;
        document.getElementById('bulkReason').value = reason;
        form.submit();
    });
})();
</script>
<?php endif; ?>
