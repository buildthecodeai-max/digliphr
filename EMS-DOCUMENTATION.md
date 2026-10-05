# Employee Management System (EMS) — Documentation

> PHP 8.2+ · MySQL 8 · Bootstrap 5 · cPanel / Hostinger Ready · PKR Currency

---

## Table of Contents

1. [Overview](#overview)
2. [Server Requirements](#server-requirements)
3. [Installation](#installation)
4. [Default Credentials](#default-credentials)
5. [Admin Portal](#admin-portal)
   - [Dashboard](#dashboard)
   - [Employees](#employees)
   - [Attendance](#attendance)
   - [Leave Management](#leave-management)
   - [Payroll](#payroll)
   - [Reports](#reports)
   - [Organization Structure](#organization-structure)
   - [Communication](#communication)
6. [Employee Portal](#employee-portal)
7. [Roles & Permissions](#roles--permissions)
8. [Cron Jobs](#cron-jobs)
9. [Environment Configuration](#environment-configuration)
10. [Troubleshooting](#troubleshooting)

---

## Overview

The Employee Management System (EMS) is a full-featured, self-hosted HR platform. It provides two portals — an **Admin Portal** for HR managers and supervisors, and an **Employee Portal** for individual staff members — each with role-based access control.

EMS is designed for small to mid-sized organizations and ships as a single deployable PHP package with no external runtime dependencies beyond a web server, PHP, and MySQL.

**Key features:**
- Employee lifecycle management (onboarding, profiles, documents, offboarding)
- Attendance tracking with camera check-in, GPS geofencing, and device integration
- Leave management with custom types and multi-level approval chains
- Payroll engine with salary structures, deductions, loans, advances, and payslips
- Analytics and reports across 6 modules with CSV export
- Built-in team chat (direct messages and channels)
- Role-based access control with granular permission flags

---

## Server Requirements

| Requirement | Minimum |
|-------------|---------|
| PHP | 8.2 or higher |
| MySQL | 5.7+ / MySQL 8 / MariaDB 10.3+ |
| PHP Extensions | pdo_mysql, openssl, mbstring, gd, fileinfo, json, curl |
| Web Server | Apache (mod_rewrite) or LiteSpeed (rewrite enabled) |
| HTTPS | Strongly recommended (required for camera & GPS attendance) |
| Storage | `storage/` directory must be writable by PHP (chmod 755) |

> **Warning:** Camera-based check-in and GPS attendance will not work on HTTP. Enable SSL (Let's Encrypt / AutoSSL) on your subdomain before going live.

---

## Installation

### Option A — Fresh install (recommended)

**Step 1 — Create a MySQL database**

In Hostinger hPanel → **Databases** → **MySQL Databases**. Create a database (e.g. `u123456_ems`), a user, and grant that user *All Privileges*. Note the full DB name, username, and password — cPanel/hPanel prefixes both with your account name.

**Step 2 — Create a subdomain & set document root**

hPanel → **Domains** → add a subdomain such as `ems.yourdomain.com`. Set its **Document Root** to end in `/public`, for example:
```
/home/u123456/public_html/ems/public
```

**Step 3 — Upload and extract the zip**

Via File Manager or FTP, upload `ems-cpanel-YYYYMMDD.zip` into the subdomain folder and extract it. Move all files up one level so `public/`, `app/`, `vendor/` etc. sit directly under the subdomain path — not nested inside an extra folder.

**Step 4 — Set storage permissions**

File Manager → select `storage/` → Permissions → **755**, recurse into subfolders. Do the same for `install/`.

**Step 5 — Run the web installer**

Open `https://ems.yourdomain.com/install` in your browser. Enter your App URL, DB credentials, and admin account details. Submit and wait for success.

**Step 6 — Enable SSL & change the admin password**

hPanel → **SSL** → enable **Free SSL** for the subdomain. Then login to EMS and change the Super Admin password immediately.

---

### Option B — Restore existing data

If the package was built with a database export (`database/import.sql`):

**Step 1 — Import the SQL dump**

hPanel → **phpMyAdmin** → select the empty database → **Import** → choose `database/import.sql`.

**Step 2 — Configure the .env file**

Copy `.env.production.example` to `.env`. Set `APP_URL`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`. Keep `APP_DEBUG=false` and `SESSION_SECURE=true`.

**Step 3 — Lock the installer**

Create an empty file at `install/installed.lock` to prevent the web installer from overwriting the imported database. Then open your site URL and log in.

> **Note:** No Composer needed. The `vendor/` folder is bundled inside the zip — you do not need to run any build command on the server.

---

## Default Credentials

> **Change these immediately after first login.**

| Field | Value |
|-------|-------|
| Email | `admin@example.com` |
| Password | `Admin@123` |
| Role | Super Admin |
| Portal | `/admin/dashboard` |

> **Security:** These are seeded defaults. Change the password on first login and set a real `APP_KEY` in production. Never set `APP_DEBUG=true` on a live server.

---

## Admin Portal

### Dashboard

The admin dashboard gives a real-time overview of the workforce. It loads automatically on login for accounts with the `dashboard.view` permission.

- Metric tiles — total employees, present today, pending approvals, latest payroll total
- 7-day attendance trend chart (line) and department headcount chart (bar)
- Attendance exception list — late, absent, missing punch entries
- Approval inbox — pending leave, attendance, overtime, loan, payroll requests
- Onboarding next 30 days — upcoming starters with joining date
- Recent payroll periods with status badges

---

### Employees

Central management of all staff records covering the full employment lifecycle.

| Action | Description |
|--------|-------------|
| Create employee | Name, department, designation, branch, shift, joining date, contract end, salary structure |
| Documents | Upload, categorize and track expiry of employee documents (CNIC, passport, contracts, certificates) |
| Employment agreements | Generate and collect digital signatures for employment contracts |
| Bulk import | Upload employees via CSV with column mapping and error preview |
| Export | Download the full employee list as CSV for external tools or audits |
| Workflow | Manage probation to permanent transitions, contract renewals, terminations |
| Assets | Track company equipment assigned to each employee (laptops, phones, vehicles) |

**Organization hierarchy:** Employees belong to: **Company → Branch → Department → Designation**. Each level is managed separately under the Administration section.

---

### Attendance

EMS supports multiple methods for recording daily attendance, from self-service camera check-in to manual entry and external device integration.

#### Check-in methods

| Method | Description |
|--------|-------------|
| Camera Check-in | Employees take a selfie directly in the browser. Requires HTTPS. Photos stored in `storage/attendance-images/`. |
| GPS Geofencing | Optional location capture. Admin sets coordinates and radius per branch. Out-of-fence check-ins are flagged automatically. |
| Manual Entry | HR enters check-in/check-out times on behalf of an employee. Requires `attendance.manual` permission. |
| Device Integration | Connect biometric/RFID devices. Devices are approved in the admin panel before their punches are accepted. |

#### Shift management

Shifts define start/end times, grace periods, and overtime thresholds. **Weekly schedule patterns** let you assign different shifts to different days of the week per employee or department. Shifts are assigned individually or in bulk.

#### Attendance workflow

1. Employee checks in via camera, GPS, manual, or device punch
2. System evaluates the record → marks as Present, Late, Absent, Half-Day, Early Departure, or Missing based on assigned shift
3. Admin reviews exceptions in Attendance → Missing/Exceptions; HR can correct, approve, or reject
4. Data flows to payroll — present days, overtime hours, and deductions feed directly into payroll calculation

#### Reports available

Overview · Employee Summary · Daily Attendance · Monthly Sheet · Department · Branch · Shift · Absence · Late Arrivals · Early Departures · Overtime · Missing/Exceptions · Corrections

---

### Leave Management

Covers leave type configuration, balance allocation, employee requests, and multi-level approval.

#### Leave types

Create unlimited custom leave types (Annual, Sick, Casual, Maternity, Unpaid, etc.). Each type has its own accrual rules, carry-forward policy, and whether it deducts from payroll.

#### Leave balances

HR allocates leave quotas per employee per leave type per year. Balances are visible to both the employee and their manager. Remaining balance is automatically updated on approval.

#### Approval workflow

1. Employee submits request — selects leave type, dates, and reason from the Employee Portal
2. Request routes through configured approval chain (line manager → HR → director)
3. Final decision — Approved / Rejected; employee notified in-app and by email (if SMTP configured)

#### Additional features

- Extend leave after original approval
- Cancel approved leave (if not yet started)
- Archive / restore leave records
- Holidays calendar — public holidays excluded from leave counts
- Export leave data to CSV

---

### Payroll

The payroll engine calculates net pay from salary structures, attendance deductions, overtime earnings, loan installments, and advances.

#### Payroll lifecycle

| Step | Action |
|------|--------|
| 1. Create period | Define month/year. Employees with active salary structures are included automatically. |
| 2. Process | System calculates gross pay, deductions (late, absent, tax, loans), overtime pay, and net salary. |
| 3. Review & edit | HR can manually adjust individual line items before final approval. |
| 4. Approve & lock | Locks period against further edits. Payslips generated and available to employees. |
| 5. Mark paid | Records disbursement date and closes the period. |

#### Salary structures

Create reusable salary templates with named components (Basic, HRA, Transport, Tax Deduction, EOBI…). Assign a structure to each employee. The payroll engine applies the template and adds attendance-derived adjustments automatically.

#### Loans & Advances

Employees apply for a loan or salary advance through the portal. HR approves the amount and repayment schedule. Monthly installments are automatically deducted from payroll until the balance reaches zero.

---

### Reports

The reporting workspace provides six dedicated report modules, each with sub-sections, filters, graphical charts, and CSV export.

| Module | Sub-sections |
|--------|-------------|
| **Attendance** | Overview, Employee Summary, Daily, Monthly Sheet, Department, Branch, Shift, Absence, Late Arrivals, Early Departures, Overtime, Missing/Exceptions, Corrections |
| **Leave** | Leave summary by employee, type, and department; balance report; pending vs approved breakdown |
| **Payroll** | Period summary, gross vs net trend, deduction breakdown, department totals |
| **Workforce** | Headcount by department/branch, new hires, exits, compliance gaps |
| **Loans** | Active loans, outstanding balances, overdue installments, advance summary |
| **Documents** | Expiring documents, missing documents by category, compliance status |

#### Filters & views

Every report supports filters for date range, branch, department, employee, and shift. Three display modes: **Table** · **Graphical** · **Combined**. Any table view can be exported as CSV.

---

### Organization Structure

| Level | Description | Key fields |
|-------|-------------|------------|
| Company | Top-level legal entity. Holds logo, registration number, contact details. | Name, address, currency, timezone |
| Branch | Physical location within a company. GPS coordinates set here for geofencing. | GPS lat/lng, radius (meters) |
| Department | Functional team within a branch. Approval chains configured at this level. | Manager, leave approval chain |
| Designation | Job title / position. Assigned to employees individually. | Title, level |
| Shift | Work schedule template. Supports flexible times and week patterns. | Start/end, grace, overtime threshold |

---

### Communication

#### Announcements

Broadcast company-wide or department-specific announcements. Announcements appear on the employee dashboard and are retained in a searchable log.

#### Team Chat

Built-in messaging with direct messages and channels. Permissions control who can create channels, invite members, post messages, and delete others' messages. Accessible to any user with the `chat.access` permission.

#### Notifications

In-app notification feed for leave decisions, payroll approval, loan updates, attendance corrections, and system events. Email notifications sent via SMTP when configured in `.env`.

---

## Employee Portal

Every non-admin user lands in the Employee Portal after login. The portal gives staff visibility into their own HR data and the ability to submit requests without contacting HR.

| Feature | Description |
|---------|-------------|
| Dashboard | Personal attendance summary, upcoming shifts, recent leave status, announcements |
| Attendance | Camera check-in/check-out with optional GPS, view own attendance history and monthly sheet |
| Leave | Submit leave requests, view balances, track approval status, cancel pending requests |
| Payslips | View and download monthly payslips after payroll is locked and marked paid |
| Documents | View personal documents uploaded by HR, download copies |
| Employment Agreements | View and digitally sign employment contracts |
| Loans & Advances | Apply for a loan or salary advance, view repayment schedule and outstanding balance |
| Assets | View company equipment assigned to you |
| Profile | Update personal information, profile photo, emergency contacts |
| Team Chat | Direct messages and channels (if `chat.access` permission is granted) |

---

## Roles & Permissions

EMS uses a flat role system where each role holds a set of granular permission flags. A user can hold one role. The **Super Admin** role bypasses all permission checks.

Roles are managed at **Admin → Roles**. Changes take effect on the user's next page load.

| Module | Sample permissions |
|--------|-------------------|
| Employees | view · create · update · delete · import · export |
| Attendance | view · edit · approve · reject · manual · images · locations · correct · archive · device.manage |
| Leave | view · create · approve · reject · extend · cancel · types · balances · export |
| Payroll | view · create · process · approve · lock · reopen · mark_paid · cancel · export |
| Loans / Advances | view · approve · manage |
| Reports | view · export |
| Roles | view · manage |
| Settings | manage |
| Chat | access · direct_message · create_channel · manage_channel · post_message · delete_any_message |
| Audit | view |

---

## Cron Jobs

Several background tasks run on a schedule: contract expiry reminders, document expiry alerts, auto-attendance marking, and notification cleanup. Set up a single cron on the server to handle them all.

### Recommended schedule (every 15 minutes)

```bash
*/15 * * * * /usr/local/bin/php /home/u123456/public_html/ems/cron/run.php >> /home/u123456/public_html/ems/storage/logs/cron.log 2>&1
```

> Replace `/home/u123456/public_html/ems` with your actual server path. Confirm the correct PHP binary path in hPanel's PHP version manager if you get a "command not found" error.

### Available tasks

| Task | Description |
|------|-------------|
| `expiry_reminders` | Sends notifications for contracts ending in the next 30 days |
| `document_expiry` | Alerts HR for employee documents expiring soon |
| `auto_attendance` | Marks employees who did not check in as Absent at end-of-day |
| `cleanup` | Removes old sessions, expired cache, and temporary export files |

---

## Environment Configuration

All runtime settings live in the `.env` file at the project root. Never commit this file to version control. A production template is included as `.env.production.example`.

```env
# Application
APP_NAME="Employee Management System"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ems.yourdomain.com
APP_KEY=your-random-32-char-key
APP_TIMEZONE=Asia/Karachi

# Database
DB_HOST=127.0.0.1
DB_DATABASE=u123456_ems
DB_USERNAME=u123456_ems
DB_PASSWORD=your-db-password

# Session
SESSION_SECURE=true
SESSION_LIFETIME=120

# Mail (optional)
MAIL_HOST=mail.yourdomain.com
MAIL_PORT=587
MAIL_USERNAME=noreply@yourdomain.com
MAIL_PASSWORD=your-smtp-password
MAIL_ENCRYPTION=tls

# Attendance
ATTENDANCE_DEFAULT_RADIUS=100
CURRENCY=PKR
```

> **Tip:** Generate a secure `APP_KEY` with: `openssl rand -base64 32`

---

## Troubleshooting

| Issue | Fix |
|-------|-----|
| 500 error after upload | Set PHP to 8.2+, enable all required extensions (pdo_mysql, gd, mbstring, openssl), check `storage/logs/` for the actual error message. |
| /install loops or shows blank | Confirm `vendor/` was fully uploaded. Ensure document root points to `public/` or that the root `.htaccess` is in place. |
| DB connection failed | Use the full cPanel-prefixed DB name and username (e.g. `u123456_ems`), host `127.0.0.1`. |
| CSS / JS missing on live site | Ensure `APP_URL` in `.env` exactly matches the live HTTPS URL (including subdomain). Clear browser cache. |
| Camera / GPS blocked | Site must be served over HTTPS. Enable Free SSL in hPanel and update `APP_URL`. |
| Permission denied writing files | Set `storage/` and `install/` to `755` (or `775`) recursively in File Manager. |
| Nested extract path | After extracting the zip, move all contents up one level so `public/` sits directly inside your subdomain folder. |
| Cron not running | Confirm the PHP binary path with `which php` in SSH or check hPanel's PHP version manager. Test the command manually first. |
| Email not sent | Verify SMTP credentials in `.env`. Use port 587 with TLS or 465 with SSL. |
| Blank page after login | Check `storage/logs/app.log` for PHP errors. Common cause: missing extension or incorrect `APP_KEY`. |

> **Debug tip:** Enable `APP_DEBUG=true` temporarily to see error messages on screen, then turn it off immediately once resolved. Never leave debug mode on in production.

---

*Employee Management System — buildthecode.ai@gmail.com*
