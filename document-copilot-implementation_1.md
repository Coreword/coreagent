# Document Analysis Copilot — Implementation Guide

**版本**: v0.1
**目標**: 建立一套模組化文件分析 pipeline，支援多個 vertical（Council / NHS / Financial / Farm / Energy / Creative Rights），共用同一核心引擎，每個 vertical 只需換 schema + rule set。

---

## 0. 設計原則

1. **一套引擎，多個 vertical** — 唔起六個獨立系統。差異用 config 表達，唔用 code 分叉。
2. **LLM 做語意，Rule engine 做合規** — 硬性檢查（欄位有冇、日期夠唔夠、金額對唔對）一律由確定性規則處理。LLM 只負責抽取同摘要。
3. **Human-in-the-loop** — 呢啲領域（公共服務、醫療轉介、貸款）涉及問責，系統只做 flagging 同 summary，唔做最終決策。
4. **可追溯** — 每個抽取出嚟嘅欄位都要有 source citation（邊份文件、邊一頁、邊段文字），否則客戶唔會信。
5. **由窄開始** — 先做一個 vertical 做透，再抽象化。過早抽象係最常見嘅失敗模式。

---

## 1. 系統架構

```
┌─────────────────────────────────────────────────────────┐
│  Vue 3 + Tailwind (Case Workspace UI)                   │
│  上傳 → 檢視抽取結果 → 覆核 flagged issues → 匯出報告      │
└────────────────────────┬────────────────────────────────┘
                         │ REST / SSE
┌────────────────────────▼────────────────────────────────┐
│  Laravel 11 API + Queue Workers                          │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌────────────┐ │
│  │ Ingest   │→│ Classify │→│ Extract  │→│ Rule Check │ │
│  └──────────┘ └──────────┘ └──────────┘ └────────────┘ │
│         │                        │              │        │
│         ▼                        ▼              ▼        │
│  ┌──────────────────────────────────────────────────┐   │
│  │ PostgreSQL + pgvector                             │   │
│  │ documents / chunks / embeddings / extractions /   │   │
│  │ findings / cases / audit_log                      │   │
│  └──────────────────────────────────────────────────┘   │
└──────────┬───────────────────────────────┬──────────────┘
           │                               │
    ┌──────▼──────┐               ┌────────▼────────┐
    │ OCR Service │               │ LLM Provider    │
    │ (PaddleOCR/ │               │ (OpenAI 起步 →  │
    │  Azure DI)  │               │  自建 Qwen)     │
    └─────────────┘               └─────────────────┘
```

### 為何 Laravel 做 orchestration

沿用現有 stack，唔另開 Python service。文件處理係 I/O-bound 為主，Laravel Queue（Redis driver）足夠應付。只有 OCR 同日後嘅本地 LLM inference 需要獨立 Python service，用 HTTP 介面隔離。

---

## 2. 資料模型

```sql
-- 個案：一個 case 包含多份文件
CREATE TABLE cases (
    id              BIGSERIAL PRIMARY KEY,
    vertical        VARCHAR(50) NOT NULL,   -- council_casework / nhs_referral / ...
    reference       VARCHAR(100),
    status          VARCHAR(30) DEFAULT 'intake',
    assigned_to     BIGINT REFERENCES users(id),
    created_at      TIMESTAMPTZ DEFAULT now()
);

-- 文件
CREATE TABLE documents (
    id              BIGSERIAL PRIMARY KEY,
    case_id         BIGINT REFERENCES cases(id) ON DELETE CASCADE,
    filename        VARCHAR(255),
    storage_path    TEXT,
    mime_type       VARCHAR(100),
    page_count      INT,
    doc_type        VARCHAR(100),           -- classify 階段填入
    doc_type_conf   NUMERIC(4,3),
    ocr_status      VARCHAR(30) DEFAULT 'pending',
    created_at      TIMESTAMPTZ DEFAULT now()
);

-- 文字分塊（RAG + citation 用）
CREATE TABLE chunks (
    id              BIGSERIAL PRIMARY KEY,
    document_id     BIGINT REFERENCES documents(id) ON DELETE CASCADE,
    page_number     INT,
    chunk_index     INT,
    content         TEXT NOT NULL,
    bbox            JSONB,                  -- 座標，用嚟喺 UI highlight
    embedding       VECTOR(1536)            -- text-embedding-3-small
);
CREATE INDEX chunks_embedding_idx ON chunks
    USING hnsw (embedding vector_cosine_ops);

-- 抽取結果：每個 schema field 一行
CREATE TABLE extractions (
    id              BIGSERIAL PRIMARY KEY,
    case_id         BIGINT REFERENCES cases(id) ON DELETE CASCADE,
    document_id     BIGINT REFERENCES documents(id),
    field_key       VARCHAR(100) NOT NULL,
    field_value     TEXT,
    value_type      VARCHAR(30),            -- string / date / money / bool / enum
    confidence      NUMERIC(4,3),
    source_chunk_id BIGINT REFERENCES chunks(id),
    verified_by     BIGINT REFERENCES users(id),
    verified_at     TIMESTAMPTZ
);

-- 規則檢查結果
CREATE TABLE findings (
    id              BIGSERIAL PRIMARY KEY,
    case_id         BIGINT REFERENCES cases(id) ON DELETE CASCADE,
    rule_key        VARCHAR(100) NOT NULL,
    severity        VARCHAR(20),            -- blocker / warning / info
    message         TEXT,
    evidence        JSONB,                  -- 引用嘅 extraction / chunk id
    resolved_at     TIMESTAMPTZ,
    resolved_by     BIGINT REFERENCES users(id)
);

-- 審計軌跡（合規要求）
CREATE TABLE audit_log (
    id              BIGSERIAL PRIMARY KEY,
    case_id         BIGINT,
    actor_type      VARCHAR(20),            -- user / system / llm
    actor_id        BIGINT,
    action          VARCHAR(100),
    payload         JSONB,
    created_at      TIMESTAMPTZ DEFAULT now()
);
```

