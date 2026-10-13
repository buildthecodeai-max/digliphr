<?php
$total  = count($rows);
$unread = count(array_filter($rows, fn($r) => !(int)($r['is_read'] ?? 0)));
$read   = $total - $unread;
?>
<div class="page-header d-flex align-items-start justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <h1 class="mb-1">Notifications</h1>
        <p class="subtitle mb-0">Manage your system and HR notifications</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <button class="btn btn-sm btn-soft" id="nmpMarkAll">
            <i data-lucide="check-check" style="width:14px;height:14px"></i> Mark all read
        </button>
        <button class="btn btn-sm btn-soft" id="nmpDeleteRead" style="color:var(--ems-warning,#f59e0b)">
            <i data-lucide="trash" style="width:14px;height:14px"></i> Delete read
        </button>
        <button class="btn btn-sm btn-soft" id="nmpDeleteAll" style="color:var(--ems-danger,#ef4444)">
            <i data-lucide="trash-2" style="width:14px;height:14px"></i> Delete all
        </button>
    </div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-4">
        <div class="card ems-card p-3 text-center">
            <div class="fs-3 fw-bold mb-0" id="nmpStatTotal"><?= $total ?></div>
            <div class="small text-muted">Total</div>
        </div>
    </div>
    <div class="col-4">
        <div class="card ems-card p-3 text-center">
            <div class="fs-3 fw-bold mb-0" id="nmpStatUnread" style="color:var(--ems-primary)"><?= $unread ?></div>
            <div class="small text-muted">Unread</div>
        </div>
    </div>
    <div class="col-4">
        <div class="card ems-card p-3 text-center">
            <div class="fs-3 fw-bold mb-0 text-secondary" id="nmpStatRead"><?= $read ?></div>
            <div class="small text-muted">Read</div>
        </div>
    </div>
</div>

<!-- Filter tabs -->
<div class="d-flex gap-2 mb-3">
    <button class="btn btn-sm btn-primary" id="nmpFilterAll" onclick="nmpFilter('all')">All <span class="badge bg-white text-dark ms-1" id="nmpBadgeAll"><?= $total ?></span></button>
    <button class="btn btn-sm btn-soft" id="nmpFilterUnread" onclick="nmpFilter('unread')">Unread <span class="badge ms-1" id="nmpBadgeUnread" style="background:var(--ems-primary)"><?= $unread ?></span></button>
    <button class="btn btn-sm btn-soft" id="nmpFilterRead" onclick="nmpFilter('read')">Read <span class="badge bg-secondary ms-1" id="nmpBadgeRead"><?= $read ?></span></button>
</div>

<!-- Notification list -->
<div class="card ems-card overflow-hidden" id="nmpList">
<?php if (empty($rows)): ?>
    <div class="p-5 text-center text-muted" id="nmpEmpty">
        <i data-lucide="bell-off" style="width:40px;height:40px;opacity:.3;display:block;margin:0 auto 12px"></i>
        You're all caught up — no notifications yet.
    </div>
