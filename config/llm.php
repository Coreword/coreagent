<?php

return [
    'provider' => env('LLM_PROVIDER', 'openai'),

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'chat_model' => env('OPENAI_CHAT_MODEL', 'gpt-4o-mini'),
        'embedding_model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
    ],

    // Classify/Extract/Summarise jobs check this before calling the LLM API.
    // Without a key, those jobs record an audit_log entry and mark the case
    // 'awaiting_api_key' instead of failing the queue worker.
    'enabled' => fn () => filled(config('llm.openai.api_key')),
];
