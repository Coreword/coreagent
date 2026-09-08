<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    cases: Array,
    verticals: Array,
});

const form = useForm({
    vertical: props.verticals[0] ?? '',
    reference: '',
    document: null,
});

function submit() {
    form.post(route('cases.store'), {
        forceFormData: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <Head title="Cases" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Document Copilot — Cases
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg dark:bg-gray-800">
                    <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-gray-100">
                        新增個案 + 上傳文件
                    </h3>
                    <form @submit.prevent="submit" class="space-y-4">
                        <div>
                            <InputLabel for="vertical" value="Vertical" />
                            <select
                                id="vertical"
                                v-model="form.vertical"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                            >
                                <option v-for="v in verticals" :key="v" :value="v">{{ v }}</option>
                            </select>
                            <InputError :message="form.errors.vertical" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="reference" value="Reference（可留空）" />
                            <TextInput
                                id="reference"
                                v-model="form.reference"
                                type="text"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="form.errors.reference" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="document" value="文件（PDF / DOCX / TXT，20MB 以內）" />
                            <input
                                id="document"
                                type="file"
                                accept=".pdf,.docx,.txt"
                                @input="form.document = $event.target.files[0]"
                                class="mt-1 block w-full text-sm text-gray-900 dark:text-gray-300"
                            />
                            <InputError :message="form.errors.document" class="mt-2" />
                        </div>

                        <PrimaryButton :disabled="form.processing">上傳並建立個案</PrimaryButton>
                    </form>
                </div>

                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">ID</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Vertical</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Reference</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">文件數</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="c in cases" :key="c.id">
                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-300">{{ c.id }}</td>
                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-300">{{ c.vertical }}</td>
                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-300">{{ c.reference ?? '—' }}</td>
                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-300">{{ c.status }}</td>
                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-300">{{ c.documents_count }}</td>
                                <td class="px-4 py-2 text-right text-sm">
                                    <Link :href="route('cases.show', c.id)" style="color: var(--cw-blue-strong);" class="hover:underline">睇結果</Link>
                                </td>
                            </tr>
                            <tr v-if="cases.length === 0">
                                <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500">未有個案</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
