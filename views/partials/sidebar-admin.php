<?php
use App\Support\Navigation;

$scope = 'admin';
$workspaces = Navigation::workspaces($scope);
$activeWs = Navigation::activeWorkspace($scope);
$userName = $authUser['name'] ?? 'User';
$userRole = $authUser['primary_role'] ?? ($authUser['roles'][0] ?? 'user');
$activeKey = $activeWs['key'] ?? '';
$navGroups = [
    'Overview' => ['dashboard'],
    'Workforce' => ['people', 'attendance', 'leave', 'approvals'],
    'Work' => ['monitoring'],
    'Communication' => ['chat'],
    'HR & Finance' => ['payroll', 'documents'],
    'Analytics' => ['reports'],
    'Administration' => ['administration'],
];
?>
<aside class="app-sidebar workspace-sidebar" id="appSidebar" aria-label="Main navigation"
       data-workspace="<?= e($activeKey) ?>">
    <div class="sidebar-brand">
        <span class="sidebar-brand-mark" aria-hidden="true">
            <img src="<?= asset('images/diglip-logo.png') ?>" alt="Diglip" width="46" height="46" style="object-fit:contain">
        </span>
        <span class="sidebar-brand-copy">
            <strong><?= e($appName ?? 'Employee Management System') ?></strong>
        </span>
    </div>
    <nav class="sidebar-nav" id="sidebarNav">
        <?php foreach ($navGroups as $groupLabel => $groupKeys): ?>
            <?php $groupItems = array_values(array_filter($workspaces, static fn(array $item): bool => in_array($item['key'] ?? '', $groupKeys, true))); ?>
            <?php if (!$groupItems) continue; ?>
        <div class="nav-section">
            <div class="nav-section-label"><?= e($groupLabel) ?></div>
            <?php foreach ($groupItems as $item): ?>
                <?php
                $isActive = ($activeKey !== '' && $activeKey === ($item['key'] ?? ''));
                $accent = $item['accent'] ?? 'purple';
                $badge = $item['badge'] ?? null;
                ?>
                <a href="<?= e($item['url']) ?>"
                   class="sidebar-link<?= $isActive ? ' active' : '' ?>"
                   data-tone="<?= e($accent) ?>"
                   data-workspace-key="<?= e($item['key'] ?? '') ?>"
                   aria-current="<?= $isActive ? 'page' : 'false' ?>">
                    <span class="sidebar-icon"><i data-lucide="<?= e($item['icon']) ?>"></i></span>
                    <span class="sidebar-label"><?= e($item['label']) ?></span>
                    <?php if ($badge === 'chat_unread'): ?>
                        <span class="sidebar-badge d-none" data-chat-unread-badge aria-hidden="true">0</span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </nav>
    <script>
    (function () {
        // Fast-path scroll restore: runs the instant #sidebarNav exists (well
        // before app.js loads at the end of body), so a scrolled sidebar never
        // visibly snaps from top -> saved position on a full page navigation.
        try {
            var nav = document.getElementById('sidebarNav');
            if (!nav) return;
            nav.style.scrollBehavior = 'auto';
            var saved = parseInt(localStorage.getItem('ems.sidebar.admin.scrollTop') || '0', 10);
            if (!isNaN(saved) && saved > 0) nav.scrollTop = saved;
        } catch (e) {}
    })();
    </script>
    <div class="sidebar-footer">
        <div class="sidebar-footer-user">
            <span class="avatar-sm"><?= e(strtoupper(substr($userName, 0, 1))) ?></span>
            <div class="sidebar-footer-meta">
                <div class="name"><?= e($userName) ?></div>
                <div class="role"><?= e(ucwords(str_replace('_', ' ', (string) $userRole))) ?></div>
            </div>
        </div>
        <form method="POST" action="/logout" class="mt-1 px-1"><?= csrf_field() ?>
            <button type="submit" class="btn btn-soft btn-sm w-100">
                <i data-lucide="log-out" style="width:14px;height:14px"></i>
                <span class="ms-1">Sign out</span>
            </button>
        </form>
    </div>
</aside>
