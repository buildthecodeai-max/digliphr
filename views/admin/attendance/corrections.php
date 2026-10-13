<?php
/** @var array $records */
/** @var array $filters */
/** @var array $statusCounts */

$currentStatus = $filters['status'] ?? 'pending';
$backUrl = '/admin/attendance/corrections' . ($currentStatus ? '?status=' . urlencode($currentStatus) : '');

// Avatar colour helper
$avPalettes = [
    ['#DBEAFE','#1E40AF'],['#D1FAE5','#065F46'],['#FEF3C7','#92400E'],
    ['#FCE7F3','#9D174D'],['#EDE9FE','#5B21B6'],['#FFEDD5','#9A3412'],
    ['#CFFAFE','#164E63'],['#F0FDF4','#14532D'],
];
function corr_avatar(string $name, array $pal): string {
    $idx = 0;
    foreach (str_split($name) as $ch) { $idx = ($idx * 31 + ord($ch)) & 0x7fffffff; }
    [$bg, $fg] = $pal[$idx % count($pal)];
    $ini = implode('', array_map(fn($p) => strtoupper($p[0] ?? ''), array_slice(explode(' ', trim($name)), 0, 2)));
    return '<div class="corr-av" style="background:' . e($bg) . ';color:' . e($fg) . '">' . e($ini ?: '?') . '</div>';
}

function fmt_time(?string $dt): string {
    if (!$dt) return '—';
    $ts = strtotime($dt);
    return $ts ? date('g:i A', $ts) : '—';
}

