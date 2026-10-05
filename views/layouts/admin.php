<?php
/** @var string $content */
use App\Support\Navigation;

$navScope = 'admin';
$activeWs = Navigation::activeWorkspace($navScope);
$workspaceLabel = $activeWs['label'] ?? 'Admin';
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
    <title><?= e($title ?? 'Admin') ?> — <?= e($appName ?? config('app.name')) ?></title>
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
    <link href="<?= asset('css/reports.css') ?>" rel="stylesheet">
    <link href="<?= asset('css/instant-ui.css') ?>" rel="stylesheet">
    <link href="<?= asset('css/wow.css') ?>" rel="stylesheet">
    <script>
    (function () {
        try {
            var key = 'ems.sidebar.admin.collapsed';
            if (localStorage.getItem(key) === '1' && window.innerWidth >= 992) {
                document.documentElement.classList.add('ems-sidebar-collapsed-pending');
            }
        } catch (e) {}
    })();
    </script>
</head>
<body class="app-body has-workspace-nav">
<div class="app-shell" id="appShell" data-sidebar-scope="admin" data-workspace="<?= e($workspaceKey) ?>" data-accent="<?= e($workspaceAccent) ?>">
    <?php include config('app.paths.views') . '/partials/sidebar-admin.php'; ?>
    <div class="app-main">
        <header class="app-topbar workspace-topbar">
            <button type="button" class="btn btn-sm btn-soft d-lg-none" id="sidebarToggle" aria-label="Open menu">
                <i data-lucide="menu"></i>
            </button>
            <button type="button" class="btn btn-sm btn-soft d-none d-lg-inline-flex" id="sidebarCollapseBtn" aria-label="Collapse sidebar">
                <i data-lucide="panel-left"></i>
            </button>
            <?php if (($activeWs['key'] ?? '') !== 'dashboard'): ?>
            <button type="button" class="btn btn-sm btn-soft" id="backBtn" aria-label="Go back" onclick="history.back()">
                <i data-lucide="arrow-left"></i>
            </button>
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
                <input type="search" class="form-control form-control-sm" placeholder="Search employees, documents…" aria-label="Global search" id="globalSearch" autocomplete="off">
                <div class="global-search-hints small text-muted d-none" id="globalSearchHints">Search employees by name or code</div>
                <div class="global-search-results d-none" id="globalSearchResults" role="listbox" aria-label="Employee search results"></div>
            </div>
            <div class="topbar-actions ms-auto d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-soft" id="themeToggleBtn" aria-label="Toggle dark/light mode" title="Toggle theme">
                    <i data-lucide="sun" id="themeIconLight" style="width:16px;height:16px"></i>
                    <i data-lucide="moon" id="themeIconDark" style="width:16px;height:16px;display:none"></i>
                </button>
                <div class="dropdown d-none d-md-block" id="quickCreateDropdown">
                    <button class="btn btn-sm btn-primary" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Quick create">
                        <i data-lucide="plus" class="me-1" style="width:14px;height:14px"></i>Create
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <?php foreach (Navigation::quickActions($navScope) as $qa): ?>
                            <li>
                                <a class="dropdown-item d-flex align-items-center gap-2" href="<?= e($qa['url']) ?>">
                                    <i data-lucide="<?= e($qa['icon'] ?? 'plus') ?>" style="width:14px;height:14px"></i>
                                    <?= e($qa['label']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="dropdown">
                    <button class="btn btn-sm btn-soft position-relative" data-bs-toggle="dropdown" id="notifBtn" aria-label="Notifications">
                        <i data-lucide="bell"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none" id="notifCount">0</span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-0" style="min-width:320px" id="notifMenu">
                        <div class="p-2 border-bottom fw-semibold small d-flex justify-content-between align-items-center">
                            <span>Notifications</span>
                            <button type="button" class="btn btn-sm p-0 text-muted" id="notifMarkAllRead" style="font-size:.7rem;line-height:1.2">Mark all read</button>
                        </div>
                        <div id="notifList" class="list-group list-group-flush small" style="max-height:280px;overflow:auto">
                            <div class="p-3 text-muted">Loading…</div>
                        </div>
                        <div class="p-2 border-top text-center"><a href="/admin/notifications" class="small">View all</a></div>
                    </div>
                </div>
                <div class="dropdown">
                    <button class="btn btn-sm btn-soft d-flex align-items-center gap-2" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-label="Account menu">
                        <span class="avatar-sm"><?= e(strtoupper(substr($authUser['name'] ?? 'A', 0, 1))) ?></span>
                        <span class="small d-none d-md-inline"><?= e($authUser['name'] ?? 'Admin') ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end account-menu">
                        <li><a class="dropdown-item" href="/change-password"><i data-lucide="settings" class="me-2" style="width:14px;height:14px"></i>Settings</a></li>
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
<div id="toastContainer" class="toast-container position-fixed bottom-0 end-0 p-3"></div>
<div class="drawer-backdrop" id="drawerBackdrop" hidden></div>
<aside class="detail-drawer" id="detailDrawer" aria-hidden="true" role="dialog" aria-labelledby="detailDrawerTitle">
    <div class="detail-drawer-header">
        <strong id="detailDrawerTitle">Details</strong>
        <button type="button" class="btn btn-sm btn-soft" id="detailDrawerClose" aria-label="Close details"><i data-lucide="x"></i></button>
    </div>
    <div class="detail-drawer-body" id="detailDrawerBody"></div>
</aside>
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered"><div class="modal-content">
        <div class="modal-body" id="confirmModalBody">Are you sure?</div>
        <div class="modal-footer py-2">
            <button type="button" class="btn btn-soft btn-sm" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary btn-sm" id="confirmModalOk">Confirm</button>
        </div>
    </div></div>
</div>
<div class="modal fade" id="mobileSearchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-body">
            <label class="form-label small" for="mobileGlobalSearch">Search</label>
            <input type="search" class="form-control" id="mobileGlobalSearch" placeholder="Search employees…" aria-label="Search">
        </div>
        <div class="modal-footer py-2">
            <button type="button" class="btn btn-soft btn-sm" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary btn-sm" id="mobileSearchGo">Search</button>
        </div>
    </div></div>
</div>
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
