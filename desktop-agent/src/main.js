const { app, BrowserWindow, Tray, Menu, nativeImage, Notification, dialog, ipcMain } = require('electron');
const path = require('path');
const fs = require('fs');
const os = require('os');
const crypto = require('crypto');
const { execFile } = require('child_process');
const { promisify } = require('util');
const execFileAsync = promisify(execFile);

const Store = require('electron-store');
const { v4: uuidv4 } = require('uuid');

const store = new Store({ name: 'ems-monitoring-agent' });

// Allow .env override for dev — ignored in packaged builds
function loadDotEnv() {
  const envPath = path.join(__dirname, '..', '.env');
  if (!fs.existsSync(envPath)) return;
  for (const line of fs.readFileSync(envPath, 'utf8').split(/\r?\n/)) {
    const m = line.match(/^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)\s*$/);
    if (!m) continue;
    if (!process.env[m[1]]) process.env[m[1]] = m[2].replace(/^["']|["']$/g, '');
  }
}
if (!app.isPackaged) loadDotEnv();

// Default to Diglip's live server; override via EMS_API_BASE env or stored value
const API_BASE = (
  process.env.EMS_API_BASE ||
  store.get('apiBase') ||
  'https://hr.diglip.com'
).replace(/\/$/, '');

const AGENT_VERSION = '0.1.0';

let tray = null;
let setupWin = null;
let heartbeatTimer = null;
let screenshotTimer = null;
let activityTimer = null;
let monitoringActive = false;
let sessionMeta = null;
let lastSyncAt = null;
let captureFailures = 0;
let ackShownThisRun = false;

// ── IPC handlers for the setup window ──────────────────────────────────────

ipcMain.handle('setup:get-email', () => store.get('email') || '');

ipcMain.handle('setup:login', async (_event, { email, password }) => {
  try {
    store.set('email', email);
    store.set('password', password);
    await loginAndRegister();
    if (setupWin && !setupWin.isDestroyed()) {
      setupWin.close();
      setupWin = null;
    }
    return { success: true };
  } catch (err) {
    store.delete('accessToken');
    return { success: false, message: err.message };
  }
});

// ── Utilities ───────────────────────────────────────────────────────────────

function deviceUid() {
  let uid = store.get('deviceUid');
  if (!uid) {
    uid = `win-${os.hostname()}-${crypto.createHash('sha1').update(os.homedir()).digest('hex').slice(0, 12)}`;
    store.set('deviceUid', uid);
  }
  return uid;
}

async function api(pathname, { method = 'GET', body = null, token = null, formData = null } = {}) {
  const headers = { Accept: 'application/json' };
  const auth = token || store.get('accessToken') || store.get('sessionToken');
  if (auth) headers.Authorization = `Bearer ${auth}`;

  let payload = body;
  if (!formData && body && !(body instanceof Buffer)) {
    headers['Content-Type'] = 'application/json';
    payload = JSON.stringify(body);
  }

  const res = await fetch(`${API_BASE}${pathname}`, {
    method,
    headers: formData ? { Authorization: headers.Authorization, Accept: headers.Accept } : headers,
    body: formData || payload,
  });
  const data = await res.json().catch(() => ({ success: false, message: 'Invalid JSON response' }));
  if (!res.ok || data.success === false) {
    const err = new Error(data.message || `HTTP ${res.status}`);
    err.code = data.code;
    err.data = data.data || data.errors;
    err.status = res.status;
    throw err;
  }
  return data;
}

// ── Tray ────────────────────────────────────────────────────────────────────

function setTrayLabel(label) {
  if (!tray) return;
  tray.setToolTip(label);
  try { tray.setTitle(label); } catch (_) {}
}

function buildMenu() {
  const mode = sessionMeta?.modeLabel || 'Activity + Periodic Screenshots';
  const started = sessionMeta?.started_at
    ? new Date(sessionMeta.started_at).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })
    : '—';
  const interval = sessionMeta?.screenshot_interval_minutes ?? store.get('intervalMinutes') ?? 10;
  const sync = lastSyncAt ? new Date(lastSyncAt).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }) : '—';
  const status = monitoringActive ? 'Monitoring Active' : 'Monitoring Stopped';
  setTrayLabel(status);

  return Menu.buildFromTemplate([
    { label: status, enabled: false },
    { label: `Mode: ${mode}`, enabled: false },
    { label: `Started: ${started}`, enabled: false },
    { label: `Interval: ${interval} min`, enabled: false },
    { label: `Last sync: ${sync}`, enabled: false },
    { type: 'separator' },
    {
      label: 'Start session',
      enabled: !monitoringActive && !!store.get('accessToken'),
      click: () => startSession().catch(notifyActionRequired),
    },
    {
      label: 'Stop session',
      enabled: monitoringActive,
      click: () => stopSession('tray_stop').catch(notifyActionRequired),
    },
    { type: 'separator' },
    {
      label: 'Acknowledge policy…',
      click: () => showPolicyAckIfNeeded(true).catch(notifyActionRequired),
    },
    { type: 'separator' },
    {
      label: 'Sign out',
      click: () => {
        store.delete('accessToken');
        store.delete('sessionToken');
        store.delete('email');
        store.delete('password');
        monitoringActive = false;
        showSetupWindow();
      },
    },
    {
      label: 'Quit',
      click: () => app.quit(),
    },
  ]);
}