$total = (int) ($records['total'] ?? 0);
$pending  = (int) ($statusCounts['pending']  ?? 0);
$approved = (int) ($statusCounts['approved'] ?? 0);
$rejected = (int) ($statusCounts['rejected'] ?? 0);
?>
<style>
/* ── Corrections page ─────────────────────────────────────── */
.corr-stats{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:10px;margin-bottom:20px}
.corr-stat{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:14px 16px}
.corr-stat-n{font-size:28px;font-weight:700;line-height:1;font-variant-numeric:tabular-nums;margin-bottom:3px}
.corr-stat-l{font-size:11.5px;color:#6b7280;font-weight:500}
.c-amber .corr-stat-n{color:#d97706} .c-green .corr-stat-n{color:#16a34a} .c-red .corr-stat-n{color:#dc2626} .c-blue .corr-stat-n{color:#2563eb}

/* Filters */
.corr-filters{display:flex;align-items:center;gap:8px;margin-bottom:14px;flex-wrap:wrap}
.corr-srch-wrap{position:relative;flex:1;min-width:160px;max-width:280px}
.corr-srch-wrap input{width:100%;padding:7px 10px 7px 32px;background:#fff;border:1px solid #e5e7eb;border-radius:8px;font-size:13px;color:#111;outline:none;transition:border-color .15s;font-family:inherit}
.corr-srch-wrap input:focus{border-color:#6366f1}
.corr-srch-wrap input::placeholder{color:#9ca3af}
.corr-srch-ico{position:absolute;left:9px;top:50%;transform:translateY(-50%);color:#9ca3af;pointer-events:none;display:flex;align-items:center}
.corr-chips{display:flex;gap:5px;flex-wrap:wrap}
.corr-chip{padding:5px 11px;background:#fff;border:1px solid #e5e7eb;border-radius:20px;font-size:12px;font-weight:500;text-decoration:none;color:#6b7280;transition:all .12s;white-space:nowrap;display:inline-flex;align-items:center;gap:5px}
.corr-chip:hover{border-color:#6366f1;color:#6366f1}
.corr-chip.is-active{background:rgba(99,102,241,.1);border-color:#6366f1;color:#6366f1;font-weight:600}
.corr-chip strong{font-variant-numeric:tabular-nums}

/* Table */
.corr-table{width:100%;border-collapse:collapse;min-width:680px}
.corr-table thead th{background:#f9fafb;padding:9px 14px;text-align:left;font-size:10.5px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:#6b7280;border-bottom:1px solid #e5e7eb;white-space:nowrap}
.corr-table tbody tr{border-bottom:1px solid #e5e7eb;transition:background .1s}
.corr-table tbody tr:last-child{border-bottom:none}
.corr-table tbody tr:hover{background:#f9fafb}
.corr-table td{padding:11px 14px;font-size:13.5px;vertical-align:middle}

/* Avatar */
.corr-av{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0;letter-spacing:-.3px}
.corr-ecell{display:flex;align-items:center;gap:9px}
.corr-ename{font-weight:600;font-size:13.5px;line-height:1.2}
.corr-esub{font-size:11.5px;color:#6b7280;margin-top:1px}

/* Time correction arrow */
.corr-times{display:flex;align-items:center;gap:6px;font-size:12.5px;flex-wrap:wrap}
.corr-time-orig{color:#6b7280}
.corr-time-arr{color:#9ca3af;display:flex;align-items:center}
.corr-time-arr i[data-lucide]{width:12px;height:12px;stroke-width:2}
.corr-time-new{color:#4f46e5;font-weight:600}

/* Status badges */
.corr-bdg{display:inline-flex;align-items:center;gap:5px;padding:3px 8px;border-radius:20px;font-size:11.5px;font-weight:600;white-space:nowrap}
.corr-bdg-dot{width:5px;height:5px;border-radius:50%;flex-shrink:0}
.corr-bdg.pending  {background:#FFFBEB;color:#B45309} .corr-bdg.pending  .corr-bdg-dot{background:#D97706}
.corr-bdg.approved {background:#F0FDF4;color:#15803D} .corr-bdg.approved .corr-bdg-dot{background:#16A34A}
.corr-bdg.rejected {background:#FEF2F2;color:#B91C1C} .corr-bdg.rejected .corr-bdg-dot{background:#DC2626}
.corr-bdg.cancelled{background:#f3f4f6;color:#6b7280}  .corr-bdg.cancelled .corr-bdg-dot{background:#9ca3af}

/* Action buttons */
.corr-acts{display:flex;align-items:center;gap:5px;justify-content:flex-end}
.corr-acts form{display:inline}
.ba-corr{display:inline-flex;align-items:center;gap:4px;padding:5px 10px;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;border:none;transition:all .12s;white-space:nowrap;font-family:inherit;text-decoration:none}
.ba-corr i[data-lucide]{width:11px;height:11px;stroke-width:2.5}
.ba-c-ok {background:#DCFCE7;color:#15803D} .ba-c-ok:hover{background:#16a34a;color:#fff}
.ba-c-rej{background:#FEE2E2;color:#B91C1C} .ba-c-rej:hover{background:#dc2626;color:#fff}
.ba-c-eye{background:#f3f4f6;color:#374151;border:1px solid #e5e7eb} .ba-c-eye:hover{background:#e5e7eb;color:#111}

/* Empty */
.corr-empty{padding:52px 20px;text-align:center}
.corr-empty i[data-lucide]{width:40px;height:40px;opacity:.2;display:block;margin:0 auto 12px}
.corr-empty h3{font-size:15px;font-weight:600;margin-bottom:4px}
.corr-empty p{font-size:13px;color:#6b7280}
.corr-row[data-hidden]{display:none}

/* ── Dark mode ─────────────────────────────────────────────── */
html[data-theme="dark"] .corr-stat{background:#0d1b2a;border-color:rgba(96,165,250,.13)}
html[data-theme="dark"] .corr-stat-l{color:#64748b}
html[data-theme="dark"] .corr-srch-wrap input{background:rgba(255,255,255,.04);border-color:rgba(96,165,250,.18);color:#e2e8f0}
html[data-theme="dark"] .corr-srch-wrap input::placeholder{color:rgba(148,163,184,.5)}
html[data-theme="dark"] .corr-srch-ico{color:#64748b}
html[data-theme="dark"] .corr-chip{background:rgba(255,255,255,.05);border-color:rgba(96,165,250,.15);color:#94a3b8}
html[data-theme="dark"] .corr-chip:hover{border-color:rgba(32,212,131,.3);color:#20d483}
html[data-theme="dark"] .corr-chip.is-active{background:rgba(32,212,131,.12);border-color:rgba(32,212,131,.3);color:#20d483}
html[data-theme="dark"] .corr-table thead th{background:rgba(255,255,255,.03);color:#64748b;border-bottom-color:rgba(96,165,250,.1)}
html[data-theme="dark"] .corr-table tbody tr{border-bottom-color:rgba(96,165,250,.08)}
html[data-theme="dark"] .corr-table tbody tr:hover{background:rgba(32,212,131,.05)}
html[data-theme="dark"] .corr-esub,.corr-time-orig{color:#64748b}
html[data-theme="dark"] .corr-esub{color:#64748b}
html[data-theme="dark"] .corr-time-orig{color:#64748b}
html[data-theme="dark"] .corr-time-new{color:#20d483}
html[data-theme="dark"] .corr-bdg.pending  {background:rgba(217,119,6,.15);color:#fcd34d}
html[data-theme="dark"] .corr-bdg.approved {background:rgba(22,163,74,.15);color:#86efac}
html[data-theme="dark"] .corr-bdg.rejected {background:rgba(220,38,38,.15);color:#fca5a5}
html[data-theme="dark"] .corr-bdg.cancelled{background:rgba(255,255,255,.05);color:#64748b}
html[data-theme="dark"] .ba-c-ok {background:rgba(22,163,74,.18);color:#86efac}
html[data-theme="dark"] .ba-c-ok:hover{background:#16a34a;color:#fff}
html[data-theme="dark"] .ba-c-rej{background:rgba(220,38,38,.18);color:#fca5a5}
html[data-theme="dark"] .ba-c-rej:hover{background:#dc2626;color:#fff}
html[data-theme="dark"] .ba-c-eye{background:rgba(255,255,255,.06);color:#94a3b8;border-color:rgba(96,165,250,.15)}
html[data-theme="dark"] .ba-c-eye:hover{background:rgba(255,255,255,.1);color:#e2e8f0}
html[data-theme="dark"] .corr-empty p{color:#64748b}

@media(max-width:640px){
    .corr-stats{grid-template-columns:1fr 1fr}
    .corr-srch-wrap{max-width:100%;flex:1 1 100%}
}
</style>

<!-- Header -->
<div class="page-header d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h1>Correction Requests</h1>
        <p class="subtitle">Review and approve attendance time corrections</p>
    </div>
    <a href="/admin/attendance" class="btn btn-soft btn-sm">
        <i data-lucide="arrow-left" style="width:14px;height:14px" class="me-1"></i>Back to Attendance
    </a>
</div>

<?php include config('app.paths.views') . '/partials/alerts.php'; ?>

<!-- Stat tiles -->
<div class="corr-stats">
    <div class="corr-stat c-amber">
        <div class="corr-stat-n"><?= $pending ?></div>
        <div class="corr-stat-l">Pending</div>
    </div>
    <div class="corr-stat c-green">
        <div class="corr-stat-n"><?= $approved ?></div>
        <div class="corr-stat-l">Approved</div>
    </div>
    <div class="corr-stat c-red">
        <div class="corr-stat-n"><?= $rejected ?></div>
        <div class="corr-stat-l">Rejected</div>
    </div>
    <div class="corr-stat c-blue">
        <div class="corr-stat-n"><?= $pending + $approved + $rejected ?></div>
        <div class="corr-stat-l">Total</div>
    </div>
</div>

<!-- Filters -->
<div class="corr-filters">
    <div class="corr-srch-wrap">
        <span class="corr-srch-ico"><i data-lucide="search" style="width:13px;height:13px;stroke-width:2"></i></span>
        <input type="text" id="corrSearch" placeholder="Search employees…" aria-label="Search employees">
    </div>
    <div class="corr-chips">
        <a href="/admin/attendance/corrections" class="corr-chip <?= $currentStatus === '' ? 'is-active' : '' ?>">
            All <strong><?= $pending + $approved + $rejected ?></strong>
        </a>
        <a href="/admin/attendance/corrections?status=pending" class="corr-chip <?= $currentStatus === 'pending' ? 'is-active' : '' ?>">
            Pending <strong><?= $pending ?></strong>
        </a>
        <a href="/admin/attendance/corrections?status=approved" class="corr-chip <?= $currentStatus === 'approved' ? 'is-active' : '' ?>">
            Approved <strong><?= $approved ?></strong>
        </a>
        <a href="/admin/attendance/corrections?status=rejected" class="corr-chip <?= $currentStatus === 'rejected' ? 'is-active' : '' ?>">
            Rejected <strong><?= $rejected ?></strong>
        </a>
    </div>
</div>

<!-- Table card -->
<div class="card ems-card">
    <div class="table-responsive">
        <table class="corr-table" id="corrTable">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Date</th>
                    <th>Time correction</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="corrTbody">
            <?php if (empty($records['data'])): ?>
                <tr>
                    <td colspan="6">
                        <div class="corr-empty">
                            <i data-lucide="clock-x"></i>
                            <h3>No correction requests</h3>
                            <p><?= $currentStatus ? 'No ' . e($currentStatus) . ' corrections found.' : 'All attendance records look accurate.' ?></p>
                        </div>
                    </td>
                </tr>
            <?php else: foreach ($records['data'] as $row):
                $isPending = ($row['status'] ?? '') === 'pending';
                $origIn  = fmt_time($row['original_check_in_at'] ?? null);
                $origOut = fmt_time($row['original_check_out_at'] ?? null);
                $newIn   = fmt_time($row['requested_check_in_at'] ?? null);
                $newOut  = fmt_time($row['requested_check_out_at'] ?? null);
                $hasCorrIn  = ($newIn  !== '—' && $newIn  !== $origIn);
                $hasCorrOut = ($newOut !== '—' && $newOut !== $origOut);
            ?>
                <tr class="corr-row" data-name="<?= e(strtolower($row['employee_name'] ?? '')) ?>">
                    <td>
                        <div class="corr-ecell">
                            <?= corr_avatar($row['employee_name'] ?? '', $avPalettes) ?>
                            <div>
                                <div class="corr-ename"><?= e($row['employee_name'] ?? '') ?></div>
                                <div class="corr-esub"><?= e($row['employee_code'] ?? '') ?></div>
                            </div>
                        </div>
                    </td>
                    <td style="font-size:13px;color:var(--ems-text-muted);font-variant-numeric:tabular-nums;white-space:nowrap">
                        <?= e(format_date($row['attendance_date'] ?? null)) ?>
                    </td>
                    <td>
                        <?php if ($hasCorrIn): ?>
                        <div class="corr-times mb-1">
                            <span class="corr-time-orig"><?= e($origIn) ?></span>
                            <span class="corr-time-arr"><i data-lucide="chevron-right"></i></span>
                            <span class="corr-time-new"><?= e($newIn) ?></span>
                            <span style="font-size:10.5px;color:var(--ems-text-muted);margin-left:2px">In</span>
                        </div>
                        <?php endif; ?>
                        <?php if ($hasCorrOut): ?>
                        <div class="corr-times">
                            <span class="corr-time-orig"><?= e($origOut) ?></span>
                            <span class="corr-time-arr"><i data-lucide="chevron-right"></i></span>
                            <span class="corr-time-new"><?= e($newOut) ?></span>
                            <span style="font-size:10.5px;color:var(--ems-text-muted);margin-left:2px">Out</span>
                        </div>
                        <?php endif; ?>
                        <?php if (!$hasCorrIn && !$hasCorrOut): ?>
                        <span style="color:var(--ems-text-muted);font-size:12.5px"><?= e($newIn !== '—' ? $newIn : ($newOut !== '—' ? $newOut : '—')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:12.5px;color:var(--ems-text-muted);max-width:200px"><?= e($row['reason'] ?? '') ?></td>
                    <td>
                        <span class="corr-bdg <?= e($row['status'] ?? '') ?>">
                            <span class="corr-bdg-dot"></span>
                            <?= e(ucfirst($row['status'] ?? '')) ?>
                        </span>
                    </td>
                    <td>
                        <div class="corr-acts">
                            <?php if ($isPending && can('attendance.correct')): ?>
                                <form method="POST" action="/admin/attendance/corrections/<?= (int) $row['id'] ?>/approve">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="_back" value="<?= e($backUrl) ?>">
                                    <button type="submit" class="ba-corr ba-c-ok">
                                        <i data-lucide="check"></i> Approve
                                    </button>
                                </form>
                                <form method="POST" action="/admin/attendance/corrections/<?= (int) $row['id'] ?>/reject">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="_back" value="<?= e($backUrl) ?>">
                                    <button type="submit" class="ba-corr ba-c-rej">
                                        <i data-lucide="x"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                            <a href="/admin/attendance/<?= (int) $row['attendance_id'] ?>" class="ba-corr ba-c-eye">
                                <i data-lucide="eye"></i> Open
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (!empty($records['data'])): ?>
    <div class="px-3 py-2 border-top d-flex align-items-center justify-content-between" style="background:var(--ems-card-bg-alt,#f9fafb);font-size:12px;color:var(--ems-text-muted)">
        <span id="corrCount"><?= $total ?> request<?= $total !== 1 ? 's' : '' ?></span>
        <?php if (($records['last_page'] ?? 1) > 1): ?>
        <div><?= paginate_links($records, '/admin/attendance/corrections?status=' . urlencode($currentStatus)) ?></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
(function () {
    var input = document.getElementById('corrSearch');
    var tbody = document.getElementById('corrTbody');
    var count = document.getElementById('corrCount');
    if (!input || !tbody) return;
    input.addEventListener('input', function () {
        var q = this.value.toLowerCase().trim();
        var rows = tbody.querySelectorAll('tr.corr-row');
        var vis = 0;
        rows.forEach(function (r) {
            var match = !q || (r.dataset.name || '').includes(q);
            r.toggleAttribute('data-hidden', !match);
            if (match) vis++;
        });
        if (count) count.textContent = vis + ' request' + (vis !== 1 ? 's' : '');
    });
})();
</script>
