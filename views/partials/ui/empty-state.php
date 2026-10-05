<?php
/**
 * <EmptyState /> — used when a list/table/panel has no data.
 *
 *   ui('empty-state', [
 *       'icon' => 'inbox', 'title' => 'No leave requests yet',
 *       'text' => 'Requests submitted by your team will show up here.',
 *       'actionLabel' => 'New request', 'actionHref' => '/employee/leave/create',
 *   ]);
 *
 * @var string      $icon
 * @var string      $title
 * @var string|null $text
 * @var string|null $actionLabel
 * @var string|null $actionHref
 */

$icon        = $icon        ?? 'inbox';
$title       = $title       ?? 'Nothing here yet';
$text        = $text        ?? null;
$actionLabel = $actionLabel ?? null;
$actionHref  = $actionHref  ?? null;
?>
<div class="ds-empty">
    <div class="ds-empty-art">
        <i data-lucide="<?= e($icon) ?>" width="26" height="26"></i>
    </div>
    <div class="ds-empty-title"><?= e($title) ?></div>
    <?php if ($text): ?>
        <div class="ds-empty-text"><?= e($text) ?></div>
    <?php endif; ?>
    <?php if ($actionLabel && $actionHref): ?>
        <?php ui('button', ['label' => $actionLabel, 'href' => $actionHref, 'variant' => 'primary', 'size' => 'sm', 'icon' => 'plus']); ?>
    <?php endif; ?>
</div>
