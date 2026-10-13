<?php
/** @var array $employee */
$emp = $employee;
$empName = trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''));

$avPalettes = [
    ['#DBEAFE','#1E40AF'],['#D1FAE5','#065F46'],['#FEF3C7','#92400E'],
    ['#FCE7F3','#9D174D'],['#EDE9FE','#5B21B6'],['#FFEDD5','#9A3412'],
    ['#CFFAFE','#164E63'],['#F0FDF4','#14532D'],['#FEE2E2','#7F1D1D'],
];
function emp_show_av(string $name, array $pal): array {
    $idx = 0;
    foreach (str_split($name) as $ch) { $idx = ($idx * 31 + ord($ch)) & 0x7fffffff; }
    [$bg, $fg] = $pal[$idx % count($pal)];
    $ini = implode('', array_map(fn($p) => strtoupper($p[0] ?? ''), array_slice(explode(' ', trim($name)), 0, 2)));
    return [$bg, $fg, $ini ?: '?'];
}
[$avBg, $avFg, $ini] = emp_show_av($empName, $avPalettes);

$statusMeta = [
    'active'        => ['Active',        '#DCFCE7','#15803D','#16A34A'],
    'probation'     => ['Probation',     '#FFFBEB','#B45309','#D97706'],
    'inactive'      => ['Inactive',      '#F3F4F6','#374151','#6B7280'],
    'terminated'    => ['Terminated',    '#FEF2F2','#B91C1C','#DC2626'],
    'resigned'      => ['Resigned',      '#FEF2F2','#B91C1C','#DC2626'],
    'suspended'     => ['Suspended',     '#FEF2F2','#B91C1C','#DC2626'],
    'notice_period' => ['Notice Period', '#FEF3C7','#92400E','#D97706'],
    'retired'       => ['Retired',       '#F3F4F6','#374151','#6B7280'],
];
$sm = $statusMeta[$emp['employment_status'] ?? 'inactive'] ?? $statusMeta['inactive'];
[$smLabel, $smBg, $smText, $smDot] = $sm;

