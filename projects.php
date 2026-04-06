<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$editId = (int)get_param('edit_id', 0);
$editProject = null;
if ($editId > 0) {
    $editProject = fetch_one('SELECT * FROM projects WHERE id = :id', [':id' => $editId]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)post_param('action', 'save');
    $projectId = (int)post_param('project_id', 0);
    $status = (string)post_param('status', 'active');
    if (!in_array($status, ['active', 'archived'], true)) {
        $status = 'active';
    }

    if ($action === 'delete' && $projectId > 0) {
        try {
            $stmt = db()->prepare('DELETE FROM projects WHERE id = :id');
            $stmt->execute([':id' => $projectId]);
            add_toast('Project deleted', 'success');
        } catch (Throwable $e) {
            add_toast('Cannot delete project: linked records exist', 'danger');
        }
        redirect('/projects.php');
    }

    if ($projectId > 0) {
        $stmt = db()->prepare('UPDATE projects SET name=:name, description=:description, status=:status, updated_at=CURRENT_TIMESTAMP WHERE id=:id');
        $stmt->execute([
            ':name' => post_param('name'),
            ':description' => post_param('description'),
            ':status' => $status,
            ':id' => $projectId,
        ]);
        add_toast('Project updated', 'success');
    } else {
        $stmt = db()->prepare('INSERT INTO projects (name, description, status) VALUES (:name, :description, :status)');
        $stmt->execute([
            ':name' => post_param('name'),
            ':description' => post_param('description'),
            ':status' => $status,
        ]);
        add_toast('Project created', 'success');
    }
    redirect('/projects.php');
}

$projects = fetch_all('SELECT * FROM projects ORDER BY created_at DESC');
?>
<div class="app-content">
    <div class="users-header">
        <h2>Projects</h2>
        <div class="text-muted small">Manage projects and statuses</div>
    </div>
    <div class="row g-4 justify-content-center">
        <div class="col-12 col-xxl-10">
            <div class="row g-4">
                <div class="col-xl-4 col-lg-5">
                    <div class="card users-panel p-3">
                <h6><?php echo $editProject ? 'Edit Project' : 'Create Project'; ?></h6>
                <form method="post" data-draft-key="project">
                    <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="project_id" value="<?php echo (int)($editProject['id'] ?? 0); ?>">
                    <div class="mb-2">
                        <input class="form-control" name="name" placeholder="Project name" value="<?php echo h($editProject['name'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-2">
                        <textarea class="form-control" name="description" placeholder="Description"><?php echo h($editProject['description'] ?? ''); ?></textarea>
                    </div>
                    <select class="form-select mb-2" name="status">
                        <option value="active" <?php echo ($editProject['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="archived" <?php echo ($editProject['status'] ?? '') === 'archived' ? 'selected' : ''; ?>>Archived</option>
                    </select>
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary flex-grow-1"><?php echo $editProject ? 'Update Project' : 'Create Project'; ?></button>
                        <?php if ($editProject): ?>
                            <a class="btn btn-outline-secondary" href="/projects.php">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        <div class="col-xl-8 col-lg-7">
            <div class="card users-panel p-3">
                <div class="table-responsive">
                <table class="table users-table mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($projects as $project): ?>
                        <tr>
                            <td><?php echo h($project['name']); ?></td>
                            <td class="text-muted"><?php echo h((string)($project['description'] ?? '')); ?></td>
                            <td><?php echo h($project['status']); ?></td>
                            <td>
                                <div class="users-actions">
                                    <a class="btn btn-sm btn-outline-secondary" href="/project.php?id=<?php echo (int)$project['id']; ?>">Open</a>
                                    <a class="btn btn-sm btn-outline-primary" href="/projects.php?edit_id=<?php echo (int)$project['id']; ?>">Edit</a>
                                    <form method="post" onsubmit="return confirm('Delete project?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="project_id" value="<?php echo (int)$project['id']; ?>">
                                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
