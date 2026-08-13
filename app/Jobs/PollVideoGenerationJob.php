<?php

namespace App\Jobs;

use App\Models\VideoGeneration;
use App\Services\Media\VideoGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PollVideoGenerationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected int $videoGenerationId, protected int $attempt = 1)
    {
    }

    public function handle(VideoGenerationService $videos): void
    {
        $videoGeneration = VideoGeneration::find($this->videoGenerationId);

        if (! $videoGeneration) {
            return;
        }

        $videoGeneration = $videos->pollAndUpdate($videoGeneration);

        if (in_array($videoGeneration->status, ['completed', 'failed'], true)) {
            return;
        }

        if ($this->attempt >= config('media.poll_max_attempts')) {
            $videoGeneration->update([
                'status' => 'failed',
                'raw_response' => array_merge($videoGeneration->raw_response ?? [], ['error' => 'polling timed out']),
            ]);

            return;
        }

        // Self-requeue rather than relying on `schedule:run` (no cron/Task
        // Scheduler set up on this dev box) — each attempt polls again after
        // a delay until the job completes, fails, or hits the attempt cap.
        static::dispatch($this->videoGenerationId, $this->attempt + 1)
            ->delay(now()->addSeconds(config('media.poll_interval_seconds')));
    }
}
