<?php

namespace App\Services\Agent\Tools;

use App\Models\CaseRecord;
use App\Models\User;
use App\Services\Agent\ToolInterface;

class ListCasesTool implements ToolInterface
{
    public function name(): string
    {
        return 'list_cases';
    }

    public function description(): string
    {
        return '列出 Document Copilot 個案（可選 vertical/status filter），每個個案包括 id/vertical/reference/status/文件數。';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'vertical' => ['type' => 'string', 'description' => '例如 financial_evidence，留空即全部'],
                'status' => ['type' => 'string', 'description' => '例如 awaiting_api_key/checked/summarised，留空即全部'],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, User $user): array
    {
        $query = CaseRecord::withCount('documents')->latest();

        if (! empty($arguments['vertical'])) {
            $query->where('vertical', $arguments['vertical']);
        }

        if (! empty($arguments['status'])) {
            $query->where('status', $arguments['status']);
        }

        return $query->limit(20)->get([
            'id', 'vertical', 'reference', 'status', 'created_at',
        ])->map(fn (CaseRecord $case) => [
            'id' => $case->id,
            'vertical' => $case->vertical,
            'reference' => $case->reference,
            'status' => $case->status,
            'documents_count' => $case->documents_count,
        ])->all();
    }
}
