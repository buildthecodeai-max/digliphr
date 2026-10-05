# cPanel Deployment Guide — Employee Management System

This package is ready for shared hosting / cPanel upload. Composer is **not** required on the server (`vendor/` is included).

**We cannot upload to your hosting for you.** Use the zip in `dist/` and follow the steps below on your own cPanel account.

## Requirements

- PHP **8.2+** (cPanel → Select PHP Version / MultiPHP)
- Extensions: `pdo_mysql`, `openssl`, `mbstring`, `gd`, `fileinfo`, `json`, `curl`
- MySQL 5.7+ / 8.x or MariaDB 10.3+
- `mod_rewrite` enabled (default on most cPanel hosts)
- HTTPS strongly recommended (required for camera + GPS attendance outside localhost)

## Package contents

Upload the zip built as `dist/ems-cpanel-YYYYMMDD.zip`. It includes:

- Application code (`app/`, `config/`, `routes/`, `views/`)
- Front controller (`public/`)
- Composer dependencies (`vendor/`)
- Database schema + seeder
- Web installer (`/install`)
- Security `.htaccess` files for sensitive folders
- Root `.htaccess` (fallback when document root cannot be `public/`)

When the package was built with a MySQL export, it also includes
`database/import.sql`. This is a complete snapshot of the EMS database at
the time the package was created.

---

## Subdomain deployment (recommended)

Example: `app.example.com` or `ems.example.com`

### 1. Create the subdomain

1. In cPanel → **Domains** (or **Subdomains**)
2. Create a subdomain, e.g. `ems` → `ems.example.com`
3. Note the folder cPanel creates (often `/home/USER/ems.example.com` or `/home/USER/public_html/ems`)

### 2. Create the MySQL database

1. cPanel → **MySQL® Databases**
2. Create a database (example: `cpaneluser_ems`)
3. Create a user + strong password
4. Add the user to the database with **ALL PRIVILEGES**
5. Copy the **full** database name and username (cPanel prefixes them with your account name)

### 3. Upload and extract

1. Upload `ems-cpanel-YYYYMMDD.zip` via File Manager or FTP into the subdomain folder (or a temporary folder)
2. Extract the zip
3. Move the **inner** package contents so the subdomain folder contains:
   - `app/`, `public/`, `vendor/`, `config/`, `database/`, `install/`, `storage/`, `routes/`, `views/`, `.htaccess`, etc.
4. Avoid an extra nesting level like `ems.example.com/ems-cpanel-20260727/...` — the app folders should sit directly under the subdomain path you will use

### 4. Point document root at `public/` (critical)

In cPanel → **Domains** / **Subdomains**, edit the subdomain and set **Document Root** to the package’s `public` folder, for example:

```text
/home/USER/ems.example.com/public
```

or

```text
/home/USER/public_html/ems/public
```

The browser should hit `public/index.php`, not the project root. This keeps `app/`, `config/`, `.env`, and `storage/` outside the web root.

### 5. Permissions

Make `storage/` (and its subfolders) writable by PHP:

- File Manager → select `storage` → Permissions → `0755` or `0775`
- Recurse into subfolders if your host allows
- If uploads or logs fail later, try `0775` / `0777` only as needed (prefer the least permissive option that works)

Also ensure `install/` is writable during setup (the installer writes `installed.lock`).

### 6. Run the installer

1. Open `https://ems.example.com/install` (use your real subdomain)
2. Enter:
   - **App URL** — exact live URL, e.g. `https://ems.example.com` (no trailing slash)
   - **DB host** — usually `localhost`
   - **DB name / user / password** — the full cPanel values from step 2
   - Admin name, email, and password (or accept defaults and change immediately after login)
3. Submit and wait for success
4. Login, then change the Super Admin password

Default seed credentials (if you did not override them):

- Email: `admin@example.com`
- Password: `Admin@123`

### Install from the included MySQL export

Use this path only when the package includes `database/import.sql` and you
want to restore the existing EMS data, users, settings, and reports.

1. Open cPanel → **phpMyAdmin**, select the empty database created above, and
   import `database/import.sql` from the extracted package.
