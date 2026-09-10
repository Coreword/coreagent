<script setup>
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps({
    templates: Array,
});

const workflowCount = computed(() => props.templates.length);

function use(template) {
    router.visit(route('home', { prompt: template.prompt }));
}

function startBlank() {
    router.visit(route('home'));
}
</script>

<template>
    <Head title="Templates" />

    <AuthenticatedLayout>
        <template #header><span>Templates</span></template>

        <section class="page-shell">
            <div class="template-hero">
                <div>
                    <span class="eyebrow-text">DISCOVERY</span>
                    <h1>Start with a useful shape.</h1>
                    <p>These starting points have enough structure to be helpful and enough room to make your own.</p>
                    <button type="button" class="app-button button-secondary" @click="startBlank">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z" /></svg>
                        Start from a blank brief
                    </button>
                </div>
                <div class="template-hero-art">
                    <span class="art-node start"></span>
                    <div class="art-card"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" /><path d="M16 2v4M8 2v4M3 10h18" /></svg></div>
                    <div class="art-card"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z" /></svg></div>
                    <div class="art-card"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8" /><path d="m21 21-4.3-4.3" /></svg></div>
                    <div class="art-card"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18" /><path d="M18 17V9M13 17V5M8 17v-4" /></svg></div>
                    <span class="art-node end"></span>
                </div>
            </div>

            <div class="section-title-row">
                <div><h2 style="margin: 0;">Automation templates</h2></div>
                <span class="eyebrow-text" style="text-transform: none; letter-spacing: normal; font-weight: 700; color: var(--cw-muted);">{{ workflowCount }} ready-to-adapt workflow{{ workflowCount === 1 ? '' : 's' }}</span>
            </div>

            <div class="template-grid" style="margin-top: 20px;">
                <div v-for="t in templates" :key="t.title" class="template-card">
                    <div class="template-icon">
                        <svg v-if="t.icon === 'file'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z" /></svg>
                        <svg v-else-if="t.icon === 'list'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" /></svg>
                        <svg v-else-if="t.icon === 'video'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 8-6 4 6 4Z" /><rect x="2" y="6" width="14" height="12" rx="2" /></svg>
                        <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z" /></svg>
                    </div>
                    <span class="template-tag">{{ t.tag }}</span>
                    <h3>{{ t.title }}</h3>
                    <p>{{ t.description }}</p>
                    <button type="button" class="use-link" @click="use(t)">
                        Use template
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M7 7h10v10" /></svg>
                    </button>
                </div>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
