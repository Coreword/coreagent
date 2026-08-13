<?php

namespace App\Services\Agent;

use App\Models\AgentStep;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\Llm\AllProvidersFailedException;
use App\Services\Llm\ProviderRouter;
use Illuminate\Support\Str;
use Throwable;

class AgentOrchestrator
{
    protected int $maxIterations = 8;

    protected const SYSTEM_PROMPT = <<<'PROMPT'
你係 coreAgent，一個可以直接執行任務嘅 AI 助手，運行喺 Altodock Digital 嘅 coreAgent 平台入面。
你可以用 tool 查詢 Document Copilot 個案資料（list_cases / get_case），或者提交影片生成任務（generate_video / check_video_status）。
回覆用廣東話，簡潔清楚。如果唔知道答案，或者冇合適嘅 tool 可以用，老實講返俾用戶知，唔好作大或者假設冇根據嘅資料。
PROMPT;

    public function __construct(protected ProviderRouter $router, protected ToolRegistry $tools)
    {
    }

    public function run(Conversation $conversation, string $userMessage): void
    {
        $conversation->update(['status' => 'processing']);

        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $userMessage,
        ]);

        if (! $conversation->title) {
            $conversation->update(['title' => Str::limit($userMessage, 60)]);
        }

        $history = $this->loadHistory($conversation);
        $toolsSchema = $this->tools->toSchemaArray();

        for ($i = 0; $i < $this->maxIterations; $i++) {
            try {
                $result = $this->router->chat(
                    array_merge([['role' => 'system', 'content' => self::SYSTEM_PROMPT]], $history),
                    $toolsSchema,
                    $conversation->provider,
                );
            } catch (AllProvidersFailedException $e) {
                $this->finalize($conversation, $e->getMessage());

                return;
            }

            $providerUsed = $result['provider_used'];

            if (! empty($result['tool_calls'])) {
                $assistantMessage = Message::create([
                    'conversation_id' => $conversation->id,
                    'role' => 'assistant',
                    'content' => $result['content'],
                    'tool_calls' => $result['tool_calls'],
                ]);

                $history[] = [
                    'role' => 'assistant',
                    'content' => $result['content'],
                    'tool_calls' => $result['tool_calls'],
                ];

                foreach ($result['tool_calls'] as $call) {
                    AgentStep::create([
                        'conversation_id' => $conversation->id,
                        'message_id' => $assistantMessage->id,
                        'step_type' => 'tool_call',
                        'tool_name' => $call['name'],
                        'tool_input' => $call['arguments'],
                        'provider_used' => $providerUsed,
                    ]);

                    $output = $this->executeTool($call, $conversation);

                    AgentStep::create([
                        'conversation_id' => $conversation->id,
                        'message_id' => $assistantMessage->id,
                        'step_type' => 'tool_result',
                        'tool_name' => $call['name'],
                        'tool_output' => $output,
                        'provider_used' => $providerUsed,
                    ]);

                    $toolResultContent = json_encode($output, JSON_UNESCAPED_UNICODE);

                    Message::create([
                        'conversation_id' => $conversation->id,
                        'role' => 'tool',
                        'content' => $toolResultContent,
                        'tool_name' => $call['name'],
                        'tool_call_id' => $call['id'],
                    ]);

                    $history[] = [
                        'role' => 'tool',
                        'content' => $toolResultContent,
                        'tool_call_id' => $call['id'],
                        'name' => $call['name'],
                    ];
                }

                continue;
            }

            $finalMessage = Message::create([
                'conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => $result['content'] ?? '（冇回覆內容）',
            ]);

            AgentStep::create([
                'conversation_id' => $conversation->id,
                'message_id' => $finalMessage->id,
                'step_type' => 'final_answer',
                'provider_used' => $providerUsed,
            ]);

            $conversation->update(['status' => 'idle']);

            return;
        }

        $this->finalize($conversation, "已達步驟上限（{$this->maxIterations} round），停止執行。");
    }

    protected function executeTool(array $call, Conversation $conversation): array
    {
        $tool = $this->tools->get($call['name']);

        if (! $tool) {
            return ['error' => "unknown tool: {$call['name']}"];
        }

        try {
            return $tool->execute(
                array_merge($call['arguments'], ['_conversation_id' => $conversation->id]),
                $conversation->user,
            );
        } catch (Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    protected function finalize(Conversation $conversation, string $text): void
    {
        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $text,
        ]);

        $conversation->update(['status' => 'idle']);
    }

    /**
     * @return array<int, array{role: string, content: ?string, tool_calls?: array, tool_call_id?: string, name?: string}>
     */
    protected function loadHistory(Conversation $conversation): array
    {
        return $conversation->messages()->get()->map(fn (Message $m) => array_filter([
            'role' => $m->role,
            'content' => $m->content,
            'tool_calls' => $m->tool_calls,
            'tool_call_id' => $m->tool_call_id,
            'name' => $m->tool_name,
        ], fn ($v) => $v !== null))->all();
    }
}
