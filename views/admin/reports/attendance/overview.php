<?php
/** @var array $bulkTotals */
$t = $bulkTotals ?? [];
$showCharts = in_array($viewMode, ['graphical', 'combined'], true);
$showTables = in_array($viewMode, ['table', 'combined'], true);

$attendancePct = (float) ($t['attendance_pct'] ?? 0);
$heroTone = $attendancePct >= 90 ? '#22C55E' : ($attendancePct >= 75 ? '#F59E0B' : '#EF4444');
$periodLabel = (!empty($filters['from']) && !empty($filters['to']))
    ? date('j M', strtotime($filters['from'])) . ' – ' . date('j M Y', strtotime($filters['to']))
    : 'the selected period';

?>
<div class="report-hero" style="--hero-tone: <?= e($heroTone) ?>">
    <div class="report-hero-ring" style="--pct: <?= max(0, min(100, $attendancePct)) ?>">
        <div class="report-hero-ring-value"><?= number_format($attendancePct, 0) ?>%</div>
    </div>
    <div class="report-hero-body">
        <div class="report-hero-eyebrow">Attendance Health · <?= e($periodLabel) ?></div>
        <h2 class="report-hero-headline">
            <?php if ($attendancePct >= 90): ?>Workforce is showing up strong
            <?php elseif ($attendancePct >= 75): ?>Attendance is holding steady
            <?php else: ?>Attendance needs attention
            <?php endif; ?>
        </h2>
        <p class="report-hero-sub"><?= number_format((float) ($t['present_days'] ?? 0), 1) ?> present days out of <?= number_format((float) ($t['scheduled_working_days'] ?? 0), 1) ?> scheduled, across <?= (int) ($t['employee_count'] ?? 0) ?> employees.</p>
    </div>
    <div class="report-hero-stats">
        <div class="report-hero-stat"><div class="report-hero-stat-value"><?= (int) ($t['employee_count'] ?? 0) ?></div><div class="report-hero-stat-label">Employees</div></div>
        <div class="report-hero-stat"><div class="report-hero-stat-value"><?= number_format((float) ($t['present_days'] ?? 0), 1) ?></div><div class="report-hero-stat-label">Present</div></div>
        <div class="report-hero-stat"><div class="report-hero-stat-value"><?= number_format((float) ($t['absent_days'] ?? 0), 1) ?></div><div class="report-hero-stat-label">Absent</div></div>
        <div class="report-hero-stat"><div class="report-hero-stat-value"><?= (int) ($t['not_checked_in_today'] ?? 0) ?></div><div class="report-hero-stat-label">Not In Today</div></div>
    </div>
</div>

<?php if ($showCharts): ?>
<div class="row g-3 mb-3">
    <div class="col-lg-8"><div class="card ems-card"><div class="card-header">Attendance Trend</div><div class="card-body chart-panel"><canvas id="attTrend"></canvas></div></div></div>
    <div class="col-lg-4"><div class="card ems-card"><div class="card-header">Status Mix</div><div class="card-body chart-panel"><canvas id="attDonut"></canvas></div></div></div>
    <div class="col-lg-6"><div class="card ems-card"><div class="card-header">By Department</div><div class="card-body chart-panel"><canvas id="attDept"></canvas></div></div></div>
    <div class="col-lg-6">
        <div class="card ems-card">
            <div class="card-header">Work Hours</div>
            <div class="card-body small">
                <div class="d-flex justify-content-between py-1 border-bottom"><span>Average work</span><strong><?= e(format_minutes((int)$workHours['avg_work_minutes'])) ?></strong></div>
                <div class="d-flex justify-content-between py-1 border-bottom"><span>Required</span><strong><?= e(format_minutes((int)$workHours['required_minutes'])) ?></strong></div>
                <div class="d-flex justify-content-between py-1 border-bottom"><span>Overtime total</span><strong><?= e(format_minutes((int)$workHours['total_overtime_minutes'])) ?></strong></div>
                <div class="d-flex justify-content-between py-1"><span>Missing</span><strong><?= e(format_minutes((int)$workHours['missing_minutes'])) ?></strong></div>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card ems-card">
            <div class="card-header d-flex justify-content-between"><span>Attendance Heatmap</span><span class="small text-muted">Present · Late · Leave · Absent</span></div>
            <div class="card-body heatmap-wrap">
                <?php if (empty($heatmap['employees'])): ?>
                    <div class="empty-state"><i class="bi bi-calendar-x"></i>No attendance records were found for the selected period.</div>
                <?php else: ?>
                <table class="heatmap-table">
                    <thead>
                    <tr>
                        <th style="text-align:left;min-width:120px">Employee</th>
                        <?php foreach ($heatmap['dates'] as $d): ?><th title="<?= e($d) ?>"><?= e(date('d', strtotime($d))) ?></th><?php endforeach; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($heatmap['employees'] as $i => $emp): ?>
                        <tr>
                            <th style="text-align:left"><?= e($emp['name']) ?></th>
                            <?php foreach ($heatmap['matrix'][$i] as $status): ?>
                                <?php
                                $cls = match ($status) {
                                    'present', 'remote', 'manual' => 'hm-present',
                                    'absent' => 'hm-absent',
                                    'late' => 'hm-late',
                                    'on_leave' => 'hm-leave',
                                    'half_day' => 'hm-half',
                                    default => 'hm-empty',
                                };
                                ?>
                                <td><span class="heatmap-cell <?= $cls ?>" title="<?= e(($status ?: 'none') . '') ?>"></span></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($showTables): ?>