2. Copy `.env.production.example` to `.env`, then enter the exact cPanel
   database name, username, password, and live HTTPS URL. Keep
   `APP_ENV=production`, `APP_DEBUG=false`, and `SESSION_SECURE=true`.
3. Create the empty file `install/installed.lock` so the web installer does
   not overwrite the imported database.
4. Open the application URL and sign in using the accounts from the imported
   database. Do not run `/install` after importing this full database dump.

The deployment archive deliberately excludes local logs, active sessions,
and uploaded employee files. Upload those files separately only if they are
needed and you are authorized to transfer them.

### 7. HTTPS (optional but recommended)

- Enable AutoSSL / Let’s Encrypt for the subdomain in cPanel
- Camera + GPS attendance typically **requires HTTPS** on non-localhost hosts
- Set `APP_URL` to the `https://` URL and `SESSION_SECURE=true` in production

---

## Alternate: document root cannot be changed

If the host forces the subdomain root (no `public/` document root):

1. Extract the **full** package into the subdomain document root
2. Keep the included root `.htaccess` (it rewrites requests into `public/`)
3. Visit `https://ems.example.com/install`
4. Sensitive folders (`app`, `config`, `database`, `storage`, `vendor`) are blocked by per-folder `.htaccess`

This works on most Apache/cPanel hosts. Prefer pointing document root at `public/` when possible.

---

## Main domain (`public_html`) setup

Same as subdomain, but extract under `public_html` (or a subfolder) and set document root to `.../public`, or use the root `.htaccess` fallback above.

---

## Manual install (optional)

If the web installer fails:

1. Copy `.env.production.example` → `.env`
2. Edit DB + `APP_URL` + set a random `APP_KEY`
3. Import `database/schema.sql` in phpMyAdmin  
   - Remove or ignore `CREATE DATABASE` / `USE` lines if the DB already exists
4. If you have SSH:
   ```bash
   php database/seeds/DatabaseSeeder.php
   ```
5. Create empty file: `install/installed.lock`

---

## Cron (recommended)

cPanel → **Cron Jobs** → every 15 minutes:

```bash
/usr/local/bin/php /home/USER/path/to/ems/cron/run.php >> /home/USER/path/to/ems/storage/logs/cron.log 2>&1
```

Adjust the PHP path using cPanel’s “Select PHP Version” / MultiPHP if needed.

---

## After install checklist

- [ ] Document root is `public/` (or root rewrite is working)
- [ ] Force HTTPS / SSL (AutoSSL or Let’s Encrypt)
- [ ] Change Super Admin password
- [ ] Set company / branch GPS coordinates for attendance geofencing
- [ ] Configure SMTP in `.env` (or Settings) for password reset / notifications
- [ ] Confirm camera attendance works over HTTPS
- [ ] Installer locked (`install/installed.lock` exists)

---

## Troubleshooting

| Issue | Fix |
|-------|-----|
| 500 error after upload | Set PHP to 8.2+, enable required extensions, check `storage/logs` |
| `/install` loops or blank | Ensure `vendor/` uploaded; document root points to `public` or root `.htaccess` exists |
| DB connection failed | Use cPanel full DB name/user (`cpaneluser_dbname`), host `localhost` |
| CSS/JS missing | Confirm `APP_URL` matches the live HTTPS URL (including subdomain) |
| Camera / GPS blocked | Site must be served over HTTPS |
| Permission denied writing files | `chmod -R 775 storage` (or ownership matching the web user) |
| Nested extract path | Move contents up so `public/` is directly under the path you configured |

---

## Security notes

- Do **not** upload your local `.env` with debug credentials
- Keep `APP_DEBUG=false` in production
- Do not expose `storage/`, `vendor/`, or `.env` publicly
- Prefer document root = `public/` so application code is not web-accessible

---

## Rebuild this package

From the project root (on a machine with Composer):

```bash
composer install --no-dev --optimize-autoloader
bash scripts/build-cpanel.sh
```

The zip is written to `dist/ems-cpanel-YYYYMMDD.zip`.
