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
    $bugs = fetch_all('SELECT * FROM bugs ORDER BY created_at DESC LIMIT 100');
    json_response(['data' => $bugs]);
}

if ($method === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $id = $payload['id'] ?? null;
    $assigneeId = isset($payload['assignee_id']) && (int)$payload['assignee_id'] > 0 ? (int)$payload['assignee_id'] : null;
    $dueDate = trim((string)($payload['due_date'] ?? date('Y-m-d')));

    if ($assigneeId !== null && !is_user_available($assigneeId, $dueDate)) {
        json_response(['error' => 'User is not available (vacation/sick leave) on this date'], 400);
    }

    if ($id) {
        $stmt = db()->prepare('UPDATE bugs SET title=:title, description=:description, status=:status, priority=:priority, severity=:severity, assignee_id=:assignee_id, updated_at=CURRENT_TIMESTAMP WHERE id=:id');
        $stmt->execute([
            ':title' => $payload['title'] ?? '',
            ':description' => $payload['description'] ?? '',
            ':status' => $payload['status'] ?? 'new',
            ':priority' => $payload['priority'] ?? 'medium',
            ':severity' => $payload['severity'] ?? 'major',
            ':assignee_id' => $assigneeId,
            ':id' => $id,
        ]);
        record_activity('updated', 'bug', (int)$id, [
            'title' => (string)($payload['title'] ?? ''),
            'status' => (string)($payload['status'] ?? 'new'),
        ]);
        json_response(['status' => 'updated']);
    }

    $stmt = db()->prepare('INSERT INTO bugs (project_id, title, description, severity, priority, status, reporter_id) VALUES (:project_id, :title, :description, :severity, :priority, :status, :reporter_id)');
    $stmt->execute([
        ':project_id' => $payload['project_id'] ?? 1,
        ':title' => $payload['title'] ?? 'Untitled',
        ':description' => $payload['description'] ?? '',
        ':severity' => $payload['severity'] ?? 'major',
        ':priority' => $payload['priority'] ?? 'medium',
        ':status' => $payload['status'] ?? 'new',
        ':reporter_id' => $user['id'],
    ]);
    $newId = (int)db()->lastInsertId();
    record_activity('created', 'bug', $newId, [
        'title' => (string)($payload['title'] ?? 'Untitled'),
        'project_id' => (int)($payload['project_id'] ?? 1),
    ]);
    json_response(['status' => 'created', 'id' => $newId]);
}

json_response(['error' => 'Method not allowed'], 405);