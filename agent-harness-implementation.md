# AI Agent Harness — Implementation Plan

**版本**: v0.1
**目標**: 建立一套 agent harness，令同一個 project 上任何一個 AI agent session 都可以被另一個 agent 無縫接手 — 包括狀態、決策、失敗紀錄、環境指紋，以及 session 內嘅密鑰（經加密封裝交接）。
**Stack**: Laravel 12 / PHP 8.2 / `ext-sodium`（已確認可用）

---

## 0. 設計原則

1. **JSON 係 source of truth，MD 係 render 出嚟嘅** — 兩份文件唔可以各自寫。人睇 MD，機器讀 JSON，MD 由 JSON 生成。兩者分叉係呢類系統最常見嘅腐爛方式。
2. **State 分兩層：append-only event log + materialized snapshot** — log 做審計同 replay，snapshot 做交接。接手 agent 讀 snapshot，唔使 replay 成個 log。
3. **Secret 唔入 harness，只入 reference** — 除咗明確標記為 ephemeral 嘅 session token。分 tier 處理（見 §4.1）。
4. **加密用信封制，唔用共享密碼** — 每個 agent identity 一對 X25519 keypair，私鑰永遠唔離開該 agent 嘅 profile 目錄。交接 = 用收件人公鑰重新封裝 DEK，唔係傳私鑰。
5. **交接要可驗證，唔止可讀** — schema validation + Ed25519 簽名 + hash chain + 環境指紋比對。接手 agent 要有能力講「呢份 handover 唔啱，唔接」。
6. **失敗紀錄同下一步同樣重要** — `failed_attempts[]` 同 `next_actions[0]` 先係「無縫」嘅真正來源。冇咗佢，接手 agent 會重行一次死路。
7. **Harness 唔可以要求 agent 自律** — 靠 Claude Code hooks 自動觸發寫入，唔靠 prompt 叫佢「記得更新 JSON」。

---

## 1. 點解要 JSON，MD 唔夠

| 需求 | MD | JSON |
|---|---|---|
| 人類覆核 / PR review | ✅ | ❌ |
| 機器 parse 唔會 ambiguous | ❌ | ✅ |
| Schema 驗證（缺欄位即刻 fail） | ❌ | ✅ |
| Diff 有語意（邊個 field 變咗） | ❌ | ✅ |
| 加密 payload 嵌入 | ❌ | ✅ |
| 簽名 / hash chain（要 canonical 序列化） | ❌ | ✅ |
| LLM 讀嘅 token 效率 | 中 | 高（結構化） |

結論：JSON 做 harness，MD 做 `agent:session:render` 出嚟嘅副產品。**MD 檔案頂部要有 `<!-- GENERATED — do not edit, source: .agent/sessions/<id>/state.json -->`**。

---

## 2. 檔案結構

```
.agent/                                  ← 入 repo（除咗 keys/）
├── harness.json                         ← Harness manifest：project invariants、agent roster、policy
├── agents.json                          ← Agent 身份註冊表（公鑰 only）
├── schema/
│   ├── harness.schema.json
│   ├── session-state.schema.json
│   ├── handover.schema.json
│   └── agents.schema.json
├── sessions/
│   └── 2026-08-12T0930Z-a3f9/
│       ├── state.json                   ← Materialized snapshot（交接讀呢個）
│       ├── events.ndjson                ← Append-only event log
│       ├── session.md                   ← Generated，人睇
│       └── secrets.sealed.json          ← 加密 secret bundle（見 §4）
├── handover/
│   ├── HEAD                             ← 指向最新 handover envelope 檔名
│   └── 2026-08-12T1145Z-a3f9-to-b7c2.json
└── lease.json                           ← 寫入鎖，防止兩個 agent 同時寫

C:\ClaudeProfiles\<agent-id>\            ← 唔入 repo，唔可以入
├── identity.box.key                     ← X25519 私鑰（加密用）
└── identity.sign.key                    ← Ed25519 私鑰（簽名用）
```

