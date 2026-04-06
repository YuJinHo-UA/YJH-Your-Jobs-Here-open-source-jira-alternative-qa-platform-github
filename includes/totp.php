<?php
declare(strict_types=1);

function totp_base32_secret(int $length = 32): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    for ($i = 0; $i < $length; $i++) {
        $secret .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $secret;
}

function base32_decode_str(string $value): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $clean = strtoupper(preg_replace('/[^A-Z2-7]/', '', $value) ?? '');
    $buffer = 0;
    $bitsLeft = 0;
    $output = '';

    for ($i = 0, $len = strlen($clean); $i < $len; $i++) {
        $idx = strpos($alphabet, $clean[$i]);
        if ($idx === false) {
            continue;
        }
        $buffer = ($buffer << 5) | $idx;
        $bitsLeft += 5;
        if ($bitsLeft >= 8) {
            $bitsLeft -= 8;
            $output .= chr(($buffer >> $bitsLeft) & 0xFF);
        }
    }
    return $output;
}

function totp_code(string $base32Secret, ?int $timestamp = null, int $period = 30, int $digits = 6): string
{
    $time = $timestamp ?? time();
    $counter = intdiv($time, $period);
    $secret = base32_decode_str($base32Secret);
    $counterBin = pack('N*', 0) . pack('N*', $counter);
    $hash = hash_hmac('sha1', $counterBin, $secret, true);
    $offset = ord(substr($hash, -1)) & 0x0F;
    $part = substr($hash, $offset, 4);
    $value = unpack('N', $part)[1] & 0x7FFFFFFF;
    $mod = 10 ** $digits;
    $otp = (string)($value % $mod);
    return str_pad($otp, $digits, '0', STR_PAD_LEFT);
}

function verify_totp(string $base32Secret, string $code, int $window = 1, int $period = 30): bool
{
    $cleanCode = preg_replace('/\D/', '', $code) ?? '';
    if (strlen($cleanCode) !== 6) {
        return false;
    }

    $now = time();
    for ($i = -$window; $i <= $window; $i++) {
        if (hash_equals(totp_code($base32Secret, $now + ($i * $period), $period), $cleanCode)) {
            return true;
        }
    }
    return false;
}

function totp_otpauth_uri(string $issuer, string $accountName, string $secret): string
{
    $label = rawurlencode($issuer . ':' . $accountName);
    return sprintf(
        'otpauth://totp/%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
        $label,
        rawurlencode($secret),
        rawurlencode($issuer)
    );
}

