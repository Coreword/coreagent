<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Scheduled" />

    <AuthenticatedLayout>
        <template #header><span>Scheduled</span></template>

        <section class="page-shell">
            <div class="page-heading">
                <div>
                    <span class="eyebrow-text">RECURRING WORK</span>
                    <h1>Scheduled</h1>
                    <p>Run a task on a cadence instead of on demand.</p>
                </div>
            </div>

            <div class="scheduled-layout">
                <div class="schedule-table-wrap">
                    <div class="schedule-empty">
                        <div style="display: grid; place-items: center; width: 52px; height: 52px; margin-bottom: 4px; color: var(--cw-blue); background: var(--cw-blue-soft); border-radius: 17px;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" /><path d="M16 2v4M8 2v4M3 10h18" /></svg>
                        </div>
                        <strong>Not built yet</strong>
                        <p>Scheduling — a task that repeats on a cadence rather than running once — isn't wired up in coreAgent. This is here so the nav matches CoreAI's full layout, honestly marked rather than faked.</p>
                    </div>
                </div>
                <div class="summary-card">
                    <h3>This week</h3>
                    <div class="summary-stat"><strong>0</strong><span>scheduled tasks</span></div>
                    <div class="summary-stat"><strong>0</strong><span>runs completed</span></div>
                </div>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
