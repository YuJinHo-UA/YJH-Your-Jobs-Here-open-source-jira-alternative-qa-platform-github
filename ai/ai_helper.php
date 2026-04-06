<?php
declare(strict_types=1);

<<<<<<< HEAD
=======
require_once __DIR__ . '/../includes/functions.php';

>>>>>>> 7e7a5ae49ac6caacc4b2a0ad95dd06bd60dfa616
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

<<<<<<< HEAD
    /**
     * Простой чат с AI
     */
    public function chat(string $message, string $page = ''): array
    {
        $pageContext = trim($page) !== '' ? ("\nCurrent page: " . $page) : '';
        $prompt = trim($message . $pageContext);
        return $this->generate('chat', $prompt, 'You are an AI QA assistant. Answer practically and concisely.');
    }

    /**
     * Генерация через провайдера
     */
    public function generate(string $actionType, string $prompt, string $systemMessage = '', bool $useCache = true): array
    {
        $key = hash('sha256', implode('|', [$this->provider, $this->model, $actionType, $systemMessage, $prompt]));

        // Попытка взять из кеша (если есть)
=======
    public function generate(string $actionType, string $prompt, string $systemMessage = '', bool $useCache = true): array
    {
        $key = hash('sha256', implode('|', [$this->provider, $this->model, $actionType, $systemMessage, $prompt]));
>>>>>>> 7e7a5ae49ac6caacc4b2a0ad95dd06bd60dfa616
        if ($useCache) {
            $cached = $this->getFromCache($key);
            if ($cached !== null) {
                return ['text' => $cached, 'model' => $this->model, 'tokens_used' => null, 'cached' => true];
            }
        }

        $result = $this->providerRequest($prompt, $systemMessage, $actionType);
<<<<<<< HEAD

        if ($useCache && !empty($result['text'])) {
=======
        if ($useCache && $result['text'] !== '') {
>>>>>>> 7e7a5ae49ac6caacc4b2a0ad95dd06bd60dfa616
            $this->saveToCache($key, $prompt, $result['text'], (int)($result['tokens_used'] ?? 0));
        }

        return $result + ['cached' => false];
    }

<<<<<<< HEAD
    /**
     * Запрос к провайдеру
     */
    private function providerRequest(string $prompt, string $systemMessage, string $actionType): array
    {
        if ($this->provider !== 'openai') {
            return $this->mockResponse($actionType, 'AI provider is set to mock/fallback');
        }

        if (empty($this->apiKey)) {
            return $this->mockResponse($actionType, 'OPENAI_API_KEY is missing');
        }

        try {
            $result = $this->callOpenAI($prompt, $systemMessage);
            if (!empty($result['error'])) {
                return $this->mockResponse($actionType, (string)$result['error']);
            }
            if (trim((string)($result['text'] ?? '')) === '') {
                return $this->mockResponse($actionType, 'OpenAI returned empty response');
            }
            return $result;
        } catch (Throwable $e) {
            return $this->mockResponse($actionType, $e->getMessage());
        }
    }

    /**
     * Реальный вызов OpenAI
     */
=======
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
                return $this->mockResponse($actionType, $prompt, $e->getMessage());
            }
        }

        return $this->mockResponse($actionType, $prompt, null);
    }

>>>>>>> 7e7a5ae49ac6caacc4b2a0ad95dd06bd60dfa616
    private function callOpenAI(string $prompt, string $systemMessage): array
    {
        $payload = [
            'model' => $this->model,
            'temperature' => 0.2,
<<<<<<< HEAD
            'messages' => [
                ['role' => 'system', 'content' => $systemMessage ?: 'You are a helpful assistant.'],
=======
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => $systemMessage !== '' ? $systemMessage : 'You are helpful assistant.'],
>>>>>>> 7e7a5ae49ac6caacc4b2a0ad95dd06bd60dfa616
                ['role' => 'user', 'content' => $prompt],
            ],
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
<<<<<<< HEAD
            throw new RuntimeException('Failed to encode payload');
        }

=======
            throw new RuntimeException('Failed to encode request payload');
        }
>>>>>>> 7e7a5ae49ac6caacc4b2a0ad95dd06bd60dfa616
        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL extension is required');
        }

        $ch = curl_init($this->openaiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
<<<<<<< HEAD
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey
            ],
=======
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $this->apiKey],
>>>>>>> 7e7a5ae49ac6caacc4b2a0ad95dd06bd60dfa616
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_TIMEOUT => $this->timeout,
        ]);

        $raw = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $httpCode >= 400) {
<<<<<<< HEAD
            error_log("OpenAI request failed: HTTP $httpCode, curl_error: $err, response: $raw");
            return ['text' => '', 'model' => $this->model, 'tokens_used' => 0, 'error' => $err ?: "HTTP $httpCode"];
        }

        $decoded = json_decode((string)$raw, true);
        $text = '';

        if (is_array($decoded) && isset($decoded['choices'][0]['message']['content'])) {
            $text = trim((string)$decoded['choices'][0]['message']['content']);
        } else {
            error_log("OpenAI returned invalid JSON: " . $raw);
            $text = 'AI response invalid or empty';
        }

        return ['text' => $text, 'model' => (string)($decoded['model'] ?? $this->model), 'tokens_used' => (int)($decoded['usage']['total_tokens'] ?? 0)];
    }

    /**
     * Мок-ответ если что-то сломалось
     */
    private function mockResponse(string $actionType, ?string $error): array
    {
        return [
            'text' => json_encode([
                'note' => 'mock mode',
                'reason' => $error ?: 'fallback mode',
                'action' => $actionType,
            ], JSON_UNESCAPED_UNICODE),
            'model' => $this->provider === 'mock' ? 'mock' : $this->model,
            'tokens_used' => 0,
        ];
    }

    /**
     * Заглушка кеша (можно подключить SQLite/MySQL)
     */
    private function getFromCache(string $hash): ?string
    {
        return null; // пока без кеша
=======
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
>>>>>>> 7e7a5ae49ac6caacc4b2a0ad95dd06bd60dfa616
    }

    private function saveToCache(string $hash, string $prompt, string $response, int $tokensUsed): void
    {
<<<<<<< HEAD
        // здесь можно добавить сохранение в БД
    }
}
=======
        $stmt = db()->prepare(
            'INSERT INTO ai_cache (prompt_hash, prompt, response, model, tokens_used, created_at)
             VALUES (:prompt_hash, :prompt, :response, :model, :tokens_used, CURRENT_TIMESTAMP)
             ON CONFLICT(prompt_hash)
             DO UPDATE SET response = excluded.response, model = excluded.model, tokens_used = excluded.tokens_used, created_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([':prompt_hash' => $hash, ':prompt' => $prompt, ':response' => $response, ':model' => $this->model, ':tokens_used' => $tokensUsed]);
    }

    private function mockResponse(string $actionType, string $prompt, ?string $error): array
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
                'answer' => 'AI is running in mock mode. Set OPENAI_API_KEY and YJH_AI_PROVIDER=openai for real responses. Your message: ' . mb_substr(trim($prompt), 0, 220),
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
>>>>>>> 7e7a5ae49ac6caacc4b2a0ad95dd06bd60dfa616
