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

    protected function fakeProvider(string $key, bool $configured, bool $throws = false): LlmProviderInterface
    {
        return new class($key, $configured, $throws) implements LlmProviderInterface
        {
            public function __construct(
                protected string $key,
                protected bool $configured,
                protected bool $throws,
            ) {
            }

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
