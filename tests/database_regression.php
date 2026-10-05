#!/usr/bin/env php
<?php

declare(strict_types=1);

if (getenv('TEST_DB') !== '1') {
    echo "Database regression tests skipped (set TEST_DB=1 to enable)." . PHP_EOL;
    exit(0);
}

$root = dirname(__DIR__);
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$name = getenv('DB_DATABASE') ?: 'employee_management_test';
$user = getenv('DB_USERNAME') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';
$pdo = new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$columnExists = static function (string $table, string $column) use ($pdo, $name): bool {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?');
    $stmt->execute([$name, $table, $column]);
    return (int) $stmt->fetchColumn() === 1;
};

foreach ([
    ['password_resets', 'expires_at'],
    ['password_resets', 'used_at'],
    ['password_reset_attempts', 'requested_at'],
    ['employees', 'company_id'],
    ['employees', 'date_of_birth'],
    ['employees', 'national_id'],
    ['attendance', 'company_id'],
    ['payroll_records', 'company_id'],
    ['monitoring_screenshots', 'company_id'],
    ['monitoring_site_activity', 'domain'],
    ['monitoring_site_rules', 'category'],
    ['monitoring_work_schedules', 'work_days'],
    ['monitoring_distraction_events', 'action_taken'],
    ['monitoring_disputes', 'reason'],
    ['monitoring_access_logs', 'actor_user_id'],
    ['employee_attendance_devices', 'device_identifier_hash'],
    ['employee_attendance_devices', 'approved_employee_id'],
    ['attendance_security_events', 'event_type'],
] as [$table, $column]) {
    $assert($columnExists($table, $column), "Missing {$table}.{$column} required by regression coverage.");
}

$companyCount = (int) $pdo->query('SELECT COUNT(*) FROM companies')->fetchColumn();
if ($companyCount >= 2) {
    $crossTenant = (int) $pdo->query('SELECT COUNT(*) FROM employees e JOIN companies c ON c.id <> e.company_id')->fetchColumn();
    $assert($crossTenant > 0, 'Fixture must include employees belonging to a different company for isolation coverage.');
}

echo 'Database regression checks passed.' . PHP_EOL;