function refreshTrayMenu() {
  if (tray) tray.setContextMenu(buildMenu());
}

function notifyActionRequired(err) {
  const message = err?.message || String(err);
  if (Notification.isSupported()) {
    new Notification({ title: 'Diglip Monitor — action required', body: message }).show();
  } else {
    dialog.showMessageBox({ type: 'warning', title: 'Diglip Monitor — action required', message });
  }
}

// ── Setup window ─────────────────────────────────────────────────────────────

function showSetupWindow() {
  if (setupWin && !setupWin.isDestroyed()) {
    setupWin.focus();
    return;
  }
  setupWin = new BrowserWindow({
    width: 420,
    height: 440,
    resizable: false,
    center: true,
    title: 'Diglip Work Monitor — Setup',
    skipTaskbar: false,
    webPreferences: {
      nodeIntegration: true,
      contextIsolation: false,
    },
  });
  setupWin.setMenuBarVisibility(false);
  setupWin.loadFile(path.join(__dirname, 'setup.html'));
  setupWin.on('closed', () => { setupWin = null; });
}

// ── Auth ─────────────────────────────────────────────────────────────────────

async function loginAndRegister() {
  const email = process.env.EMS_EMAIL || store.get('email');
  const password = process.env.EMS_PASSWORD || store.get('password');
  if (!email || !password) {
    throw new Error('No credentials stored. Please sign in.');
  }

  const data = await api('/api/monitoring/auth/login', {
    method: 'POST',
    token: null,
    body: {
      email,
      password,
      device_uid: deviceUid(),
      hostname: os.hostname(),
      os_name: process.platform,
      os_version: os.release(),
      agent_version: AGENT_VERSION,
    },
  });

  const accessToken = data.data?.access_token;
  if (!accessToken) throw new Error('No access token returned from server.');
  store.set('accessToken', accessToken);
  store.set('email', email);
  if (data.data?.policy?.screenshot_interval_minutes) {
    store.set('intervalMinutes', data.data.policy.screenshot_interval_minutes);
  }
  if (data.data?.policy?.acknowledgement_required) {
    await showPolicyAckIfNeeded(true, data.data.policy);
  }
  refreshTrayMenu();
  return data;
}

async function showPolicyAckIfNeeded(force = false, policy = null) {
  if (ackShownThisRun && !force) return;
  let pol = policy;
  if (!pol) {
    const res = await api('/api/monitoring/policy');
    pol = res.data?.policy;
  }
  if (!pol?.acknowledgement_required && !force) return;
  if (!pol?.acknowledgement_required) return;

  ackShownThisRun = true;
  const result = await dialog.showMessageBox({
    type: 'info',
    title: pol.notice_title || 'Work Activity Monitoring',
    message: pol.notice_title || 'Work Activity Monitoring',
    detail: `${pol.notice_text || ''}\n\nScreenshot interval: ${pol.screenshot_interval_minutes} minutes.\nMonitoring starts after check-in and stops after checkout.`,
    buttons: ['I Acknowledge', 'Later'],
    defaultId: 0,
    cancelId: 1,
    noLink: true,
  });
  if (result.response === 0) {
    await api('/api/monitoring/policy/acknowledge', {
      method: 'POST',
      body: { policy_id: pol.id },
    });
  }
}

// ── Session ──────────────────────────────────────────────────────────────────

