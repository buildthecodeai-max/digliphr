<?php
use App\Support\Navigation;

$scope = $navScope ?? Navigation::scope();
$items = Navigation::mobileNav($scope);
if (!$items) {
    return;
}
?>
<nav class="mobile-bottom-nav d-lg-none" id="mobileBottomNav" aria-label="Primary mobile navigation">
    <?php foreach ($items as $item): ?>
        <?php
        $action = $item['action'] ?? null;
        $active = !$action && Navigation::mobileItemActive($item);
        $badge = $item['badge'] ?? null;
        ?>
        <?php if ($action === 'search'): ?>
            <button type="button"
                    class="mobile-bottom-nav-item"
                    data-mobile-search
                    aria-label="Open search">
                <i data-lucide="<?= e($item['icon']) ?>" aria-hidden="true"></i>
                <span><?= e($item['label']) ?></span>
            </button>
        <?php else: ?>
            <a href="<?= e($item['url']) ?>"
               class="mobile-bottom-nav-item<?= $active ? ' is-active' : '' ?>"
               aria-current="<?= $active ? 'page' : 'false' ?>">
                <span class="mobile-bottom-nav-icon-wrap">
                    <i data-lucide="<?= e($item['icon']) ?>" aria-hidden="true"></i>
                    <?php if ($badge === 'chat_unread'): ?>
                        <span class="mobile-nav-badge d-none" data-chat-unread-badge aria-hidden="true">0</span>
                    <?php endif; ?>
                </span>
                <span><?= e($item['label']) ?></span>
            </a>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>
