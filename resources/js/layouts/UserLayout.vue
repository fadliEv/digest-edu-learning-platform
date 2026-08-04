<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

// ─── Auth State ───────────────────────────────────────────────────────────────
const page = usePage();
const user = computed(() => page.props.auth?.user as { name: string; email: string } | null);

const initials = computed(() => {
    if (!user.value?.name) return '?';
    return user.value.name
        .split(' ')
        .slice(0, 2)
        .map((w: string) => w[0])
        .join('')
        .toUpperCase();
});

// ─── Dropdown ────────────────────────────────────────────────────────────────
const dropdownOpen = ref(false);
</script>

<template>
    <div class="user-layout">
        <!-- ═══ NAVBAR ═══ -->
        <header class="navbar">
            <div class="navbar__inner">
                <!-- Logo -->
                <Link href="/home" class="navbar__logo">
                    <div class="logo-icon">
                        <Icon icon="healthicons:intestine" width="18" height="18" />
                    </div>
                    <span class="logo-text">Digest<span class="logo-accent">Edu</span></span>
                </Link>

                <!-- Nav Links -->
                <nav class="navbar__nav">
                    <Link href="/home" class="nav-link" :class="{ 'nav-link--active': $page.url.startsWith('/home') }">
                        Beranda
                    </Link>
                    <Link href="/videos" class="nav-link" :class="{ 'nav-link--active': $page.url.startsWith('/videos') }">
                        Materi Video
                    </Link>
                </nav>

                <!-- Right: notif + avatar -->
                <div class="navbar__right">
                    <!-- Notification Bell -->
                    <button class="icon-btn" aria-label="Notifikasi">
                        <Icon icon="lucide:bell" width="18" height="18" />
                    </button>

                    <!-- Avatar Dropdown -->
                    <div class="avatar-wrap" @click="dropdownOpen = !dropdownOpen">
                        <div class="avatar">{{ initials }}</div>
                        <span class="avatar-name">{{ user?.name?.split(' ')[0] }}</span>
                        <Icon icon="lucide:chevron-down" width="14" height="14" class="avatar-chevron" :class="{ 'rotate-180': dropdownOpen }" />

                        <!-- Dropdown Menu -->
                        <Transition name="dropdown">
                            <div v-if="dropdownOpen" class="dropdown-menu" @click.stop>
                                <div class="dropdown-header">
                                    <p class="dropdown-name">{{ user?.name }}</p>
                                    <p class="dropdown-email">{{ user?.email }}</p>
                                </div>
                                <div class="dropdown-divider"></div>
                                <Link href="/profile" class="dropdown-item">
                                    <Icon icon="lucide:user" width="15" height="15" />
                                    Profil Saya
                                </Link>
                                <Link href="/logout" method="post" as="button" class="dropdown-item dropdown-item--danger">
                                    <Icon icon="lucide:log-out" width="15" height="15" />
                                    Keluar
                                </Link>
                            </div>
                        </Transition>
                    </div>
                </div>
            </div>
        </header>

        <!-- Backdrop for dropdown -->
        <div v-if="dropdownOpen" class="dropdown-backdrop" @click="dropdownOpen = false"></div>

        <!-- ═══ PAGE CONTENT ═══ -->
        <main class="page-content">
            <slot />
        </main>

        <!-- ═══ FOOTER ═══ -->
        <footer class="footer">
            <div class="footer__inner">
                <p>© 2026 DigestEdu. Semua hak dilindungi.</p>
                <p>Edukasi kesehatan pencernaan interaktif untuk Indonesia.</p>
            </div>
        </footer>
    </div>
</template>

