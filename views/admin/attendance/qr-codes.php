<div class="page-header">
    <div>
        <h1>QR Punch Codes</h1>
        <p class="subtitle">Employees scan these QR codes to check in / out via their phone</p>
    </div>
    <a href="/admin/attendance/devices" class="btn btn-sm btn-soft">
        <i data-lucide="cpu" class="me-1" style="width:14px;height:14px"></i>Biometric Devices
    </a>
</div>

<?php include config('app.paths.views') . '/partials/alerts.php'; ?>

<div class="row g-4">

    <!-- Generate new QR -->
    <div class="col-lg-4">
        <div class="card ems-card h-100">
            <div class="card-header fw-semibold py-2">Generate New QR Code</div>
            <div class="card-body">
                <form method="POST" action="/admin/attendance/qr-codes/generate">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label small" for="qr_name">Label</label>
                        <input type="text" name="name" id="qr_name" class="form-control form-control-sm"
                               value="Main Office" placeholder="e.g. Main Entrance">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small" for="qr_location">Location (optional)</label>
                        <input type="text" name="location" id="qr_location" class="form-control form-control-sm"
                               placeholder="e.g. Ground Floor">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i data-lucide="qr-code" class="me-1" style="width:14px;height:14px"></i>Generate
                    </button>
                </form>
                <hr class="my-3">
                <p class="small text-muted mb-0">
                    <strong>How it works:</strong><br>
                    Print or display the QR on a screen at your entrance.
                    Employees open the camera on their phone, scan it,
                    then confirm check-in or check-out in the employee portal.
                </p>
            </div>
        </div>
    </div>

    <!-- Active QR codes -->
    <div class="col-lg-8">
        <?php if (empty($tokens)): ?>
        <div class="card ems-card">
            <div class="card-body text-center py-5 text-muted">
                No QR codes yet. Generate one on the left.
            </div>
        </div>
        <?php else: ?>
        <div class="row g-3">
            <?php
            $baseUrl = rtrim((string) config('app.url', 'https://hr.diglip.com'), '/');
            foreach ($tokens as $t):
                $url = $baseUrl . '/employee/qr-punch?token=' . urlencode((string) $t['token']);
                $qrSrc = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($url);
            ?>
            <div class="col-sm-6">
                <div class="card ems-card text-center">
                    <div class="card-body">
                        <div class="fw-semibold mb-1"><?= e($t['name']) ?></div>
                        <?php if ($t['location']): ?>
                        <div class="small text-muted mb-2"><?= e($t['location']) ?></div>
                        <?php endif; ?>
                        <img src="<?= $qrSrc ?>" alt="QR Code" class="my-2 rounded border"
                             style="width:180px;height:180px">
                        <div class="mt-2 d-flex gap-2 justify-content-center">
                            <a href="<?= $qrSrc ?>&format=png" download="qr-<?= e($t['name']) ?>.png"
                               class="btn btn-sm btn-soft">
                                <i data-lucide="download" class="me-1" style="width:13px;height:13px"></i>Download
                            </a>
                            <a href="<?= htmlspecialchars($url) ?>" target="_blank" class="btn btn-sm btn-soft">
                                <i data-lucide="external-link" class="me-1" style="width:13px;height:13px"></i>Test
                            </a>
                            <form method="POST" action="/admin/attendance/qr-codes/<?= (int) $t['id'] ?>/delete"
                                  onsubmit="return confirm('Delete this QR code?')">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-danger">
                                    <i data-lucide="trash-2" style="width:13px;height:13px"></i>
                                </button>
                            </form>
                        </div>
                        <div class="mt-2 small text-muted">
                            Created <?= e(format_date($t['created_at'])) ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

</div>