---

## 3. Pipeline 分階段實作

### Stage 1 — Ingest

**輸入**: PDF / JPG / PNG / DOCX
**輸出**: 每頁純文字 + bbox 座標，寫入 `chunks`

處理分支：

| 文件狀態 | 處理方式 |
|---|---|
| Text-layer PDF | `pdftotext` / `spatie/pdf-to-text` 直接抽 |
| 掃描 PDF / 圖片 | 送 OCR service |
| DOCX | `phpoffice/phpword` 抽文字 |

**OCR 選型**

- **起步**: Azure Document Intelligence — 對表格、表單欄位辨識好過通用 OCR，有 prebuilt models（invoice / receipt / ID）。按頁收費，MVP 階段成本可控。
- **日後自建**: PaddleOCR（中英俱佳）或 docTR，包成 FastAPI service，Laravel 用 HTTP 呼叫。

**分塊策略**

- 表單類文件（NHS 轉介表、資助申請表）：**按 section 分塊**，唔好用固定 token 長度切，否則欄位同標籤會被切散
- 敘述類文件（案件紀錄、合約）：500–800 token，overlap 100 token
- 每個 chunk 保留 `page_number` 同 `bbox`，UI 先可以做 highlight

### Stage 2 — Classify

判斷 `doc_type`。兩層做法：

1. **快速路徑** — 檔名 pattern + 關鍵字規則（例如出現「NHS」「Referral」「GP Practice」→ 高機會係轉介表）。命中就唔使叫 LLM，慳成本。
2. **LLM 路徑** — 快速路徑唔確定先用。只送首 1–2 頁，唔好送成份文件。

```
System: 你係文件分類器。只輸出 JSON，冇任何前後文字。
可選類型: {vertical 嘅 doc_type 清單}
輸出格式: {"doc_type": "...", "confidence": 0.0-1.0, "reason": "..."}
```

信心值低於 0.7 → flag 出嚟俾人手確認，唔好靜靜地估。

### Stage 3 — Extract

核心係 **schema-driven extraction**。每個 vertical 定義自己嘅 field schema：

```php
// config/schemas/nhs_referral.php
return [
    'doc_types' => ['referral_form', 'gp_letter', 'test_report', 'waiting_list_record'],
    'fields' => [
        'patient_nhs_number' => [
            'type' => 'string',
            'pattern' => '/^\d{3}\s?\d{3}\s?\d{4}$/',
            'required' => true,
            'source_doc_types' => ['referral_form'],
        ],
        'referral_date' => [
            'type' => 'date',
            'required' => true,
        ],
        'referring_clinician' => [
            'type' => 'string',
            'required' => true,
        ],
        'urgency' => [
            'type' => 'enum',
            'options' => ['routine', 'urgent', 'two_week_wait'],
            'required' => true,
        ],
        'specialty' => ['type' => 'string', 'required' => true],
        'supporting_tests' => ['type' => 'array', 'required' => false],
    ],
];
```

**抽取 prompt 模板**

