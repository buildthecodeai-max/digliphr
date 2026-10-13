/* Command Palette — Ctrl+K / ⌘K
   Searches employees via existing /api/employees/search endpoint
   and provides fuzzy-filtered quick-jump navigation links. */

(function () {
    'use strict';

    // ------------------------------------------------------------------ nav items
    var PAGES = [
        { name: 'Dashboard',          sub: 'Overview & workforce snapshot',    url: '/admin/dashboard',              icon: 'layout-dashboard', badge: 'Page' },
        { name: 'Employees',          sub: 'All employee records',             url: '/admin/employees',              icon: 'users',            badge: 'Page' },
        { name: 'Attendance',         sub: 'Daily check-in / check-out logs',  url: '/admin/attendance',             icon: 'calendar-check',   badge: 'Page' },
        { name: 'Approvals',          sub: 'Pending leave & HR requests',      url: '/admin/approvals',              icon: 'check-circle',     badge: 'Page' },
        { name: 'Leave Management',   sub: 'Leave requests & balances',        url: '/admin/leaves',                 icon: 'umbrella',         badge: 'Page' },
        { name: 'Payroll',            sub: 'Salary processing & payslips',     url: '/admin/payroll',                icon: 'banknote',         badge: 'Page' },
        { name: 'Documents',          sub: 'Company documents & e-sign',       url: '/admin/documents',              icon: 'file-text',        badge: 'Page' },
        { name: 'Team Chat',          sub: 'Internal messaging',               url: '/chat',                         icon: 'message-square',   badge: 'Page' },
        { name: 'People',             sub: 'Org chart & directory',            url: '/admin/people',                 icon: 'contact',          badge: 'Page' },
        { name: 'Attendance Reports', sub: 'Full analytics & exports',         url: '/admin/reports/attendance',     icon: 'calendar',         badge: 'Report' },
        { name: 'Leave Reports',      sub: 'Leave analytics',                  url: '/admin/reports/leave',          icon: 'umbrella',         badge: 'Report' },
        { name: 'Payroll Reports',    sub: 'Payroll analytics',                url: '/admin/reports/payroll',        icon: 'receipt',          badge: 'Report' },
        { name: 'Departments',        sub: 'Manage departments',               url: '/admin/departments',            icon: 'building-2',       badge: 'Settings' },
        { name: 'Branches',           sub: 'Manage office branches',           url: '/admin/branches',               icon: 'map-pin',          badge: 'Settings' },
        { name: 'Shifts',             sub: 'Work shift schedules',             url: '/admin/shifts',                 icon: 'clock',            badge: 'Settings' },
        { name: 'Designations',       sub: 'Job titles & roles',               url: '/admin/designations',           icon: 'briefcase',        badge: 'Settings' },
        { name: 'Settings',           sub: 'Company & app settings',           url: '/admin/settings',               icon: 'settings',         badge: 'Settings' },
        { name: 'Add Employee',       sub: 'Create a new employee record',     url: '/admin/employees/create',       icon: 'user-plus',        badge: 'Action' },
        { name: 'Work Monitoring',    sub: 'Screen & activity monitoring',     url: '/admin/monitoring',             icon: 'monitor',          badge: 'Page' },
    ];

    // ------------------------------------------------------------------ dom refs
    var overlay, paletteBox, inputEl, resultsEl;
    var activeIdx = -1;
    var searchTimer = null;
    var currentQuery = '';
    var isOpen = false;

    // ------------------------------------------------------------------ init
    function buildDOM() {
        if (document.getElementById('cmdPalette')) return;

        overlay = document.createElement('div');
        overlay.id = 'cmdPalette';
        overlay.className = 'cmd-palette-overlay';
        overlay.setAttribute('hidden', '');
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', 'Command palette');

        overlay.innerHTML =
            '<div class="cmd-palette" id="cmdPaletteBox">' +
                '<div class="cmd-input-wrap">' +
                    '<span class="cmd-input-icon">' +
                        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">' +
                            '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>' +
                        '</svg>' +
                    '</span>' +
                    '<input type="text" id="cmdPaletteInput" ' +
                        'placeholder="Search employees, pages, reports…" ' +
                        'autocomplete="off" autocorrect="off" spellcheck="false" ' +
                        'aria-label="Search" aria-autocomplete="list" aria-controls="cmdResults">' +
                    '<span class="cmd-esc-badge">Esc</span>' +
                '</div>' +
                '<div class="cmd-results" id="cmdResults" role="listbox"></div>' +
                '<div class="cmd-footer">' +
                    '<span class="cmd-hint"><kbd>↑</kbd><kbd>↓</kbd> navigate</span>' +
                    '<span class="cmd-hint"><kbd>↵</kbd> open</span>' +
                    '<span class="cmd-hint"><kbd>Esc</kbd> close</span>' +
                '</div>' +
            '</div>';

        document.body.appendChild(overlay);
        paletteBox = document.getElementById('cmdPaletteBox');
        inputEl    = document.getElementById('cmdPaletteInput');
        resultsEl  = document.getElementById('cmdResults');

        // Close on backdrop click
        overlay.addEventListener('click', function (e) {
            if (!paletteBox.contains(e.target)) close();
        });

        inputEl.addEventListener('input', function () {
            onQuery(inputEl.value.trim());
        });

        inputEl.addEventListener('keydown', function (e) {
            switch (e.key) {
                case 'ArrowDown': e.preventDefault(); moveActive(1);   break;
                case 'ArrowUp':   e.preventDefault(); moveActive(-1);  break;
                case 'Enter':     e.preventDefault(); confirmActive(); break;
                case 'Escape':    close(); break;
            }
        });
    }

    // ------------------------------------------------------------------ open / close
    function open() {
        buildDOM();
        overlay.removeAttribute('hidden');
        isOpen = true;
        inputEl.value = '';
        currentQuery = '';
        activeIdx = -1;
        showDefault();
        requestAnimationFrame(function () { inputEl.focus(); });
    }

    function close() {
        if (!overlay) return;
        overlay.setAttribute('hidden', '');
        isOpen = false;
        activeIdx = -1;
        clearTimeout(searchTimer);
    }

    // ------------------------------------------------------------------ rendering
    function renderGroups(groups) {
        var html = '';
        var idx  = 0;
        var total = 0;

        groups.forEach(function (g) {
            if (!g.items || !g.items.length) return;
            html += '<div class="cmd-group-label">' + esc(g.label) + '</div>';
            g.items.forEach(function (item) {
                html +=
                    '<a class="cmd-item" href="' + esc(item.url) + '" role="option" data-cmd-idx="' + idx + '">' +
                        '<span class="cmd-icon-wrap"><i data-lucide="' + esc(item.icon || 'arrow-right') + '"></i></span>' +
                        '<span class="cmd-item-body">' +
                            '<span class="cmd-item-name">' + esc(item.name) + '</span>' +
                            (item.sub ? '<span class="cmd-item-sub">' + esc(item.sub) + '</span>' : '') +
                        '</span>' +
                        (item.badge ? '<span class="cmd-badge">' + esc(item.badge) + '</span>' : '') +
                    '</a>';
                idx++;
                total++;
            });
        });

        if (!total) {
            html =
                '<div class="cmd-empty">' +
                    '<span class="cmd-empty-icon">🔍</span>' +
                    'No results for <strong>' + esc(currentQuery) + '</strong>' +
                '</div>';
        }

        resultsEl.innerHTML = html;
        activeIdx = -1;

        if (window.lucide) lucide.createIcons({ nodes: [resultsEl] });

        // mouse hover sets active
        resultsEl.querySelectorAll('.cmd-item').forEach(function (el) {
            el.addEventListener('mouseenter', function () {
                setActive(parseInt(el.dataset.cmdIdx, 10));
            });
            el.addEventListener('click', function () {
                close();
            });
        });
    }

    function showDefault() {
        renderGroups([
            { label: 'Quick Jump', items: PAGES.slice(0, 8) },
        ]);
    }

    // ------------------------------------------------------------------ search
    function onQuery(q) {
        clearTimeout(searchTimer);
        currentQuery = q;
        activeIdx = -1;

        if (!q) { showDefault(); return; }

        var pageHits = PAGES.filter(function (p) {
            return contains(p.name, q) || contains(p.badge || '', q) || contains(p.sub, q);
        }).slice(0, 6);

        if (q.length < 2) {
            renderGroups([{ label: 'Pages & Actions', items: pageHits }]);
            return;
        }

        // Show pages immediately, employee rows will fill in
        renderGroups([
            { label: 'Pages & Actions', items: pageHits },
            { label: 'Employees', items: [] },
        ]);

        searchTimer = setTimeout(function () {
            fetch('/api/employees/search?q=' + encodeURIComponent(q), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (currentQuery !== q) return; // stale
                var raw  = (data && data.data && data.data.items) || [];
                var emps = raw.map(function (e) {
                    var fullName = ((e.first_name || '') + ' ' + (e.last_name || '')).trim();
                    var sub = [e.employee_code, e.department_name].filter(Boolean).join(' · ');
                    return { name: fullName, sub: sub, url: '/admin/employees/' + e.id, icon: 'user', badge: 'Employee' };
                });
                renderGroups([
                    { label: 'Pages & Actions', items: pageHits },
                    { label: 'Employees', items: emps },
                ]);
            })
            .catch(function () {
                // Employee search failed — just show page results
                renderGroups([{ label: 'Pages & Actions', items: pageHits }]);
            });
        }, 180);
    }

    // ------------------------------------------------------------------ keyboard nav
    function allItems() {
        return Array.from(resultsEl.querySelectorAll('.cmd-item'));
    }

    function setActive(idx) {
        var items = allItems();
        if (!items.length) return;
        if (idx < 0) idx = items.length - 1;
        if (idx >= items.length) idx = 0;
        activeIdx = idx;
        items.forEach(function (el, i) {
            el.classList.toggle('cmd-item--active', i === idx);
        });
        var activeEl = items[idx];
        if (activeEl) activeEl.scrollIntoView({ block: 'nearest' });
    }

    function moveActive(delta) {
        var items = allItems();
        if (!items.length) return;
        var next = activeIdx + delta;
        if (next < 0) next = items.length - 1;
        if (next >= items.length) next = 0;
        setActive(next);
    }

    function confirmActive() {
        var items = allItems();
        var target = (activeIdx >= 0 && items[activeIdx]) ? items[activeIdx] : items[0];
        if (target && target.href) {
            close();
            window.location.href = target.href;
        }
    }

    // ------------------------------------------------------------------ helpers
    function contains(haystack, needle) {
        return (haystack || '').toLowerCase().indexOf((needle || '').toLowerCase()) !== -1;
    }
    function esc(s) {
        return String(s || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // ------------------------------------------------------------------ global trigger
    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            // Don't hijack when user is typing in a textarea or contenteditable
            var tag = document.activeElement && document.activeElement.tagName;
            if (tag === 'TEXTAREA' || (document.activeElement && document.activeElement.isContentEditable)) return;
            e.preventDefault();
            if (isOpen) { close(); } else { open(); }
        }
    });

    // Also wire the existing topbar search input: clicking it opens the palette
    document.addEventListener('DOMContentLoaded', function () {
        var gs = document.getElementById('globalSearch');
        if (gs) {
            gs.addEventListener('focus', function () {
                gs.blur();
                open();
            });
            // Replace the placeholder text with a hint
            gs.placeholder = 'Search  (Ctrl+K)';
        }
    });

    // Expose for use from topbar button if needed
    window.EMS = window.EMS || {};
    window.EMS.openCmdPalette = open;

})();
