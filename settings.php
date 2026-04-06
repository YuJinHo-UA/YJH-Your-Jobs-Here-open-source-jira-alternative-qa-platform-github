<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $theme = post_param('theme');
    $language = post_param('language');
    $password = post_param('password');

    $params = [':theme' => $theme, ':language' => $language, ':id' => $user['id']];
    $sql = 'UPDATE users SET theme=:theme, language=:language';

    if ($password) {
        $sql .= ', password_hash=:password_hash';
        $params[':password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        log_security_event('password_changed', [], (int)$user['id']);
    }

    $sql .= ' WHERE id=:id';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    add_toast('Settings updated', 'success');
    redirect('/settings.php');
}
?>
<div class="app-content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Settings</h2>
    </div>
    <div class="card p-3">
        <form method="post" data-draft-key="settings">
            <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Theme</label>
                    <select name="theme" class="form-select">
                        <option value="light" <?php echo $user['theme'] === 'light' ? 'selected' : ''; ?>>Light</option>
                        <option value="dark" <?php echo $user['theme'] === 'dark' ? 'selected' : ''; ?>>Dark</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Language</label>
                    <select name="language" class="form-select">
                        <option value="en" <?php echo $user['language'] === 'en' ? 'selected' : ''; ?>>English</option>
                        <option value="ru" <?php echo $user['language'] === 'ru' ? 'selected' : ''; ?>>Russian</option>
                        <option value="ua" <?php echo $user['language'] === 'ua' ? 'selected' : ''; ?>>Ukrainian</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">New password</label>
                    <input type="password" name="password" class="form-control" placeholder="Leave empty">
                </div>
            </div>
            <div class="mt-3">
                <button class="btn btn-primary">Save</button>
                <a href="/2fa-setup.php" class="btn btn-outline-secondary">2FA Setup</a>
            </div>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
