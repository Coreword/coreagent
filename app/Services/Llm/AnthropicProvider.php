<?php

namespace App\Services\Llm;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class AnthropicProvider implements LlmProviderInterface
{
    public function __construct(protected array $config)
    {
    }

    public function key(): string
    {
        return 'claude';
    }

    public function isConfigured(): bool
    {
        return filled($this->config['api_key'] ?? null);
    }

    public function chat(array $messages, array $tools = []): array
    {
        if (! $this->isConfigured()) {
            throw new ProviderNotConfiguredException('Provider [claude] has no api_key configured.');
        }

        // Anthropic has no "system" role inside messages[] — it's a separate
        // top-level field — and no "tool" role, so both need translating.
        $systemText = collect($messages)
            ->where('role', 'system')
            ->pluck('content')
            ->implode("\n");

        $wireMessages = $this->toWireMessages(
            array_values(array_filter($messages, fn (array $m) => $m['role'] !== 'system'))
        );

        $payload = [
            'model' => $this->config['model'],
            'max_tokens' => $this->config['max_tokens'],
            'messages' => $wireMessages,
        ];

        if ($systemText !== '') {
            $payload['system'] = $systemText;
        }

        if (! empty($tools)) {
            $payload['tools'] = array_map(fn (array $tool) => [
                'name' => $tool['name'],
                'description' => $tool['description'],
                'input_schema' => $tool['parameters'],
            ], $tools);
        }

        $response = Http::withHeaders([
            'x-api-key' => $this->config['api_key'],
            'anthropic-version' => $this->config['version'],
        ])
            ->baseUrl($this->config['base_url'])
            ->post('/v1/messages', $payload);

        if ($response->failed()) {
            throw new RuntimeException('[claude] messages request failed: '.$response->body());
        }

        $blocks = $response->json('content', []);

        $content = collect($blocks)->where('type', 'text')->pluck('text')->implode('');
        $toolCalls = collect($blocks)->where('type', 'tool_use')->map(fn (array $block) => [
            'id' => $block['id'],
            'name' => $block['name'],
            'arguments' => $block['input'] ?? [],
        ])->values()->all();

        return [
            'content' => $content !== '' ? $content : null,
            'tool_calls' => $toolCalls,
            'raw' => $response->json(),
        ];
    }

    /**
     * Merge consecutive tool-result messages into a single "user" message with
     * multiple tool_result blocks, matching how Anthropic expects a tool-calling
     * turn to be represented.
     */
    protected function toWireMessages(array $messages): array
    {
        $wire = [];

        foreach ($messages as $message) {
            if ($message['role'] === 'tool') {
                $block = [
                    'type' => 'tool_result',
                    'tool_use_id' => $message['tool_call_id'],
                    'content' => $message['content'] ?? '',
                ];

                $last = end($wire);
                if ($last && $last['role'] === 'user' && $this->isToolResultOnly($last)) {
                    $wire[count($wire) - 1]['content'][] = $block;
                } else {
                    $wire[] = ['role' => 'user', 'content' => [$block]];
                }

                continue;
            }

            if ($message['role'] === 'assistant' && ! empty($message['tool_calls'])) {
                $blocks = [];
                if (filled($message['content'] ?? null)) {
                    $blocks[] = ['type' => 'text', 'text' => $message['content']];
                }
                foreach ($message['tool_calls'] as $call) {
                    $blocks[] = [
                        'type' => 'tool_use',
                        'id' => $call['id'],
                        'name' => $call['name'],
                        'input' => $call['arguments'],
                    ];
                }
                $wire[] = ['role' => 'assistant', 'content' => $blocks];

                continue;
            }

            $wire[] = [
                'role' => $message['role'],
                'content' => [['type' => 'text', 'text' => $message['content'] ?? '']],
            ];
        }

        return $wire;
    }

    protected function isToolResultOnly(array $wireMessage): bool
    {
        return collect($wireMessage['content'])->every(fn (array $block) => $block['type'] === 'tool_result');
    }
}
