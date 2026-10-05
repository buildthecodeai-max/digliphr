# Operations runbook

## Migrations

Before the first deployment of this version, record the current database state:

```sh
php bin/migrate.php --baseline
```

Future releases apply only new, checksum-verified files:

```sh
php bin/migrate.php --status
php bin/migrate.php --up
```

Use `--dry-run` in deployment checks. Never edit an applied migration; add a new timestamped file.

## Backups and recovery

Create a compressed, transaction-consistent MySQL backup:

```sh
php bin/backup.php
```

Validate a backup by importing it into a temporary database, checking core tables, and removing only that generated test database:

```sh
php bin/restore-test.php storage/backups/employee_management_YYYYMMDD_HHMMSS.sql.gz
```

Schedule the backup command outside the web process using the host scheduler, retain encrypted copies off-host, and perform a restore test at least monthly. The backup directory is intentionally not tracked in source control.

## Monitoring

The `GET /health` endpoint returns only an overall status and safe dependency checks. Use the `X-Request-ID` response header to correlate user reports with `storage/logs/observability-YYYY-MM-DD.log`. Monitoring retention and authorization remain tenant-scoped; keep screenshot storage private and review retention settings regularly.
