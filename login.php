<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';
$language = current_language();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = post_param('email');
    $password = post_param('password');
    verify_csrf();

    $stmt = db()->prepare('SELECT * FROM users WHERE email = :email');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        add_toast('Welcome back', 'success');
        redirect('/index.php');
    }

    add_toast('Invalid credentials', 'danger');
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
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button class="btn btn-primary w-100">Login</button>
                </form>
                <div class="mt-3 text-muted small">Demo: admin@yujin.ho / admin123</div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/i18n.js"></script>
</body>
</html>
