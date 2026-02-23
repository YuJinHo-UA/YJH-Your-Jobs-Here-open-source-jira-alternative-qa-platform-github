<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

final class AIHelper
{
    private string $provider;
    private string $model;
    private string $apiKey;
    private string $openaiUrl;
    private int $cacheTtl;
    private int $timeout;

    public function __construct()
    {
        $config = include __DIR__ . '/ai_config.php';
        $this->provider = (string)($config['provider'] ?? 'mock');
        $this->model = (string)($config['model'] ?? 'gpt-4o-mini');
        $this->apiKey = (string)($config['api_key'] ?? '');
        $this->openaiUrl = (string)($config['openai_url'] ?? 'https://api.openai.com/v1/chat/completions');
        $this->cacheTtl = max(60, (int)($config['cache_ttl'] ?? 86400));
        $this->timeout = max(5, (int)($config['request_timeout'] ?? 25));
    }

    public function generate(string $actionType, string $prompt, string $systemMessage = '', bool $useCache = true): array
    {
        $key = hash('sha256', implode('|', [$this->provider, $this->model, $actionType, $systemMessage, $prompt]));
        if ($useCache) {
            $cached = $this->getFromCache($key);
            if ($cached !== null) {
                return ['text' => $cached, 'model' => $this->model, 'tokens_used' => null, 'cached' => true];
            }
        }

        $result = $this->providerRequest($prompt, $systemMessage, $actionType);
        if ($useCache && $result['text'] !== '') {
            $this->saveToCache($key, $prompt, $result['text'], (int)($result['tokens_used'] ?? 0));
        }

        return $result + ['cached' => false];
    }

    public function generateTestCases(string $featureDescription): array
    {
        $template = file_get_contents(__DIR__ . '/prompts/test_case.txt') ?: '';
        $prompt = str_replace('{{feature}}', $featureDescription, $template);
        return $this->generate('test_case', $prompt, 'You are a senior QA engineer. Return strict JSON only.');
    }

    public function analyzeBug(string $title, string $description): array
    {
        $template = file_get_contents(__DIR__ . '/prompts/bug_analysis.txt') ?: '';
        $prompt = str_replace(['{{title}}', '{{description}}'], [$title, $description], $template);
        return $this->generate('bug_analysis', $prompt, 'You are a QA bug triage assistant. Return strict JSON only.');
    }

    public function checkDuplicates(string $title, string $description, array $bugs): array
    {
        $lines = [];
        foreach ($bugs as $bug) {
            $lines[] = sprintf('#%d | %s | %s', (int)$bug['id'], (string)$bug['title'], trim((string)($bug['description'] ?? '')));
        }

        $prompt = "New bug title: {$title}\nNew bug description: {$description}\n\nExisting bugs:\n" . implode("\n", $lines);
        $prompt .= "\n\nReturn strict JSON: {\"duplicates\":[{\"id\":123,\"similarity\":92,\"reason\":\"...\"}]}";
        return $this->generate('duplicate_check', $prompt, 'You are a semantic bug duplicate detector. Return strict JSON only.', false);
    }

    public function generateWeeklyReport(array $stats): array
    {
        $prompt = 'Write a concise QA weekly report in Ukrainian based on JSON stats: ' . json_encode($stats, JSON_UNESCAPED_UNICODE);
        $prompt .= '\nStructure: summary, risks, recommendations.';
        return $this->generate('report', $prompt, 'You are a QA analytics assistant.');
    }

    public function chat(string $message, string $page = ''): array
    {
        $context = $page !== '' ? ('Current page: ' . $page . "\n") : '';
        $prompt = $context . "User message:\n" . $message . "\n\nAnswer briefly and practically for QA workflow.";
        return $this->generate('chat', $prompt, 'You are an AI QA assistant for YJH.', false);
    }

    private function providerRequest(string $prompt, string $systemMessage, string $actionType): array
    {
        if ($this->provider === 'openai' && $this->apiKey !== '') {
            try {
                return $this->callOpenAI($prompt, $systemMessage);
            } catch (Throwable $e) {
                return $this->mockResponse($actionType, $e->getMessage());
            }
        }

        return $this->mockResponse($actionType, null);
    }

