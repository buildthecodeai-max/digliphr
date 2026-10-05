<script>
(function () {
    try {
        var mode = localStorage.getItem('ems-theme-mode') || 'light';
        var accent = localStorage.getItem('ems-accent') || 'indigo';
        var aliases = { cyan: 'navy', blue: 'indigo', green: 'forest' };
        var valid = { teal: 1, navy: 1, forest: 1, indigo: 1 };
        accent = aliases[accent] || accent;
        if (!valid[accent]) accent = 'indigo';
        var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        var theme = mode === 'dark' || (mode === 'system' && prefersDark) ? 'dark' : 'light';
        var root = document.documentElement;
        root.setAttribute('data-theme', theme);
        root.setAttribute('data-ems-theme-mode', mode);
        root.setAttribute('data-ems-accent', accent);
        try { localStorage.setItem('ems-accent', accent); } catch (e2) {}
    } catch (e) {}
})();
</script>
<style id="ems-appearance-fouc">
html[data-theme="dark"] { color-scheme: dark; background: #0B1016; }
html[data-theme="dark"] body,
html[data-theme="dark"] .app-body { background: #0B1016; color: #D0D7E2; }
html[data-theme="dark"] .workspace-tabs,
html[data-theme="dark"] .mobile-bottom-nav,
html[data-theme="dark"] .app-topbar {
    background: #1A222D !important;
    background-color: #1A222D !important;
    border-color: #2A3441;
    color: #D0D7E2;
}
html[data-theme="dark"] .workspace-tab { color: #9AA6B8; }
html[data-theme="dark"] .workspace-tab.is-active { color: #A5B4FC; background: rgba(148, 163, 184, .12); }
html[data-theme="dark"] .workspace-breadcrumb,
html[data-theme="dark"] .workspace-breadcrumb-list { color: #8B97A8; }
html[data-theme="dark"] .quick-fab-menu {
    background: #1E2733 !important;
    background-color: #1E2733 !important;
    border-color: #2A3441 !important;
    color: #E2E8F0 !important;
}
html[data-theme="dark"] .quick-fab-menu-label { color: #9AA6B8 !important; }
html[data-theme="dark"] .quick-fab-item { color: #E2E8F0 !important; }
html[data-theme="dark"] .chat-shell,
html[data-theme="dark"] .chat-rail,
html[data-theme="dark"] .chat-main,
html[data-theme="dark"] .chat-thread,
html[data-theme="dark"] .chat-composer,
html[data-theme="dark"] .chat-main-head {
    background: #121820 !important;
    color: #D0D7E2 !important;
    border-color: #2A3441 !important;
}
html[data-theme="dark"] .chat-rail { background: #1A222D !important; }
html[data-theme="dark"] .chat-composer,
html[data-theme="dark"] .chat-main-head { background: #1A222D !important; }
</style>
