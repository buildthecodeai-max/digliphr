<?php
/** @var array $items */
/** @var array $counts */
/** @var string|null $module */
/** @var int $companyId */

// Avatar colour palette (bg, text) — index derived from name
$avPalettes = [
    ['#DBEAFE', '#1E40AF'], ['#D1FAE5', '#065F46'], ['#FEF3C7', '#92400E'],
    ['#FCE7F3', '#9D174D'], ['#EDE9FE', '#5B21B6'], ['#FFEDD5', '#9A3412'],
    ['#CFFAFE', '#164E63'], ['#F0FDF4', '#14532D'],
];
function approval_avatar(string $name, array $palettes): string {
    $idx = 0;
    foreach (str_split($name) as $ch) { $idx = ($idx * 31 + ord($ch)) & 0x7fffffff; }
    [$bg, $fg] = $palettes[$idx % count($palettes)];
    $ini = implode('', array_map(fn($p) => strtoupper($p[0] ?? ''), array_slice(explode(' ', trim($name)), 0, 2)));
    return '<div class="appr-avatar" style="background:' . e($bg) . ';color:' . e($fg) . '">' . e($ini ?: '?') . '</div>';
}

// Module meta: label, icon, colours for badge, approve/reject routes
$moduleMeta = [
    'leave'      => ['label' => 'Leave',       'icon' => 'calendar-days', 'cls' => 'mod-leave',      'can_inline' => true,  'approve_url' => '/admin/leave/%d/approve',                  'reject_url' => '/admin/leave/%d/reject'],
    'attendance' => ['label' => 'Correction',  'icon' => 'clock',         'cls' => 'mod-attendance', 'can_inline' => true,  'approve_url' => '/admin/attendance/corrections/%d/approve', 'reject_url' => '/admin/attendance/corrections/%d/reject'],
    'overtime'   => ['label' => 'Overtime',    'icon' => 'timer',         'cls' => 'mod-overtime',   'can_inline' => true,  'approve_url' => '/admin/overtime/%d/approve',               'reject_url' => '/admin/overtime/%d/reject'],
    'loan'       => ['label' => 'Loan',        'icon' => 'banknote',      'cls' => 'mod-loan',       'can_inline' => false, 'approve_url' => null, 'reject_url' => null],
    'payroll'    => ['label' => 'Payroll',     'icon' => 'receipt-text',  'cls' => 'mod-payroll',    'can_inline' => false, 'approve_url' => null, 'reject_url' => null],
];

