# Setup and Deployment

## Requirements

- PHP 8.2 or newer
- MySQL 8 or newer
- PHP extensions: `pdo_mysql`, `openssl`, `mbstring`, `gd`, `fileinfo`, `json`, and `curl`
- Composer
- HTTPS in production; camera and geolocation require a secure context except on localhost

## Local installation

```bash
composer install
cp .env.example .env
```

Set `APP_URL`, database credentials, timezone, currency, and mail settings in `.env`.

Create and seed the database:

```bash
mysql -u root -p < database/schema.sql
php database/seeds/DatabaseSeeder.php
```

Alternatively, open `/install` and use the web installer.

Run locally:

```bash
php -S localhost:8000 -t public
```

Open [http://localhost:8000](http://localhost:8000).

The seeded development administrator is documented in the project README. Change it immediately after first login.

## Production deployment

1. Set the web root to `public/`.
2. Use a production `.env` with a strong `APP_KEY` and secure database credentials.
3. Enable HTTPS and set `SESSION_SECURE=true`.
4. Keep `storage/` private and writable by the application user only.
5. Run migrations and verify `/health`.
6. Configure cron jobs outside the web process.
7. Test login, tenant isolation, uploads, attendance, payroll, backups, and restore procedures.

## Migrations

```bash
php bin/migrate.php --baseline
php bin/migrate.php --status
php bin/migrate.php --dry-run
php bin/migrate.php --up
```

Never edit an applied migration. Add a new migration with a checksum-tracked change.

## Scheduled jobs

```cron
*/15 * * * * php /path/to/cron/run.php >> /path/to/storage/logs/cron.log 2>&1
```

Available jobs include absence marking, missing checkout handling, leave accrual, evidence cleanup, and expiry reminders.

## Backups

```bash
php bin/backup.php
php bin/restore-test.php storage/backups/employee_management_YYYYMMDD_HHMMSS.sql.gz
```

Keep encrypted copies off-host and perform a restore test at least monthly.
