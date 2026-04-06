<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/theme.php';
$user = current_user();
$toasts = consume_toasts();
$themeName = theme_name();
$themeClass = theme_class();
$language = current_language();
$returnPath = $_SERVER['REQUEST_URI'] ?? '/index.php';
$returnPath = is_string($returnPath) && str_starts_with($returnPath, '/') ? $returnPath : '/index.php';
$langEnHref = '/set-language.php?lang=en&return=' . urlencode($returnPath);
$langRuHref = '/set-language.php?lang=ru&return=' . urlencode($returnPath);
$langUaHref = '/set-language.php?lang=ua&return=' . urlencode($returnPath);
?>
<!doctype html>
<html lang="<?php echo h($language); ?>" class="<?php echo h($themeClass); ?>" data-theme="<?php echo h($themeName); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>YJH</title>
    <script>
        (function () {
            var savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark' || savedTheme === 'light') {
                var root = document.documentElement;
                root.classList.remove('theme-dark', 'theme-light');
                root.classList.add('theme-' + savedTheme);
                root.dataset.theme = savedTheme;
            }
        })();
    </script>
    <style>
        html, body { background: #f8fafc; color: #0f172a; }
        html.theme-dark, html.theme-dark body { background: #0f172a; color: #e2e8f0; }
    </style>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
</head>
<body data-theme="<?php echo h($themeName); ?>">
<div class="app-shell">
    <header class="app-header d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-link text-light d-lg-none" id="mobileMenuBtn"><i class="fa-solid fa-bars"></i></button>
            <div class="brand-wrap">
                <div class="brand">YJH</div>
                <div class="brand-sub">Your Job's Here</div>
            </div>
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="globalSearchInput" placeholder="Search (Ctrl+K)" autocomplete="off">
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <button class="theme-switch" id="themeToggle" type="button" aria-label="Toggle theme" title="Toggle theme">
                <span class="theme-switch-track">
                    <i class="fa-solid fa-sun theme-icon-sun"></i>
                    <i class="fa-solid fa-moon theme-icon-moon"></i>
                    <span class="theme-switch-knob"></span>
                </span>
            </button>
            <div class="dropdown lang-dropdown">
                <button class="lang-toggle dropdown-toggle d-flex align-items-center justify-content-center" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="Change language" title="Change language">
                    <i class="fa-solid fa-globe"></i>
                    <span class="lang-toggle-code"><?php echo strtoupper(h($language)); ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item <?php echo $language === 'en' ? 'active' : ''; ?>" href="<?php echo h($langEnHref); ?>">English (EN)</a></li>
                    <li><a class="dropdown-item <?php echo $language === 'ru' ? 'active' : ''; ?>" href="<?php echo h($langRuHref); ?>">Russian (RU)</a></li>
                    <li><a class="dropdown-item <?php echo $language === 'ua' ? 'active' : ''; ?>" href="<?php echo h($langUaHref); ?>">Ukrainian (UA)</a></li>
                </ul>
            </div>
            <div class="user-chip">
                <span><?php echo h($user['username'] ?? ''); ?></span>
                <a class="text-light" href="/settings.php"><i class="fa-solid fa-gear"></i></a>
                <a class="text-light" href="/logout.php"><i class="fa-solid fa-right-from-bracket"></i></a>
            </div>
        </div>
    </header>
    <div class="app-body">

