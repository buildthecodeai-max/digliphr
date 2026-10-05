<?php

declare(strict_types=1);

/**
 * Web installer for Employee Management System (cPanel-friendly).
 * Does not require shell access or Composer on the server.
 */

$root = dirname(__DIR__);
$lockFile = __DIR__ . '/installed.lock';

if (file_exists($lockFile)) {
    // Already installed — never re-enter the installer, even with a query param.
    // Delete install/installed.lock manually (shell/FTP) if a genuine reinstall is needed.
    header('Location: /login');
    exit;
}

$step = (int) ($_GET['step'] ?? 1);
$errors = [];
$messages = [];

/**
 * Posted/default form values so fields persist after validation errors.
 */
$form = [
    'app_name' => 'Employee Management System',
    'app_url' => '',
    'db_host' => 'localhost',
    'db_port' => '3306',
    'db_database' => '',
    'db_username' => '',
    'db_password' => '',
    'timezone' => 'Asia/Karachi',
    'currency' => 'PKR',
];

function installer_base_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 80) === 443)
        || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return ($https ? 'https' : 'http') . '://' . $host;
}

function installer_writable(string $path): bool
{
    if (is_dir($path) || @mkdir($path, 0755, true)) {
        return is_writable($path);
    }
    return false;
}

/**
 * Split SQL into single statements on `;` outside strings/comments.
 * Required for PDO (MYSQL_ATTR_MULTI_STATEMENTS=false) on cPanel MariaDB.
 */
function installer_split_sql(string $sql): array
{
    $sql = str_replace(["\r\n", "\r"], "\n", $sql);
    $len = strlen($sql);
    $statements = [];
    $buffer = '';
    $i = 0;

    while ($i < $len) {
        $ch = $sql[$i];
        $next = $i + 1 < $len ? $sql[$i + 1] : '';

        // Line comment -- (MySQL/MariaDB: -- must be followed by whitespace)
        if ($ch === '-' && $next === '-' && ($i + 2 >= $len || ctype_space($sql[$i + 2]))) {
            $nl = strpos($sql, "\n", $i);
            $i = $nl === false ? $len : $nl;
            continue;
        }

        // Line comment #
        if ($ch === '#') {
            $nl = strpos($sql, "\n", $i);
            $i = $nl === false ? $len : $nl;
            continue;
        }

        // Block comment /* */
        if ($ch === '/' && $next === '*') {
            $end = strpos($sql, '*/', $i + 2);
            if ($end === false) {
                break;
            }
            $i = $end + 2;
            continue;
        }

        // Single-quoted string
        if ($ch === "'") {
            $buffer .= $ch;
            $i++;
            while ($i < $len) {
                $c = $sql[$i];
                $buffer .= $c;
                if ($c === '\\' && $i + 1 < $len) {
                    $buffer .= $sql[$i + 1];
                    $i += 2;
                    continue;
                }
                if ($c === "'") {
                    // '' escape inside string
                    if ($i + 1 < $len && $sql[$i + 1] === "'") {
                        $buffer .= $sql[$i + 1];
                        $i += 2;
                        continue;
                    }
                    $i++;
                    break;
                }
                $i++;
            }
            continue;
        }

        // Double-quoted identifier/string
        if ($ch === '"') {
            $buffer .= $ch;
            $i++;
            while ($i < $len) {
                $c = $sql[$i];
                $buffer .= $c;
                if ($c === '\\' && $i + 1 < $len) {
                    $buffer .= $sql[$i + 1];
                    $i += 2;
                    continue;
                }
                if ($c === '"') {
                    if ($i + 1 < $len && $sql[$i + 1] === '"') {
                        $buffer .= $sql[$i + 1];
                        $i += 2;
                        continue;
                    }
                    $i++;
                    break;
                }
                $i++;
            }
            continue;
        }

        // Backtick identifier
        if ($ch === '`') {
            $buffer .= $ch;
            $i++;
            while ($i < $len) {
                $c = $sql[$i];
                $buffer .= $c;
                if ($c === '`') {
                    if ($i + 1 < $len && $sql[$i + 1] === '`') {
                        $buffer .= $sql[$i + 1];
                        $i += 2;
                        continue;
                    }
                    $i++;
                    break;
                }
                $i++;
            }
            continue;
        }

        if ($ch === ';') {
            $part = trim($buffer);
            if ($part !== '') {
                $statements[] = $part;
            }
            $buffer = '';
            $i++;
            continue;
        }

        $buffer .= $ch;
        $i++;
    }

    $part = trim($buffer);
    if ($part !== '') {
        $statements[] = $part;
    }

    return $statements;
}

