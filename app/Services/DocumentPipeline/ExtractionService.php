<?php

namespace App\Services\DocumentPipeline;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Extraction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ExtractionService
{
    public function __construct(protected EmbeddingService $embeddings)
    {
    }

    /**
     * @param  Collection<int, \App\Models\Chunk>  $chunks
     */
    public function extract(Document $document, Collection $chunks): void
    {
        $vertical = $document->case->vertical;
        $fields = config("schemas.{$vertical}.fields", []);

        // Only fields relevant to this document's doc_type, per its source_doc_types.
        $relevantFields = collect($fields)->filter(
            fn (array $field) => empty($field['source_doc_types']) || in_array($document->doc_type, $field['source_doc_types'], true)
        );

        if ($relevantFields->isEmpty()) {
            return;
        }

        if (! filled(config('llm.openai.api_key'))) {
            AuditLog::record($document->case_id, 'system', null, 'llm_call_skipped_no_key', [
                'document_id' => $document->id,
                'stage' => 'extract',
            ]);

            return;
        }

        // Top-k retrieval by embedding similarity when embeddings exist, otherwise
        // fall back to the first few chunks (still true when OPENAI_API_KEY covers
        // chat completions but embeddings were skipped for any reason).
        $relevantChunks = $chunks->first()?->embedding
            ? $this->embeddings->topK($this->embeddings->embed($relevantFields->keys()->implode(' ')) ?? [], $chunks, 10)
            : $chunks->take(10);

        $result = $this->callExtractionApi($relevantFields, $relevantChunks);

        foreach ($result as $fieldKey => $fieldResult) {
            if (! array_key_exists($fieldKey, $relevantFields->all())) {
                continue;
            }

            $value = $fieldResult['value'] ?? null;
            $confidence = (float) ($fieldResult['confidence'] ?? 0.0);
            $sourceChunkIndex = $fieldResult['source_chunk'] ?? null;
            $sourceChunkId = $sourceChunkIndex !== null ? ($relevantChunks->get($sourceChunkIndex)?->id) : null;

            if (! $this->passesPatternValidation($fields[$fieldKey], $value)) {
                $confidence = min($confidence, 0.4);
            }

            Extraction::create([
                'case_id' => $document->case_id,
                'document_id' => $document->id,
                'field_key' => $fieldKey,
                'field_value' => $value !== null ? (string) $value : null,
                'value_type' => $fields[$fieldKey]['type'] ?? 'string',
                'confidence' => $confidence,
                'source_chunk_id' => $sourceChunkId,
            ]);
        }

        AuditLog::record($document->case_id, 'llm', null, 'document_extracted', [
            'document_id' => $document->id,
            'fields_extracted' => count($result),
        ]);
    }

    /**
     * @param  Collection<string, array>  $fields
     * @param  Collection<int, \App\Models\Chunk>  $chunks
     * @return array<string, array{value: mixed, confidence: float, source_chunk: ?int}>
     */
    protected function callExtractionApi(Collection $fields, Collection $chunks): array
    {
        $chunkText = $chunks->values()
            ->map(fn ($chunk, $index) => "[chunk_id: {$index}] {$chunk->content}")
            ->implode("\n\n");

        $response = Http::withToken(config('llm.openai.api_key'))
            ->baseUrl(config('llm.openai.base_url'))
            ->post('/chat/completions', [
                'model' => config('llm.openai.chat_model'),
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => <<<'PROMPT'
你係文件資料抽取器。根據提供嘅文件片段，抽取指定欄位。
規則：
- 只輸出 JSON，冇 markdown fence，冇解釋
- 文件冇提及嘅欄位，值設為 null，唔好推測
- 每個欄位要標明信心值同來源片段編號
- 日期一律轉成 YYYY-MM-DD

輸出格式:
{"fields": {"<key>": {"value": ..., "confidence": 0.0-1.0, "source_chunk": <id>}}}
PROMPT,
                    ],
                    [
                        'role' => 'user',
                        'content' => "需抽取欄位:\n".json_encode($fields, JSON_UNESCAPED_UNICODE)."\n\n{$chunkText}",
                    ],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI extraction request failed: '.$response->body());
        }

        $payload = json_decode($response->json('choices.0.message.content', '{}'), true) ?: [];

        return $payload['fields'] ?? [];
    }

    protected function passesPatternValidation(array $fieldDef, mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (! empty($fieldDef['pattern']) && ! preg_match($fieldDef['pattern'], (string) $value)) {
            return false;
        }

        if ($fieldDef['type'] === 'date') {
            return (bool) strtotime((string) $value);
        }

        if ($fieldDef['type'] === 'enum') {
            return in_array($value, $fieldDef['options'] ?? [], true);
        }

        return true;
    }
}
