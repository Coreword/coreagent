<script setup>
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    case: Object,
});

const form = useForm({ document: null });

function submit() {
    form.post(route('cases.documents.store', props.case.id), {
        forceFormData: true,
        onSuccess: () => form.reset(),
    });
}

const severityColor = {
    blocker: '#c2410c',
    warning: '#a15b16',
    info: 'var(--cw-blue-strong)',
};

// Three honest pipeline stages from the real status machine (see
// CaseController / IngestDocumentJob / RunRuleChecksJob / SummariseCaseJob),
// not CoreAI's generic mock stage grid.
const TERMINAL = ['checked', 'summarised'];
const stages = computed(() => {
    const status = props.case.status;
    return [
        {
            key: 'intake',
            label: 'Intake',
            detail: `${props.case.documents.length} document${props.case.documents.length === 1 ? '' : 's'}`,
            state: status === 'intake' ? 'blue' : 'green',
        },
        {
            key: 'processing',
            label: 'Processing & rule checks',
            detail: status === 'awaiting_api_key' ? 'Paused — needs an API key' : `${props.case.extractions.length} field${props.case.extractions.length === 1 ? '' : 's'} extracted`,
            state: status === 'intake' ? 'orange' : status === 'awaiting_api_key' ? 'orange' : TERMINAL.includes(status) ? 'green' : 'blue',
        },
        {
            key: 'summary',
            label: 'Summary',
            detail: status === 'summarised' ? 'Ready' : 'Not yet summarised',
            state: status === 'summarised' ? 'green' : 'orange',
        },
    ];
});
</script>

<template>
    <Head :title="`Case #${props.case.id}`" />

    <AuthenticatedLayout>
        <template #header><span>{{ props.case.reference ?? `Case #${props.case.id}` }}</span></template>
        <template #topbar-right>
            <Link :href="route('cases.index')" class="back-button">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6" /></svg>
                Projects
            </Link>
        </template>

        <div class="workspace-shell">
            <div class="workspace-project-head">
                <div>
                    <h1>{{ props.case.reference ?? `Case #${props.case.id}` }}</h1>
                    <p>{{ props.case.vertical }} <span>·</span> updated {{ new Date(props.case.updated_at).toLocaleString() }}</p>
                </div>
                <span class="project-state" style="margin-left: auto;"><i></i>{{ props.case.status }}</span>
            </div>

            <p v-if="props.case.status === 'awaiting_api_key'" style="max-width: 700px; margin: -10px 0 16px; font-size: 11px; color: #a15b16;">
                未設定 OPENAI_API_KEY，Classify(LLM)/Extract/Summarise 已暫停 — 喺 .env 填入 key 之後重新 dispatch job 就會繼續。
            </p>

            <div class="task-stage-grid">
                <div v-for="stage in stages" :key="stage.key" class="task-stage" :class="stage.state">
                    <div>
                        <svg v-if="stage.state === 'green'" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
                        <svg v-else-if="stage.state === 'blue'" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" /><path d="M12 6v6l4 2" /></svg>
                        <svg v-else width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" stroke-dasharray="3 3" /></svg>
                    </div>
                    <strong>{{ stage.label }}</strong>
                    <p>{{ stage.detail }}</p>
                </div>
            </div>

            <p v-if="props.case.summary" style="max-width: 760px; margin: 19px 0 0; padding: 14px 16px; background: var(--cw-surface); border: 1px solid var(--cw-border); border-radius: 12px; font-size: 12px; line-height: 1.6; white-space: pre-line;">
                {{ props.case.summary }}
            </p>

            <div class="project-detail-panel">
                <div class="detail-panel-heading"><h2>Documents</h2></div>
                <div class="context-table">
                    <div class="context-table-head" style="grid-template-columns: 1.6fr .8fr .8fr;">
                        <span>File</span><span>Type</span><span>Pipeline</span>
                    </div>
                    <div v-for="doc in props.case.documents" :key="doc.id" class="context-table-row" style="grid-template-columns: 1.6fr .8fr .8fr;">
                        <span>{{ doc.filename }}</span>
                        <span>{{ doc.doc_type ?? '—' }}</span>
                        <span class="context-status" :class="['ingested','classified','extracted'].includes(doc.pipeline_status) ? 'ready' : 'pending'">{{ doc.pipeline_status }}</span>
                    </div>
                    <div v-if="props.case.documents.length === 0" class="context-table-row" style="color: var(--cw-muted);">No documents yet</div>
                </div>

                <form @submit.prevent="submit" style="display: flex; align-items: center; gap: 10px; margin-top: 14px;">
                    <input type="file" accept=".pdf,.docx,.txt" @input="form.document = $event.target.files[0]" style="font-size: 11px;" />
                    <PrimaryButton :disabled="form.processing">Add document</PrimaryButton>
                    <InputError :message="form.errors.document" />
                </form>
            </div>

            <div class="project-detail-panel">
                <div class="detail-panel-heading"><h2>Extracted fields</h2></div>
                <div class="context-table">
                    <div class="context-table-head" style="grid-template-columns: 1fr 1fr .6fr;">
                        <span>Field</span><span>Value</span><span>Confidence</span>
                    </div>
                    <div v-for="e in props.case.extractions" :key="e.id" class="context-table-row" style="grid-template-columns: 1fr 1fr .6fr;">
                        <span>{{ e.field_key }}</span><span>{{ e.field_value ?? '—' }}</span><span>{{ e.confidence ?? '—' }}</span>
                    </div>
                    <div v-if="props.case.extractions.length === 0" class="context-table-row" style="color: var(--cw-muted);">No extractions yet</div>
                </div>
            </div>

            <div class="project-detail-panel">
                <div class="detail-panel-heading"><h2>Findings</h2></div>
                <div style="display: grid; gap: 8px;">
                    <div v-for="f in props.case.findings" :key="f.id" style="font-size: 11px;">
                        <strong :style="{ color: severityColor[f.severity] }">[{{ f.severity }}]</strong>
                        {{ f.message }}
                    </div>
                    <div v-if="props.case.findings.length === 0" style="color: var(--cw-muted); font-size: 11px;">No findings yet</div>
                </div>
            </div>

            <div class="project-detail-panel">
                <div class="detail-panel-heading"><h2>Audit log</h2></div>
                <div style="display: grid; gap: 4px;">
                    <div v-for="a in props.case.audit_logs" :key="a.id" style="font-size: 10px; color: var(--cw-muted);">
                        {{ a.created_at }} — [{{ a.actor_type }}] {{ a.action }}
                    </div>
                    <div v-if="props.case.audit_logs.length === 0" style="color: var(--cw-muted); font-size: 11px;">No audit entries yet</div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
