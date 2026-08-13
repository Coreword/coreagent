<?php

namespace App\Services\Media;

interface VideoGenerationProviderInterface
{
    public function key(): string;

    public function isConfigured(): bool;

    /**
     * @param  array{duration?: int, resolution?: string, aspect_ratio?: string}  $options
     * @return string external job id
     */
    public function submit(string $prompt, array $options = []): string;

    /**
     * @return array{status: string, output_url: ?string, raw: array}
     */
    public function pollStatus(string $externalJobId): array;
}
