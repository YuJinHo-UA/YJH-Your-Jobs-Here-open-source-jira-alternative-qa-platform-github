<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/totp.php';
require_once __DIR__ . '/includes/encryption.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$user = current_user();
$secretSessionKey = 'twofa_setup_secret';
$pendingSecret = (string)($_SESSION[$secretSessionKey] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)post_param('action', '');

    if ($action === 'start') {
        $_SESSION[$secretSessionKey] = totp_base32_secret(32);
        add_toast('2FA setup started. Scan secret and confirm code.', 'info');
        redirect('/2fa-setup.php');
    }

    if ($action === 'enable') {
        $secret = (string)($_SESSION[$secretSessionKey] ?? '');
        $code = (string)post_param('code', '');
        if ($secret === '' || !verify_totp($secret, $code)) {
            add_toast('Invalid code. Try again.', 'danger');
            redirect('/2fa-setup.php');
        }

        $stmt = db()->prepare(
            'UPDATE users
             SET twofa_secret_encrypted = :secret, twofa_enabled = 1, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $stmt->execute([
            ':secret' => encrypt_value($secret),
            ':id' => (int)$user['id'],
        ]);
        unset($_SESSION[$secretSessionKey]);
        log_security_event('2fa_enabled', [], (int)$user['id']);
        add_toast('2FA enabled', 'success');
        redirect('/2fa-setup.php');
    }

    if ($action === 'disable') {
        $stmt = db()->prepare(
            'UPDATE users
             SET twofa_secret_encrypted = NULL, twofa_enabled = 0, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $stmt->execute([':id' => (int)$user['id']]);
        unset($_SESSION[$secretSessionKey]);
        log_security_event('2fa_disabled', [], (int)$user['id']);
        add_toast('2FA disabled', 'warning');
        redirect('/2fa-setup.php');
    }
}

$freshUser = fetch_one('SELECT * FROM users WHERE id = :id', [':id' => (int)$user['id']]);
$isEnabled = (int)($freshUser['twofa_enabled'] ?? 0) === 1;
$pendingSecret = (string)($_SESSION[$secretSessionKey] ?? '');
$account = user_email($freshUser ?: []);
$otpUri = $pendingSecret !== '' ? totp_otpauth_uri('YJH', $account, $pendingSecret) : '';
?>
<div class="app-content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>2FA Setup</h2>
    </div>

    <div class="card p-3">
        <?php if (!$isEnabled && $pendingSecret === ''): ?>
            <p class="mb-3">Enable Google Authenticator based 2FA for your account.</p>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                <input type="hidden" name="action" value="start">
                <button class="btn btn-primary">Start setup</button>
            </form>
        <?php elseif (!$isEnabled && $pendingSecret !== ''): ?>
            <p class="mb-2">Step 1: Add this secret to Google Authenticator:</p>
            <code class="d-block p-2 mb-3"><?php echo h($pendingSecret); ?></code>
            <p class="mb-2">Alternative URI:</p>
            <code class="d-block p-2 mb-3" style="word-break: break-all;"><?php echo h($otpUri); ?></code>
            <form method="post" class="row g-2">
                <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                <input type="hidden" name="action" value="enable">
                <div class="col-md-4">
                    <input class="form-control" name="code" maxlength="6" placeholder="123456" required>
                </div>
                <div class="col-md-8 d-flex gap-2">
                    <button class="btn btn-success">Enable 2FA</button>
                    <a class="btn btn-outline-secondary" href="/2fa-setup.php">Reset secret</a>
                </div>
            </form>
        <?php else: ?>
            <p class="mb-3">2FA is enabled for your account.</p>
            <form method="post" onsubmit="return confirm('Disable 2FA?');">
                <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                <input type="hidden" name="action" value="disable">
                <button class="btn btn-outline-danger">Disable 2FA</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

