<?php /** @var array|null $employee */ /** @var array $stats */ ?>
<div class="page-header">
    <div>
        <h1>Welcome<?= $employee ? ', ' . e($employee['first_name']) : '' ?></h1>
        <p class="subtitle">Your attendance, leave, payroll, and documents in one colorful workspace</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-sm btn-primary" href="/employee/attendance"><i data-lucide="map-pin" class="me-1" style="width:14px;height:14px"></i>Attendance</a>
        <a class="btn btn-sm btn-soft" href="/employee/leave/create">Request Leave</a>
        <a class="btn btn-sm btn-soft" href="/employee/payslips">Payslips</a>
    </div>
</div>

<?php if (!$employee): ?>
    <div class="alert alert-warning">No employee profile is linked to your account. Contact HR.</div>
<?php else:
    $today = $stats['today'] ?? null;
    $checkIn = $today['check_in_at'] ?? null;
    $checkOut = $today['check_out_at'] ?? null;
    $workMins = (int) ($today['work_minutes'] ?? 0);
    $attPct = (float) ($stats['attendance_pct'] ?? 0);
    $leaveBal = (float) ($stats['leave_balance'] ?? 0);
    $leavePct = min(100, max(0, $leaveBal > 0 ? min(100, ($leaveBal / max($leaveBal, 20)) * 100) : 0));
?>

<div class="action-grid mb-3">
    <a class="action-card tone-mint" href="/employee/attendance"><span class="action-icon"><i data-lucide="calendar-check"></i></span><span class="action-title">Check Attendance</span><span class="action-sub">Mark today</span></a>
    <a class="action-card tone-orange" href="/employee/leave/create"><span class="action-icon"><i data-lucide="calendar-plus"></i></span><span class="action-title">Apply Leave</span><span class="action-sub">New request</span></a>
    <a class="action-card" href="/employee/payslips"><span class="action-icon"><i data-lucide="receipt"></i></span><span class="action-title">Payslips</span><span class="action-sub">Latest salary</span></a>
    <a class="action-card tone-blue" href="/employee/loans"><span class="action-icon"><i data-lucide="landmark"></i></span><span class="action-title">Loans</span><span class="action-sub">Balances</span></a>
    <a class="action-card tone-cyan" href="/employee/documents"><span class="action-icon"><i data-lucide="folder-open"></i></span><span class="action-title">Documents</span><span class="action-sub">Your files</span></a>
    <a class="action-card tone-pink" href="/employee/notifications"><span class="action-icon"><i data-lucide="bell"></i></span><span class="action-title">Messages</span><span class="action-sub"><?= number_format($stats['unread_notifications'] ?? 0) ?> unread</span></a>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4 stagger-item">
        <div class="metric-card tone-mint h-100 text-center">
            <div class="metric-label mb-2">Attendance Ring</div>
            <div class="widget-ring" style="--pct: <?= e((string) $attPct) ?>; --tone: #00C896">
                <strong><?= number_format($attPct, 0) ?>%</strong>
            </div>
            <div class="small text-secondary">Month-to-date presence</div>
        </div>
    </div>
    <div class="col-md-4 stagger-item">
        <div class="metric-card tone-orange h-100">
            <div class="metric-icon"><i data-lucide="calendar-off"></i></div>
            <div class="metric-label">Leave Balance</div>
            <div class="metric-value"><?= number_format($leaveBal, 1) ?></div>
            <div class="progress-soft mt-3 mb-2"><span style="width: <?= e((string) max(8, $leavePct)) ?>%; background: linear-gradient(90deg,#FFB547,#F35BA6)"></span></div>
            <div class="metric-delta flat"><?= number_format($stats['pending_leaves'] ?? 0) ?> pending requests</div>
        </div>
    </div>
    <div class="col-md-4 stagger-item">
        <div class="metric-card tone-purple h-100">
            <div class="metric-icon"><i data-lucide="wallet"></i></div>
            <div class="metric-label">Latest Net Pay</div>
            <div class="metric-value" style="font-size:1.25rem"><?= !empty($stats['last_payslip']) ? format_money($stats['last_payslip']['net_salary'], $stats['last_payslip']['currency']) : '—' ?></div>
            <div class="metric-delta flat"><?= e($stats['last_payslip']['period_name'] ?? 'No payslip yet') ?></div>
        </div>
    </div>
</div>

