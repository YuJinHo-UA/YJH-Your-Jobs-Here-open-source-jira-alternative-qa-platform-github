<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$boards = fetch_all('SELECT * FROM boards');
$boardId = get_param('board_id') ?: ($boards[0]['id'] ?? null);
$board = $boardId ? fetch_one('SELECT * FROM boards WHERE id = :id', [':id' => $boardId]) : null;

function resolve_kanban_target_columns(array $columns): array
{
    if (!$columns) {
        return ['todo' => 0, 'in_progress' => 0, 'review' => 0, 'done' => 0];
    }
    $firstId = (int)$columns[0]['id'];
    $targets = ['todo' => $firstId, 'in_progress' => $firstId, 'review' => $firstId, 'done' => $firstId];

    foreach ($columns as $column) {
        $name = mb_strtolower(trim((string)$column['name']));
        $id = (int)$column['id'];
        if (str_contains($name, 'todo') || str_contains($name, 'to do') || str_contains($name, 'backlog')) {
            $targets['todo'] = $id;
        }
        if (str_contains($name, 'progress') || str_contains($name, 'doing') || str_contains($name, 'work')) {
            $targets['in_progress'] = $id;
        }
        if (str_contains($name, 'review') || str_contains($name, 'test') || str_contains($name, 'qa')) {
            $targets['review'] = $id;
        }
        if (str_contains($name, 'done') || str_contains($name, 'closed') || str_contains($name, 'complete')) {
            $targets['done'] = $id;
        }
    }

    return $targets;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)post_param('action', 'save_card');

    if ($action === 'add_open_bugs' && $board) {
        $columnId = (int)post_param('column_id');
        if ($columnId <= 0) {
            add_toast('Select target column first', 'warning');
            redirect('/kanban.php?board_id=' . $boardId);
        }
        $openBugs = fetch_all(
            "SELECT id, title, description, assignee_id, due_date
             FROM bugs
             WHERE project_id = :project_id
               AND status NOT IN ('closed','verified','fixed')
             ORDER BY id DESC",
            [':project_id' => (int)$board['project_id']]
        );
        $insertStmt = db()->prepare(
            'INSERT INTO board_cards (board_id, column_id, bug_id, title, description, assignee_id, label_json, due_date, order_index)
             VALUES (:board_id, :column_id, :bug_id, :title, :description, :assignee_id, :label_json, :due_date, :order_index)'
        );
        $existsStmt = db()->prepare('SELECT 1 FROM board_cards WHERE board_id = :board_id AND bug_id = :bug_id LIMIT 1');
        $created = 0;
        foreach ($openBugs as $bug) {
            $existsStmt->execute([':board_id' => (int)$boardId, ':bug_id' => (int)$bug['id']]);
            if ($existsStmt->fetchColumn()) {
                continue;
            }
            $insertStmt->execute([
                ':board_id' => (int)$boardId,
                ':column_id' => $columnId,
                ':bug_id' => (int)$bug['id'],
                ':title' => 'BUG #' . (int)$bug['id'] . ': ' . (string)$bug['title'],
                ':description' => (string)($bug['description'] ?? ''),
                ':assignee_id' => $bug['assignee_id'] ?: null,
                ':label_json' => json_encode(['bug']),
                ':due_date' => $bug['due_date'] ?: null,
                ':order_index' => 0,
            ]);
            $created++;
        }
        add_toast('Added open bugs to board: ' . $created, 'success');
        redirect('/kanban.php?board_id=' . $boardId);
    }

    if ($action === 'add_open_testcases' && $board) {
        $columnId = (int)post_param('column_id');
        if ($columnId <= 0) {
            add_toast('Select target column first', 'warning');
            redirect('/kanban.php?board_id=' . $boardId);
        }
        $openCases = fetch_all(
            "SELECT tc.id, tc.title, tc.description, tc.priority
             FROM test_cases tc
             JOIN test_suites ts ON ts.id = tc.suite_id
             JOIN test_plans tp ON tp.id = ts.plan_id
             WHERE tp.project_id = :project_id
             ORDER BY tc.id DESC",
            [':project_id' => (int)$board['project_id']]
        );
        $insertStmt = db()->prepare(
            'INSERT INTO board_cards (board_id, column_id, test_case_id, title, description, assignee_id, label_json, due_date, order_index)
             VALUES (:board_id, :column_id, :test_case_id, :title, :description, :assignee_id, :label_json, :due_date, :order_index)'
        );
        $existsStmt = db()->prepare('SELECT 1 FROM board_cards WHERE board_id = :board_id AND test_case_id = :test_case_id LIMIT 1');
        $created = 0;
        foreach ($openCases as $case) {
            $existsStmt->execute([':board_id' => (int)$boardId, ':test_case_id' => (int)$case['id']]);
            if ($existsStmt->fetchColumn()) {
                continue;
            }
            $insertStmt->execute([
                ':board_id' => (int)$boardId,
                ':column_id' => $columnId,
                ':test_case_id' => (int)$case['id'],
                ':title' => 'TC #' . (int)$case['id'] . ': ' . (string)$case['title'],
                ':description' => (string)($case['description'] ?? ''),
                ':assignee_id' => null,
                ':label_json' => json_encode(['test-case', (string)$case['priority']]),
                ':due_date' => null,
                ':order_index' => 0,
            ]);
            $created++;
        }
        add_toast('Added test cases to board: ' . $created, 'success');
        redirect('/kanban.php?board_id=' . $boardId);
    }

    if ($action === 'sync_project_items' && $board) {
        $columns = fetch_all('SELECT * FROM board_columns WHERE board_id = :id ORDER BY order_index', [':id' => (int)$boardId]);
        $targets = resolve_kanban_target_columns($columns);
        $insertStmt = db()->prepare(
            'INSERT INTO board_cards (board_id, column_id, bug_id, test_case_id, wiki_page_id, title, description, assignee_id, label_json, due_date, order_index)
             VALUES (:board_id, :column_id, :bug_id, :test_case_id, :wiki_page_id, :title, :description, :assignee_id, :label_json, :due_date, :order_index)'
        );

        $created = 0;
        $existsBug = db()->prepare('SELECT 1 FROM board_cards WHERE board_id = :board_id AND bug_id = :id LIMIT 1');
        $existsCase = db()->prepare('SELECT 1 FROM board_cards WHERE board_id = :board_id AND test_case_id = :id LIMIT 1');
        $existsWiki = db()->prepare('SELECT 1 FROM board_cards WHERE board_id = :board_id AND wiki_page_id = :id LIMIT 1');

        $bugs = fetch_all(
            'SELECT id, title, description, status, assignee_id, due_date
             FROM bugs
             WHERE project_id = :project_id
             ORDER BY id DESC',
            [':project_id' => (int)$board['project_id']]
        );
        foreach ($bugs as $bug) {
            $existsBug->execute([':board_id' => (int)$boardId, ':id' => (int)$bug['id']]);
            if ($existsBug->fetchColumn()) {
                continue;
            }
            $status = (string)$bug['status'];
            $columnId = in_array($status, ['new', 'reopened'], true)
                ? $targets['todo']
                : (in_array($status, ['assigned', 'in_progress'], true)
                    ? $targets['in_progress']
                    : (in_array($status, ['fixed'], true) ? $targets['review'] : $targets['done']));
            $insertStmt->execute([
                ':board_id' => (int)$boardId,
                ':column_id' => $columnId,
                ':bug_id' => (int)$bug['id'],
                ':test_case_id' => null,
                ':wiki_page_id' => null,
                ':title' => 'BUG #' . (int)$bug['id'] . ': ' . (string)$bug['title'],
                ':description' => (string)($bug['description'] ?? ''),
                ':assignee_id' => $bug['assignee_id'] ?: null,
                ':label_json' => json_encode(['bug', $status]),
                ':due_date' => $bug['due_date'] ?: null,
                ':order_index' => 0,
            ]);
            $created++;
        }

        $testCases = fetch_all(
            'SELECT tc.id, tc.title, tc.description, tc.priority
             FROM test_cases tc
             JOIN test_suites ts ON ts.id = tc.suite_id
             JOIN test_plans tp ON tp.id = ts.plan_id
             WHERE tp.project_id = :project_id
             ORDER BY tc.id DESC',
            [':project_id' => (int)$board['project_id']]
        );
        foreach ($testCases as $case) {
            $existsCase->execute([':board_id' => (int)$boardId, ':id' => (int)$case['id']]);
            if ($existsCase->fetchColumn()) {
                continue;
            }
            $insertStmt->execute([
                ':board_id' => (int)$boardId,
                ':column_id' => $targets['review'],
                ':bug_id' => null,
                ':test_case_id' => (int)$case['id'],
                ':wiki_page_id' => null,
                ':title' => 'TC #' . (int)$case['id'] . ': ' . (string)$case['title'],
                ':description' => (string)($case['description'] ?? ''),
                ':assignee_id' => null,
                ':label_json' => json_encode(['test-case', (string)$case['priority']]),
                ':due_date' => null,
                ':order_index' => 0,
            ]);
            $created++;
        }

        $wikiPages = fetch_all(
            'SELECT id, title, content
             FROM wiki_pages
             WHERE project_id = :project_id
             ORDER BY id DESC',
            [':project_id' => (int)$board['project_id']]
        );
        foreach ($wikiPages as $wiki) {
            $existsWiki->execute([':board_id' => (int)$boardId, ':id' => (int)$wiki['id']]);
            if ($existsWiki->fetchColumn()) {
                continue;
            }
            $insertStmt->execute([
                ':board_id' => (int)$boardId,
                ':column_id' => $targets['todo'],
                ':bug_id' => null,
                ':test_case_id' => null,
                ':wiki_page_id' => (int)$wiki['id'],
                ':title' => 'DOC #' . (int)$wiki['id'] . ': ' . (string)$wiki['title'],
                ':description' => mb_substr((string)($wiki['content'] ?? ''), 0, 400),
                ':assignee_id' => null,
                ':label_json' => json_encode(['documentation']),
                ':due_date' => null,
                ':order_index' => 0,
            ]);
            $created++;
        }

        add_toast('Project items distributed to Kanban: ' . $created, 'success');
        redirect('/kanban.php?board_id=' . $boardId);
    }

    $cardId = (int)post_param('card_id', 0);
    $cardType = (string)post_param('card_type', 'task');
    $sourceBugId = (int)post_param('source_bug_id', 0);
    $sourceTestCaseId = (int)post_param('source_test_case_id', 0);
    $resolvedTitle = (string)post_param('title');
    $resolvedDescription = (string)post_param('description');
    $resolvedAssignee = post_param('assignee_id') ?: null;
    $resolvedDueDate = post_param('due_date') ?: null;
    $bugId = null;
    $testCaseId = null;

    if ($cardType === 'bug' && $sourceBugId > 0) {
        $bug = fetch_one(
            'SELECT id, title, description, assignee_id, due_date
             FROM bugs
             WHERE id = :id AND project_id = :project_id',
            [':id' => $sourceBugId, ':project_id' => (int)$board['project_id']]
        );
        if ($bug) {
            $bugId = (int)$bug['id'];
            $resolvedTitle = 'BUG #' . (int)$bug['id'] . ': ' . (string)$bug['title'];
            $resolvedDescription = (string)($bug['description'] ?? '');
            $resolvedAssignee = $bug['assignee_id'] ?: null;
            $resolvedDueDate = $bug['due_date'] ?: null;
        }
    }
    if ($cardType === 'test_case' && $sourceTestCaseId > 0) {
        $case = fetch_one(
            'SELECT tc.id, tc.title, tc.description, tc.priority
             FROM test_cases tc
             JOIN test_suites ts ON ts.id = tc.suite_id
             JOIN test_plans tp ON tp.id = ts.plan_id
             WHERE tc.id = :id AND tp.project_id = :project_id',
            [':id' => $sourceTestCaseId, ':project_id' => (int)$board['project_id']]
        );
        if ($case) {
            $testCaseId = (int)$case['id'];
            $resolvedTitle = 'TC #' . (int)$case['id'] . ': ' . (string)$case['title'];
            $resolvedDescription = (string)($case['description'] ?? '');
        }
    }

    $labels = array_values(array_filter(array_map('trim', explode(',', (string)post_param('labels')))));
    if ($cardType === 'feature' && !in_array('feature', $labels, true)) {
        $labels[] = 'feature';
    }
    if ($cardType === 'bug' && !in_array('bug', $labels, true)) {
        $labels[] = 'bug';
    }
    if ($cardType === 'test_case' && !in_array('test-case', $labels, true)) {
        $labels[] = 'test-case';
    }

    $payload = [
        ':board_id' => post_param('board_id'),
        ':column_id' => post_param('column_id'),
        ':bug_id' => $bugId,
        ':test_case_id' => $testCaseId,
        ':title' => $resolvedTitle,
        ':description' => $resolvedDescription,
        ':assignee_id' => $resolvedAssignee,
        ':label_json' => json_encode($labels),
        ':due_date' => $resolvedDueDate,
    ];

    if ($cardId > 0) {
        $stmt = db()->prepare('UPDATE board_cards SET column_id=:column_id, bug_id=:bug_id, test_case_id=:test_case_id, title=:title, description=:description, assignee_id=:assignee_id, label_json=:label_json, due_date=:due_date, updated_at=CURRENT_TIMESTAMP WHERE id=:id AND board_id=:board_id');
        $stmt->execute($payload + [':id' => $cardId]);
        add_toast('Card updated', 'success');
    } else {
        $stmt = db()->prepare('INSERT INTO board_cards (board_id, column_id, bug_id, test_case_id, title, description, assignee_id, label_json, due_date, order_index) VALUES (:board_id, :column_id, :bug_id, :test_case_id, :title, :description, :assignee_id, :label_json, :due_date, :order_index)');
        $stmt->execute($payload + [':order_index' => 0]);
        add_toast('Card created', 'success');
    }

    redirect('/kanban.php?board_id=' . post_param('board_id'));
}