<?php
// Group records by date for the date-split view
$byDate = [];
foreach ($table['data'] as $r) {
    $byDate[$r['attendance_date']][] = $r;
}
?>
<div class="card ems-card">
    <div class="card-header d-flex justify-content-between">
        <span>Attendance Detail</span>
        <span class="small text-muted"><?= (int) $table['total'] ?> records</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
            <thead><tr><th>Employee</th><th>Dept</th><th>Shift</th><th>In</th><th>Out</th><th>Hours</th><th>OT</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($table['data'])): ?>
                <tr><td colspan="9"><div class="empty-state mb-0 py-4">No attendance records were found for the selected period.</div></td></tr>
            <?php else: foreach ($byDate as $attDate => $dateRows): ?>
                <tr class="att-date-group-row">
                    <td colspan="9">
                        <span class="att-date-label"><?= e(date('l, j F Y', strtotime($attDate))) ?></span>
                        <span class="att-date-count"><?= count($dateRows) ?> employee<?= count($dateRows) !== 1 ? 's' : '' ?></span>
                    </td>
                </tr>
                <?php foreach ($dateRows as $r): ?>
                <tr>
                    <td><div class="fw-semibold"><?= e($r['employee_name']) ?></div><div class="small text-muted"><?= e($r['employee_code']) ?></div></td>
                    <td><?= e($r['department_name'] ?? '—') ?></td>
                    <td><?= e($r['shift_name'] ?? '—') ?></td>
                    <td><?= e($r['check_in_at'] ? date(config('app.time_format', 'g:i A'), strtotime($r['check_in_at'])) : '—') ?></td>
                    <td><?= e($r['check_out_at'] ? date(config('app.time_format', 'g:i A'), strtotime($r['check_out_at'])) : '—') ?></td>
                    <td><?= e(format_minutes((int) $r['work_minutes'])) ?></td>
                    <td><?= e(format_minutes((int) $r['overtime_minutes'])) ?></td>
                    <td><?= status_badge($r['status']) ?></td>
                    <td>
                        <button type="button" class="btn btn-sm btn-soft js-att-detail"
                            data-name="<?= e($r['employee_name']) ?>"
                            data-code="<?= e($r['employee_code']) ?>"
                            data-date="<?= e(format_date($r['attendance_date'])) ?>"
                            data-status="<?= e($r['status']) ?>"
                            data-dept="<?= e($r['department_name'] ?? '—') ?>"
                            data-shift="<?= e($r['shift_name'] ?? '—') ?>"
                            data-in="<?= e($r['check_in_at'] ? date(config('app.time_format', 'g:i A'), strtotime($r['check_in_at'])) : '—') ?>"
                            data-out="<?= e($r['check_out_at'] ? date(config('app.time_format', 'g:i A'), strtotime($r['check_out_at'])) : '—') ?>"
                            data-hours="<?= e(format_minutes((int) $r['work_minutes'])) ?>"
                            data-ot="<?= e(format_minutes((int) $r['overtime_minutes'])) ?>">
                            View
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (($table['last_page'] ?? 1) > 1): ?>
    <div class="card-footer bg-white d-flex justify-content-end">
        <?= paginate_links($table, '/admin/reports/attendance?' . http_build_query(array_merge($_GET, ['page' => null]))) ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const trend = <?= json_encode($trend, JSON_UNESCAPED_UNICODE) ?>;
    const dist = <?= json_encode($distribution, JSON_UNESCAPED_UNICODE) ?>;
    const dept = <?= json_encode($byDepartment, JSON_UNESCAPED_UNICODE) ?>;
    const qs = <?= json_encode(http_build_query(array_filter([
        'from' => $filters['from'] ?? null,
        'to' => $filters['to'] ?? null,
        'branch_id' => $filters['branch_id'] ?? null,
        'department_id' => $filters['department_id'] ?? null,
        'view' => $viewMode ?? 'table',
    ]))) ?>;

    if (window.EMSCharts) {
        if (trend.labels?.length) {
            EMSCharts.line('attTrend', trend.labels, [
                {label:'Present', data:trend.present, borderColor:EMSCharts.palette.present, backgroundColor:'rgba(47,158,68,.12)'},
                {label:'Remote / WFH', data:trend.remote, borderColor:'#3b8dff', backgroundColor:'rgba(59,141,255,.10)'},
                {label:'Absent', data:trend.absent, borderColor:EMSCharts.palette.absent, backgroundColor:'rgba(224,49,49,.08)'},
                {label:'Leave', data:trend.on_leave, borderColor:EMSCharts.palette.leave, backgroundColor:'rgba(59,91,219,.08)'},
                {label:'Late', data:trend.late, borderColor:EMSCharts.palette.late, backgroundColor:'rgba(230,119,0,.08)'},
            ], {
                onClick: function (_evt, elements) {
                    if (!elements.length) return;
                    const label = trend.labels[elements[0].index];
                    window.location.href = '/admin/reports/attendance?' + qs + '&from=' + encodeURIComponent(label) + '&to=' + encodeURIComponent(label);
                }
            });
        }
        if (dist.labels?.length) {
            const chart = EMSCharts.doughnut('attDonut', dist.labels, dist.values);
            if (chart) {
                chart.options.onClick = function (_evt, elements) {
                    if (!elements.length) return;
                    const status = (dist.raw?.[elements[0].index]?.status) || '';
                    if (window.EMS) {
                        EMS.openDrawer(dist.labels[elements[0].index],
                            '<p class="small text-muted mb-2">Drill into attendance records with this status.</p>' +
                            '<a class="btn btn-sm btn-primary" href="/admin/reports/attendance?' + qs + '&status=' + encodeURIComponent(status) + '">Open filtered table</a>');
                    }
                };
                chart.update();
            }
        }
        if (dept.length) {
            const chart = EMSCharts.bar('attDept', dept.map(r=>r.department), [{label:'Attendance %', data:dept.map(r=>r.attendance_pct)}], true);
            if (chart) {
                chart.options.onClick = function (_evt, elements) {
                    if (!elements.length) return;
                    const row = dept[elements[0].index];
                    if (!row || !window.EMS) return;
                    EMS.openDrawer(row.department,
                        '<div class="small">' +
                        '<div class="d-flex justify-content-between py-1 border-bottom"><span>Attendance</span><strong>' + row.attendance_pct + '%</strong></div>' +
                        '<div class="d-flex justify-content-between py-1 border-bottom"><span>Present</span><strong>' + row.present + '</strong></div>' +
                        '<div class="d-flex justify-content-between py-1 border-bottom"><span>Absent</span><strong>' + row.absent + '</strong></div>' +
                        '<div class="d-flex justify-content-between py-1"><span>Late</span><strong>' + row.late + '</strong></div></div>');
                };
                chart.update();
            }
        }
    }

    document.querySelectorAll('.js-att-detail').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!window.EMS) return;
            EMS.openDrawer(btn.dataset.name, [
                '<div class="small">',
                '<div class="text-muted mb-2">' + btn.dataset.code + ' · ' + btn.dataset.date + '</div>',
                '<div class="d-flex justify-content-between py-1 border-bottom"><span>Status</span><strong>' + btn.dataset.status + '</strong></div>',
                '<div class="d-flex justify-content-between py-1 border-bottom"><span>Department</span><strong>' + btn.dataset.dept + '</strong></div>',
                '<div class="d-flex justify-content-between py-1 border-bottom"><span>Shift</span><strong>' + btn.dataset.shift + '</strong></div>',
                '<div class="d-flex justify-content-between py-1 border-bottom"><span>Check-in</span><strong>' + btn.dataset.in + '</strong></div>',
                '<div class="d-flex justify-content-between py-1 border-bottom"><span>Check-out</span><strong>' + btn.dataset.out + '</strong></div>',
                '<div class="d-flex justify-content-between py-1 border-bottom"><span>Hours</span><strong>' + btn.dataset.hours + '</strong></div>',
                '<div class="d-flex justify-content-between py-1"><span>Overtime</span><strong>' + btn.dataset.ot + '</strong></div>',
                '</div>'
            ].join(''));
        });
    });
});
</script>
