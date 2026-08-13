<?php

namespace App\Jobs;

use App\Models\CaseRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MaybeAdvanceCaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected const TERMINAL_DOCUMENT_STATUSES = ['extracted', 'awaiting_api_key', 'failed'];

    public function __construct(protected CaseRecord $case)
    {
    }

    public function handle(): void
    {
        $documents = $this->case->documents;

        if ($documents->isEmpty()) {
            return;
        }

        $allFinished = $documents->every(
            fn ($document) => in_array($document->pipeline_status, self::TERMINAL_DOCUMENT_STATUSES, true)
        );

        if (! $allFinished) {
            return;
        }

        RunRuleChecksJob::dispatch($this->case);
    }
}