`.gitignore` 追加：
```gitignore
.agent/lease.json
.agent/sessions/*/events.ndjson.lock
```

> **注意**：`secrets.sealed.json` 係故意入 repo 嘅 — 佢係密文，冇對應私鑰解唔開。但如果你想更保守，可以放去 `storage/agent-secrets/` 並 gitignore，靠 handover envelope 內嵌傳遞。Phase 2 決定，兩個都支援。

---

## 3. Schema 定義

### 3.1 `harness.json` — Harness manifest

整個 project 只有一份，講明「呢個 project 嘅 agent 要遵守咩」。

```json
{
  "schema_version": "1.0.0",
  "project": {
    "id": "coreagent",
    "root": "C:/Coreword/coreAgent",
    "stack": ["laravel:12", "php:8.2", "vue:3", "inertia:2", "tailwind"],
    "vcs": { "type": "none", "note": "未 init git — 見 §6.3 fallback" }
  },
  "invariants": [
    "唔可以直接改 vendor/ 同 node_modules/",
    "所有 DB schema 改動一律經 migration，唔可以手改 sqlite",
    "唔可以 commit .env",
    "測試指令：composer test"
  ],
  "policy": {
    "handover_requires_signature": true,
    "handover_requires_clean_worktree": false,
    "secret_tiers_allowed_in_envelope": ["B", "C"],
    "dek_rotation": "every_handover",
    "lease_ttl_seconds": 5400,
    "break_glass_recipients": ["human:rickchow"]
  },
  "render": {
    "session_md_template": "resources/views/agent/session.blade.php"
  }
}
```

### 3.2 `agents.json` — 身份註冊表（只有公鑰）

```json
{
  "schema_version": "1.0.0",
  "agents": [
    {
      "id": "agent:claude-opus-5:a3f9",
      "display_name": "Opus 5 — main dev",
      "model": "claude-opus-5",
      "harness": "claude-code",
      "box_public_key": "base64:...",
      "sign_public_key": "base64:...",
      "created_at": "2026-08-12T09:30:00Z",
      "revoked_at": null
    },
    {
      "id": "human:rickchow",
      "display_name": "Rick (break-glass)",
      "box_public_key": "base64:...",
      "sign_public_key": "base64:...",
      "revoked_at": null
    }
  ]
}
```

**`human:rickchow` 一定要係每個 DEK 嘅 recipient。** 冇咗佢，任何一個 agent profile 目錄爆咗 = 全部 session secret 永久攞唔返。

### 3.3 `state.json` — Session snapshot（核心）

呢個係接手 agent 唯一必讀嘅檔案。