$columns = $boardId ? fetch_all('SELECT * FROM board_columns WHERE board_id = :id ORDER BY order_index', [':id' => $boardId]) : [];
$cards = $boardId ? fetch_all('SELECT * FROM board_cards WHERE board_id = :id ORDER BY order_index', [':id' => $boardId]) : [];
$users = fetch_all('SELECT * FROM users');
$projectBugs = $board
    ? fetch_all(
        "SELECT id, title, status
         FROM bugs
         WHERE project_id = :project_id
           AND status NOT IN ('closed','verified','fixed')
         ORDER BY id DESC",
        [':project_id' => (int)$board['project_id']]
    )
    : [];
$projectTestCases = $board
    ? fetch_all(
        "SELECT tc.id, tc.title
         FROM test_cases tc
         JOIN test_suites ts ON ts.id = tc.suite_id
         JOIN test_plans tp ON tp.id = ts.plan_id
         WHERE tp.project_id = :project_id
         ORDER BY tc.id DESC",
        [':project_id' => (int)$board['project_id']]
    )
    : [];

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
                <input type="hidden" name="action" value="save_card">
                <input type="hidden" name="card_id" value="" id="kanbanCardId">
                <div class="col-md-2">
                    <select name="card_type" class="form-select" id="kanbanCardType">
                        <option value="task">Task</option>
                        <option value="feature">Feature</option>
                        <option value="bug">Bug</option>
                        <option value="test_case">Test Case</option>
                    </select>
                </div>
                <div class="col-md-4 d-none" id="kanbanBugPickerWrap">
                    <select name="source_bug_id" class="form-select">
                        <option value="0">Select bug</option>
                        <?php foreach ($projectBugs as $bug): ?>
                            <option value="<?php echo (int)$bug['id']; ?>">#<?php echo (int)$bug['id']; ?> <?php echo h((string)$bug['title']); ?> (<?php echo h((string)$bug['status']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 d-none" id="kanbanTestCasePickerWrap">
                    <select name="source_test_case_id" class="form-select">
                        <option value="0">Select test case</option>
                        <?php foreach ($projectTestCases as $testCase): ?>
                            <option value="<?php echo (int)$testCase['id']; ?>">#<?php echo (int)$testCase['id']; ?> <?php echo h((string)$testCase['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
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
            <hr>
            <form method="post" class="row g-2">
                <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                <input type="hidden" name="board_id" value="<?php echo $boardId; ?>">
                <input type="hidden" name="action" value="add_open_bugs">
                <div class="col-md-4">
                    <select name="column_id" class="form-select" required>
                        <option value="">Import open bugs to column...</option>
                        <?php foreach ($columns as $column): ?>
                            <option value="<?php echo (int)$column['id']; ?>"><?php echo h((string)$column['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-outline-danger">Add all open bugs</button>
                </div>
            </form>
            <form method="post" class="row g-2 mt-1">
                <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                <input type="hidden" name="board_id" value="<?php echo $boardId; ?>">
                <input type="hidden" name="action" value="add_open_testcases">
                <div class="col-md-4">
                    <select name="column_id" class="form-select" required>
                        <option value="">Import test cases to column...</option>
                        <?php foreach ($columns as $column): ?>
                            <option value="<?php echo (int)$column['id']; ?>"><?php echo h((string)$column['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-outline-primary">Add all test cases</button>
                </div>
            </form>
            <form method="post" class="row g-2 mt-1">
                <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                <input type="hidden" name="board_id" value="<?php echo $boardId; ?>">
                <input type="hidden" name="action" value="sync_project_items">
                <div class="col-md-8">
                    <button class="btn btn-outline-success">Distribute all project items to Kanban</button>
                </div>
            </form>
        </div>

        <div class="kanban-add-column-wrap mb-3 text-center">
            <button type="button" class="btn btn-primary" id="kanbanAddColumnBtn" data-board-id="<?php echo (int)$boardId; ?>">
                <i class="fa-solid fa-plus me-1"></i>Add column
            </button>
        </div>

        <div class="kanban-add-column-wrap mb-3 text-center">
            <button type="button" class="btn btn-primary" id="kanbanAddColumnBtn" data-board-id="<?php echo (int)$boardId; ?>">
                <i class="fa-solid fa-plus me-1"></i>Add column
            </button>
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
                                data-bug-id="<?php echo h((string)($card['bug_id'] ?? '')); ?>"
                                data-test-case-id="<?php echo h((string)($card['test_case_id'] ?? '')); ?>"
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
                                <?php if ((int)($card['bug_id'] ?? 0) > 0): ?>
                                    <div class="mt-1"><a class="small" href="/bug.php?id=<?php echo (int)$card['bug_id']; ?>">Open linked bug #<?php echo (int)$card['bug_id']; ?></a></div>
                                <?php endif; ?>
                                <?php if ((int)($card['test_case_id'] ?? 0) > 0): ?>
                                    <div class="mt-1"><a class="small" href="/testcase.php?id=<?php echo (int)$card['test_case_id']; ?>">Open linked test case #<?php echo (int)$card['test_case_id']; ?></a></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="modal fade" id="kanbanColumnModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Create column</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label" for="kanbanColumnNameInput">Column name</label>
                        <input type="text" class="form-control" id="kanbanColumnNameInput" maxlength="100">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="kanbanColumnCreateSubmit">Create</button>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="card p-3">No boards configured.</div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