<?php else: ?>
    <?php foreach ($rows as $n): ?>
    <?php
        $isRead  = (int)($n['is_read'] ?? 0);
        $typeMap = [
            'chat'      => ['lucide-message-circle', '#6366f1'],
            'leave'     => ['lucide-calendar-check', '#f59e0b'],
            'payroll'   => ['lucide-banknote',       '#16a34a'],
            'attendance'=> ['lucide-clock',          '#3b82f6'],
            'system'    => ['lucide-bell',           '#20d483'],
        ];
        $nType  = strtolower((string)($n['type'] ?? 'system'));
        $icon   = $typeMap[$nType][0] ?? 'lucide-bell';
        $color  = $typeMap[$nType][1] ?? '#20d483';
        $bg     = $color . '20';
    ?>
    <div class="nmp-row d-flex align-items-start gap-3 p-3 border-bottom <?= $isRead ? 'nmp-read' : 'nmp-unread' ?>"
         data-nid="<?= (int)$n['id'] ?>" data-read="<?= $isRead ?>">
        <div class="flex-shrink-0 rounded-circle d-flex align-items-center justify-content-center"
             style="width:38px;height:38px;background:<?= $bg ?>;color:<?= $color ?>">
            <i data-lucide="<?= substr($icon, 7) ?>" style="width:16px;height:16px"></i>
        </div>
        <div class="flex-grow-1 min-w-0">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                <span class="small fw-semibold <?= $isRead ? 'text-muted' : '' ?>"><?= e($n['title'] ?? '') ?></span>
                <span class="small text-muted text-nowrap flex-shrink-0">
                    <?php
                        $ts = strtotime((string)($n['created_at'] ?? ''));
                        echo $ts ? date('M j, Y g:i A', $ts) : e((string)($n['created_at'] ?? ''));
                    ?>
                </span>
            </div>
            <div class="small text-secondary"><?= e($n['message'] ?? '') ?></div>
            <?php if (!empty($n['action_url'])): ?>
            <a href="<?= e($n['action_url']) ?>" class="small mt-1 d-inline-flex align-items-center gap-1 text-decoration-none" style="color:var(--ems-primary)">
                View <i data-lucide="arrow-right" style="width:11px;height:11px"></i>
            </a>
            <?php endif; ?>
        </div>
        <div class="d-flex flex-column gap-1 flex-shrink-0 align-items-end">
            <?php if (!$isRead): ?>
            <span class="badge rounded-pill" style="background:var(--ems-primary);font-size:.6rem">Unread</span>
            <?php endif; ?>
            <div class="d-flex gap-1">
                <?php if (!$isRead): ?>
                <button type="button" class="btn btn-sm btn-soft nmp-mark" data-id="<?= (int)$n['id'] ?>" title="Mark as read"
                        style="padding:2px 6px">
                    <i data-lucide="check" style="width:12px;height:12px"></i>
                </button>
                <?php endif; ?>
                <button type="button" class="btn btn-sm btn-soft nmp-del text-danger" data-id="<?= (int)$n['id'] ?>" title="Delete"
                        style="padding:2px 6px">
                    <i data-lucide="trash-2" style="width:12px;height:12px"></i>
                </button>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>
</div>

