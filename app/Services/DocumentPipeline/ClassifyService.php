<?php

namespace App\Services\DocumentPipeline;

use App\Models\AuditLog;
use App\Models\Document;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class ClassifyService
{
    public function classify(Document $document, Collection $chunks): void
    {
        $vertical = $document->case->vertical;
        $firstPageText = $chunks->first()?->content ?? '';

        $quickMatch = $this->quickPathMatch($vertical, $firstPageText);

        if ($quickMatch !== null) {
            $document->update([
                'doc_type' => $quickMatch['doc_type'],
                'doc_type_conf' => $quickMatch['confidence'],
            ]);

            AuditLog::record($document->case_id, 'system', null, 'document_classified', [
                'document_id' => $document->id,
                'doc_type' => $quickMatch['doc_type'],
                'confidence' => $quickMatch['confidence'],
                'method' => 'keyword_quick_path',
            ]);

            return;
        }

        if (! filled(config('llm.openai.api_key'))) {
            AuditLog::record($document->case_id, 'system', null, 'llm_call_skipped_no_key', [
                'document_id' => $document->id,
                'stage' => 'classify',
            ]);

            return;
        }

        $result = $this->llmClassify($vertical, $firstPageText);

        $document->update([
            'doc_type' => $result['doc_type'],
            'doc_type_conf' => $result['confidence'],
        ]);

        AuditLog::record($document->case_id, 'llm', null, 'document_classified', [
            'document_id' => $document->id,
            'doc_type' => $result['doc_type'],
            'confidence' => $result['confidence'],
            'method' => 'llm',
        ]);
    }

    /**
     * @return array{doc_type: string, confidence: float}|null
     */
    protected function quickPathMatch(string $vertical, string $text): ?array
    {
        $keywordMap = config("schemas.{$vertical}.classify_keywords", []);
        $haystack = Str::lower($text);

        foreach ($keywordMap as $docType => $keywords) {
            foreach ($keywords as $keyword) {
                if (Str::contains($haystack, Str::lower($keyword))) {
                    return ['doc_type' => $docType, 'confidence' => 0.9];
                }
            }
        }

        return null;
    }

    /**
     * @return array{doc_type: ?string, confidence: float}
     */
    protected function llmClassify(string $vertical, string $text): array
    {
        $docTypes = config("schemas.{$vertical}.doc_types", []);

        $response = Http::withToken(config('llm.openai.api_key'))
            ->baseUrl(config('llm.openai.base_url'))
            ->post('/chat/completions', [
                'model' => config('llm.openai.chat_model'),
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => "你係文件分類器。只輸出 JSON，冇任何前後文字。\n".
                            '可選類型: '.implode(', ', $docTypes)."\n".
                            '輸出格式: {"doc_type": "...", "confidence": 0.0-1.0, "reason": "..."}',
                    ],
                    ['role' => 'user', 'content' => Str::limit($text, 4000)],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI classify request failed: '.$response->body());
        }

        $payload = json_decode($response->json('choices.0.message.content', '{}'), true) ?: [];

        return [
            'doc_type' => $payload['doc_type'] ?? null,
            'confidence' => (float) ($payload['confidence'] ?? 0.0),
        ];
    }
}
