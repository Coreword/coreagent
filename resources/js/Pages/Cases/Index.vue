<script setup>
import { computed, onMounted, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    cases: Array,
    verticals: Array,
});

const search = ref('');
const mode = ref('grid');
const showModal = ref(false);

onMounted(() => {
    if (new URLSearchParams(window.location.search).get('create') === '1') {
        showModal.value = true;
    }
});

const visibleCases = computed(() =>
    props.cases.filter((c) => {
        const q = search.value.toLowerCase();
        if (!q) return true;
        return (c.reference ?? '').toLowerCase().includes(q) || c.vertical.toLowerCase().includes(q);
    }),
);

// CoreAI's Project.status is Live/Active/Paused/Draft — mapped from the real
// case pipeline (see CaseController/RunRuleChecksJob) rather than invented.
const statusMap = {
    intake: { label: 'Draft', class: 'status-draft' },
    processing: { label: 'Active', class: 'status-active' },
    awaiting_api_key: { label: 'Paused', class: 'status-paused' },
    checked: { label: 'Live', class: 'status-live' },
    summarised: { label: 'Live', class: 'status-live' },
};
function statusFor(status) {
    return statusMap[status] ?? { label: status, class: 'status-draft' };
}

const iconShades = ['project-icon-blue', 'project-icon-mint', 'project-icon-orange'];
function shadeFor(index) {
    return iconShades[index % iconShades.length];
}

const form = useForm({ vertical: props.verticals[0] ?? '', reference: '', document: null });

function submit() {
    form.post(route('cases.store'), {
        forceFormData: true,
        onSuccess: () => {
            form.reset();
            showModal.value = false;
        },
    });
}
</script>

<template>
    <Head title="Projects" />

    <AuthenticatedLayout>
        <template #header><span>Projects</span></template>

        <section class="page-shell">
            <div class="page-heading">
                <div>
                    <span class="eyebrow-text">YOUR WORKSPACES</span>
                    <h1>Projects</h1>
                    <p>Every Document Copilot case — its status, documents, and findings — in one place.</p>
                </div>
                <div class="page-heading-actions">
                    <button type="button" class="app-button button-primary" @click="showModal = true">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5v14" /></svg>
                        Create project
                    </button>
                </div>
            </div>

            <div class="toolbar">
                <label class="input-shell">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8" /><path d="m21 21-4.3-4.3" /></svg>
                    <input v-model="search" placeholder="Search reference or vertical" />
                </label>
                <div class="toolbar-right">
                    <div class="view-switch">
                        <button type="button" :class="{ active: mode === 'grid' }" aria-label="Grid view" @click="mode = 'grid'">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1" /><rect x="14" y="3" width="7" height="7" rx="1" /><rect x="3" y="14" width="7" height="7" rx="1" /><rect x="14" y="14" width="7" height="7" rx="1" /></svg>
                        </button>
                        <button type="button" :class="{ active: mode === 'list' }" aria-label="List view" @click="mode = 'list'">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" /></svg>
                        </button>
                    </div>
                </div>
            </div>

            <div :class="mode === 'grid' ? 'projects-grid' : 'projects-list'">
                <Link v-for="(c, i) in visibleCases" :key="c.id" :href="route('cases.show', c.id)" class="project-card">
                    <div class="project-card-top">
                        <div class="project-icon" :class="shadeFor(i)">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z" /></svg>
                        </div>
                    </div>
                    <div>
                        <h3>{{ c.reference ?? `Case #${c.id}` }}</h3>
                        <p>{{ c.vertical }}</p>
                    </div>
                    <div class="card-footer">
                        <span class="status-pill" :class="statusFor(c.status).class">{{ statusFor(c.status).label }}</span>
                        <span class="muted-inline">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" /><path d="M12 6v6l4 2" /></svg>
                            {{ new Date(c.created_at).toLocaleDateString() }}
                        </span>
                    </div>
                    <div class="project-meta">
                        <span>
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z" /></svg>
                            {{ c.documents_count }} document{{ c.documents_count === 1 ? '' : 's' }}
                        </span>
                    </div>
                </Link>

                <button type="button" class="new-project-card" @click="showModal = true">
                    <svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" /><path d="M12 8v8M8 12h8" /></svg>
                    <strong>Create a new project</strong>
                    <span>Start with a vertical and a document.</span>
                </button>
            </div>
        </section>

        <div v-if="showModal" class="modal-backdrop" @click.self="showModal = false">
            <div class="modal-frame">
                <button type="button" class="modal-close" @click="showModal = false">✕</button>
                <div class="modal-heading">
                    <h2>Create project</h2>
                    <p>Starts a new Document Copilot case with one document. Add more documents from the project page afterwards.</p>
                </div>
                <form class="form-stack" @submit.prevent="submit">
                    <label>
                        <span>Vertical</span>
                        <select v-model="form.vertical">
                            <option v-for="v in verticals" :key="v" :value="v">{{ v }}</option>
                        </select>
                    </label>
                    <label>
                        <span>Reference <em>(optional)</em></span>
                        <input v-model="form.reference" type="text" placeholder="e.g. REF-1234" />
                    </label>
                    <div class="sources-form-group">
                        <div class="form-label"><span>Document</span><em>PDF, DOCX, or TXT — 20MB max</em></div>
                        <label class="upload-drop">
                            <input type="file" accept=".pdf,.docx,.txt" @input="form.document = $event.target.files[0]" />
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12" /></svg>
                            <strong>{{ form.document ? form.document.name : 'Click to choose a file' }}</strong>
                            <small>or drag it here</small>
                        </label>
                        <p v-if="form.errors.document" style="color: #c2410c; font-size: 10px;">{{ form.errors.document }}</p>
                    </div>
                </form>
                <div class="modal-footer">
                    <button type="button" class="app-button button-secondary" @click="showModal = false">Cancel</button>
                    <button type="button" class="app-button button-primary" :disabled="form.processing" @click="submit">Create project</button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
