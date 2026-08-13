# Self-hosted model

The `qwen_lora` provider in `config/providers.php` talks to Ollama over its
OpenAI-compatible endpoint. Nothing here is required for the hosted providers.

## Build the model

```
ollama create coreagent-qwen -f ollama/Modelfile
```

Then in `.env`:

```
QWEN_LORA_BASE_URL=http://127.0.0.1:11434/v1
QWEN_LORA_MODEL=coreagent-qwen
```

`QWEN_LORA_BASE_URL` is the on/off switch — blank means the provider is
unconfigured and `ProviderRouter` skips it, so it never appears in the model
dropdown.

## Adding a real LoRA

`Modelfile` has an `ADAPTER` line ready but commented out, because an adapter
is a trained artefact this repo does not contain. To get one:

1. Assemble a dataset of the behaviour you want that a stock model gets wrong.
   A few hundred examples is the realistic floor; below that, a system prompt
   does the same job for none of the cost.
2. Fine-tune against `Qwen2.5-3B` (Unsloth, axolotl, or peft directly).
3. Convert the adapter to GGUF — `convert_lora_to_gguf.py` in llama.cpp.
4. Drop it at `ollama/adapters/coreagent-lora.gguf`, uncomment `ADAPTER`, and
   rebuild.

Until step 4, `coreagent-qwen` is stock Qwen with coreAgent's context size and
sampling settings. That is a customised model, not a fine-tuned one — worth
being precise about, since the provider key is named `qwen_lora`.

## Tool calling

`QWEN_LORA_SUPPORTS_TOOLS` defaults to `false`.

Measured, not assumed: given the four real tool schemas and the prompt
「而家有幾多個 case？」, `qwen2.5:3b` emitted a structurally valid `list_cases`
call. So the wire format is not the problem. What it also did was narrate the
tool it was about to use into the reply, and drift out of Cantonese into
Mandarin and simplified characters partway through — the user ends up reading
the plumbing instead of an answer.

That is a quality ceiling, not a bug to fix in the adapter. Turn tools on per
model tag once you have tested that tag, and use 7B or larger if tool use is
actually part of the product rather than a demo.

## Exposing it to the deployed site

Ollama has no authentication of its own. Anything reachable from the internet
needs a credential checked in front of it — Cloudflare Access, an nginx
`auth_request`, or a Tailscale ACL. `QWEN_LORA_API_KEY` rides in the
`Authorization` header and `QWEN_LORA_CF_ACCESS_CLIENT_ID` /
`QWEN_LORA_CF_ACCESS_CLIENT_SECRET` in Cloudflare's header pair, but the
provider only sends them: something else has to verify them.
