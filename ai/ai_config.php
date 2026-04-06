<?php
declare(strict_types=1);

return [
    'provider' => getenv('YJH_AI_PROVIDER') ?: 'openai',  // используем OpenAI
    'model' => getenv('YJH_AI_MODEL') ?: 'gpt-4o-mini',
    'api_key' => getenv('OPENAI_API_KEY') ?: '',           // твой ключ
    'openai_url' => getenv('YJH_OPENAI_URL') ?: 'https://api.openai.com/v1/chat/completions',
    'cache_ttl' => (int)(getenv('YJH_AI_CACHE_TTL') ?: 86400),
    'request_timeout' => (int)(getenv('YJH_AI_TIMEOUT') ?: 25),
];