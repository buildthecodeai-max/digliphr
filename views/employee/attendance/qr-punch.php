<?php
$isCheckedIn  = !empty($att) && empty($att['check_out_at']);
$isCheckedOut = !empty($att) && !empty($att['check_out_at']);
?>
<div class="page-header">
    <div>
        <h1><i data-lucide="qr-code" class="me-2" style="width:22px;height:22px"></i>QR Check-In</h1>
        <?php if ($qr): ?>
        <p class="subtitle"><?= e($qr['name']) ?><?= $qr['location'] ? ' — ' . e($qr['location']) : '' ?></p>
        <?php endif; ?>
    </div>
    <a href="/employee/attendance" class="btn btn-sm btn-soft">
        <i data-lucide="arrow-left" class="me-1" style="width:14px;height:14px"></i>My Attendance
    </a>
</div>

<?php include config('app.paths.views') . '/partials/alerts.php'; ?>

<?php if (!empty($error)): ?>
<div class="alert alert-danger">
    <i data-lucide="x-circle" class="me-2" style="width:16px;height:16px"></i>
    <?= e($error) ?>
</div>

<?php elseif ($isCheckedOut): ?>
<div class="card ems-card text-center py-5">
    <i data-lucide="check-circle-2" class="text-success mx-auto mb-3" style="width:52px;height:52px"></i>
    <h4 class="mb-1">Already checked out today</h4>
    <p class="text-muted mb-3">
        You checked in at <?= e(format_time($att['check_in_at'])) ?>
        and out at <?= e(format_time($att['check_out_at'])) ?>.
    </p>
    <a href="/employee/attendance" class="btn btn-soft btn-sm">View Attendance</a>
</div>

<?php elseif ($isCheckedIn): ?>
<div class="card ems-card text-center py-5">
    <div class="mb-3">
        <span class="badge bg-success fs-6 px-4 py-2">Currently Checked In</span>
    </div>
    <p class="text-muted mb-4">
        You checked in at <strong><?= e(format_time($att['check_in_at'])) ?></strong>
        &nbsp;·&nbsp; <?= e(date('D, d M Y')) ?>
    </p>
    <form method="POST" action="/employee/qr-punch">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token ?? '') ?>">
        <input type="hidden" name="action" value="check_out">
        <button type="submit" class="btn btn-danger btn-lg px-5"
                onclick="return confirm('Check out now?')">
            <i data-lucide="log-out" class="me-2" style="width:18px;height:18px"></i>Check Out
        </button>
    </form>
</div>

<?php else: ?>
<div class="card ems-card text-center py-5">
    <div class="mb-3">
        <span class="badge bg-secondary fs-6 px-4 py-2">Not Checked In</span>
    </div>
    <p class="text-muted mb-4">
        <?= e(date('D, d M Y')) ?> &nbsp;·&nbsp; <?= e(date('h:i A')) ?>
    </p>
    <form method="POST" action="/employee/qr-punch">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token ?? '') ?>">
        <input type="hidden" name="action" value="check_in">
        <button type="submit" class="btn btn-success btn-lg px-5">
            <i data-lucide="log-in" class="me-2" style="width:18px;height:18px"></i>Check In
        </button>
    </form>
</div>
<?php endif; ?>