```json
{
  "schema_version": "1.0.0",
  "session_id": "2026-08-12T0930Z-a3f9",
  "agent_id": "agent:claude-opus-5:a3f9",
  "status": "active",
  "started_at": "2026-08-12T09:30:00Z",
  "updated_at": "2026-08-12T11:44:12Z",
  "event_count": 87,

  "objective": "為 Document Copilot 加 multi-vertical schema loader",
  "scope": {
    "in": ["app/Services/Extraction/*", "config/verticals/*"],
    "out": ["前端 UI — 另一個 session 處理", "OCR service"]
  },
  "constraints": [
    "唔可以改 extractions table schema（有 production data）"
  ],

  "work_items": [
    {
      "id": "WI-1",
      "title": "SchemaLoader 支援 per-vertical override",
      "status": "done",
      "acceptance": "tests/Feature/SchemaLoaderTest.php 全綠"
    },
    {
      "id": "WI-2",
      "title": "Rule engine 讀 vertical rule set",
      "status": "in_progress",
      "blocked_by": [],
      "acceptance": "council_casework fixture 出 3 個 finding"
    }
  ],

  "decisions": [
    {
      "id": "D-1",
      "at": "2026-08-12T10:12:00Z",
      "decision": "Vertical config 用 PHP array 唔用 YAML",
      "rationale": "config:cache 可以 serialize，YAML 要額外 parse + 唔入 cache",
      "alternatives_rejected": ["symfony/yaml", "JSON in DB"],
      "reversible": true
    }
  ],

  "failed_attempts": [
    {
      "at": "2026-08-12T10:40:00Z",
      "attempted": "用 Laravel config repository 做 vertical 熱切換",
      "why_failed": "config() 係 process-wide singleton，queue worker 跨 job 會漏狀態",
      "do_not_retry": true
    }
  ],

  "artifacts": [
    {
      "path": "app/Services/Extraction/SchemaLoader.php",
      "sha256": "…",
      "role": "primary",
      "status": "modified"
    }
  ],

  "environment": {
    "php": "8.2.12",
    "node": "20.11.0",
    "composer_lock_sha256": "…",
    "package_lock_sha256": "…",
    "migrations_head": "2026_07_30_120000_add_findings_table",
    "services": [
      { "name": "queue:listen", "state": "running", "port": null },
      { "name": "vite", "state": "stopped", "port": 5173 }
    ]
  },

  "secrets": [
    {
      "key_id": "OPENAI_API_KEY",
      "tier": "A",
      "ref": "env://.env#OPENAI_API_KEY",
      "sealed": false
    },
    {
      "key_id": "AZURE_DI_SESSION_TOKEN",
      "tier": "B",
      "ref": "sealed://secrets.sealed.json#AZURE_DI_SESSION_TOKEN",
      "sealed": true,
      "expires_at": "2026-08-12T13:00:00Z"
    }
  ],

  "open_questions": [
    "Farm vertical 嘅 rule set 由邊度攞？未有 spec。"
  ],

  "next_actions": [
    { "order": 1, "action": "跑 composer test，確認 WI-1 未 regress" },
    { "order": 2, "action": "喺 RuleEngine::load() 加 vertical 參數，參考 SchemaLoader:44 嘅寫法" }
  ],

  "prev_handover_hash": null
}
```

**設計要點**：
- `next_actions[1]` 必須具體到「開邊個檔、改邊行」。寫「繼續做 WI-2」等於冇寫。
- `failed_attempts[].do_not_retry` 係 harness 唯一一個會令接手 agent **唔做**某件事嘅欄位，要當 first-class。
- `environment` 嘅 hash 用嚟偵測 drift：接手時 `composer_lock_sha256` 唔同 = 有人 install 咗嘢，state 可能過時。

### 3.4 `handover/*.json` — Handover envelope

```json
{
  "schema_version": "1.0.0",
  "handover_id": "2026-08-12T1145Z-a3f9-to-b7c2",
  "from_agent": "agent:claude-opus-5:a3f9",
  "to_agent": "agent:claude-opus-5:b7c2",
  "created_at": "2026-08-12T11:45:00Z",
  "reason": "context_limit",

  "session_ref": ".agent/sessions/2026-08-12T0930Z-a3f9/state.json",
  "state_sha256": "…",
  "prev_handover_hash": "blake2b:…",

  "environment_fingerprint": {
    "composer_lock_sha256": "…",
    "package_lock_sha256": "…",
    "worktree_manifest_sha256": "…"
  },

  "dek": {
    "alg": "XChaCha20-Poly1305-IETF",
    "kdf": "none",
    "generation": 3,
    "wrapped_for": [
      { "recipient": "agent:claude-opus-5:b7c2", "sealed_box": "base64:…" },
      { "recipient": "human:rickchow",           "sealed_box": "base64:…" }
    ]
  },

  "signature": {
    "alg": "Ed25519",
    "by": "agent:claude-opus-5:a3f9",
    "value": "base64:…",
    "over": "canonical-json(envelope minus .signature)"
  }
}
```

---

## 4. 加密設計

### 4.1 Secret tiering — 最重要嘅一步

| Tier | 例子 | Harness 入面點處理 |
|---|---|---|
| **A — 長期憑證** | `OPENAI_API_KEY`、DB password、production API key | **只存 reference**（`env://`、`vault://`）。永遠唔入 envelope。接手 agent 自己由同一個來源讀。 |
| **B — 短期 token** | OAuth access token、pre-signed URL、臨時 service token | 可以封裝入 envelope，**必須有 `expires_at`**。過期即失效，洩漏窗口有限。 |
| **C — 非機密設定** | endpoint URL、model name、feature flag | 明文 JSON。 |

