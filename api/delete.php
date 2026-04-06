<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

session_start();
$user = current_user();
if (!$user) {
    json_response(['error' => 'Unauthorized'], 401);
}
if (!in_array((string)$user['role'], ['admin', 'qa'], true)) {
    json_response(['error' => 'Forbidden'], 403);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$payload = json_decode(file_get_contents('php://input'), true) ?: [];
$token = (string)($payload['csrf_token'] ?? '');
if (!validateCsrfToken($token)) {
    json_response(['error' => 'Invalid CSRF token'], 400);
}

$table = (string)($payload['table'] ?? '');
$keys = $payload['keys'] ?? [];
if ($table === '' || !is_array($keys) || $keys === []) {
    json_response(['error' => 'table and keys are required'], 422);
}

try {
    $deleted = delete_row($table, $keys);
    json_response([
        'status' => 'ok',
        'table' => $table,
        'deleted' => $deleted,
    ]);
} catch (InvalidArgumentException $e) {
    json_response(['error' => $e->getMessage()], 422);
} catch (Throwable $e) {
    json_response(['error' => 'Delete failed', 'details' => $e->getMessage()], 500);
}
