<?php
/** @var array $employee */
/** @var array $today */
/** @var array $history */
/** @var string $csrfToken */

$attendance = $today['attendance'] ?? null;
$shift = $today['shift'] ?? [];
$canCheckIn = $today['can_check_in'] ?? false;
$canCheckOut = $today['can_check_out'] ?? false;
$remoteAllowed = $today['remote_allowed'] ?? false;
$attendanceSecurityMode = $attendanceSecurityMode ?? 'disabled';
?>
<div class="page-header">
    <div>
        <h1>My Attendance</h1>
        <p class="subtitle"><?= e(format_date(date('Y-m-d'))) ?> · <?= e($shift['name'] ?? 'No shift assigned') ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="/employee/attendance/calendar" class="btn btn-sm btn-soft">
            <i data-lucide="calendar" class="me-1" style="width:14px;height:14px"></i>Calendar
        </a>
        <a href="/employee/attendance/corrections" class="btn btn-sm btn-soft">
            <i data-lucide="pencil" class="me-1" style="width:14px;height:14px"></i>Corrections
        </a>
    </div>
</div>
<div id="attendance-offline-banner" class="alert alert-warning offline-banner d-none" role="status"><i data-lucide="wifi-off" class="me-2"></i><strong>You are offline.</strong> Attendance actions will be saved on this device and retried when the connection returns. Keep this page open.</div>
<?php if ($attendanceSecurityMode !== 'disabled'): ?>
<div class="alert alert-light border small d-flex gap-2 align-items-start" role="status">
    <i data-lucide="shield-check" class="text-primary flex-shrink-0" style="width:18px;height:18px"></i>
    <div><strong>Secure check-in is enabled.</strong> <?= in_array($attendanceSecurityMode, ['device_only', 'device_and_ip'], true) ? 'Use your approved device.' : '' ?> <?= in_array($attendanceSecurityMode, ['ip_only', 'device_and_ip'], true) ? 'Your network will also be verified.' : '' ?></div>
