<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

// ─── Props ────────────────────────────────────────────────────────────────────
defineProps<{
    title?: string;
}>();

// ─── Sidebar Collapse ─────────────────────────────────────────────────────────
const sidebarCollapsed = ref(false);

// ─── Nav Items ────────────────────────────────────────────────────────────────
const navItems = [
    { label: 'Dashboard',        icon: 'lucide:layout-dashboard', href: '/admin' },
    { label: 'Manajemen Video',  icon: 'lucide:video',            href: '/admin/videos' },
    { label: 'Manajemen Kuis',   icon: 'lucide:brain-circuit',    href: '/admin/quiz' },
    { label: 'Data Pengguna',    icon: 'lucide:users',            href: '/admin/users' },
    { label: 'Pengaturan',       icon: 'lucide:settings',         href: '/admin/settings' },
];

const page = usePage();
const currentUrl = computed(() => {
    // usePage().url is SSR-safe (no window dependency)
    const u = page.url;
    // Strip query string — keep only pathname
    return u.split('?')[0];
});

function isActive(href: string): boolean {
    const url = currentUrl.value;
    if (href === '/admin') return url === '/admin';
    // /admin/quiz should also be active when on /admin/videos/{id}/quiz
    if (href === '/admin/quiz') return url === '/admin/quiz' || /^\/admin\/videos\/\d+\/quiz/.test(url);
    // /admin/videos should NOT match /admin/videos/{id}/quiz pages
    if (href === '/admin/videos') return url.startsWith('/admin/videos') && !/^\/admin\/videos\/\d+\/quiz/.test(url);
    return url.startsWith(href);
}

function logout() {
    router.post('/logout');
}
</script>

<template>
    <div class="admin-layout">
        <!-- ═══ SIDEBAR ═══ -->
        <aside :class="['sidebar', { 'sidebar--collapsed': sidebarCollapsed }]">
            <!-- Brand -->
            <div class="sidebar__brand">
                <div class="brand-icon">
                    <Icon icon="healthicons:intestine" width="18" height="18" />
                </div>
                <span v-if="!sidebarCollapsed" class="brand-name">
                    Digest<span class="brand-accent">Edu</span>
                </span>
            </div>

            <!-- Toggle Button -->
            <button class="sidebar__toggle" @click="sidebarCollapsed = !sidebarCollapsed">
                <Icon :icon="sidebarCollapsed ? 'lucide:chevron-right' : 'lucide:chevron-left'" width="14" height="14" />
            </button>

            <!-- Nav -->
            <nav class="sidebar__nav">
                <p v-if="!sidebarCollapsed" class="nav-section-label">MENU</p>

                <Link
                    v-for="item in navItems"
                    :key="item.href"
                    :href="item.href"
                    :class="['sidebar-link', { 'sidebar-link--active': isActive(item.href) }]"
                    :title="sidebarCollapsed ? item.label : ''"
                >
                    <Icon :icon="item.icon" width="17" height="17" class="sidebar-link__icon" />
                    <span v-if="!sidebarCollapsed" class="sidebar-link__label">{{ item.label }}</span>
                </Link>
            </nav>

            <!-- Logout -->
            <button class="sidebar__logout" @click="logout">
                <Icon icon="lucide:log-out" width="16" height="16" />
                <span v-if="!sidebarCollapsed">Keluar</span>
            </button>
        </aside>

        <!-- ═══ MAIN AREA ═══ -->
        <div class="admin-main">
            <!-- Top Bar -->
            <header class="admin-topbar">
                <div class="topbar__left">
                    <h1 class="topbar__title">{{ title }}</h1>
                </div>
                <div class="topbar__right">
                    <slot name="topbar-actions" />
                </div>
            </header>

            <!-- Page Content -->
            <main class="admin-content">
                <slot />
            </main>
        </div>
    </div>
</template>

<style scoped>
.admin-layout {
    font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
    display: flex;
    min-height: 100vh;
    background: #f8fafc;
}

/* ─── Sidebar ─────────────────────────────────────────────────────────────── */
.sidebar {
    width: 200px;
    min-height: 100vh;
    background: #ffffff;
    border-right: 1px solid #e2e8f0;
    display: flex;
    flex-direction: column;
    padding: 20px 12px;
    gap: 4px;
    position: sticky;
    top: 0;
    height: 100vh;
    overflow-y: auto;
    transition: width 0.25s ease;
    flex-shrink: 0;
    box-sizing: border-box;
}
.sidebar--collapsed {
    width: 64px;
}

/* Brand */
.sidebar__brand {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 4px 8px 16px;
    border-bottom: 1px solid #f1f5f9;
    margin-bottom: 8px;
    overflow: hidden;
}
.brand-icon {
    width: 30px;
    height: 30px;
    background: #0d9488;
    color: #ffffff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.brand-name {
    font-size: 16px;
    font-weight: 800;
    color: #0f172a;
    white-space: nowrap;
}
.brand-accent {
    color: #0d9488;
}

/* Toggle */
.sidebar__toggle {
    position: absolute;
    top: 24px;
    right: -12px;
    width: 24px;
    height: 24px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: #64748b;
    z-index: 10;
    transition: all 0.15s;
}
.sidebar__toggle:hover {
    background: #f1f5f9;
    color: #0f172a;
}

/* Nav */
.sidebar__nav {
    display: flex;
    flex-direction: column;
    gap: 2px;
    flex: 1;
}
.nav-section-label {
    font-size: 10.5px;
    font-weight: 700;
    color: #94a3b8;
    letter-spacing: 0.8px;
    padding: 0 8px;
    margin: 8px 0 6px;
}
.sidebar-link {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 10px;
    border-radius: 10px;
    font-size: 13.5px;
    font-weight: 600;
    color: #475569;
    text-decoration: none;
    transition: all 0.15s;
    white-space: nowrap;
    overflow: hidden;
}
.sidebar-link:hover {
    background: #f1f5f9;
    color: #0f172a;
}
.sidebar-link--active {
    background: #f0fdfa;
    color: #0d9488;
}
.sidebar-link__icon {
    flex-shrink: 0;
}

/* Logout */
.sidebar__logout {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 10px;
    border-radius: 10px;
    font-size: 13.5px;
    font-weight: 600;
    color: #ef4444;
    background: none;
    border: none;
    cursor: pointer;
    font-family: inherit;
    transition: background 0.15s;
    white-space: nowrap;
    overflow: hidden;
    width: 100%;
    text-align: left;
}
.sidebar__logout:hover {
    background: #fff1f2;
}

/* ─── Main Area ──────────────────────────────────────────────────────────── */
.admin-main {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
}

/* Top Bar */
.admin-topbar {
    background: #ffffff;
    border-bottom: 1px solid #e2e8f0;
    padding: 0 32px;
    height: 58px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    position: sticky;
    top: 0;
    z-index: 40;
}
.topbar__title {
    font-size: 17px;
    font-weight: 800;
    color: #0f172a;
    margin: 0;
    letter-spacing: -0.3px;
}
.topbar__right {
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Content */
.admin-content {
    padding: 28px 32px 48px;
    flex: 1;
}
</style>
