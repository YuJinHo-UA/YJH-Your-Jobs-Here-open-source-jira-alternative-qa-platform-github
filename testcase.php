<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$user = current_user();
$id = get_param('id');
$planId = (int)get_param('plan_id', 0);
$preferredSuiteId = (int)get_param('suite_id', 0);
$case = $id ? fetch_one('SELECT * FROM test_cases WHERE id = :id', [':id' => $id]) : null;
$resolvedPlanId = $planId;
if ($resolvedPlanId <= 0 && $case) {
    $suiteMeta = fetch_one(
        'SELECT ts.plan_id FROM test_suites ts WHERE ts.id = :id',
        [':id' => (int)$case['suite_id']]
    );
    $resolvedPlanId = (int)($suiteMeta['plan_id'] ?? 0);
}
$suites = $resolvedPlanId > 0
    ? fetch_all('SELECT * FROM test_suites WHERE plan_id = :id ORDER BY order_index, id', [':id' => $resolvedPlanId])
    : fetch_all('SELECT * FROM test_suites ORDER BY order_index, id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)post_param('action');

    if ($action === 'create_suite') {
        $targetPlanId = (int)post_param('plan_id');
        $suiteName = trim((string)post_param('suite_name'));
        $suiteDescription = trim((string)post_param('suite_description'));

        if ($targetPlanId <= 0) {
            add_toast('Please open a test plan before creating a suite', 'warning');
            redirect('/testplans.php');
        }
        if ($suiteName === '') {
            add_toast('Suite name is required', 'warning');
            redirect('/testcase.php?plan_id=' . $targetPlanId);
        }

        $stmt = db()->prepare('INSERT INTO test_suites (plan_id, parent_suite_id, name, description, order_index) VALUES (:plan_id, :parent_suite_id, :name, :description, :order_index)');
        $stmt->execute([
            ':plan_id' => $targetPlanId,
            ':parent_suite_id' => null,
            ':name' => $suiteName,
            ':description' => $suiteDescription,
            ':order_index' => 0,
        ]);
        $newSuiteId = (int)db()->lastInsertId();
        record_activity('created', 'test_suite', $newSuiteId, [
            'plan_id' => $targetPlanId,
            'name' => $suiteName,
        ]);
        add_toast('Suite created. You can now create a test case.', 'success');
        redirect('/testcase.php?plan_id=' . $targetPlanId . '&suite_id=' . $newSuiteId);
    }

    if ($action === 'create_bug_from_case' && $id && $case) {
        $meta = fetch_one(
            'SELECT tp.project_id
             FROM test_suites ts
             JOIN test_plans tp ON tp.id = ts.plan_id
             WHERE ts.id = :suite_id',
            [':suite_id' => (int)$case['suite_id']]
        );
        $projectId = (int)($meta['project_id'] ?? 4);

        $steps = json_decode((string)$case['steps_json'], true) ?: [];
        $expected = json_decode((string)$case['expected_result_json'], true) ?: [];
        $checklist = json_decode((string)$case['checklist_json'], true) ?: [];
        $descriptionParts = array_filter([
            (string)($case['description'] ?? ''),
            $checklist ? ('Checklist: ' . implode('; ', $checklist)) : '',
        ]);
        $stmt = db()->prepare(
            'INSERT INTO bugs (project_id, title, description, steps_to_reproduce, expected_result, actual_result, environment, severity, priority, status, reporter_id)
             VALUES (:project_id, :title, :description, :steps, :expected, :actual, :environment, :severity, :priority, :status, :reporter_id)'
        );
        $stmt->execute([
            ':project_id' => $projectId,
            ':title' => 'From Test Case #' . (int)$id . ': ' . (string)$case['title'],
            ':description' => implode("\n\n", $descriptionParts),
            ':steps' => implode("\n", $steps),
            ':expected' => implode("\n", $expected),
            ':actual' => '',
            ':environment' => 'Windows 11',
            ':severity' => 'major',
            ':priority' => in_array((string)$case['priority'], ['high', 'critical'], true) ? 'high' : 'medium',
            ':status' => 'new',
            ':reporter_id' => $user['id'],
        ]);
        $bugId = (int)db()->lastInsertId();
        record_activity('created', 'bug', $bugId, [
            'source' => 'test_case',
            'test_case_id' => (int)$id,
        ]);
        add_toast('Bug created from test case', 'success');
        redirect('/bug.php?id=' . $bugId);
    }

    $steps = array_values(array_filter(array_map('trim', explode("\n", (string)post_param('steps')))));
    $expected = array_values(array_filter(array_map('trim', explode("\n", (string)post_param('expected')))));
    $checklist = array_values(array_filter(array_map('trim', explode("\n", (string)post_param('checklist')))));

    $payload = [
        ':suite_id' => post_param('suite_id'),
        ':title' => post_param('title'),
        ':description' => post_param('description'),
        ':preconditions' => post_param('preconditions'),
        ':steps_json' => json_encode($steps),
        ':expected_result_json' => json_encode($expected),
        ':checklist_json' => json_encode($checklist),
        ':type' => post_param('type'),
        ':priority' => post_param('priority'),
        ':estimated_time' => post_param('estimated_time'),
        ':automation_status' => post_param('automation_status'),
    ];

    if ($id && $case) {
        $stmt = db()->prepare('UPDATE test_cases SET suite_id=:suite_id, title=:title, description=:description, preconditions=:preconditions, steps_json=:steps_json, expected_result_json=:expected_result_json, checklist_json=:checklist_json, type=:type, priority=:priority, estimated_time=:estimated_time, automation_status=:automation_status, updated_by=:updated_by, updated_at=CURRENT_TIMESTAMP WHERE id=:id');
        $stmt->execute($payload + [':updated_by' => $user['id'], ':id' => $id]);
        record_activity('updated', 'test_case', (int)$id, [
            'title' => (string)post_param('title'),
            'suite_id' => (int)post_param('suite_id'),
        ]);
        add_toast('Test case updated', 'success');
    } else {
        $stmt = db()->prepare('INSERT INTO test_cases (suite_id, title, description, preconditions, steps_json, expected_result_json, checklist_json, type, priority, estimated_time, automation_status, created_by) VALUES (:suite_id, :title, :description, :preconditions, :steps_json, :expected_result_json, :checklist_json, :type, :priority, :estimated_time, :automation_status, :created_by)');
        $stmt->execute($payload + [':created_by' => $user['id']]);
        $id = (int)db()->lastInsertId();
        record_activity('created', 'test_case', (int)$id, [
            'title' => (string)post_param('title'),
            'suite_id' => (int)post_param('suite_id'),
        ]);
        add_toast('Test case created', 'success');
        redirect('/testcase.php?id=' . $id);
    }

    $case = fetch_one('SELECT * FROM test_cases WHERE id = :id', [':id' => $id]);
}

