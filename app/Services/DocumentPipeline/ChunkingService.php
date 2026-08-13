<?php

namespace App\Services\DocumentPipeline;

use App\Models\Chunk;
use App\Models\Document;
use Illuminate\Support\Collection;

class ChunkingService
{
    // Word-count based approximation of the 500-800 token / 100 token overlap
    // narrative-document strategy from document-copilot-implementation_1.md 3.1.
    // Form-specific section-aware chunking is a Phase 2 refinement once Classify
    // runs before chunking (currently chunking happens at Ingest, before doc_type is known).
    protected int $chunkWords = 600;

    protected int $overlapWords = 100;

    /**
     * @param  array<int, array{page_number: ?int, text: string}>  $pages
     * @return Collection<int, Chunk>
     */
    public function chunkDocument(Document $document, array $pages): Collection
    {
        $chunks = collect();
        $chunkIndex = 0;

        foreach ($pages as $page) {
            $words = preg_split('/\s+/', $page['text'], -1, PREG_SPLIT_NO_EMPTY);
            $totalWords = count($words);
            $step = max($this->chunkWords - $this->overlapWords, 1);

            for ($start = 0; $start < $totalWords; $start += $step) {
                $slice = array_slice($words, $start, $this->chunkWords);
                $content = implode(' ', $slice);

                $chunks->push(Chunk::create([
                    'document_id' => $document->id,
                    'page_number' => $page['page_number'],
                    'chunk_index' => $chunkIndex++,
                    'content' => $content,
                ]));

                if ($start + $this->chunkWords >= $totalWords) {
                    break;
                }
            }
        }

        return $chunks;
    }
}
