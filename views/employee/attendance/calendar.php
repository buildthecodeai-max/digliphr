<?php
/** @var array $employee */
/** @var int $year */
/** @var int $month */
/** @var array $dayMap */
/** @var array $stats */
/** @var bool $correctionsMode */
/** @var array $records */
/** @var array $corrections */
/** @var string $csrfToken */

$correctionsMode = $correctionsMode ?? false;
$records = $records ?? [];
$corrections = $corrections ?? [];
$dayMap = $dayMap ?? [];
$stats = $stats ?? [];
$statusColors = [
    'present' => 'success',
    'late' => 'warning',
    'absent' => 'danger',
    'remote' => 'info',
    'half_day' => 'secondary',
    'on_leave' => 'primary',
    'holiday' => 'light',
    'weekend' => 'light',
    'manual' => 'dark',
    'missing_checkout' => 'warning',
];

if (!$correctionsMode) {
    $year = (int) ($year ?? date('Y'));
    $month = (int) ($month ?? date('n'));
    $firstDay = strtotime(sprintf('%04d-%02d-01', $year, $month));
    $daysInMonth = (int) date('t', $firstDay);
    $startWeekday = (int) date('N', $firstDay);
    $monthName = date('F Y', $firstDay);
    $prevMonth = $month === 1 ? [12, $year - 1] : [$month - 1, $year];
    $nextMonth = $month === 12 ? [1, $year + 1] : [$month + 1, $year];
}
?>
<div class="page-header">
    <div>
        <h1><?= e($title ?? ($correctionsMode ? 'Attendance Corrections' : 'Attendance Calendar')) ?></h1>
        <p class="subtitle"><?= e(trim(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? ''))) ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="/employee/attendance" class="btn btn-sm btn-soft">
            <i data-lucide="map-pin" class="me-1" style="width:14px;height:14px"></i>Check In/Out
        </a>
        <?php if (!$correctionsMode): ?>
        <a href="/employee/attendance/corrections" class="btn btn-sm btn-soft">
            <i data-lucide="pencil" class="me-1" style="width:14px;height:14px"></i>Corrections
        </a>
        <?php else: ?>
        <a href="/employee/attendance/calendar" class="btn btn-sm btn-soft">
            <i data-lucide="calendar" class="me-1" style="width:14px;height:14px"></i>Calendar
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if (!$correctionsMode): ?>
<div class="row g-3 metrics-row mb-3">
    <div class="col-6 col-lg stagger-item">
        <div class="metric-card tone-mint">
            <div class="metric-label">Present</div>
            <div class="metric-value"><?= (int) ($stats['present_count'] ?? 0) ?></div>
        </div>
    </div>
    <div class="col-6 col-lg stagger-item">
        <div class="metric-card tone-orange">
            <div class="metric-label">Late</div>
            <div class="metric-value"><?= (int) ($stats['late_count'] ?? 0) ?></div>
        </div>
    </div>
    <div class="col-6 col-lg stagger-item">
        <div class="metric-card tone-blue">
            <div class="metric-label">Remote</div>
            <div class="metric-value"><?= (int) ($stats['remote_count'] ?? 0) ?></div>
        </div>
    </div>
    <div class="col-6 col-lg stagger-item">
        <div class="metric-card tone-purple">
            <div class="metric-label">Work Hours</div>
            <div class="metric-value" style="font-size:1.15rem"><?= e(format_minutes((int) ($stats['total_work_minutes'] ?? 0))) ?></div>
        </div>
    </div>
    <div class="col-12 col-lg stagger-item">
        <div class="metric-card tone-cyan">
            <div class="metric-label">Overtime</div>
            <div class="metric-value" style="font-size:1.15rem"><?= e(format_minutes((int) ($stats['total_overtime_minutes'] ?? 0))) ?></div>
        </div>
    </div>
</div>

