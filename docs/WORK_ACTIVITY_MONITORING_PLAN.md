# Work Activity Monitoring — Implementation Plan

**Status:** Implemented (Mode 2 MVP — continuous recording deferred)  
**Default mode:** Mode 2 — Activity Tracking + Periodic Screenshots  
**Delivery scope:** Server + API + Admin/Employee UI first; Electron Windows agent scaffold  
**Continuous / exceptional recording:** Deferred (must never be silent if later enabled)

---

## Locked decisions

| Item | Choice |
|------|--------|
| Default monitoring | Activity + periodic screenshots (not continuous recording) |
| Default interval | 10 minutes (admin: 5 / 10 / 15 / 20 / 30; enforce security minimum) |
| Lifecycle | Starts after successful check-in; stops after checkout |
| Agent stack | Electron (Windows-first scaffold) |
| This ship | API + DB + Admin UI + Employee UI + agent scaffold |
| Exceptional recording | Deferred; stronger visible indicator required if ever enabled |

---

## REVISED TRANSPARENCY REQUIREMENT

### Principle

Monitoring must remain **disclosed**, but must **not** interrupt the employee with routine popups.

### Forbidden (never show for routine activity)

Employees must **not** receive:

- Screenshot countdown popups
- Screenshot-captured notifications
- Repeated monitoring reminders
- Upload-success notifications
- Activity-tracking notifications
- Manager-view notifications for ordinary authorized review

### Required minimum transparency

#### 1. One-time policy notice

Before the employee’s **first monitored check-in** (and again when the policy is **first assigned** or **materially changed**), show **one** policy notice explaining:

- Activity tracking during checked-in hours
- Periodic screenshots enabled
- Screenshot interval
- When monitoring starts and stops
- What information is collected
- Who may access it
- Retention duration
- Excluded applications and breaks

Require **one-time acknowledgement**; store employee ID, policy version, timestamp, IP, device, accepted status.

Do **not** re-prompt on every check-in unless the policy version changed materially.

#### 2. Quiet persistent indicator (tray)

After check-in, show **only** a small, non-intrusive system tray indicator:

```text
Monitoring Active
```

- **Do not** display a popup for activation.
- Employee may **open the tray menu** to view:
  - Monitoring status
  - Start time
  - Assigned policy
  - Screenshot interval
  - Last successful synchronization
  - Mode: Activity + Periodic Screenshots

**Tray detail format (when opened):**

```text
Monitoring Active
Mode: Activity + Periodic Screenshots
Started: 9:02 AM
```

**Do not** show `Next screenshot: 9:12 AM` (removed — no countdown / next-capture scheduling UI).

The tray indicator **must remain visible** while monitoring is active. The agent must not hide it.

#### 3. Screenshot capture behavior

When a screenshot is captured:

- Capture **silently** in the background
- Do **not** show a popup
- Do **not** play a sound
- Do **not** interrupt the employee
- Do **not** show a countdown
- Do **not** open a window
- Do **not** move focus from the active application

#### 4. Check-in behavior

```text
Check-in succeeds
→ Monitoring session starts
→ Tray indicator → Monitoring Active
→ No additional popup
```

#### 5. Checkout behavior

```text
Checkout succeeds
→ Screenshot scheduling stops
→ Activity tracking stops
→ Pending uploads complete
→ Tray indicator → Monitoring Stopped
```

Do **not** show repeated confirmation popups unless an **error** requires employee action.

#### 6. Exceptional screen recording (deferred)

If/when enabled later, must show a **stronger** visible indicator:

```text
Screen Recording Active
```

Exceptional recording must **never** run silently.

#### 7. Errors that may notify the employee

Only show an employee-facing alert when **action is required**:

- Monitoring agent cannot authenticate
- Device is not approved
- Policy acknowledgement is required
- Local encrypted storage is full
- Monitoring failed to stop after checkout
- Agent requires an update
- Screenshot capture repeatedly fails

Routine monitoring stays quiet.

---

## Attendance integration

Hook **after** `AttendanceService::checkIn` / `checkOut` DB `commit()` so GPS attendance never rolls back on monitoring failure.

- Check-in → resolve policy → require ack if needed → require approved device → create session + short-lived token → agent tray Active  
- Checkout → stop capture/tracking → flush queue → complete session → revoke token → tray Stopped  
- Admin force-checkout → agent learns via heartbeat `stop` command  

---

## Data / API / UI (summary)

- Tables: `monitoring_*` (policies, assignments, acknowledgements, devices, sessions, activity segments, screenshots, heartbeats, alerts, storage, agent versions)
- Private storage: `storage/monitoring/company-{id}/...`
- Protected serve: `FileController` type `monitoring-screenshot` + audit on view
- Admin workspace tabs: Overview, Live, Activity, Applications, Screenshots, Devices, Policies, Alerts, Reports, Audit
- Employee: My Work Activity (Today, Activity, Screenshots if allowed, Devices, Policy, Acknowledgements)
- Permissions: `monitoring.*` as specified; manager scope enforced in queries
- Cron: retention cleanup (screenshots ~30d, activity ~90d defaults)
- Electron: quiet tray + silent capture scaffold wired to APIs

---

## Explicitly out of this ship

- Continuous recording productization  
- Keylogging, webcam, microphone  
- Public screenshot URLs  
- Productivity scores from screenshots/mouse  
- Recurring transparency popups (forbidden by revised policy)

---

## Execute when ready

Reply **implement** / **go ahead** to build against this document.
