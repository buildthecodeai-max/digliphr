<?php /** @var array $policy */ /** @var bool $alreadyAcknowledged */ ?>
<div class="page-header">
    <div>
        <h1><?= e($policy['notice_title'] ?: 'Work Monitoring Policy') ?></h1>
        <p class="subtitle">Please read and acknowledge your company's monitoring policy.</p>
    </div>
</div>

<?php include config('app.paths.views') . '/partials/alerts.php'; ?>

<div class="card mb-3">
    <div class="card-body">
        <?php if (!empty($policy['notice_text'])): ?>
            <div class="mb-4" style="white-space:pre-wrap"><?= e($policy['notice_text']) ?></div>
        <?php else: ?>
            <p class="text-muted">Your company has enabled work activity monitoring. This includes tracking of active application usage and periodic screenshots during scheduled work hours.</p>
        <?php endif; ?>

        <?php if ($alreadyAcknowledged): ?>
            <div class="alert alert-success mb-0">
                <i data-lucide="check-circle-2" class="me-2" style="width:16px;height:16px"></i>
                You have already acknowledged this policy (version <?= (int) $policy['version'] ?>).
            </div>
        <?php else: ?>
            <form method="post" action="/employee/monitoring/acknowledge">
                <?= csrf_field() ?>
                <input type="hidden" name="policy_id" value="<?= (int) $policy['id'] ?>">
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="confirmAck" required>
                    <label class="form-check-label" for="confirmAck">
                        I have read and understood the monitoring policy and consent to monitoring during my work hours.
                    </label>
                </div>
                <button class="btn btn-primary">Acknowledge Policy</button>
            </form>
        <?php endif; ?>
    </div>
</div>
