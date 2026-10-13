<?php
/** @var array $records */
/** @var array $stats */
/** @var array $filters */
/** @var array $thumbnails */
/** @var array $departments */
/** @var array $branches */
/** @var array $shifts */
/** @var bool $manualMode */
/** @var array $employees */

$manualMode = $manualMode ?? false;
$query = http_build_query(array_filter($filters));

// Avatar colour helper
$avPalettes = [
    ['#DBEAFE','#1E40AF'],['#D1FAE5','#065F46'],['#FEF3C7','#92400E'],
    ['#FCE7F3','#9D174D'],['#EDE9FE','#5B21B6'],['#FFEDD5','#9A3412'],
    ['#CFFAFE','#164E63'],['#F0FDF4','#14532D'],
];
function att_avatar(string $name, array $pal): string {
    $idx = 0;
    foreach (str_split($name) as $ch) { $idx = ($idx * 31 + ord($ch)) & 0x7fffffff; }
    [$bg, $fg] = $pal[$idx % count($pal)];
    $ini = implode('', array_map(fn($p) => strtoupper($p[0] ?? ''), array_slice(explode(' ', trim($name)), 0, 2)));
    return '<div class="att-av" style="background:' . e($bg) . ';color:' . e($fg) . '">' . e($ini ?: '?') . '</div>';
}
?>
<style>
/* ── Attendance index ─────────────────────────────────────── */
.att-stats{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px;margin-bottom:18px}
.att-stat{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:13px 15px}
.att-stat-n{font-size:26px;font-weight:700;line-height:1;font-variant-numeric:tabular-nums;margin-bottom:2px}
.att-stat-l{font-size:11px;color:#6b7280;font-weight:500}
.c-green .att-stat-n{color:#16a34a} .c-amber .att-stat-n{color:#d97706}
.c-blue .att-stat-n{color:#2563eb}  .c-red .att-stat-n{color:#dc2626}
.c-muted .att-stat-n{color:#111}

/* Filter panel */
.att-filter-card{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:12px 14px;margin-bottom:14px}
.att-filter-card .form-control,.att-filter-card .form-select{font-size:12.5px;border-radius:7px}
.att-filter-card label{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;margin-bottom:3px}

/* Status chips */
.att-status-chips{display:flex;gap:4px;flex-wrap:wrap;margin-top:4px}
.att-sch{padding:4px 9px;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:20px;font-size:11.5px;font-weight:500;text-decoration:none;color:#6b7280;transition:all .12s;white-space:nowrap}
.att-sch:hover{border-color:#6366f1;color:#6366f1}
.att-sch.is-active{background:rgba(99,102,241,.1);border-color:#6366f1;color:#6366f1;font-weight:600}

/* Table */
.att-table{width:100%;border-collapse:collapse;min-width:720px}
.att-table thead th{background:#f9fafb;padding:9px 12px;text-align:left;font-size:10.5px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:#6b7280;border-bottom:1px solid #e5e7eb;white-space:nowrap}
.att-table tbody tr{border-bottom:1px solid #e5e7eb;transition:background .1s}
.att-table tbody tr:last-child{border-bottom:none}
.att-table tbody tr:hover{background:#f9fafb}
.att-table td{padding:10px 12px;font-size:13px;vertical-align:middle}

/* Avatar */
.att-av{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11.5px;font-weight:700;flex-shrink:0;letter-spacing:-.3px}
.att-ecell{display:flex;align-items:center;gap:8px}
.att-ename{font-weight:600;font-size:13px;line-height:1.2}
.att-esub{font-size:11px;color:#6b7280}

/* Status badges */
.att-bdg{display:inline-flex;align-items:center;gap:4px;padding:2px 7px;border-radius:20px;font-size:11px;font-weight:600;white-space:nowrap}
.att-bdg-dot{width:5px;height:5px;border-radius:50%;flex-shrink:0}
.att-bdg.present       {background:#F0FDF4;color:#15803D} .att-bdg.present .att-bdg-dot{background:#16A34A}
.att-bdg.late          {background:#FFFBEB;color:#B45309} .att-bdg.late .att-bdg-dot{background:#D97706}
.att-bdg.absent        {background:#FEF2F2;color:#B91C1C} .att-bdg.absent .att-bdg-dot{background:#DC2626}
.att-bdg.remote        {background:#EFF6FF;color:#1D4ED8} .att-bdg.remote .att-bdg-dot{background:#3B82F6}
.att-bdg.half_day      {background:#F5F3FF;color:#6D28D9} .att-bdg.half_day .att-bdg-dot{background:#7C3AED}
.att-bdg.manual        {background:#F0FDF4;color:#166534} .att-bdg.manual .att-bdg-dot{background:#15803D}
.att-bdg.missing_checkout{background:#FFF7ED;color:#C2410C} .att-bdg.missing_checkout .att-bdg-dot{background:#EA580C}
.att-bdg.verified      {background:#F0FDF4;color:#15803D} .att-bdg.verified .att-bdg-dot{background:#16A34A}
.att-bdg.pending       {background:#FFFBEB;color:#B45309} .att-bdg.pending .att-bdg-dot{background:#D97706}
.att-bdg.flagged       {background:#FEF2F2;color:#B91C1C} .att-bdg.flagged .att-bdg-dot{background:#DC2626}
.att-bdg.auto_verified {background:#EFF6FF;color:#1D4ED8} .att-bdg.auto_verified .att-bdg-dot{background:#3B82F6}

/* Time cell */
.att-time{font-variant-numeric:tabular-nums;font-size:12.5px}
.att-time.missing{color:#9ca3af}

/* Empty */
.att-empty{padding:52px 20px;text-align:center}
.att-empty i[data-lucide]{width:40px;height:40px;opacity:.2;display:block;margin:0 auto 12px}
.att-empty h3{font-size:15px;font-weight:600;margin-bottom:4px}
.att-empty p{font-size:13px;color:#6b7280}

/* ── Dark mode ─────────────────────────────────────────────── */
html[data-theme="dark"] .att-stat{background:#0d1b2a;border-color:rgba(96,165,250,.13)}
html[data-theme="dark"] .att-stat-l{color:#64748b}
html[data-theme="dark"] .c-muted .att-stat-n{color:#f1f5f9}
html[data-theme="dark"] .att-filter-card{background:#0d1b2a;border-color:rgba(96,165,250,.13)}
html[data-theme="dark"] .att-filter-card label{color:#64748b}
html[data-theme="dark"] .att-sch{background:rgba(255,255,255,.05);border-color:rgba(96,165,250,.15);color:#94a3b8}
html[data-theme="dark"] .att-sch:hover{border-color:rgba(32,212,131,.3);color:#20d483}
html[data-theme="dark"] .att-sch.is-active{background:rgba(32,212,131,.12);border-color:rgba(32,212,131,.3);color:#20d483}
html[data-theme="dark"] .att-table thead th{background:rgba(255,255,255,.03);color:#64748b;border-bottom-color:rgba(96,165,250,.1)}
html[data-theme="dark"] .att-table tbody tr{border-bottom-color:rgba(96,165,250,.08)}
html[data-theme="dark"] .att-table tbody tr:hover{background:rgba(32,212,131,.05)}
html[data-theme="dark"] .att-esub{color:#64748b}
html[data-theme="dark"] .att-bdg.present       {background:rgba(22,163,74,.15);color:#86efac}
html[data-theme="dark"] .att-bdg.late          {background:rgba(217,119,6,.15);color:#fcd34d}
html[data-theme="dark"] .att-bdg.absent        {background:rgba(220,38,38,.15);color:#fca5a5}
html[data-theme="dark"] .att-bdg.remote        {background:rgba(59,130,246,.15);color:#93c5fd}
html[data-theme="dark"] .att-bdg.half_day      {background:rgba(124,58,237,.15);color:#c4b5fd}
html[data-theme="dark"] .att-bdg.manual        {background:rgba(22,163,74,.15);color:#86efac}
html[data-theme="dark"] .att-bdg.missing_checkout{background:rgba(234,88,12,.15);color:#fdba74}
html[data-theme="dark"] .att-bdg.verified      {background:rgba(22,163,74,.15);color:#86efac}
html[data-theme="dark"] .att-bdg.pending       {background:rgba(217,119,6,.15);color:#fcd34d}
html[data-theme="dark"] .att-bdg.flagged       {background:rgba(220,38,38,.15);color:#fca5a5}
html[data-theme="dark"] .att-bdg.auto_verified {background:rgba(59,130,246,.15);color:#93c5fd}
html[data-theme="dark"] .att-time.missing{color:#64748b}
html[data-theme="dark"] .att-empty p{color:#64748b}

@media(max-width:640px){
    .att-stats{grid-template-columns:1fr 1fr}
    .att-stats .att-stat:last-child{display:none}
}
</style>

<!-- Header -->
<div class="page-header d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h1><?= e($manualMode ? 'Manual Attendance Entry' : ($title ?? 'Attendance')) ?></h1>
        <p class="subtitle"><?= $manualMode ? 'Record attendance manually for employees' : 'Track and manage employee attendance records' ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if (can('attendance.view_archived')): ?>
        <a href="/admin/attendance/archived" class="btn btn-soft btn-sm">Archived</a>
        <?php endif; ?>
        <?php if (can('attendance.correct')): ?>
        <a href="/admin/attendance/corrections" class="btn btn-soft btn-sm">
            <i data-lucide="clock-x" style="width:14px;height:14px" class="me-1"></i>Corrections
        </a>
        <?php endif; ?>
        <?php if (can('attendance.manual')): ?>
        <a href="/admin/attendance/manual" class="btn btn-soft btn-sm">
            <i data-lucide="plus-circle" style="width:14px;height:14px" class="me-1"></i>Manual Entry
        </a>
        <?php endif; ?>
        <?php if (can('attendance.export')): ?>
        <a href="/admin/attendance/export?<?= e($query) ?>" class="btn btn-soft btn-sm">
            <i data-lucide="download" style="width:14px;height:14px" class="me-1"></i>Export
        </a>
        <?php endif; ?>
    </div>
</div>

<?php include config('app.paths.views') . '/partials/alerts.php'; ?>
<?php if (!$manualMode && !empty($filters['company_id'])): $savedModule = 'attendance'; $currentFilters = $filters; include config('app.paths.views') . '/partials/saved-filters.php'; endif; ?>

<?php if ($manualMode): ?>
<div class="card ems-card mb-3">
    <div class="card-body">
        <form method="post" action="/admin/attendance/manual" class="row g-3">
            <?= csrf_field() ?>
            <div class="col-md-4">
                <label class="form-label small" for="employee_id">Employee</label>
                <select name="employee_id" class="form-select form-select-sm" required id="employee_id">
                    <option value="">Select employee…</option>
                    <?php foreach ($employees ?? [] as $emp): ?>
                    <option value="<?= (int) $emp['id'] ?>">
                        <?= e($emp['employee_code'] . ' — ' . trim($emp['first_name'] . ' ' . $emp['last_name'])) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small" for="attendance_date">Date</label>
                <input type="date" name="attendance_date" class="form-control form-control-sm" value="<?= e(date('Y-m-d')) ?>" required id="attendance_date">
            </div>
            <div class="col-md-2">
                <label class="form-label small" for="check_in_at">Check In</label>
                <input type="datetime-local" name="check_in_at" class="form-control form-control-sm" id="check_in_at">
            </div>
            <div class="col-md-2">
                <label class="form-label small" for="check_out_at">Check Out</label>
                <input type="datetime-local" name="check_out_at" class="form-control form-control-sm" id="check_out_at">
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_remote" value="1" id="manual-remote">
                    <label class="form-check-label small" for="manual-remote">Remote</label>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label small" for="status-2">Status</label>
                <select name="status" class="form-select form-select-sm" id="status-2">
                    <?php foreach (['manual', 'present', 'late', 'remote', 'half_day'] as $st): ?>
                    <option value="<?= e($st) ?>"><?= e(ucwords(str_replace('_', ' ', $st))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label small" for="remarks">Remarks</label>
                <input type="text" name="remarks" class="form-control form-control-sm" id="remarks">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary btn-sm">Save Entry</button>
            </div>
        </form>
    </div>
</div>
<?php else: ?>

<!-- Stat tiles -->
<div class="att-stats">
    <div class="att-stat c-green">
        <div class="att-stat-n"><?= (int) ($stats['present_count'] ?? 0) ?></div>
        <div class="att-stat-l">Present</div>
    </div>
    <div class="att-stat c-amber">
        <div class="att-stat-n"><?= (int) ($stats['late_count'] ?? 0) ?></div>
        <div class="att-stat-l">Late</div>
    </div>
    <div class="att-stat c-blue">
        <div class="att-stat-n"><?= (int) ($stats['remote_count'] ?? 0) ?></div>
        <div class="att-stat-l">Remote</div>
    </div>
    <div class="att-stat c-red">
        <div class="att-stat-n"><?= (int) ($stats['flagged_count'] ?? 0) ?></div>
        <div class="att-stat-l">Flagged</div>
    </div>
    <div class="att-stat c-muted">
        <div class="att-stat-n"><?= e(format_minutes((int) ($stats['total_work_minutes'] ?? 0))) ?></div>
        <div class="att-stat-l">Work hours</div>
    </div>
</div>

<!-- Filter panel -->
<div class="att-filter-card">
    <form method="get" action="/admin/attendance" class="row g-2 align-items-end">
        <div class="col-6 col-md-2">
            <label for="date_from">From</label>
            <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($filters['date_from'] ?? date('Y-m-01')) ?>" id="date_from">
        </div>
        <div class="col-6 col-md-2">
            <label for="date_to">To</label>
            <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($filters['date_to'] ?? date('Y-m-d')) ?>" id="date_to">
        </div>
        <div class="col-6 col-md-2">
            <label for="department_id">Department</label>
            <select name="department_id" class="form-select form-select-sm" id="department_id">
                <option value="">All</option>
                <?php foreach ($departments as $d): ?>
                <option value="<?= (int) $d['id'] ?>" <?= (($filters['department_id'] ?? '') == $d['id']) ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label for="branch_id">Branch</label>
            <select name="branch_id" class="form-select form-select-sm" id="branch_id">
                <option value="">All</option>
                <?php foreach ($branches as $b): ?>
                <option value="<?= (int) $b['id'] ?>" <?= (($filters['branch_id'] ?? '') == $b['id']) ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label for="q">Search</label>
            <input type="text" name="q" class="form-control form-control-sm" value="<?= e($filters['q'] ?? '') ?>" placeholder="Name or code" id="q">
        </div>
        <div class="col-6 col-md-2 d-flex align-items-end gap-1">
            <button type="submit" class="btn btn-primary btn-sm flex-fill">Filter</button>
            <a href="/admin/attendance" class="btn btn-soft btn-sm">Clear</a>
        </div>
        <!-- Status quick-chips -->
        <div class="col-12">
            <?php
            $curStatus = $filters['status'] ?? '';
            $statuses = ['' => 'All', 'present' => 'Present', 'late' => 'Late', 'absent' => 'Absent', 'remote' => 'Remote', 'half_day' => 'Half Day', 'missing_checkout' => 'Missing Checkout', 'manual' => 'Manual'];
            $baseQ = array_filter($filters, fn($v, $k) => $k !== 'status' && $v !== '' && $v !== null, ARRAY_FILTER_USE_BOTH);
            ?>
            <div class="att-status-chips">
                <?php foreach ($statuses as $val => $lbl): ?>
                <a href="/admin/attendance?<?= e(http_build_query(array_merge($baseQ, $val !== '' ? ['status' => $val] : []))) ?>"
                   class="att-sch <?= $curStatus === $val ? 'is-active' : '' ?>"><?= e($lbl) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
    </form>
</div>

<!-- Table card -->
<div class="card ems-card">
    <div class="table-responsive">
        <table class="att-table">
            <thead>
                <tr>
                    <th style="width:48px">Photo</th>
                    <th>Date</th>
                    <th>Employee</th>
                    <th>Dept</th>
                    <th>Check In</th>
                    <th>Check Out</th>
                    <th>Status</th>
                    <th>Work</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($records['data'])): ?>
                <tr>
                    <td colspan="9">
                        <div class="att-empty">
                            <i data-lucide="calendar-x2"></i>
                            <h3>No attendance records</h3>
                            <p>Try adjusting the filters or date range.</p>
                        </div>
                    </td>
                </tr>
            <?php else: foreach ($records['data'] as $row):
                $thumb = $thumbnails[(int) $row['id']] ?? null;
                $imgUrl = $thumb ? '/admin/attendance/images/' . (int) $thumb['id'] : null;
                $bdgClass = str_replace(['missing_checkout','half_day','auto_verified'], ['missing_checkout','half_day','auto_verified'], $row['status'] ?? '');
            ?>
                <tr>
                    <td>
                        <?php if ($imgUrl && can('attendance.images')): ?>
                        <a href="<?= e($imgUrl) ?>" target="_blank" rel="noopener">
                            <img src="<?= e($imgUrl) ?>" alt="Check-in" class="rounded" width="36" height="36" style="object-fit:cover;border:1px solid var(--ems-border,#e5e7eb)">
                        </a>
                        <?php else: ?>
                        <div style="width:36px;height:36px;border-radius:8px;background:var(--ems-card-bg-alt,#f3f4f6);border:1px solid var(--ems-border,#e5e7eb);display:flex;align-items:center;justify-content:center">
                            <?= att_avatar($row['employee_name'] ?? '', $avPalettes) ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:12.5px;white-space:nowrap;color:var(--ems-text-muted);font-variant-numeric:tabular-nums"><?= e(format_date($row['attendance_date'])) ?></td>
                    <td>
                        <div class="att-ecell">
                            <?php if (!$imgUrl): ?>
                            <?= att_avatar($row['employee_name'] ?? '', $avPalettes) ?>
                            <?php endif; ?>
                            <div>
                                <div class="att-ename"><?= e($row['employee_name']) ?></div>
                                <div class="att-esub"><?= e($row['employee_code']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td style="font-size:12px;color:var(--ems-text-muted)"><?= e($row['department_name'] ?? '—') ?></td>
                    <td>
                        <span class="att-time <?= empty($row['check_in_at']) ? 'missing' : '' ?>">
                            <?= e(format_datetime($row['check_in_at'] ?? null, config('app.time_format', 'g:i A'))) ?: '—' ?>
                        </span>
                    </td>
                    <td>
                        <span class="att-time <?= empty($row['check_out_at']) ? 'missing' : '' ?>">
                            <?= e(format_datetime($row['check_out_at'] ?? null, config('app.time_format', 'g:i A'))) ?: '—' ?>
                        </span>
                    </td>
                    <td>
                        <span class="att-bdg <?= e($bdgClass) ?>">
                            <span class="att-bdg-dot"></span>
                            <?= e(ucwords(str_replace('_', ' ', $row['status'] ?? ''))) ?>
                        </span>
                    </td>
                    <td style="font-size:12.5px;font-variant-numeric:tabular-nums"><?= e(format_minutes((int) ($row['work_minutes'] ?? 0))) ?></td>
                    <td class="text-end text-nowrap">
                        <?php
                        $aid = (int) $row['id'];
                        $actions = [['type' => 'link', 'icon' => 'eye', 'label' => 'View', 'href' => '/admin/attendance/' . $aid]];
                        if (can('attendance.edit')) {
                            $actions[] = ['type' => 'link', 'icon' => 'pencil', 'label' => 'Edit', 'variant' => 'warning', 'href' => '/admin/attendance/' . $aid . '#edit'];
                        }
                        if (can('attendance.view_history')) {
                            $actions[] = ['type' => 'link', 'icon' => 'history', 'label' => 'History', 'href' => '/admin/attendance/' . $aid . '#audit'];
                        }
                        if (can('attendance.archive')) {
                            $actions[] = ['type' => 'link', 'icon' => 'archive', 'label' => 'Archive', 'variant' => 'danger', 'href' => '/admin/attendance/' . $aid . '#archive'];
                        }
                        if (can('attendance.edit')) {
                            $actions[] = ['type' => 'form', 'icon' => 'trash-2', 'label' => 'Delete', 'variant' => 'danger', 'action' => '/admin/attendance/' . $aid . '/delete', 'confirm' => 'Delete this attendance record permanently?'];
                        }
                        include config('app.paths.views') . '/partials/table-actions.php';
                        ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (($records['last_page'] ?? 1) > 1): ?>
    <div class="card-footer py-2 d-flex align-items-center justify-content-between" style="font-size:12px;color:var(--ems-text-muted)">
        <span><?= (int) ($records['total'] ?? 0) ?> records</span>
        <div><?= paginate_links($records, '/admin/attendance?' . $query) ?></div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>
