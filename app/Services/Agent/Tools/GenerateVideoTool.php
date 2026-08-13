<?php

namespace App\Services\Agent\Tools;

use App\Models\User;
use App\Jobs\PollVideoGenerationJob;
use App\Services\Agent\ToolInterface;
use App\Services\Media\VideoGenerationService;

class GenerateVideoTool implements ToolInterface
{
    public function __construct(protected VideoGenerationService $videos)
    {
    }

    public function name(): string
    {
        return 'generate_video';
    }

    public function description(): string
    {
        return '提交一個影片生成任務（Seedance 或 Minimax）。呢個係異步任務，唔會即刻攞到片，要用 check_video_status 再查進度。';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'prompt' => ['type' => 'string', 'description' => '影片內容描述'],
                'provider' => ['type' => 'string', 'enum' => ['seedance', 'minimax'], 'description' => '留空即自動揀第一個有設定 API key 嘅 provider'],
                'duration' => ['type' => 'integer', 'description' => '秒數，可選'],
                'resolution' => ['type' => 'string', 'description' => '例如 720p/1080p，可選'],
            ],
            'required' => ['prompt'],
        ];
    }

    public function execute(array $arguments, User $user): array
    {
        $options = array_filter([
            'duration' => $arguments['duration'] ?? null,
            'resolution' => $arguments['resolution'] ?? null,
        ], fn ($v) => $v !== null);

        $videoGeneration = $this->videos->submit(
            prompt: $arguments['prompt'],
            providerKey: $arguments['provider'] ?? null,
            conversationId: $arguments['_conversation_id'] ?? null,
            options: $options,
        );

        if ($videoGeneration->status === 'processing') {
            PollVideoGenerationJob::dispatch($videoGeneration->id)->delay(now()->addSeconds(config('media.poll_interval_seconds')));
        }

        return [
            'video_generation_id' => $videoGeneration->id,
            'provider' => $videoGeneration->provider,
            'status' => $videoGeneration->status,
            'note' => $videoGeneration->status === 'failed'
                ? ($videoGeneration->raw_response['error'] ?? 'submit failed')
                : '任務已提交，用 check_video_status(video_generation_id) 查進度',
        ];
    }
}