$total   = count($items);
$pending = $counts; // already filtered to what this admin can see
$backUrl = '/admin/approvals?company_id=' . (int) $companyId . ($module ? '&module=' . urlencode((string) $module) : '');
?>
<style>
/* ── Approvals page ───────────────────────────────────────── */
.appr-stats{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px;margin-bottom:20px}
.appr-stat{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:14px 16px}
.appr-stat-n{font-size:28px;font-weight:700;line-height:1;font-variant-numeric:tabular-nums;margin-bottom:3px}
.appr-stat-l{font-size:11.5px;color:#6b7280;font-weight:500}
.appr-stat.c-red    .appr-stat-n{color:#ef4444}
.appr-stat.c-blue   .appr-stat-n{color:#3b82f6}
.appr-stat.c-green  .appr-stat-n{color:#16a34a}
.appr-stat.c-amber  .appr-stat-n{color:#d97706}
.appr-stat.c-purple .appr-stat-n{color:#9333ea}

/* Filter row */
.appr-filters{display:flex;align-items:center;gap:8px;margin-bottom:14px;flex-wrap:wrap}
.appr-search-wrap{position:relative;flex:1;min-width:160px;max-width:280px}
.appr-search-wrap input{width:100%;padding:7px 10px 7px 32px;background:#fff;border:1px solid #e5e7eb;border-radius:8px;font-size:13px;color:#111;outline:none;transition:border-color .15s;font-family:inherit}
.appr-search-wrap input:focus{border-color:#6366f1}
.appr-search-wrap input::placeholder{color:#9ca3af}
.appr-search-icon{position:absolute;left:9px;top:50%;transform:translateY(-50%);color:#9ca3af;pointer-events:none;display:flex;align-items:center}
.appr-chips{display:flex;gap:5px;flex-wrap:wrap}
.appr-chip{padding:5px 11px;background:#fff;border:1px solid #e5e7eb;border-radius:20px;font-size:12px;font-weight:500;text-decoration:none;color:#6b7280;transition:all .12s;white-space:nowrap;display:inline-flex;align-items:center;gap:5px}
.appr-chip:hover{border-color:#6366f1;color:#6366f1}
.appr-chip.is-active{background:rgba(99,102,241,.1);border-color:#6366f1;color:#6366f1;font-weight:600}
.appr-chip strong{font-variant-numeric:tabular-nums}

/* Table */
.appr-table{width:100%;border-collapse:collapse;min-width:640px}
.appr-table thead th{background:#f9fafb;padding:9px 14px;text-align:left;font-size:10.5px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:#6b7280;border-bottom:1px solid #e5e7eb;white-space:nowrap}
.appr-table tbody tr{border-bottom:1px solid #e5e7eb;transition:background .1s}
.appr-table tbody tr:last-child{border-bottom:none}
.appr-table tbody tr:hover{background:#f9fafb}
.appr-table tbody td{padding:12px 14px;font-size:13.5px;vertical-align:middle}

/* Avatar */
.appr-avatar{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0;letter-spacing:-.3px}
.appr-ecell{display:flex;align-items:center;gap:9px}
.appr-ename{font-weight:600;font-size:13.5px;line-height:1.2}
.appr-esub{font-size:11.5px;color:#6b7280;margin-top:1px}

/* Module badges */
.appr-mod{display:inline-flex;align-items:center;gap:5px;padding:3px 8px;border-radius:6px;font-size:11.5px;font-weight:600;white-space:nowrap}
.appr-mod i[data-lucide]{width:12px;height:12px;stroke-width:2}
.mod-leave     {background:#EEF2FF;color:#4338CA}
.mod-attendance{background:#FFFBEB;color:#B45309}
.mod-overtime  {background:#FFF7ED;color:#C2410C}
.mod-loan      {background:#F0FDF4;color:#15803D}
.mod-payroll   {background:#FAF5FF;color:#7E22CE}

/* Age */
.appr-age{font-size:12.5px;white-space:nowrap}
.appr-age.urgent{color:#ef4444;font-weight:600}
.appr-age.fresh{color:#6b7280}

/* Buttons */
.appr-acts{display:flex;align-items:center;gap:5px;justify-content:flex-end}
.appr-acts form{display:inline}
.ba-appr{display:inline-flex;align-items:center;gap:4px;padding:5px 10px;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;border:none;transition:all .12s;white-space:nowrap;font-family:inherit;text-decoration:none}
.ba-appr i[data-lucide]{width:11px;height:11px;stroke-width:2.5}
.ba-ok {background:#DCFCE7;color:#15803D} .ba-ok:hover{background:#16a34a;color:#fff}
.ba-rej{background:#FEE2E2;color:#B91C1C} .ba-rej:hover{background:#dc2626;color:#fff}
.ba-rev{background:#f3f4f6;color:#374151;border:1px solid #e5e7eb} .ba-rev:hover{background:#e5e7eb;color:#111}

/* Empty */
.appr-empty{padding:52px 20px;text-align:center}
.appr-empty-icon{width:42px;height:42px;opacity:.25;display:block;margin:0 auto 12px}
.appr-empty h3{font-size:15px;font-weight:600;margin-bottom:4px}
.appr-empty p{font-size:13px;color:#6b7280}
.appr-row[data-hidden]{display:none}

/* ── Dark mode ─────────────────────────────────────────────── */
html[data-theme="dark"] .appr-stat{background:#0d1b2a;border-color:rgba(96,165,250,.13)}
html[data-theme="dark"] .appr-stat-l{color:#64748b}
html[data-theme="dark"] .appr-search-wrap input{background:rgba(255,255,255,.04);border-color:rgba(96,165,250,.18);color:#e2e8f0}
html[data-theme="dark"] .appr-search-wrap input::placeholder{color:rgba(148,163,184,.5)}
html[data-theme="dark"] .appr-search-icon{color:#64748b}
html[data-theme="dark"] .appr-chip{background:rgba(255,255,255,.05);border-color:rgba(96,165,250,.15);color:#94a3b8}
html[data-theme="dark"] .appr-chip:hover{border-color:rgba(32,212,131,.3);color:#20d483}
html[data-theme="dark"] .appr-chip.is-active{background:rgba(32,212,131,.12);border-color:rgba(32,212,131,.3);color:#20d483}
html[data-theme="dark"] .appr-table thead th{background:rgba(255,255,255,.03);color:#64748b;border-bottom-color:rgba(96,165,250,.1)}
html[data-theme="dark"] .appr-table tbody tr{border-bottom-color:rgba(96,165,250,.08)}
html[data-theme="dark"] .appr-table tbody tr:hover{background:rgba(32,212,131,.05)}
html[data-theme="dark"] .appr-esub{color:#64748b}
html[data-theme="dark"] .mod-leave     {background:rgba(99,102,241,.15);color:#a5b4fc}
html[data-theme="dark"] .mod-attendance{background:rgba(245,158,11,.15);color:#fcd34d}
html[data-theme="dark"] .mod-overtime  {background:rgba(249,115,22,.15);color:#fdba74}
html[data-theme="dark"] .mod-loan      {background:rgba(34,197,94,.15);color:#86efac}
html[data-theme="dark"] .mod-payroll   {background:rgba(168,85,247,.15);color:#d8b4fe}
html[data-theme="dark"] .appr-age.fresh{color:#64748b}
html[data-theme="dark"] .ba-ok {background:rgba(22,163,74,.18);color:#86efac}
html[data-theme="dark"] .ba-ok:hover{background:#16a34a;color:#fff}
html[data-theme="dark"] .ba-rej{background:rgba(220,38,38,.18);color:#fca5a5}
html[data-theme="dark"] .ba-rej:hover{background:#dc2626;color:#fff}
html[data-theme="dark"] .ba-rev{background:rgba(255,255,255,.06);color:#94a3b8;border-color:rgba(96,165,250,.15)}
html[data-theme="dark"] .ba-rev:hover{background:rgba(255,255,255,.1);color:#e2e8f0}
html[data-theme="dark"] .appr-empty p{color:#64748b}
</style>

<!-- Page header -->
<div class="page-header d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h1>Approval Inbox</h1>
        <p class="subtitle">One queue for every decision waiting on your team.</p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-soft btn-sm" href="/admin/approval-chains?company_id=<?= (int) $companyId ?>">
            <i data-lucide="git-branch" class="me-1"></i>Approval chains
        </a>
        <form method="POST" action="/admin/approvals/remind">
            <?= csrf_field() ?>
            <input type="hidden" name="company_id" value="<?= (int) $companyId ?>">
            <button class="btn btn-primary btn-sm">
                <i data-lucide="bell-ring" class="me-1"></i>Send reminders
            </button>
        </form>
    </div>
</div>

<?php include config('app.paths.views') . '/partials/alerts.php'; ?>

<!-- Stat tiles -->
<div class="appr-stats">
    <div class="appr-stat <?= $total > 0 ? 'c-red' : '' ?>">
        <div class="appr-stat-n"><?= $total ?></div>
        <div class="appr-stat-l">Total pending</div>
    </div>
    <?php foreach (['leave' => ['Leave', 'c-blue'], 'attendance' => ['Corrections', 'c-amber'], 'overtime' => ['Overtime', 'c-amber'], 'loan' => ['Loans', 'c-green'], 'payroll' => ['Payroll', 'c-purple']] as $k => [$lbl, $cls]): ?>
        <?php if (($counts[$k] ?? 0) > 0): ?>
        <div class="appr-stat <?= $cls ?>">
            <div class="appr-stat-n"><?= (int) ($counts[$k] ?? 0) ?></div>
            <div class="appr-stat-l"><?= $lbl ?></div>
        </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="appr-filters">
    <div class="appr-search-wrap">
        <span class="appr-search-icon"><i data-lucide="search" style="width:13px;height:13px;stroke-width:2"></i></span>
        <input type="text" id="apprSearch" placeholder="Search employees…" aria-label="Search employees">
    </div>
    <div class="appr-chips">
        <a href="/admin/approvals?company_id=<?= (int) $companyId ?>"
           class="appr-chip <?= !$module ? 'is-active' : '' ?>">
            All <strong><?= $total ?></strong>
        </a>
        <?php foreach ($counts as $key => $count): ?>
            <?php $meta = $moduleMeta[$key] ?? null; if (!$meta) continue; ?>
            <a href="/admin/approvals?company_id=<?= (int) $companyId ?>&module=<?= urlencode($key) ?>"
               class="appr-chip <?= $module === $key ? 'is-active' : '' ?>">
                <?= e($meta['label']) ?> <strong><?= (int) $count ?></strong>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Saved filters -->
<?php $savedModule = 'approvals'; $currentFilters = ['company_id' => $companyId, 'module' => $module]; ?>
<?php include config('app.paths.views') . '/partials/saved-filters.php'; ?>

<!-- Table card -->
<div class="card ems-card">
    <div class="table-responsive">
        <table class="appr-table" id="apprTable">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Type</th>
                    <th>Details</th>
                    <th>Waiting</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="apprTbody">
            <?php if (!$items): ?>
                <tr>
                    <td colspan="5">
                        <div class="appr-empty">
                            <i data-lucide="inbox" class="appr-empty-icon"></i>
                            <h3>Inbox zero</h3>
                            <p>There are no approvals matching this view.</p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            <?php foreach ($items as $item):
                $meta = $moduleMeta[$item['module']] ?? ['label' => ucfirst($item['module']), 'icon' => 'circle', 'cls' => '', 'can_inline' => false, 'approve_url' => null, 'reject_url' => null];
                $ageH = (int) $item['age_hours'];
                if ($ageH < 1)      $ageStr = 'Just now';
                elseif ($ageH < 24) $ageStr = $ageH . 'h ago';
                elseif ($ageH < 48) $ageStr = 'Yesterday';
                else                $ageStr = floor($ageH / 24) . 'd ago';
                $urgent = $ageH >= 24;
            ?>
            <tr class="appr-row" data-name="<?= e(strtolower($item['employee_name'])) ?>">
                <td>
                    <div class="appr-ecell">
                        <?= approval_avatar($item['employee_name'], $avPalettes) ?>
                        <div>
                            <div class="appr-ename"><?= e($item['employee_name']) ?></div>
                            <div class="appr-esub"><?= e($item['employee_code']) ?></div>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="appr-mod <?= e($meta['cls']) ?>">
                        <i data-lucide="<?= e($meta['icon']) ?>"></i>
                        <?= e($meta['label']) ?>
                    </span>
                </td>
                <td style="max-width:240px;font-size:13px;color:var(--ems-text-muted)"><?= e($item['detail']) ?></td>
                <td>
                    <span class="appr-age <?= $urgent ? 'urgent' : 'fresh' ?>">
                        <?php if ($urgent): ?><i data-lucide="alert-circle" style="width:12px;height:12px;vertical-align:-2px;margin-right:3px"></i><?php endif; ?>
                        <?= e($ageStr) ?>
                    </span>
                </td>
                <td>
                    <div class="appr-acts">
                        <?php if ($meta['can_inline'] && $meta['approve_url']): ?>
                            <form method="POST" action="<?= e(sprintf($meta['approve_url'], (int) $item['record_id'])) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="_back" value="<?= e($backUrl) ?>">
                                <button type="submit" class="ba-appr ba-ok">
                                    <i data-lucide="check"></i> Approve
                                </button>
                            </form>
                            <form method="POST" action="<?= e(sprintf($meta['reject_url'], (int) $item['record_id'])) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="_back" value="<?= e($backUrl) ?>">
                                <button type="submit" class="ba-appr ba-rej">
                                    <i data-lucide="x"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                        <a class="ba-appr ba-rev" href="<?= e($item['url']) ?>">
                            <i data-lucide="eye"></i> Review
                        </a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($items): ?>
    <div class="px-3 py-2 border-top d-flex align-items-center justify-content-between" style="background:var(--ems-card-bg-alt,#f9fafb);font-size:12px;color:var(--ems-text-muted)">
        <span id="apprCount"><?= count($items) ?> request<?= count($items) !== 1 ? 's' : '' ?></span>
        <a href="/admin/approval-chains?company_id=<?= (int) $companyId ?>" style="color:var(--ems-primary,#6366f1);font-weight:600;text-decoration:none">Manage chains →</a>
    </div>
    <?php endif; ?>
</div>

<script>
(function () {
    var input  = document.getElementById('apprSearch');
    var tbody  = document.getElementById('apprTbody');
    var count  = document.getElementById('apprCount');
    if (!input || !tbody) return;

    input.addEventListener('input', function () {
        var q = this.value.toLowerCase().trim();
        var rows = tbody.querySelectorAll('tr.appr-row');
        var visible = 0;
        rows.forEach(function (row) {
            var name = row.dataset.name || '';
            var match = !q || name.includes(q);
            row.toggleAttribute('data-hidden', !match);
            if (match) visible++;
        });
        if (count) count.textContent = visible + ' request' + (visible !== 1 ? 's' : '');
    });
})();
</script>
