<?php
declare(strict_types=1);

const YJH_CIPHER = 'AES-256-CBC';

<<<<<<< HEAD
function yjh_key_file_path(): string
{
    return __DIR__ . '/../.yjh-secrets/encryption.key';
}

function yjh_persistent_local_key(): string
{
    $path = yjh_key_file_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }

    if (is_file($path)) {
        $existing = trim((string)@file_get_contents($path));
        if ($existing !== '') {
            return $existing;
        }
    }

    $generated = base64_encode(random_bytes(32));
    @file_put_contents($path, $generated, LOCK_EX);
    return $generated;
}

=======
>>>>>>> 7e7a5ae49ac6caacc4b2a0ad95dd06bd60dfa616
function yjh_encryption_key(): string
{
    $configured = (string)(getenv('YJH_ENCRYPTION_KEY') ?: '');
    if ($configured === '') {
<<<<<<< HEAD
        // Per-install local key fallback (not committed to git).
        $configured = yjh_persistent_local_key();
=======
        // Development fallback. Set YJH_ENCRYPTION_KEY in production.
        $configured = 'change-this-dev-key-32-bytes-minimum';
>>>>>>> 7e7a5ae49ac6caacc4b2a0ad95dd06bd60dfa616
    }
    return hash('sha256', $configured, true);
}

function encrypt_value(string $plainText): string
{
    $iv = random_bytes(16);
    $cipherText = openssl_encrypt($plainText, YJH_CIPHER, yjh_encryption_key(), OPENSSL_RAW_DATA, $iv);
    if ($cipherText === false) {
        throw new RuntimeException('Unable to encrypt value');
    }
    return base64_encode($iv . $cipherText);
}

function decrypt_value(?string $encoded): string
{
    $payload = (string)$encoded;
    if ($payload === '') {
        return '';
    }
    $decoded = base64_decode($payload, true);
    if ($decoded === false || strlen($decoded) < 17) {
        return '';
    }
    $iv = substr($decoded, 0, 16);
    $cipherText = substr($decoded, 16);
    $plain = openssl_decrypt($cipherText, YJH_CIPHER, yjh_encryption_key(), OPENSSL_RAW_DATA, $iv);
    return $plain === false ? '' : $plain;
}

function email_hash(string $email): string
{
    $normalized = strtolower(trim($email));
    return hash('sha256', $normalized);
}