$onboardingChecks = [
    ['label' => 'Basic identity',            'done' => !empty($emp['first_name']) && !empty($emp['employee_code']),               'href' => '/admin/employees/' . (int)$emp['id'] . '/edit'],
    ['label' => 'Company contact',           'done' => !empty($emp['company_email']) && !empty($emp['phone']),                   'href' => '/admin/employees/' . (int)$emp['id'] . '/edit'],
    ['label' => 'Organization assignment',   'done' => !empty($emp['department_id']) && !empty($emp['branch_id']) && !empty($emp['designation_id']), 'href' => '/admin/employees/' . (int)$emp['id'] . '/edit'],
    ['label' => 'Shift assigned',            'done' => !empty($emp['shift_id']),                                                 'href' => '/admin/employees/' . (int)$emp['id'] . '/edit'],
    ['label' => 'Employee login',            'done' => !empty($emp['user_id']),                                                  'href' => '#create-login'],
];
$onboardingDone = count(array_filter($onboardingChecks, fn($i) => $i['done']));
$onboardingPct  = (int) round(($onboardingDone / max(1, count($onboardingChecks))) * 100);
?>
<style>
/* ── Employee detail page ──────────────────────────────────── */
.emp-hero{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px 22px;margin-bottom:18px;display:flex;align-items:center;gap:16px;flex-wrap:wrap}
.emp-hero-av{width:60px;height:60px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:800;flex-shrink:0;letter-spacing:-.5px}
.emp-hero-name{font-size:20px;font-weight:700;line-height:1.2;margin-bottom:3px}
.emp-hero-meta{font-size:13px;color:#6b7280}
.emp-hero-actions{margin-left:auto;display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.emp-status-bdg{display:inline-flex;align-items:center;gap:5px;padding:4px 11px;border-radius:20px;font-size:12px;font-weight:700}
.emp-status-dot{width:6px;height:6px;border-radius:50%;flex-shrink:0}

/* Info grid */
.emp-info-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px 20px}
.emp-info-item{}
.emp-info-label{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:#6b7280;margin-bottom:3px}
.emp-info-val{font-size:13px;font-weight:500}
.emp-info-val a{color:inherit;text-decoration:underline;text-underline-offset:2px}

/* Section card */
.emp-section{background:#fff;border:1px solid #e5e7eb;border-radius:10px;margin-bottom:14px;overflow:hidden}
.emp-section-hd{padding:11px 16px;font-size:13px;font-weight:700;border-bottom:1px solid #e5e7eb;background:#f9fafb}
.emp-section-body{padding:16px}

/* Onboarding */
.emp-ob-bar{height:6px;background:#e5e7eb;border-radius:4px;overflow:hidden;margin-bottom:10px}
.emp-ob-fill{height:100%;background:#20d483;border-radius:4px;transition:width .3s}
.emp-ob-meta{font-size:12px;color:#6b7280;margin-bottom:12px}
.emp-ob-list{display:flex;flex-direction:column;gap:6px}
.emp-ob-item{display:flex;align-items:center;gap:8px;padding:7px 10px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:500;transition:background .1s;color:inherit}
.emp-ob-item:hover{background:#f9fafb}
.emp-ob-item.is-done{color:#15803D}
.emp-ob-item i[data-lucide]{width:15px;height:15px;flex-shrink:0}
.emp-ob-item.is-done i[data-lucide]{color:#16a34a}
.emp-ob-item:not(.is-done) i[data-lucide]{color:#9ca3af}
.emp-ob-arrow{margin-left:auto;opacity:.4;width:13px;height:13px}

/* Action sidebar card */
.emp-action-card{background:#fff;border:1px solid #e5e7eb;border-radius:10px;margin-bottom:14px;overflow:hidden}
.emp-action-hd{padding:11px 16px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#6b7280;border-bottom:1px solid #e5e7eb;background:#f9fafb}
.emp-action-body{padding:14px 16px}

/* ── Dark mode ─────────────────────────────────────────────── */
html[data-theme="dark"] .emp-hero{background:#0d1b2a;border-color:rgba(96,165,250,.13)}
html[data-theme="dark"] .emp-hero-meta{color:#64748b}
html[data-theme="dark"] .emp-info-label{color:#64748b}
html[data-theme="dark"] .emp-section{background:#0d1b2a;border-color:rgba(96,165,250,.13)}
html[data-theme="dark"] .emp-section-hd{background:rgba(255,255,255,.03);border-bottom-color:rgba(96,165,250,.1)}
html[data-theme="dark"] .emp-ob-bar{background:rgba(255,255,255,.08)}
html[data-theme="dark"] .emp-ob-meta{color:#64748b}
html[data-theme="dark"] .emp-ob-item:hover{background:rgba(255,255,255,.04)}
html[data-theme="dark"] .emp-ob-item.is-done{color:#86efac}
html[data-theme="dark"] .emp-ob-item.is-done i[data-lucide]{color:#20d483}
html[data-theme="dark"] .emp-ob-item:not(.is-done) i[data-lucide]{color:#475569}
html[data-theme="dark"] .emp-action-card{background:#0d1b2a;border-color:rgba(96,165,250,.13)}
html[data-theme="dark"] .emp-action-hd{background:rgba(255,255,255,.03);border-bottom-color:rgba(96,165,250,.1);color:#64748b}
html[data-theme="dark"] .emp-status-bdg.active       {background:rgba(22,163,74,.18)!important;color:#86efac!important}
html[data-theme="dark"] .emp-status-bdg.probation    {background:rgba(217,119,6,.18)!important;color:#fcd34d!important}
html[data-theme="dark"] .emp-status-bdg.inactive,.emp-status-bdg.retired{background:rgba(255,255,255,.07)!important;color:#94a3b8!important}
html[data-theme="dark"] .emp-status-bdg.terminated,.emp-status-bdg.resigned,.emp-status-bdg.suspended{background:rgba(220,38,38,.18)!important;color:#fca5a5!important}
html[data-theme="dark"] .emp-status-bdg.notice_period{background:rgba(217,119,6,.18)!important;color:#fcd34d!important}

@media(max-width:640px){.emp-info-grid{grid-template-columns:1fr 1fr}}
@media(max-width:400px){.emp-info-grid{grid-template-columns:1fr}}
</style>

<?php include config('app.paths.views') . '/partials/alerts.php'; ?>

<!-- Hero header -->
<div class="emp-hero">
    <div class="emp-hero-av" style="background:<?= e($avBg) ?>;color:<?= e($avFg) ?>"><?= e($ini) ?></div>
    <div>
        <div class="emp-hero-name"><?= e($empName) ?></div>
        <div class="emp-hero-meta">
            <?= e($emp['employee_code'] ?? '') ?>
            <?php if (!empty($emp['designation_name'])): ?> · <?= e($emp['designation_name']) ?><?php endif; ?>
            <?php if (!empty($emp['department_name'])): ?> · <?= e($emp['department_name']) ?><?php endif; ?>
        </div>
    </div>
    <div class="emp-hero-actions">
        <?php $statusKey = str_replace([' ','/'], '_', strtolower($emp['employment_status'] ?? 'inactive')); ?>
        <span class="emp-status-bdg <?= e($statusKey) ?>" style="background:<?= e($smBg) ?>;color:<?= e($smText) ?>">
            <span class="emp-status-dot" style="background:<?= e($smDot) ?>"></span>
            <?= e($smLabel) ?>
        </span>
        <?php if (can('employees.update')): ?>
        <a href="/admin/employees/<?= (int)$emp['id'] ?>/edit" class="btn btn-soft btn-sm">
            <i data-lucide="pencil" style="width:13px;height:13px" class="me-1"></i>Edit
        </a>
        <?php endif; ?>
        <?php if (can('employees.delete')): ?>
        <form method="POST" action="/admin/employees/<?= (int)$emp['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Deactivate this employee?')">
            <?= csrf_field() ?>
            <button class="btn btn-outline-danger btn-sm">Deactivate</button>
        </form>
        <?php endif; ?>
        <a href="/admin/employees" class="btn btn-soft btn-sm">
            <i data-lucide="arrow-left" style="width:13px;height:13px" class="me-1"></i>Back
        </a>
    </div>
</div>

<!-- Tabs -->
<nav class="workspace-tabs mb-3" aria-label="Employee sections">
    <div class="workspace-tabs-scroll">
        <a class="workspace-tab is-active" href="/admin/employees/<?= (int)$emp['id'] ?>">Overview</a>
        <a class="workspace-tab" href="/admin/attendance?q=<?= urlencode((string)($emp['employee_code'] ?? '')) ?>">Attendance</a>
        <a class="workspace-tab" href="/admin/leave?employee_id=<?= (int)$emp['id'] ?>">Leave</a>
        <a class="workspace-tab" href="/admin/payroll?employee_id=<?= (int)$emp['id'] ?>">Payroll</a>
        <a class="workspace-tab" href="/admin/documents?employee_id=<?= (int)$emp['id'] ?>">Documents</a>
        <?php if (can('attendance.device.view')): ?><a class="workspace-tab" href="/admin/employees/<?= (int)$emp['id'] ?>/attendance-device">Attendance Device</a><?php endif; ?>
    </div>
</nav>

<div class="row g-3">
    <!-- Main column -->
    <div class="col-lg-8">

        <!-- Profile -->
        <div class="emp-section">
            <div class="emp-section-hd">Profile</div>
            <div class="emp-section-body">
                <div class="emp-info-grid">
                    <div class="emp-info-item">
                        <div class="emp-info-label">Company</div>
                        <div class="emp-info-val"><?= e($emp['company_name'] ?? '—') ?></div>
                    </div>
                    <div class="emp-info-item">
                        <div class="emp-info-label">Branch</div>
                        <div class="emp-info-val"><?= e($emp['branch_name'] ?? '—') ?></div>
                    </div>
                    <div class="emp-info-item">
                        <div class="emp-info-label">Department</div>
                        <div class="emp-info-val"><?= e($emp['department_name'] ?? '—') ?></div>
                    </div>
                    <div class="emp-info-item">
                        <div class="emp-info-label">Designation</div>
                        <div class="emp-info-val"><?= e($emp['designation_name'] ?? '—') ?></div>
                    </div>
                    <div class="emp-info-item">
                        <div class="emp-info-label">Manager</div>
                        <div class="emp-info-val"><?= e($emp['manager_name'] ?? '—') ?></div>
                    </div>
                    <div class="emp-info-item">
                        <div class="emp-info-label">Shift</div>
                        <div class="emp-info-val"><?= e($emp['shift_name'] ?? '—') ?></div>
                    </div>
                    <div class="emp-info-item">
                        <div class="emp-info-label">Employment Type</div>
                        <div class="emp-info-val"><?= e(ucwords(str_replace('_', ' ', (string)($emp['employment_type'] ?? '—')))) ?></div>
                    </div>
                    <div class="emp-info-item">
                        <div class="emp-info-label">Joining Date</div>
                        <div class="emp-info-val"><?= e(format_date($emp['joining_date'] ?? null)) ?></div>
                    </div>
                    <div class="emp-info-item">
                        <div class="emp-info-label">Date of Birth</div>
                        <div class="emp-info-val"><?= e(format_date($emp['date_of_birth'] ?? null)) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contact -->
        <div class="emp-section">
            <div class="emp-section-hd">Contact</div>
            <div class="emp-section-body">
                <div class="emp-info-grid">
                    <div class="emp-info-item">
                        <div class="emp-info-label">Company Email</div>
                        <div class="emp-info-val"><?= $emp['company_email'] ? '<a href="mailto:' . e($emp['company_email']) . '">' . e($emp['company_email']) . '</a>' : '—' ?></div>
                    </div>
                    <div class="emp-info-item">
                        <div class="emp-info-label">Personal Email</div>
                        <div class="emp-info-val"><?= $emp['personal_email'] ? '<a href="mailto:' . e($emp['personal_email']) . '">' . e($emp['personal_email']) . '</a>' : '—' ?></div>
                    </div>
                    <div class="emp-info-item">
                        <div class="emp-info-label">Phone</div>
                        <div class="emp-info-val"><?= e($emp['phone'] ?: '—') ?></div>
                    </div>
                    <div class="emp-info-item">
                        <div class="emp-info-label">Gender</div>
                        <div class="emp-info-val"><?= e(ucfirst((string)($emp['gender'] ?? '—'))) ?></div>
                    </div>
                    <div class="emp-info-item">
                        <div class="emp-info-label">CNIC / ID</div>
                        <div class="emp-info-val"><?= e($emp['national_id'] ?: '—') ?></div>
                    </div>
                    <div class="emp-info-item">
                        <div class="emp-info-label">Basic Salary</div>
                        <div class="emp-info-val"><?= e(format_money($emp['basic_salary'] ?? 0)) ?></div>
                    </div>
                    <div class="emp-info-item" style="grid-column:1/-1">
                        <div class="emp-info-label">Address</div>
                        <div class="emp-info-val"><?= e($emp['current_address'] ?: '—') ?></div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Sidebar -->
    <div class="col-lg-4">

        <!-- Onboarding checklist -->
        <div class="emp-section">
            <div class="emp-section-hd" style="display:flex;align-items:center;justify-content:space-between">
                Onboarding Readiness
                <span style="font-size:12px;font-weight:700;color:<?= $onboardingPct === 100 ? '#16a34a' : '#d97706' ?>"><?= $onboardingPct ?>%</span>
            </div>
            <div class="emp-section-body" style="padding:14px 16px">
                <div class="emp-ob-bar"><div class="emp-ob-fill" style="width:<?= $onboardingPct ?>%"></div></div>
                <div class="emp-ob-meta"><?= $onboardingDone ?> of <?= count($onboardingChecks) ?> items complete</div>
                <div class="emp-ob-list">
                    <?php foreach ($onboardingChecks as $check): ?>
                    <a class="emp-ob-item<?= $check['done'] ? ' is-done' : '' ?>" href="<?= e($check['href']) ?>">
                        <i data-lucide="<?= $check['done'] ? 'check-circle-2' : 'circle' ?>"></i>
                        <span><?= e($check['label']) ?></span>
                        <i data-lucide="arrow-up-right" class="emp-ob-arrow"></i>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <?php if (can('employees.update')): ?>
        <a href="/admin/employees/<?= (int)$emp['id'] ?>/workflows" class="btn btn-soft btn-sm w-100 mb-3" style="font-size:12.5px">
            <i data-lucide="list-checks" style="width:13px;height:13px" class="me-1"></i>Onboarding / Offboarding Workflow
        </a>
        <?php endif; ?>

        <!-- Login info -->
        <div class="emp-section" style="margin-bottom:14px">
            <div class="emp-section-hd">Login Access</div>
            <div class="emp-section-body">
                <?php if (!empty($emp['user_id'])): ?>
                <div style="display:flex;align-items:center;gap:8px">
                    <i data-lucide="check-circle-2" style="width:16px;height:16px;color:#16a34a;flex-shrink:0"></i>
                    <div>
                        <div style="font-size:13px;font-weight:600;color:#15803D">Login active</div>
                        <div style="font-size:12px;color:#6b7280"><?= e($emp['user_email'] ?? '') ?></div>
                    </div>
                </div>
                <?php else: ?>
                <div style="display:flex;align-items:center;gap:8px;color:#6b7280;margin-bottom:10px">
                    <i data-lucide="circle-alert" style="width:16px;height:16px;flex-shrink:0"></i>
                    <span style="font-size:13px">No employee login yet</span>
                </div>
                <?php if (can('employees.update')): ?>
                <form method="POST" action="/admin/employees/<?= (int)$emp['id'] ?>/create-login" id="create-login">
                    <?= csrf_field() ?>
                    <div class="mb-2">
                        <label class="form-label" style="font-size:11.5px;color:#6b7280;margin-bottom:3px">Login Email</label>
                        <input type="email" name="email" class="form-control form-control-sm" style="font-size:13px"
                               value="<?= e($emp['company_email'] ?: ($emp['personal_email'] ?: '')) ?>"
                               placeholder="name@company.com" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label" style="font-size:11.5px;color:#6b7280;margin-bottom:3px">Temp Password</label>
                        <input type="text" name="password" class="form-control form-control-sm" style="font-size:13px" value="Employee@123">
                    </div>
                    <button class="btn btn-primary btn-sm w-100">
                        <i data-lucide="user-check" style="width:13px;height:13px" class="me-1"></i>Create Login
                    </button>
                </form>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Change status -->
        <?php if (can('employees.update')): ?>
        <div class="emp-action-card">
            <div class="emp-action-hd">Change Status</div>
            <div class="emp-action-body">
                <form method="POST" action="/admin/employees/<?= (int)$emp['id'] ?>/status">
                    <?= csrf_field() ?>
                    <div class="mb-2">
                        <select name="employment_status" class="form-select form-select-sm" style="font-size:13px">
                            <?php foreach (['active','probation','notice_period','suspended','terminated','resigned','retired','inactive'] as $s): ?>
                            <option value="<?= $s ?>" <?= ($emp['employment_status'] ?? '') === $s ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $s)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <textarea name="notes" class="form-control form-control-sm" rows="2" style="font-size:13px" placeholder="Notes (optional)"></textarea>
                    </div>
                    <button class="btn btn-warning btn-sm w-100">Update Status</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>
