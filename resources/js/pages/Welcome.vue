<script setup lang="ts">
import AuthModal from '@/components/AuthModal.vue';
import { Icon } from '@iconify/vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

// ─── Props ─────────────────────────────────────────────────────────────────
const props = defineProps<{
    canLogin?: boolean;
    canRegister?: boolean;
    auth?: { user?: { name: string } | null };
    showAuthModal?: 'login' | 'register';
    errors?: Record<string, string>;
    name?: string;
    latestVideos?: {
        id: number;
        title: string;
        category: string;
        duration: string;
        views: string;
        thumbnail: string | null;
        isNew: boolean;
    }[];
}>();

// ─── Auth Modal State ───────────────────────────────────────────────────────
const isAuthModalOpen = ref(false);
const authModalMode = ref<'login' | 'register'>('login');

function openLoginModal() {
    authModalMode.value = 'login';
    isAuthModalOpen.value = true;
}

function openRegisterModal() {
    authModalMode.value = 'register';
    isAuthModalOpen.value = true;
}

onMounted(() => {
    const path = window.location.pathname;
    if (path === '/login') {
        authModalMode.value = 'login';
        isAuthModalOpen.value = true;
    } else if (path === '/register') {
        authModalMode.value = 'register';
        isAuthModalOpen.value = true;
    }
});

// ─── Category Filter ────────────────────────────────────────────────────────
const categories = ['Semua', 'Nutrisi', 'Anatomi', 'Pencegahan', 'Penyakit', 'Gaya Hidup'] as const;
const activeCategory = ref<string>('Semua');

// ─── Video Data (dari DB via props) ─────────────────────────────────────────
const filteredVideosList = computed(() => {
    const videos = props.latestVideos ?? [];
    return activeCategory.value === 'Semua'
        ? videos
        : videos.filter((v) => v.category === activeCategory.value);
});

// ─── Stats ───────────────────────────────────────────────────────────────────
const stats = [
    { num: '48+', label: 'Video Materi', icon: 'lucide:play-circle' },
    { num: '2.4K', label: 'Pelajar Aktif', icon: 'lucide:users' },
    { num: '12', label: 'Modul Topik', icon: 'lucide:layers' },
    { num: '96%', label: 'Tingkat Kepuasan', icon: 'lucide:star' },
];
</script>

