<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$user = current_user();
$projects = fetch_all('SELECT * FROM projects');
$selectedProjectId = (int)get_param('project_id', 0);
$validProjectIds = array_map(static fn(array $project): int => (int)$project['id'], $projects);
if ($selectedProjectId > 0 && !in_array($selectedProjectId, $validProjectIds, true)) {
    $selectedProjectId = 0;
}

$pagesSql = '
    SELECT wp.*, p.name AS project_name
    FROM wiki_pages wp
    JOIN projects p ON p.id = wp.project_id
    WHERE 1=1
';
$pagesParams = [];
if ($selectedProjectId > 0) {
    $pagesSql .= ' AND wp.project_id = :project_id';
    $pagesParams[':project_id'] = $selectedProjectId;
}
$pagesSql .= ' ORDER BY wp.created_at DESC';
$pages = fetch_all($pagesSql, $pagesParams);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)post_param('action');
    if ($action === 'delete_page') {
        $pageId = (int)post_param('page_id');
        if ($pageId > 0) {
            delete_row('wiki_pages', ['id' => $pageId]);
            add_toast('Page deleted', 'success');
        }
        $redirectQuery = $selectedProjectId > 0 ? ('?project_id=' . $selectedProjectId) : '';
        redirect('/wiki.php' . $redirectQuery);
    }

    $stmt = db()->prepare('INSERT INTO wiki_pages (project_id, parent_id, slug, title, content, author_id, editor_id) VALUES (:project_id, :parent_id, :slug, :title, :content, :author_id, :editor_id)');
    $stmt->execute([
        ':project_id' => post_param('project_id'),
        ':parent_id' => post_param('parent_id') ?: null,
        ':slug' => post_param('slug'),
        ':title' => post_param('title'),
        ':content' => post_param('content'),
        ':author_id' => $user['id'],
        ':editor_id' => $user['id'],
    ]);
    add_toast('Page created', 'success');
    redirect('/wiki.php');
}
?>
<div class="app-content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Wiki</h2>
        <form method="get" class="d-flex align-items-center gap-2">
            <label for="wikiProjectFilter" class="small text-muted mb-0">Project</label>
            <select id="wikiProjectFilter" name="project_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="0">All projects</option>
                <?php foreach ($projects as $project): ?>
                    <option value="<?php echo (int)$project['id']; ?>" <?php echo $selectedProjectId === (int)$project['id'] ? 'selected' : ''; ?>>
                        <?php echo h($project['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card p-3">
                <h6>Create Page</h6>
                <form method="post" data-draft-key="wiki">
                    <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                    <select class="form-select mb-2" name="project_id" required>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?php echo $project['id']; ?>" <?php echo $selectedProjectId === (int)$project['id'] ? 'selected' : ''; ?>><?php echo h($project['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input class="form-control mb-2" name="slug" placeholder="slug" required>
                    <input class="form-control mb-2" name="title" placeholder="Title" required>
                    <textarea class="form-control mb-2" name="content" rows="5" placeholder="Markdown"></textarea>
                    <button class="btn btn-primary">Save</button>
                </form>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card p-3">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Project</th>
                            <th>Slug</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pages as $page): ?>
                        <tr>
                            <td><?php echo h($page['title']); ?></td>
                            <td><?php echo h((string)$page['project_name']); ?></td>
                            <td><?php echo h($page['slug']); ?></td>
                            <td class="d-flex gap-2">
                                <a href="/wiki-page.php?id=<?php echo $page['id']; ?>">Open/Edit</a>
                                <form method="post" onsubmit="return confirm('Delete wiki page?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                                    <input type="hidden" name="action" value="delete_page">
                                    <input type="hidden" name="page_id" value="<?php echo (int)$page['id']; ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
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
