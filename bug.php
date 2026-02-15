<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$user = current_user();
$id = get_param('id');
$bug = $id ? fetch_one('SELECT * FROM bugs WHERE id = :id', [':id' => $id]) : null;
$projects = fetch_all('SELECT * FROM projects');
$releases = fetch_all('SELECT * FROM releases');
$users = fetch_all('SELECT * FROM users');
$duplicates = [];

function similarity_score(string $a, string $b): float
{
    $a = strtolower(trim($a));
    $b = strtolower(trim($b));
    if ($a === '' || $b === '') {
        return 0.0;
    }
    $distance = levenshtein($a, $b);
    $maxLen = max(strlen($a), strlen($b));
    return $maxLen > 0 ? (1 - ($distance / $maxLen)) : 0.0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_param('comment_message') !== null) {
    verify_csrf();
    $message = trim((string)post_param('comment_message'));
    if ($message !== '' && $id) {
        $stmt = db()->prepare('INSERT INTO bug_comments (bug_id, user_id, message) VALUES (:bug_id, :user_id, :message)');
        $stmt->execute([':bug_id' => $id, ':user_id' => $user['id'], ':message' => $message]);
        preg_match_all('/@([A-Za-z0-9_]+)/', $message, $matches);
        foreach ($matches[1] as $username) {
            $mentioned = fetch_one('SELECT id FROM users WHERE username = :u', [':u' => $username]);
            if ($mentioned) {
                $stmt = db()->prepare('INSERT INTO bug_mentions (bug_id, user_id) VALUES (:bug_id, :user_id)');
                $stmt->execute([':bug_id' => $id, ':user_id' => $mentioned['id']]);
            }
        }
        add_toast('Comment added', 'success');
    }
    redirect('/bug.php?id=' . $id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $payload = [
        'project_id' => post_param('project_id'),
        'release_id' => post_param('release_id') ?: null,
        'title' => post_param('title'),
        'description' => post_param('description'),
        'steps_to_reproduce' => post_param('steps_to_reproduce'),
        'expected_result' => post_param('expected_result'),
        'actual_result' => post_param('actual_result'),
        'environment' => post_param('environment'),
        'severity' => post_param('severity'),
        'priority' => post_param('priority'),
        'status' => post_param('status'),
        'assignee_id' => post_param('assignee_id') ?: null,
        'due_date' => post_param('due_date') ?: null,
        'estimated_time' => post_param('estimated_time') ?: null,
        'actual_time' => post_param('actual_time') ?: null,
    ];

    if ($id && $bug) {
        $stmt = db()->prepare('UPDATE bugs SET project_id=:project_id, release_id=:release_id, title=:title, description=:description, steps_to_reproduce=:steps_to_reproduce, expected_result=:expected_result, actual_result=:actual_result, environment=:environment, severity=:severity, priority=:priority, status=:status, assignee_id=:assignee_id, due_date=:due_date, estimated_time=:estimated_time, actual_time=:actual_time, updated_at=CURRENT_TIMESTAMP WHERE id=:id');
        $stmt->execute($payload + [':id' => $id]);
        add_toast('Bug updated', 'success');
    } else {
        $stmt = db()->prepare('INSERT INTO bugs (project_id, release_id, title, description, steps_to_reproduce, expected_result, actual_result, environment, severity, priority, status, assignee_id, reporter_id, due_date, estimated_time, actual_time) VALUES (:project_id, :release_id, :title, :description, :steps_to_reproduce, :expected_result, :actual_result, :environment, :severity, :priority, :status, :assignee_id, :reporter_id, :due_date, :estimated_time, :actual_time)');
        $stmt->execute($payload + [':reporter_id' => $user['id']]);
        $id = (int)db()->lastInsertId();
        add_toast('Bug created', 'success');
        redirect('/bug.php?id=' . $id);
    }

    $bug = fetch_one('SELECT * FROM bugs WHERE id = :id', [':id' => $id]);
}

if ($id) {
    $all = fetch_all('SELECT id, title, description FROM bugs WHERE id != :id', [':id' => $id]);
    foreach ($all as $row) {
        $score = similarity_score($bug['title'] ?? '', $row['title']) * 0.7 + similarity_score($bug['description'] ?? '', $row['description']) * 0.3;
        if ($score >= 0.55) {
            $duplicates[] = ['id' => $row['id'], 'title' => $row['title'], 'score' => $score];
        }
    }
    usort($duplicates, fn($a, $b) => $b['score'] <=> $a['score']);
    $duplicates = array_slice($duplicates, 0, 5);
}

$comments = $id ? fetch_all('SELECT c.*, u.username FROM bug_comments c JOIN users u ON u.id=c.user_id WHERE bug_id = :id ORDER BY created_at DESC', [':id' => $id]) : [];
?>
<div class="app-content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><?php echo $id ? 'Bug #' . $id : 'Create Bug'; ?></h2>
        <a class="btn btn-outline-secondary" href="/bugs.php">Back to list</a>
    </div>

    <?php if ($duplicates): ?>
        <div class="alert alert-warning">
            <strong>Possible duplicates:</strong>
            <?php foreach ($duplicates as $dup): ?>
                <div><a href="/bug.php?id=<?php echo $dup['id']; ?>">#<?php echo $dup['id']; ?> <?php echo h($dup['title']); ?></a> (<?php echo number_format($dup['score'] * 100, 0); ?>%)</div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="card p-3 mb-4">
        <form method="post" data-draft-key="bug-<?php echo h((string)$id); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Project</label>
                    <select name="project_id" class="form-select" required>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?php echo $project['id']; ?>" <?php echo ($bug['project_id'] ?? '') == $project['id'] ? 'selected' : ''; ?>><?php echo h($project['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Release</label>
                    <select name="release_id" class="form-select">
                        <option value="">None</option>
                        <?php foreach ($releases as $release): ?>
                            <option value="<?php echo $release['id']; ?>" <?php echo ($bug['release_id'] ?? '') == $release['id'] ? 'selected' : ''; ?>><?php echo h($release['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Title</label>
                    <input type="text" name="title" class="form-control" value="<?php echo h($bug['title'] ?? ''); ?>" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3"><?php echo h($bug['description'] ?? ''); ?></textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Severity</label>
                    <select name="severity" class="form-select" required>
                        <?php foreach (['blocker','critical','major','minor','trivial'] as $severity): ?>
                            <option value="<?php echo $severity; ?>" <?php echo ($bug['severity'] ?? 'major') === $severity ? 'selected' : ''; ?>><?php echo $severity; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Priority</label>
                    <select name="priority" class="form-select" required>
                        <?php foreach (['highest','high','medium','low','lowest'] as $priority): ?>
                            <option value="<?php echo $priority; ?>" <?php echo ($bug['priority'] ?? 'medium') === $priority ? 'selected' : ''; ?>><?php echo $priority; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <?php foreach (['new','assigned','in_progress','fixed','verified','closed','reopened'] as $status): ?>
                            <option value="<?php echo $status; ?>" <?php echo ($bug['status'] ?? 'new') === $status ? 'selected' : ''; ?>><?php echo $status; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Assignee</label>
                    <select name="assignee_id" class="form-select">
                        <option value="">Unassigned</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?php echo $u['id']; ?>" <?php echo ($bug['assignee_id'] ?? '') == $u['id'] ? 'selected' : ''; ?>><?php echo h($u['username']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Due date</label>
                    <input type="date" name="due_date" class="form-control" value="<?php echo h($bug['due_date'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Steps to reproduce</label>
                    <textarea name="steps_to_reproduce" class="form-control" rows="3"><?php echo h($bug['steps_to_reproduce'] ?? ''); ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Expected result</label>
                    <textarea name="expected_result" class="form-control" rows="3"><?php echo h($bug['expected_result'] ?? ''); ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Actual result</label>
                    <textarea name="actual_result" class="form-control" rows="3"><?php echo h($bug['actual_result'] ?? ''); ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Environment</label>
                    <input type="text" name="environment" class="form-control" value="<?php echo h($bug['environment'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Estimated time (h)</label>
                    <input type="number" name="estimated_time" class="form-control" value="<?php echo h((string)($bug['estimated_time'] ?? '')); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Actual time (h)</label>
                    <input type="number" name="actual_time" class="form-control" value="<?php echo h((string)($bug['actual_time'] ?? '')); ?>">
                </div>
            </div>
            <div class="mt-3">
                <button class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>

    <?php if ($id): ?>
        <div class="card p-3">
            <h5>Comments</h5>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                <textarea name="comment_message" class="form-control mb-2" rows="2" placeholder="Add a comment"></textarea>
                <button class="btn btn-outline-primary">Post</button>
            </form>
            <div class="mt-3">
                <?php foreach ($comments as $comment): ?>
                    <div class="mb-3">
                        <div class="fw-semibold"><?php echo h($comment['username']); ?></div>
                        <div><?php echo h($comment['message']); ?></div>
                        <div class="text-muted small"><?php echo h($comment['created_at']); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