```
System:
你係文件資料抽取器。根據提供嘅文件片段，抽取指定欄位。
規則：
- 只輸出 JSON，冇 markdown fence，冇解釋
- 文件冇提及嘅欄位，值設為 null，唔好推測
- 每個欄位要標明信心值同來源片段編號
- 日期一律轉成 YYYY-MM-DD

需抽取欄位:
{schema fields JSON}

輸出格式:
{"fields": {"<key>": {"value": ..., "confidence": 0.0-1.0, "source_chunk": <id>}}}

User:
{相關 chunks，每個前面標 [chunk_id: N]}
```

**關鍵實作點**

- **絕對唔好一次過送成份文件**。用 schema 嘅 `source_doc_types` 先過濾相關文件，再用 embedding 檢索最相關嘅 chunks（top-k 8–12）。
- 抽出嚟嘅值要**過 pattern 驗證**（NHS number 格式、日期合理性、金額範圍）。驗證唔過 → confidence 降級 + flag。
- 用 OpenAI 嘅 structured output（`response_format: json_schema`）保證格式，比純 prompt instruction 可靠好多。

### Stage 4 — Rule Check

**呢層唔用 LLM。** 用確定性規則引擎。

```php
// config/rules/nhs_referral.php
return [
    'missing_nhs_number' => [
        'severity' => 'blocker',
        'check' => fn($c) => empty($c->field('patient_nhs_number')),
        'message' => '轉介表缺少 NHS number，無法配對病人紀錄。',
    ],
    'two_week_wait_no_tests' => [
        'severity' => 'blocker',
        'check' => fn($c) => $c->field('urgency') === 'two_week_wait'
                          && empty($c->field('supporting_tests')),
        'message' => '2WW 轉介必須附相關檢查報告。',
    ],
    'referral_stale' => [
        'severity' => 'warning',
        'check' => fn($c) => $c->field('referral_date')
                          && now()->diffInDays($c->date('referral_date')) > 42,
        'message' => '轉介日期超過 6 星期，需確認等候時間狀態。',
    ],
    'missing_gp_details' => [
        'severity' => 'warning',
        'check' => fn($c) => empty($c->field('referring_clinician')),
        'message' => '缺少轉介醫生資料。',
    ],
];
```

規則寫成 config，非工程人員（例如 domain expert 客戶）可以參與定義。呢個係產品差異化嘅關鍵 — 客戶自己可以調規則。

### Stage 5 — Summarise

到呢一步先叫 LLM 寫 summary，而且**只餵已驗證嘅結構化資料 + findings**，唔好再餵原文。咁樣幻覺風險最低。

```
System:
根據以下已驗證嘅個案資料同檢查結果，寫一份俾 caseworker 睇嘅摘要。
要求：
- 200 字以內
- 先講 blocker，再講 warning，最後講整體狀態
- 唔好加入資料以外嘅推論
- 每個論點後面標 [欄位名] 以便追溯

個案資料: {extractions JSON}
檢查結果: {findings JSON}
```

---

## 4. Vertical 實作優先次序

| 順序 | Vertical | 理由 |
|---|---|---|
| 1 | **Financial Evidence Copilot** | 文件類型最標準化（月結單、稅單、糧單），欄位定義清晰，最容易做出可 demo 嘅準確度。金融/會計亦係你哋已有嘅目標客群。 |
| 2 | **NHS Referral Copilot** | 表單結構固定，completeness check 價值明顯。但要注意病人資料合規（UK GDPR / NHS DSP Toolkit），需要準備 data residency 方案。 |
| 3 | **Council Casework Copilot** | 文件最雜（信件、租約、福利通知、債務文件），難度高但痛點最大。有前兩個 vertical 打底先做。 |
| 4 | Farm / Energy / Creative Rights | 待前三個驗證框架後再擴展。Energy 嗰個其實係時序數據分析多過文件分析，可能需要另一套 pipeline。 |

**每個 vertical 落地所需**：

- 20–50 份真實（或去識別化）樣本文件
- 一份 field schema
- 10–25 條規則
- 一組 golden test set（人手標註正確答案，用嚟量度準確度）

---

## 5. 開發階段規劃

### Phase 1 — 核心 Pipeline（2–3 週）

- [ ] DB schema + migration
- [ ] 檔案上傳 + 儲存（S3 或本地）
- [ ] PDF text-layer 抽取
- [ ] Chunking + embedding 入 pgvector
- [ ] 單一 vertical（Financial）嘅 schema + extraction
- [ ] 基本 rule engine
- [ ] 最簡 UI：上傳 → 睇結果

**Phase 1 出口條件**：一份真實貸款文件包上傳後，能正確抽出 8 個核心欄位，準確率 > 85%。

### Phase 2 — 生產可用（3–4 週）

