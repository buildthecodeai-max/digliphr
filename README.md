# Employee Management System (EMS)

Production-oriented **Core PHP MVC** application for employee management, live attendance (camera + GPS), leave, HR operations, and payroll.

## Features

- Admin / HR portal and Employee Self-Service portal
- Role-based permissions (Super Admin, Company Admin, HR, Manager, Accountant, Employee)
- Organization setup: companies, branches, departments, designations
- Employee CRUD with soft delete / reactivation
- Shift management and assignment
- Live attendance with camera capture, GPS geofencing (Haversine), verification flags
- Attendance corrections with audit trail
- Leave types, balances, multi-level approval, leave extensions
- Holiday management
- Payroll periods, earnings/deductions, payslip PDF generation (Dompdf)
- Loans, salary advances, overtime
- Assets, announcements, notifications
- Reports (attendance, leave, payroll, employees)
- Audit logs and system settings
- Cron automation for absences, missing checkouts, accrual, evidence cleanup

## Technology Stack

| Layer | Stack |
|-------|--------|
| Backend | PHP 8.2+, Core PHP MVC (OOP), PDO |
| Database | MySQL 8 |
| Frontend | Bootstrap 5, Vanilla JS, Fetch API, Chart.js |
| Packages | Composer, phpdotenv, PHPMailer, Dompdf |

## Server Requirements

- PHP 8.2+ with extensions: `pdo_mysql`, `openssl`, `mbstring`, `gd`, `fileinfo`, `json`, `curl`
- MySQL 8+
- Apache/Nginx (document root = `public/`) or PHP built-in server
- HTTPS recommended for camera + geolocation (required except localhost)

## Installation

### 1. Install dependencies

```bash
composer install
cp .env.example .env
```

### 2. Configure environment

Edit `.env` with database credentials and `APP_URL`.

### 3. Create database schema + seed

```bash
mysql -u root -p < database/schema.sql
php database/seeds/DatabaseSeeder.php
```

Or use the web installer at `/install`.

### 4. Run the app

```bash
php -S localhost:8000 -t public
```

Open `http://localhost:8000`.

### Default administrator

| Field | Value |
|-------|--------|
| Email | `admin@example.com` |
| Password | `Admin@123` |

Change this password immediately after first login.

## Directory Structure

```text
app/           Controllers, Models, Services, Middleware, Core
config/        app, database, mail, permissions
database/      schema.sql, seeds/
public/        Front controller + assets (web root)
routes/        web.php, api.php
views/         Blade-less PHP templates
storage/       Private uploads, logs, payslips
cron/          Scheduled jobs
install/       Web installer + lock file
```

## Cron Setup

```cron
*/15 * * * * php /path/to/cron/run.php >> /path/to/storage/logs/cron.log 2>&1
```

Individual jobs:

```bash
php cron/run.php mark_absences
php cron/run.php missing_checkouts
php cron/run.php leave_accrual
php cron/run.php cleanup_attendance_evidence
php cron/run.php expiry_reminders
php cron/run.php birthday_reminders
```

## Attendance Camera & GPS

- Uses `navigator.mediaDevices.getUserMedia` (front camera) — gallery upload is not allowed
- Uses browser Geolocation API for lat/lng + accuracy
- Server stores check-in/out time (never trust client clock for payroll calculations)
- Distance to assigned branch is calculated with Haversine; outside-radius and low-accuracy records are flagged
- Attendance images are stored under `storage/attendance-images` and served only through authenticated `/files/...` routes

## Security Notes

- Passwords hashed with `password_hash()`
- CSRF tokens on state-changing requests
- PDO prepared statements
- Permission checks in controllers (not only UI)
- Soft deletes where appropriate
- Audit logging for sensitive actions
- Private file serving after authorization

## Permissions

See `config/permissions.php` for the full matrix. Examples:

- `employees.view|create|update|delete`
- `attendance.view|edit|approve|images`
- `leave.view|approve|extend`
- `payroll.view|process|approve`
- `reports.export`
- `settings.manage`

## Documentation

The complete documentation index is available at **[docs/README.md](docs/README.md)**. Additional docs live under `docs/`:

- Attendance calculation notes
- Leave calculation notes
- Payroll calculation notes
- Route overview

## cPanel / shared hosting

See **[CPANEL_DEPLOY.md](CPANEL_DEPLOY.md)** for upload + installer steps.

Build a deployable zip (includes `vendor/`):

```bash
composer install --no-dev --optimize-autoloader
bash scripts/build-cpanel.sh
```

Output: `dist/ems-cpanel-YYYYMMDD.zip`

## Backup

```bash
mysqldump -u root -p employee_management > backup-$(date +%F).sql
tar -czf storage-backup-$(date +%F).tar.gz storage/
```

## License

Proprietary — internal use.