async function startSession() {
  const res = await api('/api/monitoring/sessions/start', { method: 'POST', body: {} });
  const token = res.data?.session_token;
  if (token) store.set('sessionToken', token);
  sessionMeta = {
    ...(res.data?.session || {}),
    modeLabel: 'Activity + Periodic Screenshots',
    screenshot_interval_minutes: res.data?.screenshot_interval_minutes,
  };
  monitoringActive = true;
  captureFailures = 0;
  scheduleScreenshotLoop(sessionMeta.screenshot_interval_minutes || 10);
  scheduleActivityLoop();
  refreshTrayMenu();
}

async function stopSession(reason = 'agent_stop') {
  try {
    await api('/api/monitoring/sessions/stop', { method: 'POST', body: { reason } });
  } catch (_) {}
  monitoringActive = false;
  sessionMeta = null;
  store.delete('sessionToken');
  clearInterval(screenshotTimer);
  clearInterval(activityTimer);
  screenshotTimer = null;
  activityTimer = null;
  refreshTrayMenu();
}

// ── Screenshot loop ──────────────────────────────────────────────────────────

function scheduleScreenshotLoop(intervalMinutes) {
  clearInterval(screenshotTimer);
  const ms = Math.max(5, Number(intervalMinutes) || 10) * 60 * 1000;
  screenshotTimer = setInterval(() => {
    captureAndUpload().catch(async (err) => {
      captureFailures += 1;
      if (captureFailures >= 3) {
        notifyActionRequired(new Error(`Screenshot capture failed: ${err.message}`));
        captureFailures = 0;
      }
    });
  }, ms);
}

async function captureAndUpload() {
  if (!monitoringActive) return;
  let screenshot;
  try { screenshot = require('screenshot-desktop'); } catch (_) {
    throw new Error('screenshot-desktop module is not available');
  }

  const img = await screenshot({ format: 'jpg' });
  const captureId = uuidv4().replace(/-/g, '');
  const checksum = crypto.createHash('sha256').update(img).digest('hex');
  const tmp = path.join(app.getPath('temp'), `ems-mon-${captureId}.jpg`);
  fs.writeFileSync(tmp, img);

  try {
    await api('/api/monitoring/screenshots/authorize', {
      method: 'POST',
      body: {
        capture_id: captureId,
        captured_at: new Date().toISOString().slice(0, 19).replace('T', ' '),
        checksum,
        mime_type: 'image/jpeg',
        original_size: img.length,
      },
    });

    const form = new FormData();
    form.append('capture_id', captureId);
    form.append('checksum', checksum);
    form.append('mime_type', 'image/jpeg');
    form.append('file', new Blob([img], { type: 'image/jpeg' }), `${captureId}.jpg`);

    await api('/api/monitoring/screenshots/upload', { method: 'POST', formData: form });
    await api('/api/monitoring/screenshots/confirm', {
      method: 'POST',
      body: { capture_id: captureId, checksum },
    });

    captureFailures = 0;
    lastSyncAt = new Date().toISOString();
    refreshTrayMenu();
  } finally {
    try { fs.unlinkSync(tmp); } catch (_) {}
  }
}

// ── Activity loop ─────────────────────────────────────────────────────────────

function scheduleActivityLoop() {
  clearInterval(activityTimer);
  activityTimer = setInterval(() => {
    pushActivitySample().catch(() => {});
  }, 60 * 1000);
}

async function pushActivitySample() {
  if (!monitoringActive) return;
  let appName = 'Desktop';
  let title = 'Active window';
  if (process.platform === 'win32') {
    try {
      const script = `(Get-Process | Where-Object {$_.MainWindowTitle} | Sort-Object CPU -Descending | Select-Object -First 1 | ForEach-Object { $_.ProcessName + '|' + $_.MainWindowTitle })`;
      const { stdout } = await execFileAsync('powershell.exe', ['-NoProfile', '-Command', script], { timeout: 5000 });
      const line = String(stdout || '').trim();
      if (line.includes('|')) {
        const [proc, win] = line.split('|');
        appName = proc || appName;
        title = win || title;
      }
    } catch (_) {}
  }

  const started = new Date(Date.now() - 60_000);
  const ended = new Date();
  await api('/api/monitoring/activity/batch', {
    method: 'POST',
    body: {
      segments: [{
        client_segment_id: uuidv4(),
        application_name: appName,
        process_name: appName,
        window_title: title,
        started_at: started.toISOString().slice(0, 19).replace('T', ' '),
        ended_at: ended.toISOString().slice(0, 19).replace('T', ' '),
        duration_seconds: 60,
        activity_status: 'active',
      }],
    },
  });
  lastSyncAt = new Date().toISOString();
  refreshTrayMenu();
}

