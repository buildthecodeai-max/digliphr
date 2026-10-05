<?php
/** @var string $content */
use App\Support\Navigation;

$navScope = 'employee';
$activeWs = Navigation::activeWorkspace($navScope);
$workspaceLabel = $activeWs['label'] ?? 'Employee';
$workspaceKey = $activeWs['key'] ?? '';
$workspaceAccent = $activeWs['accent'] ?? 'purple';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include config('app.paths.views') . '/partials/appearance-boot.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e($csrf ?? csrf_token()) ?>">
    <title><?= e($title ?? 'Employee') ?> — <?= e($appName ?? config('app.name')) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= asset('css/app.css') ?>" rel="stylesheet">
    <link href="<?= asset('css/workspace-nav.css') ?>" rel="stylesheet">
    <link href="<?= asset('css/design-system.css') ?>" rel="stylesheet">
    <link href="<?= asset('css/product-enhancements.css') ?>" rel="stylesheet">
    <link href="<?= asset('css/saas-theme.css') ?>" rel="stylesheet">
    <link href="<?= asset('css/approved-theme.css') ?>" rel="stylesheet">
    <link href="<?= asset('css/instant-ui.css') ?>" rel="stylesheet">
</head>
<body class="app-body has-workspace-nav">
<script>
(function () {
    try {
        var key = 'ems.sidebar.employee.collapsed';
        var legacy = 'ems.sidebar.collapsed';
        if (localStorage.getItem(key) === null && localStorage.getItem(legacy) !== null) {
            localStorage.setItem(key, localStorage.getItem(legacy));
        }
        if (localStorage.getItem(key) === '1' && window.innerWidth >= 992) {
            document.documentElement.classList.add('ems-sidebar-collapsed-pending');
        }
    } catch (e) {}
})();
</script>
<style>
html.ems-sidebar-collapsed-pending .app-shell { --ems-sidebar-width: var(--ems-sidebar-collapsed); }
html.ems-sidebar-collapsed-pending .app-shell .sidebar-brand span:not(.sidebar-brand-mark),
html.ems-sidebar-collapsed-pending .app-shell .sidebar-link span:not(.sidebar-icon),
html.ems-sidebar-collapsed-pending .app-shell .nav-section-label,
html.ems-sidebar-collapsed-pending .app-shell .sidebar-footer-meta,
html.ems-sidebar-collapsed-pending .app-shell .sidebar-footer .btn span,
html.ems-sidebar-collapsed-pending .app-shell .sidebar-badge { display: none !important; }
html.ems-sidebar-collapsed-pending .app-shell .sidebar-link { justify-content: center; padding-inline: .7rem; }
html.ems-sidebar-collapsed-pending .app-shell .sidebar-link .sidebar-icon { display: grid !important; }
</style>
<div class="app-shell" id="appShell" data-sidebar-scope="employee" data-workspace="<?= e($workspaceKey) ?>" data-accent="<?= e($workspaceAccent) ?>">
    <?php include config('app.paths.views') . '/partials/sidebar-employee.php'; ?>
    <div class="app-main">
        <header class="app-topbar workspace-topbar">
            <button type="button" class="btn btn-sm btn-soft d-lg-none" id="sidebarToggle" aria-label="Open menu"><i data-lucide="menu"></i></button>
            <button type="button" class="btn btn-sm btn-soft d-none d-lg-inline-flex" id="sidebarCollapseBtn" aria-label="Collapse sidebar"><i data-lucide="panel-left"></i></button>
            <?php if (($activeWs['key'] ?? '') !== 'dashboard'): ?>
            <button type="button" class="btn btn-sm btn-soft" id="backBtn" aria-label="Go back" onclick="history.back()"><i data-lucide="arrow-left"></i></button>
            <?php endif; ?>
            <div class="topbar-title-wrap">
                <div class="topbar-workspace" data-accent="<?= e($workspaceAccent) ?>">
                    <span class="topbar-workspace-dot" aria-hidden="true"></span>
                    <?= e($workspaceLabel) ?>
                </div>
                <div class="topbar-title"><?= e($title ?? '') ?></div>
            </div>
            <div class="topbar-search position-relative ms-3 d-none d-md-block flex-grow-1">
                <i data-lucide="search" class="search-icon"></i>
                <input type="search" class="form-control form-control-sm" placeholder="Search…" aria-label="Global search" id="globalSearch" autocomplete="off">
            </div>
            <div class="ms-auto d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-soft" id="themeToggleBtn" aria-label="Toggle dark/light mode" title="Toggle theme">
                    <i data-lucide="sun" id="themeIconLight" style="width:16px;height:16px"></i>
                    <i data-lucide="moon" id="themeIconDark" style="width:16px;height:16px;display:none"></i>
                </button>
                <a href="/employee/attendance" class="btn btn-sm btn-primary d-none d-sm-inline-flex">
                    <i data-lucide="camera" class="me-1" style="width:14px;height:14px"></i>Check In/Out
                </a>
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
                        <div class="p-2 border-top text-center"><a href="/employee/notifications" class="small">View all</a></div>
                    </div>
                </div>
                <div class="dropdown">
                    <button class="btn btn-sm btn-soft d-flex align-items-center gap-2" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-label="Account menu">
                        <span class="avatar-sm"><?= e(strtoupper(substr($authUser['name'] ?? 'E', 0, 1))) ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end account-menu">
                        <li><a class="dropdown-item" href="/employee/profile"><i data-lucide="user" class="me-2" style="width:14px;height:14px"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="/change-password"><i data-lucide="settings" class="me-2" style="width:14px;height:14px"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><form method="POST" action="/logout" class="m-0"><?= csrf_field() ?><button type="submit" class="dropdown-item text-danger"><i data-lucide="log-out" class="me-2" style="width:14px;height:14px"></i>Logout</button></form></li>
                    </ul>
                </div>
            </div>
        </header>
        <?php include config('app.paths.views') . '/partials/workspace-breadcrumbs.php'; ?>
        <?php include config('app.paths.views') . '/partials/workspace-tabs.php'; ?>
        <main class="app-content motion-fade-up">
            <?php include config('app.paths.views') . '/partials/alerts.php'; ?>
            <?= $content ?>
        </main>
    </div>
