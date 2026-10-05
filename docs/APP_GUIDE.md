# Application Guide

## Purpose

EMS connects HR administration, employee self-service, attendance, leave, payroll, communication, documents, and work-activity controls in one tenant-scoped application.

## User roles

| Role | Primary responsibility |
|---|---|
| Super Admin | Platform administration, companies, users, roles, settings, and audit |
| Company Admin | Company setup, workforce administration, approvals, and reports |
| HR | Employees, leave, attendance corrections, documents, and onboarding |
| Manager | Team approvals, attendance review, and employee activity summaries |
| Accountant | Payroll, salary structures, loans, advances, and payslips |
| Employee | Self-service attendance, leave, payslips, documents, profile, and chat |

Access is permission-based. Controllers enforce permissions server-side; hiding a button does not grant or remove permission.

## Main workspaces

### Dashboard

Shows workforce totals, attendance trends, pending approvals, payroll status, onboarding, and exceptions. Use it as the daily starting point rather than a complete record list.

### People

Manage companies, branches, departments, designations, employees, employee imports, employee accounts, and lifecycle workflows.

### Attendance

Record camera and GPS check-ins, review late/absent records, handle corrections, configure shifts, review overtime, manage holidays, and export reports.

### Leave

Configure leave types and balances, submit and review requests, apply approval chains, manage holidays, and review leave reports.

### Payroll

Create periods, process earnings and deductions, review variance, approve, lock, generate payslips, and manage loans and advances.

### Approvals

One inbox for leave, attendance corrections, overtime, loans, advances, and payroll decisions. Approval chains and due reminders are configurable.

### Team Chat

Channels, direct messages, files, saved messages, mentions, notifications, and acknowledgement-based documents.

### Work Monitoring

Domain-level website activity, time totals, policy categories, distraction alerts, schedules, device state, employee disputes, reports, and access logging. See [WEB_ACTIVITY_MONITORING.md](WEB_ACTIVITY_MONITORING.md) for the collection boundaries.

### Reports

Attendance, leave, payroll, employees, loans, documents, and workforce summaries. Exports require the appropriate permission.

### Administration

Users, roles, audit logs, company setup, approval chains, announcements, chat settings, appearance, and application settings.

## Common workflows

### New company setup

1. Create the company and configure currency/timezone.
2. Add branches and geofencing details.
3. Add departments and designations.
4. Configure shifts, holidays, leave types, and approval chains.
5. Add employees or import a validated CSV.
6. Assign roles and create employee logins.
7. Review payroll structures before processing the first period.

### Payroll period

Create → finalize attendance and leave → process → review variance → approve → lock → generate payslips → record payment.

### Employee onboarding

Start the workflow from the employee record, assign tasks for personal details, access, payroll information, policies, equipment, and manager introduction. Complete tasks progressively; do not mark the workflow complete until required evidence is available.

## Data rules

- All company-owned records must remain scoped to the authenticated company context.
- Deleted records are normally soft-deleted and retained for auditability.
- Financial and attendance calculations use server-side values and database records.
- Private files are served through authorized routes, not direct public storage URLs.
