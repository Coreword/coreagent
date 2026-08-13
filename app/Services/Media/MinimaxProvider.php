<?php

namespace App\Services\Media;

use Illuminate\Support\Facades\Http;
use RuntimeException;

// Wire format based on public docs as of 2026 (platform.minimax.io): async
// submit -> poll pattern. MiniMax's exact field/param names can differ between
// direct-account and reseller-gateway access — verify against your account's
// actual docs and adjust config/media.php (submit_path/poll_path) if needed.
class MinimaxProvider implements VideoGenerationProviderInterface
{
    public function __construct(protected array $config)
    {
    }

    public function key(): string
    {
        return 'minimax';
    }

    public function isConfigured(): bool
    {
        return filled($this->config['api_key'] ?? null) && filled($this->config['base_url'] ?? null);
    }

    public function submit(string $prompt, array $options = []): string
    {
        if (! $this->isConfigured()) {
            throw new MediaProviderNotConfiguredException('minimax has no api_key/base_url configured.');
        }

        $response = Http::withToken($this->config['api_key'])
            ->baseUrl($this->config['base_url'])
            ->post($this->config['submit_path'], array_merge([
                'model' => $this->config['model'],
                'prompt' => $prompt,
            ], $options));

        if ($response->failed()) {
            throw new RuntimeException('[minimax] video submit failed: '.$response->body());
        }

        $jobId = $response->json('task_id') ?? $response->json('job_id') ?? $response->json('id');

        if (! $jobId) {
            throw new RuntimeException('[minimax] video submit response had no task_id/job_id/id: '.$response->body());
        }

        return (string) $jobId;
    }

    public function pollStatus(string $externalJobId): array
    {
        $response = Http::withToken($this->config['api_key'])
            ->baseUrl($this->config['base_url'])
            ->get($this->config['poll_path'], ['task_id' => $externalJobId]);

        if ($response->failed()) {
            throw new RuntimeException('[minimax] video poll failed: '.$response->body());
        }

        $rawStatus = strtolower((string) ($response->json('status') ?? $response->json('state') ?? 'processing'));
        $outputUrl = $response->json('file_url') ?? $response->json('video_url') ?? $response->json('output_url');

        return [
            'status' => $this->normalizeStatus($rawStatus, $outputUrl),
            'output_url' => $outputUrl,
            'raw' => $response->json() ?? [],
        ];
    }

    protected function normalizeStatus(string $rawStatus, ?string $outputUrl): string
    {
        if ($outputUrl) {
            return 'completed';
        }

        return match (true) {
            str_contains($rawStatus, 'fail'), str_contains($rawStatus, 'error') => 'failed',
            str_contains($rawStatus, 'success'), str_contains($rawStatus, 'complete'), str_contains($rawStatus, 'done') => 'completed',
            default => 'processing',
        };
    }
}
