<?php
use App\Support\Navigation;

$scope = $navScope ?? Navigation::scope();
$crumbs = Navigation::breadcrumbs($scope);
if (!$crumbs) {
    return;
}
?>
<nav class="workspace-breadcrumb" aria-label="Breadcrumb">
    <ol class="workspace-breadcrumb-list">
        <?php foreach ($crumbs as $i => $crumb): ?>
            <li class="workspace-breadcrumb-item">
                <?php if ($i < count($crumbs) - 1): ?>
                    <a href="<?= e($crumb['url']) ?>"><?= e($crumb['label']) ?></a>
                    <span class="workspace-breadcrumb-sep" aria-hidden="true">/</span>
                <?php else: ?>
                    <span aria-current="page"><?= e($crumb['label']) ?></span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>
