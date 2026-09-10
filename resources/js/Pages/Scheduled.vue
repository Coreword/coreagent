<script setup>
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

// coreAgent has no scheduling backend yet — this is a visual mock of the
// reference design's Scheduled page, not real data. It exists so the nav's
// full layout is in place; wiring it up to actual recurring runs is future
// work, not something to fake with a live-looking API response.
const schedules = [
    { name: 'Competitor Price Monitor', icon: 'calendar', trigger: 'Every day', nextRun: 'Today, 16:30', typical: '1 min', status: 'live' },
    { name: 'Weekly Market Report', icon: 'calendar', trigger: 'Every Monday', nextRun: 'Mon, 08:00', typical: '5 min', status: 'live' },
    { name: 'Lead Follow-up Agent', icon: 'calendar', trigger: 'Every weekday', nextRun: 'Mon, 09:15', typical: '3 min', status: 'paused' },
    { name: 'Job Listing Scraper', icon: 'calendar', trigger: 'Every day', nextRun: 'Tomorrow, 08:00', typical: '2 min', status: 'live' },
    { name: 'Onboarding Email Series', icon: 'calendar', trigger: 'Every day', nextRun: '—', typical: '1 min', status: 'finished' },
];

const upcomingRuns = [
    { name: 'Competitor Price Monitor', at: 'Today · 16:30', color: 'var(--cw-blue)' },
    { name: 'Weekly Market Report', at: 'Monday · 08:00', color: 'var(--cw-orange)' },
    { name: 'Job Listing Scraper', at: 'Tomorrow · 08:00', color: 'var(--cw-green)' },
];

const tabs = ['All schedules', 'Live', 'Paused', 'Finished'];
const activeTab = ref('All schedules');

const visibleSchedules = computed(() => {
    if (activeTab.value === 'All schedules') return schedules;
    return schedules.filter((s) => s.status === activeTab.value.toLowerCase());
});

const liveCount = computed(() => schedules.filter((s) => s.status === 'live').length);
const nextRunTodayCount = computed(() => schedules.filter((s) => s.nextRun.startsWith('Today')).length);

const statusMap = {
    live: { label: 'Live', class: 'status-live' },
    paused: { label: 'Paused', class: 'status-processing' },
    finished: { label: 'Finished', class: 'status-draft' },
};
function statusFor(status) {
    return statusMap[status] ?? { label: status, class: 'status-draft' };
}
</script>

<template>
    <Head title="Scheduled" />

    <AuthenticatedLayout>
        <template #header><span>Scheduled</span></template>

        <section class="page-shell">
            <div class="page-heading">
                <div>
                    <span class="eyebrow-text">OPERATIONS</span>
                    <h1>Scheduled</h1>
                    <p>Give recurring work a dependable rhythm, with every run visible in one place.</p>
                </div>
                <div class="page-heading-actions">
                    <button type="button" class="app-button button-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5v14" /></svg>
                        Create schedule
                    </button>
                </div>
            </div>

            <div class="scheduled-layout">
                <div class="schedule-table-wrap">
                    <div class="schedule-tabs-row">
                        <div class="schedule-tabs">
                            <button v-for="t in tabs" :key="t" type="button" class="schedule-tab" :class="{ active: activeTab === t }" @click="activeTab = t">{{ t }}</button>
                        </div>
                        <button type="button" class="filter-button">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M7 12h10M10 18h4" /></svg>
                            Filters
                        </button>
                    </div>

                    <table v-if="visibleSchedules.length" class="schedule-table">
                        <thead>
                            <tr>
                                <th>Schedule</th>
                                <th>Trigger</th>
                                <th>Next run</th>
                                <th>Typical run</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="s in visibleSchedules" :key="s.name">
                                <td>
                                    <div class="schedule-name-cell">
                                        <span class="schedule-name-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" /><path d="M16 2v4M8 2v4M3 10h18M12 14v4M10 16h4" /></svg></span>
                                        {{ s.name }}
                                    </div>
                                </td>
                                <td>{{ s.trigger }}</td>
                                <td>{{ s.nextRun }}</td>
                                <td>{{ s.typical }}</td>
                                <td><span class="status-pill" :class="statusFor(s.status).class">{{ statusFor(s.status).label }}</span></td>
                                <td><button type="button" class="schedule-row-menu" aria-label="Schedule options">⋯</button></td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-else class="schedule-empty">
                        <div style="display: grid; place-items: center; width: 52px; height: 52px; margin-bottom: 4px; color: var(--cw-blue); background: var(--cw-blue-soft); border-radius: 17px;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" /><path d="M16 2v4M8 2v4M3 10h18" /></svg>
                        </div>
                        <strong>Nothing here yet</strong>
                        <p>No schedules match this filter.</p>
                    </div>
                </div>

                <div>
                    <div class="summary-card">
                        <div class="summary-icon-badge"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-2.64-6.36" /><path d="M21 3v6h-6" /></svg></div>
                        <span class="eyebrow-text">SCHEDULE SUMMARY</span>
                        <h3>Work is in motion.</h3>
                        <div class="summary-stat"><strong>{{ liveCount }}</strong><span>live schedules</span></div>
                        <div class="summary-stat"><strong>{{ nextRunTodayCount }}</strong><span>next run today</span></div>
                    </div>

                    <div class="summary-card upcoming-runs-card">
                        <div class="upcoming-runs-header">
                            <h3>Upcoming runs</h3>
                            <span class="eyebrow-text" style="text-transform: none; letter-spacing: normal; color: var(--cw-blue-strong); cursor: pointer;">View all</span>
                        </div>
                        <div v-for="r in upcomingRuns" :key="r.name" class="upcoming-run-row">
                            <span class="run-dot" :style="{ background: r.color }"></span>
                            <div>
                                <strong>{{ r.name }}</strong>
                                <span>{{ r.at }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