<style scoped>
.user-layout {
    font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
    background-color: #f8fafc;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

/* ─── Navbar ─────────────────────────────────────────────────────────────── */
.navbar {
    background: #ffffff;
    border-bottom: 1px solid #e2e8f0;
    position: sticky;
    top: 0;
    z-index: 50;
}
.navbar__inner {
    max-width: 1240px;
    margin: 0 auto;
    padding: 0 24px;
    height: 58px;
    display: flex;
    align-items: center;
    gap: 32px;
}
.navbar__logo {
    display: flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    flex-shrink: 0;
}
.logo-icon {
    width: 30px;
    height: 30px;
    background: #0d9488;
    color: #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}
.logo-text {
    font-size: 17px;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.3px;
}
.logo-accent {
    color: #0d9488;
}

/* Nav Links */
.navbar__nav {
    display: flex;
    align-items: center;
    gap: 4px;
}
.nav-link {
    padding: 6px 14px;
    border-radius: 8px;
    font-size: 13.5px;
    font-weight: 600;
    color: #64748b;
    text-decoration: none;
    transition: all 0.15s;
}
.nav-link:hover {
    color: #0f172a;
    background: #f1f5f9;
}
.nav-link--active {
    color: #0d9488;
    background: #f0fdfa;
}

/* Right Side */
.navbar__right {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 8px;
}
.icon-btn {
    width: 36px;
    height: 36px;
    border: none;
    background: none;
    color: #64748b;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.15s;
}
.icon-btn:hover {
    background: #f1f5f9;
    color: #0f172a;
}

/* Avatar */
.avatar-wrap {
    position: relative;
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    padding: 4px 10px 4px 4px;
    border-radius: 100px;
    border: 1px solid #e2e8f0;
    transition: border-color 0.15s;
    user-select: none;
}
.avatar-wrap:hover {
    border-color: #cbd5e1;
}
.avatar {
    width: 28px;
    height: 28px;
    background: #0d9488;
    color: #fff;
    border-radius: 50%;
    font-size: 11px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.avatar-name {
    font-size: 13px;
    font-weight: 600;
    color: #0f172a;
}
.avatar-chevron {
    color: #94a3b8;
    transition: transform 0.2s;
}
.rotate-180 {
    transform: rotate(180deg);
}

/* Dropdown */
.dropdown-menu {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    width: 220px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.1);
    padding: 8px;
    z-index: 100;
}
.dropdown-header {
    padding: 8px 10px;
}
.dropdown-name {
    font-size: 13px;
    font-weight: 700;
    color: #0f172a;
    margin: 0 0 2px;
}
.dropdown-email {
    font-size: 11.5px;
    color: #94a3b8;
    margin: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.dropdown-divider {
    height: 1px;
    background: #f1f5f9;
    margin: 6px 0;
}
.dropdown-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 10px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    color: #0f172a;
    text-decoration: none;
    transition: background 0.15s;
    cursor: pointer;
    width: 100%;
    background: none;
    border: none;
    font-family: inherit;
    text-align: left;
}
.dropdown-item:hover {
    background: #f8fafc;
}
.dropdown-item--danger {
    color: #ef4444;
}
.dropdown-item--danger:hover {
    background: #fff1f2;
}
.dropdown-backdrop {
    position: fixed;
    inset: 0;
    z-index: 49;
}

/* Dropdown Transition */
.dropdown-enter-active { transition: opacity 0.15s ease, transform 0.15s ease; }
.dropdown-leave-active { transition: opacity 0.1s ease, transform 0.1s ease; }
.dropdown-enter-from  { opacity: 0; transform: translateY(-6px); }
.dropdown-leave-to    { opacity: 0; transform: translateY(-4px); }

/* ─── Main Content ───────────────────────────────────────────────────────── */
.page-content {
    max-width: 1240px;
    margin: 0 auto;
    padding: 32px 24px 60px;
    width: 100%;
    flex: 1;
    box-sizing: border-box;
}

/* ─── Footer ─────────────────────────────────────────────────────────────── */
.footer {
    background: #ffffff;
    border-top: 1px solid #e2e8f0;
    padding: 20px 0;
    margin-top: auto;
}
.footer__inner {
    max-width: 1240px;
    margin: 0 auto;
    padding: 0 24px;
    display: flex;
    justify-content: space-between;
    font-size: 12.5px;
    color: #94a3b8;
    flex-wrap: wrap;
    gap: 8px;
}
.footer__inner p {
    margin: 0;
}
</style>
