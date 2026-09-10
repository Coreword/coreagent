<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';

defineProps({
    configured: Boolean,
    phoneNumberId: { type: String, default: null },
});

const playbooks = [
    {
        title: 'New enquiry qualifier',
        description: 'Ask the right questions, identify intent, and pass a considered summary to sales.',
        icon: 'message',
        color: 'orange',
        prompt: '有個新嘅 WhatsApp 查詢，幫我問清楚需求，再總結畀 sales team。',
    },
    {
        title: 'Customer support guide',
        description: 'Answer with product knowledge, then hand over the conversation when nuance matters.',
        icon: 'book',
        color: 'blue',
        prompt: '幫我根據產品知識回覆呢個客戶查詢，如果太複雜就話我知要轉真人跟進。',
    },
    {
        title: 'Booking assistant',
        description: 'Confirm availability, collect essentials, and keep the conversation moving.',
        icon: 'calendar',
        color: 'green',
        prompt: '幫我同呢位客戶確認預約時間，收集必要資料。',
    },
];

function usePlaybook(p) {
    router.visit(route('home', { prompt: p.prompt }));
}
</script>

<template>
    <Head title="WhatsApp Agents" />

    <AuthenticatedLayout>
        <template #header><span>WhatsApp Agents</span></template>

        <section class="whatsapp-page">
            <div class="whatsapp-hero">
                <div class="whatsapp-copy">
                    <span class="orange-eyebrow">CHANNEL AGENTS</span>
                    <h1>A helpful agent,<br><span style="color: var(--cw-blue-strong);">where conversations begin.</span></h1>
                    <p>Turn your team's knowledge and best responses into a thoughtful WhatsApp experience that stays on task.</p>

                    <div class="whatsapp-status-pill">
                        <span class="whatsapp-status-dot" :class="configured ? 'on' : 'off'"></span>
                        {{ configured ? `Connected · ${phoneNumberId}` : 'Not configured' }}
                    </div>

                    <div class="app-button-row">
                        <button type="button" class="app-button button-orange" @click="router.visit(route('whatsapp-agents'))">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5v14" /></svg>
                            {{ configured ? 'Manage agent' : 'Create an agent' }}
                        </button>
                        <button type="button" class="app-button button-secondary" @click="router.visit(route('templates'))">
                            Explore templates
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M7 7h10v10" /></svg>
                        </button>
                    </div>

                    <div class="whatsapp-trust-row">
                        <span><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg> Prompted by your policy</span>
                        <span><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg> Escalates to people</span>
                    </div>
                </div>
                <div class="whatsapp-illustration">
                    <div class="wa-stack">
                        <div class="wa-line light"></div>
                        <div class="wa-line mid"></div>
                        <div class="wa-line dark"></div>
                        <span class="wa-dot green"></span>
                        <span class="wa-dot orange"></span>
                        <div class="wa-bubble top">
                            <span class="wa-bubble-label">New enquiry</span>
                            <p>Can you help me choose?</p>
                        </div>
                        <div class="wa-bubble bottom">
                            <span class="wa-bubble-label">coreAgent</span>
                            <p>Absolutely. A few quick details first…</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="section-title-row" style="margin-top: 40px;">
                <div>
                    <span class="eyebrow-text">START WITH A PLAYBOOK</span>
                    <h2>Make a conversation useful from day one.</h2>
                </div>
                <button type="button" class="text-action" @click="router.visit(route('templates'))">
                    View all templates
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M7 7h10v10" /></svg>
                </button>
            </div>
            <div class="agent-template-grid">
                <button type="button" v-for="p in playbooks" :key="p.title" class="agent-template" @click="usePlaybook(p)">
                    <div class="agent-template-icon" :class="p.color">
                        <svg v-if="p.icon === 'message'" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5Z" /></svg>
                        <svg v-else-if="p.icon === 'book'" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" /><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z" /></svg>
                        <svg v-else width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" /><path d="M16 2v4M8 2v4M3 10h18M12 14v4M10 16h4" /></svg>
                    </div>
                    <h3>{{ p.title }}</h3>
                    <p>{{ p.description }}</p>
                </button>
            </div>

            <div class="section-title-row" style="margin-top: 44px;">
                <div><span class="eyebrow-text">HOW IT WORKS</span><h2>One agent, three moving parts.</h2></div>
            </div>
            <div class="agent-template-grid">
                <div class="agent-template">
                    <div class="agent-template-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2Z" /></svg></div>
                    <h3>Inbound webhook</h3>
                    <p>Meta calls coreAgent's <code>/webhooks/whatsapp</code> for every message; the signature is verified before anything else runs.</p>
                </div>
                <div class="agent-template">
                    <div class="agent-template-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z" /></svg></div>
                    <h3>Same agent loop</h3>
                    <p>The number gets its own ongoing conversation and runs through the identical AgentOrchestrator as the web chat.</p>
                </div>
                <div class="agent-template">
                    <div class="agent-template-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2 11 13" /><path d="M22 2 15 22l-4-9-9-4Z" /></svg></div>
                    <h3>Outbound reply</h3>
                    <p>The final answer is sent back through the Cloud API — markdown stripped down to WhatsApp's own formatting.</p>
                </div>
            </div>

            <p v-if="!configured" style="max-width: 600px; margin: 18px 0 0; color: var(--cw-muted); font-size: 10px; line-height: 1.6;">
                Needs a Meta WhatsApp Business App. See <code style="font-size: 9px;">WHATSAPP_SETUP.md</code> in the repo for the exact steps — creating the Meta App has to be done by hand, it can't be automated.
            </p>
        </section>
    </AuthenticatedLayout>
</template>
