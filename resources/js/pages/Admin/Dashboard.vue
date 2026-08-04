<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Icon } from '@iconify/vue';
import { Head, Link } from '@inertiajs/vue3';

// ─── Props from Controller ────────────────────────────────────────────────────
defineProps<{
    stats: {
        total_videos: number;
        total_views: number;
        quiz_active: number;
        published: number;
        total_users: number;
    };
    latestVideos: Array<{
        id: number;
        title: string;
        dyntube_video_id: string;
        category: string;
        view_count: number;
        quiz_status: string;
        is_published: boolean;
        created_at: string;
    }>;
    latestUsers: Array<{
        id: number;
        name: string;
        email: string;
        created_at: string;
    }>;
}>();

// ─── Stat Card Config ─────────────────────────────────────────────────────────
const quizStatusConfig: Record<string, { label: string; class: string }> = {
    aktif:  { label: 'Generated',  class: 'status--green' },
    proses: { label: 'Processing', class: 'status--amber' },
    gagal:  { label: 'Failed',     class: 'status--red' },
    belum:  { label: 'Pending',    class: 'status--slate' },
};

function getQuizStatus(status: string) {
    return quizStatusConfig[status] ?? { label: status, class: 'status--slate' };
}

function formatNumber(n: number): string {
    return n.toLocaleString('id-ID');
}
</script>

