# `.agent/` — harness state

Machine-owned. Written by `php artisan agent:*` commands, not by hand.

| Path | What it is |
|---|---|
| `harness.json` | Project invariants and handover policy. The one file an agent reads before doing anything. |
| `agents.json` | Identity registry. **Public keys only** — a private key here is a critical defect. |
| `schema/` | JSON Schema for every document below. `common.schema.json` holds shared primitives. |
| `sessions/<id>/` | Per-session `events.ndjson` (append-only), `state.json` (snapshot), `session.md` (generated). |
| `handover/` | Signed envelopes plus `HEAD`, which names the newest one. |
| `tools/json-lint.php` | Framework-free JSON validation, usable from hooks and CI before `composer install`. |
| `lease.json` | Write lock. Gitignored — it is runtime state, not project state. |

Private keys live under `C:/ClaudeProfiles/<agent-id>/` and never enter this directory.

`state.json` is materialised from `events.ndjson`; the log is the record and the
snapshot is the convenience. If they disagree, the log wins.

Full design, threat model and phase plan: [`../agent-harness-implementation.md`](../agent-harness-implementation.md).