function installer_is_ignorable_schema_error(PDOException $e, string $statement): bool
{
    $msg = $e->getMessage();
    $isAlterConstraint = (bool) preg_match('/^\s*ALTER\s+TABLE\b/i', $statement)
        && (bool) preg_match('/\bADD\s+CONSTRAINT\b/i', $statement);

    if (!$isAlterConstraint) {
        return false;
    }

    // Duplicate FK / key name — safe when re-running install after partial success.
    return (bool) preg_match(
        '/Duplicate (foreign key constraint name|key name|key on write)|errno:\s*121\b/i',
        $msg
    );
}

function installer_run_sql(PDO $pdo, string $sqlFile): void
{
    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
        throw new RuntimeException('Unable to read SQL file: ' . $sqlFile);
    }

    // cPanel DB is already created — skip CREATE DATABASE / USE.
    $sql = preg_replace('/CREATE DATABASE.*?;/is', '', $sql) ?? $sql;
    $sql = preg_replace('/USE\s+[`\w]+;/i', '', $sql) ?? $sql;

    $statements = installer_split_sql($sql);
    foreach ($statements as $statement) {
        if ($statement === '') {
            continue;
        }
        try {
            $pdo->exec($statement);
        } catch (PDOException $e) {
            if (installer_is_ignorable_schema_error($e, $statement)) {
                continue;
            }
            $preview = preg_replace('/\s+/', ' ', $statement) ?? $statement;
            if (strlen($preview) > 180) {
                $preview = substr($preview, 0, 180) . '…';
            }
            throw new RuntimeException(
                'Schema SQL failed: ' . $e->getMessage() . ' — Statement: ' . $preview,
                (int) $e->getCode(),
                $e
            );
        }
    }
}

function installer_env_quote(string $value): string
{
    // Quote when empty or when dotenv-sensitive characters are present.
    if ($value === '' || preg_match('/[\s#"\'\\\\$!@]/', $value) === 1) {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }
    return $value;
}

