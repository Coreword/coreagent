<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\DocumentPipeline\ChunkingService;
use App\Services\DocumentPipeline\EmbeddingService;
use App\Services\DocumentPipeline\IngestService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class IngestDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected Document $document)
    {
    }

    public function handle(IngestService $ingest, ChunkingService $chunking, EmbeddingService $embeddings): void
    {
        $pages = $ingest->extractPages($this->document);
        $chunks = $chunking->chunkDocument($this->document, $pages);
        $embeddings->embedChunks($chunks);

        $this->document->update(['pipeline_status' => 'ingested']);
        $this->document->case()->update(['status' => 'processing']);

        ClassifyDocumentJob::dispatch($this->document);
    }
}
