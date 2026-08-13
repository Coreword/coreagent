<?php

namespace App\Services\Media;

use App\Models\VideoGeneration;

class VideoGenerationService
{
    /** @var array<string, VideoGenerationProviderInterface> */
    protected array $providers;

    public function __construct()
    {
        $this->providers = [
            'seedance' => new SeedanceProvider(config('media.seedance')),
            'minimax' => new MinimaxProvider(config('media.minimax')),
        ];
    }

    public function availableProviders(): array
    {
        return collect($this->providers)
            ->filter(fn (VideoGenerationProviderInterface $p) => $p->isConfigured())
            ->keys()
            ->values()
            ->all();
    }

    public function submit(string $prompt, ?string $providerKey, ?int $conversationId, array $options = []): VideoGeneration
    {
        $providerKey ??= $this->availableProviders()[0] ?? null;
        $provider = $providerKey ? ($this->providers[$providerKey] ?? null) : null;

        if (! $provider || ! $provider->isConfigured()) {
            return VideoGeneration::create([
                'conversation_id' => $conversationId,
                'provider' => $providerKey ?? 'none',
                'prompt' => $prompt,
                'status' => 'failed',
                'raw_response' => ['error' => 'no video provider configured (seedance/minimax api key missing)'],
            ]);
        }

        $videoGeneration = VideoGeneration::create([
            'conversation_id' => $conversationId,
            'provider' => $provider->key(),
            'prompt' => $prompt,
            'status' => 'pending',
        ]);

        try {
            $jobId = $provider->submit($prompt, $options);
            $videoGeneration->update(['external_job_id' => $jobId, 'status' => 'processing']);
        } catch (\Throwable $e) {
            $videoGeneration->update(['status' => 'failed', 'raw_response' => ['error' => $e->getMessage()]]);
        }

        return $videoGeneration;
    }

    public function pollAndUpdate(VideoGeneration $videoGeneration): VideoGeneration
    {
        if (! $videoGeneration->external_job_id || in_array($videoGeneration->status, ['completed', 'failed'], true)) {
            return $videoGeneration;
        }

        $provider = $this->providers[$videoGeneration->provider] ?? null;

        if (! $provider) {
            $videoGeneration->update(['status' => 'failed', 'raw_response' => ['error' => 'unknown provider']]);

            return $videoGeneration;
        }

        try {
            $result = $provider->pollStatus($videoGeneration->external_job_id);
            $videoGeneration->update([
                'status' => $result['status'],
                'output_url' => $result['output_url'],
                'raw_response' => $result['raw'],
            ]);
        } catch (\Throwable $e) {
            $videoGeneration->update(['raw_response' => ['error' => $e->getMessage()]]);
        }

        return $videoGeneration;
    }
}
