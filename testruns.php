<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$user = current_user();
$projects = fetch_all('SELECT id, name FROM projects ORDER BY name');
$allPlans = fetch_all('SELECT * FROM test_plans');
$users = fetch_all('SELECT * FROM users');
$resultFilter = get_param('result');
$validResultFilters = ['pass', 'fail', 'in_progress'];
$selectedProjectId = (int)get_param('project_id', 0);
$availableProjectIds = array_map(static fn(array $project): int => (int)$project['id'], $projects);
if ($selectedProjectId > 0 && !in_array($selectedProjectId, $availableProjectIds, true)) {
    $selectedProjectId = 0;
}
$plans = $selectedProjectId > 0
    ? array_values(array_filter($allPlans, static fn(array $plan): bool => (int)$plan['project_id'] === $selectedProjectId))
    : $allPlans;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)post_param('action', 'create');
    $redirectProjectId = (int)post_param('redirect_project_id', $selectedProjectId);
    $redirectProjectQuery = $redirectProjectId > 0 ? ('?project_id=' . $redirectProjectId) : '';

    if ($action === 'delete') {
        $runId = (int)post_param('run_id');
        $run = fetch_one('SELECT id FROM test_runs WHERE id = :id', [':id' => $runId]);
        if (!$run) {
            add_toast('Test run not found', 'warning');
            redirect('/testruns.php' . $redirectProjectQuery);
        }

        db()->beginTransaction();
        try {
            $stmt = db()->prepare('DELETE FROM test_executions WHERE test_run_id = :id');
            $stmt->execute([':id' => $runId]);
            $stmt = db()->prepare('DELETE FROM test_runs WHERE id = :id');
            $stmt->execute([':id' => $runId]);
            db()->commit();
            add_toast('Test run deleted', 'success');
        } catch (Throwable $e) {
            db()->rollBack();
            add_toast('Unable to delete test run', 'danger');
        }

        redirect('/testruns.php' . $redirectProjectQuery);
    }

    if ($action === 'update') {
        $runId = (int)post_param('run_id');
        $run = fetch_one('SELECT id, completed_at FROM test_runs WHERE id = :id', [':id' => $runId]);
        if (!$run) {
            add_toast('Test run not found', 'warning');
            redirect('/testruns.php' . $redirectProjectQuery);
        }

        $status = (string)post_param('status', 'in_progress');
        $completedAt = null;
        if ($status === 'completed') {
            $completedAt = $run['completed_at'] ?: date('Y-m-d H:i:s');
        }

        $stmt = db()->prepare('
            UPDATE test_runs
            SET plan_id = :plan_id,
                name = :name,
                description = :description,
                status = :status,
                assigned_to = :assigned_to,
                completed_at = :completed_at
            WHERE id = :id
        ');
        $stmt->execute([
            ':plan_id' => post_param('plan_id'),
            ':name' => post_param('name'),
            ':description' => post_param('description'),
            ':status' => $status,
            ':assigned_to' => post_param('assigned_to') ?: null,
            ':completed_at' => $completedAt,
            ':id' => $runId,
        ]);

        add_toast('Test run updated', 'success');
        redirect('/testruns.php' . $redirectProjectQuery);
    }

    $planId = (int)post_param('plan_id', 0);
    if ($planId <= 0) {
        add_toast('Select a test plan before creating a run', 'warning');
        redirect('/testruns.php' . $redirectProjectQuery);
    }

    $stmt = db()->prepare('INSERT INTO test_runs (plan_id, name, description, status, assigned_to, created_by) VALUES (:plan_id, :name, :description, :status, :assigned_to, :created_by)');
    $stmt->execute([
        ':plan_id' => $planId,
        ':name' => post_param('name'),
        ':description' => post_param('description'),
        ':status' => 'in_progress',
        ':assigned_to' => post_param('assigned_to') ?: null,
        ':created_by' => $user['id'],
    ]);
    $runId = (int)db()->lastInsertId();

    $cases = fetch_all('SELECT tc.id FROM test_cases tc JOIN test_suites ts ON ts.id=tc.suite_id WHERE ts.plan_id = :id', [':id' => $planId]);
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

$sql = '
    SELECT tr.*, tp.name as plan_name, au.username as assigned_name, cu.username as created_by_name
    FROM test_runs tr
    JOIN test_plans tp ON tp.id=tr.plan_id
    LEFT JOIN users au ON au.id = tr.assigned_to
    LEFT JOIN users cu ON cu.id = tr.created_by
    WHERE 1=1
';
$params = [];
if (in_array($resultFilter, $validResultFilters, true)) {
    if ($resultFilter === 'in_progress') {
        $sql .= " AND EXISTS (
            SELECT 1
            FROM test_executions te
            WHERE te.test_run_id = tr.id AND te.status IN ('blocked', 'not_tested', 'skipped')
        )";
    } else {
        $sql .= ' AND EXISTS (
            SELECT 1
            FROM test_executions te
            WHERE te.test_run_id = tr.id AND te.status = :result
        )';
        $params[':result'] = $resultFilter;
    }
}
$selectedProjectParams = [];
if ($selectedProjectId > 0) {
    $sql .= ' AND tp.project_id = :project_id';
    $selectedProjectParams[':project_id'] = $selectedProjectId;
}
$params += $selectedProjectParams;
$sql .= ' ORDER BY tr.created_at DESC';
$runs = fetch_all($sql, $params);
?>
<div class="app-content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Test Runs</h2>
        <form method="get" class="d-flex align-items-center gap-2">
            <label for="testRunProjectFilter" class="small text-muted mb-0">Project</label>
            <select id="testRunProjectFilter" class="form-select form-select-sm" name="project_id">
                <option value="0">All projects</option>
                <?php foreach ($projects as $project): ?>
                    <option value="<?php echo (int)$project['id']; ?>" <?php echo $selectedProjectId === (int)$project['id'] ? 'selected' : ''; ?>>
                        <?php echo h((string)$project['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (in_array($resultFilter, $validResultFilters, true)): ?>
                <input type="hidden" name="result" value="<?php echo h($resultFilter); ?>">
            <?php endif; ?>
            <button class="btn btn-sm btn-outline-primary">Apply</button>
        </form>
    </div>
    <?php if (in_array($resultFilter, $validResultFilters, true)): ?>
        <div class="alert alert-info py-2">
            Filter: runs with <strong><?php echo h($resultFilter); ?></strong> results
            <a class="ms-2" href="/testruns.php<?php echo $selectedProjectId > 0 ? ('?project_id=' . (int)$selectedProjectId) : ''; ?>">clear</a>
        </div>
    <?php endif; ?>
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card p-3">
                <h6>Create Test Run</h6>
                <form method="post" data-draft-key="testrun">
                    <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                    <input type="hidden" name="redirect_project_id" value="<?php echo (int)$selectedProjectId; ?>">
                    <select class="form-select mb-2" name="plan_id" required>
                        <?php if (!$plans): ?>
                            <option value="">No plans available</option>
                        <?php else: ?>
                            <?php foreach ($plans as $plan): ?>
                                <option value="<?php echo $plan['id']; ?>"><?php echo h($plan['name']); ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
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
                            <th>Assignee</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($runs as $run): ?>
                        <tr>
                            <td><?php echo h($run['name']); ?></td>
                            <td><?php echo h($run['plan_name']); ?></td>
                            <td><?php echo h($run['status']); ?></td>
                            <td><?php echo h($run['assigned_name'] ?? 'Unassigned'); ?></td>
                            <td><?php echo h((string)($run['created_at'] ?? '')); ?></td>
                            <td class="d-flex gap-2 flex-wrap">
                                <a class="btn btn-sm btn-outline-primary" href="/testrun.php?id=<?php echo (int)$run['id']; ?>">Execute</a>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-secondary"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#editRun<?php echo (int)$run['id']; ?>"
                                    aria-expanded="false"
                                    aria-controls="editRun<?php echo (int)$run['id']; ?>"
                                >
                                    Edit
                                </button>
                                <form method="post" onsubmit="return confirm('Delete test run? This will remove all executions in this run.');">
                                    <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="redirect_project_id" value="<?php echo (int)$selectedProjectId; ?>">
                                    <input type="hidden" name="run_id" value="<?php echo (int)$run['id']; ?>">
                                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <tr class="collapse" id="editRun<?php echo (int)$run['id']; ?>">
                            <td colspan="6">
                                <form method="post" class="row g-2 align-items-end">
                                    <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="redirect_project_id" value="<?php echo (int)$selectedProjectId; ?>">
                                    <input type="hidden" name="run_id" value="<?php echo (int)$run['id']; ?>">
                                    <div class="col-md-3">
                                        <label class="form-label mb-1">Name</label>
                                        <input class="form-control form-control-sm" name="name" value="<?php echo h($run['name']); ?>" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label mb-1">Plan</label>
                                        <select class="form-select form-select-sm" name="plan_id" required>
                                            <?php foreach ($plans as $plan): ?>
                                                <option value="<?php echo (int)$plan['id']; ?>" <?php echo (int)$run['plan_id'] === (int)$plan['id'] ? 'selected' : ''; ?>>
                                                    <?php echo h($plan['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label mb-1">Status</label>
                                        <select class="form-select form-select-sm" name="status">
                                            <?php foreach (['in_progress', 'completed', 'aborted'] as $status): ?>
                                                <option value="<?php echo h($status); ?>" <?php echo $run['status'] === $status ? 'selected' : ''; ?>><?php echo h($status); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label mb-1">Assignee</label>
                                        <select class="form-select form-select-sm" name="assigned_to">
                                            <option value="">Unassigned</option>
                                            <?php foreach ($users as $u): ?>
                                                <option value="<?php echo (int)$u['id']; ?>" <?php echo (string)($run['assigned_to'] ?? '') === (string)$u['id'] ? 'selected' : ''; ?>>
                                                    <?php echo h($u['username']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label mb-1">Description</label>
                                        <input class="form-control form-control-sm" name="description" value="<?php echo h((string)($run['description'] ?? '')); ?>">
                                    </div>
                                    <div class="col-12 d-flex justify-content-end">
                                        <button class="btn btn-sm btn-primary">Save changes</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
