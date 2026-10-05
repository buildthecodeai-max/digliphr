#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
STAMP="$(date +%Y%m%d)"
OUT_DIR="${ROOT}/dist"
NAME="ems-cpanel-${STAMP}"
STAGE="${OUT_DIR}/${NAME}"
ZIP="${OUT_DIR}/${NAME}.zip"

rm -rf "${STAGE}"
mkdir -p "${STAGE}"

echo "==> Assembling cPanel package in ${STAGE}"

rsync -a \
  --exclude '.git' \
  --exclude '.DS_Store' \
  --exclude '.env' \
  --exclude 'install/installed.lock' \
  --exclude 'storage/logs/*' \
  --exclude 'storage/attendance-images/*' \
  --exclude 'storage/employee-documents/*' \
  --exclude 'storage/employment-agreements/signatures/*' \
  --exclude 'storage/leave-attachments/*' \
  --exclude 'storage/payslips/*' \
  --exclude 'storage/exports/*' \
  --exclude 'storage/framework/sessions/*' \
  --exclude 'storage/framework/cache/*' \
  --exclude 'public/uploads/*' \
  --exclude 'dist' \
  --exclude 'Screenshot*' \
  --exclude 'diglip logo.png' \
  --exclude 'docs' \
  --exclude '.idea' \
  --exclude '.vscode' \
  "${ROOT}/" "${STAGE}/"

# Keep required placeholders
mkdir -p \
  "${STAGE}/storage/logs" \
  "${STAGE}/storage/attendance-images" \
  "${STAGE}/storage/employee-documents" \
  "${STAGE}/storage/employment-agreements/signatures" \
  "${STAGE}/storage/leave-attachments" \
  "${STAGE}/storage/payslips" \
  "${STAGE}/storage/exports" \
  "${STAGE}/storage/framework/sessions" \
  "${STAGE}/storage/framework/cache" \
  "${STAGE}/public/uploads"

touch \
  "${STAGE}/storage/logs/.gitkeep" \
  "${STAGE}/storage/attendance-images/.gitkeep" \
  "${STAGE}/storage/employee-documents/.gitkeep" \
  "${STAGE}/storage/employment-agreements/signatures/.gitkeep" \
  "${STAGE}/storage/leave-attachments/.gitkeep" \
  "${STAGE}/storage/payslips/.gitkeep" \
  "${STAGE}/storage/exports/.gitkeep" \
  "${STAGE}/storage/framework/sessions/.gitkeep" \
  "${STAGE}/storage/framework/cache/.gitkeep" \
  "${STAGE}/public/uploads/.gitkeep"

# Root htaccess for public_html uploads (docroot = project root)
cp "${STAGE}/.htaccess.deploy-root" "${STAGE}/.htaccess"

# Fresh env template only — no secrets
rm -f "${STAGE}/.env" "${STAGE}/install/installed.lock"

# Ensure vendor exists (required for no-composer cPanel hosts)
if [[ ! -f "${STAGE}/vendor/autoload.php" ]]; then
  echo "ERROR: vendor/ is missing. Run composer install --no-dev before packaging." >&2
  exit 1
fi

# Optionally bundle a user-supplied database dump for cPanel/phpMyAdmin.
# Usage: CPANEL_SQL_DUMP=/absolute/path/to/dump.sql bash scripts/build-cpanel.sh
if [[ -n "${CPANEL_SQL_DUMP:-}" ]]; then
  if [[ ! -f "${CPANEL_SQL_DUMP}" ]]; then
    echo "ERROR: SQL dump not found: ${CPANEL_SQL_DUMP}" >&2
    exit 1
  fi
  cp "${CPANEL_SQL_DUMP}" "${STAGE}/database/import.sql"
  # Keep this supplied dump current with the mandatory agreement feature.
  for safe_migration in \
    "${STAGE}/database/migrations/2026_09_10_employment_agreements.sql" \
    "${STAGE}/database/migrations/2026_09_10_attendance_device_security.sql"; do
    if [[ -f "${safe_migration}" ]]; then
      {
        printf '\n\n-- Applied from %s\n' "$(basename "${safe_migration}")"
        sed '/^USE `/d' "${safe_migration}"
      } >> "${STAGE}/database/import.sql"
    fi
  done
  echo "==> Included database dump as database/import.sql"
fi

# Production .env defaults file (user edits or uses installer)
cat > "${STAGE}/.env.production.example" <<'EOF'
APP_NAME="Employee Management System"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
APP_KEY=
APP_TIMEZONE=Asia/Karachi

DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=cpaneluser_ems
DB_USERNAME=cpaneluser_ems
DB_PASSWORD=change-me

SESSION_LIFETIME=120
SESSION_SECURE=true
REMEMBER_ME_DAYS=30
LOGIN_MAX_ATTEMPTS=5
LOGIN_LOCKOUT_MINUTES=15
TRUSTED_PROXIES=

MAIL_MAILER=smtp
MAIL_HOST=mail.your-domain.com
MAIL_PORT=587
MAIL_USERNAME=noreply@your-domain.com
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@your-domain.com
MAIL_FROM_NAME="${APP_NAME}"

ATTENDANCE_DEFAULT_RADIUS=100
ATTENDANCE_GPS_ACCURACY_THRESHOLD=50
ATTENDANCE_IMAGE_RETENTION_DAYS=365
ATTENDANCE_LOCATION_RETENTION_DAYS=365

UPLOAD_MAX_SIZE=5242880
CURRENCY=PKR
DATE_FORMAT=Y-m-d
TIME_FORMAT="g:i A"
EOF

chmod -R u+rwX,go+rX "${STAGE}"
chmod -R u+rwX "${STAGE}/storage" "${STAGE}/install" || true

rm -f "${ZIP}"
(
  cd "${OUT_DIR}"
  zip -rq "${NAME}.zip" "${NAME}"
)

SIZE="$(du -h "${ZIP}" | awk '{print $1}')"
echo "==> Created ${ZIP} (${SIZE})"
echo "==> Self-serve upload only — extract into your subdomain (or public_html) folder"
echo "==> Point document root at public/ (see CPANEL_DEPLOY.md — Subdomain deployment)"
echo "==> Then open https://YOUR-SUBDOMAIN/install"
