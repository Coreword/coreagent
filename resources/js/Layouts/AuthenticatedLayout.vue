<script setup>
import { ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import { Link } from '@inertiajs/vue3';
import { currentTheme, toggleTheme } from '@/theme';

const mobileOpen = ref(false);
const theme = ref(currentTheme());

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
</script>

<template>
    <div class="coreword-app">
        <div class="sidebar-dim" :class="{ visible: mobileOpen }" @click="mobileOpen = false"></div>

        <aside class="sidebar" :class="{ 'mobile-open': mobileOpen }">
            <div class="sidebar-header">
                <Link :href="route('chat.index')" class="brand">
                    <ApplicationLogo class="brand-mark fill-current" />
                    <span>coreAgent</span>
                </Link>
            </div>

            <nav class="side-nav">
                <Link :href="route('chat.index')" :class="{ active: route().current('chat.*') }">
                    AI Chat
                </Link>
                <Link :href="route('cases.index')" :class="{ active: route().current('cases.*') }">
                    Cases
                </Link>
                <a href="https://altostudio.altodock.com" target="_blank" rel="noopener noreferrer">
                    AltoStudio ↗
                </a>
            </nav>

            <div v-if="route().current('chat.*')" class="recent-chats">
                <div class="recent-heading"><span>Recent chats</span></div>
                <slot name="recent-chats" />
            </div>

            <div class="sidebar-bottom">
                <button type="button" @click="onToggleTheme" class="icon-button" style="width: 100%; justify-content: flex-start; gap: 8px; padding-inline: 8px;">
                    <svg v-if="theme === 'dark'" width="16" height="16" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4" /><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41" /></svg>
                    <svg v-else width="16" height="16" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" /></svg>
                    <span style="font-size: 11px; font-weight: 700;">{{ theme === 'dark' ? 'Light mode' : 'Dark mode' }}</span>
                </button>

                <Dropdown align="left" width="48">
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
            </div>
        </aside>

        <div class="app-main">
            <header class="app-topbar">
                <button type="button" class="mobile-menu" @click="mobileOpen = true" aria-label="Open navigation">
                    <svg width="18" height="18" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M4 12h16M4 18h16" /></svg>
                </button>
                <div v-if="$slots.header" style="min-width: 0; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; font-size: 13px; font-weight: 700; color: var(--cw-navy);">
                    <slot name="header" />
                </div>
                <div v-else></div>
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
