<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$id = (int)get_param('id');
$user = current_user();
$plan = fetch_one('SELECT * FROM test_plans WHERE id = :id', [':id' => $id]);
if (!$plan) {
    echo '<div class="app-content">Test plan not found</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)post_param('action');
    if ($action === 'delete_suite') {
        $suiteId = (int)post_param('suite_id');
        if ($suiteId > 0) {
            delete_row('test_suites', ['id' => $suiteId]);
            add_toast('Suite deleted', 'success');
        }
        redirect('/testplan.php?id=' . $id);
    }
    if ($action === 'delete_case') {
        $caseId = (int)post_param('case_id');
        if ($caseId > 0) {
            delete_row('test_cases', ['id' => $caseId]);
            add_toast('Test case deleted', 'success');
        }
        redirect('/testplan.php?id=' . $id);
    }
    if ($action === 'update_suite') {
        $suiteId = (int)post_param('suite_id');
        $suiteName = trim((string)post_param('suite_name'));
        $suiteDescription = trim((string)post_param('suite_description'));
        $orderIndex = (int)post_param('order_index');
        if ($suiteId > 0 && $suiteName !== '') {
            $stmt = db()->prepare('UPDATE test_suites SET name=:name, description=:description, order_index=:order_index WHERE id=:id AND plan_id=:plan_id');
            $stmt->execute([
                ':name' => $suiteName,
                ':description' => $suiteDescription,
                ':order_index' => $orderIndex,
                ':id' => $suiteId,
                ':plan_id' => $id,
            ]);
            add_toast('Suite updated', 'success');
        }
        redirect('/testplan.php?id=' . $id);
    }
    if ($action === 'update_case') {
        $caseId = (int)post_param('case_id');
        $suiteId = (int)post_param('suite_id');
        $title = trim((string)post_param('title'));
        $priority = (string)post_param('priority');
        $expected = trim((string)post_param('expected_result'));
        $actual = trim((string)post_param('actual_result'));
        $status = (string)post_param('execution_status');
        $resultLabel = trim((string)post_param('result_label'));
        $noteLabel = trim((string)post_param('note_label'));
        if ($caseId > 0 && $title !== '' && in_array($priority, ['critical', 'high', 'medium', 'low'], true)) {
            $stmt = db()->prepare('UPDATE test_cases SET suite_id=:suite_id, title=:title, priority=:priority, expected_result_json=:expected_result_json, updated_by=:updated_by, updated_at=CURRENT_TIMESTAMP WHERE id=:id');
            $stmt->execute([
                ':suite_id' => $suiteId,
                ':title' => $title,
                ':priority' => $priority,
                ':expected_result_json' => json_encode($expected !== '' ? [$expected] : [], JSON_UNESCAPED_UNICODE),
                ':updated_by' => $user['id'],
                ':id' => $caseId,
            ]);

            if (in_array($status, ['pass', 'fail', 'blocked', 'not_tested', 'skipped'], true)) {
                $latestExecution = fetch_one(
                    'SELECT te.id
                     FROM test_executions te
                     JOIN test_runs tr ON tr.id = te.test_run_id
                     WHERE te.test_case_id = :case_id AND tr.plan_id = :plan_id
                     ORDER BY te.executed_at DESC, te.id DESC
                     LIMIT 1',
                    [':case_id' => $caseId, ':plan_id' => $id]
                );

                $notes = trim($resultLabel . ($noteLabel !== '' ? (' | ' . $noteLabel) : ''));
                if ($latestExecution) {
                    $executionStmt = db()->prepare('UPDATE test_executions SET status=:status, actual_result=:actual_result, notes=:notes, executed_by=:executed_by, executed_at=CURRENT_TIMESTAMP WHERE id=:id');
                    $executionStmt->execute([
                        ':status' => $status,
                        ':actual_result' => $actual,
                        ':notes' => $notes,
                        ':executed_by' => $user['id'],
                        ':id' => (int)$latestExecution['id'],
                    ]);
                } else {
                    $runStmt = db()->prepare('INSERT INTO test_runs (plan_id, name, description, status, assigned_to, created_by) VALUES (:plan_id, :name, :description, :status, :assigned_to, :created_by)');
                    $runStmt->execute([
                        ':plan_id' => $id,
                        ':name' => 'Inline Updates',
                        ':description' => 'Auto-created run for inline case updates',
                        ':status' => 'in_progress',
                        ':assigned_to' => $user['id'],
                        ':created_by' => $user['id'],
                    ]);
                    $runId = (int)db()->lastInsertId();
                    $executionStmt = db()->prepare('INSERT INTO test_executions (test_run_id, test_case_id, executed_by, status, actual_result, notes) VALUES (:test_run_id, :test_case_id, :executed_by, :status, :actual_result, :notes)');
                    $executionStmt->execute([
                        ':test_run_id' => $runId,
                        ':test_case_id' => $caseId,
                        ':executed_by' => $user['id'],
                        ':status' => $status,
                        ':actual_result' => $actual,
                        ':notes' => $notes,
                    ]);
                }
            }
            add_toast('Test case row updated', 'success');
        }
        redirect('/testplan.php?id=' . $id);
    }
    if (post_param('suite_name')) {
        $stmt = db()->prepare('INSERT INTO test_suites (plan_id, parent_suite_id, name, description, order_index) VALUES (:plan_id, :parent_suite_id, :name, :description, :order_index)');
        $stmt->execute([
            ':plan_id' => $id,
            ':parent_suite_id' => post_param('parent_suite_id') ?: null,
            ':name' => post_param('suite_name'),
            ':description' => post_param('suite_description'),
            ':order_index' => 0,
        ]);
        record_activity('created', 'test_suite', (int)db()->lastInsertId(), [
            'plan_id' => $id,
            'name' => (string)post_param('suite_name'),
        ]);
        add_toast('Suite created', 'success');
        redirect('/testplan.php?id=' . $id);
    }
    if ($action === 'import_yjh_cases') {
        $suite = fetch_one('SELECT id FROM test_suites WHERE plan_id = :plan_id AND name = :name LIMIT 1', [
            ':plan_id' => $id,
            ':name' => 'YJH Full Coverage Suite',
        ]);

        if (!$suite) {
            $stmt = db()->prepare('INSERT INTO test_suites (plan_id, parent_suite_id, name, description, order_index) VALUES (:plan_id, :parent_suite_id, :name, :description, :order_index)');
            $stmt->execute([
                ':plan_id' => $id,
                ':parent_suite_id' => null,
                ':name' => 'YJH Full Coverage Suite',
                ':description' => 'Imported from YJH full testcase matrix',
                ':order_index' => 999,
            ]);
            $suiteId = (int)db()->lastInsertId();
        } else {
            $suiteId = (int)$suite['id'];
        }

        $casesToImport = [
            ['code' => 'TC-001', 'title' => 'Успішний вхід', 'priority' => 'critical', 'result' => '✅ Pass', 'note' => '', 'expected' => 'Користувач успішно входить в систему', 'actual' => 'Користувач увійшов', 'status' => 'pass'],
            ['code' => 'TC-002', 'title' => 'Вхід з невірним паролем', 'priority' => 'high', 'result' => '✅ Pass', 'note' => '', 'expected' => 'Відображається повідомлення «Невірний пароль»', 'actual' => 'Повідомлення відображено', 'status' => 'pass'],
            ['code' => 'TC-003', 'title' => 'Створення багу', 'priority' => 'critical', 'result' => '✅ Pass', 'note' => '', 'expected' => 'Баг створено успішно', 'actual' => 'Створення багу пройшло', 'status' => 'pass'],
            ['code' => 'TC-004', 'title' => 'Зміна статусу багу', 'priority' => 'high', 'result' => '✅ Pass', 'note' => '', 'expected' => 'Статус змінюється на обраний', 'actual' => 'Статус успішно змінено', 'status' => 'pass'],
            ['code' => 'TC-005', 'title' => 'Виконання тест-кейсу', 'priority' => 'high', 'result' => '✅ Pass', 'note' => '', 'expected' => 'Тест-кейс можна виконати', 'actual' => 'Виконано', 'status' => 'pass'],
            ['code' => 'TC-006', 'title' => 'Створення багу з тест-кейсу', 'priority' => 'high', 'result' => '✅ Pass', 'note' => '', 'expected' => 'Баг автоматично пов’язаний з тест-кейсом', 'actual' => 'Баг створено та прив’язано', 'status' => 'pass'],
            ['code' => 'TC-007', 'title' => 'Drag & drop канбан', 'priority' => 'high', 'result' => '✅ Pass', 'note' => '', 'expected' => 'Задачу можна перетягнути на іншу колонку', 'actual' => 'Перетягування працює', 'status' => 'pass'],
            ['code' => 'TC-008', 'title' => 'Створення Wiki', 'priority' => 'medium', 'result' => '✅ Pass', 'note' => '', 'expected' => 'Сторінка Wiki створюється', 'actual' => 'Створення успішне', 'status' => 'pass'],
            ['code' => 'TC-009', 'title' => 'Версіонування Wiki', 'priority' => 'medium', 'result' => '✅ Pass', 'note' => '', 'expected' => 'Можна переглядати історію змін', 'actual' => 'Версії відображаються', 'status' => 'pass'],
            ['code' => 'TC-010', 'title' => 'Глобальний пошук', 'priority' => 'medium', 'result' => '✅ Pass', 'note' => '', 'expected' => 'Пошук по всій платформі знаходить об’єкти', 'actual' => 'Результати пошуку коректні', 'status' => 'pass'],
            ['code' => 'TC-011', 'title' => 'Налаштування 2FA', 'priority' => 'high', 'result' => '⚠️ Env Issue', 'note' => 'Потребує модуль GD', 'expected' => 'Користувач може налаштувати 2FA', 'actual' => 'Неможливо через відсутність GD', 'status' => 'blocked'],
            ['code' => 'TC-012', 'title' => 'Вхід з 2FA', 'priority' => 'high', 'result' => '⚠️ Env Issue', 'note' => 'Залежить від TC-011', 'expected' => 'Користувач входить після введення коду 2FA', 'actual' => 'Неможливо через TC-011', 'status' => 'blocked'],
            ['code' => 'TC-013', 'title' => 'XSS-захист', 'priority' => 'critical', 'result' => '✅ Pass', 'note' => '', 'expected' => 'Форма захищена від XSS', 'actual' => 'Захист працює', 'status' => 'pass'],
            ['code' => 'TC-014', 'title' => 'Дашборд та графіки', 'priority' => 'medium', 'result' => '✅ Pass', 'note' => '', 'expected' => 'Графіки коректно відображаються', 'actual' => 'Відображення нормальне', 'status' => 'pass'],
            ['code' => 'TC-015', 'title' => 'AI-асистент', 'priority' => 'medium', 'result' => '❌ Not Available', 'note' => 'Потребує OpenAI API ключ', 'expected' => 'AI відповідає на запити', 'actual' => 'Не працює без ключа', 'status' => 'fail'],
        ];

        $runStmt = db()->prepare('INSERT INTO test_runs (plan_id, name, description, status, assigned_to, created_by) VALUES (:plan_id, :name, :description, :status, :assigned_to, :created_by)');
        $runStmt->execute([
            ':plan_id' => $id,
            ':name' => 'YJH Full Table Execution',
            ':description' => 'Imported execution results for YJH full testcase table',
            ':status' => 'completed',
            ':assigned_to' => $user['id'],
            ':created_by' => $user['id'],
        ]);
        $runId = (int)db()->lastInsertId();

        $caseStmt = db()->prepare('INSERT INTO test_cases (suite_id, title, description, preconditions, steps_json, expected_result_json, checklist_json, type, priority, estimated_time, automation_status, created_by) VALUES (:suite_id, :title, :description, :preconditions, :steps_json, :expected_result_json, :checklist_json, :type, :priority, :estimated_time, :automation_status, :created_by)');
        $executionStmt = db()->prepare('INSERT INTO test_executions (test_run_id, test_case_id, executed_by, status, actual_result, notes) VALUES (:test_run_id, :test_case_id, :executed_by, :status, :actual_result, :notes)');

        $importedCount = 0;
        foreach ($casesToImport as $row) {
            $fullTitle = $row['code'] . ' - ' . $row['title'];
            $exists = fetch_one('SELECT id FROM test_cases WHERE suite_id = :suite_id AND title = :title LIMIT 1', [
                ':suite_id' => $suiteId,
                ':title' => $fullTitle,
            ]);
            if ($exists) {
                continue;
            }

            $caseStmt->execute([
                ':suite_id' => $suiteId,
                ':title' => $fullTitle,
                ':description' => $row['note'] !== '' ? ('Note: ' . $row['note']) : null,
                ':preconditions' => null,
                ':steps_json' => json_encode([], JSON_UNESCAPED_UNICODE),
                ':expected_result_json' => json_encode([$row['expected']], JSON_UNESCAPED_UNICODE),
                ':checklist_json' => json_encode([], JSON_UNESCAPED_UNICODE),
                ':type' => 'functional',
                ':priority' => $row['priority'],
                ':estimated_time' => null,
                ':automation_status' => 'manual',
                ':created_by' => $user['id'],
            ]);
            $caseId = (int)db()->lastInsertId();

            $notes = trim($row['result'] . ($row['note'] !== '' ? (' | ' . $row['note']) : ''));
            $executionStmt->execute([
                ':test_run_id' => $runId,
                ':test_case_id' => $caseId,
                ':executed_by' => $user['id'],
                ':status' => $row['status'],
                ':actual_result' => $row['actual'],
                ':notes' => $notes,
            ]);
            $importedCount++;
        }

        record_activity('imported', 'test_plan', $id, [
            'imported_cases' => $importedCount,
            'source' => 'yjh_full_table',
        ]);
        add_toast('YJH table imported: ' . $importedCount . ' test cases', 'success');
        redirect('/testplan.php?id=' . $id);
    }
}

