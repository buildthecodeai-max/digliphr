<?php
$employeeName = trim((string) (($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? '')));
?>
<div class="agreement-gate" role="dialog" aria-modal="true" aria-labelledby="agreementTitle" data-agreement-gate>
    <div class="agreement-gate__panel">
        <header class="agreement-gate__header">
            <div>
                <span class="agreement-gate__eyebrow"><i data-lucide="shield-check"></i> Required onboarding step</span>
                <h1 id="agreementTitle">Employment Agreement</h1>
                <p>Read all <?= (int) $pageCount ?> pages, confirm your acceptance, and sign below to enter your workspace.</p>
            </div>
            <span class="badge text-bg-warning">Pending signature</span>
        </header>

        <?php include config('app.paths.views') . '/partials/alerts.php'; ?>

        <div class="agreement-gate__grid">
            <section class="agreement-document" aria-label="Employment agreement document">
                <div class="agreement-document__toolbar">
                    <div><i data-lucide="file-text"></i><strong>Employment Agreement</strong><span>Version <?= e($agreement['template_version']) ?> · <?= (int) $pageCount ?> pages</span></div>
                    <a class="btn btn-sm btn-soft" href="/employee/onboarding/agreement/template" target="_blank" rel="noopener"><i data-lucide="external-link"></i> Open PDF</a>
                </div>
                <iframe src="/employee/onboarding/agreement/template#toolbar=1&navpanes=0&view=FitH" title="Employment Agreement PDF"></iframe>
            </section>

            <aside class="agreement-signing" aria-label="Agreement acceptance and signature">
                <form method="POST" action="/employee/onboarding/agreement/accept" id="agreementAcceptForm" novalidate>
                    <?= csrf_field() ?>
                    <div class="agreement-signing__identity">
                        <span class="avatar-sm"><?= e(strtoupper(substr($employeeName ?: 'E', 0, 1))) ?></span>
                        <div><strong><?= e($employeeName) ?></strong><small><?= e($employee['employee_code'] ?? '') ?></small></div>
                    </div>

                    <label class="form-label" for="signerName">Full legal name</label>
                    <input class="form-control" id="signerName" name="signer_name" value="<?= e($employeeName) ?>" maxlength="191" autocomplete="name" required>

                    <div class="d-flex align-items-center justify-content-between mt-3 mb-2">
                        <label class="form-label mb-0" for="signatureCanvas">Draw your signature</label>
                        <button class="btn btn-sm btn-ghost" type="button" id="clearSignature"><i data-lucide="eraser"></i> Clear</button>
                    </div>
                    <div class="signature-pad" id="signaturePad">
                        <canvas id="signatureCanvas" width="560" height="180" aria-label="Signature drawing area"></canvas>
                        <span class="signature-pad__line" aria-hidden="true"></span>
                        <span class="signature-pad__hint">Sign above the line using your mouse or finger</span>
                    </div>
                    <input type="hidden" name="signature_data" id="signatureData">
                    <p class="signature-error" id="signatureError" role="alert" hidden>Please draw your signature.</p>

                    <label class="agreement-consent mt-3" for="agreementConsent">
                        <input class="form-check-input" type="checkbox" name="consent" value="1" id="agreementConsent" required>
                        <span><?= e($consentText) ?></span>
                    </label>

                    <div class="agreement-legal-note">
                        <i data-lucide="lock-keyhole"></i>
                        <span>Your signed PDF, acceptance time, account, and security audit details will be stored privately in My Documents.</span>
                    </div>
                    <button class="btn btn-primary btn-lg w-100" type="submit" id="acceptAgreementButton">
                        <i data-lucide="pen-line"></i> Agree &amp; Sign Employment Agreement
                    </button>
                </form>
            </aside>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';
    const canvas = document.getElementById('signatureCanvas');
    const pad = document.getElementById('signaturePad');
    const clear = document.getElementById('clearSignature');
    const form = document.getElementById('agreementAcceptForm');
    const data = document.getElementById('signatureData');
    const error = document.getElementById('signatureError');
    const context = canvas.getContext('2d');
    let drawing = false;
    let signed = false;

    context.lineWidth = 3;
    context.lineCap = 'round';
    context.lineJoin = 'round';
    context.strokeStyle = '#0f172a';

    function point(event) {
        const rect = canvas.getBoundingClientRect();
        return {
            x: (event.clientX - rect.left) * (canvas.width / rect.width),
            y: (event.clientY - rect.top) * (canvas.height / rect.height)
        };
    }
    function start(event) {
        event.preventDefault();
        drawing = true;
        const p = point(event);
        context.beginPath();
        context.moveTo(p.x, p.y);
        canvas.setPointerCapture(event.pointerId);
    }
    function move(event) {
        if (!drawing) return;
        event.preventDefault();
        const p = point(event);
        context.lineTo(p.x, p.y);
        context.stroke();
        signed = true;
        error.hidden = true;
        pad.classList.remove('is-invalid');
    }
    function end(event) {
        if (!drawing) return;
        drawing = false;
        if (canvas.hasPointerCapture(event.pointerId)) canvas.releasePointerCapture(event.pointerId);
    }
    function reset() {
        context.clearRect(0, 0, canvas.width, canvas.height);
        signed = false;
        data.value = '';
    }

    canvas.addEventListener('pointerdown', start);
    canvas.addEventListener('pointermove', move);
    canvas.addEventListener('pointerup', end);
    canvas.addEventListener('pointercancel', end);
    clear.addEventListener('click', reset);
    form.addEventListener('submit', function (event) {
        if (!signed) {
            event.preventDefault();
            error.hidden = false;
            pad.classList.add('is-invalid');
            canvas.focus();
            return;
        }
        data.value = canvas.toDataURL('image/png');
        const button = document.getElementById('acceptAgreementButton');
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Creating signed agreement…';
    });
})();
</script>
