<?php

namespace App\Services\Llm;

// Pluggable slot for a future self-hosted Qwen + LoRA deployment. Deliberately
// not implemented — see document-copilot-implementation_1.md section 6 for the
// conditions (API spend, data volume, data residency requirement) that would
// justify building this out. isConfigured() is always false so ProviderRouter
// skips it until a base_url is actually deployed and wired in.
class QwenLoraProvider implements LlmProviderInterface
{
    public function __construct(protected array $config)
    {
    }

    public function key(): string
    {
        return 'qwen_lora';
    }

    public function isConfigured(): bool
    {
        return filled($this->config['base_url'] ?? null);
    }

    public function chat(array $messages, array $tools = []): array
    {
        throw new ProviderNotConfiguredException(
            'qwen_lora is a pluggable slot for a future self-hosted deployment — not implemented yet.'
        );
    }
}
