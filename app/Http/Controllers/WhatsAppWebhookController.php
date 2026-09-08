<?php

namespace App\Http\Controllers;

use App\Jobs\RunAgentJob;
use App\Models\Message;
use App\Services\WhatsApp\WhatsAppConversationResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    /**
     * Meta's one-time subscription handshake: GET with hub.mode=subscribe,
     * hub.verify_token, hub.challenge. Echo the challenge back verbatim if the
     * token matches what was configured in the Meta App dashboard, otherwise
     * refuse — this is the only thing standing between "anyone can register
     * a webhook URL" and this endpoint.
     */
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode', $request->query('hub.mode'));
        $token = $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));

        $expected = config('services.whatsapp.verify_token');

        if ($mode === 'subscribe' && filled($expected) && hash_equals($expected, (string) $token)) {
            return response((string) $challenge, 200);
        }

        Log::warning('[WhatsAppWebhook] verification handshake rejected', ['mode' => $mode]);

        return response('', 403);
    }

    /**
     * Inbound message delivery. Meta expects a fast 2xx — it retries on
     * anything else, including a slow response — so this only validates,
     * persists the inbound message, and dispatches the same RunAgentJob the
     * web chat uses. The actual LLM call and the WhatsApp reply both happen
     * off-request (see SendWhatsAppReplyJob, fired by MessageObserver).
     */
    public function receive(Request $request, WhatsAppConversationResolver $resolver): Response
    {
        if (! $this->hasValidSignature($request)) {
            Log::warning('[WhatsAppWebhook] invalid X-Hub-Signature-256, dropping payload');

            return response('', 403);
        }

        $entries = $request->input('entry', []);

        foreach ($entries as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                foreach ($change['value']['messages'] ?? [] as $waMessage) {
                    $this->handleInboundMessage($waMessage, $resolver);
                }
                // change['value']['statuses'] (sent/delivered/read receipts) is
                // deliberately ignored — nothing in coreAgent tracks delivery state.
            }
        }

        // Always 200: a payload we can't make sense of (unsupported message
        // type, malformed entry) is not something Meta should keep retrying.
        return response('', 200);
    }

    protected function handleInboundMessage(array $waMessage, WhatsAppConversationResolver $resolver): void
    {
        $waMessageId = $waMessage['id'] ?? null;
        $from = $waMessage['from'] ?? null;

        if (! $waMessageId || ! $from) {
            return;
        }

        // Meta redelivers on timeout/non-2xx; the unique constraint on
        // wa_message_id is the actual guard, this is just the cheap early exit.
        if (Message::where('wa_message_id', $waMessageId)->exists()) {
            return;
        }

        $text = $waMessage['text']['body'] ?? null;

        $conversation = $resolver->resolve($from);

        if (! $text) {
            // Non-text (image/audio/document/location/...). Acknowledging with
            // a plain reply keeps the contact from wondering if the bot is
            // dead, without building media ingestion this pass.
            Message::create([
                'conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => '而家淨係支援文字消息，麻煩打字俾我 🙏',
                'wa_message_id' => null,
            ]);

            return;
        }

        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $text,
            'wa_message_id' => $waMessageId,
        ]);

        $conversation->update(['status' => 'processing']);

        RunAgentJob::dispatch($conversation, $text);
    }

    /**
     * HMAC-SHA256 over the raw body, keyed by the Meta App Secret — verifies
     * the payload actually came from Meta and wasn't replayed/forged. Must run
     * against the raw, unparsed body: re-serializing $request->all() would not
     * reproduce the exact bytes Meta signed.
     */
    protected function hasValidSignature(Request $request): bool
    {
        $appSecret = config('services.whatsapp.app_secret');

        if (blank($appSecret)) {
            // No secret configured — this is a setup gap, not something to fail
            // open on. Treat as invalid so a misconfigured deploy is loud.
            return false;
        }

        $signatureHeader = $request->header('X-Hub-Signature-256', '');

        if (! str_starts_with($signatureHeader, 'sha256=')) {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $appSecret);
        $provided = substr($signatureHeader, strlen('sha256='));

        return hash_equals($expected, $provided);
    }
}