</div>
<?php include config('app.paths.views') . '/partials/quick-actions-fab.php'; ?>
<?php include config('app.paths.views') . '/partials/floating-chat.php'; ?>
<?php include config('app.paths.views') . '/partials/mobile-bottom-nav.php'; ?>
<div class="modal fade" id="mobileSearchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-body">
            <label class="form-label small" for="mobileGlobalSearch">Search</label>
            <input type="search" class="form-control" id="mobileGlobalSearch" placeholder="Go to leave, payslips…" aria-label="Search">
        </div>
        <div class="modal-footer py-2">
            <button type="button" class="btn btn-soft btn-sm" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary btn-sm" id="mobileSearchGo">Go</button>
        </div>
    </div></div>
</div>
<div id="toastContainer" class="toast-container position-fixed bottom-0 end-0 p-3"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="<?= asset('js/charts.js') ?>"></script>
<script src="<?= asset('js/app.js') ?>"></script>
<script src="<?= asset('js/appearance.js') ?>"></script>
<script src="<?= asset('js/workspace-nav.js') ?>"></script>
<script src="<?= asset('js/design-system.js') ?>"></script>
<script>if (window.lucide) lucide.createIcons();</script>
<script>
(function () {
    var btn = document.getElementById('themeToggleBtn');
    var iconLight = document.getElementById('themeIconLight');
    var iconDark  = document.getElementById('themeIconDark');
    function syncIcon() {
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        iconLight.style.display = isDark  ? 'block' : 'none';
        iconDark.style.display  = !isDark ? 'block' : 'none';
        btn.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
    }
    syncIcon();
    btn.addEventListener('click', function () {
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        var next   = isDark ? 'light' : 'dark';
        try { localStorage.setItem('ems-theme-mode', next); } catch (e) {}
        document.documentElement.setAttribute('data-theme', next);
        document.documentElement.setAttribute('data-ems-theme-mode', next);
        syncIcon();
        if (window.lucide) lucide.createIcons();
    });
    new MutationObserver(syncIcon).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
})();
</script>
</body>
</html>
