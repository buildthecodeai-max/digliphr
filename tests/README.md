# Test strategy

Run `php tests/run.php` for the fast, dependency-free checks used by CI. It lints application, route, view, installer, and operations-tool PHP files, validates attendance JavaScript, and guards the tenant/privacy invariants for payroll, attendance, and monitoring.

Database integration tests should run against a disposable MySQL database in staging or CI. Set `TEST_DB=1` only in that environment; never point integration tests at production. The integration suite should verify:

- an employee from company A cannot be loaded by a company B administrator;
- attendance exports and payroll variance queries never cross company boundaries;
- monitoring screenshots and activity records require both tenant scope and employee authorization;
- reset tokens expire, are single-use, and revoke active sessions after a password change.

Use `php bin/migrate.php --baseline` once for an existing database, then `php bin/migrate.php --up` for subsequent deployments. Fresh installations are seeded by the installer and should be baselined afterward.
