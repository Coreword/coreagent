<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';

defineProps({
    templates: Array,
});

function use(template) {
    router.visit(route('home', { prompt: template.prompt }));
}
</script>

<template>
    <Head title="Templates" />

    <AuthenticatedLayout>
        <template #header><span>Templates</span></template>

        <section class="page-shell">
            <div class="page-heading">
                <div>
                    <span class="eyebrow-text">READY-MADE BRIEFS</span>
                    <h1>Templates</h1>
                    <p>Pick one and it lands in the Home composer, ready to send — these use the tools coreAgent actually has today, not a mockup.</p>
                </div>
            </div>

            <div class="template-grid">
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
                        Use this task
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M7 7h10v10" /></svg>
                    </button>
                </div>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