> 呢個 tiering 就係我開頭嘅保留意見嘅落地：Tier A 加密咗都冇意義（收件人解得開 = 攻擊者拎到 agent profile 就解得開，而 `.env` 本身已經喺同一部機）。真正需要密碼學保護嘅係 Tier B — 佢哋唔喺 `.env`，只存在於 session 記憶入面，冇 harness 就會隨 session 一齊消失。

### 4.2 信封加密流程

```
                    ┌──────────────────────────────┐
  Tier B secret ───►│ AEAD encrypt                 │
                    │ XChaCha20-Poly1305-IETF      │
                    │ key = DEK (32 random bytes)  │
                    │ AAD = session_id|key_id|gen  │──► secrets.sealed.json
                    └──────────────┬───────────────┘
                                   │ DEK
                    ┌──────────────▼───────────────┐
                    │ sodium_crypto_box_seal(DEK,  │──► wrapped_for[incoming]
                    │   recipient_box_public_key)  │──► wrapped_for[break-glass]
                    └──────────────────────────────┘
```

**點解 AAD 要綁 `session_id|key_id|generation`**：防止有人將 session A 嘅密文 copy 落 session B 嘅 JSON 扮成另一個 key。冇 AAD 綁定，密文係可以自由搬遷嘅。

**點解用 `crypto_box_seal`（anonymous sealed box）而唔用 `crypto_box`**：sealed box 唔需要寄件人私鑰參與，即係 harness 可以離線為任何註冊咗公鑰嘅 agent 封裝 DEK，唔使兩邊同時在線。代價係收件人驗證唔到寄件人 — 所以外層一定要有 Ed25519 簽名（§4.4）。

### 4.3 DEK rotation — 每次 handover 都轉

```
handover 時：
  1. 舊 agent 用自己私鑰 unseal 舊 DEK(gen N)
  2. 解開所有 Tier B secret
  3. 生成新 DEK(gen N+1)
  4. 用新 DEK 重新加密（AAD 用新 generation）
  5. 新 DEK 只 seal 俾：接手 agent + break-glass
  6. 舊 agent 嘅 wrap 唔再寫入
```

效果：舊 agent（或者攞到舊 agent profile 嘅人）解唔到 handover 之後嘅內容。呢個係 forward secrecy，值得每次都做 — rotation 成本係毫秒級。

### 4.4 簽名同 canonical JSON

Hash 同簽名要 byte-stable，所以要定死序列化規則：

- Key 按 UTF-8 code point 升序排
- 冇多餘空白（`JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`，冇 `PRETTY_PRINT`）
- 數字唔用科學記數法，float 一律轉 string 存
- `null` 欄位保留，唔可以 drop
- 簽名時將 `.signature` 整個 key 移除，唔係設做 `null`

寫成一個 class：`App\Agent\Harness\CanonicalJson::encode(array $data): string`。**呢個 class 一旦有人「順手改靚啲」，所有歷史簽名即刻全部驗唔到**，所以要有 golden-file 測試鎖死輸出。

### 4.5 私鑰儲存

- 路徑：`C:\ClaudeProfiles\<agent-id>\identity.{box,sign}.key`
- 格式：raw bytes，base64
- Windows ACL：只有 owner 可讀（`icacls <file> /inheritance:r /grant:r "$env:USERNAME:(R)"`）
- **絕對唔可以出現喺 `.agent/` 底下**。Phase 1 就要加一個 guard：`agent:harness:doctor` 掃描 `.agent/` 有冇 base64 blob 長度 = 44（32 bytes 私鑰嘅特徵），有就 fail。

---

## 5. Handover protocol — 三階段 + lease

單純「寫份 JSON 就走」會出現兩個 agent 同時寫、或者接手 agent 讀到寫到一半嘅 state。用 lease + 三階段：

