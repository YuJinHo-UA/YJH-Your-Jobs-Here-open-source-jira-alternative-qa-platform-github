<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$user = current_user();
$filters = [
    'status' => get_param('status'),
    'priority' => get_param('priority'),
    'priority_group' => get_param('priority_group'),
    'project_id' => get_param('project_id'),
    'assignee_id' => get_param('assignee_id'),
    'created_date' => get_param('created_date'),
    'closed_date' => get_param('closed_date'),
];

$sql = "SELECT b.*, p.name as project_name, u.username as assignee,
        (
            SELECT ua.type
            FROM user_availability ua
            WHERE ua.user_id = b.assignee_id
              AND ua.start_date <= date('now')
              AND ua.end_date >= date('now')
            LIMIT 1
        ) as assignee_unavailability
        FROM bugs b
        JOIN projects p ON p.id=b.project_id
        LEFT JOIN users u ON u.id=b.assignee_id
        WHERE 1=1";
$params = [];
if ($filters['status']) {
    $sql .= " AND b.status = :status";
    $params[':status'] = $filters['status'];
}
if ($filters['priority']) {
    $sql .= " AND b.priority = :priority";
    $params[':priority'] = $filters['priority'];
}
if ($filters['priority_group'] === 'high') {
    $sql .= " AND b.priority IN ('highest', 'high')";
}
if ($filters['project_id']) {
    $sql .= " AND b.project_id = :project_id";
    $params[':project_id'] = $filters['project_id'];
}
if ($filters['assignee_id']) {
    $sql .= " AND b.assignee_id = :assignee_id";
    $params[':assignee_id'] = $filters['assignee_id'];
}
if ($filters['created_date']) {
    $sql .= " AND date(b.created_at) = :created_date";
    $params[':created_date'] = $filters['created_date'];
}
if ($filters['closed_date']) {
    $sql .= " AND date(b.closed_at) = :closed_date";
    $params[':closed_date'] = $filters['closed_date'];
}
$sql .= " ORDER BY b.created_at DESC";

