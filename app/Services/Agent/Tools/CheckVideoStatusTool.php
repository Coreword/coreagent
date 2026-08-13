<?php

namespace App\Services\Agent\Tools;

use App\Models\User;
use App\Models\VideoGeneration;
use App\Services\Agent\ToolInterface;
use App\Services\Media\VideoGenerationService;

class CheckVideoStatusTool implements ToolInterface
{
    public function __construct(protected VideoGenerationService $videos)
    {
    }

    public function name(): string
    {
        return 'check_video_status';
    }

    public function description(): string
    {
        return '查一個 generate_video 任務嘅目前狀態，完成咗會有 output_url。';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'video_generation_id' => ['type' => 'integer'],
            ],
            'required' => ['video_generation_id'],
        ];
    }

    public function execute(array $arguments, User $user): array
    {
        $videoGeneration = VideoGeneration::find($arguments['video_generation_id'] ?? null);

        if (! $videoGeneration) {
            return ['error' => 'video_generation_id not found'];
        }

        $videoGeneration = $this->videos->pollAndUpdate($videoGeneration);

        return [
            'status' => $videoGeneration->status,
            'output_url' => $videoGeneration->output_url,
        ];
    }
}
