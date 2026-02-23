<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../ai/ai_helper.php';

session_start();
$user = current_user();
if (!$user) {
    json_response(['error' => 'Unauthorized'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$payload = json_decode(file_get_contents('php://input'), true) ?: [];
$action = trim((string)($payload['action'] ?? ''));
if ($action === '') {
    json_response(['error' => 'Action is required'], 400);
}

$ai = new AIHelper();
$startedAt = microtime(true);

try {
    switch ($action) {
        case 'generate_test_cases':
            $feature = trim((string)($payload['feature'] ?? ''));
            if ($feature === '') {
                json_response(['error' => 'Feature description required'], 400);
            }
            $result = $ai->generateTestCases($feature);
            $decoded = decode_json_safely($result['text']);
            write_ai_log((int)$user['id'], 'test_case', $feature, $result['text'], (int)($result['tokens_used'] ?? 0), duration_ms($startedAt));
            json_response(['success' => true, 'data' => $decoded, 'meta' => ['model' => $result['model'], 'cached' => (bool)($result['cached'] ?? false)]]);

        case 'assist_bug':
            $title = trim((string)($payload['title'] ?? ''));
            $description = trim((string)($payload['description'] ?? ''));
            if ($title === '' && $description === '') {
                json_response(['error' => 'Title or description is required'], 400);
            }
            $result = $ai->analyzeBug($title, $description);
            $decoded = decode_json_safely($result['text']);
            write_ai_log((int)$user['id'], 'bug_analysis', $title . "\n" . $description, $result['text'], (int)($result['tokens_used'] ?? 0), duration_ms($startedAt));
            json_response(['success' => true, 'data' => $decoded, 'meta' => ['model' => $result['model'], 'cached' => (bool)($result['cached'] ?? false)]]);

        case 'check_duplicates':
            $title = trim((string)($payload['title'] ?? ''));
            $description = trim((string)($payload['description'] ?? ''));
            if ($title === '' && $description === '') {
                json_response(['error' => 'Title or description is required'], 400);
            }
            $existingBugs = fetch_all('SELECT id, title, description, created_at, status FROM bugs ORDER BY created_at DESC LIMIT 150');
            $result = $ai->checkDuplicates($title, $description, $existingBugs);
            $decoded = decode_json_safely($result['text']);
            write_ai_log((int)$user['id'], 'duplicate_check', $title . "\n" . $description, $result['text'], (int)($result['tokens_used'] ?? 0), duration_ms($startedAt));
            json_response(['success' => true, 'data' => $decoded, 'meta' => ['model' => $result['model'], 'cached' => (bool)($result['cached'] ?? false)]]);

        case 'generate_report':
            $days = max(1, min(90, (int)($payload['days'] ?? 7)));
            $stats = build_report_stats($days);
            $result = $ai->generateWeeklyReport($stats);
            $decoded = decode_json_safely($result['text']);
            write_ai_log((int)$user['id'], 'report', json_encode($stats, JSON_UNESCAPED_UNICODE) ?: '', $result['text'], (int)($result['tokens_used'] ?? 0), duration_ms($startedAt));
            json_response(['success' => true, 'data' => $decoded, 'meta' => ['model' => $result['model'], 'cached' => (bool)($result['cached'] ?? false), 'days' => $days]]);

        case 'chat':
            $message = trim((string)($payload['message'] ?? ''));
            $page = trim((string)($payload['page'] ?? ''));
            if ($message === '') {
                json_response(['error' => 'Message is required'], 400);
            }
            $result = $ai->chat($message, $page);
            $decoded = decode_json_safely($result['text']);
            write_ai_log((int)$user['id'], 'chat', $message, $result['text'], (int)($result['tokens_used'] ?? 0), duration_ms($startedAt));
            json_response(['success' => true, 'data' => $decoded, 'meta' => ['model' => $result['model'], 'cached' => (bool)($result['cached'] ?? false)]]);

        default:
            json_response(['error' => 'Action not found'], 404);
    }
} catch (Throwable $e) {
    json_response(['error' => 'AI processing failed', 'details' => $e->getMessage()], 500);
}

function decode_json_safely(string $text): array
{
    $decoded = json_decode($text, true);
    if (is_array($decoded)) {
        return $decoded;
    }
    return ['raw' => $text];
}

function duration_ms(float $startedAt): int
{
    return (int)round((microtime(true) - $startedAt) * 1000);
}

function write_ai_log(int $userId, string $actionType, string $prompt, string $response, int $tokens, int $durationMs): void
{
    $stmt = db()->prepare('INSERT INTO ai_logs (user_id, action_type, prompt, response, tokens_used, duration_ms) VALUES (:user_id, :action_type, :prompt, :response, :tokens_used, :duration_ms)');
    $stmt->execute([
        ':user_id' => $userId,
        ':action_type' => $actionType,
        ':prompt' => $prompt,
        ':response' => $response,
        ':tokens_used' => $tokens,
        ':duration_ms' => $durationMs,
    ]);
}

function build_report_stats(int $days): array
{
    $periodExpr = sprintf("date('now','-%d day')", $days);
    $total = fetch_one("SELECT COUNT(*) AS total FROM bugs WHERE date(created_at) >= {$periodExpr}");
    $closed = fetch_one("SELECT COUNT(*) AS total FROM bugs WHERE status='closed' AND date(COALESCE(closed_at, updated_at, created_at)) >= {$periodExpr}");
    $critical = fetch_one("SELECT COUNT(*) AS total FROM bugs WHERE severity IN ('blocker','critical') AND date(created_at) >= {$periodExpr}");
    $avgClose = fetch_one("SELECT AVG(julianday(COALESCE(closed_at, updated_at)) - julianday(created_at)) AS avg_days FROM bugs WHERE status='closed' AND created_at IS NOT NULL AND date(COALESCE(closed_at, updated_at, created_at)) >= {$periodExpr}");
    $topModules = fetch_all("SELECT p.name, COUNT(*) AS total FROM bugs b JOIN projects p ON p.id=b.project_id WHERE date(b.created_at) >= {$periodExpr} GROUP BY p.id ORDER BY total DESC LIMIT 5");

    return [
        'days' => $days,
        'new_bugs' => (int)($total['total'] ?? 0),
        'closed_bugs' => (int)($closed['total'] ?? 0),
        'critical_bugs' => (int)($critical['total'] ?? 0),
        'avg_close_days' => round((float)($avgClose['avg_days'] ?? 0), 2),
        'top_modules' => $topModules,
    ];
}