<template>
    <Head title="DigestEdu — Platform Edukasi Kesehatan Pencernaan">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="" />
        <link
            href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
            rel="stylesheet"
        />
    </Head>

    <div class="page-root">
        <!-- ═══════════════════ NAVBAR ═══════════════════ -->
        <header class="navbar">
            <div class="navbar__inner">
                <!-- Logo -->
                <Link href="/" class="navbar__logo">
                    <div class="logo-icon">
                        <Icon icon="healthicons:intestine" width="20" height="20" />
                    </div>
                    <span class="logo-text">Digest<span class="logo-text--accent">Edu</span></span>
                </Link>

                <!-- Auth Actions -->
                <div class="navbar__actions">
                    <template v-if="auth?.user">
                        <Link href="/dashboard" class="btn btn--ghost">Dashboard</Link>
                    </template>
                    <template v-else>
                        <button type="button" class="btn btn--outline" @click="openLoginModal">Masuk</button>
                        <button type="button" class="btn btn--primary" @click="openRegisterModal">Daftar</button>
                    </template>
                </div>
            </div>
        </header>

        <!-- ═══════════════════ MAIN ═══════════════════ -->
        <main class="main">
            <!-- ── Hero Banner ── -->
            <section class="hero">
                <div class="hero__content">
                    <!-- Badge -->
                    <div class="hero__badge">
                        <Icon icon="lucide:book-open" width="14" height="14" />
                        Platform Edukasi Pencernaan Terpercaya
                    </div>

                    <h1 class="hero__title">
                        Jaga Ususmu,<br />
                        Jaga Masa Depanmu.
                    </h1>

                    <p class="hero__subtitle">
                        Pelajari cara merawat sistem pencernaan dan asupan gizi secara interaktif — dengan video, kuis
                        evaluasi, dan materi berbasis sains.
                    </p>

                    <!-- CTA -->
                    <button type="button" class="hero__cta" @click="openRegisterModal">
                        <Icon icon="lucide:play" width="16" height="16" />
                        Mulai Belajar
                    </button>

                    <div class="hero__divider"></div>

                    <!-- Stats -->
                    <div class="hero__stats">
                        <div v-for="stat in stats" :key="stat.label" class="stat">
                            <div class="stat__num">{{ stat.num }}</div>
                            <div class="stat__label">{{ stat.label }}</div>
                        </div>
                    </div>
                </div>

                <!-- Decorative circles -->
                <div class="hero__deco hero__deco--1"></div>
                <div class="hero__deco hero__deco--2"></div>
                <div class="hero__deco hero__deco--3"></div>
            </section>

            <!-- ── Video Terbaru ── -->
            <section class="materi">
                <!-- Section Header -->
                <div class="materi__header">
                    <div>
                        <h2 class="materi__title">Video Terbaru</h2>
                        <p class="materi__subtitle">Konten edukasi terkini dari tim ahli kami</p>
                    </div>
                    <button type="button" class="materi__see-all" @click="openLoginModal">
                        Lihat Semua
                        <Icon icon="lucide:chevron-right" width="16" height="16" />
                    </button>
                </div>

                <!-- Category Filter -->
                <div class="filter-bar">
                    <button
                        v-for="cat in categories"
                        :key="cat"
                        :class="['filter-btn', { 'filter-btn--active': activeCategory === cat }]"
                        @click="activeCategory = cat"
                    >
                        {{ cat }}
                    </button>
                </div>

                <!-- Video Grid -->
                <div class="video-grid" v-if="filteredVideosList.length > 0">
                    <div
                        v-for="video in filteredVideosList"
                        :key="video.id"
                        class="video-card"
                        role="button"
                        tabindex="0"
                        @click="openLoginModal"
                        @keydown.enter="openLoginModal"
                    >
                        <!-- Thumbnail -->
                        <div class="video-card__media">
                            <img
                                v-if="video.thumbnail"
                                :src="video.thumbnail"
                                :alt="video.title"
                                class="video-card__img"
                                loading="lazy"
                            />
                            <div v-else class="video-card__img video-card__img--placeholder">
                                <Icon icon="lucide:play-circle" width="40" height="40" />
                            </div>

                            <span v-if="video.isNew" class="badge-new">BARU</span>

                            <span class="badge-duration">
                                <Icon icon="lucide:clock" width="11" height="11" />
                                {{ video.duration }}
                            </span>

                            <span class="badge-lock">
                                <Icon icon="lucide:lock" width="12" height="12" />
                            </span>
                        </div>

                        <!-- Body -->
                        <div class="video-card__body">
                            <span class="category-pill pill--emerald">
                                {{ video.category }}
                            </span>
                            <h3 class="video-card__title">{{ video.title }}</h3>
                            <p class="video-card__views">
                                <Icon icon="lucide:eye" width="12" height="12" />
                                {{ video.views }} ditonton
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else class="video-empty">
                    <Icon icon="lucide:video-off" width="48" height="48" />
                    <p>Belum ada video yang tersedia.</p>
                </div>
            </section>
        </main>

        <!-- ═══════════════════ FOOTER ═══════════════════ -->
        <footer class="footer">
            <div class="footer__inner">
                <p>© 2026 DigestEdu. Semua hak dilindungi.</p>
                <p>Edukasi kesehatan pencernaan interaktif untuk Indonesia.</p>
            </div>
        </footer>

        <!-- ═══════════════════ AUTH MODAL ═══════════════════ -->
        <AuthModal :show="isAuthModalOpen" :initial-mode="authModalMode" @close="isAuthModalOpen = false" />
    </div>
</template>

<style scoped>
/* ─── Design Tokens ─────────────────────────────────────────────────────── */
:root {
    --teal-600: #0d9488;
    --teal-700: #0f766e;
    --slate-900: #0f172a;
    --slate-600: #475569;
    --slate-400: #94a3b8;
    --slate-200: #e2e8f0;
    --slate-100: #f1f5f9;
    --slate-50: #f8fafc;
}