$suites = fetch_all('SELECT * FROM test_suites WHERE plan_id = :id ORDER BY order_index', [':id' => $id]);
$cases = fetch_all(
    'SELECT
        tc.*,
        ts.name AS suite_name,
        te.status AS execution_status,
        te.actual_result AS execution_actual_result,
        te.notes AS execution_notes
    FROM test_cases tc
    JOIN test_suites ts ON ts.id = tc.suite_id
    LEFT JOIN test_executions te ON te.id = (
        SELECT te2.id
        FROM test_executions te2
        WHERE te2.test_case_id = tc.id
        ORDER BY te2.executed_at DESC, te2.id DESC
        LIMIT 1
    )
    WHERE ts.plan_id = :id
    ORDER BY tc.id ASC',
    [':id' => $id]
);
?>
<div class="app-content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><?php echo h($plan['name']); ?></h2>
        <a class="btn btn-outline-secondary" href="/testplans.php">Back</a>
    </div>
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card p-3">
                <h6>Create Suite</h6>
                <form method="post" data-draft-key="suite-<?php echo $id; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                    <input class="form-control mb-2" name="suite_name" placeholder="Suite name" required>
                    <textarea class="form-control mb-2" name="suite_description" placeholder="Description"></textarea>
                    <select class="form-select mb-2" name="parent_suite_id">
                        <option value="">No parent</option>
                        <?php foreach ($suites as $suite): ?>
                            <option value="<?php echo $suite['id']; ?>"><?php echo h($suite['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-primary">Save</button>
                </form>
                <div class="mt-4">
                    <h6>Suites</h6>
                    <?php foreach ($suites as $suite): ?>
                        <form method="post" class="border rounded p-2 mb-2">
                            <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                            <input type="hidden" name="action" value="update_suite">
                            <input type="hidden" name="suite_id" value="<?php echo (int)$suite['id']; ?>">
                            <input class="form-control form-control-sm mb-1" name="suite_name" value="<?php echo h($suite['name']); ?>" required>
                            <textarea class="form-control form-control-sm mb-1" name="suite_description" rows="2"><?php echo h((string)($suite['description'] ?? '')); ?></textarea>
                            <input class="form-control form-control-sm mb-2" type="number" name="order_index" value="<?php echo (int)($suite['order_index'] ?? 0); ?>">
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-outline-primary" type="submit">Update</button>
                                <button class="btn btn-sm btn-outline-danger" type="submit" formaction="/testplan.php?id=<?php echo $id; ?>" name="action" value="delete_suite" onclick="return confirm('Delete suite?');">Delete</button>
                            </div>
                        </form>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Test Cases</h6>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                        <input type="hidden" name="action" value="import_yjh_cases">
                        <button class="btn btn-sm btn-outline-success" type="submit">Import YJH Full Table</button>
                    </form>
                </div>
                <div class="table-responsive test-cases-table-wrap">
                <table class="table test-cases-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Status</th>
                            <th>Details</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($cases as $case): ?>
                        <?php
                        $code = '#' . (int)$case['id'];
                        if (preg_match('/^(TC-\d{3})\s*-\s*/u', (string)$case['title'], $matches)) {
                            $code = $matches[1];
                        }
                        $expectedList = json_decode((string)$case['expected_result_json'], true) ?: [];
                        $expectedPreview = (string)($expectedList[0] ?? '');
                        $executionStatus = (string)($case['execution_status'] ?? '');
                        $executionActual = (string)($case['execution_actual_result'] ?? '');
                        $executionNotes = (string)($case['execution_notes'] ?? '');
                        $resultLabel = '';
                        $noteLabel = '';
                        if ($executionNotes !== '') {
                            $parts = explode('|', $executionNotes, 2);
                            $resultLabel = trim((string)($parts[0] ?? ''));
                            $noteLabel = trim((string)($parts[1] ?? ''));
                        }
                        ?>
                        <tr>
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                                <input type="hidden" name="action" value="update_case">
                                <input type="hidden" name="case_id" value="<?php echo (int)$case['id']; ?>">
                                <td>
                                    <span class="d-none d-lg-inline"><?php echo h($code); ?></span>
                                    <span class="d-inline d-lg-none fw-semibold">ID: <?php echo h($code); ?></span>
                                </td>
                                <td>
                                    <input class="form-control form-control-sm" name="title" value="<?php echo h((string)$case['title']); ?>" required>
                                </td>
                                <td>
                                    <select class="form-select form-select-sm" name="execution_status">
                                        <?php foreach (['pass', 'fail', 'blocked', 'not_tested', 'skipped'] as $status): ?>
                                            <option value="<?php echo $status; ?>" <?php echo $executionStatus === $status ? 'selected' : ''; ?>><?php echo $status; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <details class="tc-inline-details">
                                        <summary>Edit more fields</summary>
                                        <div class="tc-inline-grid mt-2">
                                            <div>
                                                <label class="form-label form-label-sm">Suite</label>
                                                <select class="form-select form-select-sm" name="suite_id">
                                                    <?php foreach ($suites as $suiteOption): ?>
                                                        <option value="<?php echo (int)$suiteOption['id']; ?>" <?php echo (int)$suiteOption['id'] === (int)$case['suite_id'] ? 'selected' : ''; ?>>
                                                            <?php echo h($suiteOption['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="form-label form-label-sm">Priority</label>
                                                <select class="form-select form-select-sm" name="priority">
                                                    <?php foreach (['critical', 'high', 'medium', 'low'] as $priority): ?>
                                                        <option value="<?php echo $priority; ?>" <?php echo (string)$case['priority'] === $priority ? 'selected' : ''; ?>><?php echo $priority; ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="form-label form-label-sm">Result</label>
                                                <input class="form-control form-control-sm" name="result_label" value="<?php echo h($resultLabel); ?>">
                                            </div>
                                            <div>
                                                <label class="form-label form-label-sm">Note</label>
                                                <input class="form-control form-control-sm" name="note_label" value="<?php echo h($noteLabel); ?>">
                                            </div>
                                            <div>
                                                <label class="form-label form-label-sm">Expected Result</label>
                                                <input class="form-control form-control-sm" name="expected_result" value="<?php echo h($expectedPreview); ?>">
                                            </div>
                                            <div>
                                                <label class="form-label form-label-sm">Actual Result</label>
                                                <input class="form-control form-control-sm" name="actual_result" value="<?php echo h($executionActual); ?>">
                                            </div>
                                        </div>
                                    </details>
                                </td>
                                <td class="d-flex gap-2">
                                    <button class="btn btn-sm btn-outline-primary" type="submit">Update</button>
                                    <a class="btn btn-sm btn-outline-secondary" href="/testcase.php?id=<?php echo $case['id']; ?>">Open</a>
                                    <button class="btn btn-sm btn-outline-danger" type="submit" name="action" value="delete_case" onclick="return confirm('Delete test case?');">Delete</button>
                                </td>
                            </form>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <a class="btn btn-outline-primary" href="/testcase.php?plan_id=<?php echo $id; ?>">Create Test Case</a>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
