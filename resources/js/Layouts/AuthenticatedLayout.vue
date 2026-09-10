<script setup>
import { ref, computed, onMounted } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import LoginModal from '@/Components/LoginModal.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { currentTheme, toggleTheme } from '@/theme';
import { showLoginModal, openLoginModal, closeLoginModal } from '@/loginModal';

const page = usePage();
const mobileOpen = ref(false);
const theme = ref(currentTheme());
const projectsOpen = ref(true);

// /home is the only route a guest actually reaches (every other auth-gated
// route bounces back to it) — the interface renders in full, but a guest
// sees the login modal over it immediately, and every gated action here or
// on the page re-opens it via the shared loginModal module.
onMounted(() => {
    if (!page.props.auth.user) openLoginModal();
});

function onToggleTheme() {
    theme.value = toggleTheme();
}

function initials(name) {
    return (name ?? '?')
        .split(' ')
        .map((part) => part[0])
        .filter(Boolean)
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

// CoreAI's navItems minus "projects" (that lives in the expandable tree
// below, not the top nav list) — icons are inline to avoid a dependency.
const navItems = [
    { route: 'home', label: 'Home', icon: 'home' },
    { route: 'scheduled', label: 'Scheduled', icon: 'calendar' },
    { route: 'whatsapp-agents', label: 'WhatsApp Agents', icon: 'whatsapp' },
    { route: 'integrations', label: 'Integrations', icon: 'boxes' },
    { route: 'templates', label: 'Templates', icon: 'layers' },
    { route: 'activity', label: 'Activity', icon: 'activity' },
];

const sidebar = computed(() => page.props.sidebar ?? { recentChats: [], recentCases: [] });

function newAgent() {
    router.visit(route('home'));
}
</script>

<template>
    <div class="coreword-app">
        <div class="sidebar-dim" :class="{ visible: mobileOpen }" @click="mobileOpen = false"></div>

        <aside class="sidebar" :class="{ 'mobile-open': mobileOpen }">
            <div class="sidebar-header">
                <Link :href="route('home')" class="brand">
                    <ApplicationLogo class="brand-mark" />
                    <span>coreAgent</span>
                </Link>
            </div>

            <button class="app-button button-primary new-agent-button" @click="newAgent">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z" /></svg>
                New Agent
            </button>

            <nav class="side-nav">
                <button v-for="item in navItems" :key="item.route" :class="{ active: route().current(item.route) }" @click="router.visit(route(item.route))">
                    <svg v-if="item.icon === 'home'" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z" /></svg>
                    <svg v-else-if="item.icon === 'calendar'" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" /><path d="M16 2v4M8 2v4M3 10h18" /></svg>
                    <svg v-else-if="item.icon === 'whatsapp'" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z" /></svg>
                    <svg v-else-if="item.icon === 'boxes'" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1" /><rect x="14" y="3" width="7" height="7" rx="1" /><rect x="3" y="14" width="7" height="7" rx="1" /><rect x="14" y="14" width="7" height="7" rx="1" /></svg>
                    <svg v-else-if="item.icon === 'layers'" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 2 8 4.5v9L12 20l-8-4.5v-9Z" /><path d="m12 11 8-4.5M12 11v9M12 11 4 6.5" /></svg>
                    <svg v-else width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2" /></svg>
                    <span>{{ item.label }}</span>
                </button>
            </nav>

            <div class="sidebar-groups">
                <section>
                    <div class="sidebar-group-heading">
                        <span>Projects</span>
                        <div>
                            <button @click="projectsOpen = !projectsOpen" aria-label="Toggle project list"><svg :class="{ open: projectsOpen }" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6" /></svg></button>
                            <button @click="router.visit(route('cases.index'))" aria-label="View all projects"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5v14" /></svg></button>
                        </div>
                    </div>
                    <div v-if="projectsOpen" class="project-tree">
                        <div v-if="sidebar.recentCases.length === 0" class="sidebar-empty">No projects yet</div>
                        <div v-for="c in sidebar.recentCases" :key="c.id" class="project-tree-item">
                            <Link :href="route('cases.show', c.id)" :class="{ selected: route().current('cases.show') && String($page.props.case?.id) === String(c.id) }">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z" /></svg>
                                <span>{{ c.reference ?? `Case #${c.id}` }}</span>
                            </Link>
                        </div>
                    </div>
                </section>
            </div>

            <div class="recent-chats">
                <div class="recent-heading"><span>Recent chats</span></div>
                <div v-if="sidebar.recentChats.length === 0" class="recent-chats-empty">未有對話</div>
                <Link
                    v-for="c in sidebar.recentChats"
                    :key="c.id"
                    :href="route('chat.show', c.id)"
                    :class="{ active: route().current('chat.show') && String($page.props.activeConversation?.id) === String(c.id) }"
                >
                    <span class="title">{{ c.title ?? '（未有標題）' }}</span>
                    <span class="status-row">
                        <span class="status-dot" :class="{ processing: c.status === 'processing' }"></span>
                        {{ c.status }}
                    </span>
                </Link>
            </div>

            <div class="sidebar-bottom">
                <button type="button" @click="onToggleTheme" class="theme-toggle-button">
                    <svg v-if="theme === 'dark'" width="16" height="16" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4" /><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41" /></svg>
                    <svg v-else width="16" height="16" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" /></svg>
                    <span>{{ theme === 'dark' ? 'Light mode' : 'Dark mode' }}</span>
                </button>

                <a href="https://altostudio.altodock.com" target="_blank" rel="noopener noreferrer" class="invite-card">
                    <span class="invite-star">✦</span>
                    <span><strong>AltoStudio</strong><small>The team behind coreAgent.</small></span>
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M7 7h10v10" /></svg>
                </a>

                <Dropdown v-if="$page.props.auth.user" align="left" width="48">
                    <template #trigger>
                        <button type="button" class="profile-button">
                            <span class="avatar avatar-small">{{ initials($page.props.auth.user.name) }}</span>
                            <span>
                                <strong>{{ $page.props.auth.user.name }}</strong>
                                <small>{{ $page.props.auth.user.email }}</small>
                            </span>
                        </button>
                    </template>
                    <template #content>
                        <DropdownLink :href="route('profile.edit')">Profile</DropdownLink>
                        <DropdownLink :href="route('logout')" method="post" as="button">Log Out</DropdownLink>
                    </template>
                </Dropdown>
                <button v-else type="button" class="profile-button" @click="showLoginModal = true">
                    <span class="avatar avatar-small">?</span>
                    <span>
                        <strong>Guest</strong>
                        <small>Sign in to save work</small>
                    </span>
                </button>
            </div>
        </aside>

        <LoginModal :show="showLoginModal" @close="showLoginModal = false" />

        <div class="app-main">
            <header class="app-topbar">
                <button type="button" class="mobile-menu" @click="mobileOpen = true" aria-label="Open navigation">
                    <svg width="18" height="18" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M4 12h16M4 18h16" /></svg>
                </button>
                <div class="breadcrumb" style="display: flex; align-items: center; min-width: 0;">
                    <span class="crumb-star">✦</span>
                    <span v-if="$slots.header" style="overflow: hidden; white-space: nowrap; text-overflow: ellipsis; font-size: 12px; font-weight: 700; color: var(--cw-navy);">
                        <slot name="header" />
                    </span>
                </div>
                <div class="topbar-right">
                    <slot name="topbar-right" />
                </div>
            </header>

            <main>
                <slot />
            </main>
        </div>
    </div>
</template>
