<!-- Live Time-Clock Widget (shared partial) -->
<div class="tc-widget mb-3" id="tcWidget">
    <div class="tc-header">
        <div class="tc-header-left">
            <span class="tc-status-dot" id="tcDot"></span>
            <div>
                <div class="tc-status-label" id="tcStatusLabel">Loading…</div>
                <div class="tc-status-sub" id="tcStatusSub"></div>
            </div>
        </div>
        <div class="tc-datetime">
            <div class="tc-time-live" id="tcClock"></div>
            <div class="tc-date-live" id="tcDate"></div>
        </div>
    </div>

    <div class="tc-body">
        <div class="tc-ring-wrap">
            <svg class="tc-ring-svg" viewBox="0 0 160 160">
                <circle class="tc-ring-bg" cx="80" cy="80" r="68"/>
                <circle class="tc-ring-fg" cx="80" cy="80" r="68" id="tcRingFg"/>
            </svg>
            <div class="tc-ring-inner">
                <span class="tc-ring-time" id="tcWorkedDisplay">00:00:00</span>
                <span class="tc-ring-label">WORKED TODAY</span>
            </div>
        </div>

        <div class="tc-stats">
            <div class="tc-stat-card">
                <span class="tc-stat-icon" style="background:#1a3a5e"><i data-lucide="clock"></i></span>
                <div>
                    <div class="tc-stat-label">Worked Today</div>
                    <div class="tc-stat-val" id="tcWorkedStat">00:00:00</div>
                </div>
            </div>
            <div class="tc-stat-card">
                <span class="tc-stat-icon" style="background:#3a2a0e"><i data-lucide="coffee"></i></span>
                <div>
                    <div class="tc-stat-label">Break Time</div>
                    <div class="tc-stat-val" id="tcBreakStat">00:00:00</div>
                </div>
            </div>
            <div class="tc-stat-card">
                <span class="tc-stat-icon" style="background:#1a2a4a"><i data-lucide="bar-chart-2"></i></span>
                <div>
                    <div class="tc-stat-label">Sessions</div>
                    <div class="tc-stat-val" id="tcSessions">0</div>
                </div>
            </div>
            <div class="tc-stat-card">
                <span class="tc-stat-icon" style="background:#2a1a3e"><i data-lucide="activity"></i></span>
                <div>
                    <div class="tc-stat-label">Last Activity</div>
                    <div class="tc-stat-val tc-stat-sm" id="tcLastActivity">—</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Working: Check Out (left) + Start Break (right) -->
    <div id="tcBtnsWorking" style="display:none">
        <div class="row g-2 mb-3">
            <div class="col-6">
                <button class="tc-action-btn btn-checkout-soft w-100 mb-0" onclick="tcAction('check_out')">
                    &#x2B73; Check Out
                </button>
            </div>
            <div class="col-6">
                <button class="tc-action-btn btn-startbreak w-100 mb-0" onclick="tcAction('break_start')">
                    &#x23F8; Start Break
                </button>
            </div>
        </div>
    </div>
    <!-- On break: End Break full width -->
    <div id="tcBtnsBreak" style="display:none">
        <button class="tc-action-btn btn-endbreak w-100 mb-3" onclick="tcAction('break_end')">
            &#x25BA; End Break
        </button>
    </div>
    <!-- Checked out: Check In full width + optional undo -->
    <div id="tcBtnsOut">
        <button class="tc-action-btn btn-checkin w-100 mb-2" onclick="tcAction('check_in')">
            Check In
        </button>
        <div id="tcUndoWrap" style="display:none;text-align:center;margin-bottom:.75rem">
            <button class="btn btn-sm btn-soft text-warning" id="tcUndoBtn" onclick="tcAction('undo_checkout')" style="font-size:.78rem;border-color:rgba(245,158,11,.3)">
                &#8635; Undo checkout — continue previous session
            </button>
            <div class="small text-muted mt-1" id="tcUndoLabel" style="font-size:.7rem"></div>
        </div>
    </div>

    <div class="tc-footer">
        <div class="tc-footer-left">
            <span class="fw-bold">Today's Activity</span>
        </div>
        <div class="d-flex gap-3">
            <button class="tc-text-btn" onclick="tcToggleActivity()" id="tcActivityToggle">See Activity</button>
            <a class="tc-text-btn" href="/employee/attendance">My Attendance</a>
        </div>
    </div>

    <div class="tc-timeline" id="tcTimeline" style="display:none"></div>
