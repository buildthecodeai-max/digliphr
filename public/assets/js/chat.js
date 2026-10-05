(function () {
  'use strict';

  const root = document.getElementById('chatApp');
  if (!root) return;

  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const state = {
    userId: Number(root.dataset.userId || 0),
    pollMs: Math.max(3000, Math.min(10000, Number(root.dataset.poll || 5) * 1000)),
    channelId: Number(root.dataset.channel || 0) || null,
    conversationId: Number(root.dataset.dm || 0) || null,
    threadParentId: null,
    lastId: 0,
    seenIds: new Set(),
    permissions: {},
    channels: [],
    conversations: [],
    members: [],
    messages: [],
    pollInFlight: false,
  };

  // Mutual exclusion: never keep both a channel and a DM selected.
  if (state.channelId && state.conversationId) {
    state.conversationId = null;
  }

  /** Own-message check uses authenticated users.id only (never role / employee_id). */
  function isOwnMessage(m) {
    const uid = Number(state.userId || 0);
    if (!uid || !m) return false;
    return Number(m.user_id) === uid;
  }

  function syncAuthUserId(payload) {
    const uid = Number(payload && payload.user_id);
    if (uid > 0) state.userId = uid;
  }

  const els = {
    channelList: document.getElementById('chatChannelList'),
    discoverList: document.getElementById('chatDiscoverList'),
    dmList: document.getElementById('chatDmList'),
    messages: document.getElementById('chatMessages'),
    threadMessages: document.getElementById('chatThreadMessages'),
    title: document.getElementById('chatTitle'),
    subtitle: document.getElementById('chatSubtitle'),
    composer: document.getElementById('chatComposer'),
    body: document.getElementById('chatBody'),
    file: document.getElementById('chatFile'),
    typing: document.getElementById('chatTyping'),
    pins: document.getElementById('chatPins'),
    thread: document.getElementById('chatThread'),
    threadComposer: document.getElementById('chatThreadComposer'),
    threadBody: document.getElementById('chatThreadBody'),
    backBtn: document.getElementById('chatBackBtn'),
    searchInput: document.getElementById('chatSearchInput'),
    searchOverlay: document.getElementById('chatSearchOverlay'),
    searchResults: document.getElementById('chatSearchResults'),
  };

  function api(url, options = {}) {
    const opts = { credentials: 'same-origin', ...options };
    opts.headers = opts.headers || {};
    if (!(opts.body instanceof FormData)) {
      opts.headers['Content-Type'] = opts.headers['Content-Type'] || 'application/json';
    }
    opts.headers['X-CSRF-TOKEN'] = csrf;
    opts.headers['Accept'] = 'application/json';
    return fetch(url, opts).then(async (r) => {
      const data = await r.json().catch(() => ({}));
      if (!r.ok || data.success === false) {
        throw new Error(data.message || 'Request failed');
      }
      return data;
    });
  }

  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));
  }

  function dayKey(iso) {
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '';
    return d.toISOString().slice(0, 10);
  }

  function fmtTime(iso) {
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '';
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  }

  function setMobile(panel) {
    root.classList.remove('mobile-main', 'mobile-thread');
    if (panel) root.classList.add(panel);
  }

  function isActiveChannel(id) {
    return !!state.channelId && !state.conversationId && state.channelId === Number(id);
  }

  function isActiveDm(id) {
    return !!state.conversationId && !state.channelId && state.conversationId === Number(id);
  }

  function syncUrl() {
    if (location.pathname !== '/chat') return;
    const params = new URLSearchParams();
    if (state.channelId) params.set('channel', String(state.channelId));
    else if (state.conversationId) params.set('dm', String(state.conversationId));
    const next = '/chat' + (params.toString() ? '?' + params.toString() : '');
    if (location.pathname + location.search !== next) {
      history.replaceState({ channelId: state.channelId, conversationId: state.conversationId }, '', next);
    }
    root.dataset.channel = state.channelId ? String(state.channelId) : '0';
    root.dataset.dm = state.conversationId ? String(state.conversationId) : '0';
  }

  function currentChannel() {
    if (!state.channelId) return null;
    return state.channels.find((x) => Number(x.id) === state.channelId) || null;
  }

  function canPostInSelection() {
    if (!state.permissions.post_message && state.permissions.post_message !== undefined) {
      return false;
    }
    if (state.conversationId) return true;
    const c = currentChannel();
    if (!c) return true;
    if (state.permissions.manage_channel) return true;
    return Number(c.can_post) !== 0;
  }

  function updateComposerAccess() {
    const allowed = canPostInSelection();
    const form = els.composer;
    if (!form) return;
    let notice = document.getElementById('chatComposerNotice');
    if (!notice) {
      notice = document.createElement('div');
      notice.id = 'chatComposerNotice';
      notice.className = 'chat-composer-notice text-muted small px-3 pb-2';
      form.parentNode?.insertBefore(notice, form);
    }
    const controls = form.querySelectorAll('textarea, button, input');
    controls.forEach((el) => {
      if (el.id === 'chatFile' && root.dataset.canUpload === '0') return;
      el.disabled = !allowed;
    });
    if (!allowed && (state.channelId || state.conversationId)) {
      notice.hidden = false;
      notice.textContent = 'You have read-only access in this channel. Only admins can post announcements.';
      form.setAttribute('aria-disabled', 'true');
    } else {
      notice.hidden = true;
      notice.textContent = '';
      form.removeAttribute('aria-disabled');
      syncComposerUi(els.body, document.getElementById('chatSendBtn'));
    }
  }

  function renderLists() {
    // Exactly one activeChat highlight: channel XOR DM.
    els.channelList.innerHTML = state.channels.map((c) => {
      const active = isActiveChannel(c.id) ? ' active activeChat' : '';
      const unread = Number(c.unread_count || 0);
      return `<button type="button" class="chat-list-item${active}" data-open-channel="${c.id}">
        <span class="meta"><span class="name"># ${esc(c.name)}</span><span class="hint">${esc(c.channel_type)}</span></span>
        ${unread ? `<span class="chat-badge">${unread > 99 ? '99+' : unread}</span>` : ''}
      </button>`;
    }).join('') || '<div class="px-2 small text-muted">No channels yet</div>';

    const discover = state.discoverable || [];
    els.discoverList.innerHTML = discover.length
      ? `<div class="chat-section-label">Browse</div>` + discover.map((c) =>
        `<button type="button" class="chat-list-item" data-join-channel="${c.id}">
          <span class="meta"><span class="name"># ${esc(c.name)}</span><span class="hint">Join</span></span>
        </button>`).join('')
      : '';

    els.dmList.innerHTML = state.conversations.map((c) => {
      const active = isActiveDm(c.id) ? ' active activeChat' : '';
      const unread = Number(c.unread_count || 0);
      const name = c.display_name || 'DM';
      return `<button type="button" class="chat-list-item${active}" data-open-dm="${c.id}">
        <span class="meta"><span class="name">${esc(name)}</span><span class="hint">Direct</span></span>
        ${unread ? `<span class="chat-badge">${unread > 99 ? '99+' : unread}</span>` : ''}
      </button>`;
    }).join('') || '<div class="px-2 small text-muted">No DMs yet — use Members or + to start one</div>';

    if (window.lucide) lucide.createIcons();
  }

  function attachmentHtml(a) {
    const url = `/files/chat-attachment/${a.id}`;
    if (a.preview_type === 'image') {
      return `<div class="chat-attachment"><a href="${url}" target="_blank" rel="noopener"><img src="${url}" alt="${esc(a.original_filename)}"></a></div>`;
    }
    if (a.preview_type === 'pdf') {
      return `<div class="chat-attachment"><div class="small mb-1">${esc(a.original_filename)}</div><iframe src="${url}"></iframe><a class="small" href="${url}">Download</a></div>`;
    }
    if (a.preview_type === 'text') {
      return `<div class="chat-attachment"><div class="small mb-1">${esc(a.original_filename)}</div><iframe class="text-preview" src="${url}"></iframe><a class="small" href="${url}">Download</a></div>`;
    }
    return `<div class="chat-attachment"><div class="fw-semibold small">${esc(a.original_filename)}</div><div class="small text-muted">${esc(a.mime_type || '')} · preview unavailable</div><a class="small" href="${url}">Download</a></div>`;
  }

  function messageHtml(m) {
    const reactions = (m.reactions || []).map((r) =>
      `<button type="button" class="chat-reaction" data-react="${m.id}" data-emoji="${esc(r.emoji)}">${esc(r.emoji)} ${r.count}</button>`
    ).join('');
    const attachments = (m.attachments || []).map(attachmentHtml).join('');
    const edited = Number(m.is_edited) ? ' · edited' : '';
    const reply = Number(m.reply_count || 0);
    return `<article class="chat-msg" data-msg-id="${m.id}" id="msg-${m.id}">
      <div class="chat-avatar">${esc(m.initials || '?')}</div>
      <div>
        <div class="chat-msg-head">
          <span class="author">${esc(m.user_name || 'User')}</span>
          <span class="time">${esc(fmtTime(m.created_at))}${edited}</span>
          ${Number(m.read_count) ? `<span class="time">· read ${m.read_count}</span>` : ''}
        </div>
        <div class="chat-msg-body">${m.is_deleted ? '<em class="chat-deleted">Message deleted</em>' : (m.body_html || esc(m.body || ''))}</div>
        ${attachments}
        ${reactions ? `<div class="chat-reactions">${reactions}</div>` : ''}
        ${!m.is_deleted ? `<div class="chat-msg-actions">
          <button type="button" data-react="${m.id}" data-emoji="👍">👍</button>
          <button type="button" data-react="${m.id}" data-emoji="❤️">❤️</button>
          <button type="button" data-react="${m.id}" data-emoji="😄">😄</button>
          <button type="button" data-thread="${m.id}">Thread${reply ? ` (${reply})` : ''}</button>
          <button type="button" data-save="${m.id}">${m.is_saved ? 'Unsave' : 'Save'}</button>
          ${state.permissions.pin_message ? `<button type="button" data-pin="${m.id}">${m.is_pinned ? 'Unpin' : 'Pin'}</button>` : ''}
          ${isOwnMessage(m) || state.permissions.delete_any ? `<button type="button" data-edit="${m.id}">Edit</button><button type="button" data-delete="${m.id}">Delete</button>` : ''}
          ${Number(m.requires_acknowledgement) ? `<button type="button" data-ack="${m.id}">Acknowledge</button>` : ''}
        </div>` : ''}
      </div>
    </article>`;
  }

  function rememberMessageIds(list) {
    (list || []).forEach((m) => {
      const id = Number(m.id);
      if (id > 0) {
        state.seenIds.add(id);
        if (id > state.lastId) state.lastId = id;
      }
    });
  }

  function dedupeMessages(list) {
    return (list || []).filter((m) => {
      const id = Number(m.id);
      if (!id || state.seenIds.has(id)) return false;
      if (targetHasMessage(id)) return false;
      return true;
    });
  }

  function targetHasMessage(id) {
    return !!(els.messages && els.messages.querySelector('[data-msg-id="' + id + '"]'));
  }

  function renderMessages(target, list, replace) {
    const rows = replace ? (list || []) : dedupeMessages(list);
    let html = '';
    let lastDay = '';
    rows.forEach((m) => {
      const day = dayKey(m.created_at);
      if (day && day !== lastDay) {
        html += `<div class="chat-date-sep">${esc(day)}</div>`;
        lastDay = day;
      }
      html += messageHtml(m);
    });
    if (replace) {
      state.seenIds = new Set();
      rememberMessageIds(rows);
      target.innerHTML = html || '<div class="text-muted small px-1" data-chat-empty>No messages yet. Say hello.</div>';
    } else if (html) {
      rememberMessageIds(rows);
      // Drop the "no messages" placeholder before appending the first real message.
      const placeholder = target.querySelector('[data-chat-empty]');
      if (placeholder) placeholder.remove();
      target.insertAdjacentHTML('beforeend', html);
    }
    target.scrollTop = target.scrollHeight;
    if (window.lucide) lucide.createIcons();
  }

  function updateHeader() {
    if (state.channelId) {
      const c = currentChannel();
      els.title.textContent = c ? `# ${c.name}` : '# channel';
      els.subtitle.textContent = c ? (c.description || c.channel_type || '') : '';
      if (els.body) els.body.placeholder = c ? `Message #${c.name}` : 'Message #channel';
    } else if (state.conversationId) {
      const c = state.conversations.find((x) => Number(x.id) === state.conversationId);
      const name = c ? (c.display_name || 'Direct message') : 'Direct message';
      els.title.textContent = name;
      els.subtitle.textContent = 'Private conversation';
      if (els.body) els.body.placeholder = `Message ${name}`;
    } else {
      els.title.textContent = 'Select a conversation';
      els.subtitle.textContent = '';
      if (els.body) els.body.placeholder = 'Message…';
    }
    updateComposerAccess();
  }

  function insertAtCursor(textarea, text) {
    if (!textarea) return;
    const start = textarea.selectionStart ?? textarea.value.length;
    const end = textarea.selectionEnd ?? start;
    const before = textarea.value.slice(0, start);
    const after = textarea.value.slice(end);
    textarea.value = before + text + after;
    const pos = start + text.length;
    textarea.focus();
    textarea.setSelectionRange(pos, pos);
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
  }

  function wrapSelection(textarea, before, after) {
    if (!textarea) return;
    const start = textarea.selectionStart ?? 0;
    const end = textarea.selectionEnd ?? start;
    const selected = textarea.value.slice(start, end) || 'text';
    const next = before + selected + after;
    textarea.value = textarea.value.slice(0, start) + next + textarea.value.slice(end);
    textarea.focus();
    textarea.setSelectionRange(start + before.length, start + before.length + selected.length);
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
  }

  function syncComposerUi(textarea, sendBtn) {
    if (!textarea || !sendBtn) return;
    const empty = !textarea.value.trim();
    sendBtn.disabled = empty;
    textarea.style.height = 'auto';
    textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
  }

  async function loadMessages(replace = true) {
    if (!state.channelId && !state.conversationId) return;
    const params = new URLSearchParams();
    if (state.channelId) params.set('channel_id', state.channelId);
    if (state.conversationId) params.set('conversation_id', state.conversationId);
    if (state.threadParentId) params.set('thread_parent_id', state.threadParentId);
    const res = await api('/api/chat/messages?' + params.toString());
    const list = res.data?.messages || [];
    if (!state.threadParentId) {
      state.messages = list;
      if (replace) {
        state.lastId = 0;
        state.seenIds = new Set();
      }
      renderMessages(els.messages, list, replace);
      // Refresh sidebar unread after opening (server marks last_read + chat bell notifs).
      if (window.EMS && typeof EMS.refreshNotifications === 'function') {
        EMS.refreshNotifications();
      }
    } else {
      renderMessages(els.threadMessages, list, true);
    }
  }

  async function openChannel(id) {
    state.channelId = Number(id) || null;
    state.conversationId = null; // clear DM selection
    state.threadParentId = null;
    state.lastId = 0;
    state.seenIds = new Set();
    closeThread();
    syncUrl();
    updateHeader();
    renderLists();
    setMobile('mobile-main');
    await loadMessages(true);
  }

  async function openDm(id) {
    state.conversationId = Number(id) || null;
    state.channelId = null; // clear channel selection
    state.threadParentId = null;
    state.lastId = 0;
    state.seenIds = new Set();
    closeThread();
    syncUrl();
    updateHeader();
    renderLists();
    setMobile('mobile-main');
    await loadMessages(true);
  }

  function closeThread() {
    state.threadParentId = null;
    els.thread.hidden = true;
    root.classList.remove('has-thread', 'mobile-thread');
  }

  async function openThread(id) {
    state.threadParentId = Number(id);
    els.thread.hidden = false;
    root.classList.add('has-thread');
    setMobile('mobile-thread');
    await loadMessages(true);
  }

  async function bootstrap() {
    const res = await api('/api/chat/bootstrap');
    const d = res.data || {};
    state.permissions = d.permissions || {};
    state.channels = d.channels || [];
    state.discoverable = d.discoverable || [];
    state.conversations = d.conversations || [];
    state.members = d.members || [];
    syncAuthUserId(d);
    renderLists();
    updateHeader();

    if (state.channelId) await openChannel(state.channelId);
    else if (state.conversationId) await openDm(state.conversationId);
    else if (state.channels[0]) await openChannel(state.channels[0].id);
    else syncUrl();

    const focusMsg = Number(root.dataset.msg || 0);
    if (focusMsg) {
      const el = document.getElementById('msg-' + focusMsg);
      if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
      root.dataset.msg = '0';
    }
  }

  async function poll() {
    if (!state.channelId && !state.conversationId) return;
    if (state.pollInFlight) return;
    state.pollInFlight = true;
    const params = new URLSearchParams();
    if (state.channelId) params.set('channel_id', state.channelId);
    if (state.conversationId) params.set('conversation_id', state.conversationId);
    if (state.lastId) params.set('after_id', state.lastId);
    if (state.threadParentId) params.set('thread_parent_id', state.threadParentId);
    try {
      const res = await api('/api/chat/poll?' + params.toString());
      const d = res.data || {};
      syncAuthUserId(d);
      state.channels = d.channels || state.channels;
      state.conversations = d.conversations || state.conversations;
      renderLists();
      updateHeader();

      const typing = d.typing || [];
      if (typing.length) {
        els.typing.hidden = false;
        els.typing.textContent = typing.map((t) => t.name).join(', ') + ' typing…';
      } else {
        els.typing.hidden = true;
      }

      const pins = d.pins || [];
      if (pins.length) {
        els.pins.hidden = false;
        els.pins.innerHTML = '<strong>Pinned:</strong> ' + pins.map((p) => esc((p.body || '').slice(0, 80))).join(' · ');
      } else {
        els.pins.hidden = true;
      }

      // Accept messages from any sender role (admin/employee). Filter only by id/dedupe.
      const incoming = (d.messages || []).filter((m) => {
        if (!m || !Number(m.id)) return false;
        // Do NOT drop when sender.role === admin or employee_id is missing.
        return true;
      });
      if (incoming.length && !state.threadParentId) {
        const fresh = dedupeMessages(incoming.filter((m) => Number(m.id) > state.lastId || !state.seenIds.has(Number(m.id))));
        if (fresh.length) {
          renderMessages(els.messages, fresh, false);
        }
      } else if (incoming.length && state.threadParentId) {
        renderMessages(els.threadMessages, incoming, true);
      }
    } catch (_) {
      // ignore transient poll errors
    } finally {
      state.pollInFlight = false;
    }
  }

  async function sendMessage(form, isThread) {
    if (!isThread && !canPostInSelection()) {
      throw new Error('You cannot post in this channel.');
    }
    const fd = new FormData(form);
    // Never send both targets — selection is XOR.
    if (state.channelId) fd.set('channel_id', String(state.channelId));
    else fd.delete('channel_id');
    if (state.conversationId) fd.set('conversation_id', String(state.conversationId));
    else fd.delete('conversation_id');
    if (isThread && state.threadParentId) fd.set('thread_parent_id', String(state.threadParentId));
    if (!isThread && els.file?.files?.[0]) fd.set('file', els.file.files[0]);
    fd.set('_csrf', csrf);

    const res = await api('/api/chat/messages', { method: 'POST', body: fd, headers: {} });
    const msg = res.data?.message;
    form.reset();
    if (els.file) els.file.value = '';
    if (isThread) {
      syncComposerUi(els.threadBody, document.getElementById('chatThreadSendBtn'));
    } else {
      syncComposerUi(els.body, document.getElementById('chatSendBtn'));
    }
    if (msg) {
      if (isThread) {
        await loadMessages(true);
      } else {
        // Optimistic append; dedupe prevents double-render when poll catches up.
        renderMessages(els.messages, [msg], false);
      }
    }
  }

  function memberRowHtml(u) {
    const online = u.online ? '<span class="chat-online-dot" title="Online"></span>' : '';
    const meta = [u.role, u.department, u.branch].filter(Boolean).join(' · ');
    return `<button type="button" class="chat-list-item" data-start-dm-user="${u.id}">
      <span class="meta">
        <span class="name">${online}${esc(u.name || 'User')}</span>
        <span class="hint">${esc(meta || u.email || '')}</span>
      </span>
      <span class="hint">Message</span>
    </button>`;
  }

  function showMembersModal(users, title) {
    const modalEl = document.getElementById('chatModal');
    const titleEl = document.getElementById('chatModalTitle');
    const bodyEl = document.getElementById('chatModalBody');
    const footEl = document.getElementById('chatModalFooter');
    if (!modalEl || !bodyEl) {
      // Fallback when Bootstrap modal markup is missing
      const pick = (users || [])[0];
      if (!pick) return alert('No members found');
      return startDmWithUser(pick.id);
    }
    if (titleEl) titleEl.textContent = title || 'Members';
    if (footEl) footEl.innerHTML = '';
    const list = users || [];
    bodyEl.innerHTML = `
      <input type="search" class="form-control form-control-sm mb-2" id="chatMemberFilter" placeholder="Filter by name or email">
      <div id="chatMemberResults" class="chat-list" style="max-height:360px;overflow:auto">
        ${list.map(memberRowHtml).join('') || '<div class="text-muted small">No members found</div>'}
      </div>`;
    const filter = bodyEl.querySelector('#chatMemberFilter');
    const results = bodyEl.querySelector('#chatMemberResults');
    filter?.addEventListener('input', () => {
      const q = filter.value.trim().toLowerCase();
      const filtered = !q ? list : list.filter((u) =>
        String(u.name || '').toLowerCase().includes(q)
        || String(u.email || '').toLowerCase().includes(q)
        || String(u.department || '').toLowerCase().includes(q)
        || String(u.branch || '').toLowerCase().includes(q)
      );
      results.innerHTML = filtered.map(memberRowHtml).join('') || '<div class="text-muted small">No matches</div>';
    });
    bodyEl.onclick = async (ev) => {
      const btn = ev.target.closest('[data-start-dm-user]');
      if (!btn) return;
      const uid = Number(btn.getAttribute('data-start-dm-user'));
      try {
        await startDmWithUser(uid);
        const inst = window.bootstrap?.Modal?.getOrCreateInstance(modalEl);
        inst?.hide();
      } catch (err) {
        alert(err.message || 'Could not start DM');
      }
    };
    const inst = window.bootstrap?.Modal?.getOrCreateInstance(modalEl);
    if (inst) inst.show();
    else bodyEl.scrollIntoView({ behavior: 'smooth' });
  }

  async function startDmWithUser(userId) {
    const uid = Number(userId);
    if (!uid) throw new Error('Invalid user');
    const dm = await api('/api/chat/dm', {
      method: 'POST',
      body: JSON.stringify({ user_id: uid, _csrf: csrf }),
    });
    const conv = dm.data?.conversation;
    await bootstrap();
    if (conv?.id) await openDm(conv.id);
  }

  async function loadMemberDirectory(q) {
    const url = q
      ? '/api/chat/members?q=' + encodeURIComponent(q)
      : '/api/chat/members';
    const res = await api(url);
    const users = res.data?.members || res.data?.users || [];
    state.members = users;
    return users;
  }

  // Events
  root.addEventListener('click', async (e) => {
    const t = e.target.closest('[data-open-channel],[data-open-dm],[data-join-channel],[data-react],[data-thread],[data-save],[data-pin],[data-edit],[data-delete],[data-ack]');
    if (!t) return;
    try {
      if (t.dataset.openChannel) return openChannel(t.dataset.openChannel);
      if (t.dataset.openDm) return openDm(t.dataset.openDm);
      if (t.dataset.joinChannel) {
        await api('/api/chat/channels/' + t.dataset.joinChannel + '/join', {
          method: 'POST',
          body: JSON.stringify({ _csrf: csrf }),
        });
        await bootstrap();
        return openChannel(t.dataset.joinChannel);
      }
      if (t.dataset.react) {
        await api('/api/chat/messages/' + t.dataset.react + '/react', {
          method: 'POST',
          body: JSON.stringify({ emoji: t.dataset.emoji || '👍', _csrf: csrf }),
        });
        return loadMessages(true);
      }
      if (t.dataset.thread) return openThread(t.dataset.thread);
      if (t.dataset.save) {
        await api('/api/chat/messages/' + t.dataset.save + '/save', {
          method: 'POST', body: JSON.stringify({ _csrf: csrf }),
        });
        return loadMessages(true);
      }
      if (t.dataset.pin) {
        await api('/api/chat/messages/' + t.dataset.pin + '/pin', {
          method: 'POST', body: JSON.stringify({ _csrf: csrf }),
        });
        return loadMessages(true);
      }
      if (t.dataset.delete) {
        if (!confirm('Delete this message?')) return;
        await api('/api/chat/messages/' + t.dataset.delete + '/delete', {
          method: 'POST', body: JSON.stringify({ _csrf: csrf }),
        });
        return loadMessages(true);
      }
      if (t.dataset.edit) {
        const next = prompt('Edit message');
        if (next == null) return;
        await api('/api/chat/messages/' + t.dataset.edit + '/edit', {
          method: 'POST', body: JSON.stringify({ body: next, _csrf: csrf }),
        });
        return loadMessages(true);
      }
      if (t.dataset.ack) {
        await api('/api/chat/messages/' + t.dataset.ack + '/acknowledge', {
          method: 'POST', body: JSON.stringify({ _csrf: csrf }),
        });
        alert('Acknowledged');
      }
    } catch (err) {
      alert(err.message || 'Action failed');
    }
  });

  els.composer?.addEventListener('submit', async (e) => {
    e.preventDefault();
    try { await sendMessage(els.composer, false); }
    catch (err) { alert(err.message || 'Send failed'); }
  });

  els.threadComposer?.addEventListener('submit', async (e) => {
    e.preventDefault();
    try { await sendMessage(els.threadComposer, true); }
    catch (err) { alert(err.message || 'Reply failed'); }
  });

  let typingTimer = null;
  const sendBtn = document.getElementById('chatSendBtn');
  const threadSendBtn = document.getElementById('chatThreadSendBtn');

  els.body?.addEventListener('input', () => {
    syncComposerUi(els.body, sendBtn);
    clearTimeout(typingTimer);
    typingTimer = setTimeout(() => {
      const payload = { _csrf: csrf };
      if (state.channelId) payload.channel_id = state.channelId;
      if (state.conversationId) payload.conversation_id = state.conversationId;
      api('/api/chat/typing', { method: 'POST', body: JSON.stringify(payload) }).catch(() => {});
    }, 400);
  });

  els.threadBody?.addEventListener('input', () => {
    syncComposerUi(els.threadBody, threadSendBtn);
  });

  document.getElementById('chatAttachBtn')?.addEventListener('click', () => {
    if (els.file && !els.file.disabled) els.file.click();
  });

  document.getElementById('chatFormatBtn')?.addEventListener('click', () => {
    wrapSelection(els.body, '*', '*');
  });

  document.getElementById('chatEmojiBtn')?.addEventListener('click', () => {
    insertAtCursor(els.body, '😊');
  });

  document.getElementById('chatMentionBtn')?.addEventListener('click', () => {
    insertAtCursor(els.body, '@');
  });

  document.getElementById('chatThreadEmojiBtn')?.addEventListener('click', () => {
    insertAtCursor(els.threadBody, '😊');
  });

  document.getElementById('chatThreadMentionBtn')?.addEventListener('click', () => {
    insertAtCursor(els.threadBody, '@');
  });

  els.body?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      if (!sendBtn?.disabled) els.composer?.requestSubmit();
    }
  });

  els.threadBody?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      if (!threadSendBtn?.disabled) els.threadComposer?.requestSubmit();
    }
  });

  syncComposerUi(els.body, sendBtn);
  syncComposerUi(els.threadBody, threadSendBtn);

  els.backBtn?.addEventListener('click', () => {
    if (state.threadParentId) {
      closeThread();
      setMobile('mobile-main');
    } else {
      setMobile(null);
    }
  });

  document.getElementById('chatThreadClose')?.addEventListener('click', () => {
    closeThread();
    setMobile('mobile-main');
  });

  document.getElementById('btnCreateChannel')?.addEventListener('click', async () => {
    const name = prompt('Channel name');
    if (!name) return;
    const type = confirm('OK = public, Cancel = private') ? 'public' : 'private';
    try {
      const res = await api('/api/chat/channels', {
        method: 'POST',
        body: JSON.stringify({ name, channel_type: type, _csrf: csrf }),
      });
      await bootstrap();
      if (res.data?.channel?.id) openChannel(res.data.channel.id);
    } catch (err) {
      alert(err.message || 'Could not create channel');
    }
  });

  document.getElementById('btnNewDm')?.addEventListener('click', async () => {
    try {
      const users = await loadMemberDirectory('');
      showMembersModal(users, 'Start a direct message');
    } catch (err) {
      alert(err.message || 'Could not load members');
    }
  });

  document.getElementById('btnMembers')?.addEventListener('click', async () => {
    try {
      let users = state.members;
      if (!users.length) users = await loadMemberDirectory('');
      else {
        // Refresh online flags
        users = await loadMemberDirectory('');
      }
      showMembersModal(users, 'Company members');
    } catch (err) {
      alert(err.message || 'Could not load members');
    }
  });

  let searchTimer = null;
  els.searchInput?.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(async () => {
      const q = els.searchInput.value.trim();
      if (q.length < 2) {
        els.searchOverlay.hidden = true;
        return;
      }
      try {
        const res = await api('/api/chat/search?q=' + encodeURIComponent(q));
        const messages = res.data?.messages || [];
        const files = res.data?.files || [];
        els.searchResults.innerHTML =
          '<div class="mb-2 fw-semibold">Messages</div>' +
          (messages.map((m) => `<div class="mb-2 small"><strong>${esc(m.user_name)}</strong>: ${esc((m.body || '').slice(0, 120))}
            <a href="/chat?${m.channel_id ? 'channel=' + m.channel_id : 'dm=' + m.conversation_id}&msg=${m.id}">Open</a></div>`).join('') || '<div class="text-muted small">None</div>') +
          '<div class="mt-3 mb-2 fw-semibold">Files</div>' +
          (files.map((f) => `<div class="mb-2 small"><a href="/files/chat-attachment/${f.id}">${esc(f.original_filename)}</a></div>`).join('') || '<div class="text-muted small">None</div>');
        els.searchOverlay.hidden = false;
      } catch (_) {}
    }, 300);
  });

  document.getElementById('chatSearchClose')?.addEventListener('click', () => {
    els.searchOverlay.hidden = true;
  });

  bootstrap().then(() => {
    setInterval(poll, state.pollMs);
  }).catch((err) => {
    els.messages.innerHTML = `<div class="text-danger p-3">${esc(err.message || 'Failed to load chat')}</div>`;
  });
})();
