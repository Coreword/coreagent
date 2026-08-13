<?php

// Financial Evidence Copilot 規則引擎 — 唔用 LLM，純確定性檢查。
// $c 係 App\Services\DocumentPipeline\RuleCheckContext，提供 field()/date() 存取已驗證嘅 extraction 值。

return [
    'missing_applicant_name' => [
        'severity' => 'blocker',
        'check' => fn ($c) => empty($c->field('applicant_name')),
        'message' => '個案缺少申請人姓名，無法核對身份。',
    ],
    'missing_income_evidence' => [
        'severity' => 'blocker',
        'check' => fn ($c) => empty($c->field('gross_income')) && empty($c->field('net_income')),
        'message' => '個案缺少入息證明（糧單嘅 gross/net income）。',
    ],
    'missing_bank_statement' => [
        'severity' => 'warning',
        'check' => fn ($c) => empty($c->field('bank_name')) && empty($c->field('closing_balance')),
        'message' => '個案缺少銀行月結單資料。',
    ],
    'document_stale' => [
        'severity' => 'warning',
        'check' => fn ($c) => $c->field('document_date')
                          && now()->diffInDays($c->date('document_date')) > 90,
        'message' => '文件日期超過 90 日，可能唔反映最新財務狀況。',
    ],
    'income_mismatch' => [
        'severity' => 'warning',
        'check' => fn ($c) => $c->field('gross_income') && $c->field('net_income')
                          && (float) $c->field('gross_income') < (float) $c->field('net_income'),
        'message' => 'Gross income 細過 net income，數字可能有誤，需要人手覆核。',
    ],
    'low_confidence_flag' => [
        'severity' => 'info',
        'check' => fn ($c) => $c->hasLowConfidenceField(0.6),
        'message' => '有欄位嘅抽取信心值偏低，建議人手覆核。',
    ],
];
