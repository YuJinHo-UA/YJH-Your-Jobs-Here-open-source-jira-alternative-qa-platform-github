<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

function rate_limit_key(string $scope, ?string $identifier = null): string
{
    $ip = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : 'unknown-ip';
    $id = trim((string)$identifier);
    return $scope . '|' . ($id !== '' ? $id : 'anon') . '|' . $ip;
}

function check_rate_limit(string $scope, ?string $identifier = null, int $maxAttempts = 5, int $timeWindow = 900): bool
{
    $key = rate_limit_key($scope, $identifier);
    $threshold = date('Y-m-d H:i:s', time() - $timeWindow);

    $cleanup = db()->prepare('DELETE FROM rate_limit_entries WHERE key = :key AND attempted_at < :threshold');
    $cleanup->execute([':key' => $key, ':threshold' => $threshold]);

    $countStmt = db()->prepare('SELECT COUNT(*) FROM rate_limit_entries WHERE key = :key');
    $countStmt->execute([':key' => $key]);
    $count = (int)$countStmt->fetchColumn();
    return $count < $maxAttempts;
}

function add_rate_limit_attempt(string $scope, ?string $identifier = null): void
{
    $key = rate_limit_key($scope, $identifier);
    $stmt = db()->prepare('INSERT INTO rate_limit_entries (key, attempted_at) VALUES (:key, CURRENT_TIMESTAMP)');
    $stmt->execute([':key' => $key]);
}

function clear_rate_limit(string $scope, ?string $identifier = null): void
{
    $key = rate_limit_key($scope, $identifier);
    $stmt = db()->prepare('DELETE FROM rate_limit_entries WHERE key = :key');
    $stmt->execute([':key' => $key]);
}

