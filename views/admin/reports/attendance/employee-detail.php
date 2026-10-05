<?php
/** @var array $detail */
$detail = $detail ?? [];
$employee = $detail['employee'] ?? [];
$days = $detail['days'] ?? [];
$summary = $detail['summary'] ?? [];
$payroll = $payrollSummary ?? [];
$devices = $deviceInfo ?? [];

if (empty($employee)):
?>
<div class="card ems-card"><div class="card-body empty-state py-5">
    <i class="bi bi-person-badge"></i>
    Select an employee from the Employee Summary tab (or the Employee filter above) to see their day-by-day attendance breakdown.
</div></div>
<?php return; endif; ?>

<?php
$empName = trim($employee['first_name'] . ' ' . $employee['last_name']);
$avatarPairs = [['#6C63FF', '#4338CA'], ['#00C896', '#0E9F6E'], ['#3BA4FF', '#1D4ED8'], ['#F59E0B', '#B45309'], ['#F35BA6', '#BE185D'], ['#14B8A6', '#0F766E']];
[$avA, $avB] = $avatarPairs[crc32($employee['employee_code'] ?? $empName) % count($avatarPairs)];
$initials = implode('', array_map(static fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)), array_slice(preg_split('/\s+/', $empName), 0, 2)));
$detailPct = (float) ($summary['attendance_pct'] ?? 0);
$detailTone = $detailPct >= 90 ? '#22C55E' : ($detailPct >= 75 ? '#F59E0B' : '#EF4444');
?>
<div class="report-hero" style="--hero-tone: <?= e($detailTone) ?>">
    <div class="emp-chip-avatar" style="--avatar-a: <?= e($avA) ?>; --avatar-b: <?= e($avB) ?>; width:64px; height:64px; font-size:1.2rem;"><?= e($initials) ?></div>
    <div class="report-hero-body">
        <div class="report-hero-eyebrow"><?= e($employee['employee_code']) ?> · <?= e($employee['department_name'] ?? '—') ?> · <?= e($employee['designation_name'] ?? '—') ?></div>
        <h2 class="report-hero-headline"><?= e($empName) ?></h2>
        <p class="report-hero-sub">Reporting period: <?= e(format_date($filters['from'])) ?> — <?= e(format_date($filters['to'])) ?> · <?= e($employee['branch_name'] ?? '—') ?></p>
    </div>
    <div class="report-hero-stats">
        <div class="report-hero-stat"><div class="report-hero-stat-value"><?= number_format($detailPct, 0) ?>%</div><div class="report-hero-stat-label">Attendance</div></div>
        <div class="report-hero-stat"><div class="report-hero-stat-value"><?= number_format((float) ($summary['present_days'] ?? 0), 1) ?></div><div class="report-hero-stat-label">Present</div></div>
        <div class="report-hero-stat"><div class="report-hero-stat-value"><?= number_format((float) ($summary['absent_days'] ?? 0), 1) ?></div><div class="report-hero-stat-label">Absent</div></div>
    </div>
    <div class="d-flex gap-2" style="position:relative">
        <a class="btn btn-sm" style="background:rgba(255,255,255,.16);color:#fff;border:1px solid rgba(255,255,255,.3)" href="/admin/employees/<?= (int) $employee['id'] ?>"><i data-lucide="user" class="me-1" style="width:14px;height:14px"></i>Profile</a>
        <a class="btn btn-sm" style="background:rgba(255,255,255,.16);color:#fff;border:1px solid rgba(255,255,255,.3)" href="/admin/reports/attendance?section=employee_summary&<?= e(http_build_query(array_filter(['from' => $filters['from'], 'to' => $filters['to']]))) ?>">Back to Summary</a>
    </div>
</div>

<div class="row g-2 metrics-row mb-3">
    <?php foreach ([
        ['Working Days', $summary['scheduled_working_days'] ?? 0, 'blue', 'calendar'],
        ['Present', $summary['present_days'] ?? 0, 'mint', 'user-check'],
        ['Absent', $summary['absent_days'] ?? 0, 'red', 'user-x'],
        ['Paid Leave', $summary['paid_leave_days'] ?? 0, 'primary', 'calendar-off'],
        ['Unpaid Leave', $summary['unpaid_leave_days'] ?? 0, 'orange', 'calendar-off'],
        ['Late Occurrences', $summary['late_days'] ?? 0, 'yellow', 'alarm-clock'],
        ['Half Days', $summary['half_days'] ?? 0, 'cyan', 'sun-half'],
        ['Worked Hours', round(($summary['worked_minutes'] ?? 0) / 60, 1), 'purple', 'clock'],
        ['Required Hours', round(($summary['required_minutes'] ?? 0) / 60, 1), 'blue', 'clock'],
        ['Overtime', round(($summary['overtime_minutes'] ?? 0) / 60, 1), 'cyan', 'timer'],
        ['Undertime', round(($summary['undertime_minutes'] ?? 0) / 60, 1), 'orange', 'hourglass'],
    ] as [$label, $value, $tone, $icon]): ?>
        <div class="col-6 col-md-3 col-xl-2 stagger-item">
            <div class="metric-card tone-<?= e($tone) ?>">
                <div class="metric-icon"><i data-lucide="<?= e($icon) ?>"></i></div>
                <div class="metric-label"><?= e($label) ?></div>
                <div class="metric-value"><?= is_float($value) ? number_format($value, 1) : $value ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<script>if (window.lucide) lucide.createIcons();</script>

