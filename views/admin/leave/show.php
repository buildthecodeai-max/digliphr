<?php
$lr = $leaveRequest;
$isArchived = $isArchived ?? !empty($lr['deleted_at']);

$avPalettes = [
    ['#DBEAFE','#1E40AF'],['#D1FAE5','#065F46'],['#FEF3C7','#92400E'],
    ['#FCE7F3','#9D174D'],['#EDE9FE','#5B21B6'],['#FFEDD5','#9A3412'],
    ['#CFFAFE','#164E63'],['#F0FDF4','#14532D'],
];
function lv_show_av(string $name, array $pal): array {
    $idx = 0;
    foreach (str_split($name) as $ch) { $idx = ($idx * 31 + ord($ch)) & 0x7fffffff; }
    return $pal[$idx % count($pal)];
}
$empName = $lr['employee_name'] ?? '?';
[$avBg, $avFg] = lv_show_av($empName, $avPalettes);
$ini = implode('', array_map(fn($p) => strtoupper($p[0] ?? ''), array_slice(explode(' ', trim($empName)), 0, 2)));

$statusMeta = [
    'pending'   => ['label'=>'Pending',   'bg'=>'#FEF9C3','text'=>'#854D0E','dot'=>'#CA8A04','ddbg'=>'rgba(217,119,6,.15)','ddtx'=>'#fcd34d'],
    'approved'  => ['label'=>'Approved',  'bg'=>'#DCFCE7','text'=>'#14532D','dot'=>'#16A34A','ddbg'=>'rgba(22,163,74,.15)', 'ddtx'=>'#86efac'],
    'rejected'  => ['label'=>'Rejected',  'bg'=>'#FEE2E2','text'=>'#7F1D1D','dot'=>'#DC2626','ddbg'=>'rgba(220,38,38,.15)','ddtx'=>'#fca5a5'],
    'cancelled' => ['label'=>'Cancelled', 'bg'=>'#F3F4F6','text'=>'#374151','dot'=>'#6B7280','ddbg'=>'rgba(255,255,255,.07)','ddtx'=>'#94a3b8'],
    'draft'     => ['label'=>'Draft',     'bg'=>'#EEF2FF','text'=>'#312E81','dot'=>'#6366F1','ddbg'=>'rgba(99,102,241,.15)','ddtx'=>'#a5b4fc'],
    'withdrawn' => ['label'=>'Withdrawn', 'bg'=>'#FEF3C7','text'=>'#92400E','dot'=>'#D97706','ddbg'=>'rgba(217,119,6,.15)', 'ddtx'=>'#fcd34d'],
];
$sm = $statusMeta[$lr['status'] ?? 'draft'] ?? $statusMeta['draft'];

