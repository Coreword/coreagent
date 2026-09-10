<script setup>
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    prefill: { type: String, default: null },
});

const page = usePage();
const hasProjects = computed(() => (page.props.sidebar?.recentCases ?? []).length > 0);

function goCreateProject() {
    router.visit(route('cases.index', { create: 1 }));
}

const form = useForm({ content: props.prefill ?? '' });
const selectedChip = ref(null);

const examples = {
    'Look up a case': '幫我搵返 case reference "REF-1234"，同我講下宜家嘅狀態、有幾多份文件，同埋有冇未解決嘅 finding。',
    'List open cases': '幫我列出所有未完成嘅 case。',
    'Generate a video': '幫我生成一條短片：一個寧靜嘅海邊日出，鏡頭慢慢拉遠，帶少少電影感。',
    'Check a video job': '幫我睇下之前提交嘅影片生成任務做到邊。',
};
const chips = Object.keys(examples);

function selectChip(chip) {
    selectedChip.value = selectedChip.value === chip ? null : chip;
}

function useExample(chip) {
    form.content = examples[chip];
    selectedChip.value = null;
}

function submitTask() {
    if (!form.content.trim()) return;
    form.post(route('home.submit'));
}

function onKeydown(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        submitTask();
    }
}
</script>

<template>
    <Head title="Home" />

    <AuthenticatedLayout>
        <template #header><span>Your workspace</span></template>

        <section class="home-view">
            <div v-if="!hasProjects" class="onboarding-card">
                <div class="onboarding-icon">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z" /><path d="M12 11v5M9.5 13.5h5" /></svg>
                    <span class="accent-dot"></span>
                </div>
                <div class="onboarding-eyebrow">Your first coreAgent project</div>
                <h2>Start with a clear place for the work.</h2>
                <p>Projects keep briefs, files, connector permissions, reusable skills, and task history together. Begin with a project, then add context only when it is useful.</p>
                <button type="button" class="app-button button-primary" @click="goCreateProject">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z" /></svg>
                    Create a project
                </button>
                <div class="onboarding-steps">
                    <span><em>1</em> Name the outcome</span>
                    <span><em>2</em> Add useful context</span>
                    <span><em>3</em> Start the first task</span>
                </div>
            </div>

            <div class="welcome-ribbon"><span class="ribbon-star">✦</span> coreAgent can look up cases, generate video, and answer over WhatsApp</div>

            <div class="home-main-grid">
                <div class="home-copy">
                    <div class="eyebrow">✦ TASK-FIRST AI WORKSPACE</div>
                    <h1>What can <em>coreAgent</em><br>do for you?</h1>
                    <p>Describe the outcome. coreAgent picks the right tool — case lookup, video generation, or a plain answer — and gets to work.</p>

                    <div class="task-composer">
                        <textarea
                            v-model="form.content"
                            @keydown="onKeydown"
                            placeholder="Assign a task, ask a question, or start an automation…"
                        ></textarea>
                        <div class="composer-footer">
                            <div class="composer-tools">
                                <span class="composer-source"><span></span> Auto-routed</span>
                            </div>
                            <div class="composer-actions">
                                <button
                                    type="button"
                                    class="app-button send-button"
                                    :disabled="form.processing || !form.content.trim()"
                                    aria-label="Send task"
                                    @click="submitTask"
                                >
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4Z" /><path d="M22 2 11 13" /></svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="suggestion-zone">
                        <span class="suggestion-label">Start with an intent</span>
                        <div class="suggestion-row">
                            <button
                                v-for="chip in chips"
                                :key="chip"
                                type="button"
                                class="suggestion-chip"
                                :class="{ 'is-selected': selectedChip === chip }"
                                @click="selectChip(chip)"
                            >{{ chip }}</button>
                        </div>
                        <div v-if="selectedChip" class="example-popover" style="position: relative; margin-top: 10px; max-width: 480px; padding: 14px; background: var(--cw-surface); border: 1px solid var(--cw-border); border-radius: 12px;">
                            <p style="margin: 0 0 10px; font-size: 11px; color: var(--cw-muted); line-height: 1.55;">{{ examples[selectedChip] }}</p>
                            <button type="button" class="text-action" style="color: var(--cw-blue-strong); font-size: 10px; font-weight: 800;" @click="useExample(selectedChip)">Use this task →</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="home-lower">
                <div class="section-title-row">
                    <div><span class="eyebrow-text">A FOCUSED START</span><h2>One clear place to begin.</h2></div>
                </div>
                <div class="start-cards">
                    <button type="button" class="start-card" @click="useExample('Look up a case')">
                        <div class="start-card-icon blue"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z" /></svg></div>
                        <span class="label">Look up a case</span>
                        <small>Pull status, documents, and findings for a reference.</small>
                        <svg class="arrow" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M7 7h10v10" /></svg>
                    </button>
                    <button type="button" class="start-card" @click="useExample('Generate a video')">
                        <div class="start-card-icon orange"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 8-6 4 6 4Z" /><rect x="2" y="6" width="14" height="12" rx="2" /></svg></div>
                        <span class="label">Generate a video</span>
                        <small>Describe a scene and submit it to the render pipeline.</small>
                        <svg class="arrow" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M7 7h10v10" /></svg>
                    </button>
                    <button type="button" class="start-card" @click="router.visit(route('whatsapp-agents'))">
                        <div class="start-card-icon green"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z" /></svg></div>
                        <span class="label">Set up a WhatsApp agent</span>
                        <small>Connect a number so this agent answers on WhatsApp.</small>
                        <svg class="arrow" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M7 7h10v10" /></svg>
                    </button>
                </div>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
