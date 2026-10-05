#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
$root = dirname(__DIR__);
if (class_exists(\Dotenv\Dotenv::class)) {
    \Dotenv\Dotenv::createImmutable($root)->safeLoad();
}
$dump = $argv[1] ?? null;
if ($dump === null || !is_file($dump)) {
    throw new InvalidArgumentException('Usage: php bin/restore-test.php /path/to/backup.sql.gz');
}
$config = require $root . '/config/database.php';
$db = $config['connections']['mysql'];
$testDb = $db['database'] . '_restore_test_' . date('YmdHis');
$admin = new PDO(sprintf('mysql:host=%s;port=%d;charset=%s', $db['host'], $db['port'], $db['charset']), $db['username'], $db['password'], $db['options']);
$admin->exec('CREATE DATABASE `' . str_replace('`', '``', $testDb) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$command = str_ends_with($dump, '.gz') ? 'gzip -dc ' . escapeshellarg($dump) : 'cat ' . escapeshellarg($dump);
$command .= sprintf(' | mysql --host=%s --port=%d --user=%s %s', escapeshellarg($db['host']), $db['port'], escapeshellarg($db['username']), escapeshellarg($testDb));
if ($db['password'] !== '') {
    putenv('MYSQL_PWD=' . $db['password']);
}
$exitCode = 0;
passthru($command, $exitCode);
putenv('MYSQL_PWD');
try {
    if ($exitCode !== 0) {
        throw new RuntimeException('Restore import failed.');
    }
    $check = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $db['host'], $db['port'], $testDb, $db['charset']), $db['username'], $db['password'], $db['options']);
    foreach (['users', 'employees', 'attendance', 'payroll_periods'] as $table) {
        $check->query('SELECT 1 FROM `' . $table . '` LIMIT 1');
    }
    echo "Restore test passed in {$testDb}" . PHP_EOL;
} finally {
    $admin->exec('DROP DATABASE IF EXISTS `' . str_replace('`', '``', $testDb) . '`');
}
