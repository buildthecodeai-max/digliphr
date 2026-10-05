<?php
$firstName = explode(' ', trim((string) ($authUser['name'] ?? 'there')))[0] ?: 'there';
$trend = array_slice($attendanceTrend ?? [], -7);
$presentValues = array_map(static fn(array $row): int => (int) ($row['present_count'] ?? 0), $trend);
$activeEmployees = max(1, (int) ($stats['employees_active'] ?? 0));
$attendanceRates = array_map(static fn(int $value): float => round(($value / $activeEmployees) * 100, 1), $presentValues);
$averageRate = $attendanceRates ? round(array_sum($attendanceRates) / count($attendanceRates), 1) : 0;
$bestIndex = $attendanceRates ? array_search(max($attendanceRates), $attendanceRates, true) : null;
$lowIndex = $attendanceRates ? array_search(min($attendanceRates), $attendanceRates, true) : null;
$approvalIconMap = ['leave' => ['tree-palm', 'is-leave'], 'attendance' => ['calendar-check', ''], 'overtime' => ['timer', ''], 'loan' => ['hand-coins', 'is-payroll'], 'payroll' => ['wallet', 'is-payroll']];
?>

<div class="saas-dashboard">
    <header class="saas-dashboard-head wow-hero">
        <div>
            <h1>Good <?= date('G') < 12 ? 'morning' : (date('G') < 18 ? 'afternoon' : 'evening') ?>, <?= e($firstName) ?></h1>
            <p><?= e($dashboardProfile['subtitle'] ?? 'Here is what is happening across your workforce today.') ?></p>
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
        <a class="saas-metric" href="/admin/employees">
            <span class="saas-metric-icon"><i data-lucide="users"></i></span>
            <span class="saas-metric-copy"><span class="saas-metric-label">Total employees</span><strong class="saas-metric-value"><?= number_format((int) ($stats['employees_total'] ?? 0)) ?></strong></span>
            <span class="saas-metric-note is-positive"><i data-lucide="arrow-up-right"></i> <?= number_format((int) ($stats['employees_active'] ?? 0)) ?> currently active</span>
        </a>
        <a class="saas-metric is-teal" href="/admin/attendance">
            <span class="saas-metric-icon"><i data-lucide="circle-check-big"></i></span>
            <span class="saas-metric-copy"><span class="saas-metric-label">Present today</span><strong class="saas-metric-value"><?= number_format((int) ($stats['present_today'] ?? 0)) ?></strong></span>
            <span class="saas-metric-note is-positive"><?= number_format(((int) ($stats['present_today'] ?? 0) / $activeEmployees) * 100, 1) ?>% of active employees</span>
        </a>
        <a class="saas-metric is-coral" href="/admin/approvals">
            <span class="saas-metric-icon"><i data-lucide="file-clock"></i></span>
            <span class="saas-metric-copy"><span class="saas-metric-label">Pending approvals</span><strong class="saas-metric-value"><?= number_format(max(count($approvalItems ?? []), (int) ($stats['pending_leaves'] ?? 0))) ?></strong></span>
            <span class="saas-metric-note"><?= number_format((int) ($stats['pending_leaves'] ?? 0)) ?> leave requests awaiting review</span>
        </a>
        <a class="saas-metric is-purple" href="/admin/payroll">
            <span class="saas-metric-icon"><i data-lucide="wallet-cards"></i></span>
            <span class="saas-metric-copy"><span class="saas-metric-label">Latest payroll</span><strong class="saas-metric-value"><?= e(format_money($latestPayrollTotal ?? 0)) ?></strong></span>
            <span class="saas-metric-note"><?= number_format((int) ($stats['payroll_pending'] ?? 0)) ?> periods need attention</span>
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
            <section class="saas-panel">
                <div class="saas-panel-head"><h2>Approval inbox</h2><a href="/admin/approvals">View all</a></div>
                <div class="saas-panel-body">
                <?php if (empty($approvalItems)): ?><div class="empty-state py-4"><i data-lucide="inbox"></i>No requests need review.</div>
                <?php else: foreach (array_slice($approvalItems, 0, 4) as $item): $module = (string) ($item['module'] ?? 'approval'); [$icon, $tone] = $approvalIconMap[$module] ?? ['clipboard-check', '']; ?>
                    <article class="saas-approval"><span class="saas-list-icon <?= e($tone) ?>"><i data-lucide="<?= e($icon) ?>"></i></span><span class="saas-approval-copy"><strong><?= e($item['title'] ?? 'Approval request') ?></strong><span><?= e($item['employee_name'] ?? 'Employee') ?> · <?= e($item['employee_code'] ?? '') ?></span><small><?= e($item['detail'] ?? 'Review requested') ?></small></span><a class="saas-review" href="<?= e($item['url'] ?? '/admin/approvals') ?>">Review</a></article>
                <?php endforeach; endif; ?>
                </div>
            </section>

            <section class="saas-panel">
                <div class="saas-panel-head"><h2>Onboarding next 30 days</h2><a href="/admin/employees">View all</a></div>
                <div class="saas-panel-body">
                <?php if (empty($onboardingEmployees)): ?><div class="empty-state py-4"><i data-lucide="user-round-check"></i>No upcoming starters.</div>
                <?php else: foreach (array_slice($onboardingEmployees, 0, 5) as $employee): $initials = strtoupper(substr((string) $employee['first_name'], 0, 1) . substr((string) $employee['last_name'], 0, 1)); ?>
                    <a class="saas-onboarding" href="/admin/employees/<?= (int) $employee['id'] ?>"><span class="saas-avatar"><?= e($initials) ?></span><div><strong><?= e($employee['first_name'] . ' ' . $employee['last_name']) ?></strong><small><?= e($employee['designation_name'] ?? 'Employee') ?> · <?= e($employee['department_name'] ?? 'Unassigned') ?></small></div><time><?= e(format_date($employee['joining_date'])) ?></time></a>
                <?php endforeach; endif; ?>
                </div>
            </section>

            <section class="saas-panel">
                <div class="saas-panel-head"><h2>Recent payroll periods</h2><a href="/admin/payroll">View all</a></div>
                <div class="saas-panel-body">
                <?php if (empty($recentPayroll)): ?><div class="empty-state py-4">No payroll periods available.</div>
                <?php else: foreach (array_slice($recentPayroll, 0, 4) as $period): ?>
                    <a class="saas-onboarding" href="/admin/payroll/<?= (int) $period['id'] ?>"><span class="saas-list-icon is-payroll"><i data-lucide="wallet"></i></span><div><strong><?= e($period['name'] ?? ($period['period_year'] . '-' . $period['period_month'])) ?></strong><small><?= number_format((int) ($period['total_employees'] ?? 0)) ?> employees · <?= e(format_money($period['total_net'] ?? 0)) ?></small></div><span><?= status_badge($period['status']) ?></span></a>
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
    if (window.lucide) lucide.createIcons();
});
</script>
