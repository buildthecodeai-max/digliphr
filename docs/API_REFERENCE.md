# API Reference

API responses use the application JSON response wrapper. Authenticated browser APIs use the session cookie and CSRF protection for state-changing requests. The desktop monitoring agent uses bearer-token authentication.

## Health

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/api/health` | Safe dependency health response |
| GET | `/health` | Web health response with request correlation |

## Authenticated workforce APIs

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/api/me` | Current user, employee profile, and permissions |
| GET | `/api/notifications` | Current user notifications |
| POST | `/api/notifications/{id}/read` | Mark a notification read |
| GET | `/api/employees/search?q=` | Tenant-scoped employee search |
| GET | `/api/dashboard/stats` | Dashboard statistics |

## Reports

| Method | Endpoint |
|---|---|
| GET | `/api/reports/summary` |
| GET | `/api/reports/attendance` |
| GET | `/api/reports/leave` |
| GET | `/api/reports/payroll` |
| GET | `/api/reports/loans` |
| GET | `/api/reports/workforce` |
| GET | `/api/reports/documents` |

## Attendance

Employee-only endpoints:

- `GET /api/attendance/today`
- `POST /api/attendance/check-in`
- `POST /api/attendance/check-out`
- `POST /api/attendance/corrections`

Check-in/out requests may include live camera evidence, GPS coordinates, accuracy, device information, and client metadata. Server time and server-side geofence calculations are authoritative.

## Team chat

Chat endpoints cover bootstrap, polling, messages, reactions, editing, deletion, pinning, saving, channels, direct messages, members, search, typing status, files, and notifications. See `routes/api.php` for the complete method and path list.

## Monitoring agent

The agent authenticates at `POST /api/monitoring/auth/login`, then uses its bearer token for device registration, policy retrieval, sessions, heartbeat, activity batches, and screenshot authorization/upload/confirmation.

Activity batches are domain-level and policy-controlled. The server returns matched site controls and any approved employee exceptions so the agent can apply warnings or blocking locally.

## Request conventions

- Send JSON for API requests unless an endpoint requires multipart upload.
- Include the CSRF token for cookie-authenticated POST requests.
- Use ISO-compatible server timestamps where accepted.
- Treat validation errors as user-correctable and authorization errors as non-retryable.
- Use `X-Request-ID` from responses when reporting an error to support.
