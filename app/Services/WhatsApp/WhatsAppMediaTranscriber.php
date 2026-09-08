<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Turns a WhatsApp voice note into text: download the media from the Cloud
 * API (two hops — Meta returns a signed URL, then the audio itself, both
 * needing the WhatsApp access token), then transcribe it with OpenAI's
 * Whisper endpoint. No other configured provider (DeepSeek/Claude/Qwen) does
 * speech-to-text, so this is OpenAI-only — see config/providers.php.
 */
class WhatsAppMediaTranscriber
{
    public function __construct(protected array $whatsappConfig, protected array $openaiConfig)
    {
    }

    public function isConfigured(): bool
    {
        return filled($this->whatsappConfig['access_token'] ?? null)
            && filled($this->openaiConfig['api_key'] ?? null);
    }

    /**
     * @param  string  $mediaId  the WhatsApp media id from message.audio.id
     */
    public function transcribe(string $mediaId): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Voice transcription is not configured (needs WHATSAPP_ACCESS_TOKEN and OPENAI_API_KEY).');
        }

        [$bytes, $mimeType] = $this->downloadMedia($mediaId);

        return $this->transcribeAudio($bytes, $mimeType);
    }

    /**
     * @return array{0: string, 1: string} [raw bytes, mime type]
     */
    protected function downloadMedia(string $mediaId): array
    {
        $token = $this->whatsappConfig['access_token'];

        $lookup = Http::withToken($token)
            ->timeout(15)
            ->get(sprintf(
                '%s/%s/%s',
                rtrim($this->whatsappConfig['graph_base_url'], '/'),
                $this->whatsappConfig['api_version'],
                $mediaId,
            ));

        if ($lookup->failed()) {
            throw new RuntimeException("WhatsApp media lookup failed: HTTP {$lookup->status()}");
        }

        $url = $lookup->json('url');
        $mimeType = $lookup->json('mime_type', 'audio/ogg');

        if (blank($url)) {
            throw new RuntimeException('WhatsApp media lookup returned no url.');
        }

        // Media URLs are not public — Meta requires the same bearer token
        // to fetch the bytes as to look up where they are.
        $download = Http::withToken($token)->timeout(30)->get($url);

        if ($download->failed()) {
            throw new RuntimeException("WhatsApp media download failed: HTTP {$download->status()}");
        }

        return [$download->body(), $mimeType];
    }

    protected function transcribeAudio(string $bytes, string $mimeType): string
    {
        $extension = str_contains($mimeType, 'ogg') ? 'ogg'
            : (str_contains($mimeType, 'mp4') || str_contains($mimeType, 'aac') ? 'm4a'
            : (str_contains($mimeType, 'amr') ? 'amr' : 'ogg'));

        $response = Http::withToken($this->openaiConfig['api_key'])
            ->timeout(60)
            ->attach('file', $bytes, "voice.{$extension}")
            ->post(rtrim($this->openaiConfig['base_url'], '/').'/audio/transcriptions', [
                'model' => $this->openaiConfig['transcribe_model'],
            ]);

        if ($response->failed()) {
            throw new RuntimeException("Whisper transcription failed: HTTP {$response->status()}");
        }

        $text = trim((string) $response->json('text'));

        if ($text === '') {
            throw new RuntimeException('Whisper returned an empty transcription.');
        }

        return $text;
    }
}
