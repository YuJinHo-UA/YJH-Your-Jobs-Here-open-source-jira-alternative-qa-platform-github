<?php
declare(strict_types=1);

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf_token'];
}

function generateCsrfToken(): string
{
    return csrf_token();
}

function validateCsrfToken(string $token): bool
{
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken((string)$token)) {
        http_response_code(400);
        echo 'Invalid CSRF token';
        exit;
    }
}
