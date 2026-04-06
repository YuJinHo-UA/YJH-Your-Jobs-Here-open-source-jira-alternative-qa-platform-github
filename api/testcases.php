<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';

session_start();
$user = current_user();
if (!$user) {
    json_response(['error' => 'Unauthorized'], 401);
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $cases = fetch_all('SELECT * FROM test_cases ORDER BY created_at DESC LIMIT 100');
    json_response(['data' => $cases]);
}

if ($method === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $stmt = db()->prepare('INSERT INTO test_cases (suite_id, title, steps_json, expected_result_json, created_by) VALUES (:suite_id, :title, :steps_json, :expected_result_json, :created_by)');
    $stmt->execute([
        ':suite_id' => $payload['suite_id'] ?? 1,
        ':title' => $payload['title'] ?? 'Untitled',
        ':steps_json' => json_encode($payload['steps'] ?? []),
        ':expected_result_json' => json_encode($payload['expected'] ?? []),
        ':created_by' => $user['id'],
    ]);
    $newId = (int)db()->lastInsertId();
    record_activity('created', 'test_case', $newId, [
        'title' => (string)($payload['title'] ?? 'Untitled'),
        'suite_id' => (int)($payload['suite_id'] ?? 1),
    ]);
    json_response(['status' => 'created', 'id' => $newId]);
}

json_response(['error' => 'Method not allowed'], 405);
