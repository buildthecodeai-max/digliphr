#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
$root = dirname(__DIR__);
if (class_exists(\Dotenv\Dotenv::class)) {
    \Dotenv\Dotenv::createImmutable($root)->safeLoad();
}
$config = require $root . '/config/database.php';
$db = $config['connections']['mysql'];
$output = null;
$force = in_array('--force', $argv, true);
foreach ($argv as $index => $arg) {
    if ($arg === '--output' && isset($argv[$index + 1])) {
        $output = $argv[$index + 1];
    }
}
$output ??= $root . '/storage/backups/' . $db['database'] . '_' . date('Ymd_His') . '.sql.gz';
$output = str_starts_with($output, '/') ? $output : $root . '/' . ltrim($output, '/');
if (file_exists($output) && !$force) {
    throw new RuntimeException("Backup exists: {$output}. Use --force only when intentional.");
}
if (!is_dir(dirname($output))) {
    mkdir(dirname($output), 0750, true);
}
$dump = tempnam(sys_get_temp_dir(), 'ems-backup-');
if ($dump === false) {
    throw new RuntimeException('Unable to create temporary backup file.');
}
$command = sprintf('mysqldump --single-transaction --routines --triggers --host=%s --port=%d --user=%s %s', escapeshellarg($db['host']), $db['port'], escapeshellarg($db['username']), escapeshellarg($db['database']));
$descriptor = [1 => ['file', $dump, 'w'], 2 => ['pipe', 'w']];
if ($db['password'] !== '') {
    putenv('MYSQL_PWD=' . $db['password']);
}
$passwordSet = $db['password'] !== '';
$process = proc_open($command, $descriptor, $pipes);
if (!is_resource($process)) {
    unlink($dump);
    throw new RuntimeException('Unable to start mysqldump.');
}
$error = stream_get_contents($pipes[2]);
fclose($pipes[2]);
$exitCode = proc_close($process);
if ($passwordSet) {
    putenv('MYSQL_PWD');
}
if ($exitCode !== 0) {
    unlink($dump);
    throw new RuntimeException('Backup failed: ' . trim((string) $error));
}
$gzip = gzopen($output, 'wb9');
$source = fopen($dump, 'rb');
if ($gzip === false || $source === false) {
    unlink($dump);
    throw new RuntimeException('Unable to compress backup.');
}
while (!feof($source)) {
    gzwrite($gzip, (string) fread($source, 1024 * 1024));
}
fclose($source);
gzclose($gzip);
unlink($dump);
echo "Backup written to {$output}" . PHP_EOL;