```
 舊 agent                          .agent/lease.json                 新 agent
    │                                     │                              │
    │─ prepare ──────────────────────────►│                              │
    │  • flush event log → state.json     │                              │
    │  • rotate DEK, seal 俾 to_agent     │                              │
    │  • 寫 handover envelope + 簽名       │                              │
    │  • lease.status = "handing_over"    │                              │
    │                                     │                              │
    │                                     │◄──────────── claim ──────────│
    │                                     │  • schema validate           │
    │                                     │  • 驗簽名（用 agents.json）   │
    │                                     │  • 驗 prev_handover_hash 鏈   │
    │                                     │  • 驗 environment 指紋        │
    │                                     │  • unseal DEK，試解一個 secret│
    │                                     │  ↑ 任何一步 fail → 唔 commit  │
    │                                     │                              │
    │                                     │◄─────────── commit ──────────│
    │                                     │  lease.holder = to_agent     │
    │                                     │  HEAD → 新 envelope           │
```

**Lease 語意**：
- `lease.json` = `{ holder, session_id, acquired_at, expires_at, status }`
- TTL 由 `harness.json.policy.lease_ttl_seconds` 定（預設 90 分鐘）
- 過期 lease 可以被 `agent:harness:claim --force` 搶，但會寫一條 `lease_stolen` event 入 log
- 寫入用 atomic rename（寫 `.tmp` → `rename()`），唔用 in-place 覆蓋

**Claim 驗證 fail 嘅處理**：唔係靜靜地繼續。接手 agent 要向用戶報告具體邊一項 fail，例如「composer.lock 指紋唔同，state 入面嘅 dependency 假設可能已失效」，等人決定係咪 `--accept-drift`。

---

## 6. CLI surface（Artisan commands）

```bash
# 身份
php artisan agent:identity:init --id="agent:claude-opus-5:a3f9"   # 生成 keypair，寫入 profile + 註冊公鑰
php artisan agent:identity:list

# Session 生命週期
php artisan agent:session:start --objective="..." 
php artisan agent:session:event --type=decision --data='{...}'    # append 落 events.ndjson
php artisan agent:session:snapshot                                 # events → state.json
php artisan agent:session:render                                   # state.json → session.md
php artisan agent:session:end

# Secret
php artisan agent:secret:put AZURE_DI_SESSION_TOKEN --tier=B --ttl=3600
php artisan agent:secret:get AZURE_DI_SESSION_TOKEN                # 需要持有 lease
php artisan agent:secret:list                                      # 只列 key_id + tier，唔出值

# Handover
php artisan agent:handover:prepare --to="agent:claude-opus-5:b7c2" --reason=context_limit
php artisan agent:handover:claim  --as="agent:claude-opus-5:b7c2"
php artisan agent:handover:commit --as="agent:claude-opus-5:b7c2"
php artisan agent:handover:verify <handover-id>                    # 唯讀，任何人可跑

# 健康檢查
php artisan agent:harness:doctor                                   # schema、hash chain、私鑰洩漏、lease 過期
```

命名空間放喺 `app/Console/Commands/Agent/`，核心邏輯放 `app/Agent/Harness/`（唔放 Service，因為佢完全唔依賴 HTTP layer）：

```
app/Agent/Harness/
├── CanonicalJson.php
├── SchemaValidator.php
├── EventLog.php
├── Snapshotter.php
├── Lease.php
├── Identity/
│   ├── IdentityStore.php        ← 讀寫 profile 私鑰
│   └── AgentRegistry.php        ← 讀 agents.json
├── Crypto/
│   ├── Envelope.php             ← DEK seal / unseal
│   ├── SecretBundle.php         ← AEAD encrypt / decrypt + AAD 綁定
│   └── Signer.php               ← Ed25519
└── Handover/
    ├── Preparer.php
    ├── Claimer.php
    └── Verifier.php
```

---

## 7. Claude Code hooks 整合

呢個 project 未有 `.claude/`，要新建 `.claude/settings.json`。Hooks 係令 harness 唔靠 agent 自律嘅關鍵：

