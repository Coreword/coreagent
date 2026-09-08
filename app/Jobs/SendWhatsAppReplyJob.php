<?php

namespace App\Jobs;

use App\Models\Message;
use App\Services\WhatsApp\WhatsAppClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendWhatsAppReplyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly Message $message)
    {
    }

    public function handle(WhatsAppClient $client): void
    {
        $conversation = $this->message->conversation;
        $phoneNumber = $conversation->user->phone_number;

        if (! $phoneNumber || ! $this->message->content) {
            return;
        }

        try {
            $client->sendText($phoneNumber, $this->stripMarkdown($this->message->content));
        } catch (Throwable $e) {
            // Don't let a WhatsApp delivery failure retry into AgentOrchestrator
            // territory or corrupt conversation state — it already finished and
            // committed the message; this is purely "could we deliver it".
            Log::warning('[SendWhatsAppReplyJob] delivery failed: '.$e->getMessage(), [
                'conversation_id' => $conversation->id,
            ]);

            throw $e; // still rethrow so the queue's retry/backoff applies.
        }
    }

    /**
     * The agent's replies are written assuming a markdown-rendering client
     * (the web Chat.vue view). WhatsApp has its own much smaller formatting
     * dialect — single asterisks for bold, single underscores for italic, no
     * headers, no fenced code blocks — so a literal "**foo**" or "## Title"
     * would render as visible punctuation instead of formatting.
     */
    protected function stripMarkdown(string $text): string
    {
        $text = preg_replace('/^#{1,6}\s+/m', '', $text);          // # Heading -> Heading
        $text = preg_replace('/\*\*(.+?)\*\*/s', '*$1*', $text);    // **bold** -> *bold*
        $text = preg_replace('/```[a-zA-Z]*\n?/', '', $text);       // fenced code markers
        $text = str_replace('```', '', $text);

        return trim($text);
    }
}
