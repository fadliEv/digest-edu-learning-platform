<script setup lang="ts">
import UserLayout from '@/layouts/UserLayout.vue';
import { Icon } from '@iconify/vue';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

// ─── Props from Controller ────────────────────────────────────────────────────
const props = defineProps<{
    continueVideos: Array<{
        id: number;
        title: string;
        thumbnail: string | null;
        category: string;
        duration_seconds: number | null;
        last_position: number;
        progress: number;
    }>;
    latestVideos: Array<{
        id: number;
        title: string;
        thumbnail: string | null;
        category: string;
        category_slug: string;
        views: string;
        duration: string;
        is_new: boolean;
    }>;
    categories: string[];
    auth: { user: { name: string; email: string } };
}>();

// ─── Category Filter ──────────────────────────────────────────────────────────
const activeCategory = ref('Semua');

const filteredVideos = computed(() =>
    activeCategory.value === 'Semua'
        ? props.latestVideos
        : props.latestVideos.filter((v) => v.category === activeCategory.value),
);

// ─── Helpers ──────────────────────────────────────────────────────────────────
const categoryClassMap: Record<string, string> = {
    Nutrisi:    'pill--emerald',
    Anatomi:    'pill--cyan',
    Pencegahan: 'pill--amber',
    Penyakit:   'pill--rose',
    'Gaya Hidup': 'pill--teal',
    Umum:       'pill--slate',
};

function getCategoryClass(cat: string): string {
    return categoryClassMap[cat] ?? 'pill--slate';
}

/** Format seconds to readable "X Menit Y Detik tersisa" */
function formatTimeLeft(durationSecs: number | null, lastPos: number): string {
    if (!durationSecs) return '';
    const remaining = Math.max(0, durationSecs - lastPos);
    const mins = Math.floor(remaining / 60);
    return mins > 0 ? `${mins} Menit tersisa` : 'Hampir selesai';
}

// Fallback thumbnail placeholder
const fallbackThumb = 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=500&h=300&fit=crop';
</script>

<template>
    <Head title="Beranda — DigestEdu" />

    <UserLayout>
        <!-- ── Greeting ── -->
        <div class="greeting">
            <h2 class="greeting__title">
                Selamat datang kembali, {{ auth.user.name.split(' ')[0] }} 👋
            </h2>
            <p class="greeting__sub">Lanjutkan perjalanan belajarmu tentang kesehatan pencernaan.</p>
        </div>

        <!-- ══ SECTION: Lanjut Menonton ══ -->
        <section v-if="continueVideos.length > 0" class="section">
            <div class="section__header">
                <div>
                    <h3 class="section__title">Lanjut Menonton</h3>
                    <p class="section__sub">Lanjutkan materi yang sedang kamu pelajari</p>
                </div>
                <a href="/videos" class="see-all-link">
                    Lihat Semua
                    <Icon icon="lucide:chevron-right" width="15" height="15" />
                </a>
            </div>

            <div class="continue-grid">
                <div v-for="video in continueVideos" :key="video.id" class="continue-card">
                    <!-- Thumbnail -->
                    <div class="continue-card__media">
                        <img
                            :src="video.thumbnail ?? fallbackThumb"
                            :alt="video.title"
                            class="continue-card__img"
                            loading="lazy"
                        />
                        <span :class="['category-pill', getCategoryClass(video.category)]">
                            {{ video.category }}
                        </span>
                    </div>

                    <!-- Body -->
                    <div class="continue-card__body">
                        <h4 class="continue-card__title">{{ video.title }}</h4>

                        <div class="time-left">
                            <Icon icon="lucide:clock" width="12" height="12" />
                            {{ formatTimeLeft(video.duration_seconds, video.last_position) }}
                        </div>

                        <!-- Progress Bar -->
                        <div class="progress-wrap">
                            <div class="progress-label">
                                <span>Progress</span>
                                <span class="progress-pct">{{ video.progress }}%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-bar__fill" :style="{ width: video.progress + '%' }"></div>
                            </div>
                        </div>

                        <!-- Action -->
                        <a :href="`/video/${video.id}`" class="btn-lanjutkan">
                            <Icon icon="lucide:play" width="14" height="14" />
                            Lanjutkan
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Empty State: No Continue Videos -->
        <section v-else class="section">
            <div class="section__header">
                <div>
                    <h3 class="section__title">Lanjut Menonton</h3>
                    <p class="section__sub">Kamu belum memulai materi apapun</p>
                </div>
            </div>
            <div class="empty-state">
                <Icon icon="lucide:play-circle" width="40" height="40" class="empty-icon" />
                <p>Mulai tonton materi pertamamu di bawah ini!</p>
            </div>
        </section>

        <!-- ══ SECTION: Materi Terbaru ══ -->
        <section class="section">
            <div class="section__header">
                <div>
                    <h3 class="section__title">Materi Terbaru</h3>
                    <p class="section__sub">Konten edukasi terkini dari tim ahli kami</p>
                </div>
                <a href="/videos" class="see-all-link">
                    Lihat Semua
                    <Icon icon="lucide:chevron-right" width="15" height="15" />
                </a>
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
            <div class="video-grid">
                <a
                    v-for="video in filteredVideos"
                    :key="video.id"
                    :href="`/video/${video.id}`"
                    class="video-card"
                >
                    <div class="video-card__media">
                        <img
                            :src="video.thumbnail ?? fallbackThumb"
                            :alt="video.title"
                            class="video-card__img"
                            loading="lazy"
                        />
                        <span v-if="video.is_new" class="badge-new">BARU</span>
                        <span class="badge-duration">
                            <Icon icon="lucide:clock" width="11" height="11" />
                            {{ video.duration }}
                        </span>
                    </div>
                    <div class="video-card__body">
                        <span :class="['category-pill', getCategoryClass(video.category)]">
                            {{ video.category }}
                        </span>
                        <h4 class="video-card__title">{{ video.title }}</h4>
                        <p class="video-card__views">
                            <Icon icon="lucide:eye" width="12" height="12" />
                            {{ video.views }} ditonton
                        </p>
                    </div>
                </a>
            </div>

            <!-- Empty filter state -->
            <div v-if="filteredVideos.length === 0" class="empty-state">
                <Icon icon="lucide:video-off" width="36" height="36" class="empty-icon" />
                <p>Belum ada materi untuk kategori ini.</p>
            </div>
        </section>
    </UserLayout>
