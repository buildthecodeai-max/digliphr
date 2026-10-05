<?php
/** @var string $content */
use App\Support\Navigation;

$isAdminNav = auth()->isAdmin();
$navScope = $isAdminNav ? 'admin' : 'employee';
$activeWs = class_exists(Navigation::class) ? Navigation::activeWorkspace($navScope) : null;
$workspaceLabel = $activeWs['label'] ?? 'Team Chat';
$workspaceKey = $activeWs['key'] ?? 'chat';
$workspaceAccent = $activeWs['accent'] ?? 'pink';
$viewsPath = rtrim((string) config('app.paths.views'), '/');
?>
<!DOCTYPE html>
<html lang="en" data-ems-shell="chat">
<head>
    <?php
    $_emsPartial = $viewsPath . '/partials/appearance-boot.php';
    if (is_file($_emsPartial)) {
        include $_emsPartial;
    }
    unset($_emsPartial);
    ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="<?= e($csrf ?? csrf_token()) ?>">
    <meta name="color-scheme" content="light dark">
    <title><?= e($title ?? 'Team Chat') ?> — <?= e($appName ?? config('app.name')) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= asset('css/app.css') ?>" rel="stylesheet">
    <link href="<?= asset('css/workspace-nav.css') ?>" rel="stylesheet">
    <?php
    $chatPublicAssets = dirname(__DIR__, 2) . '/public/assets';
    $chatCssVer = is_file($chatPublicAssets . '/css/chat.css') ? (string) filemtime($chatPublicAssets . '/css/chat.css') : (string) time();
    $chatJsVer = is_file($chatPublicAssets . '/js/chat.js') ? (string) filemtime($chatPublicAssets . '/js/chat.js') : (string) time();
    ?>
    <link href="<?= asset('css/chat.css') ?>?v=<?= e($chatCssVer) ?>" rel="stylesheet">
    <link href="<?= asset('css/design-system.css') ?>" rel="stylesheet">
    <link href="<?= asset('css/saas-theme.css') ?>" rel="stylesheet">
    <link href="<?= asset('css/approved-theme.css') ?>" rel="stylesheet">
    <link href="<?= asset('css/instant-ui.css') ?>" rel="stylesheet">
</head>
<body class="app-body chat-body has-workspace-nav">
<div class="app-shell" id="appShell" data-sidebar-scope="<?= $isAdminNav ? 'admin' : 'employee' ?>" data-workspace="<?= e($workspaceKey) ?>" data-accent="<?= e($workspaceAccent) ?>">
    <?php
    $_emsPartial = $viewsPath . '/partials/sidebar-' . ($isAdminNav ? 'admin' : 'employee') . '.php';
    if (is_file($_emsPartial)) {
        include $_emsPartial;
    }
    unset($_emsPartial);
    ?>
    <div class="app-main chat-app-main">
        <header class="app-topbar chat-topbar workspace-topbar">
            <button type="button" class="btn btn-sm btn-soft d-lg-none" id="sidebarToggle" aria-label="Open menu">
                <i data-lucide="menu"></i>
            </button>
            <button type="button" class="btn btn-sm btn-soft d-none d-lg-inline-flex" id="sidebarCollapseBtn" aria-label="Collapse sidebar">
                <i data-lucide="panel-left"></i>
            </button>
            <div class="topbar-title-wrap">
                <div class="topbar-workspace" data-accent="<?= e($workspaceAccent) ?>">
                    <span class="topbar-workspace-dot" aria-hidden="true"></span>
                    <?= e($workspaceLabel) ?>
                </div>
                <div class="topbar-title"><?= e($title ?? 'Team Chat') ?></div>
            </div>
            <div class="topbar-actions ms-auto d-flex align-items-center gap-2">
                <a href="<?= $isAdminNav ? '/admin/dashboard' : '/employee/dashboard' ?>" class="btn btn-sm btn-soft">Dashboard</a>
                <div class="dropdown">
                    <button class="btn btn-sm btn-soft position-relative" data-bs-toggle="dropdown" id="notifBtn" aria-label="Notifications">
                        <i data-lucide="bell"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none" id="notifCount">0</span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-0" style="min-width:320px" id="notifMenu">
                        <div class="p-2 border-bottom fw-semibold small">Notifications</div>
                        <div id="notifList" class="list-group list-group-flush small" style="max-height:280px;overflow:auto">
                            <div class="p-3 text-muted">Loading…</div>
                        </div>
                        <div class="p-2 border-top text-center">
                            <a href="<?= $isAdminNav ? '/admin/notifications' : '/employee/notifications' ?>" class="small">View all</a>
                        </div>
                    </div>
                </div>
                <div class="dropdown">
                    <button class="btn btn-sm btn-soft d-flex align-items-center gap-2" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-label="Account menu">
                        <span class="avatar-sm"><?= e(strtoupper(substr($authUser['name'] ?? 'U', 0, 1))) ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end account-menu">
                        <?php if ($isAdminNav): ?>
                            <li><a class="dropdown-item" href="/change-password"><i data-lucide="settings" class="me-2" style="width:14px;height:14px"></i>Settings</a></li>
                        <?php else: ?>
                            <li><a class="dropdown-item" href="/employee/profile"><i data-lucide="user" class="me-2" style="width:14px;height:14px"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="/change-password"><i data-lucide="settings" class="me-2" style="width:14px;height:14px"></i>Settings</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <?php
                        $_emsPartial = $viewsPath . '/partials/appearance-menu.php';
                        if (is_file($_emsPartial)) {
                            include $_emsPartial;
                        }
                        unset($_emsPartial);
                        ?>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="/logout" class="m-0"><?= csrf_field() ?>
                                <button type="submit" class="dropdown-item text-danger"><i data-lucide="log-out" class="me-2" style="width:14px;height:14px"></i>Logout</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </header>
        <?php
        $_emsPartial = $viewsPath . '/partials/workspace-breadcrumbs.php';
        if (is_file($_emsPartial)) {
            include $_emsPartial;
        }
        $_emsPartial = $viewsPath . '/partials/workspace-tabs.php';
        if (is_file($_emsPartial)) {
            include $_emsPartial;
        }
        unset($_emsPartial);
        ?>
        <main class="app-content chat-content p-0">
            <?php
            $_emsPartial = $viewsPath . '/partials/alerts.php';
            if (is_file($_emsPartial)) {
                include $_emsPartial;
            }
            unset($_emsPartial);
            ?>
            <?= $content ?>
        </main>
    </div>
</div>
<?php
$_emsPartial = $viewsPath . '/partials/mobile-bottom-nav.php';
if (is_file($_emsPartial)) {
    include $_emsPartial;
}
unset($_emsPartial);
?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js"></script>
<script src="<?= asset('js/app.js') ?>"></script>
<script src="<?= asset('js/appearance.js') ?>"></script>
<script src="<?= asset('js/workspace-nav.js') ?>"></script>
<script src="<?= asset('js/design-system.js') ?>"></script>
<script>if (window.lucide) lucide.createIcons();</script>
<?php if (!empty($chatBoot)): ?>
<script src="<?= asset('js/chat.js') ?>?v=<?= e($chatJsVer) ?>"></script>
<?php endif; ?>
</body>
</html>
