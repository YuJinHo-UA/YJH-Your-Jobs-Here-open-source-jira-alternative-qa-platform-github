<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$projectId = get_param('project_id');
$projects = fetch_all('SELECT * FROM projects');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $stmt = db()->prepare('INSERT INTO releases (project_id, name, description, release_date, status) VALUES (:project_id, :name, :description, :release_date, :status)');
    $stmt->execute([
        ':project_id' => post_param('project_id'),
        ':name' => post_param('name'),
        ':description' => post_param('description'),
        ':release_date' => post_param('release_date'),
        ':status' => post_param('status'),
    ]);
    add_toast('Release created', 'success');
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
                <h6>Create Release</h6>
                <form method="post" data-draft-key="release">
                    <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                    <select class="form-select mb-2" name="project_id" required>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?php echo $project['id']; ?>" <?php echo $projectId == $project['id'] ? 'selected' : ''; ?>><?php echo h($project['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input class="form-control mb-2" name="name" placeholder="Release name" required>
                    <textarea class="form-control mb-2" name="description" placeholder="Description"></textarea>
                    <input class="form-control mb-2" type="date" name="release_date">
                    <select class="form-select mb-2" name="status">
                        <option value="planned">Planned</option>
                        <option value="in_progress">In Progress</option>
                        <option value="released">Released</option>
                        <option value="cancelled">Cancelled</option>
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
                            <th>Release</th>
                            <th>Project</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($releases as $release): ?>
                        <tr>
                            <td><?php echo h($release['name']); ?></td>
                            <td><?php echo h($release['project_name']); ?></td>
                            <td><?php echo h($release['status']); ?></td>
                            <td><?php echo h($release['release_date']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
