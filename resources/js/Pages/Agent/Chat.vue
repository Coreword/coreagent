<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    conversations: Array,
    activeConversation: Object,
    availableProviders: Array,
});

const messageForm = useForm({ content: '' });

// Ported from claude-web-console (C:\Coreword\claude-web-console) — same regex,
// same "/resume [search]" convention. There it searches CLI session files; here
// it filters `conversations`, which the page already has in full (no pagination
// on that query), so no extra request is needed.
function isHistoryResumeIntent(text) {
    const normalized = text.toLowerCase().replace(/\s+/g, ' ');
    const explicitlyNegative = /(?:唔好|唔需要|不要|不用|不需|毋須|無需).{0,12}(?:load|載入|讀取|恢復|resume|搵返|找回)/i.test(normalized);
    if (explicitlyNegative) return false;

    const hasLoadAction = /(?:load|載入|讀取|恢復|resume|繼續|搵返|找回|打開|開返)/i.test(normalized);
    const hasHistoryTarget = /(?:history|歷史|過往|之前|上次|舊(?:對話|session)|session|conversation)/i.test(normalized);
    return hasLoadAction && hasHistoryTarget;
}

const resumeQuery = ref(null); // null = picker closed; '' or a search string = open

const resumeResults = computed(() => {
    if (resumeQuery.value === null) return [];
    const q = resumeQuery.value.toLowerCase();
    if (!q) return props.conversations;
    return props.conversations.filter((c) => (c.title ?? '').toLowerCase().includes(q));
});

function closeResumePicker() {
    resumeQuery.value = null;
}

function resumeConversation(id) {
    resumeQuery.value = null;
    router.visit(route('chat.show', id));
}

function sendMessage() {
    if (!props.activeConversation) return;
    const text = messageForm.content.trim();
    if (!text) return;

    if (text === '/resume' || text.startsWith('/resume ')) {
        messageForm.reset();
        resumeQuery.value = text.slice('/resume'.length).trim();
        return;
    }

    if (isHistoryResumeIntent(text)) {
        messageForm.reset();
        resumeQuery.value = '';
        return;
    }

    messageForm.post(route('chat.messages.store', props.activeConversation.id), {
        preserveScroll: true,
        onSuccess: () => messageForm.reset(),
    });
}

function onInputKeydown(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        sendMessage();
    }
}

function setProvider(event) {
    router.patch(route('chat.update', props.activeConversation.id), {
        provider: event.target.value || null,
    }, { preserveScroll: true });
}

function stepsFor(messageId) {
    return (props.activeConversation?.steps ?? []).filter((s) => s.message_id === messageId);
}

// Tool-call trace shown in the collapsible "工具執行過程" section — excludes
// final_answer, which is surfaced separately as the message's "via <model>" tag.
function toolStepsFor(messageId) {
    return stepsFor(messageId).filter((s) => s.step_type !== 'final_answer');
}

// Which provider actually produced this assistant message — distinct from the
// conversation's provider *setting* (which may be "Auto" and fall back at
// request time), this is what really answered.
function providerFor(messageId) {
    return stepsFor(messageId).find((s) => s.step_type === 'final_answer')?.provider_used ?? null;
}

// Workspace panel: only the *finished, good* output of the conversation, not
// every intermediate tool call — a completed video, or a case the agent
// actually found (get_case with no error). "processing"/failed video jobs and
// error results stay in the collapsible tool trace instead of cluttering this.
const workspaceItems = computed(() => {
    const videos = (props.activeConversation?.video_generations ?? [])
        .filter((v) => v.status === 'completed' && v.output_url)
        .map((v) => ({ type: 'video', key: `video-${v.id}`, sortAt: v.created_at, data: v }));

    // Dedupe by case id, keeping the most recent lookup — the agent may query
    // the same case more than once in one conversation.
    const casesById = new Map();
    for (const s of props.activeConversation?.steps ?? []) {
        if (s.step_type !== 'tool_result' || s.tool_name !== 'get_case') continue;
        const output = s.tool_output;
        if (!output || output.error) continue;
        casesById.set(output.id, { type: 'case', key: `case-${output.id}`, sortAt: s.created_at, data: output });
    }

    return [...videos, ...casesById.values()].sort((a, b) => new Date(b.sortAt) - new Date(a.sortAt));
});

function unresolvedCount(caseData) {
    return (caseData.findings ?? []).filter((f) => !f.resolved).length;
}

let pollTimer = null;