- [ ] OCR 整合（掃描文件）
- [ ] 分類階段
- [ ] Citation highlight（點欄位跳去原文位置）
- [ ] 人手覆核 / 修正流程
- [ ] Audit log
- [ ] 匯出報告（PDF / DOCX）
- [ ] Golden test set + 自動評估腳本

### Phase 3 — 多 Vertical + 成本優化（持續）

- [ ] 第二、三個 vertical
- [ ] 規則編輯 UI（俾客戶自己改）
- [ ] 評估自建模型（見下節）

---

## 6. 模型策略 — API 起步，按數據決定是否自建

**Phase 1–2 一律用 API**（`gpt-4o-mini` 做抽取，`text-embedding-3-small` 做 embedding）。理由：

- 未知準確度基準之前 fine-tune 係浪費
- 未有足夠訓練樣本
- MVP 階段速度比成本重要

**幾時先考慮自建 Qwen + LoRA**：

同時滿足以下條件先值得投入：

1. 月 API 開支超過 GPU 成本（雲端 A10G/4090 約 US$300–600/月）
2. 某個 vertical 嘅抽取任務已經穩定重複，累積到 500+ 條人手覆核過嘅樣本
3. 有客戶明確要求資料唔可以出境（NHS / council 好可能會提呢個要求 —— 呢個可能係最先觸發自建嘅原因，唔係成本）

**自建路徑**（到時先做）：

- Base model: Qwen3.6（Apache 2.0，商用最安全）
- 訓練框架: LLaMA-Factory 或 Unsloth
- 訓練資料: 由 `extractions` 表直接生成 —— 人手覆核過嘅記錄天然就係 instruction-response pair。呢個係點解要喺 Phase 2 就做好覆核流程嘅原因。
- 部署: vLLM + adapter 熱載入

---

## 7. 合規注意事項（英國市場）

呢啲 vertical 全部踩到敏感資料，落地前必須處理：

- **UK GDPR** — 資料處理法律依據、保留期限、被遺忘權
- **NHS 相關** — Data Security and Protection Toolkit；病人資料原則上唔應該送去第三方 LLM API，除非有明確 DPA 同 data residency 保證（OpenAI 有 EU data residency 選項，需確認條款）
- **Council casework** — 涉及弱勢群體資料，通常要求 UK-based hosting
- **金融文件** — FCA 相關要求視乎用途；若只做內部分析工具風險較低
- **去識別化** — 建議喺送去 LLM 之前做 PII redaction（姓名、地址、NHS number 替換成 token），回來再還原。呢個可以大幅降低合規門檻。

> 以上係一般性資訊，唔構成法律意見。落實前建議搵熟悉 UK data protection 嘅律師確認，尤其係 NHS 同 council 呢兩個 vertical。

---

## 8. 評估方法

冇評估等於冇產品。每個 vertical 準備 golden set：

| 指標 | 定義 | 目標 |
|---|---|---|
| Field accuracy | 抽對嘅欄位 / 總欄位 | > 92% |
| Field recall | 文件有但抽唔到嘅比率 | 漏抽 < 5% |
| Hallucination rate | 文件冇但抽咗出嚟 | < 1%（呢個最緊要） |
| Rule precision | flag 出嚟真係有問題嘅比率 | > 90% |
| End-to-end latency | 10 頁文件包處理時間 | < 60 秒 |

**Hallucination rate 係最關鍵指標**。喺呢啲領域，抽錯一個數字比抽唔到更危險 —— 抽唔到人手會補，抽錯咗人手唔會發現。

---

## 9. 多媒體 AI（影片 / 2D / 3D）

呢部分同上面嘅文件 pipeline 唔應該同期做，運算成本同技術路線差幾個量級。建議：

- **短期**: 用 API 驗證需求（影片 → Runway / Kling；3D → Meshy / Tripo）
- **2D 素材** 係唯一可以早期自建嘅部分：FLUX 或 SD3.5 + LoRA，單張 24GB GPU 可行，用 ComfyUI 編排
- **影片 / 3D 自建** 需要 A100/H100 級別硬件，除非有明確客戶付費需求，否則唔建議喺 pivot 初期投入

詳細 setup 另開文件。

---

## 10. 立即可做嘅三件事

1. 搵 20–30 份真實（去識別化）金融證明文件，人手標註出你想抽嘅欄位 —— 呢個 golden set 係之後所有工作嘅基準
2. 起 DB schema + 最簡 ingest → extract → 顯示嘅垂直切片，唔理 UI 靚唔靚
3. 攞第一版準確度數字出嚟，先再決定投唔投入落去
