<?php

namespace App\Services\Llm;

interface LlmProviderInterface
{
    /**
     * @param  array<int, array{role: string, content: ?string, tool_calls?: array, tool_call_id?: string, name?: string}>  $messages
     *     Neutral message shape. role is user/assistant/tool. Assistant messages that
     *     called tools carry tool_calls; tool messages carry tool_call_id + name.
     * @param  array<int, array{name: string, description: string, parameters: array}>  $tools
     * @return array{content: ?string, tool_calls: array<int, array{id: string, name: string, arguments: array}>, raw: array}
     */
    public function chat(array $messages, array $tools = []): array;

    public function key(): string;

    public function isConfigured(): bool;
}
