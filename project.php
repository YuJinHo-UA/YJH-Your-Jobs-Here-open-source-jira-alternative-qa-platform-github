<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$id = (int)get_param('id');
$project = fetch_one('SELECT * FROM projects WHERE id = :id', [':id' => $id]);
if (!$project) {
    echo '<div class="app-content">Project not found</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $status = (string)post_param('status', 'active');
    if (!in_array($status, ['active', 'archived'], true)) {
        $status = 'active';
    }

    $stmt = db()->prepare('UPDATE projects SET name=:name, description=:description, status=:status, updated_at=CURRENT_TIMESTAMP WHERE id=:id');
    $stmt->execute([
        ':name' => post_param('name'),
        ':description' => post_param('description'),
        ':status' => $status,
        ':id' => $id,
    ]);
    add_toast('Project updated', 'success');
    redirect('/project.php?id=' . $id);
}

$releases = fetch_all('SELECT * FROM releases WHERE project_id = :id', [':id' => $id]);
?>
<div class="app-content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><?php echo h($project['name']); ?></h2>
        <a class="btn btn-outline-secondary" href="/projects.php">Back</a>
    </div>
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card p-3">
                <h6>Project details</h6>
                <form method="post" data-draft-key="project-<?php echo $id; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                    <div class="mb-2">
                        <input class="form-control" name="name" value="<?php echo h($project['name']); ?>" required>
                    </div>
                    <div class="mb-2">
                        <textarea class="form-control" name="description"><?php echo h($project['description']); ?></textarea>
                    </div>
                    <select class="form-select mb-2" name="status">
                        <option value="active" <?php echo $project['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="archived" <?php echo $project['status'] === 'archived' ? 'selected' : ''; ?>>Archived</option>
                    </select>
                    <button class="btn btn-primary">Save</button>
                </form>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card p-3">
                <h6>Releases</h6>
                <?php foreach ($releases as $release): ?>
                    <div class="mb-2">
                        <a href="/releases.php?project_id=<?php echo $id; ?>"><?php echo h($release['name']); ?></a>
                        <span class="text-muted small">(<?php echo h($release['status']); ?>)</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
