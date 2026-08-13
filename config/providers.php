<?php

return [
    // Order ProviderRouter tries providers in. Unconfigured (no api_key) providers
    // are skipped; a provider that throws mid-request falls through to the next one.
    'chat_priority' => array_filter(array_map('trim', explode(',', env('CHAT_PROVIDER_PRIORITY', 'openai,deepseek,claude')))),

    'openai' => [
        'driver' => 'openai_compatible',
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'model' => env('OPENAI_CHAT_MODEL', 'gpt-4o-mini'),
    ],

    // DeepSeek's API is OpenAI-compatible (same request/response shape, including
    // tool_calls), so it reuses the OpenAiCompatibleProvider with a different
    // base_url/model.
    'deepseek' => [
        'driver' => 'openai_compatible',
        'api_key' => env('DEEPSEEK_API_KEY'),
        'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'),
        'model' => env('DEEPSEEK_CHAT_MODEL', 'deepseek-chat'),
    ],

    'claude' => [
        'driver' => 'anthropic',
        'api_key' => env('ANTHROPIC_API_KEY'),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
        'model' => env('ANTHROPIC_CHAT_MODEL', 'claude-sonnet-4-5-20250929'),
        'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
        'max_tokens' => (int) env('ANTHROPIC_MAX_TOKENS', 4096),
    ],

    // Pluggable slot for a future self-hosted Qwen + LoRA deployment. Not
    // implemented yet — see document-copilot-implementation_1.md section 6 for
    // the conditions that would make self-hosting worth the investment.
    'qwen_lora' => [
        'driver' => 'qwen_lora',
        'base_url' => env('QWEN_LORA_BASE_URL'),
        'model' => env('QWEN_LORA_MODEL'),
    ],
];