function installer_write_env(array $env, string $root): void
{
    $template = file_exists($root . '/.env.example')
        ? file_get_contents($root . '/.env.example')
        : '';

    $values = [
        'APP_NAME' => $env['APP_NAME'],
        'APP_ENV' => 'production',
        'APP_DEBUG' => 'false',
        'APP_URL' => $env['APP_URL'],
        'APP_KEY' => $env['APP_KEY'],
        'APP_TIMEZONE' => $env['APP_TIMEZONE'],
        'DB_HOST' => $env['DB_HOST'],
        'DB_PORT' => $env['DB_PORT'],
        'DB_DATABASE' => $env['DB_DATABASE'],
        'DB_USERNAME' => $env['DB_USERNAME'],
        'DB_PASSWORD' => $env['DB_PASSWORD'],
        'SESSION_LIFETIME' => '120',
        'SESSION_SECURE' => str_starts_with($env['APP_URL'], 'https') ? 'true' : 'false',
        'CURRENCY' => $env['CURRENCY'],
        'MAIL_MAILER' => 'smtp',
        'MAIL_HOST' => 'localhost',
        'MAIL_PORT' => '587',
        'MAIL_USERNAME' => 'null',
        'MAIL_PASSWORD' => 'null',
        'MAIL_ENCRYPTION' => 'tls',
        'MAIL_FROM_ADDRESS' => 'noreply@' . (parse_url($env['APP_URL'], PHP_URL_HOST) ?: 'example.com'),
        'MAIL_FROM_NAME' => '"' . $env['APP_NAME'] . '"',
        'ATTENDANCE_DEFAULT_RADIUS' => '100',
        'ATTENDANCE_GPS_ACCURACY_THRESHOLD' => '50',
        'ATTENDANCE_IMAGE_RETENTION_DAYS' => '365',
        'ATTENDANCE_LOCATION_RETENTION_DAYS' => '365',
        'UPLOAD_MAX_SIZE' => '5242880',
        'DATE_FORMAT' => 'Y-m-d',
        'TIME_FORMAT' => 'H:i',
        'REMEMBER_ME_DAYS' => '30',
        'LOGIN_MAX_ATTEMPTS' => '5',
        'LOGIN_LOCKOUT_MINUTES' => '15',
    ];

    if (is_string($template) && $template !== '') {
        $lines = preg_split("/\r\n|\n|\r/", $template) ?: [];
        $out = [];
        foreach ($lines as $line) {
            if ($line === '' || str_starts_with(ltrim($line), '#') || !str_contains($line, '=')) {
                $out[] = $line;
                continue;
            }
            [$key] = explode('=', $line, 2);
            $key = trim($key);
            if (array_key_exists($key, $values)) {
                $val = (string) $values[$key];
                if ($key === 'DB_PASSWORD' || $key === 'MAIL_FROM_NAME' || preg_match('/\s/', $val) || preg_match('/[!@#]/', $val)) {
                    if (!str_starts_with($val, '"')) {
                        $val = installer_env_quote($val);
                    }
                }
                $out[] = $key . '=' . $val;
                unset($values[$key]);
            } else {
                $out[] = $line;
            }
        }
        foreach ($values as $key => $val) {
            $val = (string) $val;
            if ($key === 'DB_PASSWORD' || ($key !== 'MAIL_FROM_NAME' && preg_match('/[\s!@#]/', $val))) {
                $val = installer_env_quote($val);
            }
            $out[] = $key . '=' . $val;
        }
        file_put_contents($root . '/.env', implode("\n", $out) . "\n");
        return;
    }

    $lines = [];
    foreach ($values as $k => $v) {
        $v = (string) $v;
        if ($k === 'DB_PASSWORD' || ($k !== 'MAIL_FROM_NAME' && preg_match('/[\s!@#]/', $v))) {
            $v = installer_env_quote($v);
        }
        $lines[] = $k . '=' . $v;
    }
    file_put_contents($root . '/.env', implode("\n", $lines) . "\n");
}

function check_requirements(): array
{
    $root = dirname(__DIR__);
    return [
        'PHP >= 8.2' => version_compare(PHP_VERSION, '8.2.0', '>='),
        'PDO' => extension_loaded('pdo'),
        'PDO MySQL' => extension_loaded('pdo_mysql'),
        'OpenSSL' => extension_loaded('openssl'),
        'Mbstring' => extension_loaded('mbstring'),
        'GD' => extension_loaded('gd'),
        'Fileinfo' => extension_loaded('fileinfo'),
        'JSON' => extension_loaded('json'),
        'Curl' => extension_loaded('curl'),
        'storage writable' => installer_writable($root . '/storage'),
        'storage/logs writable' => installer_writable($root . '/storage/logs'),
        'vendor installed' => file_exists($root . '/vendor/autoload.php'),
        '.env writable (or creatable)' => !file_exists($root . '/.env') || is_writable($root . '/.env') || is_writable($root),
        'schema.sql present' => file_exists($root . '/database/schema.sql'),
        'DatabaseSeeder present' => file_exists($root . '/database/seeds/DatabaseSeeder.php'),
    ];
}