<script>
(function () {
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).getAttribute ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : '';
    var currentFilter = 'all';

    function api(url, done) {
        fetch(url, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json' },
            body: '{}'
        }).then(function (r) { return r.json(); }).then(done).catch(function () { done(null); });
    }

    function recalcStats() {
        var all    = document.querySelectorAll('.nmp-row');
        var unread = document.querySelectorAll('.nmp-unread');
        var readN  = all.length - unread.length;
        document.getElementById('nmpStatTotal').textContent  = all.length;
        document.getElementById('nmpStatUnread').textContent = unread.length;
        document.getElementById('nmpStatRead').textContent   = readN;
        document.getElementById('nmpBadgeAll').textContent    = all.length;
        document.getElementById('nmpBadgeUnread').textContent = unread.length;
        document.getElementById('nmpBadgeRead').textContent   = readN;
        if (!all.length) {
            document.getElementById('nmpList').innerHTML =
                '<div class="p-5 text-center text-muted">' +
                '<i data-lucide="bell-off" style="width:40px;height:40px;opacity:.3;display:block;margin:0 auto 12px"></i>' +
                'You\'re all caught up — no notifications yet.</div>';
            if (window.lucide) lucide.createIcons({ nodes: [document.getElementById('nmpList')] });
        }
    }

    function applyFilter(f) {
        currentFilter = f;
        ['All', 'Unread', 'Read'].forEach(function (n) {
            var btn = document.getElementById('nmpFilter' + n);
            if (btn) btn.className = btn.className.replace('btn-primary', 'btn-soft').replace('btn-soft btn-soft', 'btn-soft');
        });
        var activeId = { all: 'nmpFilterAll', unread: 'nmpFilterUnread', read: 'nmpFilterRead' }[f];
        var activeBtn = document.getElementById(activeId);
        if (activeBtn) activeBtn.className = activeBtn.className.replace('btn-soft', 'btn-primary');

        document.querySelectorAll('.nmp-row').forEach(function (row) {
            var isRead = row.dataset.read === '1';
            var show = f === 'all' || (f === 'unread' && !isRead) || (f === 'read' && isRead);
            row.style.display = show ? '' : 'none';
        });
    }

    window.nmpFilter = applyFilter;

    // Individual delete
    document.addEventListener('click', function (e) {
        var delBtn = e.target.closest('.nmp-del');
        if (delBtn) {
            var id = delBtn.dataset.id;
            api('/api/notifications/' + id + '/delete', function (r) {});
            var row = delBtn.closest('.nmp-row');
            if (row) row.remove();
            recalcStats();
            return;
        }

        var markBtn = e.target.closest('.nmp-mark');
        if (markBtn) {
            var id = markBtn.dataset.id;
            api('/api/notifications/' + id + '/read', function () {});
            var row = markBtn.closest('.nmp-row');
            if (row) {
                row.dataset.read = '1';
                row.classList.remove('nmp-unread');
                row.classList.add('nmp-read');
                // Remove badge and mark button
                var badge = row.querySelector('.badge');
                if (badge) badge.remove();
                markBtn.remove();
                // Grey out title
                var title = row.querySelector('.fw-semibold');
                if (title) title.classList.add('text-muted');
                // Apply current filter
                if (currentFilter === 'unread') row.style.display = 'none';
                if (currentFilter === 'read')   row.style.display = '';
            }
            recalcStats();
            return;
        }
    });

    // Bulk: Mark all read
    var markAllBtn = document.getElementById('nmpMarkAll');
    if (markAllBtn) {
        markAllBtn.addEventListener('click', function () {
            markAllBtn.disabled = true;
            api('/api/notifications/read-all', function () {
                document.querySelectorAll('.nmp-row.nmp-unread').forEach(function (row) {
                    row.dataset.read = '1';
                    row.classList.remove('nmp-unread');
                    row.classList.add('nmp-read');
                    var badge = row.querySelector('.badge');
                    if (badge) badge.remove();
                    var mk = row.querySelector('.nmp-mark');
                    if (mk) mk.remove();
                    var title = row.querySelector('.fw-semibold');
                    if (title) title.classList.add('text-muted');
                    if (currentFilter === 'unread') row.style.display = 'none';
                    if (currentFilter === 'read')   row.style.display = '';
                });
                recalcStats();
                markAllBtn.disabled = false;
            });
        });
    }

    // Bulk: Delete read
    var delReadBtn = document.getElementById('nmpDeleteRead');
    if (delReadBtn) {
        delReadBtn.addEventListener('click', function () {
            if (!confirm('Delete all read notifications?')) return;
            delReadBtn.disabled = true;
            api('/api/notifications/delete-read', function () {
                document.querySelectorAll('.nmp-row.nmp-read').forEach(function (r) { r.remove(); });
                recalcStats();
                delReadBtn.disabled = false;
            });
        });
    }

    // Bulk: Delete all
    var delAllBtn = document.getElementById('nmpDeleteAll');
    if (delAllBtn) {
        delAllBtn.addEventListener('click', function () {
            if (!confirm('Delete ALL notifications? This cannot be undone.')) return;
            delAllBtn.disabled = true;
            api('/api/notifications/delete-all', function () {
                document.querySelectorAll('.nmp-row').forEach(function (r) { r.remove(); });
                recalcStats();
                delAllBtn.disabled = false;
            });
        });
    }

    // Refresh bell count after actions on this page
    if (window.EMS && EMS.refreshNotifications) EMS.refreshNotifications();
})();
</script>