// A plain `watch` on status only re-fires on a *change*, so a reload that
// comes back still "processing" (the common case for a slow reply — Qwen on
// CPU can take 20-40s, well past one 2s tick) leaves nothing to trigger the
// next poll: the value didn't move, so the watcher stays silent and the UI
// looks frozen until a manual refresh. Rescheduling from the reload's own
// onFinish instead re-checks status after every tick regardless of whether it
// changed, so the loop only stops once it's genuinely idle.
function schedulePoll() {
    clearTimeout(pollTimer);
    if (props.activeConversation?.status !== 'processing') return;
    pollTimer = setTimeout(() => {
        router.reload({
            only: ['activeConversation', 'conversations'],
            preserveScroll: true,
            onFinish: schedulePoll,
        });
    }, 2000);
}

watch(() => props.activeConversation?.id, schedulePoll, { immediate: true });
watch(() => props.activeConversation?.status, schedulePoll);

// A picker left open from one conversation shouldn't bleed into the next.
watch(() => props.activeConversation?.id, closeResumePicker);

onBeforeUnmount(() => clearTimeout(pollTimer));

const providerLabels = {
    openai: 'OpenAI',
    deepseek: 'DeepSeek',
    claude: 'Claude',
    qwen_gpu: 'Qwen (GPU tunnel)',
    qwen_lora: 'Qwen (server CPU)',
};
</script>

