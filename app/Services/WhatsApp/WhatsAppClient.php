<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Thin wrapper over the WhatsApp Cloud API's /messages endpoint. There is no
 * inbound side here — that's WhatsAppWebhookController, which owns request
 * verification. This class only ever sends.
 */
class WhatsAppClient
{
    public function __construct(protected array $config)
    {
    }

    public function isConfigured(): bool
    {
        return filled($this->config['access_token'] ?? null)
            && filled($this->config['phone_number_id'] ?? null);
    }

    /**
     * @param  string  $to  E.164 phone number, digits only per Meta's API (no "+").
     */
    public function sendText(string $to, string $body): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('WhatsApp is not configured (missing WHATSAPP_ACCESS_TOKEN or WHATSAPP_PHONE_NUMBER_ID).');
        }

        // WhatsApp text bodies are capped at 4096 chars; the agent's own
        // max_tokens ceilings make this generous, but a runaway tool-result
        // dump should still get truncated rather than silently rejected by Meta.
        $body = mb_substr($body, 0, 4096);

        $url = sprintf(
            '%s/%s/%s/messages',
            rtrim($this->config['graph_base_url'], '/'),
            $this->config['api_version'],
            $this->config['phone_number_id'],
        );

        $response = Http::withToken($this->config['access_token'])
            ->timeout(15)
            ->post($url, [
                'messaging_product' => 'whatsapp',
                'to' => ltrim($to, '+'),
                'type' => 'text',
                'text' => ['body' => $body, 'preview_url' => true],
            ]);

        if ($response->failed()) {
            Log::warning('[WhatsAppClient] send failed', [
                'to' => $to,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException("WhatsApp send failed: HTTP {$response->status()}");
        }
    }
}
