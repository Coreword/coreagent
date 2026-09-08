<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    items: Array,
    stats: Object,
});

function relativeTime(iso) {
    const diffMs = Date.now() - new Date(iso).getTime();
    const mins = Math.round(diffMs / 60000);
    if (mins < 1) return 'just now';
    if (mins < 60) return `${mins}m ago`;
    const hours = Math.round(mins / 60);
    if (hours < 24) return `${hours}h ago`;
    return `${Math.round(hours / 24)}d ago`;
}
</script>

<template>
    <Head title="Activity" />

    <AuthenticatedLayout>
        <template #header><span>Activity</span></template>

        <section class="page-shell">
            <div class="page-heading">
                <div>
                    <span class="eyebrow-text">RECENT WORK</span>
                    <h1>Activity</h1>
                    <p>Your recent messages and every project's creation, merged into one timeline.</p>
                </div>
            </div>

            <div class="activity-overview">
                <div><span>Messages sent</span><strong>{{ stats.messages }}</strong></div>
                <div><span>Projects</span><strong>{{ stats.cases }}</strong></div>
                <div><span>Conversations</span><strong>{{ stats.conversations }}</strong></div>
            </div>

            <div class="activity-list">
                <div class="activity-list-header"><strong>Recent</strong></div>
                <Link v-for="item in items" :key="`${item.type}-${item.id}`" :href="item.href" class="activity-row" style="text-decoration: none;">
                    <div class="activity-icon" :class="item.icon">
                        <svg v-if="item.type === 'message'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2Z" /></svg>
                        <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z" /></svg>
                    </div>
                    <div>
                        <strong>{{ item.title }}</strong>
                        <span>{{ item.subtitle }}</span>
                    </div>
                    <time>{{ relativeTime(item.at) }}</time>
                </Link>
                <div v-if="items.length === 0" style="padding: 40px; text-align: center; color: var(--cw-muted); font-size: 11px;">
                    Nothing yet — send a message or create a project to see it here.
                </div>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