/* ─── Root ──────────────────────────────────────────────────────────────── */
.page-root {
    font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
    background-color: #f8fafc;
    color: #0f172a;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

/* ─── Navbar ────────────────────────────────────────────────────────────── */
.navbar {
    background-color: #ffffff;
    border-bottom: 1px solid #e2e8f0;
    position: sticky;
    top: 0;
    z-index: 50;
}
.navbar__inner {
    max-width: 1240px;
    margin: 0 auto;
    padding: 14px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.navbar__logo {
    display: flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
}
.logo-icon {
    width: 34px;
    height: 34px;
    background-color: #0d9488;
    color: #ffffff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.logo-text {
    font-size: 19px;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.4px;
}
.logo-text--accent {
    color: #0d9488;
}
.navbar__actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

/* ─── Buttons (shared) ──────────────────────────────────────────────────── */
.btn {
    padding: 7.5px 20px;
    border-radius: 100px;
    font-size: 13.5px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    font-family: inherit;
    border: none;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
}
.btn--ghost {
    background-color: #f1f5f9;
    color: #0f172a;
}
.btn--outline {
    border: 1px solid #0d9488;
    color: #0d9488;
    background: transparent;
}
.btn--outline:hover {
    background-color: rgba(13, 148, 136, 0.06);
}
.btn--primary {
    background-color: #0d9488;
    color: #ffffff;
}
.btn--primary:hover {
    background-color: #0f766e;
}

/* ─── Main ──────────────────────────────────────────────────────────────── */
.main {
    max-width: 1240px;
    margin: 0 auto;
    padding: 28px 24px 60px;
    width: 100%;
    flex: 1;
    box-sizing: border-box;
}

/* ─── Hero ──────────────────────────────────────────────────────────────── */
.hero {
    background: linear-gradient(135deg, #0e7490 0%, #0d9488 50%, #047857 100%);
    border-radius: 24px;
    padding: 48px 48px 40px;
    color: #ffffff;
    position: relative;
    overflow: hidden;
    box-shadow: 0 12px 36px rgba(13, 148, 136, 0.2);
}
.hero__content {
    position: relative;
    z-index: 2;
    max-width: 640px;
}
.hero__badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255, 255, 255, 0.18);
    backdrop-filter: blur(8px);
    padding: 6px 14px;
    border-radius: 100px;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 20px;
}
.hero__title {
    font-size: clamp(32px, 4vw, 44px);
    font-weight: 800;
    line-height: 1.15;
    margin: 0 0 16px;
    letter-spacing: -0.8px;
}
.hero__subtitle {
    font-size: 15.5px;
    line-height: 1.65;
    color: rgba(255, 255, 255, 0.88);
    margin: 0 0 28px;
}
.hero__cta {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background-color: #ffffff;
    color: #0f766e;
    font-size: 14px;
    font-weight: 700;
    padding: 11px 24px;
    border: none;
    border-radius: 100px;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
    font-family: inherit;
    margin-bottom: 36px;
}
.hero__cta:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.16);
}
.hero__divider {
    height: 1px;
    background: rgba(255, 255, 255, 0.2);
    margin-bottom: 28px;
}
.hero__stats {
    display: flex;
    gap: 40px;
    flex-wrap: wrap;
}
.stat__num {
    font-size: 24px;
    font-weight: 800;
    line-height: 1;
    margin-bottom: 4px;
}
.stat__label {
    font-size: 12px;
    color: rgba(255, 255, 255, 0.78);
    font-weight: 500;
}

/* Decorative circles */
.hero__deco {
    position: absolute;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.07);
    pointer-events: none;
}
.hero__deco--1 { width: 320px; height: 320px; right: -60px;  top: -60px;   }
.hero__deco--2 { width: 220px; height: 220px; right: 80px;   bottom: -40px; }
.hero__deco--3 { width: 140px; height: 140px; right: 320px;  top: 40px;    }

/* ─── Materi Section ────────────────────────────────────────────────────── */
.materi {
    margin-top: 44px;
}
.materi__header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    margin-bottom: 20px;
}
.materi__title {
    font-size: 22px;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 4px;
    letter-spacing: -0.4px;
}
.materi__subtitle {
    font-size: 13.5px;
    color: #64748b;
    margin: 0;
}
.materi__see-all {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: #0d9488;
    font-size: 13.5px;
    font-weight: 700;
    background: none;
    border: none;
    cursor: pointer;
    padding: 0;
    font-family: inherit;
    transition: color 0.2s;
}
.materi__see-all:hover {
    color: #0f766e;
}

