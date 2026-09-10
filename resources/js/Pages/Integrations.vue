<script setup>
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps({
    whatsappConfigured: Boolean,
});

// Same connector catalogue CoreAI's mockup shows. Only WhatsApp is a real,
// working connector today — everything else is shown with an honest
// "Coming soon" state rather than a faked "Connected" badge, so the grid
// matches the reference layout without claiming capabilities coreAgent
// doesn't have yet.
const connectors = [
    { name: 'WhatsApp', letter: 'W', color: '#22a06b', description: 'Run a responsive customer conversation agent.', categories: ['communication'], real: true },
    { name: 'Gmail', letter: 'M', color: '#ea4335', description: 'Draft and send email from an agent task.', categories: ['google', 'communication'] },
    { name: 'Google Drive', letter: 'D', color: '#4285f4', description: 'Read selected files and folders as context.', categories: ['google', 'data'] },
    { name: 'Google Sheets', letter: 'S', color: '#34a853', description: 'Write a result into a structured worksheet.', categories: ['google', 'data', 'work'] },
    { name: 'Slack', letter: 'S', color: '#4a154b', description: 'Share concise updates with your team.', categories: ['work', 'communication'] },
    { name: 'Notion', letter: 'N', color: '#172033', description: 'Search and update connected project knowledge.', categories: ['work', 'data'] },
    { name: 'Instagram', letter: 'I', color: '#e1306c', description: 'Monitor comments and prepare on-brand replies.', categories: ['social', 'communication'] },
    { name: 'HubSpot', letter: 'H', color: '#ff7a59', description: 'Create and enrich qualified customer records.', categories: ['work', 'data'] },
    { name: 'Shopify', letter: 'S', color: '#95bf47', description: 'Bring product details into commerce workflows.', categories: ['work', 'data'] },
    { name: 'Outlook', letter: 'O', color: '#0078d4', description: 'Read mail and schedule based on business context.', categories: ['work', 'communication'] },
    { name: 'Dropbox', letter: 'D', color: '#0061ff', description: 'Use shared documents and folders as a source.', categories: ['data', 'work'] },
    { name: 'Airtable', letter: 'A', color: '#fcb400', description: 'Keep an operational record current automatically.', categories: ['data', 'work'] },
];

const categories = [
    { key: 'all', label: 'All apps' },
    { key: 'google', label: 'Google' },
    { key: 'social', label: 'Social' },
    { key: 'work', label: 'Work' },
    { key: 'data', label: 'Data' },
    { key: 'communication', label: 'Communication' },
];

const search = ref('');
const activeCategory = ref('all');

const connectedCount = computed(() => (props.whatsappConfigured ? 1 : 0));

const visibleConnectors = computed(() => {
    const query = search.value.toLowerCase();
    return connectors.filter((c) => {
        const matchesSearch = c.name.toLowerCase().includes(query);
        const matchesCategory = activeCategory.value === 'all' || c.categories.includes(activeCategory.value);
        return matchesSearch && matchesCategory;
    });
});

function isConnected(c) {
    return c.real && props.whatsappConfigured;
}

function open(c) {
    if (c.real) router.visit(route('whatsapp-agents'));
}
</script>

<template>
    <Head title="Integrations" />

    <AuthenticatedLayout>
        <template #header><span>Integrations</span></template>

        <section class="page-shell">
            <div class="page-heading">
                <div>
                    <span class="eyebrow-text">CONNECTED CONTEXT</span>
                    <h1>Integrations</h1>
                    <p>Choose the services coreAgent can use to move a task from intent to action.</p>
                </div>
                <div class="page-heading-actions">
                    <button type="button" class="app-button button-primary" @click="router.visit(route('whatsapp-agents'))">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5v14" /></svg>
                        Manage connectors
                    </button>
                </div>
            </div>

            <div class="connected-banner">
                <div class="connected-banner-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1" /><rect x="14" y="3" width="7" height="7" rx="1" /><rect x="3" y="14" width="7" height="7" rx="1" /><rect x="14" y="14" width="7" height="7" rx="1" /></svg>
                </div>
                <div class="connected-banner-body">
                    <strong>{{ connectedCount }} connector{{ connectedCount === 1 ? '' : 's' }} linked</strong>
                    <span>Available across projects whenever you choose to use them.</span>
                </div>
                <button type="button" class="text-action" @click="router.visit(route('whatsapp-agents'))">
                    Review access
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M7 7h10v10" /></svg>
                </button>
            </div>

            <div class="toolbar">
                <label class="input-shell">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8" /><path d="m21 21-4.3-4.3" /></svg>
                    <input v-model="search" placeholder="Search integrations" />
                </label>
                <div class="category-pills">
                    <button
                        v-for="c in categories"
                        :key="c.key"
                        type="button"
                        class="category-pill"
                        :class="{ active: activeCategory === c.key }"
                        @click="activeCategory = c.key"
                    >{{ c.label }}</button>
                </div>
            </div>

            <div class="integration-grid">
                <div
                    v-for="c in visibleConnectors"
                    :key="c.name"
                    class="integration-card"
                    :style="c.real ? 'cursor: pointer;' : ''"
                    @click="open(c)"
                >
                    <div class="integration-card-top">
                        <div class="app-logo" :style="{ background: c.color }">{{ c.letter }}</div>
                        <span v-if="isConnected(c)" class="connector-status connected">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
                            Connected
                        </span>
                        <span v-else-if="c.real" class="coming-soon-badge">Set up</span>
                        <span v-else class="coming-soon-badge">Coming soon</span>
                    </div>
                    <h3>{{ c.name }}</h3>
                    <p>{{ c.description }}</p>
                    <span v-if="isConnected(c)" class="manage-link is-connected">
                        Manage
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M7 7h10v10" /></svg>
                    </span>
                    <span v-else-if="c.real" class="manage-link">
                        Connect
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M7 7h10v10" /></svg>
                    </span>
                    <span v-else class="manage-link is-disabled">Coming soon</span>
                </div>

                <p v-if="visibleConnectors.length === 0" class="empty-results">
                    <template v-if="search">No integrations match "{{ search }}".</template>
                    <template v-else>No integrations in this category yet.</template>
                </p>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
