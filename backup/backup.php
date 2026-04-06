<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$sourceDb = $root . DIRECTORY_SEPARATOR . 'database.sqlite';
$backupDir = $root . DIRECTORY_SEPARATOR . 'backups';

if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
    fwrite(STDERR, "Failed to create backup directory: $backupDir" . PHP_EOL);
    exit(1);
}

if (!file_exists($sourceDb)) {
    fwrite(STDERR, "Source DB not found: $sourceDb" . PHP_EOL);
    exit(1);
}

$filename = 'database-backup-' . date('Ymd-His') . '.sqlite';
$target = $backupDir . DIRECTORY_SEPARATOR . $filename;

if (!copy($sourceDb, $target)) {
    fwrite(STDERR, 'Backup failed' . PHP_EOL);
    exit(1);
}

echo "Backup created: $target" . PHP_EOL;

