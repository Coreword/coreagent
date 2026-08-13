<script setup>
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
    blocker: 'text-red-600 dark:text-red-400',
    warning: 'text-amber-600 dark:text-amber-400',
    info: 'text-blue-600 dark:text-blue-400',
};
</script>

<template>
    <Head :title="`Case #${props.case.id}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Case #{{ props.case.id }} — {{ props.case.vertical }}
                </h2>
                <Link :href="route('cases.index')" class="text-sm text-indigo-600 hover:underline">← 返去列表</Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg dark:bg-gray-800">
                    <div class="flex items-center gap-4">
                        <span class="text-sm text-gray-500">Status:</span>
                        <span class="rounded bg-gray-100 px-2 py-1 text-sm font-medium dark:bg-gray-700 dark:text-gray-200">
                            {{ props.case.status }}
                        </span>
                        <span v-if="props.case.status === 'awaiting_api_key'" class="text-sm text-amber-600 dark:text-amber-400">
                            未設定 OPENAI_API_KEY，Classify(LLM)/Extract/Summarise 已暫停 — 喺 .env 填入 key 之後重新 dispatch job 就會繼續。
                        </span>
                    </div>
                    <p v-if="props.case.summary" class="mt-4 whitespace-pre-line text-sm text-gray-800 dark:text-gray-200">
                        {{ props.case.summary }}
                    </p>
                </div>

                <div class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg dark:bg-gray-800">
                    <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-gray-100">文件</h3>
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr>
                                <th class="px-2 py-1 text-left text-xs uppercase text-gray-500">檔案</th>
                                <th class="px-2 py-1 text-left text-xs uppercase text-gray-500">doc_type</th>
                                <th class="px-2 py-1 text-left text-xs uppercase text-gray-500">Pipeline status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="doc in props.case.documents" :key="doc.id">
                                <td class="px-2 py-1 text-sm text-gray-900 dark:text-gray-300">{{ doc.filename }}</td>
                                <td class="px-2 py-1 text-sm text-gray-900 dark:text-gray-300">{{ doc.doc_type ?? '—' }}</td>
                                <td class="px-2 py-1 text-sm text-gray-900 dark:text-gray-300">{{ doc.pipeline_status }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <form @submit.prevent="submit" class="mt-4 flex items-center gap-3">
                        <input
                            type="file"
                            accept=".pdf,.docx,.txt"
                            @input="form.document = $event.target.files[0]"
                            class="text-sm text-gray-900 dark:text-gray-300"
                        />
                        <PrimaryButton :disabled="form.processing">加多份文件</PrimaryButton>
                        <InputError :message="form.errors.document" />
                    </form>
                </div>

                <div class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg dark:bg-gray-800">
                    <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-gray-100">抽取欄位</h3>
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr>
                                <th class="px-2 py-1 text-left text-xs uppercase text-gray-500">欄位</th>
                                <th class="px-2 py-1 text-left text-xs uppercase text-gray-500">值</th>
                                <th class="px-2 py-1 text-left text-xs uppercase text-gray-500">信心值</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="e in props.case.extractions" :key="e.id">
                                <td class="px-2 py-1 text-sm text-gray-900 dark:text-gray-300">{{ e.field_key }}</td>
                                <td class="px-2 py-1 text-sm text-gray-900 dark:text-gray-300">{{ e.field_value ?? '—' }}</td>
                                <td class="px-2 py-1 text-sm text-gray-900 dark:text-gray-300">{{ e.confidence ?? '—' }}</td>
                            </tr>
                            <tr v-if="props.case.extractions.length === 0">
                                <td colspan="3" class="px-2 py-4 text-center text-sm text-gray-500">未有抽取結果</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg dark:bg-gray-800">
                    <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-gray-100">Findings</h3>
                    <ul class="space-y-2">
                        <li v-for="f in props.case.findings" :key="f.id" class="text-sm">
                            <span class="font-semibold" :class="severityColor[f.severity]">[{{ f.severity }}]</span>
                            {{ f.message }}
                        </li>
                        <li v-if="props.case.findings.length === 0" class="text-sm text-gray-500">未有 findings</li>
                    </ul>
                </div>

                <div class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg dark:bg-gray-800">
                    <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-gray-100">Audit Log</h3>
                    <ul class="space-y-1">
                        <li v-for="a in props.case.audit_logs" :key="a.id" class="text-xs text-gray-600 dark:text-gray-400">
                            {{ a.created_at }} — [{{ a.actor_type }}] {{ a.action }}
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
