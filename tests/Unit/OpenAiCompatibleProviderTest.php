<?php

namespace Tests\Unit;

use App\Services\Llm\OpenAiCompatibleProvider;
use App\Services\Llm\ProviderNotConfiguredException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Covers the parts of the adapter that only matter once a self-hosted provider
 * shares it with the hosted ones: what counts as configured, whose timeout
 * applies, and whether a model that cannot call tools is sent any.
 */
class OpenAiCompatibleProviderTest extends TestCase
{
    public function test_a_provider_needs_both_an_api_key_and_a_base_url(): void
    {
        $this->assertTrue($this->provider()->isConfigured());

        $this->assertFalse(
            $this->provider(['api_key' => ''])->isConfigured(),
            'A base_url with no credential is not configured.'
        );

        // The case that matters for qwen_lora: config/providers.php supplies a
        // placeholder api_key, so api_key alone would otherwise make an
        // undeployed Ollama look ready and put it in the model dropdown.
        $this->assertFalse(
            $this->provider(['base_url' => ''])->isConfigured(),
            'A credential with nowhere to send it is not configured.'
        );
    }

    public function test_chat_refuses_to_run_when_half_configured(): void
    {
        Http::fake();

        $this->expectException(ProviderNotConfiguredException::class);
        $this->expectExceptionMessageMatches('/api_key and base_url/');

        $this->provider(['base_url' => ''])->chat([['role' => 'user', 'content' => 'hi']]);
    }

    public function test_the_timeout_comes_from_config_so_each_provider_owns_its_budget(): void
    {
        $this->assertSame(120, $this->provider(['timeout' => 120])->timeout());
        $this->assertSame(30, $this->provider()->timeout(), 'Absent config falls back to the hosted-API default.');
    }

    public function test_tools_are_withheld_from_a_model_that_cannot_call_them(): void
    {
        Http::fake(['*' => Http::response($this->wireReply())]);

        $this->provider(['supports_tools' => false])->chat(
            [['role' => 'user', 'content' => 'hi']],
            $this->toolSchema()
        );

        Http::assertSent(fn ($request) => ! array_key_exists('tools', $request->data()));
    }

    public function test_tools_are_forwarded_when_the_model_supports_them(): void
    {
        Http::fake(['*' => Http::response($this->wireReply())]);

        $this->provider()->chat(
            [['role' => 'user', 'content' => 'hi']],
            $this->toolSchema()
        );

        Http::assertSent(function ($request) {
            $tools = $request->data()['tools'] ?? [];

            return count($tools) === 1
                && $tools[0]['type'] === 'function'
                && $tools[0]['function']['name'] === 'search_documents';
        });
    }

    public function test_extra_headers_are_sent_for_whatever_fronts_a_tunnel(): void
    {
        Http::fake(['*' => Http::response($this->wireReply())]);

        $this->provider(['headers' => ['CF-Access-Client-Id' => 'client-id-123']])
            ->chat([['role' => 'user', 'content' => 'hi']]);

        Http::assertSent(function ($request) {
            return $request->hasHeader('CF-Access-Client-Id', 'client-id-123')
                && $request->hasHeader('Authorization', 'Bearer test-key');
        });
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function provider(array $overrides = []): OpenAiCompatibleProvider
    {
        return new OpenAiCompatibleProvider('qwen_lora', $overrides + [
            'api_key' => 'test-key',
            'base_url' => 'http://127.0.0.1:11434/v1',
            'model' => 'qwen2.5:3b',
        ]);
    }

    /**
     * @return array<int, array{name: string, description: string, parameters: array}>
     */
    protected function toolSchema(): array
    {
        return [[
            'name' => 'search_documents',
            'description' => 'Search the case documents.',
            'parameters' => ['type' => 'object', 'properties' => []],
        ]];
    }

    /**
     * @return array<string, mixed>
     */
    protected function wireReply(): array
    {
        return ['choices' => [['message' => ['content' => 'hello']]]];
    }
}
