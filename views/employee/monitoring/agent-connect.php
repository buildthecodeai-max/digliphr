<?php
$hasDevice = $deviceUid !== '';
$hasToken  = $token !== null;
$expiresSecs = $hasToken ? max(0, strtotime($expires) - time()) : 0;
?>
<div class="container-fluid px-3 px-md-4 py-4" style="max-width:600px">
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <div class="d-flex align-items-center gap-3 mb-4">
                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width:48px;height:48px;background:var(--ems-primary-soft,#eff6ff)">
                    <i data-lucide="monitor-smartphone" style="width:22px;height:22px;color:var(--ems-primary,#3b82f6)"></i>
                </div>
                <div>
                    <h5 class="mb-0 fw-semibold">Connect Desktop Agent</h5>
                    <div class="text-muted small">Link your computer to the monitoring system</div>
                </div>
            </div>

            <?php if (!$hasDevice): ?>
            <div class="alert alert-warning d-flex gap-2 align-items-start">
                <i data-lucide="triangle-alert" style="width:16px;height:16px;flex-shrink:0;margin-top:2px"></i>
                <div>
                    <strong>No device detected.</strong><br>
                    <span class="small">Open the desktop agent and click <em>"Connect via Web"</em> — it will open this page with your device ID pre-filled.</span>
                </div>
            </div>

            <?php elseif ($hasToken): ?>
            <div class="alert alert-success d-flex gap-2 align-items-start mb-3">
                <i data-lucide="check-circle" style="width:16px;height:16px;flex-shrink:0;margin-top:2px"></i>
                <div>Your agent connection code has been generated. It expires in <strong id="countdown"><?= $expiresSecs ?> seconds</strong>.</div>
            </div>

            <div class="text-center py-3">
                <div class="text-muted small mb-2">Connection code</div>
                <div class="d-inline-block rounded-3 px-4 py-3 fw-bold font-monospace"
                     style="font-size:1.6rem;letter-spacing:.25em;background:var(--ems-surface,#f8fafc);border:2px solid var(--ems-border,#e2e8f0)">
                    <?= e(strtoupper(substr($token, 0, 4) . '-' . substr($token, 4, 4))) ?>
                </div>
                <div class="text-muted small mt-2">Enter this in the desktop agent — it will connect automatically.</div>
            </div>

            <div class="text-center mt-3">
                <a href="<?= e('/employee/monitoring/agent-connect?device_uid=' . urlencode($deviceUid)) ?>" class="btn btn-sm btn-outline-secondary">
                    <i data-lucide="refresh-cw" style="width:14px;height:14px"></i> Generate new code
                </a>
            </div>

            <script>
            (function() {
                var secs = <?= (int) $expiresSecs ?>;
                var el = document.getElementById('countdown');
                if (!el || secs <= 0) return;
                var t = setInterval(function() {
                    secs--;
                    if (secs <= 0) {
                        clearInterval(t);
                        el.textContent = 'expired';
                        el.closest('.alert').className = el.closest('.alert').className.replace('alert-success','alert-danger');
                    } else {
                        el.textContent = secs + ' seconds';
                    }
                }, 1000);
            })();
            </script>

            <?php else: ?>
            <div class="alert alert-danger d-flex gap-2 align-items-start">
                <i data-lucide="x-circle" style="width:16px;height:16px;flex-shrink:0;margin-top:2px"></i>
                <div>Could not generate a connection token. The monitoring migration may not have run yet. Contact your administrator.</div>
            </div>
            <?php endif; ?>

            <hr class="my-4">
            <div class="text-muted small">
                <strong>How it works:</strong>
                <ol class="mb-0 mt-1 ps-3">
                    <li>Open the Diglip Work Monitor app on your computer.</li>
                    <li>Click <strong>"Connect via Web Session"</strong> in the setup window.</li>
                    <li>This page opens automatically with a connection code.</li>
                    <li>The agent reads the code and connects — no password needed.</li>
                </ol>
            </div>
        </div>
    </div>
</div>
