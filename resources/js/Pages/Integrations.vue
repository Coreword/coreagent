<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';

defineProps({
    whatsappConfigured: Boolean,
});

// Same connector list CoreAI's mockup uses — shown honestly as "coming soon"
// rather than a fake "Connected" state, except WhatsApp, which is real.
const connectors = [
    { name: 'WhatsApp', letter: 'W', color: '#22a06b', description: 'Run a responsive customer conversation agent.', real: true },
    { name: 'Gmail', letter: 'M', color: '#ea4335', description: 'Draft and send email from an agent task.' },
    { name: 'Google Drive', letter: 'D', color: '#4285f4', description: 'Read selected files and folders as context.' },
    { name: 'Google Sheets', letter: 'S', color: '#34a853', description: 'Write a result into a structured worksheet.' },
    { name: 'Slack', letter: 'S', color: '#4a154b', description: 'Share concise updates with your team.' },
    { name: 'Notion', letter: 'N', color: '#172033', description: 'Search and update connected project knowledge.' },
];

function open(connector) {
    if (connector.real) router.visit(route('whatsapp-agents'));
}
</script>

<template>
    <Head title="Integrations" />

    <AuthenticatedLayout>
        <template #header><span>Integrations</span></template>

        <section class="page-shell">
            <div class="page-heading">
                <div>
                    <span class="eyebrow-text">CONNECTED TOOLS</span>
                    <h1>Integrations</h1>
                    <p>WhatsApp is live. The rest aren't built yet — shown here so the nav matches CoreAI's full layout, honestly marked instead of faked.</p>
                </div>
            </div>

            <div class="integration-grid">
                <div v-for="c in connectors" :key="c.name" class="integration-card" :style="c.real ? 'cursor: pointer;' : ''" @click="open(c)">
                    <div class="integration-card-top">
                        <div class="app-logo" :style="{ background: c.color }">{{ c.letter }}</div>
                        <span v-if="c.real" class="coming-soon-badge" :style="whatsappConfigured ? 'color:#147a50;background:var(--cw-green-soft);border-color:transparent;' : ''">
                            {{ whatsappConfigured ? 'Connected' : 'Set up' }}
                        </span>
                        <span v-else class="coming-soon-badge">Coming soon</span>
                    </div>
                    <h3>{{ c.name }}</h3>
                    <p>{{ c.description }}</p>
                </div>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
