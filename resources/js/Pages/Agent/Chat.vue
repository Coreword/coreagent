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

function newConversation() {
    router.post(route('chat.store'));
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
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">AI Chat</h2>
        </template>

        <!-- Console colour language (dark mode): --bg #0f1115 / --panel #161923 /
             --border #2a2f3d / --accent #d97757 (Claude terracotta, same in both
             modes) / --user-bubble #2a3040 / --assistant-bubble #1c2432 -->
        <div class="mx-auto flex h-[calc(100vh-8.5rem)] max-w-[100rem] gap-0 overflow-hidden rounded-lg border border-gray-200 dark:border-[#2a2f3d] px-0 py-0 sm:mx-4 lg:mx-auto">
            <!-- Left: conversation history -->
            <aside class="flex w-72 shrink-0 flex-col border-r border-gray-200 bg-gray-50 dark:border-[#2a2f3d] dark:bg-[#161923]">
                <div class="border-b border-gray-200 p-3 dark:border-[#2a2f3d]">
                    <button
                        @click="newConversation"
                        class="w-full rounded-md border border-[#d97757] bg-[#d97757] px-3 py-2 text-sm font-semibold text-[#1a1a1a] transition hover:brightness-110"
                    >
                        + New chat
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto">
                    <Link
                        v-for="c in conversations"
                        :key="c.id"
                        :href="route('chat.show', c.id)"
                        class="block border-b border-gray-200 px-3 py-2 text-sm transition hover:bg-gray-100 dark:border-[#2a2f3d] dark:hover:bg-[#1c2130]"
                        :class="activeConversation?.id === c.id ? 'bg-gray-100 dark:bg-[#1c2130]' : ''"
                    >
                        <div class="truncate text-gray-900 dark:text-[#e6e8ef]">{{ c.title ?? '（未有標題）' }}</div>
                        <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-[#8b90a0]">
                            <span
                                class="inline-block h-2 w-2 rounded-full"
                                :class="c.status === 'processing' ? 'bg-amber-400' : 'bg-emerald-500'"
                            ></span>
                            {{ c.status }}
                        </div>
                    </Link>
                    <div v-if="conversations.length === 0" class="p-3 text-sm text-gray-500 dark:text-[#8b90a0]">未有對話</div>
                </div>
            </aside>

            <!-- Right: chat panel -->
            <section class="flex flex-1 flex-col bg-white dark:bg-[#0f1115]">
                <template v-if="activeConversation">
                    <div class="flex items-center justify-between border-b border-gray-200 px-3 py-2 text-xs text-gray-500 dark:border-[#2a2f3d] dark:text-[#8b90a0]">
                        <span class="flex items-center gap-1.5">
                            <span
                                class="inline-block h-2 w-2 rounded-full"
                                :class="activeConversation.status === 'processing' ? 'bg-amber-400' : 'bg-emerald-500'"
                            ></span>
                            {{ activeConversation.status === 'processing' ? '處理緊…' : '待命' }}
                        </span>
                        <label class="flex items-center gap-2">
                            <span>Model:</span>
                            <select
                                :value="activeConversation.provider ?? ''"
                                @change="setProvider"
                                class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs text-gray-900 focus:border-[#d97757] focus:ring-[#d97757] dark:border-[#2a2f3d] dark:bg-[#0d0f14] dark:text-[#e6e8ef]"
                            >
                                <option value="">Auto（跟優先序 fallback）</option>
                                <option v-for="p in availableProviders" :key="p" :value="p">{{ providerLabels[p] ?? p }}</option>
                            </select>
                        </label>
                    </div>

                    <div class="flex-1 space-y-3 overflow-y-auto p-4">
                        <div v-for="m in activeConversation.messages" :key="m.id" class="flex" :class="m.role === 'user' ? 'justify-end' : 'justify-start'">
                            <div
                                class="max-w-lg rounded-[10px] px-4 py-2.5 text-sm leading-relaxed text-gray-900 dark:text-[#e6e8ef]"
                                :class="m.role === 'user' ? 'bg-indigo-100 dark:bg-[#2a3040]' : 'bg-gray-100 dark:bg-[#1c2432]'"
                            >
                                <p class="whitespace-pre-line">{{ m.content }}</p>
                                <div v-if="m.role === 'assistant' && providerFor(m.id)" class="mt-1.5 text-xs text-[#d97757]">
                                    via {{ providerLabels[providerFor(m.id)] ?? providerFor(m.id) }}
                                </div>
                                <details v-if="toolStepsFor(m.id).length" class="mt-2 text-xs text-gray-500 dark:text-[#8b90a0]">
                                    <summary class="cursor-pointer hover:text-gray-900 dark:hover:text-[#e6e8ef]">工具執行過程（{{ toolStepsFor(m.id).length }} steps）</summary>
                                    <ul class="mt-1 space-y-1">
                                        <li v-for="s in toolStepsFor(m.id)" :key="s.id">
                                            🔧 [{{ s.step_type }}] {{ s.tool_name }}
                                            <span v-if="s.provider_used" class="text-[#d97757]">— {{ s.provider_used }}</span>
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
                            <div class="max-w-md rounded-[10px] bg-gray-100 px-4 py-2.5 text-sm text-gray-900 dark:bg-[#1c2432] dark:text-[#e6e8ef]">
                                <div class="mb-2 flex items-center justify-between">
                                    <span>{{ resumeQuery ? `搜尋「${resumeQuery}」嘅對話：` : '揀一個對話 resume：' }}</span>
                                    <button type="button" class="text-gray-500 hover:text-gray-900 dark:text-[#8b90a0] dark:hover:text-[#e6e8ef]" @click="closeResumePicker">✕</button>
                                </div>
                                <div v-if="resumeResults.length === 0" class="text-gray-500 dark:text-[#8b90a0]">
                                    未搵到符合嘅對話。
                                </div>
                                <button
                                    v-for="c in resumeResults"
                                    :key="c.id"
                                    type="button"
                                    class="block w-full rounded-md px-2 py-1.5 text-left transition hover:bg-gray-200 dark:hover:bg-[#232838]"
                                    @click="resumeConversation(c.id)"
                                >
                                    <div class="truncate">{{ c.title ?? '（未有標題）' }}</div>
                                    <div class="text-xs text-gray-500 dark:text-[#8b90a0]">{{ c.status }}</div>
                                </button>
                            </div>
                        </div>

                        <div v-if="activeConversation.messages.length === 0 && resumeQuery === null" class="text-center text-sm text-gray-500 dark:text-[#8b90a0]">
                            開始打字同 coreAgent 傾偈啦
                        </div>
                    </div>

                    <form @submit.prevent="sendMessage" class="flex items-end gap-2 border-t border-gray-200 p-3 dark:border-[#2a2f3d]">
                        <textarea
                            v-model="messageForm.content"
                            @keydown="onInputKeydown"
                            rows="2"
                            placeholder="打字…（Enter 送出，Shift+Enter 換行。試下 /resume 搵返舊對話）"
                            class="flex-1 resize-none rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:border-[#d97757] focus:ring-[#d97757] dark:border-[#2a2f3d] dark:bg-[#0d0f14] dark:text-[#e6e8ef] dark:placeholder:text-[#8b90a0]"
                        ></textarea>
                        <button
                            type="submit"
                            :disabled="messageForm.processing || activeConversation.status === 'processing'"
                            class="rounded-md border border-[#d97757] bg-[#d97757] px-4 py-2 text-sm font-semibold text-[#1a1a1a] transition hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            送出
                        </button>
                    </form>
                </template>

                <div v-else class="flex flex-1 items-center justify-center text-sm text-gray-500 dark:text-[#8b90a0]">
                    揀一個對話，或者撳「+ New chat」開始
                </div>
            </section>

            <!-- Right: workspace — finished results (videos) and referenced docs
                 (Document Copilot cases the agent looked up), pulled out of the
                 chat log so they don't get buried under conversation turns. -->
            <aside
                v-if="activeConversation"
                class="hidden w-80 shrink-0 flex-col overflow-y-auto border-l border-gray-200 bg-gray-50 dark:border-[#2a2f3d] dark:bg-[#161923] xl:flex"
            >
                <div class="border-b border-gray-200 px-3 py-2 text-xs font-semibold text-gray-500 dark:border-[#2a2f3d] dark:text-[#8b90a0]">
                    Workspace
                </div>

                <div v-if="workspaceItems.length === 0" class="p-3 text-sm text-gray-500 dark:text-[#8b90a0]">
                    未有結果 — agent 完成任務之後，靚嘅結果同文件會出現喺呢度。
                </div>

                <div class="flex-1 space-y-3 p-3">
                    <div
                        v-for="item in workspaceItems"
                        :key="item.key"
                        class="rounded-md border border-gray-200 bg-white p-3 text-sm dark:border-[#2a2f3d] dark:bg-[#1c2130]"
                    >
                        <template v-if="item.type === 'video'">
                            <div class="mb-1.5 flex items-center justify-between text-xs text-gray-500 dark:text-[#8b90a0]">
                                <span>🎬 {{ item.data.provider }}</span>
                                <span>{{ new Date(item.data.created_at).toLocaleString() }}</span>
                            </div>
                            <p class="mb-2 line-clamp-2 text-gray-900 dark:text-[#e6e8ef]">{{ item.data.prompt }}</p>
                            <video :src="item.data.output_url" controls class="w-full rounded-md" style="max-height: 180px"></video>
                            <a
                                :href="item.data.output_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="mt-2 inline-block text-xs text-[#d97757] hover:underline"
                            >
                                下載 / 開新分頁 →
                            </a>
                        </template>

                        <template v-else-if="item.type === 'case'">
                            <div class="mb-1.5 flex items-center justify-between text-xs text-gray-500 dark:text-[#8b90a0]">
                                <span>📄 {{ item.data.vertical }}</span>
                                <span>{{ item.data.status }}</span>
                            </div>
                            <p class="mb-1 font-medium text-gray-900 dark:text-[#e6e8ef]">{{ item.data.reference ?? `Case #${item.data.id}` }}</p>
                            <p v-if="item.data.summary" class="mb-2 line-clamp-2 text-xs text-gray-600 dark:text-[#8b90a0]">{{ item.data.summary }}</p>
                            <p v-if="unresolvedCount(item.data) > 0" class="mb-2 text-xs text-amber-600 dark:text-amber-400">
                                ⚠ {{ unresolvedCount(item.data) }} 個未解決嘅 finding
                            </p>
                            <Link :href="route('cases.show', item.data.id)" class="text-xs text-[#d97757] hover:underline">
                                睇詳情 →
                            </Link>
                        </template>
                    </div>
                </div>
            </aside>
        </div>
    </AuthenticatedLayout>
</template>
