# Employee Management System — Full Feature List

A Core PHP MVC application (PHP 8.2+, MySQL, PDO, no framework) providing a full HR/attendance/payroll suite across two portals: an **Admin/HR portal** and an **Employee Self-Service portal**, plus a company-wide **Team Chat** and an optional **desktop monitoring agent**.

---

## 1. Authentication & Access

- Email/password login with lockout after repeated failed attempts, "remember me," and forgot/reset password flow
- **SSO login** (OAuth-style redirect/callback) alongside standard credentials
- Two-factor secret field on the user account (infrastructure for 2FA)
- Session-based auth with password-changed invalidation (changing a password kills other active sessions)
- Multi-tenant aware throughout — every query scopes to the signed-in user's company unless they're a super admin

## 2. Roles & Permissions

- Six built-in roles out of the box: Super Admin, Company Admin, HR Manager, Department Manager, Accountant, Employee
- **Create custom roles** (global or scoped to one company), assign them to users, and **delete** custom roles once no users are assigned (built-in roles are protected and can't be deleted)
- Fine-grained permission model (150+ permission keys across every module) with a checkbox matrix per role, grouped by module
- Manager/department-scoped visibility: a Department Manager only sees their own team's data in reports and monitoring, without a separate code path from the company-admin view
- Full audit trail on every permission change

## 3. Organization Setup

- **Companies** — multi-company/tenant support
- **Branches** — with GPS coordinates and a configurable attendance geofence radius per branch
- **Departments** — optional head employee, optional weekly-schedule override (see §5)
- **Designations** — job titles with a numeric seniority level and a "Manager or above" flag that drives weekly-schedule precedence
- Guided **Company Setup wizard** for first-time configuration

## 4. Employee Records

- Full employee CRUD with soft delete/reactivation, profile photo, personal/contact/emergency-contact info, bank details, national ID, employment type & status history
- **Bulk employee import** (CSV/spreadsheet) with validation and batch reporting
- **Onboarding/offboarding workflows** — task-based checklists tracked per employee (start workflow, assign tasks, mark complete)
- **Employment agreements** — templated contract generation the employee reviews and accepts online, with acceptance recorded
- Reporting-manager hierarchy (`reporting_manager_id`) used for approval routing and manager-scoped visibility
- Employee documents and company/personal assets tracked per employee, with expiry reminders

## 5. Attendance — Check-in & Scheduling

- **Live check-in/out** with camera capture and GPS geofencing (Haversine distance against the branch radius), each capture flagged verified/flagged based on distance and GPS accuracy
- **Device/IP security** — configurable per company: device-only, IP-only, both, or disabled; first device auto-approved or admin-approved depending on policy; device-change requests, approval, and revocation, all logged as security events
- **Remote/Work-From-Home** attendance for employees flagged as remote-eligible
- **Shifts** — start/end time, paid breaks, grace period, late-mark threshold, half-day cutoff, overtime threshold, and overnight-shift support (crossing midnight)
- **Shift rosters & date-ranged assignments** — an employee's shift for a specific date resolves through per-date roster → date-ranged assignment → their default shift, in that order
- **Weekly Schedule Patterns** (admin-configurable, full/half/off per weekday) resolved per employee with clear precedence: a "Manager or above" designation gets the company's manager pattern (e.g. weekends off) → otherwise their department's assigned pattern (e.g. a support team with Saturday as a full day and Monday as a half day) → otherwise the company default (e.g. Saturday half day, Sunday off)
- **Auto checkout** after a configurable number of hours if an employee forgets to check out; cron also sweeps missing-checkout records
- **Attendance corrections/regularization** — employee requests a fix to a check-in/out, admin approves (re-runs the same calculation engine used at live check-in, preserving an audit trail) or rejects
- **Manual attendance entry** and CSV export for admins
- Daily attendance calendar (employee-facing) and full attendance list/archive (admin-facing)
- Holidays — company-wide or branch-specific, public/optional/company/restricted types

## 6. Attendance Intelligence & Reporting

A dedicated reporting engine (single source of truth shared by dashboards, exports, and payroll) that never assumes "no check-in = absent" — it checks holiday → approved leave → rest day → then absence, and treats a half-scheduled day (e.g. a half-day Saturday) as half a working-day unit throughout every calculation, not just a display label.

Report tabs (all filterable by date range, branch, department, shift, employee; all export to CSV):

- **Overview** — a color-coded attendance-health hero banner plus grouped KPIs (workforce, time & hours, rates)
- **Employee Summary** — sortable table with every employee's scheduled days, present/absent/leave/late/half-day/holiday/rest-day counts, worked & overtime hours, and an attendance-percentage bar; click a row for the full breakdown
- **Employee Detail** — day-by-day breakdown for one employee showing exactly how each total was calculated, plus a payroll-compatible summary (working/present/absent/paid-leave/unpaid-leave days, required vs. worked hours, calculated vs. approved overtime shown separately) and their attendance-device history
- **Daily Attendance** — a single day's full roster with status, late-by, worked hours
- **Monthly Sheet** — a printable matrix (employee × day-of-month) with color-coded status pills and a legend, built for HR sign-off
- **Department / Branch / Shift** — aggregated attendance percentage and hours per group (factual totals only, no performance ranking)
- **Absence Report** — flags consecutive-absence streaks and cross-references pending leave requests
- **Late Arrivals** — grace-period-aware, with total late employees/occurrences/minutes
- **Early Departures**
- **Overtime** — calculated overtime (from shift rules) shown separately from approved overtime (from the payroll-payable request queue), so a gap between the two is visible rather than silently merged
- **Missing/Exceptions** — incomplete or flagged records, duplicate attendance rows, and pending correction requests in one exception queue
- **Corrections** — the regularization queue with approve/reject actions

## 7. Leave Management

- Configurable **leave types** (paid/unpaid, half-day allowed, min/max days per request, notice period, gender restriction, requires-attachment)
- **Leave balances** per employee per year (opening, accrued, used, pending, carried-forward, adjusted, encashed, closing) with automated monthly accrual
- Leave request submission with automatic weekend/holiday exclusion from chargeable days, half-day support, handover notes and emergency contact
- **Multi-level approval chains** (configurable per company) with an inbox for pending approvals; leave extensions and amendments supported
- Leave calendar and balance views for employees; full analytics for admins (by status, by type, monthly trend, by department)

## 8. Payroll & Compensation

- **Payroll periods** — draft → processing → calculated → approved → paid → locked lifecycle
- **Salary structures** — configurable earning/deduction components (fixed or percentage-of-basic), assignable per employee with an effective date
- Automatic payroll processing per employee: proration for mid-period joiners/leavers, unpaid-leave deduction, loan installment and salary-advance deduction, statutory/tax component calculation
- **Overtime** — a separate approval queue (request → approve/reject) feeding the amount actually paid, decoupled from the raw calculated overtime attendance produces
- **Loans** and **salary advances** with installment schedules, auto-deducted each payroll run
- **Payslip PDF generation** (Dompdf) and self-service payslip access for employees
- Payroll variance reporting (period-over-period changes) and full payroll analytics

## 9. Team Chat

- Channels (public/private) and direct messages, with typing indicators and read receipts
- Message reactions, pinning, editing, deletion (own messages), acknowledgement requests
- File/image attachments with a dedicated files browser
- Mentions, saved messages, per-channel and global search
- Channel membership management (invite, remove, join public channels)
- Configurable chat settings (who can create channels, mass-mention limits, etc.)

## 10. Work Monitoring (optional desktop agent)

- A companion **desktop agent** (separate app in `desktop-agent/`) that registers a device, sends heartbeats, and batches activity data
- Live view of currently-active employees, and per-employee/team activity history
- **Website & application usage tracking** with configurable site-category rules (productive/neutral/distracting/blocked)
- **Screenshot capture** — policy-gated, with authorization/upload/confirm handshake, retention cleanup, and controlled access (never shown to the monitored employee, only authorized viewers)
- Distraction event detection and an employee-facing dispute/appeal flow for flagged activity, reviewed by an admin
- Monitoring-specific audit log and storage-usage reporting

## 11. Documents & Assets

- Company/employee document library with expiry tracking and reminders
- Asset register (company equipment assigned to employees) with assignment history

## 12. Reports Workspace

A general reporting hub (separate from the attendance-specific module in §6) covering:

- Executive overview combining attendance, leave, payroll, workforce, loan, and document metrics with period-over-period comparison
- Leave analytics, payroll analytics (with per-period drill-down), loan analytics, workforce analytics (headcount, new hires, exits, missing-data flags, upcoming birthdays/anniversaries), and document analytics
- A lightweight **report builder** that routes to the right filtered report, and **saved filters** so a user's preferred view persists
- CSV export everywhere; PDF via the browser's print dialog

## 13. Notifications, Announcements & Approvals

- In-app notification bell (leave decisions, document/contract expiry, birthdays, chat mentions, etc.) plus email via configurable SMTP
- Company-wide announcements
- A unified **approval inbox** aggregating everything awaiting the signed-in user's decision (leave, corrections, overtime, device changes, etc.), driven by the same configurable approval-chain engine used for leave

## 14. Administration

- User management (create/deactivate, assign roles, reset access)
- **Audit logs** — who changed what, when, before/after values, across every module
- System settings (attendance security mode, GPS accuracy threshold, image/location retention, currency, date/time format, upload limits, leave accrual rate, etc.)
- Company setup wizard for first-run configuration

## 15. Automation (Cron)

Runs every 15 minutes by default (`cron/run.php`), or any job individually:

| Job | What it does |
|---|---|
| `mark_absences` | Backfills absent/on-leave/holiday/weekend attendance rows for days with no record (supports date-range backfill) |
| `auto_checkout` | Auto-closes attendance left checked-in past the configured hour limit |
| `missing_checkouts` | Flags stale open check-ins as needing review |
| `leave_accrual` | Monthly leave balance accrual |
| `cleanup_attendance_evidence` | Purges attendance images/locations past their retention window |
| `cleanup_monitoring_evidence` | Purges monitoring screenshots/activity/heartbeats past retention |
| `expiry_reminders` | Notifies on upcoming document and contract expiry |
| `birthday_reminders` | Sends birthday/work-anniversary reminders |

## 16. API

A JSON API (`routes/api.php`) backs the live check-in widget, chat, notifications, monitoring desktop agent, and mobile/embedded report views — session-authenticated, CSRF-protected on all mutating endpoints.

## 17. Deployment

- Self-contained web installer (`/install`) or manual `.env` + SQL import
- Packaged for shared hosting / cPanel-style deployment (`dist/`, `CPANEL_DEPLOY.md`) — document root should point at `public/`, with per-folder `.htaccess` protecting everything else
- Works on Apache/LiteSpeed with `mod_rewrite`, or the PHP built-in server for local development

---

*Generated from a full review of the codebase (controllers, routes, navigation config, and database schema) — reflects what is actually implemented, not a roadmap.*
