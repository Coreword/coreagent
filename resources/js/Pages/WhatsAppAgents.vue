<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    configured: Boolean,
    phoneNumberId: { type: String, default: null },
});
</script>

<template>
    <Head title="WhatsApp Agents" />

    <AuthenticatedLayout>
        <template #header><span>WhatsApp Agents</span></template>

        <section class="whatsapp-page">
            <div class="whatsapp-hero">
                <div class="whatsapp-copy">
                    <span class="orange-eyebrow">CHANNEL</span>
                    <h1>The same agent, <em>on WhatsApp</em>.</h1>
                    <p>Every message a contact sends to your WhatsApp number runs through the exact same agent that answers here — same tools, same DeepSeek/OpenAI/Claude fallback chain, same conversation history, just delivered over WhatsApp instead of the browser.</p>
                </div>
                <div class="whatsapp-status-panel">
                    <div class="whatsapp-status-card">
                        <div style="margin-bottom: 10px;">
                            <span class="whatsapp-status-dot" :class="configured ? 'on' : 'off'"></span>
                            <strong style="font-size: 12px;">{{ configured ? 'Connected' : 'Not configured' }}</strong>
                        </div>
                        <p v-if="configured" style="margin: 0; font-size: 10px; color: var(--cw-muted);">
                            Phone number ID <code style="font-size: 9px;">{{ phoneNumberId }}</code>
                        </p>
                        <p v-else style="margin: 0 0 12px; font-size: 10px; color: var(--cw-muted); line-height: 1.6;">
                            Needs a Meta WhatsApp Business App. See <code style="font-size: 9px;">WHATSAPP_SETUP.md</code> in the repo for the exact steps — creating the Meta App has to be done by hand, it can't be automated.
                        </p>
                    </div>
                </div>
            </div>

            <div class="channel-title" style="margin-top: 40px;">
                <span class="eyebrow-text">HOW IT WORKS</span>
                <h2 style="margin: 5px 0 0; color: var(--cw-navy); font-size: 1.25rem; font-weight: 800; letter-spacing: -.045em;">One agent, three moving parts.</h2>
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
        </section>
    </AuthenticatedLayout>
</template>
