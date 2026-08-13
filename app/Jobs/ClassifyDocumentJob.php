<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\DocumentPipeline\ClassifyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ClassifyDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected Document $document)
    {
    }

    public function handle(ClassifyService $classify): void
    {
        $classify->classify($this->document, $this->document->chunks()->orderBy('chunk_index')->get());
        $this->document->refresh();

        if ($this->document->doc_type === null) {
            $this->document->update(['pipeline_status' => 'awaiting_api_key']);
            MaybeAdvanceCaseJob::dispatch($this->document->case);

            return;
        }

        $this->document->update(['pipeline_status' => 'classified']);

        ExtractDocumentJob::dispatch($this->document);
    }
}
