<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center pt-6 sm:justify-center sm:pt-0"
        style="background: var(--cw-cloud);"
    >
        <div class="flex items-center gap-2">
            <Link href="/" class="flex items-center gap-2">
                <ApplicationLogo class="h-10 w-10" style="color: var(--cw-blue);" />
                <span style="font-weight: 800; letter-spacing: -.04em; color: var(--cw-navy);">coreAgent</span>
            </Link>
        </div>

        <div
            class="mt-6 w-full overflow-hidden px-6 py-4 shadow-md sm:max-w-md sm:rounded-lg"
            style="background: var(--cw-surface); border: 1px solid var(--cw-border);"
        >
            <slot />
        </div>
    </div>
</template>
