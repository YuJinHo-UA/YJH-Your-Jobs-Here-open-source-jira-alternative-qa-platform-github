<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$user = current_user();
$id = (int)get_param('id');
$page = fetch_one('SELECT wp.*, p.name AS project_name FROM wiki_pages wp JOIN projects p ON p.id = wp.project_id WHERE wp.id = :id', [':id' => $id]);
if (!$page) {
    echo '<div class="app-content">Page not found</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)post_param('action');
    if ($action === 'delete_page') {
        delete_row('wiki_pages', ['id' => $id]);
        add_toast('Page deleted', 'success');
        redirect('/wiki.php?project_id=' . (int)$page['project_id']);
    }

    $newContent = post_param('content');
    $newTitle = post_param('title');
    $newSlug = trim((string)post_param('slug'));
    if ($newSlug === '') {
        $newSlug = (string)$page['slug'];
    }

    $stmt = db()->prepare('INSERT INTO wiki_history (page_id, user_id, content_diff, version) VALUES (:page_id, :user_id, :content_diff, :version)');
    $stmt->execute([
        ':page_id' => $id,
        ':user_id' => $user['id'],
        ':content_diff' => $page['content'],
        ':version' => $page['version'],
    ]);

    $stmt = db()->prepare('UPDATE wiki_pages SET slug=:slug, title=:title, content=:content, editor_id=:editor_id, version=version+1, updated_at=CURRENT_TIMESTAMP WHERE id=:id');
    $stmt->execute([
        ':slug' => $newSlug,
        ':title' => $newTitle,
        ':content' => $newContent,
        ':editor_id' => $user['id'],
        ':id' => $id,
    ]);

    add_toast('Page updated', 'success');
    redirect('/wiki-page.php?id=' . $id);
}

$history = fetch_all('SELECT * FROM wiki_history WHERE page_id = :id ORDER BY version DESC', [':id' => $id]);
$leftVersion = (int)(get_param('left') ?: ($history[0]['version'] ?? $page['version']));
$rightVersion = (int)(get_param('right') ?: $page['version']);

$leftContent = $leftVersion === $page['version'] ? $page['content'] : (fetch_one('SELECT content_diff FROM wiki_history WHERE page_id = :id AND version = :version', [':id' => $id, ':version' => $leftVersion])['content_diff'] ?? '');
$rightContent = $rightVersion === $page['version'] ? $page['content'] : (fetch_one('SELECT content_diff FROM wiki_history WHERE page_id = :id AND version = :version', [':id' => $id, ':version' => $rightVersion])['content_diff'] ?? '');
?>
<div class="app-content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><?php echo h($page['title']); ?></h2>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="/wiki.php?project_id=<?php echo (int)$page['project_id']; ?>">Back</a>
            <form method="post" onsubmit="return confirm('Delete this page?');">
                <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                <input type="hidden" name="action" value="delete_page">
                <button class="btn btn-outline-danger" type="submit">Delete</button>
            </form>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card p-3">
                <h6>Edit Page</h6>
                <form method="post" data-draft-key="wiki-<?php echo $id; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                    <div class="small text-muted mb-2">Project: <?php echo h((string)$page['project_name']); ?></div>
                    <input class="form-control mb-2" name="slug" value="<?php echo h((string)$page['slug']); ?>" required>
                    <input class="form-control mb-2" name="title" value="<?php echo h($page['title']); ?>" required>
                    <textarea class="form-control mb-2" name="content" rows="10"><?php echo h($page['content']); ?></textarea>
                    <button class="btn btn-primary">Save</button>
                </form>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card p-3">
                <h6>Version Compare</h6>
                <form method="get" class="d-flex gap-2 mb-3">
                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                    <select name="left" class="form-select">
                        <option value="<?php echo $page['version']; ?>">Current</option>
                        <?php foreach ($history as $row): ?>
                            <option value="<?php echo $row['version']; ?>" <?php echo $leftVersion === (int)$row['version'] ? 'selected' : ''; ?>>v<?php echo $row['version']; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="right" class="form-select">
                        <option value="<?php echo $page['version']; ?>">Current</option>
                        <?php foreach ($history as $row): ?>
                            <option value="<?php echo $row['version']; ?>" <?php echo $rightVersion === (int)$row['version'] ? 'selected' : ''; ?>>v<?php echo $row['version']; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-outline-primary">Compare</button>
                </form>
                <div class="row">
                    <div class="col-6">
                        <div class="text-muted small">Left</div>
                        <pre class="small compare-pre p-2 rounded" style="white-space: pre-wrap;"><?php echo h($leftContent); ?></pre>
                    </div>
                    <div class="col-6">
                        <div class="text-muted small">Right</div>
                        <pre class="small compare-pre p-2 rounded" style="white-space: pre-wrap;"><?php echo h($rightContent); ?></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