$bugs = fetch_all($sql, $params);
$projects = fetch_all('SELECT * FROM projects');
$users = fetch_all('SELECT * FROM users');
$savedFilters = fetch_all('SELECT * FROM saved_filters WHERE user_id = :id', [':id' => $user['id']]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)post_param('action');
    if ($action === 'quick_update_bug') {
        $bugId = (int)post_param('bug_id');
        $title = trim((string)post_param('title'));
        $status = (string)post_param('status');
        $priority = (string)post_param('priority');
        $severity = (string)post_param('severity');
        $assigneeId = post_param('assignee_id') ?: null;
        if (
            $bugId > 0 &&
            $title !== '' &&
            in_array($status, ['new', 'assigned', 'in_progress', 'fixed', 'verified', 'closed', 'reopened'], true) &&
            in_array($priority, ['highest', 'high', 'medium', 'low', 'lowest'], true) &&
            in_array($severity, ['blocker', 'critical', 'major', 'minor', 'trivial'], true)
        ) {
            $stmt = db()->prepare('UPDATE bugs SET title=:title, status=:status, priority=:priority, severity=:severity, assignee_id=:assignee_id, updated_at=CURRENT_TIMESTAMP WHERE id=:id');
            $stmt->execute([
                ':title' => $title,
                ':status' => $status,
                ':priority' => $priority,
                ':severity' => $severity,
                ':assignee_id' => $assigneeId,
                ':id' => $bugId,
            ]);
            add_toast('Bug row updated', 'success');
        }
        $qs = $_SERVER['QUERY_STRING'] ?? '';
        redirect('/bugs.php' . ($qs !== '' ? ('?' . $qs) : ''));
    }
}
?>
<div class="app-content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Bugs</h2>
        <a class="btn btn-primary" href="/bug.php">Create Bug</a>
    </div>
    <div class="card p-3 mb-3">
        <form id="bugFilterForm" class="row g-2" method="get">
            <div class="col-md-3">
                <select name="project_id" class="form-select">
                    <option value="">All projects</option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?php echo $project['id']; ?>" <?php echo $filters['project_id'] == $project['id'] ? 'selected' : ''; ?>><?php echo h($project['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">Status</option>
                    <?php foreach (['new','assigned','in_progress','fixed','verified','closed','reopened'] as $status): ?>
                        <option value="<?php echo $status; ?>" <?php echo $filters['status'] === $status ? 'selected' : ''; ?>><?php echo $status; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="priority" class="form-select">
                    <option value="">Priority</option>
                    <?php foreach (['highest','high','medium','low','lowest'] as $priority): ?>
                        <option value="<?php echo $priority; ?>" <?php echo $filters['priority'] === $priority ? 'selected' : ''; ?>><?php echo $priority; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="assignee_id" class="form-select">
                    <option value="">Assignee</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo $filters['assignee_id'] == $u['id'] ? 'selected' : ''; ?>><?php echo h($u['username']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-outline-primary">Apply</button>
                <button type="button" class="btn btn-outline-secondary" id="saveFilterBtn">Save Filter</button>
            </div>
        </form>
        <?php if ($savedFilters): ?>
            <div class="mt-3">
                <span class="text-muted">Saved filters:</span>
                <?php foreach ($savedFilters as $filter): ?>
                    <?php $filterData = json_decode($filter['filter_json'], true); ?>
                    <a class="badge text-bg-light" href="/bugs.php?<?php echo http_build_query($filterData); ?>"><?php echo h($filter['name']); ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="card p-3">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Project</th>
                    <th>Status</th>
                    <th>Priority</th>
                    <th>Severity</th>
                    <th>Assignee</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($bugs as $bug): ?>
                <tr>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                        <input type="hidden" name="action" value="quick_update_bug">
                        <input type="hidden" name="bug_id" value="<?php echo (int)$bug['id']; ?>">
                        <td><a href="/bug.php?id=<?php echo $bug['id']; ?>" data-preview-type="bug" data-preview-id="<?php echo $bug['id']; ?>">#<?php echo $bug['id']; ?></a></td>
                        <td><input class="form-control form-control-sm" name="title" value="<?php echo h($bug['title']); ?>" required></td>
                        <td><?php echo h($bug['project_name']); ?></td>
                        <td>
                            <select class="form-select form-select-sm" name="status">
                                <?php foreach (['new','assigned','in_progress','fixed','verified','closed','reopened'] as $status): ?>
                                    <option value="<?php echo $status; ?>" <?php echo $bug['status'] === $status ? 'selected' : ''; ?>><?php echo $status; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <select class="form-select form-select-sm" name="priority">
                                <?php foreach (['highest','high','medium','low','lowest'] as $priority): ?>
                                    <option value="<?php echo $priority; ?>" <?php echo $bug['priority'] === $priority ? 'selected' : ''; ?>><?php echo $priority; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <select class="form-select form-select-sm" name="severity">
                                <?php foreach (['blocker','critical','major','minor','trivial'] as $severity): ?>
                                    <option value="<?php echo $severity; ?>" <?php echo $bug['severity'] === $severity ? 'selected' : ''; ?>><?php echo $severity; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <select class="form-select form-select-sm" name="assignee_id">
                                <option value="">Unassigned</option>
                                <?php foreach ($users as $u): ?>
                                    <option value="<?php echo (int)$u['id']; ?>" <?php echo (string)$bug['assignee_id'] === (string)$u['id'] ? 'selected' : ''; ?>><?php echo h($u['username']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!empty($bug['assignee_unavailability'])): ?>
                                <div class="small text-warning mt-1">⚠️ unavailable today (<?php echo h((string)$bug['assignee_unavailability']); ?>)</div>
                            <?php endif; ?>
                        </td>
                        <td class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-primary" type="submit">Update</button>
                            <a class="btn btn-sm btn-outline-secondary" href="/bug.php?id=<?php echo $bug['id']; ?>">Open</a>
                        </td>
                    </form>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
