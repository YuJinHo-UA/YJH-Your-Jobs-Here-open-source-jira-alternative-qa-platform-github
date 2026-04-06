<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';
require_role(['admin']);
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$currentUser = current_user();
log_security_event('admin_users_access', ['path' => '/admin/users.php'], (int)($currentUser['id'] ?? 0));
$editId = (int)get_param('edit_id', 0);
$editUser = null;
if ($editId > 0) {
    $editUser = fetch_one('SELECT * FROM users WHERE id = :id', [':id' => $editId]);
}
$activeTab = (string)get_param('tab', $editUser ? 'new' : 'users');
if (!in_array($activeTab, ['new', 'users'], true)) {
    $activeTab = 'users';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post_param('action', 'save');
    $userId = (int)post_param('user_id', 0);

    if ($action === 'delete' && $userId > 0) {
        if ($userId === (int)$currentUser['id']) {
            add_toast('You cannot delete your own account', 'danger');
            redirect('/admin/users.php?tab=users');
        }
        try {
            log_security_event('admin_user_delete', ['target_user_id' => $userId], (int)$currentUser['id']);
            $stmt = db()->prepare('DELETE FROM users WHERE id = :id');
            $stmt->execute([':id' => $userId]);
            add_toast('User deleted', 'success');
        } catch (Throwable $e) {
            add_toast('Cannot delete user: linked records exist', 'danger');
        }
        redirect('/admin/users.php?tab=users');
    }

    if ($userId > 0) {
        $email = normalize_email((string)post_param('email'));
        $hash = email_hash($email);
        $marker = 'enc:' . substr($hash, 0, 24);

        $existing = fetch_one('SELECT role FROM users WHERE id = :id', [':id' => $userId]);
        $nextRole = (string)post_param('role');

        $sql = 'UPDATE users SET username=:username, email=:email, email_encrypted=:email_encrypted, email_hash=:email_hash, role=:role, updated_at=CURRENT_TIMESTAMP';
        $params = [
            ':username' => post_param('username'),
            ':email' => $marker,
            ':email_encrypted' => encrypt_value($email),
            ':email_hash' => $hash,
            ':role' => $nextRole,
            ':id' => $userId,
        ];
        $password = (string)post_param('password', '');
        if ($password !== '') {
            $sql .= ', password_hash=:password_hash';
            $params[':password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }
        $sql .= ' WHERE id=:id';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        if (($existing['role'] ?? '') !== $nextRole) {
            log_security_event('admin_role_changed', [
                'target_user_id' => $userId,
                'from' => $existing['role'] ?? null,
                'to' => $nextRole,
            ], (int)$currentUser['id']);
        }

        add_toast('User updated', 'success');
    } else {
        $email = normalize_email((string)post_param('email'));
        $hash = email_hash($email);
        $marker = 'enc:' . substr($hash, 0, 24);
        $stmt = db()->prepare('INSERT INTO users (username, email, password_hash, role) VALUES (:username, :email, :password_hash, :role)');
        $stmt->execute([
            ':username' => post_param('username'),
            ':email' => $marker,
            ':password_hash' => password_hash(post_param('password'), PASSWORD_DEFAULT),
            ':role' => post_param('role'),
        ]);

        $newUserId = (int)db()->lastInsertId();
        $update = db()->prepare('UPDATE users SET email_encrypted = :email_encrypted, email_hash = :email_hash WHERE id = :id');
        $update->execute([
            ':email_encrypted' => encrypt_value($email),
            ':email_hash' => $hash,
            ':id' => $newUserId,
        ]);

        log_security_event('admin_user_created', ['target_user_id' => $newUserId], (int)$currentUser['id']);
        add_toast('User created', 'success');
    }
    redirect('/admin/users.php?tab=' . ($userId > 0 ? 'users' : 'new'));
}

$users = fetch_all('SELECT * FROM users ORDER BY created_at DESC');
foreach ($users as &$u) {
    $u['display_email'] = user_email($u);
}
unset($u);
$editUserEmail = $editUser ? user_email($editUser) : '';
?>
<div class="app-content">
    <div class="users-header">
        <h2 class="mb-0">Users</h2>
        <div class="text-muted small">Manage accounts, roles and access</div>
    </div>
    <div class="row justify-content-center">
        <div class="col-12 col-xxl-10">
            <div class="card users-panel p-3">
                <ul class="nav nav-tabs admin-tabs mb-3">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $activeTab === 'new' ? 'active' : ''; ?>" href="/admin/users.php?tab=new">New User</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $activeTab === 'users' ? 'active' : ''; ?>" href="/admin/users.php?tab=users">Users Base</a>
                    </li>
                </ul>

                <?php if ($activeTab === 'new'): ?>
                <div class="form-narrow mx-auto w-100">
                <h6 class="mb-3"><?php echo $editUser ? 'Edit User' : 'Create User'; ?></h6>
                <form method="post" data-draft-key="user">
                    <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="user_id" value="<?php echo (int)($editUser['id'] ?? 0); ?>">
                    <input class="form-control mb-2" name="username" placeholder="Username" value="<?php echo h($editUser['username'] ?? ''); ?>" required autocomplete="off">
                    <input class="form-control mb-2" name="email" type="email" placeholder="Email" value="<?php echo h($editUserEmail); ?>" required autocomplete="off">
                    <input class="form-control mb-2" name="password" type="password" placeholder="<?php echo $editUser ? 'Leave empty to keep password' : 'Password'; ?>" <?php echo $editUser ? '' : 'required'; ?> autocomplete="new-password">
                    <select class="form-select mb-3" name="role">
                        <option value="admin" <?php echo ($editUser['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="qa" <?php echo ($editUser['role'] ?? '') === 'qa' ? 'selected' : ''; ?>>QA</option>
                        <option value="developer" <?php echo ($editUser['role'] ?? '') === 'developer' ? 'selected' : ''; ?>>Developer</option>
                        <option value="viewer" <?php echo ($editUser['role'] ?? '') === 'viewer' ? 'selected' : ''; ?>>Viewer</option>
                    </select>
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary flex-grow-1"><?php echo $editUser ? 'Update User' : 'Create User'; ?></button>
                        <?php if ($editUser): ?>
                            <a class="btn btn-outline-secondary" href="/admin/users.php?tab=users">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                <table class="table users-table mb-0">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?php echo h($u['username']); ?></td>
                            <td><?php echo h($u['display_email']); ?></td>
                            <td>
                                <span class="users-role-badge role-<?php echo h($u['role']); ?>"><?php echo h($u['role']); ?></span>
                            </td>
                            <td>
                                <div class="users-actions">
                                <a class="btn btn-sm btn-outline-primary" href="/admin/users.php?tab=new&edit_id=<?php echo (int)$u['id']; ?>">
                                    <i class="fa-solid fa-pen-to-square me-1"></i>Edit
                                </a>
                                <?php if ((int)$u['id'] !== (int)$currentUser['id']): ?>
                                    <form method="post" onsubmit="return confirm('Delete user?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                        <button class="btn btn-sm btn-outline-danger">
                                            <i class="fa-solid fa-trash me-1"></i>Delete
                                        </button>
                                    </form>
                                <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
