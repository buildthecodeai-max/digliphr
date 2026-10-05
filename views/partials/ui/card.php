<?php
/**
 * <Card /> — surface container with consistent radius, padding and hover lift.
 *
 *   ui('card', ['title' => 'Recent activity', 'body' => $html, 'interactive' => true]);
 *
 * @var string|null $title
 * @var string|null $subtitle
 * @var string|null $icon        Lucide icon shown beside the title.
 * @var string      $body        Pre-escaped HTML for the card body.
 * @var string|null $footer      Pre-escaped HTML for the card footer.
 * @var string|null $actions     Pre-escaped HTML rendered on the header's right.
 * @var bool        $interactive Adds the hover-lift affordance.
 * @var bool        $flush       Removes body padding.
 */

$title       = $title    ?? null;
$subtitle    = $subtitle ?? null;
$icon        = $icon     ?? null;
$body        = $body     ?? '';
$footer      = $footer   ?? null;
$actions     = $actions  ?? null;
$interactive = !empty($interactive);
$flush       = !empty($flush);

$classes = ['card'];
if ($interactive) {
    $classes[] = 'is-interactive';
}
if (!empty($class)) {
    $classes[] = $class;
}
?>
<div class="<?= e(implode(' ', $classes)) ?>">
    <?php if ($title !== null || $actions !== null): ?>
        <div class="card-header d-flex align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-2 min-w-0">
                <?php if ($icon): ?>
                    <i data-lucide="<?= e($icon) ?>" class="text-secondary"></i>
                <?php endif; ?>
                <div class="min-w-0">
                    <?php if ($title !== null): ?>
                        <strong class="d-block text-truncate"><?= e($title) ?></strong>
                    <?php endif; ?>
                    <?php if ($subtitle !== null): ?>
                        <span class="small text-muted"><?= e($subtitle) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($actions !== null): ?>
                <div class="d-flex align-items-center gap-2 flex-none"><?= $actions ?></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="<?= $flush ? '' : 'card-body' ?>"><?= $body ?></div>

    <?php if ($footer !== null): ?>
        <div class="card-footer"><?= $footer ?></div>
    <?php endif; ?>
</div>
