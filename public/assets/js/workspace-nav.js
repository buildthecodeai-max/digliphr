/**
 * Workspace navigation — sticky tabs, FAB, bottom nav, chat unread badge.
 * Full-page links only (no SPA); forms remain untouched.
 */
(function () {
    'use strict';

    function qs(sel, root) {
        return (root || document).querySelector(sel);
    }

    function qsa(sel, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(sel));
    }

    function initTabIndicator() {
        const tabs = qs('.workspace-tabs');
        if (!tabs) return;
        const scroll = qs('.workspace-tabs-scroll', tabs);
        const indicator = qs('.workspace-tab-indicator', tabs);
        const active = qs('.workspace-tab.is-active', tabs);
        if (!scroll || !indicator || !active) return;

        function place(animate) {
            const left = active.offsetLeft;
            const width = active.offsetWidth;
            indicator.style.left = left + 'px';
            indicator.style.width = Math.max(24, width * 0.55) + 'px';
            // Keep active tab in view on mobile
            const mid = left + width / 2;
            const target = Math.max(0, mid - scroll.clientWidth / 2);
            if (Math.abs(scroll.scrollLeft - target) > 8) {
                if (animate) {
                    scroll.scrollTo({ left: target, behavior: 'smooth' });
                } else {
                    // Instant on initial load: this is a full-page-nav app, so every
                    // load is a "first paint" — an animated scroll here reads as an
                    // unwanted jump rather than a deliberate transition.
                    scroll.scrollLeft = target;
                }
            }
        }

        place(false);
        window.addEventListener('resize', function () { place(true); }, { passive: true });
    }

    function initFab() {
        const fab = qs('#quickFab');
        const toggle = qs('#quickFabToggle');
        const menu = qs('#quickFabMenu');
        if (!fab || !toggle || !menu) return;

        function close() {
            fab.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
            menu.hidden = true;
        }

        function open() {
            fab.classList.add('is-open');
            toggle.setAttribute('aria-expanded', 'true');
            menu.hidden = false;
            if (window.lucide) lucide.createIcons({ nodes: [menu] });
        }

        toggle.addEventListener('click', function (e) {
            e.stopPropagation();
            if (menu.hidden) open();
            else close();
        });

        document.addEventListener('click', function (e) {
            if (!fab.contains(e.target)) close();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') close();
        });
    }

    function initMobileSearch() {
        const triggers = qsa('[data-mobile-search]');
        const modalEl = qs('#mobileSearchModal');
        const input = qs('#mobileGlobalSearch');
        const go = qs('#mobileSearchGo');
        if (!triggers.length || !modalEl || !window.bootstrap) return;

        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        const scope = (qs('#appShell') && qs('#appShell').getAttribute('data-sidebar-scope')) || 'admin';

        function runSearch() {
            const q = (input && input.value || '').trim();
            modal.hide();
            if (!q) return;
            if (scope === 'employee') {
                const map = {
                    leave: '/employee/leave',
                    attendance: '/employee/attendance',
                    payslip: '/employee/payslips',
                    payslips: '/employee/payslips',
                    loan: '/employee/loans',
                    advance: '/employee/advances',
                    document: '/employee/documents',
                    chat: '/chat',
                    profile: '/employee/profile'
                };
                const key = Object.keys(map).find(function (k) { return q.toLowerCase().indexOf(k) !== -1; });
                window.location.href = key ? map[key] : '/employee/leave?q=' + encodeURIComponent(q);
                return;
            }
            window.location.href = '/admin/employees?q=' + encodeURIComponent(q);
        }

        triggers.forEach(function (btn) {
            btn.addEventListener('click', function () {
                modal.show();
                setTimeout(function () { if (input) input.focus(); }, 200);
            });
        });

        if (go) go.addEventListener('click', runSearch);
        if (input) {
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    runSearch();
                }
            });
        }
    }

    function setUnreadBadges(total) {
        const badges = qsa('[data-chat-unread-badge]');
        badges.forEach(function (el) {
            if (total > 0) {
                el.textContent = total > 99 ? '99+' : String(total);
                el.classList.remove('d-none');
                el.setAttribute('aria-hidden', 'false');
            } else {
                el.classList.add('d-none');
                el.setAttribute('aria-hidden', 'true');
            }
        });
    }

    function initChatUnread() {
        const badges = qsa('[data-chat-unread-badge]');
        if (!badges.length) return;
        if (!window.EMS || typeof EMS.fetchJson !== 'function') {
            setUnreadBadges(0);
            return;
        }

        EMS.fetchJson('/api/chat/bootstrap')
            .then(function (res) {
                const data = res.data || {};
                let total = 0;
                (data.channels || []).forEach(function (c) {
                    total += Number(c.unread_count || 0);
                });
                (data.conversations || []).forEach(function (c) {
                    total += Number(c.unread_count || 0);
                });
                setUnreadBadges(total);
            })
            .catch(function () {
                // Hide gracefully when chat API unavailable / no permission
                setUnreadBadges(0);
            });
    }

    function initGlobalSearchEnhancements() {
        const input = qs('#globalSearch');
        if (!input) return;
        const scope = (qs('#appShell') && qs('#appShell').getAttribute('data-sidebar-scope')) || 'admin';

        // Employee search: keyword shortcuts; admin keeps employees search from app.js
        if (scope === 'employee') {
            input.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter') return;
                e.preventDefault();
                const q = input.value.trim().toLowerCase();
                if (!q) return;
                if (q.indexOf('chat') !== -1) return void (window.location.href = '/chat');
                if (q.indexOf('leave') !== -1) return void (window.location.href = '/employee/leave');
                if (q.indexOf('attend') !== -1) return void (window.location.href = '/employee/attendance');
                if (q.indexOf('pay') !== -1) return void (window.location.href = '/employee/payslips');
                window.location.href = '/employee/documents';
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        initTabIndicator();
        initFab();
        initMobileSearch();
        initChatUnread();
        initGlobalSearchEnhancements();
    });
})();
