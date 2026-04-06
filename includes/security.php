<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
<<<<<<< HEAD
require_once __DIR__ . '/logger.php';
=======
>>>>>>> 7e7a5ae49ac6caacc4b2a0ad95dd06bd60dfa616

function client_ip(): ?string
{
    return isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : null;
}

function client_user_agent(): ?string
{
    return isset($_SERVER['HTTP_USER_AGENT']) ? (string)$_SERVER['HTTP_USER_AGENT'] : null;
}

function log_security_event(string $action, array $details = [], ?int $userId = null): void
{
    if ($userId === null && isset($_SESSION['user_id'])) {
        $userId = (int)$_SESSION['user_id'];
    }

    $stmt = db()->prepare(
        'INSERT INTO security_log (user_id, action, ip_address, user_agent, details)
         VALUES (:user_id, :action, :ip, :ua, :details)'
    );
    $stmt->execute([
        ':user_id' => $userId,
        ':action' => $action,
        ':ip' => client_ip(),
        ':ua' => client_user_agent(),
        ':details' => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
<<<<<<< HEAD
    yjh_log_write('security', json_encode([
        'user_id' => $userId,
        'action' => $action,
        'ip' => client_ip(),
        'details' => $details,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $action);
=======
>>>>>>> 7e7a5ae49ac6caacc4b2a0ad95dd06bd60dfa616
}

