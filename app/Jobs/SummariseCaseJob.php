<?php

namespace App\Jobs;

use App\Models\CaseRecord;
use App\Services\DocumentPipeline\SummaryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SummariseCaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected CaseRecord $case)
    {
    }

    public function handle(SummaryService $summary): void
    {
        $text = $summary->summarise($this->case);

        if ($text === null) {
            return;
        }

        $this->case->update([
            'summary' => $text,
            'status' => 'summarised',
        ]);
    }
}