$form['app_url'] = installer_base_url();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'configure') {
        $form = [
            'app_name' => trim((string) ($_POST['app_name'] ?? 'Employee Management System')),
            'app_url' => rtrim(trim((string) ($_POST['app_url'] ?? '')), '/'),
            'db_host' => trim((string) ($_POST['db_host'] ?? 'localhost')),
            'db_port' => trim((string) ($_POST['db_port'] ?? '3306')),
            'db_database' => trim((string) ($_POST['db_database'] ?? '')),
            'db_username' => trim((string) ($_POST['db_username'] ?? '')),
            'db_password' => (string) ($_POST['db_password'] ?? ''),
            'timezone' => trim((string) ($_POST['timezone'] ?? 'Asia/Karachi')),
            'currency' => trim((string) ($_POST['currency'] ?? 'PKR')),
        ];

        $env = [
            'APP_NAME' => $form['app_name'] !== '' ? $form['app_name'] : 'Employee Management System',
            'APP_URL' => $form['app_url'],
            'APP_KEY' => bin2hex(random_bytes(16)),
            'APP_TIMEZONE' => $form['timezone'] !== '' ? $form['timezone'] : 'Asia/Karachi',
            'DB_HOST' => $form['db_host'] !== '' ? $form['db_host'] : 'localhost',
            'DB_PORT' => $form['db_port'] !== '' ? $form['db_port'] : '3306',
            'DB_DATABASE' => $form['db_database'],
            'DB_USERNAME' => $form['db_username'],
            'DB_PASSWORD' => $form['db_password'],
            'CURRENCY' => $form['currency'] !== '' ? $form['currency'] : 'PKR',
        ];

        try {
            if ($env['APP_URL'] === '' || $env['DB_DATABASE'] === '' || $env['DB_USERNAME'] === '') {
                throw new RuntimeException('App URL, database name, and database username are required.');
            }

            if (!file_exists($root . '/database/schema.sql')) {
                throw new RuntimeException('Missing database/schema.sql — upload the full EMS package.');
            }
            if (!file_exists($root . '/vendor/autoload.php')) {
                throw new RuntimeException('Missing vendor/ — upload the full EMS package (vendor included).');
            }
            if (!file_exists($root . '/database/seeds/DatabaseSeeder.php')) {
                throw new RuntimeException('Missing database/seeds/DatabaseSeeder.php.');
            }
            if (!installer_writable($root . '/storage') || !installer_writable($root . '/storage/logs')) {
                throw new RuntimeException('storage/ must be writable. In cPanel File Manager, set storage and storage/logs to 755 or 775.');
            }

            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $env['DB_HOST'],
                $env['DB_PORT'],
                $env['DB_DATABASE']
            );
            try {
                $pdo = new PDO($dsn, $env['DB_USERNAME'], $env['DB_PASSWORD'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
                ]);
            } catch (PDOException $e) {
                throw new RuntimeException(
                    'Database connection failed: ' . $e->getMessage()
                    . ' — Check DB name, username, password, and that the user is assigned to the database in cPanel → MySQL Databases.'
                );
            }

            installer_run_sql($pdo, $root . '/database/schema.sql');
            $productEnhancements = $root . '/database/migrations/2026_08_26_product_ui_enhancements.sql';
            if (file_exists($productEnhancements)) {
                installer_run_sql($pdo, $productEnhancements);
            }
            // Monitoring is optional in older upgrades, but fresh installations
            // receive the complete domain-activity and policy stack.
            foreach ([
                $root . '/database/migrations/2026_07_30_work_activity_monitoring.sql',
                $root . '/database/migrations/2026_07_30_monitoring_admin_only.sql',
                $root . '/database/migrations/2026_08_27_monitoring_web_activity.sql',
                $root . '/database/migrations/2026_09_10_employment_agreements.sql',
                $root . '/database/migrations/2026_09_10_attendance_device_security.sql',
            ] as $monitoringMigration) {
                if (file_exists($monitoringMigration)) {
                    installer_run_sql($pdo, $monitoringMigration);
                }
            }
            installer_write_env($env, $root);

            if (!is_readable($root . '/.env')) {
                throw new RuntimeException('Could not write .env — make the site root writable, then try again.');
            }

            // Seed in-process (no shell / passthru required on cPanel).
            require $root . '/vendor/autoload.php';
            require_once $root . '/database/seeds/DatabaseSeeder.php';
            ob_start();
            try {
                (new DatabaseSeeder())->run();
            } finally {
                ob_end_clean();
            }

            file_put_contents($lockFile, date('c') . " — installed via web installer\n");
            $messages[] = 'Installation completed successfully.';
            $step = 3;
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
            $step = 2;
        }
    } else {
        $errors[] = 'Invalid install request (missing action). Please submit the form again.';
        $step = 2;
    }
}

$checks = check_requirements();
$allOk = !in_array(false, $checks, true);

