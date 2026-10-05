<?php
/**
 * <Tab /> — tab bar with the shared sliding indicator.
 *
 *   ui('tabs', ['items' => [
 *       ['label' => 'Overview', 'href' => '/admin/reports', 'icon' => 'layout-dashboard', 'active' => true],
 *       ['label' => 'Attendance', 'href' => '/admin/reports/attendance'],
 *   ]]);
 *
 * @var array  $items  [['label','href','icon','active','badge'], …]
 * @var string $ariaLabel
 */

$items = $items ?? [];
$ariaLabel = $ariaLabel ?? 'Sections';
?>
<nav class="report-tabs nav" role="tablist" aria-label="<?= e($ariaLabel) ?>">
    <?php foreach ($items as $item): ?>
        <?php $isActive = !empty($item['active']); ?>
        <a class="nav-link<?= $isActive ? ' active' : '' ?>"
           href="<?= e($item['href'] ?? '#') ?>"
           role="tab"
           aria-selected="<?= $isActive ? 'true' : 'false' ?>"
           <?= $isActive ? 'aria-current="page"' : '' ?>>
            <?php if (!empty($item['icon'])): ?>
                <i data-lucide="<?= e($item['icon']) ?>"></i>
            <?php endif; ?>
            <span><?= e($item['label'] ?? '') ?></span>
            <?php if (isset($item['badge']) && $item['badge'] !== '' && $item['badge'] !== null): ?>
                <span class="badge bg-danger"><?= e($item['badge']) ?></span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
    <span class="ds-tab-indicator" aria-hidden="true"></span>
</nav>
