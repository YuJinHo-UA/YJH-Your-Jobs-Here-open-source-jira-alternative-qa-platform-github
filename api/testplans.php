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
    $plans = fetch_all('SELECT * FROM test_plans ORDER BY created_at DESC');
    json_response(['data' => $plans]);
}

if ($method === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $stmt = db()->prepare('INSERT INTO test_plans (project_id, name, status, created_by) VALUES (:project_id, :name, :status, :created_by)');
    $stmt->execute([
        ':project_id' => $payload['project_id'] ?? 1,
        ':name' => $payload['name'] ?? 'Untitled',
        ':status' => $payload['status'] ?? 'draft',
        ':created_by' => $user['id'],
    ]);
    $newId = (int)db()->lastInsertId();
    record_activity('created', 'test_plan', $newId, [
        'name' => (string)($payload['name'] ?? 'Untitled'),
        'project_id' => (int)($payload['project_id'] ?? 1),
    ]);
    json_response(['status' => 'created', 'id' => $newId]);
}

json_response(['error' => 'Method not allowed'], 405);
