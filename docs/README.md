# Employee Management System Documentation

This folder contains the product, technical, security, and operations documentation for the Employee Management System (EMS).

## Start here

- [Application guide](APP_GUIDE.md) — product areas, roles, and common workflows
- [Setup and deployment](SETUP.md) — local installation, hosting, database, and cron
- [Security guide](SECURITY.md) — authentication, tenant isolation, privacy, and audit controls
- [UI guidelines](UI_GUIDELINES.md) — theme, spacing, components, responsive behavior, and accessibility
- [API reference](API_REFERENCE.md) — public health, authenticated APIs, chat, reports, attendance, and monitoring
- [Operations runbook](operations.md) — migrations, backups, restore testing, and monitoring

## Module documentation

- [Attendance](ATTENDANCE.md)
- [Leave](LEAVE.md)
- [Payroll](PAYROLL.md)
- [Website activity monitoring](WEB_ACTIVITY_MONITORING.md)
- [Work activity monitoring plan](WORK_ACTIVITY_MONITORING_PLAN.md)

## Source of truth

Routes live in `routes/web.php` and `routes/api.php`. Permissions live in `config/permissions.php`. Database structure and migrations live in `database/`. The application is Core PHP MVC with PHP templates rather than a separate frontend framework.
