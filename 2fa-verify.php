<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/totp.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/rate_limit.php';
$toasts = consume_toasts();

$pendingUserId = (int)($_SESSION['pending_2fa_user_id'] ?? 0);
$pendingAt = (int)($_SESSION['pending_2fa_at'] ?? 0);
if ($pendingUserId <= 0 || $pendingAt <= 0 || (time() - $pendingAt) > 600) {
    unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_at']);
    add_toast('2FA session expired. Login again.', 'danger');
    redirect('/login.php');
}

$user = fetch_one('SELECT * FROM users WHERE id = :id', [':id' => $pendingUserId]);
if (!$user) {
    unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_at']);
    add_toast('User not found', 'danger');
    redirect('/login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $code = (string)post_param('code', '');
    $scope = 'login_2fa';
    $identifier = (string)$pendingUserId;
    if (!check_rate_limit($scope, $identifier, 5, 900)) {
        log_security_event('2fa_rate_limited', ['user_id' => $pendingUserId], $pendingUserId);
        add_toast('Too many invalid 2FA attempts. Try again later.', 'danger');
    } else {
        $secret = decrypt_value((string)($user['twofa_secret_encrypted'] ?? ''));
        if ($secret !== '' && verify_totp($secret, $code)) {
            clear_rate_limit($scope, $identifier);
            unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_at']);
            $_SESSION['user_id'] = $pendingUserId;
            log_security_event('2fa_success', [], $pendingUserId);
            add_toast('Welcome back', 'success');
            redirect('/index.php');
        }
        add_rate_limit_attempt($scope, $identifier);
        log_security_event('2fa_failed', ['user_id' => $pendingUserId], $pendingUserId);
        add_toast('Invalid authentication code', 'danger');
    }
}
?>
<!doctype html>
<html lang="<?php echo h(current_language()); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>2FA Verification - YJH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
</head>
<body class="theme-light" data-theme="light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card p-4">
                <h3 class="mb-3">Two-Factor Verification</h3>
                <?php foreach ($toasts as $toast): ?>
                    <div class="alert alert-<?php echo h($toast['level'] === 'danger' ? 'danger' : 'info'); ?> py-2">
                        <?php echo h($toast['message']); ?>
                    </div>
                <?php endforeach; ?>
                <p class="text-muted small mb-3">Enter the 6-digit code from Google Authenticator.</p>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                    <div class="mb-3">
                        <label class="form-label">Authentication code</label>
                        <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" class="form-control" required>
                    </div>
                    <button class="btn btn-primary w-100">Verify</button>
                </form>
                <div class="mt-3 text-end">
                    <a href="/logout.php" class="small">Cancel login</a>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/i18n.js"></script>
</body>
</html>
