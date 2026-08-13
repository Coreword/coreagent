<?php

namespace App\Services\Agent\Tools;

use App\Models\CaseRecord;
use App\Models\User;
use App\Services\Agent\ToolInterface;

class GetCaseTool implements ToolInterface
{
    public function name(): string
    {
        return 'get_case';
    }

    public function description(): string
    {
        return '攞返一個 Document Copilot 個案嘅詳情，包括已抽取欄位（extractions）同合規檢查結果（findings）。';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'case_id' => ['type' => 'integer', 'description' => '個案 ID'],
            ],
            'required' => ['case_id'],
        ];
    }

    public function execute(array $arguments, User $user): array
    {
        $case = CaseRecord::with(['extractions', 'findings'])->find($arguments['case_id'] ?? null);

        if (! $case) {
            return ['error' => "case_id {$arguments['case_id']} not found"];
        }

        return [
            'id' => $case->id,
            'vertical' => $case->vertical,
            'reference' => $case->reference,
            'status' => $case->status,
            'summary' => $case->summary,
            'extractions' => $case->extractions->map(fn ($e) => [
                'field_key' => $e->field_key,
                'field_value' => $e->field_value,
                'confidence' => $e->confidence,
            ])->all(),
            'findings' => $case->findings->map(fn ($f) => [
                'severity' => $f->severity,
                'rule_key' => $f->rule_key,
                'message' => $f->message,
                'resolved' => $f->resolved_at !== null,
            ])->all(),
        ];
    }
}
