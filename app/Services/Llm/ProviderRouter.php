<?php

namespace App\Services\Llm;

use Throwable;

class ProviderRouter
{
    /**
     * @param  array<string, LlmProviderInterface>  $providers  keyed by provider key; see
     *     AppServiceProvider for the default openai/deepseek/claude/qwen_lora wiring.
     *     Injectable so tests can substitute fakes without hitting real APIs.
     */
    public function __construct(protected array $providers)
    {
    }

    /**
     * @param  array  $messages  see LlmProviderInterface::chat()
     * @param  array  $tools  see LlmProviderInterface::chat()
     * @param  ?string  $forceProvider  pin to one provider (conversation-level override); skips fallback
     * @return array{content: ?string, tool_calls: array, raw: array, provider_used: string}
     */
    public function chat(array $messages, array $tools = [], ?string $forceProvider = null): array
    {
        $candidates = $forceProvider
            ? [$forceProvider]
            : config('providers.chat_priority', []);

        $lastException = null;

        foreach ($candidates as $key) {
            $provider = $this->providers[$key] ?? null;

            if (! $provider || ! $provider->isConfigured()) {
                continue;
            }

            try {
                $result = $provider->chat($messages, $tools);

                return $result + ['provider_used' => $provider->key()];
            } catch (Throwable $e) {
                $lastException = $e;

                continue;
            }
        }

        throw new AllProvidersFailedException(
            $forceProvider
                ? "Provider [{$forceProvider}] is not configured or failed."
                : '未設定任何 LLM provider（OpenAI/DeepSeek/Claude 都未有 API key），或者全部 provider 都請求失敗。',
            previous: $lastException
        );
    }

    public function availableProviders(): array
    {
        return collect($this->providers)
            ->filter(fn (LlmProviderInterface $p) => $p->isConfigured())
            ->keys()
            ->values()
            ->all();
    }
}