</div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card ems-card h-100">
            <div class="card-header">Live Check-In / Check-Out</div>
            <div class="card-body">
                <div id="attendance-status-bar" class="alert alert-light border small mb-3 attendance-live-status">
                    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                        <span>Status: <?= $attendance ? status_badge($attendance['status']) : status_badge('absent') ?></span>
                        <span id="geo-status" class="text-muted">Waiting for location…</span>
                    </div>
                    <div class="small text-secondary mt-2" id="attendance-guidance">
                        <?= $canCheckIn ? 'Ready to check in. Location verification will run when you continue.' : ($canCheckOut ? 'You are checked in. Check out when your workday is complete.' : 'Today’s attendance is complete or unavailable for changes.') ?>
                    </div>
                </div>

                <div class="attendance-diagnostics mb-3" aria-label="Attendance diagnostics">
                    <div class="diagnostic-item" id="diagnostic-network"><span class="diagnostic-dot"></span><strong>Network</strong><span data-diagnostic-text>Checking…</span></div>
                    <div class="diagnostic-item" id="diagnostic-gps"><span class="diagnostic-dot"></span><strong>Location</strong><span data-diagnostic-text>Waiting…</span></div>
                    <div class="diagnostic-item" id="diagnostic-camera"><span class="diagnostic-dot"></span><strong>Camera</strong><span data-diagnostic-text>Optional</span></div>
                </div>

                <!-- Camera section — hidden until user opts in -->
                <div id="camera-section" class="d-none mb-3">
                    <div class="ratio ratio-4x3 bg-dark rounded mb-2 position-relative overflow-hidden">
                        <video id="attendance-video" autoplay playsinline muted class="d-none w-100 h-100 object-fit-cover"></video>
                        <canvas id="attendance-canvas" class="d-none"></canvas>
                        <img id="attendance-preview" class="d-none w-100 h-100 object-fit-cover" alt="Preview">
                        <div id="camera-placeholder" class="d-flex align-items-center justify-content-center text-white-50">
                            <div class="text-center px-3">
                                <i data-lucide="camera" class="mb-2" style="width:36px;height:36px"></i>
                                <div class="small">Camera optional</div>
                                <div class="small opacity-75 mt-1">GPS is enough to check in</div>
                            </div>
                        </div>
                    </div>

                    <div id="camera-status" class="small text-muted mb-2">Photo optional. Use Start Camera only if you want a selfie.</div>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" id="btn-start-camera" class="btn btn-sm btn-soft">
                            <i data-lucide="camera" class="me-1" style="width:14px;height:14px"></i>Start Camera
                        </button>
                        <button type="button" id="btn-capture" class="btn btn-sm btn-soft" disabled>
                            <i data-lucide="aperture" class="me-1" style="width:14px;height:14px"></i>Capture
                        </button>
                        <button type="button" id="btn-retake" class="btn btn-sm btn-soft d-none">
                            <i data-lucide="rotate-ccw" class="me-1" style="width:14px;height:14px"></i>Retake
                        </button>
                    </div>
                </div>

                <div class="mb-3">
                    <button type="button" id="btn-toggle-camera" class="btn btn-sm btn-soft w-100">
                        <i data-lucide="camera" class="me-1" style="width:14px;height:14px"></i>Add Selfie Photo (Optional)
                    </button>
                </div>

                <?php if ($remoteAllowed): ?>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="is-remote" value="1">
                    <label class="form-check-label small" for="is-remote">Mark as remote attendance</label>
                </div>
                <?php endif; ?>

                <div class="d-grid gap-2 attendance-actions">
                    <button type="button" id="btn-check-in" class="btn btn-success" <?= $canCheckIn ? '' : 'disabled' ?>>
                        <i data-lucide="log-in" class="me-1" style="width:14px;height:14px"></i>Check In
                    </button>
                    <button type="button" id="btn-check-out" class="btn btn-danger" <?= $canCheckOut ? '' : 'disabled' ?>>
                        <i data-lucide="log-out" class="me-1" style="width:14px;height:14px"></i>Check Out
                    </button>
                </div>

                <div id="attendance-message" class="mt-3"></div>
                <button type="button" id="btn-retry-attendance" class="btn btn-sm btn-outline-primary w-100 mt-2 d-none"><i data-lucide="refresh-cw" class="me-1"></i>Retry saved attendance now</button>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="row g-3 metrics-row mb-3">
            <div class="col-6 col-md-3 stagger-item">
                <div class="metric-card tone-mint">
                    <div class="metric-label">Check In</div>
                    <div class="metric-value" style="font-size:1rem" id="summary-check-in"><?= e(format_datetime($attendance['check_in_at'] ?? null)) ?></div>
                </div>
            </div>
            <div class="col-6 col-md-3 stagger-item">
                <div class="metric-card tone-orange">
                    <div class="metric-label">Check Out</div>
                    <div class="metric-value" style="font-size:1rem" id="summary-check-out"><?= e(format_datetime($attendance['check_out_at'] ?? null)) ?></div>
                </div>
            </div>
            <div class="col-6 col-md-3 stagger-item">
                <div class="metric-card tone-purple">
                    <div class="metric-label">Work Time</div>
                    <div class="metric-value" style="font-size:1rem" id="summary-work"><?= e(format_minutes((int) ($attendance['work_minutes'] ?? 0))) ?></div>
                </div>
            </div>
            <div class="col-6 col-md-3 stagger-item">
                <div class="metric-card tone-blue">
                    <div class="metric-label">Late</div>
                    <div class="metric-value" style="font-size:1rem"><?= e(format_minutes((int) ($attendance['late_minutes'] ?? 0))) ?></div>
                </div>
            </div>
        </div>

        <div class="card ems-card">
            <div class="card-header">Recent History</div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Check In</th>
                            <th>Check Out</th>
                            <th>Status</th>
                            <th>Work</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($history)): ?>
                        <tr><td colspan="5"><div class="empty-state py-4 mb-0">No attendance records yet.</div></td></tr>
                    <?php else: ?>
                        <?php foreach ($history as $row): ?>
                        <tr>
                            <td><?= e(format_date($row['attendance_date'])) ?></td>
                            <td><?= e(format_datetime($row['check_in_at'] ?? null, config('app.time_format', 'H:i'))) ?></td>
                            <td><?= e(format_datetime($row['check_out_at'] ?? null, config('app.time_format', 'H:i'))) ?></td>
                            <td><?= status_badge($row['status']) ?></td>
                            <td><?= e(format_minutes((int) ($row['work_minutes'] ?? 0))) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
window.ATTENDANCE_CONFIG = {
    csrfToken: <?= json_encode($csrfToken) ?>,
    canCheckIn: <?= $canCheckIn ? 'true' : 'false' ?>,
    canCheckOut: <?= $canCheckOut ? 'true' : 'false' ?>,
    remoteAllowed: <?= $remoteAllowed ? 'true' : 'false' ?>,
    api: {
        checkIn: '/api/attendance/check-in',
        checkOut: '/api/attendance/check-out',
        todayStatus: '/api/attendance/today-status'
    }
};
</script>
<?php
// Cache-bust so cPanel/CDN/browser cannot keep an old auto-camera attendance.js
$attendanceJsPath = dirname(__DIR__, 3) . '/public/assets/js/attendance.js';
$attendanceJsVer = is_file($attendanceJsPath) ? (string) filemtime($attendanceJsPath) : 'camera-optional-v2';
?>
<script src="<?= e(asset('js/attendance.js')) ?>?v=<?= e($attendanceJsVer) ?>"></script>
<script>
document.getElementById('btn-toggle-camera').addEventListener('click', function () {
    document.getElementById('camera-section').classList.remove('d-none');
    this.classList.add('d-none');
});
</script>
