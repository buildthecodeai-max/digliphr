<?php
use App\Support\Navigation;

$scope = $navScope ?? Navigation::scope();
$activeWs = Navigation::activeWorkspace($scope);
$children = $activeWs['children'] ?? [];
if (!$activeWs || count($children) < 2) {
    return;
}
$accent = $activeWs['accent'] ?? 'purple';
?>
<nav class="workspace-tabs" data-workspace="<?= e($activeWs['key'] ?? '') ?>" data-accent="<?= e($accent) ?>" aria-label="<?= e(($activeWs['label'] ?? 'Workspace') . ' sections') ?>">
    <div class="workspace-tabs-scroll" role="tablist">
        <?php foreach ($children as $child): ?>
            <?php $active = Navigation::childIsActive($child); ?>
            <a href="<?= e($child['url']) ?>"
               class="workspace-tab<?= $active ? ' is-active' : '' ?>"
               role="tab"
               aria-selected="<?= $active ? 'true' : 'false' ?>"
               <?= $active ? 'aria-current="page"' : '' ?>>
                <?= e($child['label']) ?>
            </a>
        <?php endforeach; ?>
        <span class="workspace-tab-indicator" aria-hidden="true"></span>
    </div>
</nav>