<template>
    <Head title="Dashboard Admin — DigestEdu" />

    <AdminLayout title="Dashboard">
        <!-- Topbar Actions Slot -->
        <template #topbar-actions>
            <Link href="/admin/videos" class="btn-upload">
                <Icon icon="lucide:plus" width="16" height="16" />
                Unggah Video Baru
            </Link>
        </template>

        <!-- ══ STAT CARDS ══ -->
        <div class="stat-grid">
            <!-- Total Video -->
            <div class="stat-card">
                <div class="stat-card__icon stat-card__icon--teal">
                    <Icon icon="lucide:video" width="20" height="20" />
                </div>
                <div class="stat-card__body">
                    <p class="stat-card__num">{{ stats.total_videos }}</p>
                    <p class="stat-card__label">Total Video</p>
                    <p class="stat-card__sub">+3 bulan ini</p>
                </div>
            </div>

            <!-- Total Penonton -->
            <div class="stat-card">
                <div class="stat-card__icon stat-card__icon--blue">
                    <Icon icon="lucide:users" width="20" height="20" />
                </div>
                <div class="stat-card__body">
                    <p class="stat-card__num">{{ formatNumber(stats.total_views) }}</p>
                    <p class="stat-card__label">Total Penonton</p>
                    <p class="stat-card__sub">+18% dari bulan lalu</p>
                </div>
            </div>

            <!-- Kuis AI Generated -->
            <div class="stat-card">
                <div class="stat-card__icon stat-card__icon--green">
                    <Icon icon="lucide:brain-circuit" width="20" height="20" />
                </div>
                <div class="stat-card__body">
                    <p class="stat-card__num">{{ stats.quiz_active }}</p>
                    <p class="stat-card__label">Kuis AI Generated</p>
                    <p class="stat-card__sub">
                        {{ stats.total_videos > 0 ? Math.round((stats.quiz_active / stats.total_videos) * 100) : 0 }}% dari total video
                    </p>
                </div>
            </div>

            <!-- Dipublikasikan -->
            <div class="stat-card">
                <div class="stat-card__icon stat-card__icon--amber">
                    <Icon icon="lucide:globe" width="20" height="20" />
                </div>
                <div class="stat-card__body">
                    <p class="stat-card__num">{{ stats.published }}</p>
                    <p class="stat-card__label">Dipublikasikan</p>
                    <p class="stat-card__sub">{{ stats.total_videos - stats.published }} draft belum tayang</p>
                </div>
            </div>
        </div>

        <!-- ══ VIDEO TABLE ══ -->
        <div class="panel">
            <div class="panel__header">
                <div>
                    <h3 class="panel__title">Daftar Video</h3>
                    <p class="panel__sub">
                        {{ stats.total_videos }} video total · {{ stats.published }} dipublikasikan
                    </p>
                </div>
                <Link href="/admin/videos" class="link-all">
                    Lihat Semua
                    <Icon icon="lucide:arrow-right" width="14" height="14" />
                </Link>
            </div>

            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>JUDUL VIDEO</th>
                            <th>DYNTUBE ID</th>
                            <th>TOTAL PENONTON</th>
                            <th>STATUS KUIS AI</th>
                            <th>DIUNGGAH</th>
                            <th>PUBLIKASI</th>
                            <th>AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="latestVideos.length === 0">
                            <td colspan="7" class="table-empty">
                                <Icon icon="lucide:inbox" width="24" height="24" />
                                Belum ada video yang diunggah.
                            </td>
                        </tr>
                        <tr v-for="video in latestVideos" :key="video.id">
                            <!-- Title + Category -->
                            <td class="col-title">
                                <p class="title-text">{{ video.title }}</p>
                                <span class="cat-badge">{{ video.category }}</span>
                            </td>

                            <!-- Dyntube ID -->
                            <td>
                                <code class="dyntube-id">{{ video.dyntube_video_id }}</code>
                            </td>

                            <!-- Views -->
                            <td class="col-num">
                                {{ formatNumber(video.view_count) }}
                            </td>

                            <!-- Quiz Status -->
                            <td>
                                <span :class="['status-badge', getQuizStatus(video.quiz_status).class]">
                                    {{ getQuizStatus(video.quiz_status).label }}
                                </span>
                            </td>

                            <!-- Date -->
                            <td class="col-date">{{ video.created_at }}</td>

                            <!-- Publish Toggle -->
                            <td>
                                <div :class="['pub-toggle', { 'pub-toggle--on': video.is_published }]">
                                    <div class="pub-toggle__knob"></div>
                                </div>
                                <span class="pub-label">{{ video.is_published ? 'Tayang' : 'Draft' }}</span>
                            </td>

                            <!-- Actions -->
                            <td>
                                <div class="action-group">
                                    <Link :href="`/admin/videos/${video.id}`" class="action-btn" title="Detail">
                                        <Icon icon="lucide:eye" width="14" height="14" />
                                    </Link>
                                    <Link :href="`/admin/videos/${video.id}/edit`" class="action-btn" title="Edit">
                                        <Icon icon="lucide:pencil" width="14" height="14" />
                                    </Link>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ══ LATEST USERS ══ -->
        <div class="panel" style="margin-top: 24px;">
            <div class="panel__header">
                <div>
                    <h3 class="panel__title">Pengguna Terbaru</h3>
                    <p class="panel__sub">{{ stats.total_users }} total pengguna terdaftar</p>
                </div>
                <Link href="/admin/users" class="link-all">
                    Lihat Semua
                    <Icon icon="lucide:arrow-right" width="14" height="14" />
                </Link>
            </div>

            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>NAMA</th>
                            <th>EMAIL</th>
                            <th>BERGABUNG</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="latestUsers.length === 0">
                            <td colspan="3" class="table-empty">
                                <Icon icon="lucide:users" width="22" height="22" />
                                Belum ada pengguna terdaftar.
                            </td>
                        </tr>
                        <tr v-for="u in latestUsers" :key="u.id">
                            <td>
                                <div class="user-cell">
                                    <div class="user-avatar">
                                        {{ u.name.split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase() }}
                                    </div>
                                    <span class="user-name">{{ u.name }}</span>
                                </div>
                            </td>
                            <td class="col-email">{{ u.email }}</td>
                            <td class="col-date">{{ u.created_at }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
/* ─── Upload Button ───────────────────────────────────────────────────────── */
.btn-upload {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    background: #0d9488;
    color: #ffffff;
    font-size: 13.5px;
    font-weight: 700;
    padding: 8px 18px;
    border-radius: 10px;
    text-decoration: none;
    transition: background 0.15s, transform 0.15s;
    font-family: inherit;
}
.btn-upload:hover {
    background: #0f766e;
    transform: translateY(-1px);
}

/* ─── Stat Grid ───────────────────────────────────────────────────────────── */
.stat-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 18px;
    margin-bottom: 28px;
}
.stat-card {
    background: #ffffff;
    border: 1px solid #f1f5f9;
    border-radius: 18px;
    padding: 22px 20px;
    display: flex;
    align-items: flex-start;
    gap: 16px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
}
.stat-card__icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.stat-card__icon--teal  { background: #f0fdfa; color: #0d9488; }
.stat-card__icon--blue  { background: #eff6ff; color: #3b82f6; }
.stat-card__icon--green { background: #f0fdf4; color: #16a34a; }
.stat-card__icon--amber { background: #fffbeb; color: #d97706; }

.stat-card__body {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.stat-card__num {
    font-size: 26px;
    font-weight: 800;
    color: #0f172a;
    margin: 0;
    letter-spacing: -0.5px;
    line-height: 1;
}
.stat-card__label {
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    margin: 4px 0 2px;
}
.stat-card__sub {
    font-size: 11.5px;
    color: #94a3b8;
    margin: 0;
}

/* ─── Panel ───────────────────────────────────────────────────────────────── */
.panel {
    background: #ffffff;
    border: 1px solid #f1f5f9;
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
}
.panel__header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    padding: 20px 24px 16px;
    border-bottom: 1px solid #f1f5f9;
}
.panel__title {
    font-size: 15px;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 2px;
}
.panel__sub {
    font-size: 12.5px;
    color: #64748b;
    margin: 0;
}
.link-all {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: #0d9488;
    font-size: 12.5px;
    font-weight: 700;
    text-decoration: none;
    transition: color 0.15s;
}
.link-all:hover {
    color: #0f766e;
}

/* ─── Table ───────────────────────────────────────────────────────────────── */
.table-wrap {
    overflow-x: auto;
}
.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.data-table th {
    padding: 10px 16px;
    text-align: left;
    font-size: 11px;
    font-weight: 700;
    color: #94a3b8;
    letter-spacing: 0.5px;
    border-bottom: 1px solid #f1f5f9;
    white-space: nowrap;
}
.data-table td {
    padding: 13px 16px;
    border-bottom: 1px solid #f8fafc;
    vertical-align: middle;
    color: #0f172a;
}
.data-table tbody tr:last-child td {
    border-bottom: none;
}
.data-table tbody tr:hover td {
    background: #f8fafc;
}

/* Col variants */
.col-title { min-width: 220px; }
.col-num { text-align: right; font-weight: 700; }
.col-date { color: #64748b; font-size: 12px; white-space: nowrap; }
.col-email { color: #64748b; font-size: 12px; }

.title-text {
    font-weight: 700;
    margin: 0 0 3px;
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.cat-badge {
    font-size: 10.5px;
    color: #64748b;
    font-weight: 500;
}

.dyntube-id {
    font-size: 11.5px;
    background: #f1f5f9;
    color: #475569;
    padding: 2px 7px;
    border-radius: 5px;
    font-family: 'Courier New', monospace;
}

/* Table empty */
.table-empty {
    text-align: center;
    padding: 40px !important;
    color: #94a3b8;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}

/* ─── Quiz Status Badge ───────────────────────────────────────────────────── */
.status-badge {
    display: inline-block;
    font-size: 11.5px;
    font-weight: 700;
    padding: 3px 9px;
    border-radius: 100px;
}
.status--green { background: #dcfce7; color: #16a34a; }
.status--amber { background: #fef3c7; color: #d97706; }
.status--red   { background: #fee2e2; color: #dc2626; }
.status--slate { background: #f1f5f9; color: #64748b; }

/* ─── Publish Toggle ─────────────────────────────────────────────────────── */
td:has(.pub-toggle) {
    display: flex;
    align-items: center;
    gap: 7px;
}
.pub-toggle {
    width: 36px;
    height: 20px;
    border-radius: 100px;
    background: #e2e8f0;
    position: relative;
    cursor: pointer;
    transition: background 0.2s;
    flex-shrink: 0;
}
.pub-toggle--on {
    background: #0d9488;
}
.pub-toggle__knob {
    position: absolute;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: #ffffff;
    top: 3px;
    left: 3px;
    transition: transform 0.2s;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.15);
}
.pub-toggle--on .pub-toggle__knob {
    transform: translateX(16px);
}
.pub-label {
    font-size: 11.5px;
    font-weight: 600;
    color: #64748b;
}

/* ─── Action Buttons ─────────────────────────────────────────────────────── */
.action-group {
    display: flex;
    gap: 4px;
}
.action-btn {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    color: #64748b;
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    transition: all 0.15s;
}
.action-btn:hover {
    background: #f0fdfa;
    border-color: #99f6e4;
    color: #0d9488;
}

/* ─── User Cell ──────────────────────────────────────────────────────────── */
.user-cell {
    display: flex;
    align-items: center;
    gap: 10px;
}
.user-avatar {
    width: 28px;
    height: 28px;
    background: #0d9488;
    color: #fff;
    border-radius: 50%;
    font-size: 10.5px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.user-name {
    font-weight: 600;
}

/* ─── Responsive ─────────────────────────────────────────────────────────── */
@media (max-width: 1024px) {
    .stat-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 640px) {
    .stat-grid { grid-template-columns: 1fr; }
}
</style>
