<?php
$firstName = explode(' ', trim((string) ($authUser['name'] ?? 'there')))[0] ?: 'there';
$trend = array_slice($attendanceTrend ?? [], -7);
$presentValues = array_map(static fn(array $row): int => (int) ($row['present_count'] ?? 0), $trend);
$activeEmployees = max(1, (int) ($stats['employees_active'] ?? 0));
$attendanceRates = array_map(static fn(int $value): float => round(($value / $activeEmployees) * 100, 1), $presentValues);
$averageRate = $attendanceRates ? round(array_sum($attendanceRates) / count($attendanceRates), 1) : 0;
$bestIndex = $attendanceRates ? array_search(max($attendanceRates), $attendanceRates, true) : null;
$lowIndex = $attendanceRates ? array_search(min($attendanceRates), $attendanceRates, true) : null;
$absentCount = (int) ($stats['absent_today'] ?? 0);
$remoteCount  = (int) ($stats['remote_today'] ?? 0);
?>

<div class="saas-dashboard">
    <header class="saas-dashboard-head wow-hero">
        <div>
            <h1>Good <?= date('G') < 12 ? 'morning' : (date('G') < 18 ? 'afternoon' : 'evening') ?>, <?= e($firstName) ?></h1>
            <p><?= e($dashboardProfile['subtitle'] ?? 'Here is what is happening across your workforce today.') ?></p>
            <div class="hero-chips-row">
                <a class="hero-chip" href="/admin/attendance" style="--hc:#22c55e">
                    <i data-lucide="circle-check-big"></i><?= (int)($stats['present_today'] ?? 0) ?> Present
                </a>
                <?php if ((int)($stats['late_today'] ?? 0) > 0): ?>
                <a class="hero-chip" href="/admin/attendance" style="--hc:#f59e0b">
                    <i data-lucide="alarm-clock"></i><?= (int)($stats['late_today'] ?? 0) ?> Late
                </a>
                <?php endif; ?>
                <?php if ($absentCount > 0): ?>
                <a class="hero-chip" href="/admin/attendance" style="--hc:#ef4444">
                    <i data-lucide="user-minus"></i><?= $absentCount ?> Absent
                </a>
                <?php endif; ?>
                <?php if ($remoteCount > 0): ?>
                <a class="hero-chip" href="/admin/attendance/remote" style="--hc:#6366f1">
                    <i data-lucide="wifi"></i><?= $remoteCount ?> Remote
                </a>
                <?php endif; ?>
                <?php if ((int)($stats['on_leave_today'] ?? 0) > 0): ?>
                <a class="hero-chip" href="/admin/leave" style="--hc:#a78bfa">
                    <i data-lucide="tree-palm"></i><?= (int)($stats['on_leave_today'] ?? 0) ?> On Leave
                </a>
                <?php endif; ?>
                <?php if ((int)($stats['missing_checkout'] ?? 0) > 0): ?>
                <a class="hero-chip hero-chip--warn" href="/admin/attendance" style="--hc:#fb923c">
                    <i data-lucide="triangle-alert"></i><?= (int)($stats['missing_checkout'] ?? 0) ?> Missing checkout
                </a>
                <?php endif; ?>
            </div>
        </div>
        <div class="d-flex gap-2">
            <?php if (can('reports.view')): ?><a class="btn btn-soft btn-sm" href="/admin/reports"><i data-lucide="bar-chart-3"></i> Reports</a><?php endif; ?>
            <?php if (can('employees.create')): ?><a class="btn btn-primary btn-sm" href="/admin/employees/create"><i data-lucide="user-plus"></i> Add employee</a><?php endif; ?>
        </div>
    </header>

    <?php if ($setup && !$setup['is_complete'] && !$setup['dismissed']): ?>
    <div class="card setup-hero"><div class="card-body d-flex align-items-center gap-3 flex-wrap"><span class="setup-step-icon"><i data-lucide="rocket"></i></span><div class="flex-grow-1"><strong>Complete your company setup</strong><div class="small text-secondary"><?= (int) $setup['completed'] ?> of <?= (int) $setup['total'] ?> essentials completed</div><div class="progress-soft mt-2" style="max-width:420px"><span style="width:<?= (int) $setup['percent'] ?>%"></span></div></div><a class="btn btn-primary btn-sm" href="/admin/setup?company_id=<?= (int) $setup['company']['id'] ?>">Continue setup</a></div></div>
    <?php endif; ?>

    <section class="saas-metrics" aria-label="Workforce summary">
        <a class="saas-metric saas-metric--glow" href="/admin/employees" style="--glow:#2563eb">
            <span class="saas-metric-icon"><i data-lucide="users"></i></span>
            <span class="saas-metric-copy">
                <span class="saas-metric-label">Total employees</span>
                <strong class="saas-metric-value"><?= number_format((int) ($stats['employees_total'] ?? 0)) ?></strong>
                <span class="saas-metric-delta" id="delta-employees"></span>
            </span>
            <span class="saas-metric-note is-positive"><i data-lucide="arrow-up-right"></i> <?= number_format((int) ($stats['employees_active'] ?? 0)) ?> currently active</span>
            <span class="saas-sparkline-wrap"><canvas id="spark-employees" aria-hidden="true"></canvas></span>
        </a>
        <a class="saas-metric is-teal saas-metric--glow" href="/admin/attendance" style="--glow:#0d9488">
            <span class="saas-metric-icon"><i data-lucide="circle-check-big"></i></span>
            <span class="saas-metric-copy">
                <span class="saas-metric-label">Present today</span>
                <strong class="saas-metric-value"><?= number_format((int) ($stats['present_today'] ?? 0)) ?></strong>
                <span class="saas-metric-delta" id="delta-present"></span>
            </span>
            <span class="saas-metric-note is-positive"><?= number_format(((int) ($stats['present_today'] ?? 0) / $activeEmployees) * 100, 1) ?>% of active employees</span>
            <span class="saas-sparkline-wrap"><canvas id="spark-present" aria-hidden="true"></canvas></span>
        </a>
        <a class="saas-metric is-coral saas-metric--glow" href="/admin/leave" style="--glow:#ef4444">
            <span class="saas-metric-icon"><i data-lucide="file-clock"></i></span>
            <span class="saas-metric-copy">
                <span class="saas-metric-label">Pending leaves</span>
                <strong class="saas-metric-value"><?= number_format((int) ($stats['pending_leaves'] ?? 0)) ?></strong>
                <span class="saas-metric-delta" id="delta-pending"></span>
            </span>
            <span class="saas-metric-note"><?= number_format((int) ($stats['pending_leaves'] ?? 0)) ?> leave requests awaiting review</span>
            <span class="saas-sparkline-wrap"><canvas id="spark-pending" aria-hidden="true"></canvas></span>
        </a>
        <a class="saas-metric is-purple saas-metric--glow" href="/admin/leave" style="--glow:#7c3aed">
            <span class="saas-metric-icon"><i data-lucide="tree-palm"></i></span>
            <span class="saas-metric-copy">
                <span class="saas-metric-label">On leave today</span>
                <strong class="saas-metric-value"><?= number_format((int) ($stats['on_leave_today'] ?? 0)) ?></strong>
                <span class="saas-metric-delta" id="delta-leave"></span>
            </span>
            <span class="saas-metric-note"><?= number_format($absentCount) ?> absent · <?= number_format($remoteCount) ?> remote</span>
            <span class="saas-sparkline-wrap"><canvas id="spark-leave" aria-hidden="true"></canvas></span>
        </a>
    </section>

    <div class="saas-dashboard-grid">
        <div class="saas-dashboard-column">
            <section class="saas-panel">
                <div class="saas-panel-head"><h2>Attendance trend</h2><a href="/admin/attendance">Last 7 days <i data-lucide="chevron-right"></i></a></div>
                <div class="saas-panel-body">
                    <div class="saas-chart"><canvas id="saasAttendanceChart" aria-label="Seven-day attendance trend"></canvas></div>
                    <div class="saas-chart-summary">
                        <div><span>Average attendance</span><strong><?= number_format($averageRate, 1) ?>%</strong></div>
                        <div><span>Best day</span><strong><?= $bestIndex !== null ? e(format_date($trend[$bestIndex]['dt'])) . ' (' . number_format($attendanceRates[$bestIndex], 1) . '%)' : '—' ?></strong></div>
                        <div><span>Lowest day</span><strong><?= $lowIndex !== null ? e(format_date($trend[$lowIndex]['dt'])) . ' (' . number_format($attendanceRates[$lowIndex], 1) . '%)' : '—' ?></strong></div>
                    </div>
                </div>
            </section>

            <section class="saas-panel">
                <div class="saas-panel-head"><h2>Department headcount</h2><a href="/admin/departments">All departments <i data-lucide="chevron-right"></i></a></div>
                <div class="saas-panel-body"><div class="saas-dept-chart"><canvas id="saasDepartmentChart" aria-label="Department headcount"></canvas></div></div>
            </section>

            <section class="saas-panel">
                <div class="saas-panel-head"><h2>Attendance exceptions</h2><a href="/admin/attendance">View all</a></div>
                <div class="saas-panel-body">
                    <?php if (empty($attendanceExceptions)): ?><div class="empty-state py-4"><i data-lucide="badge-check"></i>No exceptions in the last 14 days.</div>
                    <?php else: ?><div class="saas-exception-list"><?php foreach (array_slice($attendanceExceptions, 0, 6) as $exception): ?>
                        <a class="saas-exception" href="/admin/attendance/<?= (int) $exception['id'] ?>"><i data-lucide="triangle-alert"></i><div><strong><?= e(trim(($exception['first_name'] ?? '') . ' ' . ($exception['last_name'] ?? ''))) ?></strong><small><?= e(ucwords(str_replace('_', ' ', (string) $exception['status']))) ?> · <?= e(format_date($exception['attendance_date'])) ?></small></div></a>
                    <?php endforeach; ?></div><?php endif; ?>
                </div>
            </section>
        </div>

        <div class="saas-dashboard-column is-secondary">

            <!-- On Leave Today -->
            <section class="saas-panel">
                <div class="saas-panel-head">
                    <h2>On leave today <span class="badge text-bg-secondary ms-1"><?= count($onLeaveToday ?? []) ?></span></h2>
                    <a href="/admin/leave">View all <i data-lucide="chevron-right"></i></a>
                </div>
                <div class="saas-panel-body">
                <?php if (empty($onLeaveToday)): ?>
                    <div class="empty-state py-4"><i data-lucide="tree-palm"></i>All employees are in today.</div>
                <?php else: foreach ($onLeaveToday as $emp):
                    $initials = strtoupper(substr((string)($emp['first_name'] ?? ''), 0, 1) . substr((string)($emp['last_name'] ?? ''), 0, 1)); ?>
                    <a class="saas-onboarding" href="/admin/employees/<?= (int) $emp['employee_id'] ?>">
                        <span class="saas-avatar" style="background:var(--ems-primary,#3b82f6)"><?= e($initials) ?></span>
                        <div>
                            <strong><?= e(trim($emp['first_name'] . ' ' . $emp['last_name'])) ?></strong>
                            <small><?= e($emp['leave_type_name'] ?? 'Leave') ?> · <?= e($emp['department_name'] ?? 'Unassigned') ?></small>
                        </div>
                        <span class="badge text-bg-primary" style="font-size:.65rem">Out</span>
                    </a>
                <?php endforeach; endif; ?>
                </div>
            </section>

            <!-- Absent / No Check-in Today -->
            <section class="saas-panel">
                <div class="saas-panel-head">
                    <h2>Absent / no check-in <?php if (!empty($noShowToday)): ?><span class="badge text-bg-danger ms-1"><?= count($noShowToday) ?></span><?php endif; ?></h2>
                    <a href="/admin/attendance">View all <i data-lucide="chevron-right"></i></a>
                </div>
                <div class="saas-panel-body">
                <?php if (empty($noShowToday)): ?>
                    <div class="empty-state py-4"><i data-lucide="badge-check"></i>All active employees checked in today.</div>
                <?php else: foreach ($noShowToday as $row):
                    $initials   = strtoupper(substr((string)($row['first_name'] ?? ''), 0, 1) . substr((string)($row['last_name'] ?? ''), 0, 1));
                    $isAbsent   = ($row['status_label'] ?? '') === 'absent';
                    $badgeClass = $isAbsent ? 'text-bg-danger' : 'text-bg-secondary';
                    $badgeText  = $isAbsent ? 'Absent' : 'No check-in';
                    ?>
                    <a class="saas-onboarding" href="/admin/employees/<?= (int) $row['employee_id'] ?>">
                        <span class="saas-avatar" style="background:#ef4444;color:#fff"><?= e($initials) ?></span>
                        <div>
                            <strong><?= e(trim($row['first_name'] . ' ' . $row['last_name'])) ?></strong>
                            <small><?= e($row['department_name'] ?? 'Unassigned') ?> · <?= e($row['employee_code'] ?? '') ?></small>
                        </div>
                        <span class="badge <?= $badgeClass ?>" style="font-size:.65rem"><?= $badgeText ?></span>
                    </a>
                <?php endforeach; endif; ?>
                </div>
            </section>

            <!-- Recent Joiners -->
            <section class="saas-panel">
                <div class="saas-panel-head">
                    <h2>Recent joiners <span class="badge text-bg-secondary ms-1"><?= count($recentJoiners ?? []) ?></span></h2>
                    <a href="/admin/employees">View all <i data-lucide="chevron-right"></i></a>
                </div>
                <div class="saas-panel-body">
                <?php if (empty($recentJoiners)): ?>
                    <div class="empty-state py-4"><i data-lucide="user-round-check"></i>No new joiners in the last 60 days.</div>
                <?php else: foreach ($recentJoiners as $employee):
                    $initials = strtoupper(substr((string)($employee['first_name'] ?? ''), 0, 1) . substr((string)($employee['last_name'] ?? ''), 0, 1));
                    $daysAgo  = (int) ((strtotime('today') - strtotime((string)$employee['joining_date'])) / 86400);
                    $dayLabel = $daysAgo === 0 ? 'Today' : ($daysAgo === 1 ? 'Yesterday' : $daysAgo . 'd ago');
                    ?>
                    <a class="saas-onboarding" href="/admin/employees/<?= (int) $employee['id'] ?>">
                        <span class="saas-avatar" style="background:#10b981;color:#fff"><?= e($initials) ?></span>
                        <div>
                            <strong><?= e($employee['first_name'] . ' ' . $employee['last_name']) ?></strong>
                            <small><?= e($employee['designation_name'] ?? 'Employee') ?> · <?= e($employee['department_name'] ?? 'Unassigned') ?></small>
                        </div>
                        <time style="font-size:.7rem;color:var(--ems-text-muted,#6b7280);white-space:nowrap"><?= e($dayLabel) ?></time>
                    </a>
                <?php endforeach; endif; ?>
                </div>
            </section>

            <!-- Recent Leave Requests -->
            <section class="saas-panel">
                <div class="saas-panel-head"><h2>Recent leave requests</h2><a href="/admin/leave">View all <i data-lucide="chevron-right"></i></a></div>
                <div class="saas-panel-body">
                <?php if (empty($recentLeaves)): ?><div class="empty-state py-4"><i data-lucide="inbox"></i>No recent leave requests.</div>
                <?php else: foreach (array_slice($recentLeaves, 0, 5) as $leave): ?>
                    <a class="saas-onboarding" href="/admin/leave/<?= (int) $leave['id'] ?>">
                        <span class="saas-list-icon is-leave"><i data-lucide="tree-palm"></i></span>
                        <div>
                            <strong><?= e(trim($leave['first_name'] . ' ' . $leave['last_name'])) ?></strong>
                            <small><?= e($leave['leave_type_name']) ?> · <?= e(format_date($leave['start_date'])) ?></small>
                        </div>
                        <span><?= status_badge($leave['status']) ?></span>
                    </a>
                <?php endforeach; endif; ?>
                </div>
            </section>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const trend = <?= json_encode($trend, JSON_UNESCAPED_UNICODE) ?>;
    const rates = <?= json_encode($attendanceRates) ?>;
    const departments = <?= json_encode($departmentHeadcount ?? [], JSON_UNESCAPED_UNICODE) ?>;
    if (window.EMSCharts) {
        const primary = getComputedStyle(document.documentElement).getPropertyValue('--color-primary').trim() || '#2563eb';
        EMSCharts.line('saasAttendanceChart', trend.map(row => row.dt), [{label:'Attendance %',data:rates,borderColor:primary,backgroundColor:'rgba(37,99,235,.10)',pointBackgroundColor:primary}], {plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,max:100,ticks:{callback:value=>value+'%'}}}});
        EMSCharts.bar('saasDepartmentChart', departments.map(row => row.name), [{label:'Employees',data:departments.map(row => Number(row.total)),backgroundColor:primary,borderRadius:5,maxBarThickness:44}], false);
    }

    if (window.Chart && trend.length >= 2) {
        const sparkPresent  = trend.map(r => Number(r.present_count || 0));
        const sparkLate     = trend.map(r => Number(r.late_count    || 0));
        const sparkOnLeave  = trend.map(r => Number(r.absent_count  || 0));

        function setDelta(id, vals) {
            const el = document.getElementById(id);
            if (!el || vals.length < 2) return;
            const last = vals[vals.length - 1];
            const avg  = vals.slice(0, -1).reduce((s, v) => s + v, 0) / (vals.length - 1);
            if (avg === 0) return;
            const pct = ((last - avg) / avg) * 100;
            el.textContent = (pct >= 0 ? '↑' : '↓') + ' ' + Math.abs(pct).toFixed(1) + '%';
            el.className = 'saas-metric-delta ' + (Math.abs(pct) < 1 ? 'is-flat' : pct > 0 ? 'is-up' : 'is-down');
        }

        function drawSpark(id, vals, hex) {
            const ctx = document.getElementById(id);
            if (!ctx) return;
            const [r,g,b] = [hex.slice(1,3), hex.slice(3,5), hex.slice(5,7)].map(h => parseInt(h, 16));
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: trend.map(row => row.dt),
                    datasets: [{
                        data: vals,
                        backgroundColor: vals.map((v, i) => i === vals.length - 1
                            ? 'rgba('+r+','+g+','+b+',.8)'
                            : 'rgba('+r+','+g+','+b+',.35)'),
                        borderRadius: 3,
                        borderSkipped: false,
                        maxBarThickness: 14,
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    animation: { duration: 800, easing: 'easeOutQuart' },
                    plugins: { legend: { display: false }, tooltip: { enabled: false } },
                    scales: { x: { display: false }, y: { display: false, beginAtZero: true } }
                }
            });
        }

        setDelta('delta-employees', sparkPresent);
        setDelta('delta-present',   sparkPresent);
        setDelta('delta-pending',   sparkLate);
        setDelta('delta-leave',     sparkOnLeave);

        drawSpark('spark-employees', sparkPresent, '#2563eb');
        drawSpark('spark-present',   sparkPresent, '#0d9488');
        drawSpark('spark-pending',   sparkLate,    '#ef4444');
        drawSpark('spark-leave',     sparkOnLeave, '#7c3aed');
    }

    if (window.lucide) lucide.createIcons();
});
</script>
