<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$user = current_user();
$projects = fetch_all('SELECT * FROM projects');
$pages = fetch_all('SELECT * FROM wiki_pages ORDER BY created_at DESC');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
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
    </div>
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card p-3">
                <h6>Create Page</h6>
                <form method="post" data-draft-key="wiki">
                    <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                    <select class="form-select mb-2" name="project_id" required>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?php echo $project['id']; ?>"><?php echo h($project['name']); ?></option>
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
                            <th>Slug</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pages as $page): ?>
                        <tr>
                            <td><?php echo h($page['title']); ?></td>
                            <td><?php echo h($page['slug']); ?></td>
                            <td><a href="/wiki-page.php?id=<?php echo $page['id']; ?>">Open</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
