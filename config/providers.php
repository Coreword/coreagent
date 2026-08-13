<?php

return [
    // Order ProviderRouter tries providers in. Unconfigured (no api_key) providers
    // are skipped; a provider that throws mid-request falls through to the next one.
    // qwen_lora sits last on purpose: it is the cheapest to run but the weakest,
    // so it should catch requests the hosted APIs could not serve, not pre-empt them.
    'chat_priority' => array_filter(array_map('trim', explode(',', env('CHAT_PROVIDER_PRIORITY', 'openai,deepseek,claude,qwen_gpu,qwen_lora')))),

    'openai' => [
        'driver' => 'openai_compatible',
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'model' => env('OPENAI_CHAT_MODEL', 'gpt-4o-mini'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 30),
        'supports_tools' => true,
    ],

    // DeepSeek's API is OpenAI-compatible (same request/response shape, including
    // tool_calls), so it reuses the OpenAiCompatibleProvider with a different
    // base_url/model.
    'deepseek' => [
        'driver' => 'openai_compatible',
        'api_key' => env('DEEPSEEK_API_KEY'),
        'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'),
        'model' => env('DEEPSEEK_CHAT_MODEL', 'deepseek-chat'),
        'timeout' => (int) env('DEEPSEEK_TIMEOUT', 30),
        'supports_tools' => true,
    ],

    'claude' => [
        'driver' => 'anthropic',
        'api_key' => env('ANTHROPIC_API_KEY'),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
        'model' => env('ANTHROPIC_CHAT_MODEL', 'claude-sonnet-4-5-20250929'),
        'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
        'max_tokens' => (int) env('ANTHROPIC_MAX_TOKENS', 4096),
        'timeout' => (int) env('ANTHROPIC_TIMEOUT', 60),
        'supports_tools' => true,
    ],

    // The same self-hosted Qwen, reached over an SSH reverse tunnel to a machine
    // with a GPU. Identical model, different hardware: measured 5.3 tok/s on the
    // server's CPU against roughly a second per reply on an RTX 4050.
    //
    // Set up from the GPU machine, not the server:
    //     ssh -N -R 127.0.0.1:11435:127.0.0.1:11434 <server>
    //
    // The remote end binds to loopback, so the tunnel exposes nothing publicly
    // and needs no authentication layer in front of it — which is why this does
    // not need Cloudflare or any other gateway.
    //
    // When the tunnel is down the port simply refuses the connection, which
    // throws immediately and costs the router nothing before it falls through to
    // qwen_lora on the server's own CPU. That is the intended steady state, not
    // an error case: the laptop is not expected to be up all the time.
    'qwen_gpu' => [
        'driver' => 'openai_compatible',
        'api_key' => env('QWEN_GPU_API_KEY', 'ollama-tunnel'),
        'base_url' => env('QWEN_GPU_BASE_URL'),
        'model' => env('QWEN_GPU_MODEL', 'coreagent-qwen'),

        // Shorter than the CPU provider deliberately. A GPU that is genuinely
        // there answers fast; a long wait here means the tunnel is half-open,
        // and the useful response to that is to fall through quickly.
        'timeout' => (int) env('QWEN_GPU_TIMEOUT', 60),

        'supports_tools' => (bool) env('QWEN_GPU_SUPPORTS_TOOLS', false),
        'headers' => [],
    ],
    // Self-hosted Qwen through Ollama's OpenAI-compatible endpoint.
    //
    // base_url is what decides whether this provider exists at all: empty means
    // unconfigured and ProviderRouter skips it, exactly like a missing api_key
    // does for the hosted providers. Point it at http://127.0.0.1:11434/v1 for
    // a local Ollama, or at the public hostname of a tunnel once one is running.
    //
    // Ollama itself does no authentication. When this is reached over a tunnel
    // the token below is only meaningful if something in front of Ollama checks
    // it — Cloudflare Access, an nginx auth_request, a Tailscale ACL. Exposing
    // a bare Ollama to the internet hands anyone who finds the URL free use of
    // the GPU, so treat base_url pointing at a public host as a commitment to
    // put a checker in front of it.
    'qwen_lora' => [
        'driver' => 'openai_compatible',
        'api_key' => env('QWEN_LORA_API_KEY', 'ollama-local'),
        'base_url' => env('QWEN_LORA_BASE_URL'),
        'model' => env('QWEN_LORA_MODEL', 'qwen2.5:3b'),

        // A cold Ollama has to page the weights into VRAM before the first
        // token, which routinely outlasts the 30s the hosted APIs need.
        'timeout' => (int) env('QWEN_LORA_TIMEOUT', 120),

        // Off by default, but not because the calls come out malformed —
        // measured against qwen2.5:3b, the emitted list_cases call was
        // structurally valid. The problem is what comes with it: the model
        // narrates its tool reasoning into the answer and drifts from
        // Cantonese into Mandarin and simplified characters mid-reply, so the
        // user sees the plumbing. Turn on per model tag after testing; a 7B+
        // holds the instruction far better.
        'supports_tools' => (bool) env('QWEN_LORA_SUPPORTS_TOOLS', false),

        // Extra headers for whatever fronts the tunnel. Cloudflare Access wants
        // CF-Access-Client-Id / CF-Access-Client-Secret, which do not fit the
        // Authorization header the api_key uses.
        'headers' => array_filter([
            'CF-Access-Client-Id' => env('QWEN_LORA_CF_ACCESS_CLIENT_ID'),
            'CF-Access-Client-Secret' => env('QWEN_LORA_CF_ACCESS_CLIENT_SECRET'),
        ]),
    ],
];
