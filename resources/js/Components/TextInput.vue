<script setup>
import { onMounted, ref } from 'vue';

const model = defineModel({
    type: String,
    required: true,
});

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        class="rounded-md border-gray-300 shadow-sm focus:border-[#2f66db] focus:ring-[#2f66db] dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-[#2f66db] dark:focus:ring-[#2f66db]"
        v-model="model"
        ref="input"
    />
</template>