    private function callOpenAI(string $prompt, string $systemMessage): array
    {
        $payload = [
            'model' => $this->model,
            'temperature' => 0.2,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => $systemMessage !== '' ? $systemMessage : 'You are helpful assistant.'],
                ['role' => 'user', 'content' => $prompt],
            ],
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Failed to encode request payload');
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL extension is required');
        }

        $ch = curl_init($this->openaiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $this->apiKey],
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_TIMEOUT => $this->timeout,
        ]);

        $raw = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $httpCode >= 400) {
            throw new RuntimeException('OpenAI request failed: ' . ($err !== '' ? $err : 'HTTP ' . $httpCode));
        }

        $decoded = json_decode((string)$raw, true);
        $text = trim((string)($decoded['choices'][0]['message']['content'] ?? ''));
        return ['text' => $text, 'model' => (string)($decoded['model'] ?? $this->model), 'tokens_used' => (int)($decoded['usage']['total_tokens'] ?? 0)];
    }

    private function getFromCache(string $hash): ?string
    {
        $row = fetch_one('SELECT response, created_at FROM ai_cache WHERE prompt_hash = :hash', [':hash' => $hash]);
        if (!$row) {
            return null;
        }

        $createdAt = strtotime((string)($row['created_at'] ?? ''));
        if ($createdAt === false || (time() - $createdAt) > $this->cacheTtl) {
            return null;
        }

        return (string)($row['response'] ?? '');
    }

    private function saveToCache(string $hash, string $prompt, string $response, int $tokensUsed): void
    {
        $stmt = db()->prepare(
            'INSERT INTO ai_cache (prompt_hash, prompt, response, model, tokens_used, created_at)
             VALUES (:prompt_hash, :prompt, :response, :model, :tokens_used, CURRENT_TIMESTAMP)
             ON CONFLICT(prompt_hash)
             DO UPDATE SET response = excluded.response, model = excluded.model, tokens_used = excluded.tokens_used, created_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([':prompt_hash' => $hash, ':prompt' => $prompt, ':response' => $response, ':model' => $this->model, ':tokens_used' => $tokensUsed]);
    }

    private function mockResponse(string $actionType, ?string $error): array
    {
        $payload = match ($actionType) {
            'bug_analysis' => [
                'steps_to_reproduce' => ['Open related screen', 'Perform key action', 'Observe issue'],
                'expected_result' => ['Feature works without errors'],
                'actual_result' => ['Error appears or flow is blocked'],
                'analysis' => 'Possible cause: invalid auth validation.',
                'note' => $error ? ('fallback: ' . $error) : 'mock mode',
            ],
            'duplicate_check' => ['duplicates' => [], 'note' => $error ? ('fallback: ' . $error) : 'mock mode'],
            'report' => [
                'summary' => 'New defects increased in auth module.',
                'risks' => ['Critical issues in billing are accumulating.'],
                'recommendations' => ['Run focused regression for auth + billing.'],
                'note' => $error ? ('fallback: ' . $error) : 'mock mode',
            ],
            'chat' => [
                'answer' => 'Chat is active. Ask about test cases, bug analysis, duplicates, or weekly reports.',
                'note' => $error ? ('fallback: ' . $error) : 'mock mode',
            ],
            default => [
                'cases' => [[
                    'id' => 'TC-01',
                    'title' => 'Happy path scenario',
                    'preconditions' => ['Registered user exists'],
                    'steps' => ['Open form', 'Enter valid data', 'Submit'],
                    'expected' => ['Operation succeeds'],
                    'priority' => 'High',
                ]],
                'note' => $error ? ('fallback: ' . $error) : 'mock mode',
            ],
        };

        return ['text' => (string)json_encode($payload, JSON_UNESCAPED_UNICODE), 'model' => $this->provider === 'mock' ? 'mock' : $this->model, 'tokens_used' => 0];
    }
}
