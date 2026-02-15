<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$id = (int)get_param('id');
$plan = fetch_one('SELECT * FROM test_plans WHERE id = :id', [':id' => $id]);
if (!$plan) {
    echo '<div class="app-content">Test plan not found</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_param('suite_name')) {
    verify_csrf();
    $stmt = db()->prepare('INSERT INTO test_suites (plan_id, parent_suite_id, name, description, order_index) VALUES (:plan_id, :parent_suite_id, :name, :description, :order_index)');
    $stmt->execute([
        ':plan_id' => $id,
        ':parent_suite_id' => post_param('parent_suite_id') ?: null,
        ':name' => post_param('suite_name'),
        ':description' => post_param('suite_description'),
        ':order_index' => 0,
    ]);
    add_toast('Suite created', 'success');
    redirect('/testplan.php?id=' . $id);
}

$suites = fetch_all('SELECT * FROM test_suites WHERE plan_id = :id ORDER BY order_index', [':id' => $id]);
$cases = fetch_all('SELECT tc.*, ts.name as suite_name FROM test_cases tc JOIN test_suites ts ON ts.id=tc.suite_id WHERE ts.plan_id = :id', [':id' => $id]);
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
                        <div><?php echo h($suite['name']); ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card p-3">
                <h6>Test Cases</h6>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Suite</th>
                            <th>Priority</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($cases as $case): ?>
                        <tr>
                            <td><?php echo h($case['title']); ?></td>
                            <td><?php echo h($case['suite_name']); ?></td>
                            <td><?php echo h($case['priority']); ?></td>
                            <td><a href="/testcase.php?id=<?php echo $case['id']; ?>">Open</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <a class="btn btn-outline-primary" href="/testcase.php?plan_id=<?php echo $id; ?>">Create Test Case</a>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
