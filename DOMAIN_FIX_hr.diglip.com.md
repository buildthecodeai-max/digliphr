# hr.diglip.com — Document Root Fix

## Symptom

Visiting `https://hr.diglip.com/` redirects to `/login`, but `/login` (and
every other page) shows a generic Hostinger "This Page Does Not Exist" error
instead of the application's login screen.

## Root cause

The domain's **document root is set to the project folder** instead of its
`public/` subfolder. This app's front controller lives at `public/index.php`;
anything outside `public/` is application code, not something a browser
should hit directly.

Diagnosis performed on 2026-09-22:

| Request | Result | Meaning |
|---|---|---|
| `GET /` | 302 → `/login` | Reaches the app via `DirectoryIndex index.php` at the project root (works by coincidence, not by routing) |
| `GET /login` | 404 (Hostinger static page, no PHP headers) | Never reaches the app — sub-paths aren't routed to `public/index.php` |
| `GET /public/assets/css/app.css` | 200 | Confirms the document root is the **project root** (the `public/` folder is one level below it) |
| `GET /composer.json` | 200 | Project files are directly web-accessible — should never be reachable from a browser |
| `GET /.env` | 403 | **Not exposed.** DB password, `APP_KEY`, and mail credentials are safe. |
| `GET /database/schema.sql`, `/storage/logs/` | 403 | Also blocked by existing per-folder `.htaccess` protection |

## Fix (Hostinger hPanel)

1. Log into hPanel → **Websites** → `hr.diglip.com` → hosting settings
2. Find **Document Root** (may be under "Advanced" or "PHP Configuration")
3. Change it from the project folder to its `public` subfolder, e.g.:
   ```text
   /domains/hr.diglip.com/public_html          ← current (wrong)
   /domains/hr.diglip.com/public_html/public   ← correct
   ```
4. Save. Takes effect within a minute or two — no code changes needed.

This single change fixes both problems at once:
- Routing starts working (`/login`, `/admin/...`, everything)
- `composer.json` / `README.md` stop being web-accessible, since they're no
  longer under the document root at all

## Verification steps (after changing document root)

```bash
curl -sI https://hr.diglip.com/login
# Expect: HTTP/2 200, with an x-powered-by: PHP header and an EMSSESSID cookie

curl -s -o /dev/null -w "%{http_code}\n" https://hr.diglip.com/composer.json
# Expect: 404 (no longer reachable)
```

## Notes

- This mirrors the guidance already in [CPANEL_DEPLOY.md](CPANEL_DEPLOY.md)
  ("Point document root at `public/` (critical)") — same underlying issue,
  just confirmed live on this specific domain.
- No credentials were exposed during this incident (`.env` returned 403
  throughout), so no rotation of DB password or `APP_KEY` is required.