$cal    = (int)($lr['calendar_days']  ?? 0);
$weDays = (int)($lr['weekend_days']   ?? 0);
$hol    = (int)($lr['holiday_days']   ?? 0);
$charge = (float)($lr['chargeable_days'] ?? 0);
$sameDay = ($lr['start_date'] ?? '') === ($lr['end_date'] ?? '');
?>
<style>
/* ── Leave detail page ───────────────────────────────────── */
.lv-show-header{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px 22px;margin-bottom:18px;display:flex;align-items:center;gap:16px;flex-wrap:wrap}
.lv-show-avatar{width:52px;height:52px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:17px;font-weight:800;flex-shrink:0;letter-spacing:-.5px}
.lv-show-name{font-size:18px;font-weight:700;line-height:1.2;margin-bottom:2px}
.lv-show-sub{font-size:13px;color:#6b7280}
.lv-show-bdg{display:inline-flex;align-items:center;gap:5px;padding:4px 11px;border-radius:20px;font-size:12px;font-weight:700}
.lv-show-dot{width:6px;height:6px;border-radius:50%;flex-shrink:0}
.lv-show-header-actions{margin-left:auto;display:flex;gap:8px;flex-wrap:wrap;align-items:center}

/* Info grid */
.lv-info-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px 20px}
.lv-info-item{}
.lv-info-label{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:#6b7280;margin-bottom:3px}
.lv-info-val{font-size:13.5px;font-weight:500}

/* Days breakdown */
.lv-days-row{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
.lv-day-chip{padding:4px 10px;border-radius:8px;font-size:12px;font-weight:600;font-variant-numeric:tabular-nums;white-space:nowrap}
.lv-day-chip.charge{background:#DCFCE7;color:#15803D}
.lv-day-chip.cal{background:#EEF2FF;color:#3730A3}
.lv-day-chip.we{background:#F3F4F6;color:#374151}
.lv-day-chip.hol{background:#FEF3C7;color:#92400E}

/* Action sidebar card */
.lv-action-card{background:#fff;border:1px solid #e5e7eb;border-radius:10px;margin-bottom:14px;overflow:hidden}
.lv-action-card-header{padding:11px 16px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#6b7280;border-bottom:1px solid #e5e7eb;background:#f9fafb}
.lv-action-card-body{padding:14px 16px}

/* Approval history table */
.lv-hist-table{width:100%;border-collapse:collapse;font-size:12.5px}
.lv-hist-table th{padding:7px 10px;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#6b7280;border-bottom:1px solid #e5e7eb;white-space:nowrap}
.lv-hist-table td{padding:9px 10px;border-bottom:1px solid #f3f4f6;vertical-align:middle}
.lv-hist-table tr:last-child td{border-bottom:none}

/* Amendment list */
.lv-amend-item{padding:9px 0;border-bottom:1px solid #f3f4f6}
.lv-amend-item:last-child{border-bottom:none}
.lv-amend-title{font-size:13px;font-weight:600}
.lv-amend-meta{font-size:11.5px;color:#6b7280;margin-top:2px}

/* Type pill */
.lv-type-pill{display:inline-block;padding:3px 10px;border-radius:6px;font-size:12.5px;font-weight:700;background:#EEF2FF;color:#4338CA}

/* ── Dark mode ─────────────────────────────────────────────── */
html[data-theme="dark"] .lv-show-header{background:#0d1b2a;border-color:rgba(96,165,250,.13)}
html[data-theme="dark"] .lv-show-sub{color:#64748b}
html[data-theme="dark"] .lv-info-label{color:#64748b}
html[data-theme="dark"] .lv-day-chip.charge{background:rgba(22,163,74,.15);color:#86efac}
html[data-theme="dark"] .lv-day-chip.cal{background:rgba(99,102,241,.15);color:#a5b4fc}
html[data-theme="dark"] .lv-day-chip.we{background:rgba(255,255,255,.06);color:#94a3b8}
html[data-theme="dark"] .lv-day-chip.hol{background:rgba(217,119,6,.15);color:#fcd34d}
html[data-theme="dark"] .lv-action-card{background:#0d1b2a;border-color:rgba(96,165,250,.13)}
html[data-theme="dark"] .lv-action-card-header{background:rgba(255,255,255,.03);color:#64748b;border-bottom-color:rgba(96,165,250,.1)}
html[data-theme="dark"] .lv-hist-table th{color:#64748b;border-bottom-color:rgba(96,165,250,.1)}
html[data-theme="dark"] .lv-hist-table td{border-bottom-color:rgba(96,165,250,.06)}
html[data-theme="dark"] .lv-amend-meta{color:#64748b}
html[data-theme="dark"] .lv-amend-item{border-bottom-color:rgba(96,165,250,.08)}
html[data-theme="dark"] .lv-type-pill{background:rgba(99,102,241,.15);color:#a5b4fc}
html[data-theme="dark"] .lv-show-bdg.pending  {background:rgba(217,119,6,.18)!important;color:#fcd34d!important}
html[data-theme="dark"] .lv-show-bdg.approved {background:rgba(22,163,74,.18)!important;color:#86efac!important}
html[data-theme="dark"] .lv-show-bdg.rejected {background:rgba(220,38,38,.18)!important;color:#fca5a5!important}
html[data-theme="dark"] .lv-show-bdg.cancelled{background:rgba(255,255,255,.07)!important;color:#94a3b8!important}
html[data-theme="dark"] .lv-show-bdg.draft    {background:rgba(99,102,241,.18)!important;color:#a5b4fc!important}
html[data-theme="dark"] .lv-show-bdg.withdrawn{background:rgba(217,119,6,.18)!important;color:#fcd34d!important}
</style>

<?php include config('app.paths.views') . '/partials/alerts.php'; ?>

<!-- Header card -->
<div class="lv-show-header">
    <div class="lv-show-avatar" style="background:<?= e($avBg) ?>;color:<?= e($avFg) ?>"><?= e($ini ?: '?') ?></div>
    <div>
        <div class="lv-show-name"><?= e($empName) ?>
            <?php if (!empty($lr['employee_code'])): ?>
            <span style="font-size:13px;font-weight:500;opacity:.6;margin-left:6px"><?= e($lr['employee_code']) ?></span>
            <?php endif; ?>
        </div>
        <div class="lv-show-sub">
            <?php
            $meta = [];
            if (!empty($lr['department_name'])) $meta[] = e($lr['department_name']);
            if (!empty($lr['leave_type_name'])) $meta[] = '<span class="lv-type-pill">' . e($lr['leave_type_name']) . '</span>';
            echo implode(' &middot; ', $meta);
            ?>
        </div>
    </div>
    <div class="lv-show-header-actions">
        <?php $statusKey = strtolower($lr['status'] ?? 'draft'); ?>
        <span class="lv-show-bdg <?= e($statusKey) ?>" style="background:<?= e($sm['bg']) ?>;color:<?= e($sm['text']) ?>">
            <span class="lv-show-dot" style="background:<?= e($sm['dot']) ?>"></span>
            <?= e($sm['label']) ?>
        </span>
        <?php if ($isArchived): ?>
        <span class="badge bg-secondary">Archived</span>
        <?php endif; ?>
        <?php if (!$isArchived && can('leave.edit') && in_array($lr['status'] ?? '', ['draft','pending','approved'], true)): ?>
        <a href="/admin/leave/<?= (int)$lr['id'] ?>/edit" class="btn btn-soft btn-sm">
            <i data-lucide="pencil" style="width:13px;height:13px" class="me-1"></i>Edit
        </a>
        <?php endif; ?>
        <a href="<?= !empty($isArchived) ? '/admin/leave/archived' : '/admin/leave' ?>" class="btn btn-soft btn-sm">
            <i data-lucide="arrow-left" style="width:13px;height:13px" class="me-1"></i>Back
        </a>
    </div>
</div>

<div class="row g-3">
    <!-- Main column -->
    <div class="col-lg-8">

        <!-- Leave details -->
        <div class="card">
            <div class="card-header fw-semibold py-2" style="font-size:13px">Leave Details</div>
            <div class="card-body">
                <div class="lv-info-grid">
                    <div class="lv-info-item">
                        <div class="lv-info-label">Period</div>
                        <div class="lv-info-val">
                            <?= e(format_date($lr['start_date'] ?? null)) ?>
                            <?php if (!$sameDay): ?>
                            <span style="opacity:.5;margin:0 5px">→</span><?= e(format_date($lr['end_date'] ?? null)) ?>
                            <?php endif; ?>
                            <?php if (!empty($lr['is_half_day'])): ?>
                            <span style="font-size:11.5px;color:#6b7280;margin-left:6px">(<?= e($lr['half_day_type'] ?? 'half') ?>)</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="lv-info-item">
                        <div class="lv-info-label">Department</div>
                        <div class="lv-info-val"><?= e($lr['department_name'] ?? '—') ?></div>
                    </div>
                    <div class="lv-info-item">
                        <div class="lv-info-label">Applied On</div>
                        <div class="lv-info-val"><?= e(format_datetime($lr['applied_at'] ?? $lr['created_at'] ?? null)) ?></div>
                    </div>
                    <?php if (!empty($lr['handover_name'])): ?>
                    <div class="lv-info-item">
                        <div class="lv-info-label">Handover To</div>
                        <div class="lv-info-val"><?= e($lr['handover_name']) ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="lv-info-item col-span-2" style="grid-column:1/-1">
                        <div class="lv-info-label">Reason</div>
                        <div class="lv-info-val" style="line-height:1.6"><?= nl2br(e($lr['reason'] ?? '—')) ?></div>
                    </div>
                </div>

                <!-- Days breakdown -->
                <div class="lv-days-row">
                    <span class="lv-day-chip charge"><?= e((string)$charge) ?> chargeable day<?= $charge != 1 ? 's' : '' ?></span>
                    <?php if ($cal > 0): ?><span class="lv-day-chip cal"><?= $cal ?> calendar</span><?php endif; ?>
                    <?php if ($weDays > 0): ?><span class="lv-day-chip we"><?= $weDays ?> weekend</span><?php endif; ?>
                    <?php if ($hol > 0): ?><span class="lv-day-chip hol"><?= $hol ?> holiday</span><?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Attachments -->
        <?php if (!empty($attachments)): ?>
        <div class="card mt-3">
            <div class="card-header fw-semibold py-2" style="font-size:13px">
                <i data-lucide="paperclip" style="width:13px;height:13px" class="me-1"></i>Attached Documents
            </div>
            <ul class="list-group list-group-flush">
                <?php foreach ($attachments as $att): ?>
                <li class="list-group-item d-flex align-items-center gap-2 py-2 px-3">
                    <i data-lucide="file" style="width:14px;height:14px;flex-shrink:0;opacity:.5"></i>
                    <span style="font-size:13px" class="text-truncate flex-grow-1"><?= e($att['original_filename'] ?? $att['filename']) ?></span>
                    <a href="/files/leave-attachment/<?= (int)$att['id'] ?>" target="_blank" class="btn btn-soft btn-sm" style="font-size:11px;white-space:nowrap">
                        <i data-lucide="eye" style="width:11px;height:11px"></i> View
                    </a>
                    <a href="/files/leave-attachment/<?= (int)$att['id'] ?>?download=1" class="btn btn-soft btn-sm" style="font-size:11px;white-space:nowrap">
                        <i data-lucide="download" style="width:11px;height:11px"></i> Download
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Approval history -->
        <?php if (!empty($approvals)): ?>
        <div class="card mt-3">
            <div class="card-header fw-semibold py-2" style="font-size:13px">Approval History</div>
            <div class="table-responsive">
                <table class="lv-hist-table">
                    <thead><tr><th>Level</th><th>Approver</th><th>Status</th><th>Comments</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach ($approvals as $a): ?>
                    <tr>
                        <td><?= (int)($a['level'] ?? 1) ?></td>
                        <td><?= e($a['approver_name'] ?? '—') ?></td>
                        <td><?= status_badge($a['status']) ?></td>
                        <td style="color:#6b7280;max-width:200px"><?= e($a['comments'] ?? '—') ?></td>
                        <td style="white-space:nowrap"><?= e(format_datetime($a['actioned_at'] ?? $a['created_at'] ?? null)) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Extensions -->
        <?php if (!empty($extensions)): ?>
        <div class="card mt-3">
            <div class="card-header fw-semibold py-2" style="font-size:13px">Extensions</div>
            <div class="table-responsive">
                <table class="lv-hist-table">
                    <thead><tr><th>Original End</th><th>New End</th><th>Days Added</th><th>Reason</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($extensions as $ex): ?>
                    <tr>
                        <td><?= e(format_date($ex['original_end_date'] ?? null)) ?></td>
                        <td><?= e(format_date($ex['new_end_date'] ?? null)) ?></td>
                        <td><?= e((string)($ex['additional_days'] ?? '')) ?></td>
                        <td style="color:#6b7280"><?= e($ex['reason'] ?? '') ?></td>
                        <td><?= status_badge($ex['status'] ?? 'approved') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Amendment history -->
        <?php if (!empty($amendments)): ?>
        <div class="lv-action-card mt-3" id="history">
            <div class="lv-action-card-header">Amendment History</div>
            <div class="lv-action-card-body" style="padding:8px 16px">
                <?php foreach ($amendments as $am): ?>
                <div class="lv-amend-item">
                    <div class="lv-amend-title"><?= e($am['reason'] ?? 'Amendment') ?></div>
                    <div class="lv-amend-meta">
                        <?= e(format_datetime($am['created_at'] ?? null)) ?>
                        <?= !empty($am['changed_by_name']) ? ' · ' . e($am['changed_by_name']) : '' ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- Sidebar -->
    <div class="col-lg-4">

        <!-- Approve / Reject -->
        <?php if (!$isArchived && ($lr['status'] ?? '') === 'pending' && can('leave.approve')): ?>
        <div class="lv-action-card" id="approve">
            <div class="lv-action-card-header">Approve / Reject</div>
            <div class="lv-action-card-body">
                <form method="POST" action="/admin/leave/<?= (int)$lr['id'] ?>/approve" class="mb-3">
                    <?= csrf_field() ?>
                    <label class="form-label small mb-1" style="font-size:11.5px;color:#6b7280">Approval comments (optional)</label>
                    <textarea name="comments" class="form-control form-control-sm mb-2" rows="2" style="font-size:13px"></textarea>
                    <button class="btn btn-success btn-sm w-100">
                        <i data-lucide="check" style="width:13px;height:13px" class="me-1"></i>Approve
                    </button>
                </form>
                <?php if (can('leave.reject')): ?>
                <form method="POST" action="/admin/leave/<?= (int)$lr['id'] ?>/reject" id="rejectForm">
                    <?= csrf_field() ?>
                    <label class="form-label small mb-1" style="font-size:11.5px;color:#6b7280">Rejection reason <span style="color:#dc2626">*</span></label>
                    <textarea name="comments" class="form-control form-control-sm mb-2" rows="2" style="font-size:13px" required id="rejectReason"></textarea>
                    <button class="btn btn-outline-danger btn-sm w-100">
                        <i data-lucide="x" style="width:13px;height:13px" class="me-1"></i>Reject
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Cancel -->
        <?php if (!$isArchived && can('leave.cancel') && in_array($lr['status'] ?? '', ['pending','approved'], true)): ?>
        <div class="lv-action-card" id="cancel">
            <div class="lv-action-card-header">Cancel Leave</div>
            <div class="lv-action-card-body">
                <form method="POST" action="/admin/leave/<?= (int)$lr['id'] ?>/cancel" data-confirm="Cancel this leave request?">
                    <?= csrf_field() ?>
                    <textarea name="reason" class="form-control form-control-sm mb-2" rows="2" style="font-size:13px" placeholder="Cancellation reason" required></textarea>
                    <button class="btn btn-outline-danger btn-sm w-100">Cancel Leave</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Extend -->
        <?php if (!$isArchived && ($lr['status'] ?? '') === 'approved' && can('leave.extend')): ?>
        <div class="lv-action-card">
            <div class="lv-action-card-header">Extend Leave</div>
            <div class="lv-action-card-body">
                <form method="POST" action="/admin/leave/<?= (int)$lr['id'] ?>/extend">
                    <?= csrf_field() ?>
                    <div class="mb-2">
                        <label class="form-label small mb-1" style="font-size:11.5px;color:#6b7280">Current end date</label>
                        <input type="text" class="form-control form-control-sm" value="<?= e($lr['end_date']) ?>" disabled style="font-size:13px">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1" style="font-size:11.5px;color:#6b7280">New end date <span style="color:#dc2626">*</span></label>
                        <input type="date" name="new_end_date" class="form-control form-control-sm" min="<?= e($lr['end_date']) ?>" required style="font-size:13px">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1" style="font-size:11.5px;color:#6b7280">Reason <span style="color:#dc2626">*</span></label>
                        <textarea name="reason" class="form-control form-control-sm" rows="2" required style="font-size:13px"></textarea>
                    </div>
                    <button class="btn btn-primary btn-sm w-100">
                        <i data-lucide="calendar-plus" style="width:13px;height:13px" class="me-1"></i>Extend Leave
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Archive -->
        <?php if (!$isArchived && can('leave.archive')): ?>
        <div class="lv-action-card" id="archive">
            <div class="lv-action-card-header">Archive</div>
            <div class="lv-action-card-body">
                <form method="POST" action="/admin/leave/<?= (int)$lr['id'] ?>/archive" data-confirm="Archive this leave request?">
                    <?= csrf_field() ?>
                    <textarea name="reason" class="form-control form-control-sm mb-2" rows="2" style="font-size:13px" placeholder="Archive reason" required></textarea>
                    <button class="btn btn-danger btn-sm w-100">
                        <i data-lucide="archive" style="width:13px;height:13px" class="me-1"></i>Archive Leave
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Archived actions -->
        <?php if ($isArchived): ?>
        <div class="lv-action-card">
            <div class="lv-action-card-header">Archived Record</div>
            <div class="lv-action-card-body">
                <?php if (can('leave.restore')): ?>
                <form method="POST" action="/admin/leave/<?= (int)$lr['id'] ?>/restore" class="mb-2" data-confirm="Restore this leave?">
                    <?= csrf_field() ?>
                    <button class="btn btn-success btn-sm w-100">
                        <i data-lucide="rotate-ccw" style="width:13px;height:13px" class="me-1"></i>Restore
                    </button>
                </form>
                <?php endif; ?>
                <?php if (can('leave.permanently_delete')): ?>
                <form method="POST" action="/admin/leave/<?= (int)$lr['id'] ?>/force-delete" data-confirm="Permanently delete this leave? This cannot be undone.">
                    <?= csrf_field() ?>
                    <textarea name="reason" class="form-control form-control-sm mb-2" required style="font-size:13px" placeholder="Deletion reason"></textarea>
                    <button class="btn btn-outline-danger btn-sm w-100">Permanently Delete</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>
