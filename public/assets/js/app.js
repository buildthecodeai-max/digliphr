(function () {
    'use strict';

    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

    window.EMS = window.EMS || {};

    window.EMS.toast = function (message, type) {
        type = type || 'success';
        const container = document.getElementById('toastContainer');
        if (!container || !window.bootstrap) {
            alert(message);
            return;
        }
        const id = 'toast-' + Date.now();
        const bg = type === 'danger' ? 'text-bg-danger' : (type === 'warning' ? 'text-bg-warning' : 'text-bg-success');
        container.insertAdjacentHTML('beforeend',
            '<div id="' + id + '" class="toast align-items-center ' + bg + ' border-0" role="alert">' +
            '<div class="d-flex"><div class="toast-body">' + message + '</div>' +
            '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div>');
        const el = document.getElementById(id);
        const toast = new bootstrap.Toast(el, { delay: 3500 });
        toast.show();
        el.addEventListener('hidden.bs.toast', function () { el.remove(); });
    };

    window.EMS.confirm = function (message) {
        return new Promise(function (resolve) {
            const modalEl = document.getElementById('confirmModal');
            if (!modalEl || !window.bootstrap) {
                resolve(window.confirm(message));
                return;
            }
            document.getElementById('confirmModalBody').textContent = message || 'Are you sure?';
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            const okBtn = document.getElementById('confirmModalOk');
            let settled = false;

            const finish = function (ok) {
                if (settled) {
                    return;
                }
                settled = true;
                okBtn.removeEventListener('click', onOk);
                resolve(ok);
            };

            const onOk = function () {
                finish(true);
                modal.hide();
            };

            okBtn.addEventListener('click', onOk);
            modalEl.addEventListener('hidden.bs.modal', function onHide() {
                modalEl.removeEventListener('hidden.bs.modal', onHide);
                finish(false);
            }, { once: true });
            modal.show();
        });
    };

    window.EMS.fetchJson = function (url, options) {
        options = options || {};
        options.headers = Object.assign({
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken
        }, options.headers || {});
        if (options.body && typeof options.body === 'object' && !(options.body instanceof FormData)) {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(options.body);
        }
        return fetch(url, options).then(function (res) {
            return res.json().then(function (data) {
                if (!res.ok) throw data;
                return data;
            });
        });
    };

    /**
     * Bind archived force-delete modal fields from a row action button.
     * Called via inline onclick / delegated click so Lucide SVG clicks still work.
     */
    window.EMS.prepareForceDelete = function (el, type) {
        var btn = el;
        if (!btn) {
            return false;
        }
        if (btn.closest && !btn.getAttribute('data-attendance-id') && !btn.getAttribute('data-leave-id')) {
            btn = btn.closest('[data-attendance-id], [data-leave-id]') || btn;
        }
        if (!type) {
            if (btn.getAttribute('data-leave-id')) {
                type = 'leave';
            } else if (btn.getAttribute('data-attendance-id')) {
                type = 'attendance';
            }
        }
        if (!type) {
            return false;
        }
        var id = btn.getAttribute('data-' + type + '-id') || '';
        if (!id) {
            return false;
        }
        var labelText = btn.getAttribute('data-' + type + '-label') || ('Record #' + id);
        var idField = document.getElementById(type === 'leave' ? 'forceDeleteLeaveId' : 'forceDeleteAttendanceId');
        var label = document.getElementById(type === 'leave' ? 'forceDeleteLeaveLabel' : 'forceDeleteAttendanceLabel');
        var reason = document.getElementById(type === 'leave' ? 'forceDeleteLeaveReason' : 'forceDeleteAttendanceReason');
        var form = document.getElementById(type === 'leave' ? 'forceDeleteLeaveForm' : 'forceDeleteAttendanceForm');
        if (idField) {
            idField.value = String(id);
        }
        if (label) {
            label.textContent = labelText;
        }
        if (reason) {
            reason.value = '';
            setTimeout(function () { reason.focus(); }, 200);
        }
        if (form) {
            form.setAttribute('action', type === 'leave'
                ? ('/admin/leave/' + id + '/force-delete')
                : ('/admin/attendance/' + id + '/force-delete'));
        }
        return true;
    };

    function initSidebar() {
        const shell = document.getElementById('appShell');
        const toggle = document.getElementById('sidebarToggle');
        const collapseBtn = document.getElementById('sidebarCollapseBtn');
        const sidebar = document.getElementById('appSidebar');
        const nav = document.getElementById('sidebarNav');
        if (!shell) return;

        const scope = shell.getAttribute('data-sidebar-scope') || 'admin';
        const KEYS = {
            collapsed: 'ems.sidebar.' + scope + '.collapsed',
            openGroups: 'ems.sidebar.' + scope + '.openGroups',
            scrollTop: 'ems.sidebar.' + scope + '.scrollTop',
            legacyCollapsed: 'ems.sidebarCollapsed',
            sharedCollapsed: 'ems.sidebar.collapsed'
        };

        // Migrate legacy / shared keys once per scope
        if (localStorage.getItem(KEYS.collapsed) === null) {
            if (localStorage.getItem(KEYS.sharedCollapsed) !== null) {
                localStorage.setItem(KEYS.collapsed, localStorage.getItem(KEYS.sharedCollapsed));
            } else if (localStorage.getItem(KEYS.legacyCollapsed) !== null) {
                localStorage.setItem(KEYS.collapsed, localStorage.getItem(KEYS.legacyCollapsed));
            }
        }

        if (localStorage.getItem(KEYS.collapsed) === '1' && window.innerWidth >= 992) {
            shell.classList.add('sidebar-collapsed');
        }
        document.documentElement.classList.remove('ems-sidebar-collapsed-pending');

        function readOpenGroups() {
            try {
                return JSON.parse(localStorage.getItem(KEYS.openGroups) || '{}') || {};
            } catch (e) {
                return {};
            }
        }

        function writeOpenGroups(map) {
            localStorage.setItem(KEYS.openGroups, JSON.stringify(map));
        }

        const openGroups = readOpenGroups();
        document.querySelectorAll('[data-sidebar-group]').forEach(function (group) {
            const key = group.getAttribute('data-sidebar-group');
            const routeOpen = group.classList.contains('is-open');
            const userOpen = !!openGroups[key];
            if (routeOpen || userOpen) {
                group.classList.add('is-open');
                const btn = group.querySelector('.sidebar-group-toggle');
                if (btn) btn.setAttribute('aria-expanded', 'true');
            }
            if (routeOpen) {
                openGroups[key] = true;
                writeOpenGroups(openGroups);
            }
        });

        document.querySelectorAll('.sidebar-group-toggle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const group = btn.closest('[data-sidebar-group]');
                if (!group) return;
                const key = group.getAttribute('data-sidebar-group');
                const willOpen = !group.classList.contains('is-open');
                group.classList.toggle('is-open', willOpen);
                btn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
                const map = readOpenGroups();
                if (willOpen) map[key] = true;
                else delete map[key];
                // Keep route-active groups open even if user toggles closed then navigates away;
                // still honor explicit collapse for non-active groups.
                if (group.classList.contains('is-active') && !willOpen) {
                    delete map[key];
                }
                writeOpenGroups(map);
            });
        });

        if (nav) {
            // Initial scroll position is restored synchronously by an inline
            // script right after #sidebarNav's markup (see sidebar-admin.php /
            // sidebar-employee.php) so it happens before first paint instead
            // of here at DOMContentLoaded, which used to cause a visible jump.
            nav.addEventListener('scroll', function () {
                localStorage.setItem(KEYS.scrollTop, String(nav.scrollTop || 0));
            }, { passive: true });
        }

        if (toggle) {
            toggle.addEventListener('click', function () {
                shell.classList.toggle('sidebar-open');
            });
        }

        if (collapseBtn) {
            collapseBtn.addEventListener('click', function () {
                shell.classList.toggle('sidebar-collapsed');
                localStorage.setItem(KEYS.collapsed, shell.classList.contains('sidebar-collapsed') ? '1' : '0');
                if (window.EMSMotion && !EMSMotion.reduced) {
                    EMSMotion.animateIn(document.getElementById('appSidebar'), 'fadeIn');
                }
            });
        }

        // When a collapsed sidebar expands on hover, Bootstrap tooltips must be
        // hidden; otherwise the tooltip remains pinned over the content area
        // while the full menu label is already visible.
        if (sidebar && window.innerWidth >= 992 && window.bootstrap) {
            sidebar.addEventListener('mouseenter', function () {
                if (!shell.classList.contains('sidebar-collapsed')) return;
                sidebar.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
                    var tip = bootstrap.Tooltip.getInstance(el);
                    if (tip) tip.hide();
                });
            });
            sidebar.addEventListener('mouseleave', function () {
                if (!shell.classList.contains('sidebar-collapsed')) return;
                sidebar.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
                    bootstrap.Tooltip.getOrCreateInstance(el);
                });
            });
        }

        document.querySelectorAll('.sidebar-link, .sidebar-sublink').forEach(function (link) {
            link.addEventListener('click', function () {
                if (window.innerWidth < 992) shell.classList.remove('sidebar-open');
                if (nav) localStorage.setItem(KEYS.scrollTop, String(nav.scrollTop || 0));
            });
        });
    }

    function notificationItems(payload) {
        if (!payload) return { items: [], unread: 0 };
        if (Array.isArray(payload)) {
            return {
                items: payload,
                unread: payload.filter(function (n) { return !Number(n.is_read); }).length
            };
        }
        const items = payload.items || [];
        const unread = typeof payload.unread_count === 'number'
            ? payload.unread_count
            : items.filter(function (n) { return !Number(n.is_read); }).length;
        return { items: items, unread: unread };
    }

    var _prevUnread = -1;

    function _relativeTime(dateStr) {
        if (!dateStr) return '';
        var d = new Date(dateStr.replace(' ', 'T'));
        if (isNaN(d)) return '';
        var diff = Math.floor((Date.now() - d.getTime()) / 1000);
        if (diff < 60) return 'just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        return Math.floor(diff / 86400) + 'd ago';
    }

    function _ringBell(btn, count, isNew) {
        if (!btn) return;
        btn.classList.remove('bell-ringing');
        void btn.offsetWidth;
        btn.classList.add('bell-ringing');
        btn.addEventListener('animationend', function () {
            btn.classList.remove('bell-ringing');
        }, { once: true });
        if (isNew && count) {
            count.classList.remove('badge-new');
            void count.offsetWidth;
            count.classList.add('badge-new');
            count.addEventListener('animationend', function () {
                count.classList.remove('badge-new');
            }, { once: true });
        }
    }

    function renderNotificationRows(list, count, payload) {
        var parsed = notificationItems(payload);
        var btn = document.getElementById('notifBtn');
        if (count) {
            if (parsed.unread > 0) {
                var isNew = _prevUnread >= 0 && parsed.unread > _prevUnread;
                count.textContent = String(parsed.unread > 99 ? '99+' : parsed.unread);
                count.classList.remove('d-none');
                if (isNew || (_prevUnread === -1 && parsed.unread > 0)) {
                    _ringBell(btn, count, true);
                }
            } else {
                count.classList.add('d-none');
            }
        }
        _prevUnread = parsed.unread;
        if (!parsed.items.length) {
            list.innerHTML = '<div class="p-3 text-muted">You\'re all caught up.</div>';
            return;
        }
        list.innerHTML = parsed.items.slice(0, 8).map(function (n) {
            var href = n.action_url || '#';
            var unreadCls = !Number(n.is_read) ? ' unread' : '';
            var time = _relativeTime(n.created_at);
            return '<a class="list-group-item list-group-item-action' + unreadCls + '" href="' + href + '" data-notif-id="' + (n.id || '') + '">' +
                '<div class="fw-semibold">' + (n.title || 'Notification') + '</div>' +
                '<div class="text-muted small">' + (n.message || '') + '</div>' +
                (time ? '<div class="notif-time">' + time + '</div>' : '') +
                '</a>';
        }).join('');
    }

    function refreshNotifications() {
        var list = document.getElementById('notifList');
        var count = document.getElementById('notifCount');
        if (!list && !count) return Promise.resolve();

        return EMS.fetchJson('/api/notifications').then(function (res) {
            var payload = res.data || [];
            if (list) {
                renderNotificationRows(list, count, payload);
            } else if (count) {
                var parsed = notificationItems(payload);
                var btn = document.getElementById('notifBtn');
                if (parsed.unread > 0) {
                    var isNew = _prevUnread >= 0 && parsed.unread > _prevUnread;
                    count.textContent = String(parsed.unread > 99 ? '99+' : parsed.unread);
                    count.classList.remove('d-none');
                    if (isNew || (_prevUnread === -1 && parsed.unread > 0)) {
                        _ringBell(btn, count, true);
                    }
                } else {
                    count.classList.add('d-none');
                }
                _prevUnread = parsed.unread;
            }
        }).catch(function () {
            if (list) list.innerHTML = '<div class="p-3 text-muted">Unable to load notifications.</div>';
        });
    }

    EMS.refreshNotifications = refreshNotifications;

    function initNotifications() {
        var list = document.getElementById('notifList');
        var count = document.getElementById('notifCount');
        if (!list && !count) return;

        refreshNotifications();
        // Re-poll every 15 s so new notifications (chat messages, leave updates, etc.) appear.
        setInterval(refreshNotifications, 15000);

        if (list) {
            list.addEventListener('click', function (e) {
                var link = e.target.closest('[data-notif-id]');
                if (!link) return;
                var id = link.getAttribute('data-notif-id');
                if (!id) return;
                var wasUnread = link.classList.contains('unread');
                EMS.fetchJson('/api/notifications/' + id + '/read', { method: 'POST', body: {} }).catch(function () {});
                link.remove();
                if (wasUnread) {
                    _prevUnread = Math.max(0, _prevUnread - 1);
                    if (count) {
                        if (_prevUnread > 0) {
                            count.textContent = String(_prevUnread > 99 ? '99+' : _prevUnread);
                        } else {
                            count.classList.add('d-none');
                        }
                    }
                }
                if (!list.querySelector('[data-notif-id]')) {
                    list.innerHTML = '<div class="p-3 text-muted">You\'re all caught up.</div>';
                }
            });
        }

        var markAllBtn = document.getElementById('notifMarkAllRead');
        if (markAllBtn) {
            markAllBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                EMS.fetchJson('/api/notifications/read-all', { method: 'POST', body: {} }).then(function () {
                    if (list) {
                        list.querySelectorAll('[data-notif-id]').forEach(function (el) { el.remove(); });
                        list.innerHTML = '<div class="p-3 text-muted">You\'re all caught up.</div>';
                    }
                    _prevUnread = 0;
                    if (count) count.classList.add('d-none');
                }).catch(function () {});
            });
        }
    }

    function initTooltips() {
        if (!window.bootstrap) return;
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            bootstrap.Tooltip.getOrCreateInstance(el);
        });
    }

    function initGlobalSearch() {
        const input = document.getElementById('globalSearch');
        if (!input) return;
        const hint = document.getElementById('globalSearchHints');
        const results = document.getElementById('globalSearchResults');
        let searchTimer = null;
        function clearResults() { if (results) { results.innerHTML = ''; results.classList.add('d-none'); } }
        function renderResults(items) {
            if (!results) return;
            results.innerHTML = '';
            if (!items.length) { results.innerHTML = '<div class="global-search-empty">No employees found</div>'; results.classList.remove('d-none'); return; }
            items.forEach(function (item) {
                const link = document.createElement('a');
                link.className = 'global-search-result';
                link.href = '/admin/employees/' + encodeURIComponent(item.id);
                link.setAttribute('role', 'option');
                const name = document.createElement('strong');
                name.textContent = (item.first_name || '') + ' ' + (item.last_name || '');
                const meta = document.createElement('small');
                meta.textContent = (item.employee_code || '') + (item.department_name ? ' · ' + item.department_name : '');
                link.appendChild(name); link.appendChild(meta); results.appendChild(link);
            });
            results.classList.remove('d-none');
        }
        input.addEventListener('focus', function () {
            if (hint) { hint.textContent = 'Search employees by name or code, then press Enter'; hint.classList.remove('d-none'); }
        });
        input.addEventListener('input', function () {
            const q = input.value.trim();
            clearTimeout(searchTimer); clearResults();
            if (q.length < 2) return;
            searchTimer = setTimeout(function () {
                fetch('/api/employees/search?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } })
                    .then(function (res) { return res.ok ? res.json() : null; })
                    .then(function (payload) { if (payload && payload.success) renderResults((payload.data && payload.data.items) || []); })
                    .catch(function () { clearResults(); });
            }, 220);
        });
        input.addEventListener('blur', function () {
            window.setTimeout(function () { if (hint) hint.classList.add('d-none'); clearResults(); }, 180);
        });
        input.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') return;
            const q = input.value.trim();
            if (!q) return;
            const shell = document.getElementById('appShell');
            const scope = shell ? (shell.getAttribute('data-sidebar-scope') || 'admin') : 'admin';
            // Employee search is handled by workspace-nav.js
            if (scope === 'employee') return;
            window.location.href = '/admin/employees?q=' + encodeURIComponent(q);
        });
    }

    function initPasswordToggles() {
        document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
            button.addEventListener('click', function () {
                const input = document.getElementById(button.getAttribute('data-password-toggle'));
                if (!input) return;
                const visible = input.type === 'text';
                input.type = visible ? 'password' : 'text';
                button.setAttribute('aria-pressed', visible ? 'false' : 'true');
                button.setAttribute('aria-label', visible ? 'Show password' : 'Hide password');
                button.innerHTML = '<i data-lucide="' + (visible ? 'eye' : 'eye-off') + '"></i>';
                if (window.lucide) lucide.createIcons();
            });
        });
    }

    function initDashboardRefresh() {
        if (!document.querySelector('[data-dashboard-stat]') || !window.EMS || typeof EMS.fetchJson !== 'function') return;
        function refresh() {
            EMS.fetchJson('/api/dashboard/stats').then(function (payload) {
                const stats = payload.data || {};
                document.querySelectorAll('[data-dashboard-stat]').forEach(function (el) {
                    const key = el.getAttribute('data-dashboard-stat');
                    if (Object.prototype.hasOwnProperty.call(stats, key)) el.textContent = Number(stats[key]).toLocaleString();
                });
            }).catch(function () {});
        }
        window.setInterval(refresh, 60000);
    }

    window.EMS.openDrawer = function (title, html) {
        const drawer = document.getElementById('detailDrawer');
        const body = document.getElementById('detailDrawerBody');
        const titleEl = document.getElementById('detailDrawerTitle');
        const backdrop = document.getElementById('drawerBackdrop');
        if (!drawer || !body) return;
        if (titleEl) titleEl.textContent = title || 'Details';
        body.innerHTML = html || '';
        drawer.classList.add('show');
        drawer.setAttribute('aria-hidden', 'false');
        if (backdrop) {
            backdrop.hidden = false;
            backdrop.classList.add('show');
        }
        document.body.classList.add('drawer-open');
        const closeBtn = document.getElementById('detailDrawerClose');
        if (closeBtn) closeBtn.focus();
    };

    window.EMS.closeDrawer = function () {
        const drawer = document.getElementById('detailDrawer');
        const backdrop = document.getElementById('drawerBackdrop');
        if (drawer) {
            drawer.classList.remove('show');
            drawer.setAttribute('aria-hidden', 'true');
        }
        if (backdrop) {
            backdrop.classList.remove('show');
            backdrop.hidden = true;
        }
        document.body.classList.remove('drawer-open');
    };

    function initDrawer() {
        const closeBtn = document.getElementById('detailDrawerClose');
        const backdrop = document.getElementById('drawerBackdrop');
        if (closeBtn) closeBtn.addEventListener('click', EMS.closeDrawer);
        if (backdrop) backdrop.addEventListener('click', EMS.closeDrawer);
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') EMS.closeDrawer();
        });
    }

    function relocateModalsToBody() {
        // Modals rendered inside .motion-fade-up / transformed parents end up
        // under the Bootstrap backdrop and become unclickable ("screen disabled").
        document.querySelectorAll('.modal').forEach(function (modal) {
            if (modal.parentElement !== document.body) {
                document.body.appendChild(modal);
            }
        });
    }

    function ensureModalOnBody(modal) {
        if (!modal || !modal.classList || !modal.classList.contains('modal')) {
            return;
        }
        // Always append as last child so the dialog stacks above any backdrop.
        if (modal.parentElement !== document.body || document.body.lastElementChild !== modal) {
            document.body.appendChild(modal);
        }
    }

    function ensureOpenModalAboveBackdrop(modal) {
        if (!modal) {
            return;
        }
        var backdrops = document.querySelectorAll('body > .modal-backdrop');
        var maxBackdropZ = 1070;
        backdrops.forEach(function (bd) {
            var z = parseInt(window.getComputedStyle(bd).zIndex, 10);
            if (!isNaN(z) && z > maxBackdropZ) {
                maxBackdropZ = z;
            }
        });
        var modalZ = parseInt(window.getComputedStyle(modal).zIndex, 10);
        if (isNaN(modalZ) || modalZ <= maxBackdropZ) {
            modal.style.zIndex = String(maxBackdropZ + 10);
        }
    }

    function initForceDeleteModals() {
        relocateModalsToBody();

        document.addEventListener('show.bs.modal', function (event) {
            ensureModalOnBody(event.target);
        }, true);

        document.addEventListener('shown.bs.modal', function (event) {
            ensureModalOnBody(event.target);
            ensureOpenModalAboveBackdrop(event.target);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initSidebar();
        initNotifications();
        initTooltips();
        initGlobalSearch();
        initPasswordToggles();
        initDashboardRefresh();
        initDrawer();
        initForceDeleteModals();
        if (window.lucide) lucide.createIcons();

        document.querySelectorAll('form[data-confirm]').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                if (form.dataset.confirmed === '1') {
                    form.dataset.confirmed = '0';
                    return;
                }
                e.preventDefault();
                EMS.confirm(form.getAttribute('data-confirm')).then(function (ok) {
                    if (ok) {
                        form.dataset.confirmed = '1';
                        form.submit();
                    }
                });
            });
        });
    });
})();
