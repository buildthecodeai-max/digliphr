#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
if (class_exists(\Dotenv\Dotenv::class)) {
    \Dotenv\Dotenv::createImmutable($root)->safeLoad();
}

$args = array_slice($argv, 1);
$command = in_array('--status', $args, true) ? 'status' : (in_array('--baseline', $args, true) ? 'baseline' : 'up');
$dryRun = in_array('--dry-run', $args, true);
$config = require $root . '/config/database.php';
$db = $config['connections']['mysql'];
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $db['host'], $db['port'], $db['database'], $db['charset']),
    $db['username'],
    $db['password'],
    $db['options']
);

$pdo->exec('CREATE TABLE IF NOT EXISTS `schema_migrations` (`id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, `migration` VARCHAR(255) NOT NULL UNIQUE, `checksum` CHAR(64) NOT NULL, `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
$files = glob($root . '/database/migrations/*.sql') ?: [];
sort($files, SORT_STRING);
$applied = $pdo->query('SELECT migration, checksum, applied_at FROM schema_migrations ORDER BY migration')->fetchAll(PDO::FETCH_ASSOC);
$appliedByName = [];
foreach ($applied as $row) {
    $appliedByName[$row['migration']] = $row;
}

if ($command === 'status') {
    foreach ($files as $file) {
        $name = basename($file);
        $state = isset($appliedByName[$name]) ? 'applied' : 'pending';
        echo sprintf("%-55s %s\n", $name, $state);
    }
    exit(0);
}

if ($command === 'baseline') {
    $statement = $pdo->prepare('INSERT INTO schema_migrations (migration, checksum) VALUES (?, ?) ON DUPLICATE KEY UPDATE checksum = VALUES(checksum)');
    foreach ($files as $file) {
        $statement->execute([basename($file), hash_file('sha256', $file)]);
    }
    echo 'Migration baseline recorded.' . PHP_EOL;
    exit(0);
}

foreach ($files as $file) {
    $name = basename($file);
    $checksum = hash_file('sha256', $file);
    if (isset($appliedByName[$name])) {
        if (!hash_equals($appliedByName[$name]['checksum'], $checksum)) {
            fwrite(STDERR, "Checksum mismatch for {$name}; restore the original migration or create a new migration.\n");
            exit(1);
        }
        continue;
    }

    echo ($dryRun ? 'Would apply ' : 'Applying ') . $name . PHP_EOL;
    if ($dryRun) {
        continue;
    }

    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException("Unable to read {$name}");
    }
    $pdo->beginTransaction();
    try {
        foreach (splitSql($sql) as $statementSql) {
            $pdo->exec($statementSql);
        }
        $insert = $pdo->prepare('INSERT INTO schema_migrations (migration, checksum) VALUES (?, ?)');
        $insert->execute([$name, $checksum]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

echo $dryRun ? 'No changes made.' . PHP_EOL : 'Migrations complete.' . PHP_EOL;

function splitSql(string $sql): array
{
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    $parts = preg_split('/;\s*(?=(?:[^\'\"]|\'[^\']*\'|\"[^\"]*\")*$)/', $sql) ?: [];
    return array_values(array_filter(array_map('trim', $parts), static fn (string $part): bool => $part !== ''));
}
