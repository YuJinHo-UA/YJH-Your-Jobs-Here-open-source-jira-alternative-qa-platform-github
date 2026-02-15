<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/includes/functions.php';

$lang = normalize_language((string)get_param('lang', 'en'));
setcookie('lang', $lang, time() + 31536000, '/');

$user = current_user();
if ($user) {
    $stmt = db()->prepare('UPDATE users SET language = :language WHERE id = :id');
    $stmt->execute([':language' => $lang, ':id' => $user['id']]);
}

$back = (string)get_param('return', '');
if ($back === '' || !str_starts_with($back, '/')) {
    $back = $_SERVER['HTTP_REFERER'] ?? '/index.php';
}

$host = parse_url((string)$back, PHP_URL_HOST);
if ($host && $host !== ($_SERVER['HTTP_HOST'] ?? '')) {
    $back = '/index.php';
}

redirect((string)$back);
