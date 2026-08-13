<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { onBeforeUnmount, watch } from 'vue';

const props = defineProps({
    conversations: Array,
    activeConversation: Object,
    availableProviders: Array,
});

const messageForm = useForm({ content: '' });

function sendMessage() {
    if (!props.activeConversation || !messageForm.content.trim()) return;
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

let pollTimer = null;

watch(
    () => props.activeConversation?.status,
    (status) => {
        clearTimeout(pollTimer);
        if (status === 'processing') {
            pollTimer = setTimeout(() => {
                router.reload({ only: ['activeConversation', 'conversations'], preserveScroll: true });
            }, 2000);
        }
    },
    { immediate: true },
);

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

        <!-- Console colour language: --bg #0f1115 / --panel #161923 / --border #2a2f3d /
             --accent #d97757 (Claude terracotta) / --user-bubble #2a3040 / --assistant-bubble #1c2432 -->
        <div class="mx-auto flex h-[calc(100vh-8.5rem)] max-w-7xl gap-0 overflow-hidden rounded-lg border border-[#2a2f3d] px-0 py-0 sm:mx-4 lg:mx-auto">
            <!-- Left: conversation history -->
            <aside class="flex w-72 shrink-0 flex-col border-r border-[#2a2f3d] bg-[#161923]">
                <div class="border-b border-[#2a2f3d] p-3">
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
                        class="block border-b border-[#2a2f3d] px-3 py-2 text-sm transition hover:bg-[#1c2130]"
                        :class="activeConversation?.id === c.id ? 'bg-[#1c2130]' : ''"
                    >
                        <div class="truncate text-[#e6e8ef]">{{ c.title ?? '（未有標題）' }}</div>
                        <div class="flex items-center gap-1.5 text-xs text-[#8b90a0]">
                            <span
                                class="inline-block h-2 w-2 rounded-full"
                                :class="c.status === 'processing' ? 'bg-amber-400' : 'bg-emerald-500'"
                            ></span>
                            {{ c.status }}
                        </div>
                    </Link>
                    <div v-if="conversations.length === 0" class="p-3 text-sm text-[#8b90a0]">未有對話</div>
                </div>
            </aside>

            <!-- Right: chat panel -->
            <section class="flex flex-1 flex-col bg-[#0f1115]">
                <template v-if="activeConversation">
                    <div class="flex items-center justify-between border-b border-[#2a2f3d] px-3 py-2 text-xs text-[#8b90a0]">
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
                                class="rounded-md border border-[#2a2f3d] bg-[#0d0f14] px-2 py-1 text-xs text-[#e6e8ef] focus:border-[#d97757] focus:ring-[#d97757]"
                            >
                                <option value="">Auto（跟優先序 fallback）</option>
                                <option v-for="p in availableProviders" :key="p" :value="p">{{ providerLabels[p] ?? p }}</option>
                            </select>
                        </label>
                    </div>

                    <div class="flex-1 space-y-3 overflow-y-auto p-4">
                        <div v-for="m in activeConversation.messages" :key="m.id" class="flex" :class="m.role === 'user' ? 'justify-end' : 'justify-start'">
                            <div
                                class="max-w-lg rounded-[10px] px-4 py-2.5 text-sm leading-relaxed text-[#e6e8ef]"
                                :class="m.role === 'user' ? 'bg-[#2a3040]' : 'bg-[#1c2432]'"
                            >
                                <p class="whitespace-pre-line">{{ m.content }}</p>
                                <div v-if="m.role === 'assistant' && providerFor(m.id)" class="mt-1.5 text-xs text-[#d97757]">
                                    via {{ providerLabels[providerFor(m.id)] ?? providerFor(m.id) }}
                                </div>
                                <details v-if="toolStepsFor(m.id).length" class="mt-2 text-xs text-[#8b90a0]">
                                    <summary class="cursor-pointer hover:text-[#e6e8ef]">工具執行過程（{{ toolStepsFor(m.id).length }} steps）</summary>
                                    <ul class="mt-1 space-y-1">
                                        <li v-for="s in toolStepsFor(m.id)" :key="s.id">
                                            🔧 [{{ s.step_type }}] {{ s.tool_name }}
                                            <span v-if="s.provider_used" class="text-[#d97757]">— {{ s.provider_used }}</span>
                                        </li>
                                    </ul>
                                </details>
                            </div>
                        </div>
                        <div v-if="activeConversation.messages.length === 0" class="text-center text-sm text-[#8b90a0]">
                            開始打字同 coreAgent 傾偈啦
                        </div>
                    </div>

                    <form @submit.prevent="sendMessage" class="flex items-end gap-2 border-t border-[#2a2f3d] p-3">
                        <textarea
                            v-model="messageForm.content"
                            @keydown="onInputKeydown"
                            rows="2"
                            placeholder="打字…（Enter 送出，Shift+Enter 換行）"
                            class="flex-1 resize-none rounded-md border border-[#2a2f3d] bg-[#0d0f14] px-3 py-2 text-sm text-[#e6e8ef] placeholder:text-[#8b90a0] focus:border-[#d97757] focus:ring-[#d97757]"
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

                <div v-else class="flex flex-1 items-center justify-center text-sm text-[#8b90a0]">
                    揀一個對話，或者撳「+ New chat」開始
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
