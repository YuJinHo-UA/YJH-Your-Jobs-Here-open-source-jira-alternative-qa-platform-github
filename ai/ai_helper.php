<?php
declare(strict_types=1);

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
        if ($useCache) {
            $cached = $this->getFromCache($key);
            if ($cached !== null) {
                return ['text' => $cached, 'model' => $this->model, 'tokens_used' => null, 'cached' => true];
            }
        }

        $result = $this->providerRequest($prompt, $systemMessage, $actionType);

        if ($useCache && !empty($result['text'])) {
            $this->saveToCache($key, $prompt, $result['text'], (int)($result['tokens_used'] ?? 0));
        }

        return $result + ['cached' => false];
    }

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
    private function callOpenAI(string $prompt, string $systemMessage): array
    {
        $payload = [
            'model' => $this->model,
            'temperature' => 0.2,
            'messages' => [
                ['role' => 'system', 'content' => $systemMessage ?: 'You are a helpful assistant.'],
                ['role' => 'user', 'content' => $prompt],
            ],
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Failed to encode payload');
        }

        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL extension is required');
        }

        $ch = curl_init($this->openaiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey
            ],
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_TIMEOUT => $this->timeout,
        ]);

        $raw = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $httpCode >= 400) {
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
    }

    private function saveToCache(string $hash, string $prompt, string $response, int $tokensUsed): void
    {
        // здесь можно добавить сохранение в БД
    }
}