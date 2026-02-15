<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $stmt = db()->prepare('INSERT INTO projects (name, description, status) VALUES (:name, :description, :status)');
    $stmt->execute([
        ':name' => post_param('name'),
        ':description' => post_param('description'),
        ':status' => post_param('status') ?: 'active',
    ]);
    add_toast('Project created', 'success');
    redirect('/projects.php');
}

$projects = fetch_all('SELECT * FROM projects ORDER BY created_at DESC');
?>
<div class="app-content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Projects</h2>
    </div>
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card p-3">
                <h6>Create Project</h6>
                <form method="post" data-draft-key="project">
                    <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                    <div class="mb-2">
                        <input class="form-control" name="name" placeholder="Project name" required>
                    </div>
                    <div class="mb-2">
                        <textarea class="form-control" name="description" placeholder="Description"></textarea>
                    </div>
                    <select class="form-select mb-2" name="status">
                        <option value="active">Active</option>
                        <option value="archived">Archived</option>
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
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($projects as $project): ?>
                        <tr>
                            <td><?php echo h($project['name']); ?></td>
                            <td><?php echo h($project['status']); ?></td>
                            <td><a href="/project.php?id=<?php echo $project['id']; ?>">Open</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
