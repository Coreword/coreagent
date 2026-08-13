<?php

namespace Tests\Unit;

use App\Services\Llm\AllProvidersFailedException;
use App\Services\Llm\LlmProviderInterface;
use App\Services\Llm\ProviderRouter;
use RuntimeException;
use Tests\TestCase;

class ProviderRouterTest extends TestCase
{
    public function test_it_falls_back_to_the_next_configured_provider_on_failure(): void
    {
        config()->set('providers.chat_priority', ['flaky', 'unconfigured', 'reliable']);

        $flaky = $this->fakeProvider('flaky', configured: true, throws: true);
        $unconfigured = $this->fakeProvider('unconfigured', configured: false);
        $reliable = $this->fakeProvider('reliable', configured: true);

        $router = new ProviderRouter([
            'flaky' => $flaky,
            'unconfigured' => $unconfigured,
            'reliable' => $reliable,
        ]);

        $result = $router->chat([['role' => 'user', 'content' => 'hi']]);

        $this->assertSame('reliable', $result['provider_used']);
        $this->assertSame('reply from reliable', $result['content']);
    }

    public function test_it_throws_when_no_provider_is_configured_or_all_fail(): void
    {
        $router = new ProviderRouter([
            'flaky' => $this->fakeProvider('flaky', configured: true, throws: true),
        ]);
        config()->set('providers.chat_priority', ['flaky']);

        $this->expectException(AllProvidersFailedException::class);

        $router->chat([['role' => 'user', 'content' => 'hi']]);
    }

    public function test_a_forced_provider_skips_the_priority_list(): void
    {
        $reliable = $this->fakeProvider('reliable', configured: true);
        $router = new ProviderRouter(['reliable' => $reliable]);
        config()->set('providers.chat_priority', []); // deliberately empty — forceProvider must bypass it

        $result = $router->chat([['role' => 'user', 'content' => 'hi']], forceProvider: 'reliable');

        $this->assertSame('reliable', $result['provider_used']);
    }

    public function test_the_failure_message_names_which_providers_were_skipped(): void
    {
        config()->set('providers.chat_priority', ['openai', 'qwen_lora']);

        $router = new ProviderRouter([
            'openai' => $this->fakeProvider('openai', configured: false),
            'qwen_lora' => $this->fakeProvider('qwen_lora', configured: false),
        ]);

        try {
            $router->chat([['role' => 'user', 'content' => 'hi']]);
            $this->fail('Expected the router to give up.');
        } catch (AllProvidersFailedException $e) {
            $this->assertStringContainsString('openai', $e->getMessage());
            $this->assertStringContainsString('qwen_lora', $e->getMessage());
            $this->assertStringContainsString('QWEN_LORA_BASE_URL', $e->getMessage());
        }
    }

    public function test_a_configured_provider_that_failed_is_not_reported_as_unconfigured(): void
    {
        // The old fixed wording said "no API key" whatever happened, which made
        // a qwen_lora timeout read as "nothing is set up" while debugging.
        config()->set('providers.chat_priority', ['openai', 'qwen_lora']);

        $router = new ProviderRouter([
            'openai' => $this->fakeProvider('openai', configured: true, throws: true),
            'qwen_lora' => $this->fakeProvider('qwen_lora', configured: false),
        ]);

        try {
            $router->chat([['role' => 'user', 'content' => 'hi']]);
            $this->fail('Expected the router to give up.');
        } catch (AllProvidersFailedException $e) {
            $this->assertStringContainsString('openai', $e->getMessage());
            $this->assertStringContainsString('laravel.log', $e->getMessage());
            $this->assertStringNotContainsString('冇任何 LLM provider 配置好', $e->getMessage());
        }
    }

    public function test_a_forced_provider_distinguishes_unconfigured_from_failed(): void
    {
        $router = new ProviderRouter([
            'qwen_lora' => $this->fakeProvider('qwen_lora', configured: false),
            'openai' => $this->fakeProvider('openai', configured: true, throws: true),
        ]);
        config()->set('providers.chat_priority', []);

        try {
            $router->chat([['role' => 'user', 'content' => 'hi']], forceProvider: 'qwen_lora');
            $this->fail('Expected the router to give up.');
        } catch (AllProvidersFailedException $e) {
            $this->assertStringContainsString('未配置', $e->getMessage());
        }

        try {
            $router->chat([['role' => 'user', 'content' => 'hi']], forceProvider: 'openai');
            $this->fail('Expected the router to give up.');
        } catch (AllProvidersFailedException $e) {
            $this->assertStringContainsString('請求失敗', $e->getMessage());
        }
    }

    protected function fakeProvider(string $key, bool $configured, bool $throws = false): LlmProviderInterface
    {
        return new class($key, $configured, $throws) implements LlmProviderInterface
        {
            public function __construct(
                protected string $key,
                protected bool $configured,
                protected bool $throws,
            ) {}

            public function key(): string
            {
                return $this->key;
            }

            public function isConfigured(): bool
            {
                return $this->configured;
            }

            public function chat(array $messages, array $tools = []): array
            {
                if ($this->throws) {
                    throw new RuntimeException("{$this->key} failed");
                }

                return ['content' => "reply from {$this->key}", 'tool_calls' => [], 'raw' => []];
            }
        };
    }
}
