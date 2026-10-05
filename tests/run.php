#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$phpFiles = [];
foreach ([$root . '/app', $root . '/config', $root . '/routes', $root . '/views', $root . '/install', $root . '/bin'] as $directory) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->getExtension() === 'php') {
            $phpFiles[] = $file->getPathname();
        }
    }
}
foreach ($phpFiles as $file) {
    $output = [];
    $status = 0;
    exec('php -l ' . escapeshellarg($file), $output, $status);
    $assert($status === 0, 'PHP syntax error: ' . $file);
}

$migrationFiles = glob($root . '/database/migrations/*.sql') ?: [];
sort($migrationFiles, SORT_STRING);
$assert($migrationFiles !== [], 'At least one SQL migration is required.');
$assert(is_file($root . '/bin/migrate.php'), 'Migration runner is missing.');
$assert(is_file($root . '/bin/backup.php'), 'Backup tool is missing.');
$assert(is_file($root . '/bin/restore-test.php'), 'Restore test tool is missing.');
$assert(str_contains((string) file_get_contents($root . '/app/Core/SecurityHeaders.php'), 'Content-Security-Policy'), 'Security headers are not configured.');
$assert(str_contains((string) file_get_contents($root . '/app/Services/Monitoring/MonitoringAuthorizationService.php'), 'company_id'), 'Monitoring authorization must be tenant-aware.');
$assert(str_contains((string) file_get_contents($root . '/app/Services/PayrollService.php'), 'company_id'), 'Payroll service must retain company scoping.');
$assert(str_contains((string) file_get_contents($root . '/app/Services/AttendanceService.php'), 'company_id'), 'Attendance service must retain company scoping.');

$js = $root . '/public/assets/js/attendance.js';
if (is_file($js)) {
    $output = [];
    $status = 0;
    exec('node --check ' . escapeshellarg($js), $output, $status);
    $assert($status === 0, 'Attendance JavaScript syntax error.');
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo 'Static and regression guard tests passed (' . count($phpFiles) . ' PHP files checked).' . PHP_EOL;
passthru('php ' . escapeshellarg(__DIR__ . '/database_regression.php'), $databaseStatus);
if ($databaseStatus !== 0) exit($databaseStatus);
passthru('php ' . escapeshellarg(__DIR__ . '/attendance_security_regression.php'), $securityStatus);
if ($securityStatus !== 0) exit($securityStatus);
passthru('php ' . escapeshellarg(__DIR__ . '/attendance_reporting_regression.php'), $reportingStatus);
exit($reportingStatus);
