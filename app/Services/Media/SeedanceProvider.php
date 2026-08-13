<?php

namespace App\Services\Media;

use Illuminate\Support\Facades\Http;
use RuntimeException;

// Seedance (ByteDance) is commonly accessed through reseller gateways
// (fal.ai, aimlapi, kie.ai) rather than one canonical direct endpoint, so
// field/param names vary more than most providers — verify against your
// actual account's docs and adjust config/media.php accordingly.
class SeedanceProvider implements VideoGenerationProviderInterface
{
    public function __construct(protected array $config)
    {
    }

    public function key(): string
    {
        return 'seedance';
    }

    public function isConfigured(): bool
    {
        return filled($this->config['api_key'] ?? null) && filled($this->config['base_url'] ?? null);
    }

    public function submit(string $prompt, array $options = []): string
    {
        if (! $this->isConfigured()) {
            throw new MediaProviderNotConfiguredException('seedance has no api_key/base_url configured.');
        }

        $response = Http::withToken($this->config['api_key'])
            ->baseUrl($this->config['base_url'])
            ->post($this->config['submit_path'], array_merge([
                'model' => $this->config['model'],
                'prompt' => $prompt,
            ], $options));

        if ($response->failed()) {
            throw new RuntimeException('[seedance] video submit failed: '.$response->body());
        }

        $jobId = $response->json('id') ?? $response->json('task_id') ?? $response->json('request_id');

        if (! $jobId) {
            throw new RuntimeException('[seedance] video submit response had no id/task_id/request_id: '.$response->body());
        }

        return (string) $jobId;
    }

    public function pollStatus(string $externalJobId): array
    {
        $path = str_contains($this->config['poll_path'], '{job_id}')
            ? str_replace('{job_id}', $externalJobId, $this->config['poll_path'])
            : $this->config['poll_path'];

        $query = str_contains($this->config['poll_path'], '{job_id}') ? [] : ['id' => $externalJobId];

        $response = Http::withToken($this->config['api_key'])
            ->baseUrl($this->config['base_url'])
            ->get($path, $query);

        if ($response->failed()) {
            throw new RuntimeException('[seedance] video poll failed: '.$response->body());
        }

        $rawStatus = strtolower((string) ($response->json('status') ?? $response->json('state') ?? 'processing'));
        $outputUrl = $response->json('video_url') ?? $response->json('output.video_url') ?? $response->json('url');

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
