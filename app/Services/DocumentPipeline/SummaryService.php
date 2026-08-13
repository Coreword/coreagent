<?php

namespace App\Services\DocumentPipeline;

use App\Models\AuditLog;
use App\Models\CaseRecord;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SummaryService
{
    public function summarise(CaseRecord $case): ?string
    {
        if (! filled(config('llm.openai.api_key'))) {
            AuditLog::record($case->id, 'system', null, 'llm_call_skipped_no_key', [
                'stage' => 'summarise',
            ]);

            return null;
        }

        // Only verified/extracted structured data + findings are fed in — never raw
        // document text — to keep hallucination risk low (doc-copilot guide 3.5).
        $extractions = $case->extractions()->get(['field_key', 'field_value', 'confidence'])->toArray();
        $findings = $case->findings()->get(['rule_key', 'severity', 'message'])->toArray();

        $response = Http::withToken(config('llm.openai.api_key'))
            ->baseUrl(config('llm.openai.base_url'))
            ->post('/chat/completions', [
                'model' => config('llm.openai.chat_model'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => <<<'PROMPT'
根據以下已驗證嘅個案資料同檢查結果，寫一份俾 caseworker 睇嘅摘要。
要求：
- 200 字以內
- 先講 blocker，再講 warning，最後講整體狀態
- 唔好加入資料以外嘅推論
- 每個論點後面標 [欄位名] 以便追溯
PROMPT,
                    ],
                    [
                        'role' => 'user',
                        'content' => '個案資料: '.json_encode($extractions, JSON_UNESCAPED_UNICODE)."\n".
                            '檢查結果: '.json_encode($findings, JSON_UNESCAPED_UNICODE),
                    ],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI summarise request failed: '.$response->body());
        }

        $summary = $response->json('choices.0.message.content');

        AuditLog::record($case->id, 'llm', null, 'case_summarised', [
            'summary_length' => mb_strlen($summary ?? ''),
        ]);

        return $summary;
    }
}
