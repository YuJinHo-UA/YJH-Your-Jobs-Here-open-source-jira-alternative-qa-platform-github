<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$projects = fetch_all('SELECT * FROM projects');
$releases = fetch_all('SELECT * FROM releases');
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $stmt = db()->prepare('INSERT INTO test_plans (project_id, release_id, name, description, status, created_by) VALUES (:project_id, :release_id, :name, :description, :status, :created_by)');
    $stmt->execute([
        ':project_id' => post_param('project_id'),
        ':release_id' => post_param('release_id') ?: null,
        ':name' => post_param('name'),
        ':description' => post_param('description'),
        ':status' => post_param('status'),
        ':created_by' => $user['id'],
    ]);
    add_toast('Test plan created', 'success');
    redirect('/testplans.php');
}

$plans = fetch_all('SELECT t.*, p.name as project_name FROM test_plans t JOIN projects p ON p.id=t.project_id ORDER BY created_at DESC');
?>
<div class="app-content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Test Plans</h2>
    </div>
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card p-3">
                <h6>Create Plan</h6>
                <form method="post" data-draft-key="testplan">
                    <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                    <select class="form-select mb-2" name="project_id" required>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?php echo $project['id']; ?>"><?php echo h($project['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="form-select mb-2" name="release_id">
                        <option value="">Release</option>
                        <?php foreach ($releases as $release): ?>
                            <option value="<?php echo $release['id']; ?>"><?php echo h($release['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input class="form-control mb-2" name="name" placeholder="Plan name" required>
                    <textarea class="form-control mb-2" name="description"></textarea>
                    <select class="form-select mb-2" name="status">
                        <option value="draft">Draft</option>
                        <option value="active">Active</option>
                        <option value="completed">Completed</option>
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
                            <th>Project</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($plans as $plan): ?>
                        <tr>
                            <td><?php echo h($plan['name']); ?></td>
                            <td><?php echo h($plan['project_name']); ?></td>
                            <td><?php echo h($plan['status']); ?></td>
                            <td><a href="/testplan.php?id=<?php echo $plan['id']; ?>">Open</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
