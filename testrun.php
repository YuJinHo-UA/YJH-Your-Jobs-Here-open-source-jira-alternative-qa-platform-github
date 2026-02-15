<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$user = current_user();
$id = (int)get_param('id');
$run = fetch_one('SELECT * FROM test_runs WHERE id = :id', [':id' => $id]);
if (!$run) {
    echo '<div class="app-content">Test run not found</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $executionId = (int)post_param('execution_id');
    $status = post_param('status');
    $actual = post_param('actual_result');
    $notes = post_param('notes');

    $stmt = db()->prepare('UPDATE test_executions SET status=:status, actual_result=:actual_result, notes=:notes, executed_by=:executed_by, executed_at=CURRENT_TIMESTAMP WHERE id=:id');
    $stmt->execute([
        ':status' => $status,
        ':actual_result' => $actual,
        ':notes' => $notes,
        ':executed_by' => $user['id'],
        ':id' => $executionId,
    ]);

    if ($status === 'fail' && post_param('create_bug') === '1') {
        $case = fetch_one('SELECT * FROM test_cases WHERE id = :id', [':id' => post_param('case_id')]);
        if ($case) {
            $stmt = db()->prepare('INSERT INTO bugs (project_id, title, description, steps_to_reproduce, expected_result, actual_result, severity, priority, status, reporter_id) VALUES (:project_id, :title, :description, :steps, :expected, :actual, :severity, :priority, :status, :reporter_id)');
            $stmt->execute([
                ':project_id' => 1,
                ':title' => 'Failed test: ' . $case['title'],
                ':description' => $case['description'],
                ':steps' => $case['steps_json'],
                ':expected' => $case['expected_result_json'],
                ':actual' => $actual,
                ':severity' => 'major',
                ':priority' => 'high',
                ':status' => 'new',
                ':reporter_id' => $user['id'],
            ]);
            $bugId = (int)db()->lastInsertId();
            $stmt = db()->prepare('UPDATE test_executions SET bug_id=:bug_id WHERE id=:id');
            $stmt->execute([':bug_id' => $bugId, ':id' => $executionId]);
        }
    }

    add_toast('Execution updated', 'success');
    redirect('/testrun.php?id=' . $id);
}

$executions = fetch_all('SELECT te.*, tc.title FROM test_executions te JOIN test_cases tc ON tc.id=te.test_case_id WHERE te.test_run_id = :id', [':id' => $id]);
?>
<div class="app-content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><?php echo h($run['name']); ?></h2>
        <a class="btn btn-outline-secondary" href="/testruns.php">Back</a>
    </div>

    <div class="card p-3">
        <table class="table">
            <thead>
                <tr>
                    <th>Case</th>
                    <th>Status</th>
                    <th>Actual Result</th>
                    <th>Notes</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($executions as $execution): ?>
                <form method="post">
                <tr>
                    <td><?php echo h($execution['title']); ?></td>
                    <td class="d-flex gap-2 align-items-center">
                            <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                            <input type="hidden" name="execution_id" value="<?php echo $execution['id']; ?>">
                            <input type="hidden" name="case_id" value="<?php echo $execution['test_case_id']; ?>">
                            <select name="status" class="form-select form-select-sm">
                                <?php foreach (['pass','fail','blocked','not_tested','skipped'] as $status): ?>
                                    <option value="<?php echo $status; ?>" <?php echo $execution['status'] === $status ? 'selected' : ''; ?>><?php echo $status; ?></option>
                                <?php endforeach; ?>
                            </select>
                    </td>
                    <td><input name="actual_result" class="form-control form-control-sm" value="<?php echo h($execution['actual_result'] ?? ''); ?>"></td>
                    <td><input name="notes" class="form-control form-control-sm" value="<?php echo h($execution['notes'] ?? ''); ?>"></td>
                    <td class="d-flex gap-2">
                        <button class="btn btn-sm btn-primary">Save</button>
                        <?php if ($execution['status'] === 'fail' && !$execution['bug_id']): ?>
                            <button class="btn btn-sm btn-outline-danger" name="create_bug" value="1">Create bug</button>
                        <?php endif; ?>
                    </td>
                </tr>
                </form>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
