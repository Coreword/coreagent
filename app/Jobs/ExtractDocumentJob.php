<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\DocumentPipeline\ExtractionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExtractDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected Document $document)
    {
    }

    public function handle(ExtractionService $extraction): void
    {
        if (! filled(config('llm.openai.api_key'))) {
            $this->document->update(['pipeline_status' => 'awaiting_api_key']);
            MaybeAdvanceCaseJob::dispatch($this->document->case);

            return;
        }

        $extraction->extract($this->document, $this->document->chunks()->orderBy('chunk_index')->get());

        $this->document->update(['pipeline_status' => 'extracted']);

        MaybeAdvanceCaseJob::dispatch($this->document->case);
    }
}
