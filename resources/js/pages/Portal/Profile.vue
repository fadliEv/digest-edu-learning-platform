<script setup lang="ts">
import UserLayout from '@/layouts/UserLayout.vue';
import { Icon } from '@iconify/vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

// ─── Props ────────────────────────────────────────────────────────────────────
const props = defineProps<{
    userProfile: {
        id: number;
        name: string;
        email: string;
        role: string;
        joined_at: string;
    };
    stats: {
        total_watched: number;
        total_quizzes: number;
        avg_score: number;
    };
    quizHistory: Array<{
        id: number;
        video_id: number;
        video_title: string;
        category: string;
        score: number;
        passed: boolean;
        attempted_at: string;
    }>;
    inProgress: Array<{
        video_id: number;
        title: string;
        category: string;
        thumbnail: string | null;
        last_position: number;
        progress: number;
        duration: string;
    }>;
}>();

// ─── Flash ────────────────────────────────────────────────────────────────────
const page = usePage();
const flash = computed(() => (page.props.flash as any) ?? {});

// ─── Active tab ───────────────────────────────────────────────────────────────
const activeTab = ref<'progress' | 'history' | 'edit'>('progress');

// ─── Edit Profile Form ────────────────────────────────────────────────────────
const form = useForm({
    name:             props.userProfile.name,
    email:            props.userProfile.email,
    current_password: '',
    new_password:     '',
    new_password_confirmation: '',
});

function submitProfile() {
    form.patch('/profile', {
        preserveScroll: true,
        onSuccess: () => {
            form.current_password = '';
            form.new_password     = '';
            form.new_password_confirmation = '';
        },
    });
}

// ─── Helpers ─────────────────────────────────────────────────────────────────
function getInitials(name: string): string {
    const parts = name.trim().split(' ');
    return parts.length >= 2
        ? (parts[0][0] + parts[1][0]).toUpperCase()
        : name.slice(0, 2).toUpperCase();
}

function scoreColor(score: number): string {
    if (score >= 85) return '#16a34a';
    if (score >= 70) return '#0d9488';
    if (score >= 50) return '#d97706';
    return '#ef4444';
}

const fallbackThumb = 'https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?w=500&h=300&fit=crop';
</script>

