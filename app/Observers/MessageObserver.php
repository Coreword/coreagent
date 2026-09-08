<?php

namespace App\Observers;

use App\Jobs\SendWhatsAppReplyJob;
use App\Models\Message;

/**
 * Bridges AgentOrchestrator (channel-agnostic by design) to WhatsApp delivery.
 * Rather than teach the orchestrator about channels, this reacts to the same
 * Message writes it already makes.
 */
class MessageObserver
{
    public function created(Message $message): void
    {
        if ($message->role !== 'assistant') {
            return;
        }

        // Intermediate tool-calling turns also get created with role=assistant
        // (see AgentOrchestrator::run) — only a message with no tool_calls is
        // the actual final answer (or the graceful-failure text from finalize()),
        // which is the only thing that should reach the user on WhatsApp.
        if (! empty($message->tool_calls)) {
            return;
        }

        if ($message->conversation->channel !== 'whatsapp') {
            return;
        }

        SendWhatsAppReplyJob::dispatch($message);
    }
}