<template>
    <Head title="AI Chat" />

    <AuthenticatedLayout>
        <template #header>
            <span v-if="activeConversation">{{ activeConversation.title ?? '（未有標題）' }}</span>
            <span v-else>AI Chat</span>
        </template>

        <template #topbar-right>
            <Link :href="route('home')" class="back-button" style="margin-right: 6px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6" /></svg>
                Home
            </Link>
            <label v-if="activeConversation" class="input-shell" style="min-height: 30px; padding-inline: 8px;">
                <select :value="activeConversation.provider ?? ''" @change="setProvider" class="model-select">
                    <option value="">Auto</option>
                    <option v-for="p in availableProviders" :key="p" :value="p">{{ providerLabels[p] ?? p }}</option>
                </select>
            </label>
            <span v-if="activeConversation" class="status-pill" :class="activeConversation.status === 'processing' ? 'status-processing' : 'status-idle'">
                {{ activeConversation.status === 'processing' ? '處理緊…' : '待命' }}
            </span>
        </template>

        <div class="mx-auto flex h-[calc(100vh-4rem)] max-w-[100rem] gap-0 overflow-hidden">
            <!-- Chat panel -->
            <section class="chat-scroll flex flex-1 flex-col">
                <template v-if="activeConversation">
                    <div class="flex-1 space-y-3 overflow-y-auto p-5">
                        <div v-for="m in activeConversation.messages" :key="m.id" class="flex" :class="m.role === 'user' ? 'justify-end' : 'justify-start'">
                            <div class="chat-bubble" :class="m.role === 'user' ? 'chat-bubble-user' : 'chat-bubble-assistant'">
                                <p class="whitespace-pre-line">{{ m.content }}</p>
                                <div v-if="m.role === 'assistant' && providerFor(m.id)" class="chat-provider-tag">
                                    via {{ providerLabels[providerFor(m.id)] ?? providerFor(m.id) }}
                                </div>
                                <details v-if="toolStepsFor(m.id).length" class="tool-trace mt-2">
                                    <summary>工具執行過程（{{ toolStepsFor(m.id).length }} steps）</summary>
                                    <ul class="mt-1 space-y-1" style="color: var(--cw-muted); font-size: 10px;">
                                        <li v-for="s in toolStepsFor(m.id)" :key="s.id">
                                            🔧 [{{ s.step_type }}] {{ s.tool_name }}
                                            <span v-if="s.provider_used" style="color: var(--cw-orange);">— {{ s.provider_used }}</span>
                                        </li>
                                    </ul>
                                </details>
                            </div>
                        </div>

                        <!-- Resume picker — a local-only pseudo-message, never sent to the
                             backend. Triggered by "/resume [search]" or a natural-language
                             resume intent (see isHistoryResumeIntent above), ported from
                             claude-web-console's showResumePicker(). -->
                        <div v-if="resumeQuery !== null" class="flex justify-start">
                            <div class="chat-bubble chat-bubble-assistant" style="max-width: 28rem;">
                                <div class="mb-2 flex items-center justify-between">
                                    <span>{{ resumeQuery ? `搜尋「${resumeQuery}」嘅對話：` : '揀一個對話 resume：' }}</span>
                                    <button type="button" style="color: var(--cw-muted);" @click="closeResumePicker">✕</button>
                                </div>
                                <div v-if="resumeResults.length === 0" style="color: var(--cw-muted);">
                                    未搵到符合嘅對話。
                                </div>
                                <button
                                    v-for="c in resumeResults"
                                    :key="c.id"
                                    type="button"
                                    class="block w-full rounded-md px-2 py-1.5 text-left transition hover:bg-[var(--cw-blue-soft)]"
                                    @click="resumeConversation(c.id)"
                                >
                                    <div class="truncate">{{ c.title ?? '（未有標題）' }}</div>
                                    <div style="color: var(--cw-muted); font-size: 10px;">{{ c.status }}</div>
                                </button>
                            </div>
                        </div>

                        <div v-if="activeConversation.messages.length === 0 && resumeQuery === null" class="text-center text-sm" style="color: var(--cw-muted);">
                            開始打字同 coreAgent 傾偈啦
                        </div>
                    </div>

                    <form @submit.prevent="sendMessage" class="p-4">
                        <div class="task-composer">
                            <textarea
                                v-model="messageForm.content"
                                @keydown="onInputKeydown"
                                rows="2"
                                placeholder="打字…（Enter 送出，Shift+Enter 換行。試下 /resume 搵返舊對話）"
                            ></textarea>
                            <div class="composer-footer">
                                <span></span>
                                <button
                                    type="submit"
                                    :disabled="messageForm.processing || activeConversation.status === 'processing'"
                                    class="app-button send-button"
                                    aria-label="送出"
                                >
                                    <svg width="15" height="15" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2 11 13" /><path d="M22 2 15 22l-4-9-9-4 20-7Z" /></svg>
                                </button>
                            </div>
                        </div>
                    </form>
                </template>

                <div v-else class="flex flex-1 items-center justify-center text-sm" style="color: var(--cw-muted);">
                    揀一個對話，或者撳「+ New chat」開始
                </div>
            </section>

            <!-- Workspace — finished results (videos) and referenced docs
                 (Document Copilot cases the agent looked up), pulled out of the
                 chat log so they don't get buried under conversation turns. -->
            <aside
                v-if="activeConversation"
                class="hidden w-80 shrink-0 flex-col gap-3 overflow-y-auto border-l p-3 xl:flex"
                style="border-color: var(--cw-border); background: var(--cw-sidebar);"
            >
                <div style="font-size: 10px; font-weight: 800; color: var(--cw-muted); text-transform: uppercase; letter-spacing: .06em;">
                    Workspace
                </div>

                <div v-if="workspaceItems.length === 0" class="text-sm" style="color: var(--cw-muted);">
                    未有結果 — agent 完成任務之後，靚嘅結果同文件會出現喺呢度。
                </div>

                <div v-for="item in workspaceItems" :key="item.key" class="rail-card">
                    <template v-if="item.type === 'video'">
                        <header>
                            <span>🎬 {{ item.data.provider }}</span>
                            <span style="color: var(--cw-muted); font-weight: 500;">{{ new Date(item.data.created_at).toLocaleString() }}</span>
                        </header>
                        <p class="mb-2 line-clamp-2" style="font-size: 11px;">{{ item.data.prompt }}</p>
                        <video :src="item.data.output_url" controls class="w-full rounded-md" style="max-height: 180px"></video>
                        <a :href="item.data.output_url" target="_blank" rel="noopener noreferrer" class="mt-2 inline-block" style="font-size: 10px; color: var(--cw-orange); font-weight: 700;">
                            下載 / 開新分頁 →
                        </a>
                    </template>

                    <template v-else-if="item.type === 'case'">
                        <header>
                            <span>📄 {{ item.data.vertical }}</span>
                            <span style="color: var(--cw-muted); font-weight: 500;">{{ item.data.status }}</span>
                        </header>
                        <p class="mb-1" style="font-weight: 700; font-size: 11px;">{{ item.data.reference ?? `Case #${item.data.id}` }}</p>
                        <p v-if="item.data.summary" class="mb-2 line-clamp-2" style="font-size: 10px; color: var(--cw-muted);">{{ item.data.summary }}</p>
                        <p v-if="unresolvedCount(item.data) > 0" class="mb-2" style="font-size: 10px; color: #a15b16;">
                            ⚠ {{ unresolvedCount(item.data) }} 個未解決嘅 finding
                        </p>
                        <Link :href="route('cases.show', item.data.id)" style="font-size: 10px; color: var(--cw-blue-strong); font-weight: 700;">
                            睇詳情 →
                        </Link>
                    </template>
                </div>
            </aside>
        </div>
    </AuthenticatedLayout>
</template>
