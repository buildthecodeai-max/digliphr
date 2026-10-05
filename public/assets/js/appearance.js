(function () {
    'use strict';

    var MODE_KEY = 'ems-theme-mode';
    var ACCENT_KEY = 'ems-accent';
    var DEFAULT_MODE = 'light';
    var DEFAULT_ACCENT = 'navy';
    var VALID_ACCENTS = { teal: 1, navy: 1, forest: 1, indigo: 1 };
    var ACCENT_ALIASES = { cyan: 'navy', blue: 'indigo', green: 'forest' };

    function safeGet(key, fallback) {
        try {
            return localStorage.getItem(key) || fallback;
        } catch (e) {
            return fallback;
        }
    }

    function safeSet(key, value) {
        try {
            localStorage.setItem(key, value);
        } catch (e) {}
    }

    function normalizeAccent(accent) {
        accent = ACCENT_ALIASES[accent] || accent;
        if (!VALID_ACCENTS[accent]) return DEFAULT_ACCENT;
        return accent;
    }

    function resolveTheme(mode) {
        if (mode === 'dark') return 'dark';
        if (mode === 'light') return 'light';
        var mq = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
        return mq && mq.matches ? 'dark' : 'light';
    }

    function applyAppearance(mode, accent) {
        mode = mode || safeGet(MODE_KEY, DEFAULT_MODE);
        accent = normalizeAccent(accent || safeGet(ACCENT_KEY, DEFAULT_ACCENT));
        if (safeGet(ACCENT_KEY, '') !== accent) {
            safeSet(ACCENT_KEY, accent);
        }
        var theme = resolveTheme(mode);
        var root = document.documentElement;
        root.setAttribute('data-theme', theme);
        root.setAttribute('data-ems-theme-mode', mode);
        root.setAttribute('data-ems-accent', accent);
        syncControls(mode, accent);
    }

    function syncControls(mode, accent) {
        document.querySelectorAll('[data-ems-mode]').forEach(function (btn) {
            var on = btn.getAttribute('data-ems-mode') === mode;
            btn.setAttribute('aria-checked', on ? 'true' : 'false');
            btn.classList.toggle('is-active', on);
        });
        document.querySelectorAll('[data-ems-accent]').forEach(function (btn) {
            var on = btn.getAttribute('data-ems-accent') === accent;
            btn.setAttribute('aria-checked', on ? 'true' : 'false');
            btn.classList.toggle('is-active', on);
        });
    }

    function setMode(mode) {
        safeSet(MODE_KEY, mode);
        applyAppearance(mode, safeGet(ACCENT_KEY, DEFAULT_ACCENT));
    }

    function setAccent(accent) {
        accent = normalizeAccent(accent);
        safeSet(ACCENT_KEY, accent);
        applyAppearance(safeGet(MODE_KEY, DEFAULT_MODE), accent);
    }

    function setPanelOpen(root, open) {
        var toggle = root.querySelector('[data-ems-appearance-toggle]');
        var panel = root.querySelector('[data-ems-appearance-panel]');
        if (!toggle || !panel) return;
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        root.classList.toggle('is-open', open);
        if (open) {
            panel.hidden = false;
        } else {
            panel.hidden = true;
        }
    }

    function bindAppearance(root) {
        var toggle = root.querySelector('[data-ems-appearance-toggle]');
        var panel = root.querySelector('[data-ems-appearance-panel]');
        if (!toggle || !panel) return;

        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            setPanelOpen(root, toggle.getAttribute('aria-expanded') !== 'true');
            if (window.lucide) lucide.createIcons();
        });

        panel.addEventListener('click', function (e) {
            e.stopPropagation();
        });

        root.querySelectorAll('[data-ems-mode]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                setMode(btn.getAttribute('data-ems-mode'));
            });
            btn.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    setMode(btn.getAttribute('data-ems-mode'));
                }
            });
        });

        root.querySelectorAll('[data-ems-accent]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                setAccent(btn.getAttribute('data-ems-accent'));
            });
            btn.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    setAccent(btn.getAttribute('data-ems-accent'));
                }
            });
        });
    }

    function init() {
        applyAppearance();

        document.querySelectorAll('[data-ems-appearance]').forEach(bindAppearance);

        document.querySelectorAll('.dropdown').forEach(function (dd) {
            if (!dd.querySelector('[data-ems-appearance]')) return;
            var toggle = dd.querySelector('[data-bs-toggle="dropdown"]');
            if (toggle && !toggle.getAttribute('data-bs-auto-close')) {
                toggle.setAttribute('data-bs-auto-close', 'outside');
            }
            dd.addEventListener('hide.bs.dropdown', function () {
                dd.querySelectorAll('[data-ems-appearance]').forEach(function (root) {
                    setPanelOpen(root, false);
                });
            });
        });

        if (window.matchMedia) {
            var mq = window.matchMedia('(prefers-color-scheme: dark)');
            var onChange = function () {
                if (safeGet(MODE_KEY, DEFAULT_MODE) === 'system') {
                    applyAppearance('system', safeGet(ACCENT_KEY, DEFAULT_ACCENT));
                }
            };
            if (mq.addEventListener) mq.addEventListener('change', onChange);
            else if (mq.addListener) mq.addListener(onChange);
        }
    }

    window.EMS = window.EMS || {};
    window.EMS.appearance = {
        apply: applyAppearance,
        setMode: setMode,
        setAccent: setAccent
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