<div class="card ems-card mb-3">
    <div class="card-header report-card-title"><span class="rct-icon"><i data-lucide="calendar-days"></i></span>Daily Breakdown</div>
    <div class="report-table-shell">
        <table class="table table-sm mb-0 align-middle">
            <thead>
            <tr>
                <th>Date</th><th>Day</th><th>Scheduled Shift</th><th>Sched In</th><th>Sched Out</th>
                <th>Actual In</th><th>Actual Out</th><th>Status</th><th>Late By</th><th>Early Dep.</th>
                <th>Worked</th><th>OT</th><th>Leave Type</th><th>Source</th><th>Remarks</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($days as $d): ?>
                <tr class="<?= $d['is_scheduled_working_day'] ? '' : 'table-light text-muted' ?>">
                    <td><?= e(format_date($d['date'])) ?></td>
                    <td><?= e($d['day_name']) ?></td>
                    <td><?= e($d['shift_name'] ?? '—') ?></td>
                    <td><?= e($d['scheduled_in'] ? date(config('app.time_format', 'g:i A'), strtotime($d['scheduled_in'])) : '—') ?></td>
                    <td><?= e($d['scheduled_out'] ? date(config('app.time_format', 'g:i A'), strtotime($d['scheduled_out'])) : '—') ?></td>
                    <td><?= e($d['check_in_at'] ? date(config('app.time_format', 'g:i A'), strtotime($d['check_in_at'])) : '—') ?></td>
                    <td><?= e($d['check_out_at'] ? date(config('app.time_format', 'g:i A'), strtotime($d['check_out_at'])) : '—') ?></td>
                    <td><?= status_badge($d['status']) ?><?= $d['is_remote'] ? ' <span class="badge bg-info">Remote</span>' : '' ?></td>
                    <td><?= $d['late_minutes'] > 0 ? $d['late_minutes'] . 'm' : '—' ?></td>
                    <td><?= $d['early_leave_minutes'] > 0 ? $d['early_leave_minutes'] . 'm' : '—' ?></td>
                    <td><?= $d['worked_minutes'] > 0 ? e(format_minutes($d['worked_minutes'])) : '—' ?></td>
                    <td><?= $d['overtime_minutes'] > 0 ? e(format_minutes($d['overtime_minutes'])) : '—' ?></td>
                    <td><?= e($d['leave_type'] ?? '—') ?></td>
                    <td><?= e($d['source'] ? ucfirst($d['source']) : '—') ?></td>
                    <td class="small text-muted"><?= e($d['remarks'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card ems-card">
            <div class="card-header report-card-title"><span class="rct-icon"><i data-lucide="wallet"></i></span>Payroll-Compatible Summary</div>
            <div class="card-body small">
                <?php foreach ([
                    'Working Days' => $payroll['working_days'] ?? 0,
                    'Present Days' => $payroll['present_days'] ?? 0,
                    'Paid Leave' => $payroll['paid_leave_days'] ?? 0,
                    'Unpaid Leave' => $payroll['unpaid_leave_days'] ?? 0,
                    'Absent Days' => $payroll['absent_days'] ?? 0,
                    'Half Days' => $payroll['half_days'] ?? 0,
                    'Required Hours' => round(($payroll['required_minutes'] ?? 0) / 60, 1),
                    'Worked Hours' => round(($payroll['worked_minutes'] ?? 0) / 60, 1),
                    'Calculated Overtime (hrs)' => round(($payroll['calculated_overtime_minutes'] ?? 0) / 60, 1),
                    'Approved Overtime (hrs)' => round(($payroll['approved_overtime_minutes'] ?? 0) / 60, 1),
                    'Undertime (hrs)' => round(($payroll['undertime_minutes'] ?? 0) / 60, 1),
                    'Payable Days' => $payroll['payable_days'] ?? 0,
                ] as $label => $value): ?>
                    <div class="d-flex justify-content-between py-1 border-bottom"><span><?= e($label) ?></span><strong><?= $value ?></strong></div>
                <?php endforeach; ?>
                <?php if (($payroll['calculated_overtime_minutes'] ?? 0) > 0 && ($payroll['approved_overtime_minutes'] ?? 0) === 0): ?>
                    <div class="alert alert-warning small mt-2 mb-0">Calculated overtime exists but no approved overtime request covers this period — payroll will not pay this overtime until a request is approved.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php if (can('attendance.device.view') && $devices): ?>
    <div class="col-lg-6">
        <div class="card ems-card">
            <div class="card-header report-card-title"><span class="rct-icon"><i data-lucide="smartphone"></i></span>Attendance Source &amp; Device</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Device</th><th>Status</th><th>Last IP</th><th>Last Seen</th></tr></thead>
                    <tbody>
                    <?php foreach ($devices as $dev): ?>
                        <tr>
                            <td><?= e($dev['device_name'] ?: ($dev['browser'] . ' / ' . $dev['operating_system'])) ?></td>
                            <td><?= status_badge($dev['status']) ?></td>
                            <td><?= e($dev['last_ip'] ?? '—') ?></td>
                            <td><?= e($dev['last_seen_at'] ? format_date($dev['last_seen_at']) : '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<script>if (window.lucide) lucide.createIcons();</script>