<div class="row g-3 metrics-row mb-3">
    <div class="col-6 col-xl-3 stagger-item">
        <div class="metric-card tone-sky">
            <div class="metric-icon"><i data-lucide="sun"></i></div>
            <div class="metric-label">Today’s Status</div>
            <div class="metric-value" style="font-size:1.15rem"><?= $today ? e(ucwords(str_replace('_', ' ', (string)$today['status']))) : 'Not marked' ?></div>
            <div class="metric-delta flat">
                <?= $checkIn ? 'In ' . e(date(config('app.time_format', 'g:i A'), strtotime($checkIn))) : 'No check-in' ?>
                <?= $checkOut ? ' · Out ' . e(date(config('app.time_format', 'g:i A'), strtotime($checkOut))) : '' ?>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3 stagger-item">
        <div class="metric-card tone-cyan">
            <div class="metric-icon"><i data-lucide="clock-3"></i></div>
            <div class="metric-label">Working Hours</div>
            <div class="metric-value" style="font-size:1.2rem"><?= $workMins > 0 ? floor($workMins / 60) . 'h ' . ($workMins % 60) . 'm' : '—' ?></div>
            <div class="metric-delta flat"><?= number_format($stats['present_month'] ?? 0) ?> present days</div>
        </div>
    </div>
    <div class="col-6 col-xl-3 stagger-item">
        <a class="metric-card tone-blue clickable text-decoration-none d-block" href="/employee/loans">
            <div class="metric-icon"><i data-lucide="landmark"></i></div>
            <div class="metric-label">Outstanding Loan</div>
            <div class="metric-value" style="font-size:1.1rem"><?= format_money($stats['loan_outstanding'] ?? 0) ?></div>
        </a>
    </div>
    <div class="col-6 col-xl-3 stagger-item">
        <a class="metric-card tone-pink clickable text-decoration-none d-block" href="/employee/documents">
            <div class="metric-icon"><i data-lucide="files"></i></div>
            <div class="metric-label">My Documents</div>
            <div class="metric-value" data-count-to="<?= (int)($stats['shared_docs'] ?? 0) ?>"><?= number_format($stats['shared_docs'] ?? 0) ?></div>
        </a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card ems-card mb-3">
            <div class="card-header">Attendance (last 14 days)</div>
            <div class="card-body chart-panel"><canvas id="empAttendanceChart" aria-label="Your attendance trend"></canvas></div>
        </div>
        <div class="card ems-card">
            <div class="card-header">Recent Detail</div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>Date</th><th>Status</th><th>Check In</th><th>Check Out</th></tr></thead>
                    <tbody>
                    <?php
                    $weekRows = array_slice(array_reverse($attendanceWeek ?? []), 0, 7);
                    if (empty($weekRows)): ?>
                        <tr><td colspan="4"><div class="empty-state py-4 mb-0">No attendance records.</div></td></tr>
                    <?php else: foreach ($weekRows as $row): ?>
                        <tr>
                            <td><?= format_date($row['attendance_date']) ?></td>
                            <td><?= status_badge($row['status']) ?></td>
                            <td class="small"><?= $row['check_in_at'] ? format_datetime($row['check_in_at']) : '—' ?></td>
                            <td class="small"><?= $row['check_out_at'] ? format_datetime($row['check_out_at']) : '—' ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card ems-card mb-3">
            <div class="card-header">Announcements</div>
            <div class="list-group list-group-flush">
                <?php if (empty($announcements)): ?>
                    <div class="list-group-item small text-muted">No announcements</div>
                <?php else: foreach ($announcements as $a): ?>
                    <div class="list-group-item py-3">
                        <div class="fw-bold small"><?= e($a['title']) ?></div>
                        <div class="text-secondary small"><?= e(mb_strimwidth(strip_tags($a['body']), 0, 100, '…')) ?></div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
        <div class="card ems-card">
            <div class="card-header">Upcoming Holidays</div>
            <div class="list-group list-group-flush">
                <?php if (empty($holidays)): ?>
                    <div class="list-group-item small text-muted">No upcoming holidays</div>
                <?php else: foreach ($holidays as $h): ?>
                    <div class="list-group-item py-3 d-flex justify-content-between align-items-center">
                        <span class="small fw-bold"><?= e($h['name']) ?></span>
                        <span class="filter-chip"><?= format_date($h['holiday_date']) ?></span>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.lucide) lucide.createIcons();
    const rows = <?= json_encode($attendanceWeek ?? [], JSON_UNESCAPED_UNICODE) ?>;
    if (!window.EMSCharts || !rows.length) return;
    const presentStatuses = ['present','late','remote','manual','half_day'];
    EMSCharts.line('empAttendanceChart', rows.map(r => r.attendance_date), [{
        label: 'Present',
        data: rows.map(r => presentStatuses.includes(r.status) ? 1 : 0),
        borderColor: EMSCharts.palette.present,
        backgroundColor: 'rgba(34,197,94,.14)',
    }, {
        label: 'Absent / Leave',
        data: rows.map(r => ['absent','on_leave'].includes(r.status) ? 1 : 0),
        borderColor: EMSCharts.palette.absent,
        backgroundColor: 'rgba(239,68,68,.10)',
    }]);
});
</script>
<?php endif; ?>
