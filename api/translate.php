<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$user = current_user();
if (!$user) {
    json_response(['error' => 'Unauthorized'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$payload = json_decode((string)file_get_contents('php://input'), true) ?: [];
$target = normalize_language((string)($payload['target'] ?? current_language()));
$texts = $payload['texts'] ?? [];

if (!is_array($texts)) {
    json_response(['error' => 'Invalid texts payload'], 400);
}

$translations = [];
foreach ($texts as $idx => $text) {
    $raw = is_string($text) ? $text : '';
    $trimmed = trim($raw);
    $length = function_exists('mb_strlen') ? mb_strlen($trimmed) : strlen($trimmed);
    if ($trimmed === '' || $length > 4000) {
        $translations[] = $raw;
        continue;
    }
    $source = normalize_language((string)($payload['source'] ?? detect_text_language($trimmed)));
    $translations[] = translate_text_cached($trimmed, $target, $source);
}

json_response([
    'target' => $target,
    'translations' => $translations,
]);
