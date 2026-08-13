<?php

namespace App\Jobs;

use App\Models\CaseRecord;
use App\Services\DocumentPipeline\RuleEngineService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunRuleChecksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected CaseRecord $case)
    {
    }

    public function handle(RuleEngineService $ruleEngine): void
    {
        $ruleEngine->run($this->case);

        $anyAwaitingApiKey = $this->case->documents()
            ->where('pipeline_status', 'awaiting_api_key')
            ->exists();

        $this->case->update([
            'status' => $anyAwaitingApiKey ? 'awaiting_api_key' : 'checked',
        ]);

        SummariseCaseJob::dispatch($this->case);
    }
}