/**
 * Relative action keeps the trailing slash on /install/ so Apache does not
 * 301 /install → /install/ and strip the POST body (silent install failure).
 */
$formAction = '?step=2';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install — Employee Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width:760px">
    <h3 class="mb-1">EMS Installer</h3>
    <p class="text-muted mb-4">cPanel / shared hosting setup for Employee Management, Attendance, Leave & Payroll</p>

    <?php foreach ($errors as $error): ?>
        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endforeach; ?>
    <?php foreach ($messages as $msg): ?>
        <div class="alert alert-success" role="alert"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endforeach; ?>

    <?php if ($step === 1): ?>
        <div class="card mb-3">
            <div class="card-header">Step 1 — Requirements</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($checks as $label => $ok): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="badge text-bg-<?= $ok ? 'success' : 'danger' ?>"><?= $ok ? 'OK' : 'Missing' ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="alert alert-info small">
            Create an empty MySQL database and user in cPanel → <strong>MySQL Databases</strong> before continuing.
            Prefer pointing the domain document root to the <code>public</code> folder.
        </div>
        <?php if ($allOk): ?>
            <a href="?step=2" class="btn btn-primary">Continue</a>
        <?php else: ?>
            <div class="alert alert-warning">Fix missing requirements, then refresh.</div>
        <?php endif; ?>
    <?php elseif ($step === 2): ?>
        <div class="card">
            <div class="card-header">Step 2 — Configuration</div>
            <div class="card-body">
                <form method="post" action="<?= htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8') ?>" autocomplete="off" novalidate>
                    <input type="hidden" name="action" value="configure">
                    <div class="mb-3">
                        <label class="form-label" for="app_name">App Name</label>
                        <input id="app_name" name="app_name" class="form-control" value="<?= htmlspecialchars($form['app_name'], ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="app_url">App URL</label>
                        <input id="app_url" name="app_url" class="form-control" value="<?= htmlspecialchars($form['app_url'], ENT_QUOTES, 'UTF-8') ?>" required>
                        <div class="form-text">Use your live HTTPS domain, e.g. https://hr.diglip.com</div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="db_host">DB Host</label>
                            <input id="db_host" name="db_host" class="form-control" value="<?= htmlspecialchars($form['db_host'], ENT_QUOTES, 'UTF-8') ?>" required autocomplete="off">
                            <div class="form-text">Usually <code>localhost</code> on cPanel</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="db_port">Port</label>
                            <input id="db_port" name="db_port" class="form-control" value="<?= htmlspecialchars($form['db_port'], ENT_QUOTES, 'UTF-8') ?>" required autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="db_database">Database Name</label>
                            <input id="db_database" name="db_database" class="form-control" value="<?= htmlspecialchars($form['db_database'], ENT_QUOTES, 'UTF-8') ?>" placeholder="cpaneluser_ems" required autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="db_username">DB Username</label>
                            <input id="db_username" name="db_username" class="form-control" value="<?= htmlspecialchars($form['db_username'], ENT_QUOTES, 'UTF-8') ?>" placeholder="cpaneluser_ems" required autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="db_password">DB Password</label>
                            <input type="password" id="db_password" name="db_password" class="form-control" value="<?= htmlspecialchars($form['db_password'], ENT_QUOTES, 'UTF-8') ?>" autocomplete="new-password">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="timezone">Timezone</label>
                            <input id="timezone" name="timezone" class="form-control" value="<?= htmlspecialchars($form['timezone'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="currency">Currency</label>
                            <input id="currency" name="currency" class="form-control" value="<?= htmlspecialchars($form['currency'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary mt-3">Install Database &amp; Seed</button>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-body">
                <h5>Installation complete</h5>
                <p class="mb-2">Default Super Admin credentials:</p>
                <ul>
                    <li>Email: <code>admin@example.com</code></li>
                    <li>Password: <code>Admin@123</code></li>
                </ul>
                <p class="text-danger small">Change this password immediately after login.</p>
                <p class="small text-muted mb-3">Optional: set up a cron job for <code>cron/run.php</code> every 15 minutes.</p>
                <a href="/login" class="btn btn-success">Go to Login</a>
            </div>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
