<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/encryption.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/security_headers.php';

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function e(?string $value): string
{
    return h($value);
}

function normalize_email(string $email): string
{
    return trim(strtolower($email));
}

function user_email(array $user): string
{
    $encrypted = (string)($user['email_encrypted'] ?? '');
    if ($encrypted !== '') {
        $decrypted = decrypt_value($encrypted);
        if ($decrypted !== '') {
            return $decrypted;
        }
    }
    return (string)($user['email'] ?? '');
}

function current_user(): ?array
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM users WHERE id = :id');
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function require_login(): void
{
    if (!current_user()) {
        header('Location: /login.php');
        exit;
    }
}

function require_role(array $roles): void
{
    $user = current_user();
    if (!$user || !in_array($user['role'], $roles, true)) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

function redirect(string $path): void
{
    if (!headers_sent()) {
        header('Location: ' . $path);
        exit;
    }

    $safePath = h($path);
    echo '<script>window.location.href=' . json_encode($path, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . $safePath . '"></noscript>';
    exit;
}

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function get_param(string $key, $default = null)
{
    return $_GET[$key] ?? $default;
}

function post_param(string $key, $default = null)
{
    return $_POST[$key] ?? $default;
}

function normalize_language(string $lang): string
{
    return in_array($lang, ['en', 'ru', 'ua'], true) ? $lang : 'en';
}

function current_language(): string
{
    $user = current_user();
    if ($user && isset($user['language'])) {
        return normalize_language((string)$user['language']);
    }

    if (isset($_COOKIE['lang'])) {
        return normalize_language((string)$_COOKIE['lang']);
    }

    return 'en';
}

function add_toast(string $message, string $level = 'info'): void
{
    $_SESSION['toasts'][] = ['message' => $message, 'level' => $level];
}

function consume_toasts(): array
{
    $toasts = $_SESSION['toasts'] ?? [];
    $_SESSION['toasts'] = [];
    return $toasts;
}

function is_active(string $path): string
{
    $current = $_SERVER['SCRIPT_NAME'] ?? '';
    return str_contains($current, $path) ? 'active' : '';
}

function fetch_all(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function fetch_one(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ?: null;
}

function deletable_tables(): array
{
    return [
        'users',
        'user_settings',
        'user_availability',
        'user_shortcuts',
        'projects',
        'releases',
        'bugs',
        'bug_comments',
        'bug_history',
        'bug_mentions',
        'bug_watchers',
        'bug_templates',
        'bug_similarity_cache',
        'git_integrations',
        'git_commits',
        'test_plans',
        'test_suites',
        'test_cases',
        'test_runs',
        'test_executions',
        'testcase_templates',
        'wiki_pages',
        'wiki_history',
        'wiki_attachments',
        'boards',
        'board_columns',
        'board_cards',
        'card_comments',
        'card_attachments',
        'attachments',
        'activity_log',
        'saved_filters',
        'webhooks',
        'public_links',
        'achievements',
        'user_achievements',
        'notifications',
        'translation_cache',
        'security_log',
        'rate_limit_entries',
        'ai_cache',
        'ai_logs',
        'ai_templates',
    ];
}

function delete_row(string $table, array $keyValues): int
{
    if (!in_array($table, deletable_tables(), true)) {
        throw new InvalidArgumentException('Table is not allowed for deletion');
    }
    if ($keyValues === []) {
        throw new InvalidArgumentException('Delete key is required');
    }

    $schema = fetch_all("PRAGMA table_info($table)");
    $validColumns = array_column($schema, 'name');
    foreach ($keyValues as $column => $_value) {
        if (!in_array((string)$column, $validColumns, true)) {
            throw new InvalidArgumentException('Invalid key column: ' . $column);
        }
    }

    $clauses = [];
    $params = [];
    foreach ($keyValues as $column => $value) {
        $param = ':k_' . preg_replace('/[^a-zA-Z0-9_]/', '_', (string)$column);
        $clauses[] = $column . ' = ' . $param;
        $params[$param] = $value;
    }

    $sql = 'DELETE FROM ' . $table . ' WHERE ' . implode(' AND ', $clauses);
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $affected = $stmt->rowCount();
    if ($affected > 0) {
        $targetId = (int)($keyValues['id'] ?? 0);
        record_activity('deleted', $table, $targetId, ['keys' => $keyValues, 'affected' => $affected]);
    }
    return $affected;
}

function record_activity(string $action, string $targetType, int $targetId, array $details = []): void
{
    try {
        $user = current_user();
        $userId = $user ? (int)$user['id'] : null;
        $stmt = db()->prepare(
            'INSERT INTO activity_log (user_id, action, target_type, target_id, details_json)
             VALUES (:user_id, :action, :target_type, :target_id, :details_json)'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':action' => $action,
            ':target_type' => $targetType,
            ':target_id' => $targetId,
            ':details_json' => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        yjh_log_write('app', json_encode([
            'user_id' => $userId,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'details' => $details,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $action . ' ' . $targetType . ' #' . $targetId);
        refresh_program_activity_doc();
    } catch (Throwable $_e) {
        // Best-effort logging should not break user flows.
    }
}

function refresh_program_activity_doc(): void
{
    $rows = fetch_all(
        'SELECT id, user_id, action, target_type, target_id, details_json, created_at
         FROM activity_log
         ORDER BY id DESC
         LIMIT 500'
    );

    $lines = [
        '# Program Activity Log',
        '',
        'Generated: ' . date('Y-m-d H:i:s'),
        'Source: `activity_log` table',
        '',
        '## Entries',
        '',
    ];

    if (!$rows) {
        $lines[] = '- No activity entries found.';
    } else {
        foreach ($rows as $row) {
            $details = trim((string)($row['details_json'] ?? ''));
            if ($details === '') {
                $details = '{}';
            }
            $lines[] = sprintf(
                '- `#%d` | %s | user=%s | %s `%s` id=%d | details=%s',
                (int)$row['id'],
                (string)($row['created_at'] ?? ''),
                (string)($row['user_id'] ?? 'null'),
                (string)($row['action'] ?? ''),
                (string)($row['target_type'] ?? ''),
                (int)($row['target_id'] ?? 0),
                $details
            );
        }
    }

    $path = __DIR__ . '/../docs/PROGRAM_ACTIVITY_LOG.md';
    @file_put_contents($path, implode(PHP_EOL, $lines) . PHP_EOL);
}

function detect_text_language(string $text): string
{
    $normalized = trim($text);
    if ($normalized === '') {
        return 'en';
    }
    if (preg_match('/[\x{0400}-\x{04FF}]/u', $normalized)) {
        return 'ru';
    }
    return 'en';
}

function translation_provider_language(string $lang): string
{
    return match ($lang) {
        'ru' => 'ru',
        'ua' => 'uk',
        default => 'en',
    };
}

function translate_text_remote(string $text, string $sourceLang, string $targetLang): string
{
    $url = getenv('YJH_TRANSLATE_URL') ?: 'https://libretranslate.de/translate';
    $apiKey = getenv('YJH_TRANSLATE_API_KEY') ?: '';
    $source = translation_provider_language($sourceLang);
    $target = translation_provider_language($targetLang);
    $payload = [
        'q' => $text,
        'source' => $source,
        'target' => $target,
        'format' => 'text',
    ];
    if ($apiKey !== '') {
        $payload['api_key'] = $apiKey;
    }
    $json = json_encode($payload);
    if ($json === false) {
        return $text;
    }

    $responseBody = null;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_TIMEOUT => 8,
        ]);
        $responseBody = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($responseBody !== false && $httpCode < 400) {
            $decoded = json_decode((string)$responseBody, true);
            $translated = trim((string)($decoded['translatedText'] ?? ''));
            if ($translated !== '') {
                return $translated;
            }
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $json,
                'timeout' => 8,
            ],
        ]);
        $responseBody = @file_get_contents($url, false, $context);
        if ($responseBody !== false) {
            $decoded = json_decode((string)$responseBody, true);
            $translated = trim((string)($decoded['translatedText'] ?? ''));
            if ($translated !== '') {
                return $translated;
            }
        }
    }

    // Fallback provider without API key requirement.
    $fallbackUrl = 'https://api.mymemory.translated.net/get?q=' . rawurlencode($text) . '&langpair=' . rawurlencode($source . '|' . $target);
    $fallbackBody = @file_get_contents($fallbackUrl, false, stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 8,
        ],
    ]));
    if ($fallbackBody !== false) {
        $fallbackDecoded = json_decode((string)$fallbackBody, true);
        $fallbackTranslated = trim((string)($fallbackDecoded['responseData']['translatedText'] ?? ''));
        if ($fallbackTranslated !== '') {
            return html_entity_decode($fallbackTranslated, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }

    return $text;
}

function translate_text_cached(string $text, string $targetLang, ?string $sourceLang = null): string
{
    $original = trim($text);
    if ($original === '') {
        return $text;
    }

    $source = normalize_language($sourceLang ?? detect_text_language($original));
    $target = normalize_language($targetLang);
    if ($source === $target) {
        return $text;
    }

    $hash = hash('sha256', $original);
    $row = fetch_one(
        'SELECT translated_text FROM translation_cache WHERE source_lang = :source_lang AND target_lang = :target_lang AND text_hash = :text_hash',
        [':source_lang' => $source, ':target_lang' => $target, ':text_hash' => $hash]
    );
    if ($row && isset($row['translated_text'])) {
        return (string)$row['translated_text'];
    }

    $translated = translate_text_remote($original, $source, $target);
    if ($translated === '') {
        $translated = $original;
    }

    $stmt = db()->prepare(
        'INSERT INTO translation_cache (source_lang, target_lang, text_hash, source_text, translated_text, provider, updated_at)
         VALUES (:source_lang, :target_lang, :text_hash, :source_text, :translated_text, :provider, CURRENT_TIMESTAMP)
         ON CONFLICT(source_lang, target_lang, text_hash)
         DO UPDATE SET translated_text = excluded.translated_text, provider = excluded.provider, updated_at = CURRENT_TIMESTAMP'
    );
    $stmt->execute([
        ':source_lang' => $source,
        ':target_lang' => $target,
        ':text_hash' => $hash,
        ':source_text' => $original,
        ':translated_text' => $translated,
        ':provider' => 'libretranslate',
    ]);

    return $translated;
}

function is_user_available(int $userId, string $dateFrom, ?string $dateTo = null): bool
{
    if ($userId <= 0) {
        return true;
    }
    $startDate = trim($dateFrom);
    $endDate = trim($dateTo ?? $dateFrom);
    if ($startDate === '' || $endDate === '') {
        return true;
    }

    $stmt = db()->prepare(
        'SELECT COUNT(*)
         FROM user_availability
         WHERE user_id = :user_id
           AND start_date <= :end_date
           AND end_date >= :start_date'
    );
    $stmt->execute([
        ':user_id' => $userId,
        ':start_date' => $startDate,
        ':end_date' => $endDate,
    ]);
    return (int)$stmt->fetchColumn() === 0;
}

function get_user_unavailability(int $userId, ?string $date = null): ?array
{
    if ($userId <= 0) {
        return null;
    }
    $checkDate = trim($date ?? date('Y-m-d'));
    if ($checkDate === '') {
        return null;
    }

    return fetch_one(
        'SELECT *
         FROM user_availability
         WHERE user_id = :user_id
           AND start_date <= :check_date
           AND end_date >= :check_date
         ORDER BY start_date ASC
         LIMIT 1',
        [':user_id' => $userId, ':check_date' => $checkDate]
    );
}

apply_security_headers();