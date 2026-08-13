<?php

namespace App\Services\Llm;

use Illuminate\Support\Facades\Log;
use Throwable;

class ProviderRouter
{
    /**
     * @param  array<string, LlmProviderInterface>  $providers  keyed by provider key; see
     *                                                          AppServiceProvider for the default openai/deepseek/claude/qwen_lora wiring.
     *                                                          Injectable so tests can substitute fakes without hitting real APIs.
     */
    public function __construct(protected array $providers) {}

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
        $skipped = [];
        $failed = [];

        foreach ($candidates as $key) {
            $provider = $this->providers[$key] ?? null;

            if (! $provider || ! $provider->isConfigured()) {
                $skipped[] = $key;

                continue;
            }

            try {
                $result = $provider->chat($messages, $tools);

                return $result + ['provider_used' => $provider->key()];
            } catch (Throwable $e) {
                // Without this, a provider failure is invisible until every
                // candidate has failed and the user sees one generic message —
                // exactly the gap that made a qwen_lora/Ollama timeout look
                // identical to "nothing configured" while debugging deploy.
                Log::warning("[ProviderRouter] {$key} failed: {$e->getMessage()}");
                $failed[] = $key;
                $lastException = $e;

                continue;
            }
        }

        throw new AllProvidersFailedException(
            $this->failureMessage($forceProvider, $skipped, $failed),
            previous: $lastException
        );
    }

    /**
     * Names which providers were skipped and which actually failed, instead of
     * a fixed sentence listing three provider names. The old wording went stale
     * the moment qwen_lora was added, and it read as "nothing is configured"
     * even when a configured provider had simply timed out.
     *
     * @param  array<int, string>  $skipped  present in the priority list, not configured
     * @param  array<int, string>  $failed  configured, but the request threw
     */
    protected function failureMessage(?string $forceProvider, array $skipped, array $failed): string
    {
        if ($forceProvider) {
            return in_array($forceProvider, $failed, true)
                ? "指定嘅 provider [{$forceProvider}] 請求失敗。"
                : "指定嘅 provider [{$forceProvider}] 未配置（缺 api_key 或 base_url）。";
        }

        if ($failed === []) {
            return $skipped === []
                ? 'providers.chat_priority 係空嘅，冇任何 provider 可以試。'
                : '冇任何 LLM provider 配置好：'.implode('、', $skipped).'。檢查 .env 入面對應嘅 API key，qwen_lora 就檢查 QWEN_LORA_BASE_URL。';
        }

        $message = '全部已配置嘅 provider 都失敗：'.implode('、', $failed).'。';

        if ($skipped !== []) {
            $message .= '（'.implode('、', $skipped).' 未配置，已跳過。）';
        }

        return $message.'詳細錯誤睇 laravel.log。';
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