| Hook | 做咩 |
|---|---|
| `SessionStart` | 跑 `agent:harness:doctor`；如果 `handover/HEAD` 有未 claim 嘅 envelope，即刻提示接手；否則 `agent:session:start` |
| `PostToolUse` (Edit/Write) | append 一條 `artifact_touched` event（path + 新 sha256） |
| `PreCompact` | **強制** `agent:session:snapshot` — context 壓縮前一定要落盤，否則 compaction 之後嘅 agent 會記錯 |
| `Stop` | `agent:session:snapshot` + `agent:session:render` |
| `SessionEnd` | 如果 lease 仲喺手 → `agent:handover:prepare --to=unassigned`，令下一個 agent 接得到 |

`--to=unassigned` 嘅情況下，DEK 只 seal 俾 break-glass recipient；下一個 agent 起 session 時要人手 re-seal（`agent:handover:reseal --to=<new-agent>`，由 break-glass 身份執行）。呢個係故意嘅摩擦 — 冇指定收件人就自動封裝俾任何人，等於冇加密。

---

## 8. 實作階段

| Phase | 內容 | Acceptance criteria |
|---|---|---|
| **0** ✅ | `.agent/` 骨架、JSON Schema、`CanonicalJson` + golden-file 測試 | ✅ `CanonicalJson::encode()` 跑 1000 次 byte-identical；golden file 對得上（見 §8.1） |
| **1** | `EventLog`、`Snapshotter`、`agent:session:*`、Blade render | 由 50 條 event 生成 `state.json` 通過 schema 驗證；`session.md` 有 GENERATED header |
| **2** | Crypto layer：`Identity`、`Envelope`、`SecretBundle`、`Signer` | round-trip 測試通過；**負面測試**：改一個 byte 密文 → decrypt throw；改 AAD 嘅 session_id → decrypt throw；改 envelope 一個 field → 驗簽 fail |
| **3** | `Lease` + handover 三階段 + hash chain | 兩個並行 process 搶 lease，只有一個成功；chain 中間插一份偽造 envelope → `verify` fail |
| **4** | `agent:harness:doctor`、drift 偵測、私鑰洩漏掃描 | 故意將私鑰 copy 入 `.agent/` → doctor exit code ≠ 0 |
| **5** | `.claude/settings.json` hooks 接線 | 真做一次 session：改 3 個檔 → PreCompact → 新 session claim → 新 agent 讀到 `next_actions` 同解到 Tier B secret |
| **6** | 文件 + `composer test` 加入 harness 測試 | `composer test` 全綠；README 加 harness 章節 |

Phase 0–2 係硬骨頭（crypto 同 canonical 序列化錯咗好難補救），Phase 3–5 相對機械。建議 0–2 做完先做一次真實 dry run 再繼續。

### 8.1 Phase 0 — 完成紀錄

**交付**

- `.agent/` 骨架：`harness.json`、`agents.json`（空 roster）、`handover/HEAD`、`sessions/`、`README.md`
- **5** 份 schema（唔係原定 4 份，見下）：`common` / `harness` / `agents` / `session-state` / `handover`
- `.agent/tools/json-lint.php` — 唔靠 Laravel bootstrap，hook 同 CI 喺 `composer install` 之前都用得
- `app/Agent/Harness/CanonicalJson.php` + `CanonicalJsonException.php`
- `tests/Unit/Agent/CanonicalJsonTest.php`（19 test / 36 assertion）+ 手寫 golden fixture
- `.gitignore` 加咗 harness runtime state 同 `*.key` backstop

**驗收結果**

- 1000 次 encode byte-identical ✅
- Golden file 由人手獨立寫，**第一次跑就對中**，唔係由 encoder 生成（自己驗自己等於冇驗）✅
- 全 suite `composer test` 50 passed ✅
- **Mutation test** — 故意改壞 encoder 三次，確認 golden test 真係會 fail：
  | Mutation | 結果 |
  |---|---|
  | key 改用數字排序 | 2 failures ✅ |
  | `\uXXXX` 改大階 hex | 2 failures ✅ |
  | forward slash 改成要 escape | 2 failures ✅ |

  三次之後 source 還原，`diff` 確認 bit-identical。

