<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';

session_start();
$user = current_user();
if (!$user) {
    json_response(['error' => 'Unauthorized'], 401);
}

$payload = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $payload['action'] ?? '';

if ($action === 'move_card') {
    $stmt = db()->prepare('UPDATE board_cards SET column_id = :column_id, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
    $stmt->execute([
        ':column_id' => $payload['column_id'],
        ':id' => $payload['card_id'],
    ]);
    json_response(['status' => 'ok']);
}

if ($action === 'update_card') {
    $stmt = db()->prepare('UPDATE board_cards SET title = :title, description = :description, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
    $stmt->execute([
        ':id' => (int)($payload['card_id'] ?? 0),
        ':title' => (string)($payload['title'] ?? ''),
        ':description' => (string)($payload['description'] ?? ''),
    ]);
    json_response(['status' => 'ok']);
}

if ($action === 'delete_card') {
    $stmt = db()->prepare('DELETE FROM board_cards WHERE id = :id');
    $stmt->execute([
        ':id' => (int)($payload['card_id'] ?? 0),
    ]);
    json_response(['status' => 'ok']);
}

if ($action === 'update_column') {
    $stmt = db()->prepare('UPDATE board_columns SET name = :name WHERE id = :id');
    $stmt->execute([
        ':id' => (int)($payload['column_id'] ?? 0),
        ':name' => trim((string)($payload['name'] ?? '')),
    ]);
    json_response(['status' => 'ok']);
}

if ($action === 'delete_column') {
    $columnId = (int)($payload['column_id'] ?? 0);
    $countStmt = db()->prepare('SELECT COUNT(*) FROM board_cards WHERE column_id = :id');
    $countStmt->execute([':id' => $columnId]);
    $cardsCount = (int)$countStmt->fetchColumn();
    if ($cardsCount > 0) {
        json_response(['error' => 'Column is not empty'], 400);
    }

    $stmt = db()->prepare('DELETE FROM board_columns WHERE id = :id');
    $stmt->execute([':id' => $columnId]);
    json_response(['status' => 'ok']);
}

if ($action === 'create_column') {
    $boardId = (int)($payload['board_id'] ?? 0);
    $name = trim((string)($payload['name'] ?? ''));
    if ($boardId <= 0 || $name === '') {
        json_response(['error' => 'Board and column name are required'], 400);
    }

    $orderStmt = db()->prepare('SELECT COALESCE(MAX(order_index), -1) FROM board_columns WHERE board_id = :board_id');
    $orderStmt->execute([':board_id' => $boardId]);
    $nextOrder = (int)$orderStmt->fetchColumn() + 1;

    $stmt = db()->prepare('INSERT INTO board_columns (board_id, name, order_index, wip_limit) VALUES (:board_id, :name, :order_index, NULL)');
    $stmt->execute([
        ':board_id' => $boardId,
        ':name' => $name,
        ':order_index' => $nextOrder,
    ]);

    json_response(['status' => 'ok', 'column_id' => (int)db()->lastInsertId()]);
}

json_response(['error' => 'Invalid action'], 400);
