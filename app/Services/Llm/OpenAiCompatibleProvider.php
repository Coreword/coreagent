<?php

namespace App\Services\Llm;

use Illuminate\Support\Facades\Http;
use RuntimeException;

// Shared by 'openai' and 'deepseek' — DeepSeek's API is wire-compatible with
// OpenAI's chat completions endpoint (same request/response shape, including
// tool_calls), so both go through this one adapter with different config.
class OpenAiCompatibleProvider implements LlmProviderInterface
{
    public function __construct(protected string $providerKey, protected array $config)
    {
    }

    public function key(): string
    {
        return $this->providerKey;
    }

    public function isConfigured(): bool
    {
        return filled($this->config['api_key'] ?? null);
    }

    public function chat(array $messages, array $tools = []): array
    {
        if (! $this->isConfigured()) {
            throw new ProviderNotConfiguredException("Provider [{$this->providerKey}] has no api_key configured.");
        }

        $payload = [
            'model' => $this->config['model'],
            'messages' => array_map([$this, 'toWireMessage'], $messages),
        ];

        if (! empty($tools)) {
            $payload['tools'] = array_map(fn (array $tool) => [
                'type' => 'function',
                'function' => [
                    'name' => $tool['name'],
                    'description' => $tool['description'],
                    'parameters' => $tool['parameters'],
                ],
            ], $tools);
        }

        $response = Http::withToken($this->config['api_key'])
            ->baseUrl($this->config['base_url'])
            ->post('/chat/completions', $payload);

        if ($response->failed()) {
            throw new RuntimeException("[{$this->providerKey}] chat completion failed: ".$response->body());
        }

        $message = $response->json('choices.0.message', []);

        return [
            'content' => $message['content'] ?? null,
            'tool_calls' => $this->fromWireToolCalls($message['tool_calls'] ?? []),
            'raw' => $response->json(),
        ];
    }

    protected function toWireMessage(array $message): array
    {
        if ($message['role'] === 'tool') {
            return [
                'role' => 'tool',
                'tool_call_id' => $message['tool_call_id'],
                'content' => $message['content'] ?? '',
            ];
        }

        if ($message['role'] === 'assistant' && ! empty($message['tool_calls'])) {
            return [
                'role' => 'assistant',
                'content' => $message['content'],
                'tool_calls' => array_map(fn (array $call) => [
                    'id' => $call['id'],
                    'type' => 'function',
                    'function' => [
                        'name' => $call['name'],
                        'arguments' => json_encode($call['arguments'], JSON_UNESCAPED_UNICODE),
                    ],
                ], $message['tool_calls']),
            ];
        }

        return [
            'role' => $message['role'],
            'content' => $message['content'] ?? '',
        ];
    }

    protected function fromWireToolCalls(array $wireToolCalls): array
    {
        return array_map(fn (array $call) => [
            'id' => $call['id'],
            'name' => $call['function']['name'],
            'arguments' => json_decode($call['function']['arguments'] ?? '{}', true) ?: [],
        ], $wireToolCalls);
    }
}
