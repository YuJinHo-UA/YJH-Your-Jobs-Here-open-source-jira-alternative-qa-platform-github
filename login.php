<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/rate_limit.php';
require_once __DIR__ . '/includes/security.php';
$language = current_language();
$toasts = consume_toasts();
$emailValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = normalize_email((string)post_param('email'));
    $emailValue = $email;
    $password = post_param('password');
    verify_csrf();

    if (!check_rate_limit('login', $email, 5, 900)) {
        log_security_event('login_rate_limited', ['email_hash' => email_hash($email)]);
        add_toast('Too many login attempts. Try again in 15 minutes.', 'danger');
    } else {
    $stmt = db()->prepare('SELECT * FROM users WHERE email_hash = :email_hash OR email = :legacy_email LIMIT 1');
    $stmt->execute([':email_hash' => email_hash($email), ':legacy_email' => $email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        clear_rate_limit('login', $email);
        $is2faEnabled = (int)($user['twofa_enabled'] ?? 0) === 1;
        $secretEncrypted = (string)($user['twofa_secret_encrypted'] ?? '');
        if ($is2faEnabled && $secretEncrypted !== '') {
            $_SESSION['pending_2fa_user_id'] = (int)$user['id'];
            $_SESSION['pending_2fa_at'] = time();
            log_security_event('login_password_ok_2fa_required', ['user_id' => (int)$user['id']], (int)$user['id']);
            redirect('/2fa-verify.php');
        }

        $_SESSION['user_id'] = $user['id'];
        log_security_event('login_success', [], (int)$user['id']);
        add_toast('Welcome back', 'success');
        redirect('/index.php');
    }

    add_rate_limit_attempt('login', $email);
    log_security_event('login_failed', ['email_hash' => email_hash($email)]);
    add_toast('Invalid credentials', 'danger');
    }
}
?>
<!doctype html>
<html lang="<?php echo h($language); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - YJH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="theme-light" data-theme="light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card p-4">
                <h3 class="mb-3">Sign in</h3>
                <?php foreach ($toasts as $toast): ?>
                    <div class="alert alert-<?php echo h($toast['level'] === 'danger' ? 'danger' : 'info'); ?> py-2">
                        <?php echo h($toast['message']); ?>
                    </div>
                <?php endforeach; ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?php echo h($emailValue); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button class="btn btn-primary w-100">Login</button>
                </form>
                <div class="mt-3 text-muted small">Use your own account credentials.</div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/i18n.js"></script>
</body>
</html>
