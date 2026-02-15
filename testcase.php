<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$user = current_user();
$id = get_param('id');
$planId = get_param('plan_id');
$case = $id ? fetch_one('SELECT * FROM test_cases WHERE id = :id', [':id' => $id]) : null;
$suites = $planId ? fetch_all('SELECT * FROM test_suites WHERE plan_id = :id', [':id' => $planId]) : fetch_all('SELECT * FROM test_suites');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
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
        add_toast('Test case updated', 'success');
    } else {
        $stmt = db()->prepare('INSERT INTO test_cases (suite_id, title, description, preconditions, steps_json, expected_result_json, checklist_json, type, priority, estimated_time, automation_status, created_by) VALUES (:suite_id, :title, :description, :preconditions, :steps_json, :expected_result_json, :checklist_json, :type, :priority, :estimated_time, :automation_status, :created_by)');
        $stmt->execute($payload + [':created_by' => $user['id']]);
        $id = (int)db()->lastInsertId();
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
        <form method="post" data-draft-key="testcase-<?php echo h((string)$id); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Suite</label>
                    <select name="suite_id" class="form-select" required>
                        <?php foreach ($suites as $suite): ?>
                            <option value="<?php echo $suite['id']; ?>" <?php echo ($case['suite_id'] ?? '') == $suite['id'] ? 'selected' : ''; ?>><?php echo h($suite['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Title</label>
                    <input type="text" name="title" class="form-control" value="<?php echo h($case['title'] ?? ''); ?>" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2"><?php echo h($case['description'] ?? ''); ?></textarea>
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
                <button class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
