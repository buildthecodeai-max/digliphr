<?php
use App\Support\Navigation;

$scope = $navScope ?? Navigation::scope();
$actions = Navigation::quickActions($scope);
if (!$actions) {
    return;
}
?>
<div class="quick-fab" id="quickFab">
    <button type="button"
            class="quick-fab-toggle"
            id="quickFabToggle"
            aria-expanded="false"
            aria-controls="quickFabMenu"
            aria-label="Quick actions">
        <i data-lucide="plus" aria-hidden="true"></i>
    </button>
    <div class="quick-fab-menu" id="quickFabMenu" hidden>
        <div class="quick-fab-menu-label">Quick create</div>
        <?php foreach ($actions as $action): ?>
            <a href="<?= e($action['url']) ?>" class="quick-fab-item">
                <span class="quick-fab-icon"><i data-lucide="<?= e($action['icon'] ?? 'plus') ?>"></i></span>
                <span><?= e($action['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</div>
