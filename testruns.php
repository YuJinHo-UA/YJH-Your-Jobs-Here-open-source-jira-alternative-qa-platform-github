<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$user = current_user();
$plans = fetch_all('SELECT * FROM test_plans');
$users = fetch_all('SELECT * FROM users');
$resultFilter = get_param('result');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $stmt = db()->prepare('INSERT INTO test_runs (plan_id, name, description, status, assigned_to, created_by) VALUES (:plan_id, :name, :description, :status, :assigned_to, :created_by)');
    $stmt->execute([
        ':plan_id' => post_param('plan_id'),
        ':name' => post_param('name'),
        ':description' => post_param('description'),
        ':status' => 'in_progress',
        ':assigned_to' => post_param('assigned_to') ?: null,
        ':created_by' => $user['id'],
    ]);
    $runId = (int)db()->lastInsertId();

    $cases = fetch_all('SELECT tc.id FROM test_cases tc JOIN test_suites ts ON ts.id=tc.suite_id WHERE ts.plan_id = :id', [':id' => post_param('plan_id')]);
    $insert = db()->prepare('INSERT INTO test_executions (test_run_id, test_case_id, executed_by, status) VALUES (:run_id, :case_id, :executed_by, :status)');
    foreach ($cases as $case) {
        $insert->execute([
            ':run_id' => $runId,
            ':case_id' => $case['id'],
            ':executed_by' => $user['id'],
            ':status' => 'not_tested',
        ]);
    }

    add_toast('Test run created', 'success');
    redirect('/testrun.php?id=' . $runId);
}

$sql = 'SELECT tr.*, tp.name as plan_name FROM test_runs tr JOIN test_plans tp ON tp.id=tr.plan_id WHERE 1=1';
$params = [];
if (in_array($resultFilter, ['pass', 'fail'], true)) {
    $sql .= ' AND EXISTS (
        SELECT 1
        FROM test_executions te
        WHERE te.test_run_id = tr.id AND te.status = :result
    )';
    $params[':result'] = $resultFilter;
}
$sql .= ' ORDER BY tr.created_at DESC';
$runs = fetch_all($sql, $params);
?>
<div class="app-content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Test Runs</h2>
    </div>
    <?php if (in_array($resultFilter, ['pass', 'fail'], true)): ?>
        <div class="alert alert-info py-2">
            Filter: runs with <strong><?php echo h($resultFilter); ?></strong> results
            <a class="ms-2" href="/testruns.php">clear</a>
        </div>
    <?php endif; ?>
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card p-3">
                <h6>Create Test Run</h6>
                <form method="post" data-draft-key="testrun">
                    <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                    <select class="form-select mb-2" name="plan_id" required>
                        <?php foreach ($plans as $plan): ?>
                            <option value="<?php echo $plan['id']; ?>"><?php echo h($plan['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input class="form-control mb-2" name="name" placeholder="Run name" required>
                    <textarea class="form-control mb-2" name="description"></textarea>
                    <select class="form-select mb-2" name="assigned_to">
                        <option value="">Unassigned</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?php echo $u['id']; ?>"><?php echo h($u['username']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-primary">Save</button>
                </form>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card p-3">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Plan</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($runs as $run): ?>
                        <tr>
                            <td><?php echo h($run['name']); ?></td>
                            <td><?php echo h($run['plan_name']); ?></td>
                            <td><?php echo h($run['status']); ?></td>
                            <td><a href="/testrun.php?id=<?php echo $run['id']; ?>">Execute</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