</div>

<script>
(function () {
    // Guard: only init once (in case partial is included multiple times)
    if (window._tcInitDone) return;
    window._tcInitDone = true;

    const TC_CSRF = '<?= e($csrf ?? csrf_token()) ?>';
    let tcState       = null;
    let tcWorkedBase  = 0;
    let tcBreakBase   = 0;
    let tcFetchedAt   = 0;
    let tcActivityOpen = false;

    function tcFmt(secs) {
        secs = Math.max(0, Math.floor(secs));
        const h = Math.floor(secs / 3600);
        const m = Math.floor((secs % 3600) / 60);
        const s = secs % 60;
        return String(h).padStart(2,'0')+':'+String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');
    }

    function tcUpdateClock() {
        const now = new Date();
        document.getElementById('tcClock').textContent =
            now.toLocaleTimeString('en-US', {hour:'2-digit', minute:'2-digit', second:'2-digit'});
        document.getElementById('tcDate').textContent =
            now.toLocaleDateString('en-US', {weekday:'short', month:'short', day:'numeric'});
    }

    function tcUpdateRing(workedSecs) {
        const r = 68, circ = 2 * Math.PI * r;
        const pct = Math.min(1, workedSecs / (8 * 3600));
        const fg = document.getElementById('tcRingFg');
        if (fg) fg.style.strokeDashoffset = circ - circ * pct;
    }

    function tcApplyStatus(data) {
        tcState      = data.state;
        tcWorkedBase = data.worked_secs;
        tcBreakBase  = data.break_secs;
        tcFetchedAt  = Date.now() / 1000;

        const dot   = document.getElementById('tcDot');
        const label = document.getElementById('tcStatusLabel');
        const sub   = document.getElementById('tcStatusSub');

        document.getElementById('tcBtnsWorking').style.display = data.state === 'checked_in'  ? '' : 'none';
        document.getElementById('tcBtnsBreak').style.display   = data.state === 'on_break'    ? '' : 'none';
        document.getElementById('tcBtnsOut').style.display     = data.state === 'checked_out' ? '' : 'none';
        // Hide undo button by default; the checked_out branch below will show it if eligible
        var _uw = document.getElementById('tcUndoWrap');
        if (_uw && data.state !== 'checked_out') _uw.style.display = 'none';

        dot.className = 'tc-status-dot';
        if (data.state === 'checked_in') {
            dot.classList.add('dot-green');
            label.textContent = 'Working';
            if (data.stale_date) {
                const sd = new Date(data.stale_date + 'T00:00:00');
                const sdStr = sd.toLocaleDateString('en-US', {month:'short', day:'numeric'});
                sub.innerHTML = '<span style="color:#f59e0b;font-weight:600">&#9888; Session from ' + sdStr + ' still open — check out to proceed.</span>';
            } else {
                sub.textContent = data.session_start_display ? 'Checked in at ' + data.session_start_display : 'Work session in progress';
            }
        } else if (data.state === 'on_break') {
            dot.classList.add('dot-yellow');
            label.textContent = 'On Break';
            sub.textContent   = 'Break in progress';
        } else {
            dot.classList.add('dot-gray');
            label.textContent = 'Checked Out';
            sub.textContent   = data.worked_secs > 0 ? "Today's session is complete" : 'Not started yet';

            // Show undo checkout button within the 30-minute grace period
            var undoWrap  = document.getElementById('tcUndoWrap');
            var undoLabel = document.getElementById('tcUndoLabel');
            if (undoWrap && data.can_undo_checkout) {
                var undoMsg = 'Checked out at ' + data.undo_at;
                if (data.undo_date && data.undo_date !== new Date().toISOString().slice(0, 10)) {
                    undoMsg += ' (previous day)';
                }
                undoWrap.style.display  = '';
                if (undoLabel) undoLabel.textContent = undoMsg + ' — undo within 30 min';
            } else if (undoWrap) {
                undoWrap.style.display = 'none';
            }
        }

        document.getElementById('tcSessions').textContent = data.sessions;
        if (data.last_activity) {
            const typeMap = {check_in:'Check In', check_out:'Check Out', break_start:'Break Start', break_end:'Break End'};
            document.getElementById('tcLastActivity').textContent =
                (typeMap[data.last_activity.type] || data.last_activity.type) + ' · ' + data.last_activity.at;
        } else {
            document.getElementById('tcLastActivity').textContent = '—';
        }

        const tl = document.getElementById('tcTimeline');
        if (data.timeline && data.timeline.length) {
            const typeIcon  = {check_in:'●', check_out:'●', break_start:'◐', break_end:'◐'};
            const typeLabel = {check_in:'Check In', check_out:'Check Out', break_start:'Break Start', break_end:'Break End'};
            const typeColor = {check_in:'#22c55e', check_out:'#64748b', break_start:'#f59e0b', break_end:'#f59e0b'};
            tl.innerHTML = data.timeline.map(function(e) {
                const d   = new Date(e.at.replace(' ','T'));
                const t   = d.toLocaleTimeString('en-US', {hour:'numeric', minute:'2-digit', hour12:true});
                const dur = e.label ? ' <span class="tc-tl-dur">'+e.label+'</span>' : '';
                return '<div class="tc-tl-row">'
                     + '<span class="tc-tl-dot" style="color:'+typeColor[e.type]+'">'+typeIcon[e.type]+'</span>'
                     + '<div><div class="tc-tl-name">'+typeLabel[e.type]+dur+'</div></div>'
                     + '<div class="tc-tl-time">'+t+'</div>'
                     + '</div>';
            }).join('');
        } else {
            tl.innerHTML = '<div class="tc-tl-empty">No activity yet today.</div>';
        }

        if (window.lucide) lucide.createIcons();
    }

    function tcTickFrame() {
        const elapsed = Date.now() / 1000 - tcFetchedAt;
        let worked = tcWorkedBase;
        let brk    = tcBreakBase;
        if (tcState === 'checked_in')    worked = tcWorkedBase + elapsed;
        else if (tcState === 'on_break') brk    = tcBreakBase  + elapsed;
        const wd = tcFmt(worked);
        document.getElementById('tcWorkedDisplay').textContent = wd;
        document.getElementById('tcWorkedStat').textContent    = wd;
        document.getElementById('tcBreakStat').textContent     = tcFmt(brk);
        tcUpdateRing(worked);
        tcUpdateClock();
    }

    async function tcFetch() {
        try {
            const r = await fetch('/employee/timeclock/status', {headers:{'X-Requested-With':'XMLHttpRequest'}});
            const j = await r.json();
            if (j.success && j.data) tcApplyStatus(j.data);
        } catch(_) {}
    }

    window.tcAction = async function(action) {
        document.querySelectorAll('#tcBtnsWorking button,#tcBtnsBreak button,#tcBtnsOut button').forEach(function(b){ b.disabled=true; });
        try {
            const fd = new FormData();
            fd.append('action', action);
            fd.append('_csrf', TC_CSRF);
            const r = await fetch('/employee/timeclock/action', {method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'}});
            const j = await r.json();
            if (j.success && j.data) {
                tcApplyStatus(j.data);
            } else {
                alert(j.message || 'Action failed. Please try again.');
            }
        } catch(_) {
            alert('Network error. Please try again.');
        }
        document.querySelectorAll('#tcBtnsWorking button,#tcBtnsBreak button,#tcBtnsOut button').forEach(function(b){ b.disabled=false; });
    };

    window.tcToggleActivity = function() {
        tcActivityOpen = !tcActivityOpen;
        document.getElementById('tcTimeline').style.display = tcActivityOpen ? 'block' : 'none';
        document.getElementById('tcActivityToggle').textContent = tcActivityOpen ? 'Hide Activity' : 'See Activity';
    };

    document.addEventListener('DOMContentLoaded', function() {
        const r = 68, circ = 2 * Math.PI * r;
        const fg = document.getElementById('tcRingFg');
        if (fg) { fg.style.strokeDasharray = circ; fg.style.strokeDashoffset = circ; }
        tcFetch();
        setInterval(tcTickFrame, 1000);
        setInterval(tcFetch, 15000);
        tcUpdateClock();
    });
})();
</script>
