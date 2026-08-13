<?php

namespace App\Services\DocumentPipeline;

use App\Models\Chunk;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class EmbeddingService
{
    // MVP: no pgvector on this Windows dev box (would need Visual Studio build tools
    // to compile it), so embeddings are stored as a JSON float array (see chunks
    // migration) and similarity is computed here in PHP. Fine at case-sized chunk
    // counts; migrate to pgvector + HNSW index once volume or a Linux host makes
    // that worthwhile.

    public function isEnabled(): bool
    {
        return filled(config('llm.openai.api_key'));
    }

    /**
     * @return array<int, float>|null
     */
    public function embed(string $text): ?array
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $response = Http::withToken(config('llm.openai.api_key'))
            ->baseUrl(config('llm.openai.base_url'))
            ->post('/embeddings', [
                'model' => config('llm.openai.embedding_model'),
                'input' => $text,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI embeddings request failed: '.$response->body());
        }

        return $response->json('data.0.embedding');
    }

    /**
     * @param  Collection<int, Chunk>  $chunks
     */
    public function embedChunks(Collection $chunks): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        foreach ($chunks as $chunk) {
            $chunk->update(['embedding' => $this->embed($chunk->content)]);
        }
    }

    /**
     * @param  array<int, float>  $queryVector
     * @param  Collection<int, Chunk>  $chunks
     * @return Collection<int, Chunk>
     */
    public function topK(array $queryVector, Collection $chunks, int $k = 10): Collection
    {
        return $chunks
            ->filter(fn (Chunk $chunk) => is_array($chunk->embedding))
            ->sortByDesc(fn (Chunk $chunk) => $this->cosineSimilarity($queryVector, $chunk->embedding))
            ->take($k)
            ->values();
    }

    /**
     * @param  array<int, float>  $a
     * @param  array<int, float>  $b
     */
    public function cosineSimilarity(array $a, array $b): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        $length = min(count($a), count($b));

        for ($i = 0; $i < $length; $i++) {
            $dot += $a[$i] * $b[$i];
            $normA += $a[$i] ** 2;
            $normB += $b[$i] ** 2;
        }

        if ($normA === 0.0 || $normB === 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }
}