</template>

<style scoped>
/* ─── Greeting ───────────────────────────────────────────────────────────── */
.greeting {
    margin-bottom: 36px;
}
.greeting__title {
    font-size: 22px;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 4px;
    letter-spacing: -0.4px;
}
.greeting__sub {
    font-size: 14px;
    color: #64748b;
    margin: 0;
}

/* ─── Section ────────────────────────────────────────────────────────────── */
.section {
    margin-bottom: 48px;
}
.section__header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    margin-bottom: 20px;
}
.section__title {
    font-size: 18px;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 3px;
    letter-spacing: -0.3px;
}
.section__sub {
    font-size: 13px;
    color: #64748b;
    margin: 0;
}
.see-all-link {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: #0d9488;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    transition: color 0.15s;
}
.see-all-link:hover {
    color: #0f766e;
}

/* ─── Continue Watching Grid ─────────────────────────────────────────────── */
.continue-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
}
.continue-card {
    background: #ffffff;
    border: 1px solid #f1f5f9;
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    transition: transform 0.2s, box-shadow 0.2s;
}
.continue-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 22px rgba(0, 0, 0, 0.08);
}
.continue-card__media {
    position: relative;
    aspect-ratio: 16 / 10;
    overflow: hidden;
}
.continue-card__img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.35s;
}
.continue-card:hover .continue-card__img {
    transform: scale(1.04);
}
.continue-card__body {
    padding: 14px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.continue-card__title {
    font-size: 13.5px;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    line-height: 1.4;
}
.time-left {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 11.5px;
    color: #94a3b8;
    font-weight: 500;
}

/* Progress */
.progress-wrap {
    display: flex;
    flex-direction: column;
    gap: 5px;
}
.progress-label {
    display: flex;
    justify-content: space-between;
    font-size: 11.5px;
    color: #94a3b8;
    font-weight: 500;
}
.progress-pct {
    color: #0d9488;
    font-weight: 700;
}
.progress-bar {
    height: 5px;
    background: #e2e8f0;
    border-radius: 100px;
    overflow: hidden;
}
.progress-bar__fill {
    height: 100%;
    background: linear-gradient(90deg, #0d9488, #0891b2);
    border-radius: 100px;
    transition: width 0.4s ease;
}

/* Lanjutkan Button */
.btn-lanjutkan {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    background: #0d9488;
    color: #ffffff;
    font-size: 13px;
    font-weight: 700;
    padding: 9px 14px;
    border-radius: 10px;
    text-decoration: none;
    transition: background 0.15s, transform 0.15s;
    font-family: inherit;
    margin-top: 4px;
}
.btn-lanjutkan:hover {
    background: #0f766e;
    transform: translateY(-1px);
}

/* ─── Category Filter ────────────────────────────────────────────────────── */
.filter-bar {
    display: flex;
    gap: 8px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}
.filter-btn {
    padding: 6px 16px;
    border-radius: 100px;
    font-size: 13px;
    font-weight: 600;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    color: #64748b;
    cursor: pointer;
    transition: all 0.15s;
    font-family: inherit;
}
.filter-btn:hover {
    border-color: #cbd5e1;
    color: #1e293b;
}
.filter-btn--active {
    background: #0d9488;
    color: #ffffff;
    border-color: #0d9488;
}

/* ─── Video Grid ─────────────────────────────────────────────────────────── */
.video-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
}
.video-card {
    background: #ffffff;
    border: 1px solid #f1f5f9;
    border-radius: 18px;
    overflow: hidden;
    text-decoration: none;
    display: flex;
    flex-direction: column;
    transition: transform 0.2s, box-shadow 0.2s;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
}
.video-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 24px rgba(0, 0, 0, 0.08);
}
.video-card__media {
    position: relative;
    aspect-ratio: 16 / 10;
    overflow: hidden;
    background: #e2e8f0;
}
.video-card__img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.35s;
}
.video-card:hover .video-card__img {
    transform: scale(1.04);
}
.badge-new {
    position: absolute;
    top: 8px;
    left: 8px;
    background: #0d9488;
    color: #fff;
    font-size: 9.5px;
    font-weight: 800;
    padding: 3px 7px;
    border-radius: 5px;
    letter-spacing: 0.4px;
}
.badge-duration {
    position: absolute;
    bottom: 8px;
    right: 8px;
    background: rgba(15, 23, 42, 0.72);
    backdrop-filter: blur(4px);
    color: #fff;
    font-size: 10.5px;
    font-weight: 600;
    padding: 3px 7px;
    border-radius: 5px;
    display: flex;
    align-items: center;
    gap: 3px;
}
.video-card__body {
    padding: 14px;
    display: flex;
    flex-direction: column;
    flex: 1;
}
.video-card__title {
    font-size: 13.5px;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.4;
    margin: 6px 0 auto;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.video-card__views {
    margin-top: 10px;
    font-size: 11.5px;
    color: #94a3b8;
    display: flex;
    align-items: center;
    gap: 4px;
    font-weight: 500;
}

/* ─── Category Pills ─────────────────────────────────────────────────────── */
.category-pill {
    display: inline-block;
    font-size: 10.5px;
    font-weight: 700;
    padding: 3px 9px;
    border-radius: 100px;
    border: 1px solid;
}
.pill--emerald { background: #ecfdf5; color: #059669; border-color: #a7f3d0; }
.pill--cyan    { background: #ecfeff; color: #0891b2; border-color: #a5f3fc; }
.pill--rose    { background: #fff1f2; color: #e11d48; border-color: #fecdd3; }
.pill--amber   { background: #fffbeb; color: #d97706; border-color: #fde68a; }
.pill--teal    { background: #f0fdfa; color: #0d9488; border-color: #99f6e4; }
.pill--slate   { background: #f8fafc; color: #64748b; border-color: #e2e8f0; }

/* ─── Empty State ────────────────────────────────────────────────────────── */
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    padding: 40px;
    color: #94a3b8;
    font-size: 14px;
}
.empty-icon {
    color: #cbd5e1;
}

/* ─── Responsive ─────────────────────────────────────────────────────────── */
@media (max-width: 1024px) {
    .continue-grid { grid-template-columns: repeat(2, 1fr); }
    .video-grid    { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 768px) {
    .video-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 480px) {
    .continue-grid { grid-template-columns: 1fr; }
    .video-grid    { grid-template-columns: 1fr; }
}
</style>