$stepsText = $case ? implode("\n", json_decode($case['steps_json'], true) ?: []) : '';
$expectedText = $case ? implode("\n", json_decode($case['expected_result_json'], true) ?: []) : '';
$checklistText = $case ? implode("\n", json_decode($case['checklist_json'], true) ?: []) : '';
?>
<div class="app-content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><?php echo $id ? 'Test Case #' . $id : 'Create Test Case'; ?></h2>
        <a class="btn btn-outline-secondary" href="/testplans.php">Back</a>
    </div>

    <div class="card p-3">
        <?php if ($id && $case): ?>
            <form method="post" class="mb-3 d-flex gap-2">
                <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                <input type="hidden" name="action" value="create_bug_from_case">
                <button type="submit" class="btn btn-outline-danger btn-sm">Create Bug from this Test Case</button>
            </form>
        <?php endif; ?>
        <form method="post" data-draft-key="testcase-<?php echo h((string)$id); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Suite</label>
                    <?php if ($suites): ?>
                        <select name="suite_id" class="form-select" required>
                            <?php foreach ($suites as $suite): ?>
                                <option value="<?php echo $suite['id']; ?>" <?php echo ((int)($case['suite_id'] ?? $preferredSuiteId) === (int)$suite['id']) ? 'selected' : ''; ?>><?php echo h($suite['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <div class="alert alert-warning mb-2">No suites available for this plan yet.</div>
                        <?php if ($resolvedPlanId > 0): ?>
                            <form method="post" class="d-flex flex-column gap-2">
                                <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                                <input type="hidden" name="action" value="create_suite">
                                <input type="hidden" name="plan_id" value="<?php echo (int)$resolvedPlanId; ?>">
                                <input type="text" name="suite_name" class="form-control form-control-sm" placeholder="Suite name" required>
                                <textarea name="suite_description" class="form-control form-control-sm" rows="2" placeholder="Suite description (optional)"></textarea>
                                <button class="btn btn-outline-primary btn-sm align-self-start" type="submit">Create Suite Here</button>
                            </form>
                        <?php else: ?>
                            <a class="btn btn-outline-primary btn-sm" href="/testplans.php">Open Test Plan</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Title</label>
                    <input type="text" name="title" class="form-control" value="<?php echo h($case['title'] ?? ''); ?>" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2"><?php echo h($case['description'] ?? ''); ?></textarea>
                </div>
                <div class="col-12 d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-outline-primary btn-sm" data-ai-action="generate_test_cases">🤖 Згенерувати тест-кейс</button>
                </div>
                <div class="col-12">
                    <div class="alert alert-info d-none mb-0" data-ai-target="test_cases_result"></div>
                </div>
                <div class="col-12">
                    <label class="form-label">Preconditions</label>
                    <textarea name="preconditions" class="form-control" rows="2"><?php echo h($case['preconditions'] ?? ''); ?></textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-select">
                        <?php foreach (['functional','ui','performance','security','integration'] as $type): ?>
                            <option value="<?php echo $type; ?>" <?php echo ($case['type'] ?? 'functional') === $type ? 'selected' : ''; ?>><?php echo $type; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Priority</label>
                    <select name="priority" class="form-select">
                        <?php foreach (['critical','high','medium','low'] as $priority): ?>
                            <option value="<?php echo $priority; ?>" <?php echo ($case['priority'] ?? 'medium') === $priority ? 'selected' : ''; ?>><?php echo $priority; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Automation</label>
                    <select name="automation_status" class="form-select">
                        <?php foreach (['manual','automated','partially'] as $status): ?>
                            <option value="<?php echo $status; ?>" <?php echo ($case['automation_status'] ?? 'manual') === $status ? 'selected' : ''; ?>><?php echo $status; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Estimated time (min)</label>
                    <input type="number" name="estimated_time" class="form-control" value="<?php echo h((string)($case['estimated_time'] ?? '')); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Steps (one per line)</label>
                    <textarea name="steps" class="form-control" rows="4"><?php echo h($stepsText); ?></textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Expected (one per line)</label>
                    <textarea name="expected" class="form-control" rows="4"><?php echo h($expectedText); ?></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Checklist (one per line)</label>
                    <textarea name="checklist" class="form-control" rows="3"><?php echo h($checklistText); ?></textarea>
                </div>
            </div>
            <div class="mt-3">
                <button class="btn btn-primary" <?php echo $suites ? '' : 'disabled'; ?>>Save</button>
            </div>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
