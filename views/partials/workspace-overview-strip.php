<?php
/** @var array $metrics list of ['label'=>,'value'=>,'sub'=>,'href'=>,'tone'=>,'icon'=>] */
$metrics = $metrics ?? [];
if (!$metrics) {
    return;
}
?>
<div class="workspace-overview-strip row g-2 g-md-3 mb-3">
    <?php foreach ($metrics as $m): ?>
        <div class="col-6 col-lg-3">
            <?php if (!empty($m['href'])): ?>
                <a class="metric-card tone-<?= e($m['tone'] ?? 'purple') ?> clickable text-decoration-none d-block" href="<?= e($m['href']) ?>">
            <?php else: ?>
                <div class="metric-card tone-<?= e($m['tone'] ?? 'purple') ?>">
            <?php endif; ?>
                <div class="metric-icon"><i data-lucide="<?= e($m['icon'] ?? 'activity') ?>"></i></div>
                <div class="metric-label"><?= e($m['label'] ?? '') ?></div>
                <div class="metric-value"><?= e((string) ($m['value'] ?? '0')) ?></div>
                <?php if (!empty($m['sub'])): ?>
                    <div class="metric-delta flat"><?= e($m['sub']) ?></div>
                <?php endif; ?>
            <?php if (!empty($m['href'])): ?>
                </a>
            <?php else: ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