// ── Heartbeat ────────────────────────────────────────────────────────────────

async function heartbeat() {
  try {
    if (!store.get('accessToken')) {
      const email = store.get('email');
      const password = store.get('password');
      if (email && password) {
        await loginAndRegister();
      } else {
        showSetupWindow();
        return;
      }
    }
    const res = await api('/api/monitoring/heartbeat', {
      method: 'POST',
      body: {
        status: monitoringActive ? 'active' : 'idle',
        agent_version: AGENT_VERSION,
        platform: process.platform,
        idle_seconds: 0,
      },
    });
    lastSyncAt = new Date().toISOString();
    const trayInfo = res.data?.tray;
    if (trayInfo?.label === 'Monitoring Active' && !monitoringActive) {
      try { await startSession(); } catch (_) {}
    }
    if (trayInfo?.label === 'Monitoring Stopped' && monitoringActive) {
      monitoringActive = false;
      sessionMeta = null;
      clearInterval(screenshotTimer);
      clearInterval(activityTimer);
    }
    for (const cmd of (res.data?.commands || [])) {
      if (cmd.type === 'stop') await stopSession(cmd.reason || 'remote_stop');
      if (cmd.type === 'update_required') notifyActionRequired(new Error(`Agent update required (v${cmd.version}).`));
    }
    refreshTrayMenu();
  } catch (err) {
    if (err.code === 'AUTH_FAILED' || err.status === 401) {
      store.delete('accessToken');
      showSetupWindow();
    } else if (err.code === 'DEVICE_NOT_APPROVED') {
      notifyActionRequired(err);
    } else if (err.code === 'POLICY_ACKNOWLEDGEMENT_REQUIRED') {
      await showPolicyAckIfNeeded(true, err.data).catch(() => notifyActionRequired(err));
    }
  }
}

// ── Tray icon ─────────────────────────────────────────────────────────────────

function createTray() {
  let icon;
  const iconPath = path.join(__dirname, '..', 'assets', 'tray-icon.png');
  if (fs.existsSync(iconPath)) {
    icon = nativeImage.createFromPath(iconPath);
  } else {
    // Fallback: 16×16 navy pixel icon
    const size = 16;
    const buf = Buffer.alloc(size * size * 4, 0);
    for (let y = 0; y < size; y++) {
      for (let x = 0; x < size; x++) {
        const i = (y * size + x) * 4;
        buf[i] = 30; buf[i + 1] = 58; buf[i + 2] = 95; buf[i + 3] = 255;
      }
    }
    icon = nativeImage.createFromBuffer(buf, { width: size, height: size });
  }
  tray = new Tray(icon);
  refreshTrayMenu();
}

// ── App lifecycle ─────────────────────────────────────────────────────────────

app.whenReady().then(async () => {
  // Single-instance lock
  if (!app.requestSingleInstanceLock()) {
    app.quit();
    return;
  }

  // Keep app alive when all windows close
  app.on('window-all-closed', (e) => e?.preventDefault?.());

  // Hidden background window keeps the process alive
  const bg = new BrowserWindow({ show: false, width: 0, height: 0, skipTaskbar: true,
    webPreferences: { nodeIntegration: false } });
  bg.hide();

  createTray();
  setTrayLabel('Monitoring Stopped');

  const email = store.get('email');
  const password = store.get('password');
  if (!email || !password) {
    // First run — show login window
    showSetupWindow();
  } else {
    try {
      await loginAndRegister();
    } catch (err) {
      if (err.code === 'AUTH_FAILED' || err.status === 401) {
        store.delete('accessToken');
        showSetupWindow();
      } else {
        notifyActionRequired(err);
      }
    }
  }

  heartbeatTimer = setInterval(() => heartbeat(), 60_000);
});

app.on('second-instance', () => {
  if (setupWin && !setupWin.isDestroyed()) setupWin.focus();
});

app.on('before-quit', () => {
  clearInterval(heartbeatTimer);
  clearInterval(screenshotTimer);
  clearInterval(activityTimer);
});
