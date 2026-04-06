<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$projectId = get_param('project_id');
$editId = (int)get_param('edit_id', 0);
$projects = fetch_all('SELECT * FROM projects');
$editRelease = null;
if ($editId > 0) {
    $editRelease = fetch_one('SELECT * FROM releases WHERE id = :id', [':id' => $editId]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post_param('action', 'save');
    $releaseId = (int)post_param('release_id', 0);

    if ($action === 'delete' && $releaseId > 0) {
        try {
            $stmt = db()->prepare('DELETE FROM releases WHERE id = :id');
            $stmt->execute([':id' => $releaseId]);
            add_toast('Release deleted', 'success');
        } catch (Throwable $e) {
            add_toast('Cannot delete release: it is used in linked records', 'danger');
        }
        redirect('/releases.php?project_id=' . urlencode((string)$projectId));
    }

    if ($releaseId > 0) {
        $stmt = db()->prepare('UPDATE releases SET project_id=:project_id, name=:name, description=:description, release_date=:release_date, status=:status, updated_at=CURRENT_TIMESTAMP WHERE id=:id');
        $stmt->execute([
            ':project_id' => post_param('project_id'),
            ':name' => post_param('name'),
            ':description' => post_param('description'),
            ':release_date' => post_param('release_date'),
            ':status' => post_param('status'),
            ':id' => $releaseId,
        ]);
        add_toast('Release updated', 'success');
    } else {
        $stmt = db()->prepare('INSERT INTO releases (project_id, name, description, release_date, status) VALUES (:project_id, :name, :description, :release_date, :status)');
        $stmt->execute([
            ':project_id' => post_param('project_id'),
            ':name' => post_param('name'),
            ':description' => post_param('description'),
            ':release_date' => post_param('release_date'),
            ':status' => post_param('status'),
        ]);
        add_toast('Release created', 'success');
    }
    redirect('/releases.php?project_id=' . post_param('project_id'));
}

$sql = 'SELECT r.*, p.name as project_name FROM releases r JOIN projects p ON p.id=r.project_id';
$params = [];
if ($projectId) {
    $sql .= ' WHERE r.project_id = :project_id';
    $params[':project_id'] = $projectId;
}
$sql .= ' ORDER BY r.release_date DESC';
$releases = fetch_all($sql, $params);
?>
<div class="app-content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Releases</h2>
    </div>
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card p-3">
                <h6><?php echo $editRelease ? 'Edit Release' : 'Create Release'; ?></h6>
                <form method="post" data-draft-key="release">
                    <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                    <input type="hidden" name="release_id" value="<?php echo (int)($editRelease['id'] ?? 0); ?>">
                    <input type="hidden" name="action" value="save">
                    <select class="form-select mb-2" name="project_id" required>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?php echo $project['id']; ?>" <?php echo (($editRelease['project_id'] ?? $projectId) == $project['id']) ? 'selected' : ''; ?>><?php echo h($project['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input class="form-control mb-2" name="name" placeholder="Release name" value="<?php echo h($editRelease['name'] ?? ''); ?>" required>
                    <textarea class="form-control mb-2" name="description" placeholder="Description"><?php echo h($editRelease['description'] ?? ''); ?></textarea>
                    <input class="form-control mb-2" type="date" name="release_date" value="<?php echo h($editRelease['release_date'] ?? ''); ?>">
                    <select class="form-select mb-2" name="status">
                        <option value="planned" <?php echo ($editRelease['status'] ?? 'planned') === 'planned' ? 'selected' : ''; ?>>Planned</option>
                        <option value="in_progress" <?php echo ($editRelease['status'] ?? 'planned') === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="released" <?php echo ($editRelease['status'] ?? 'planned') === 'released' ? 'selected' : ''; ?>>Released</option>
                        <option value="cancelled" <?php echo ($editRelease['status'] ?? 'planned') === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary">Save</button>
                        <?php if ($editRelease): ?>
                            <a class="btn btn-outline-secondary" href="/releases.php?project_id=<?php echo h((string)$projectId); ?>">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card p-3">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Release</th>
                            <th>Project</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($releases as $release): ?>
                        <tr>
                            <td><?php echo h($release['name']); ?></td>
                            <td><?php echo h($release['project_name']); ?></td>
                            <td><?php echo h($release['status']); ?></td>
                            <td><?php echo h($release['release_date']); ?></td>
                            <td class="d-flex gap-2">
                                <a class="btn btn-sm btn-outline-primary" href="/releases.php?project_id=<?php echo h((string)$projectId); ?>&edit_id=<?php echo (int)$release['id']; ?>">Edit</a>
                                <form method="post" onsubmit="return confirm('Delete release?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                                    <input type="hidden" name="release_id" value="<?php echo (int)$release['id']; ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button class="btn btn-sm btn-outline-danger">Delete</button>
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