<template>
    <Head title="Profil Saya — DigestEdu" />

    <UserLayout>
        <!-- ── Profile Header ── -->
        <div class="profile-header">
            <div class="profile-header__inner">
                <!-- Avatar besar -->
                <div class="profile-avatar">
                    {{ getInitials(userProfile.name) }}
                </div>

                <!-- Info -->
                <div class="profile-info">
                    <div class="profile-name-row">
                        <h1 class="profile-name">{{ userProfile.name }}</h1>
                        <span :class="['role-badge', userProfile.role === 'admin' ? 'role-badge--admin' : 'role-badge--user']">
                            <Icon :icon="userProfile.role === 'admin' ? 'lucide:shield-check' : 'lucide:graduation-cap'" width="12" height="12" />
                            {{ userProfile.role === 'admin' ? 'Administrator' : 'Siswa' }}
                        </span>
                    </div>
                    <p class="profile-email">
                        <Icon icon="lucide:mail" width="13" height="13" />
                        {{ userProfile.email }}
                    </p>
                    <p class="profile-joined">
                        <Icon icon="lucide:calendar-days" width="13" height="13" />
                        Bergabung sejak {{ userProfile.joined_at }}
                    </p>
                </div>

                <!-- Stats Row -->
                <div class="header-stats">
                    <div class="hstat">
                        <span class="hstat__num">{{ stats.total_watched }}</span>
                        <span class="hstat__label">Video Ditonton</span>
                    </div>
                    <div class="hstat-div" />
                    <div class="hstat">
                        <span class="hstat__num">{{ stats.total_quizzes }}</span>
                        <span class="hstat__label">Kuis Dikerjakan</span>
                    </div>
                    <div class="hstat-div" />
                    <div class="hstat">
                        <span class="hstat__num">{{ stats.avg_score }}%</span>
                        <span class="hstat__label">Rata-rata Skor</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Stat Cards ── -->
        <div class="stat-grid">
            <div class="stat-card stat-card--blue">
                <div class="stat-card__icon">
                    <Icon icon="lucide:play-circle" width="22" height="22" />
                </div>
                <div>
                    <p class="stat-card__num">{{ stats.total_watched }}</p>
                    <p class="stat-card__label">Video Ditonton</p>
                </div>
            </div>
            <div class="stat-card stat-card--teal">
                <div class="stat-card__icon">
                    <Icon icon="lucide:brain-circuit" width="22" height="22" />
                </div>
                <div>
                    <p class="stat-card__num">{{ stats.total_quizzes }}</p>
                    <p class="stat-card__label">Kuis Selesai</p>
                </div>
            </div>
            <div class="stat-card stat-card--violet">
                <div class="stat-card__icon">
                    <Icon icon="lucide:bar-chart-2" width="22" height="22" />
                </div>
                <div>
                    <p class="stat-card__num">{{ stats.avg_score }}%</p>
                    <p class="stat-card__label">Rata-rata Skor</p>
                </div>
            </div>
            <div class="stat-card stat-card--green">
                <div class="stat-card__icon">
                    <Icon icon="lucide:check-circle" width="22" height="22" />
                </div>
                <div>
                    <p class="stat-card__num">
                        {{ quizHistory.filter(q => q.passed).length }}
                    </p>
                    <p class="stat-card__label">Kuis Lulus</p>
                </div>
            </div>
        </div>

        <!-- ── Tabs ── -->
        <div class="tabs">
            <button :class="['tab-btn', { 'tab-btn--active': activeTab === 'progress' }]" @click="activeTab = 'progress'">
                <Icon icon="lucide:list-video" width="15" height="15" />
                Sedang Dipelajari
                <span v-if="inProgress.length > 0" class="tab-badge">{{ inProgress.length }}</span>
            </button>
            <button :class="['tab-btn', { 'tab-btn--active': activeTab === 'history' }]" @click="activeTab = 'history'">
                <Icon icon="lucide:history" width="15" height="15" />
                Riwayat Kuis
                <span v-if="quizHistory.length > 0" class="tab-badge">{{ quizHistory.length }}</span>
            </button>
            <button :class="['tab-btn', { 'tab-btn--active': activeTab === 'edit' }]" @click="activeTab = 'edit'">
                <Icon icon="lucide:settings" width="15" height="15" />
                Edit Profil
            </button>
        </div>

        <!-- ══ TAB: Sedang Dipelajari ══ -->
        <div v-show="activeTab === 'progress'" class="tab-content">
            <div v-if="inProgress.length === 0" class="empty-state">
                <Icon icon="lucide:play-circle" width="40" height="40" />
                <p>Kamu belum menonton video apapun.</p>
                <Link href="/home" class="btn-explore">
                    <Icon icon="lucide:compass" width="15" height="15" />
                    Jelajahi Materi
                </Link>
            </div>

            <div v-else class="progress-grid">
                <Link
                    v-for="item in inProgress"
                    :key="item.video_id"
                    :href="`/video/${item.video_id}`"
                    class="progress-card"
                >
                    <div class="progress-card__media">
                        <img
                            :src="item.thumbnail ?? fallbackThumb"
                            :alt="item.title"
                            class="progress-card__img"
                            loading="lazy"
                        />
                        <span class="progress-duration">{{ item.duration }}</span>
                        <div class="progress-card__overlay">
                            <Icon icon="lucide:play" width="20" height="20" />
                        </div>
                    </div>
                    <div class="progress-card__body">
                        <span class="progress-category">{{ item.category }}</span>
                        <h4 class="progress-title">{{ item.title }}</h4>
                        <div class="progress-bar-wrap">
                            <div class="progress-bar">
                                <div class="progress-bar__fill" :style="{ width: item.progress + '%' }" />
                            </div>
                            <span class="progress-pct">{{ item.progress }}%</span>
                        </div>
                    </div>
                </Link>
            </div>
        </div>

        <!-- ══ TAB: Riwayat Kuis ══ -->
        <div v-show="activeTab === 'history'" class="tab-content">
            <div v-if="quizHistory.length === 0" class="empty-state">
                <Icon icon="lucide:clipboard-list" width="40" height="40" />
                <p>Kamu belum mengerjakan kuis apapun.</p>
            </div>

            <div v-else class="history-table-wrap">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>VIDEO</th>
                            <th>KATEGORI</th>
                            <th>SKOR</th>
                            <th>STATUS</th>
                            <th>TANGGAL</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="attempt in quizHistory" :key="attempt.id">
                            <td class="col-title">{{ attempt.video_title }}</td>
                            <td>
                                <span class="cat-pill">{{ attempt.category }}</span>
                            </td>
                            <td>
                                <span class="score-num" :style="{ color: scoreColor(attempt.score) }">
                                    {{ attempt.score }}%
                                </span>
                            </td>
                            <td>
                                <span :class="['status-badge', attempt.passed ? 'status-badge--pass' : 'status-badge--fail']">
                                    <Icon :icon="attempt.passed ? 'lucide:check-circle' : 'lucide:x-circle'" width="12" height="12" />
                                    {{ attempt.passed ? 'Lulus' : 'Belum Lulus' }}
                                </span>
                            </td>
                            <td class="col-date">{{ attempt.attempted_at }}</td>
                            <td>
                                <Link :href="`/video/${attempt.video_id}`" class="btn-rewatch">
                                    <Icon icon="lucide:play" width="12" height="12" />
                                    Tonton
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ══ TAB: Edit Profil ══ -->
        <div v-show="activeTab === 'edit'" class="tab-content">
            <!-- Success flash -->
            <div v-if="flash.success" class="flash-success">
                <Icon icon="lucide:check-circle" width="16" height="16" />
                {{ flash.success }}
            </div>

            <div class="edit-grid">
                <!-- Identitas -->
                <div class="edit-card">
                    <h3 class="edit-card__title">
                        <Icon icon="lucide:user" width="16" height="16" />
                        Informasi Akun
                    </h3>

                    <form @submit.prevent="submitProfile">
                        <div class="form-group">
                            <label class="form-label" for="edit-name">Nama Lengkap</label>
                            <input
                                id="edit-name"
                                v-model="form.name"
                                type="text"
                                class="form-input"
                                :class="{ 'form-input--error': form.errors.name }"
                                placeholder="Nama lengkap kamu"
                            />
                            <p v-if="form.errors.name" class="form-error">{{ form.errors.name }}</p>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="edit-email">Alamat Email</label>
                            <input
                                id="edit-email"
                                v-model="form.email"
                                type="email"
                                class="form-input"
                                :class="{ 'form-input--error': form.errors.email }"
                                placeholder="email@contoh.com"
                            />
                            <p v-if="form.errors.email" class="form-error">{{ form.errors.email }}</p>
                        </div>

                        <div class="form-divider">
                            <span>Ganti Password (opsional)</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="edit-current-pw">Password Saat Ini</label>
                            <input
                                id="edit-current-pw"
                                v-model="form.current_password"
                                type="password"
                                class="form-input"
                                :class="{ 'form-input--error': form.errors.current_password }"
                                placeholder="Masukkan password saat ini"
                            />
                            <p v-if="form.errors.current_password" class="form-error">{{ form.errors.current_password }}</p>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="edit-new-pw">Password Baru</label>
                            <input
                                id="edit-new-pw"
                                v-model="form.new_password"
                                type="password"
                                class="form-input"
                                placeholder="Minimal 8 karakter"
                            />
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="edit-confirm-pw">Konfirmasi Password Baru</label>
                            <input
                                id="edit-confirm-pw"
                                v-model="form.new_password_confirmation"
                                type="password"
                                class="form-input"
                                :class="{ 'form-input--error': form.errors.new_password }"
                                placeholder="Ulangi password baru"
                            />
                            <p v-if="form.errors.new_password" class="form-error">{{ form.errors.new_password }}</p>
                        </div>

                        <button
                            type="submit"
                            class="btn-save"
                            :disabled="form.processing"
                        >
                            <Icon v-if="form.processing" icon="lucide:loader-2" width="15" height="15" class="spin" />
                            <Icon v-else icon="lucide:save" width="15" height="15" />
                            {{ form.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
                        </button>
                    </form>
                </div>

                <!-- Akun info sidebar -->
                <div class="account-info-card">
                    <h3 class="edit-card__title">
                        <Icon icon="lucide:shield" width="16" height="16" />
                        Detail Akun
                    </h3>
                    <div class="account-row">
                        <span class="account-key">ID Pengguna</span>
                        <span class="account-val">#{{ userProfile.id }}</span>
                    </div>
                    <div class="account-row">
                        <span class="account-key">Peran</span>
                        <span :class="['role-badge', userProfile.role === 'admin' ? 'role-badge--admin' : 'role-badge--user']">
                            {{ userProfile.role === 'admin' ? 'Administrator' : 'Siswa' }}
                        </span>
                    </div>
                    <div class="account-row">
                        <span class="account-key">Bergabung</span>
                        <span class="account-val">{{ userProfile.joined_at }}</span>
                    </div>
                    <div class="account-row">
                        <span class="account-key">Status</span>
                        <span class="active-dot">
                            <span class="dot-indicator" />
                            Aktif
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </UserLayout>
</template>

<style scoped>
/* ─── Profile Header ──────────────────────────────────────────────────────── */
.profile-header {
    background: linear-gradient(135deg, #0d9488 0%, #0891b2 100%);
    border-radius: 20px;
    padding: 32px;
    margin-bottom: 24px;
    color: #ffffff;
}
.profile-header__inner {
    display: flex;
    align-items: center;
    gap: 24px;
    flex-wrap: wrap;
}
.profile-avatar {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    background: rgba(255,255,255,0.25);
    border: 3px solid rgba(255,255,255,0.5);
    font-size: 24px;
    font-weight: 900;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    letter-spacing: -0.5px;
}
.profile-info { flex: 1; min-width: 0; }
.profile-name-row { display: flex; align-items: center; gap: 12px; margin-bottom: 6px; flex-wrap: wrap; }
.profile-name { font-size: 24px; font-weight: 900; margin: 0; letter-spacing: -0.4px; }
.role-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11.5px;
    font-weight: 700;
}
.role-badge--admin { background: rgba(255,255,255,0.25); color: #ffffff; }
.role-badge--user  { background: rgba(255,255,255,0.2);  color: #ffffff; }
.profile-email, .profile-joined {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    opacity: 0.85;
    margin: 0 0 4px;
}
.header-stats {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-left: auto;
    background: rgba(255,255,255,0.15);
    border-radius: 14px;
    padding: 14px 20px;
    flex-shrink: 0;
}
.hstat { display: flex; flex-direction: column; align-items: center; gap: 2px; }
.hstat__num { font-size: 22px; font-weight: 900; line-height: 1; }
.hstat__label { font-size: 11px; opacity: 0.8; white-space: nowrap; font-weight: 600; }
.hstat-div { width: 1px; height: 36px; background: rgba(255,255,255,0.3); }

/* ─── Stat Cards ──────────────────────────────────────────────────────────── */
.stat-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 28px;
}
.stat-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 18px;
    display: flex;
    align-items: center;
    gap: 14px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.03);
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
.stat-card--blue  .stat-card__icon { background: #dbeafe; color: #2563eb; }
.stat-card--teal  .stat-card__icon { background: #ccfbf1; color: #0d9488; }
.stat-card--violet .stat-card__icon { background: #f3e8ff; color: #9333ea; }
.stat-card--green .stat-card__icon { background: #dcfce7; color: #16a34a; }
.stat-card__num {
    font-size: 26px;
    font-weight: 900;
    color: #0f172a;
    margin: 0;
    line-height: 1;
}
.stat-card__label {
    font-size: 12px;
    color: #64748b;
    font-weight: 600;
    margin: 4px 0 0;
}

/* ─── Tabs ────────────────────────────────────────────────────────────────── */
.tabs {
    display: flex;
    border-bottom: 2px solid #f1f5f9;
    margin-bottom: 28px;
    gap: 4px;
}
.tab-btn {
    display: flex;
    align-items: center;
    gap: 7px;
    padding: 10px 18px;
    font-size: 13.5px;
    font-weight: 700;
    color: #64748b;
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    cursor: pointer;
    font-family: inherit;
    transition: color 0.15s;
    border-radius: 8px 8px 0 0;
}
.tab-btn:hover { color: #0f172a; background: #f8fafc; }
.tab-btn--active { color: #0d9488; border-bottom-color: #0d9488; }
.tab-badge {
    background: #0d9488;
    color: #fff;
    font-size: 10px;
    font-weight: 800;
    padding: 2px 6px;
    border-radius: 100px;
}
.tab-content { animation: fadeIn 0.2s ease; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: none; } }

/* ─── Empty State ─────────────────────────────────────────────────────────── */
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    padding: 60px 40px;
    background: #f8fafc;
    border-radius: 16px;
    color: #94a3b8;
    font-size: 14px;
}
.btn-explore {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 22px;
    border-radius: 10px;
    background: #0d9488;
    color: #fff;
    font-size: 13.5px;
    font-weight: 700;
    text-decoration: none;
    transition: opacity 0.15s;
    margin-top: 4px;
}
.btn-explore:hover { opacity: 0.9; }

/* ─── Progress Grid ───────────────────────────────────────────────────────── */
.progress-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}
.progress-card {
    background: #ffffff;
    border: 1px solid #f1f5f9;
    border-radius: 16px;
    overflow: hidden;
    text-decoration: none;
    transition: transform 0.2s, box-shadow 0.2s;
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
}
.progress-card:hover { transform: translateY(-3px); box-shadow: 0 8px 22px rgba(0,0,0,0.08); }
.progress-card__media {
    position: relative;
    aspect-ratio: 16/10;
    overflow: hidden;
    background: #e2e8f0;
}
.progress-card__img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.35s; }
.progress-card:hover .progress-card__img { transform: scale(1.04); }
.progress-duration {
    position: absolute;
    bottom: 6px;
    right: 6px;
    background: rgba(15,23,42,0.75);
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 5px;
}
.progress-card__overlay {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(13,148,136,0.4);
    opacity: 0;
    transition: opacity 0.2s;
    color: #fff;
}
.progress-card:hover .progress-card__overlay { opacity: 1; }
.progress-card__body { padding: 14px; }
.progress-category {
    font-size: 10.5px;
    font-weight: 700;
    color: #0d9488;
    background: #f0fdfa;
    padding: 2px 8px;
    border-radius: 100px;
}
.progress-title {
    font-size: 13px;
    font-weight: 700;
    color: #0f172a;
    margin: 8px 0 10px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    line-height: 1.4;
}
.progress-bar-wrap { display: flex; align-items: center; gap: 8px; }
.progress-bar { flex: 1; height: 6px; background: #e2e8f0; border-radius: 100px; overflow: hidden; }
.progress-bar__fill { height: 100%; background: linear-gradient(90deg, #0d9488, #0891b2); border-radius: 100px; }
.progress-pct { font-size: 11px; font-weight: 700; color: #0d9488; white-space: nowrap; }

/* ─── History Table ───────────────────────────────────────────────────────── */
.history-table-wrap { overflow-x: auto; border-radius: 16px; border: 1px solid #e2e8f0; }
.history-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    background: #ffffff;
}
.history-table th {
    padding: 12px 18px;
    background: #f8fafc;
    font-size: 11px;
    font-weight: 700;
    color: #64748b;
    letter-spacing: 0.5px;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
}
.history-table td {
    padding: 14px 18px;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
    vertical-align: middle;
}
.history-table tr:last-child td { border-bottom: none; }
.history-table tr:hover td { background: #f8fafc; }
.col-title { font-weight: 700; color: #0f172a; max-width: 240px; }
.cat-pill {
    padding: 3px 10px;
    border-radius: 100px;
    background: #f1f5f9;
    color: #64748b;
    font-size: 11.5px;
    font-weight: 600;
}
.score-num { font-size: 16px; font-weight: 900; }
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 100px;
    font-size: 11.5px;
    font-weight: 700;
}
.status-badge--pass { background: #dcfce7; color: #15803d; }
.status-badge--fail { background: #fee2e2; color: #dc2626; }
.col-date { color: #64748b; font-size: 12px; white-space: nowrap; }
.btn-rewatch {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 12px;
    border-radius: 8px;
    background: #f0fdfa;
    color: #0d9488;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
    transition: background 0.15s;
}
.btn-rewatch:hover { background: #ccfbf1; }

/* ─── Edit Profile ────────────────────────────────────────────────────────── */
.flash-success {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 16px;
    background: #f0fdfa;
    border: 1px solid #99f6e4;
    border-radius: 12px;
    color: #0d9488;
    font-size: 13.5px;
    font-weight: 600;
    margin-bottom: 20px;
}
.edit-grid {
    display: grid;
    grid-template-columns: 1fr 280px;
    gap: 24px;
    align-items: start;
}
.edit-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 18px;
    padding: 26px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.02);
}
.account-info-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 18px;
    padding: 22px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.02);
}
.edit-card__title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 20px;
}
.form-group { margin-bottom: 16px; }
.form-label { display: block; font-size: 12.5px; font-weight: 700; color: #475569; margin-bottom: 6px; }
.form-input {
    width: 100%;
    height: 42px;
    padding: 0 14px;
    border: 1.5px solid #e2e8f0;
    border-radius: 10px;
    font-size: 13.5px;
    color: #0f172a;
    font-family: inherit;
    background: #f8fafc;
    outline: none;
    transition: all 0.2s;
    box-sizing: border-box;
}
.form-input:focus { border-color: #0d9488; background: #fff; box-shadow: 0 0 0 3px rgba(13,148,136,0.1); }
.form-input--error { border-color: #ef4444; }
.form-error { font-size: 12px; color: #ef4444; margin: 5px 0 0; font-weight: 600; }
.form-divider {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 20px 0 16px;
    font-size: 11.5px;
    color: #94a3b8;
    font-weight: 700;
    letter-spacing: 0.3px;
}
.form-divider::before, .form-divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: #f1f5f9;
}
.btn-save {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 11px 24px;
    border-radius: 10px;
    border: none;
    background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);
    color: #fff;
    font-size: 13.5px;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.2s;
    box-shadow: 0 4px 12px rgba(13,148,136,0.2);
    width: 100%;
    justify-content: center;
    margin-top: 8px;
}
.btn-save:hover:not(:disabled) { opacity: 0.93; box-shadow: 0 6px 18px rgba(13,148,136,0.3); }
.btn-save:disabled { opacity: 0.6; cursor: not-allowed; }
.spin { animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.account-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 1px solid #f1f5f9;
}
.account-row:last-child { border-bottom: none; }
.account-key { font-size: 12.5px; color: #64748b; font-weight: 600; }
.account-val { font-size: 13px; font-weight: 700; color: #0f172a; }
.active-dot { display: flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 700; color: #16a34a; }
.dot-indicator { width: 8px; height: 8px; border-radius: 50%; background: #16a34a; }

/* ─── Responsive ──────────────────────────────────────────────────────────── */
@media (max-width: 1024px) {
    .stat-grid { grid-template-columns: repeat(2, 1fr); }
    .progress-grid { grid-template-columns: repeat(2, 1fr); }
    .edit-grid { grid-template-columns: 1fr; }
    .header-stats { margin-left: 0; width: 100%; justify-content: center; }
}
@media (max-width: 640px) {
    .profile-header__inner { flex-direction: column; text-align: center; }
    .profile-name-row { justify-content: center; }
    .profile-email, .profile-joined { justify-content: center; }
    .stat-grid { grid-template-columns: repeat(2, 1fr); }
    .progress-grid { grid-template-columns: 1fr; }
    .tabs { overflow-x: auto; }
    .tab-btn { white-space: nowrap; }
}
</style>
