<?php

// Financial Evidence Copilot — 8 個核心欄位（貸款證明文件包：糧單 / 銀行月結單）
// 呢個 schema 係根據 document-copilot-implementation_1.md 第 4 節嘅場景設計，
// 文件入面只有 NHS vertical 嘅範例，Financial 呢份要自己補。

return [
    'doc_types' => ['payslip', 'bank_statement', 'employment_letter'],

    // Quick-path classify keywords (case-insensitive substring match on page 1).
    // A hit here skips the LLM classify call entirely to save cost.
    'classify_keywords' => [
        'payslip' => ['payslip', 'gross pay', 'net pay', 'pay date', 'tax code'],
        'bank_statement' => ['statement of account', 'opening balance', 'closing balance', 'sort code'],
        'employment_letter' => ['to whom it may concern', 'employment letter', 'letter of employment'],
    ],

    'fields' => [
        'applicant_name' => [
            'type' => 'string',
            'required' => true,
        ],
        'document_date' => [
            'type' => 'date',
            'required' => true,
        ],
        'employer_name' => [
            'type' => 'string',
            'required' => false,
            'source_doc_types' => ['payslip', 'employment_letter'],
        ],
        'gross_income' => [
            'type' => 'money',
            'required' => false,
            'source_doc_types' => ['payslip'],
        ],
        'net_income' => [
            'type' => 'money',
            'required' => false,
            'source_doc_types' => ['payslip'],
        ],
        'pay_frequency' => [
            'type' => 'enum',
            'options' => ['weekly', 'monthly', 'annual'],
            'required' => false,
            'source_doc_types' => ['payslip'],
        ],
        'bank_name' => [
            'type' => 'string',
            'required' => false,
            'source_doc_types' => ['bank_statement'],
        ],
        'closing_balance' => [
            'type' => 'money',
            'required' => false,
            'source_doc_types' => ['bank_statement'],
        ],
    ],
];
