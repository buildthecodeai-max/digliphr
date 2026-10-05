# EMS Work Monitoring Agent (Mode 2)

Windows-first Electron scaffold for **Activity Tracking + Periodic Screenshots**.

## Transparency (locked)

- Quiet tray only: **Monitoring Active** / **Monitoring Stopped**
- Tray menu shows Mode, Started, interval, last sync — **no** “Next screenshot”
- Screenshots capture **silently** (no popup, sound, countdown, or focus steal)
- One-time policy acknowledgement via desktop agent dialog only (not the employee web portal)
- Employee alerts only when action is required (link to employee dashboard; ack/review via agent)

Exceptional continuous recording is **not** implemented and must never be silent if added later.

## Requirements

- Node.js 18+
- Windows recommended for screenshot + tray testing
- Running EMS server with monitoring migration applied

## Setup

```bash
cd desktop-agent
npm install
```

Copy env example:

```bash
cp .env.example .env
```

Edit `.env`:

```
EMS_API_BASE=http://localhost
EMS_EMAIL=employee@example.com
EMS_PASSWORD=your-password
```

Do **not** commit real credentials.

## Run

```bash
npm start
```

On first launch the agent:

1. Logs in via `POST /api/monitoring/auth/login`
2. Registers the device and stores the Bearer access token locally
3. Fetches policy; if acknowledgement is required, shows a **one-time** modal (not recurring)
4. Heartbeats every 60s
5. After you check in (web or API), call **Start session** from the tray (or it auto-starts when attendance is active)
6. Captures screenshots on the policy interval **silently** and uploads via authorize → upload → confirm

## Tray menu

- Status label
- Mode / Started / Interval / Last sync
- Start session / Stop session
- Open policy notice (only if ack still required)
- Quit

## API endpoints used

- `POST /api/monitoring/auth/login`
- `GET /api/monitoring/policy`
- `POST /api/monitoring/policy/acknowledge`
- `POST /api/monitoring/sessions/start`
- `POST /api/monitoring/sessions/stop`
- `POST /api/monitoring/heartbeat`
- `POST /api/monitoring/activity/batch`
- `POST /api/monitoring/screenshots/authorize`
- `POST /api/monitoring/screenshots/upload`
- `POST /api/monitoring/screenshots/confirm`

All agent calls use `Authorization: Bearer <token>` (device or session token).

## Notes

- Activity window titles on Windows use a lightweight PowerShell probe when available; otherwise a placeholder segment is sent.
- Screenshot module uses `screenshot-desktop` when installed; failures after repeated attempts raise an action-required style tray notification only.
- Local temp captures are deleted after successful confirm.