/* ─── Filter Bar ────────────────────────────────────────────────────────── */
.filter-bar {
    display: flex;
    gap: 8px;
    margin-bottom: 28px;
    overflow-x: auto;
    padding-bottom: 4px;
}
.filter-btn {
    padding: 7px 18px;
    border-radius: 100px;
    font-size: 13px;
    font-weight: 600;
    border: 1px solid #e2e8f0;
    background-color: #ffffff;
    color: #64748b;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
    font-family: inherit;
}
.filter-btn:hover {
    border-color: #cbd5e1;
    color: #1e293b;
}
.filter-btn--active {
    background-color: #0d9488;
    color: #ffffff;
    border-color: #0d9488;
}

/* ─── Video Grid ────────────────────────────────────────────────────────── */
.video-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 22px;
}

/* ─── Video Card ────────────────────────────────────────────────────────── */
.video-card {
    background-color: #ffffff;
    border: 1px solid #f1f5f9;
    border-radius: 18px;
    overflow: hidden;
    transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
    display: flex;
    flex-direction: column;
    cursor: pointer;
    outline: none;
}
.video-card:hover,
.video-card:focus-visible {
    transform: translateY(-4px);
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.08);
    border-color: #e2e8f0;
}
.video-card__media {
    position: relative;
    aspect-ratio: 16 / 10;
    overflow: hidden;
    background-color: #e2e8f0;
}
.video-card__img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.35s ease;
}
.video-card:hover .video-card__img {
    transform: scale(1.05);
}
.video-card__img--placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
    color: #94a3b8;
    object-fit: unset;
}
.video-empty {
    text-align: center;
    padding: 3rem 1rem;
    color: #94a3b8;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.95rem;
}

/* ── Media Badges ── */
.badge-new {
    position: absolute;
    top: 10px;
    left: 10px;
    background-color: #0d9488;
    color: #ffffff;
    font-size: 10px;
    font-weight: 800;
    padding: 3px 8px;
    border-radius: 6px;
    letter-spacing: 0.5px;
}
.badge-duration {
    position: absolute;
    bottom: 10px;
    right: 10px;
    background-color: rgba(15, 23, 42, 0.75);
    backdrop-filter: blur(4px);
    color: #ffffff;
    font-size: 11px;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    gap: 4px;
}
.badge-lock {
    position: absolute;
    top: 10px;
    right: 10px;
    width: 26px;
    height: 26px;
    background-color: rgba(15, 23, 42, 0.55);
    backdrop-filter: blur(4px);
    color: #ffffff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* ── Card Body ── */
.video-card__body {
    padding: 16px;
    display: flex;
    flex-direction: column;
    flex: 1;
}
.video-card__title {
    font-size: 14.5px;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.4;
    margin: 8px 0 auto;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.video-card__views {
    margin-top: 12px;
    font-size: 12px;
    color: #94a3b8;
    display: flex;
    align-items: center;
    gap: 4px;
    font-weight: 500;
}

/* ─── Category Pills ────────────────────────────────────────────────────── */
.category-pill {
    display: inline-block;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 100px;
    border: 1px solid;
    align-self: flex-start;
}
.pill--emerald { background-color: #ecfdf5; color: #059669; border-color: #a7f3d0; }
.pill--cyan    { background-color: #ecfeff; color: #0891b2; border-color: #a5f3fc; }
.pill--rose    { background-color: #fff1f2; color: #e11d48; border-color: #fecdd3; }
.pill--amber   { background-color: #fffbeb; color: #d97706; border-color: #fde68a; }
.pill--teal    { background-color: #f0fdfa; color: #0d9488; border-color: #99f6e4; }

/* ─── Footer ────────────────────────────────────────────────────────────── */
.footer {
    background-color: #ffffff;
    border-top: 1px solid #e2e8f0;
    padding: 24px 0;
    margin-top: auto;
}
.footer__inner {
    max-width: 1240px;
    margin: 0 auto;
    padding: 0 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 13px;
    color: #64748b;
    flex-wrap: wrap;
    gap: 12px;
}
.footer__inner p {
    margin: 0;
}

/* ─── Responsive ────────────────────────────────────────────────────────── */
@media (max-width: 1024px) {
    .video-grid { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 768px) {
    .video-grid { grid-template-columns: repeat(2, 1fr); }
    .hero { padding: 32px 24px; }
    .hero__stats { gap: 20px; }
}
@media (max-width: 480px) {
    .video-grid { grid-template-columns: 1fr; }
}
</style>