**同原計劃嘅偏差**

1. **加咗 `common.schema.json`** — `agentId` / `timestamp` / `sha256` / `secretTier` 等 primitive 喺 4 份 schema 入面重複太多次，重複定義遲早會分叉。
2. **`relPath` 唔再用 regex 擋 backslash** — schema regex 做 path safety 本身就太弱，而且會令 schema 檔本身充滿 escape。改為留返 Phase 1 嘅 `PathGuard` 做，schema 只擋最明顯嘅情況。
3. **Float 一律 reject，唔係轉 string** — 原文寫「float 轉 string 存」，實作改成 encoder 直接 throw。原因：如果 encoder 幫手轉，兩個唔同 float 可能轉出同一個 string，兩份唔同 document 就會撞同一個 hash。改為要求 caller 事前決定點表示，錯誤即刻浮面。
4. **`[]` vs `{}` 嘅已知不對稱** — PHP 分唔到，`json_decode(assoc:true)` 之後 `{}` 會變 `[]`。緩解唔靠 code，靠結構：`.agent/schema/*.json` 每個 object 都有 `required`，所以空 object 根本 validate 唔過，永遠去唔到簽名嗰步。已寫入 class docblock。

**仲未做（Phase 0 範圍外）**

`git init` 仍然未做。`.gitignore` 已寫定，但未有 VCS 之前 harness 產生嘅改動 rollback 唔到。

---

## 9. 已知風險同對策

| 風險 | 對策 |
|---|---|
| ~~呢個 repo 唔係 git~~ ✅ 已解決 | 2026-08-13 `git init`，origin `Coreword/coreagent`，default branch `main`。`worktree_manifest_sha256` **保留**，唔係俾 commit SHA 取代 — commit SHA 睇唔到未 commit 嘅 working-tree drift，而 agent 交接嗰刻通常就係喺呢個狀態。Phase 3 要喺 `environment_fingerprint` 加多個 `git_commit` field，兩者並存 |
| Canonical JSON 被人改動 | Golden-file 測試 + 檔頭加 `@internal — 改動會令所有歷史簽名失效` |
| Agent profile 目錄遺失 | Break-glass recipient 強制存在；`doctor` 檢查每個 DEK 至少有 2 個 recipient |
| Secret 誤入 Tier C | `agent:secret:put` 對值做 entropy heuristic（長度 ≥ 20 且 base64/hex-like）→ 唔准設 tier C，除非 `--force` |
| State 同真實 code 分叉 | `environment_fingerprint` 對唔上時 claim 唔通過，要 `--accept-drift` |
| Event log 無限膨脹 | Snapshot 之後 log 可以 rotate 去 `events.<n>.ndjson.gz`；`state.json` 保留 `event_count` 同 log segment ref |
| 兩個 agent 同時改同一份 state | Lease + atomic rename；`doctor` 偵測 orphan `.tmp` 檔 |

---

## 10. VCS 狀態

✅ 2026-08-13 完成。

- `git init`，default branch `main`
- Commit 1 `7c3c9dd` — baseline，harness 之前嘅 app（182 檔）
- Commit 2 `827d5d6` — Phase 0 harness，獨立成一個可 review 嘅 diff
- Remote `origin` → `https://github.com/Coreword/coreagent.git`
- `.gitattributes` 加咗 `tests/Fixtures/agent/canonical/* -text` — golden fixture 唔可以俾 line-ending 轉換動到，否則簽名契約會靜靜地爛

Commit 前審查過：`.env`（`.gitignore:3`）同 `database/database.sqlite`（`database/.gitignore:1`）都已被 ignore，`vendor/` 同 `node_modules/` 冇入，staged 內容掃過冇 key-shaped string。

---

## 11. 下一步

Phase 1：`EventLog`、`Snapshotter`、`agent:session:*` commands、Blade render。
