<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$boards = fetch_all('SELECT * FROM boards');
$boardId = get_param('board_id') ?: ($boards[0]['id'] ?? null);
$board = $boardId ? fetch_one('SELECT * FROM boards WHERE id = :id', [':id' => $boardId]) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $cardId = (int)post_param('card_id', 0);
    $payload = [
        ':board_id' => post_param('board_id'),
        ':column_id' => post_param('column_id'),
        ':title' => post_param('title'),
        ':description' => post_param('description'),
        ':assignee_id' => post_param('assignee_id') ?: null,
        ':label_json' => json_encode(array_filter(array_map('trim', explode(',', (string)post_param('labels'))))),
        ':due_date' => post_param('due_date') ?: null,
    ];

    if ($cardId > 0) {
        $stmt = db()->prepare('UPDATE board_cards SET column_id=:column_id, title=:title, description=:description, assignee_id=:assignee_id, label_json=:label_json, due_date=:due_date, updated_at=CURRENT_TIMESTAMP WHERE id=:id AND board_id=:board_id');
        $stmt->execute($payload + [':id' => $cardId]);
        add_toast('Card updated', 'success');
    } else {
        $stmt = db()->prepare('INSERT INTO board_cards (board_id, column_id, title, description, assignee_id, label_json, due_date, order_index) VALUES (:board_id, :column_id, :title, :description, :assignee_id, :label_json, :due_date, :order_index)');
        $stmt->execute($payload + [':order_index' => 0]);
        add_toast('Card created', 'success');
    }

    redirect('/kanban.php?board_id=' . post_param('board_id'));
}

$columns = $boardId ? fetch_all('SELECT * FROM board_columns WHERE board_id = :id ORDER BY order_index', [':id' => $boardId]) : [];
$cards = $boardId ? fetch_all('SELECT * FROM board_cards WHERE board_id = :id ORDER BY order_index', [':id' => $boardId]) : [];
$users = fetch_all('SELECT * FROM users');

$cardsByColumn = [];
foreach ($cards as $card) {
    $cardsByColumn[$card['column_id']][] = $card;
}
?>
<div class="app-content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Kanban</h2>
    </div>

    <?php if ($board): ?>
        <div class="card p-3 mb-3">
            <form method="post" class="row g-2" data-draft-key="kanban-card" id="kanbanCardForm">
                <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                <input type="hidden" name="board_id" value="<?php echo $boardId; ?>">
                <input type="hidden" name="card_id" value="" id="kanbanCardId">
                <div class="col-md-3">
                    <input class="form-control" name="title" placeholder="Card title" required>
                </div>
                <div class="col-md-3">
                    <select name="column_id" class="form-select" required>
                        <?php foreach ($columns as $column): ?>
                            <option value="<?php echo $column['id']; ?>"><?php echo h($column['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <input class="form-control" name="labels" placeholder="labels,comma">
                </div>
                <div class="col-md-2">
                    <input class="form-control" type="date" name="due_date">
                </div>
                <div class="col-md-2">
                    <select name="assignee_id" class="form-select">
                        <option value="">Assignee</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?php echo $u['id']; ?>"><?php echo h($u['username']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <textarea class="form-control" name="description" rows="2" placeholder="Description"></textarea>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-primary" id="kanbanCardSubmit">Add Card</button>
                    <button type="button" class="btn btn-outline-secondary d-none" id="kanbanCardCancel">Cancel edit</button>
                </div>
            </form>
        </div>

        <div class="kanban-board">
            <?php foreach ($columns as $column): ?>
                <?php $columnCards = $cardsByColumn[$column['id']] ?? []; ?>
                <div class="kanban-column" data-column-id="<?php echo $column['id']; ?>">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <strong class="kanban-column-title"><?php echo h($column['name']); ?></strong>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-link text-muted p-0 kanban-gear-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fa-solid fa-gear"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-dark">
                                    <li><button class="dropdown-item kanban-column-edit" type="button" data-column-id="<?php echo $column['id']; ?>">Edit column</button></li>
                                    <li><button class="dropdown-item text-danger kanban-column-delete" type="button" data-column-id="<?php echo $column['id']; ?>">Delete column</button></li>
                                </ul>
                            </div>
                        </div>
                        <span class="badge text-bg-light"><?php echo count($columnCards); ?>/<?php echo h((string)($column['wip_limit'] ?? '-')); ?></span>
                    </div>
                    <div class="kanban-cards">
                        <?php foreach ($columnCards as $card): ?>
                            <div
                                class="kanban-card"
                                draggable="true"
                                data-card-id="<?php echo $card['id']; ?>"
                                data-column-id="<?php echo $card['column_id']; ?>"
                                data-title="<?php echo h($card['title']); ?>"
                                data-description="<?php echo h($card['description'] ?? ''); ?>"
                                data-labels="<?php echo h(implode(',', json_decode($card['label_json'] ?? '[]', true) ?: [])); ?>"
                                data-due-date="<?php echo h($card['due_date'] ?? ''); ?>"
                                data-assignee-id="<?php echo h((string)($card['assignee_id'] ?? '')); ?>"
                            >
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="fw-semibold"><?php echo h($card['title']); ?></div>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-link text-muted p-0 kanban-gear-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="fa-solid fa-gear"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-dark">
                                            <li><button class="dropdown-item kanban-card-edit" type="button" data-card-id="<?php echo $card['id']; ?>">Edit card</button></li>
                                            <li><button class="dropdown-item text-danger kanban-card-delete" type="button" data-card-id="<?php echo $card['id']; ?>">Delete card</button></li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="text-muted small"><?php echo h($card['description']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="card p-3">No boards configured.</div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
