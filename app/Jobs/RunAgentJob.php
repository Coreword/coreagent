<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Services\Agent\AgentOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunAgentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected Conversation $conversation, protected string $userMessage)
    {
    }

    public function handle(AgentOrchestrator $orchestrator): void
    {
        $orchestrator->run($this->conversation, $this->userMessage);
    }
}
