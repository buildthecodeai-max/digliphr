/* =========================================================
   EMS Unified Design System — interactions
   Progressive enhancement only: every behaviour here is additive.
   No form submission, link navigation or existing handler is
   intercepted or prevented.
   ========================================================= */
(function () {
    'use strict';

    var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn, { once: true });
        } else {
            fn();
        }
    }

    /* ---------------------------------------------------------
       Ripple — pointer feedback on buttons
       Passive listener; never blocks the real click.
       --------------------------------------------------------- */
    function initRipple() {
        if (reduced) return;

        document.addEventListener('pointerdown', function (e) {
            var btn = e.target.closest('.btn:not(.btn-close), .action-btn, .btn-icon, .ds-fab');
            if (!btn || btn.disabled || btn.getAttribute('aria-disabled') === 'true') return;
            if (btn.dataset.dsState === 'loading') return;

            var rect = btn.getBoundingClientRect();
            var size = Math.max(rect.width, rect.height);
            var ripple = document.createElement('span');

            ripple.className = 'ds-ripple';
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
            ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';

            btn.appendChild(ripple);
            setTimeout(function () { ripple.remove(); }, 600);
        }, { passive: true });
    }

    /* ---------------------------------------------------------
       Button states — public API
       DS.setState(btn, 'loading' | 'success' | 'error' | null)
       --------------------------------------------------------- */
    function setState(btn, state) {
        if (!btn) return;
        if (!state) {
            delete btn.dataset.dsState;
            if (btn.dataset.dsWasDisabled === '1') {
                delete btn.dataset.dsWasDisabled;
            } else {
                btn.disabled = false;
            }
            return;
        }
        if (btn.disabled) btn.dataset.dsWasDisabled = '1';
        btn.dataset.dsState = state;
    }

    /* Opt-in: <button data-ds-loading> shows a spinner while its form submits. */
    function initFormLoading() {
        document.addEventListener('submit', function (e) {
            var form = e.target;
            if (!(form instanceof HTMLFormElement)) return;

            var btn = form.querySelector('[data-ds-loading]');
            if (!btn) return;

            // Let native validation win — don't spin on a form that won't submit.
            if (typeof form.checkValidity === 'function' && !form.checkValidity()) return;

            setState(btn, 'loading');
        }, true);
    }

    /* ---------------------------------------------------------
       Tabs — sliding indicator driven by transform (GPU)

       Scoped to .report-tabs / .nav-pills only. .workspace-tabs already
       has its own working sliding indicator owned by workspace-nav.js
       (left/width based — see workspace-nav.css); touching that element
       here too would fight it, so it is left alone.
       --------------------------------------------------------- */
    function moveIndicator(container) {
        var indicator = container.querySelector('.ds-tab-indicator');
        if (!indicator) return;

        var active = container.querySelector(
            '.workspace-tab.is-active, .nav-link.active, [aria-selected="true"]'
        );
        if (!active) {
            indicator.style.transform = 'translate3d(0,0,0) scaleX(0)';
            return;
        }

        var host = indicator.offsetParent || container;
        var hostRect = host.getBoundingClientRect();
        var rect = active.getBoundingClientRect();

        var width = Math.max(rect.width * 0.55, 24);
        var left = (rect.left - hostRect.left) + (rect.width - width) / 2;

        // width stays 1px in CSS; scaleX carries the size so only transform animates.
        indicator.style.transform =
            'translate3d(' + left + 'px, 0, 0) scaleX(' + width + ')';
    }

    function initTabs() {
        var containers = document.querySelectorAll('.report-tabs, .nav-pills');
        if (!containers.length) return;

        containers.forEach(function (container) {
            if (getComputedStyle(container).position === 'static') {
                container.style.position = 'relative';
            }
            if (!container.querySelector('.ds-tab-indicator')) {
                var ind = document.createElement('span');
                ind.className = 'ds-tab-indicator';
                container.appendChild(ind);
            }

            // Position without animating on first paint.
            var indicator = container.querySelector('.ds-tab-indicator');
            var prev = indicator.style.transition;
            indicator.style.transition = 'none';
            moveIndicator(container);
            void indicator.offsetWidth;
            indicator.style.transition = prev;

            container.addEventListener('click', function (e) {
                if (!e.target.closest('.workspace-tab, .nav-link')) return;
                // Bootstrap toggles .active after this tick.
                setTimeout(function () { moveIndicator(container); }, 0);
            });

            if (window.ResizeObserver) {
                new ResizeObserver(function () { moveIndicator(container); }).observe(container);
            }
        });

        window.addEventListener('resize', function () {
            containers.forEach(moveIndicator);
        }, { passive: true });

        // Bootstrap tab events
        document.addEventListener('shown.bs.tab', function (e) {
            var container = e.target.closest('.report-tabs, .nav-pills');
            if (container) moveIndicator(container);
        });
    }

    /* ---------------------------------------------------------
       Toasts
       DS.toast('Saved', { variant: 'success', duration: 4000 })
       --------------------------------------------------------- */
    var ICONS = {
        success: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>',
        danger: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v5M12 16h.01"/></svg>',
        warning: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/></svg>',
        info: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>'
    };

    function stack() {
        var el = document.querySelector('.ds-toast-stack');
        if (!el) {
            el = document.createElement('div');
            el.className = 'ds-toast-stack';
            el.setAttribute('role', 'region');
            el.setAttribute('aria-label', 'Notifications');
            document.body.appendChild(el);
        }
        return el;
    }

    function toast(message, options) {
        options = options || {};
        var variant = options.variant || 'info';
        var duration = typeof options.duration === 'number' ? options.duration : 4000;

        var el = document.createElement('div');
        el.className = 'ds-toast';
        el.dataset.variant = variant;
        el.setAttribute('role', variant === 'danger' ? 'alert' : 'status');
        el.setAttribute('aria-live', variant === 'danger' ? 'assertive' : 'polite');

        var icon = document.createElement('span');
        icon.className = 'ds-toast-icon';
        icon.innerHTML = ICONS[variant] || ICONS.info;

        var body = document.createElement('div');
        body.className = 'ds-toast-body';
        body.textContent = message;

        el.appendChild(icon);
        el.appendChild(body);

        if (duration > 0 && !reduced) {
            var bar = document.createElement('span');
            bar.className = 'ds-toast-progress';
            bar.style.animationDuration = duration + 'ms';
            el.appendChild(bar);
        }

        stack().appendChild(el);

        function dismiss() {
            if (!el.isConnected) return;
            el.classList.add('is-leaving');
            setTimeout(function () { el.remove(); }, 260);
        }

        if (duration > 0) setTimeout(dismiss, duration);
        el.addEventListener('click', dismiss);

        return el;
    }

    /* Promote existing server-rendered flash alerts into toasts. */
    function initFlashToasts() {
        var map = { success: 'success', danger: 'danger', error: 'danger', warning: 'warning', info: 'info' };

        document.querySelectorAll('[data-ds-flash]').forEach(function (node) {
            var text = (node.textContent || '').trim();
            if (!text) return;
            toast(text, { variant: map[node.dataset.dsFlash] || 'info' });
            node.remove();
        });
    }

    /* ---------------------------------------------------------
       Tooltips — Bootstrap, opt-in via [data-bs-toggle="tooltip"]
       --------------------------------------------------------- */
    function initTooltips() {
        if (!window.bootstrap || !window.bootstrap.Tooltip) return;
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            if (!bootstrap.Tooltip.getInstance(el)) {
                new bootstrap.Tooltip(el, { animation: true, delay: { show: 120, hide: 60 } });
            }
        });
    }

    /* ---------------------------------------------------------
       Password visibility toggle — opt-in via [data-ds-password]
       --------------------------------------------------------- */
    var EYE = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>';
    var EYE_OFF = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.7 5.1A10.9 10.9 0 0 1 12 5c6.4 0 10 7 10 7a18.4 18.4 0 0 1-2.7 3.7M6.6 6.6A18.4 18.4 0 0 0 2 12s3.6 7 10 7a10.8 10.8 0 0 0 5.4-1.4"/><path d="m2 2 20 20"/></svg>';

    function initPasswordToggles() {
        document.querySelectorAll('input[type="password"][data-ds-password]').forEach(function (input) {
            if (input.dataset.dsPasswordReady === '1') return;
            input.dataset.dsPasswordReady = '1';

            var wrap = document.createElement('div');
            wrap.className = 'ds-password-wrap';
            input.parentNode.insertBefore(wrap, input);
            wrap.appendChild(input);

            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'ds-password-toggle';
            btn.innerHTML = EYE;
            btn.setAttribute('aria-label', 'Show password');
            btn.addEventListener('click', function () {
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.innerHTML = show ? EYE_OFF : EYE;
                btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            });

            wrap.appendChild(btn);
        });
    }

    /* ---------------------------------------------------------
       Dropdowns — type-ahead + arrow-key navigation
       --------------------------------------------------------- */
    function initDropdownKeys() {
        document.addEventListener('keydown', function (e) {
            var menu = document.querySelector('.dropdown-menu.show');
            if (!menu) return;
            if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;

            var items = Array.prototype.slice.call(
                menu.querySelectorAll('.dropdown-item:not(.disabled)')
            );
            if (!items.length) return;

            e.preventDefault();
            var idx = items.indexOf(document.activeElement);
            var next = e.key === 'ArrowDown'
                ? (idx + 1) % items.length
                : (idx <= 0 ? items.length - 1 : idx - 1);

            items[next].focus();
        });
    }

    /* --------------------------------------------------------- */
    ready(function () {
        initRipple();
        initFormLoading();
        initTabs();
        initFlashToasts();
        initTooltips();
        initPasswordToggles();
        initDropdownKeys();
    });

    window.DS = {
        toast: toast,
        setState: setState,
        refreshTabs: function () {
            document.querySelectorAll('.report-tabs, .nav-pills')
                .forEach(moveIndicator);
        },
        initTooltips: initTooltips,
        initPasswordToggles: initPasswordToggles
    };
})();