<div class="card ems-card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <a href="/employee/attendance/calendar?year=<?= $prevMonth[1] ?>&month=<?= $prevMonth[0] ?>" class="action-btn" title="Previous month" aria-label="Previous month">
            <i data-lucide="chevron-left"></i>
        </a>
        <strong><?= e($monthName) ?></strong>
        <a href="/employee/attendance/calendar?year=<?= $nextMonth[1] ?>&month=<?= $nextMonth[0] ?>" class="action-btn" title="Next month" aria-label="Next month">
            <i data-lucide="chevron-right"></i>
        </a>
    </div>
    <div class="card-body p-2">
        <div class="calendar-grid">
            <div class="calendar-row calendar-head">
                <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $d): ?>
                    <div class="calendar-cell text-muted small fw-semibold text-center py-1"><?= $d ?></div>
                <?php endforeach; ?>
            </div>
            <div class="calendar-row">
                <?php for ($i = 1; $i < $startWeekday; $i++): ?>
                    <div class="calendar-cell calendar-empty"></div>
                <?php endfor; ?>
                <?php for ($day = 1; $day <= $daysInMonth; $day++):
                    $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                    $entry = $dayMap[$date] ?? null;
                    $dayOfWeek = (int) date('N', strtotime($date));
                    $isFuture = $date > date('Y-m-d');
                    if (!$entry && !$isFuture && $dayOfWeek < 6) {
                        $status = 'absent';
                    } elseif (!$entry && ($isFuture || $dayOfWeek >= 6)) {
                        $status = $dayOfWeek >= 6 ? 'weekend' : '';
                    } else {
                        $status = $entry['status'] ?? '';
                    }
                    $color = $statusColors[$status] ?? 'light';
                    $syntheticAbsent = !$entry && $status === 'absent';
                ?>
                    <div class="calendar-cell border rounded p-1 <?= ($entry || $syntheticAbsent) ? 'bg-' . e($color) . '-subtle' : '' ?>">
                        <div class="small fw-semibold"><?= $day ?></div>
                        <?php if ($entry): ?>
                            <div class="small"><?= status_badge($status) ?></div>
                            <?php if (!empty($entry['check_in_at'])): ?>
                                <div class="text-muted" style="font-size:.7rem"><?= e(date(config('app.time_format', 'g:i A'), strtotime($entry['check_in_at']))) ?></div>
                            <?php endif; ?>
                        <?php elseif ($syntheticAbsent): ?>
                            <div class="small"><?= status_badge('absent') ?></div>
                        <?php endif; ?>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($correctionsMode): ?>
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card ems-card">
            <div class="card-header">Request Correction</div>
            <div class="card-body">
                <form method="post" action="/employee/attendance/corrections">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label small" for="attendance_id">Attendance Record</label>
                        <select name="attendance_id" class="form-select form-select-sm" required id="attendance_id">
                            <option value="">Select date…</option>
                            <?php foreach ($records as $row): ?>
                            <option value="<?= (int) $row['id'] ?>">
                                <?= e(format_date($row['attendance_date'])) ?> —
                                <?= e($row['status']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small" for="requested_check_in_at">Requested Check In</label>
                        <input type="datetime-local" name="requested_check_in_at" class="form-control form-control-sm" id="requested_check_in_at">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small" for="requested_check_out_at">Requested Check Out</label>
                        <input type="datetime-local" name="requested_check_out_at" class="form-control form-control-sm" id="requested_check_out_at">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small" for="reason">Reason</label>
                        <textarea name="reason" class="form-control form-control-sm" rows="3" required id="reason"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Submit Request</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card ems-card">
            <div class="card-header">My Correction Requests</div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Requested In</th>
                            <th>Requested Out</th>
                            <th>Status</th>
                            <th>Submitted</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($corrections)): ?>
                        <tr><td colspan="5"><div class="empty-state py-4 mb-0">No correction requests.</div></td></tr>
                    <?php else: ?>
                        <?php foreach ($corrections as $c): ?>
                        <tr>
                            <td><?= e(format_date($c['attendance_date'])) ?></td>
                            <td><?= e(format_datetime($c['requested_check_in_at'] ?? null)) ?></td>
                            <td><?= e(format_datetime($c['requested_check_out_at'] ?? null)) ?></td>
                            <td><?= status_badge($c['status']) ?></td>
                            <td><?= e(format_datetime($c['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
.calendar-grid { display: flex; flex-direction: column; gap: .25rem; }
.calendar-row { display: grid; grid-template-columns: repeat(7, 1fr); gap: .25rem; }
.calendar-cell { min-height: 4.5rem; background: #fff; }
.calendar-empty { background: transparent; border: none !important; }
</style>
