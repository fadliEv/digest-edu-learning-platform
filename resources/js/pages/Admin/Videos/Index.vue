<script setup lang="ts">
import ConfirmModal from '@/components/Admin/ConfirmModal.vue';
import ProcessingModal from '@/components/Admin/ProcessingModal.vue';
import UploadVideoModal from '@/components/Admin/UploadVideoModal.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Icon } from '@iconify/vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps<{
    videos: {
        data: Array<{
            id: number;
            title: string;
            description: string | null;
            dyntube_video_id: string;
            dyntube_iframe_link: string | null;
            dyntube_thumbnail: string | null;
            category_id: number;
            category_name: string;
            view_count: number;
            quiz_status: string;
            transcoding_status: string;
            is_published: boolean;
            duration_formatted: string;
            created_at: string;
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    categories: Array<{ id: number; name: string }>;
    stats: {
        total_videos: number;
        total_views: number;
        quiz_active: number;
        published: number;
        transcoding?: number;
    };
    filters: {
        search?: string;
        category?: string;
    };
}>();

// ─── Transcoding Polling ──────────────────────────────────────────────────────
let pollingTimer: any = null;

const hasTranscodingVideo = computed(() => {
    return props.videos.data.some(v => v.transcoding_status === 'transcoding');
});

onMounted(() => {
    startPolling();
});

onUnmounted(() => {
    stopPolling();
});

function startPolling() {
    stopPolling();
    pollingTimer = setInterval(async () => {
        if (hasTranscodingVideo.value) {
            try {
                const res = await fetch('/admin/videos/transcoding-status');
                const data = await res.json();
                if (data.updated_count > 0) {
                    router.reload({ preserveScroll: true });
                }
            } catch (e) {
                // Ignore network failure on background poll
            }
        }
    }, 5000);
}

function stopPolling() {
    if (pollingTimer) {
        clearInterval(pollingTimer);
        pollingTimer = null;
    }
}

// ─── Modal State ─────────────────────────────────────────────────────────────
const isUploadModalOpen = ref(false);
const selectedVideoToEdit = ref<any | null>(null);
const isGeneratingQuiz = ref(false);

// ─── Reusable Confirm Modal State ─────────────────────────────────────────────
const confirmModalShow = ref(false);
const confirmModalTitle = ref('');
const confirmModalMessage = ref('');
const confirmModalType = ref<'danger' | 'warning' | 'info'>('warning');
const confirmModalConfirmText = ref('Ya, Lanjutkan');
const onConfirmAction = ref<(() => void) | null>(null);

function showConfirmDialog(options: {
    title: string;
    message: string;
    type?: 'danger' | 'warning' | 'info';
    confirmText?: string;
    action: () => void;
}) {
    confirmModalTitle.value = options.title;
    confirmModalMessage.value = options.message;
    confirmModalType.value = options.type || 'warning';
    confirmModalConfirmText.value = options.confirmText || 'Ya, Lanjutkan';
    onConfirmAction.value = options.action;
    confirmModalShow.value = true;
}

function handleModalConfirm() {
    confirmModalShow.value = false;
    if (onConfirmAction.value) {
        onConfirmAction.value();
        onConfirmAction.value = null;
    }
}

function openUploadModal() {
    if (hasTranscodingVideo.value) return;
    selectedVideoToEdit.value = null;
    isUploadModalOpen.value = true;
}

function openEditModal(video: any) {
    if (video.transcoding_status === 'transcoding') return;
    selectedVideoToEdit.value = video;
    isUploadModalOpen.value = true;
}

// ─── Search & Filters ────────────────────────────────────────────────────────
const search = ref(props.filters.search || '');
const selectedCategory = ref(props.filters.category || '');

watch([search, selectedCategory], () => {
    router.get(
        '/admin/videos',
        {
            search: search.value,
            category: selectedCategory.value,
        },
        { preserveState: true, replace: true }
    );
});

// ─── Actions ─────────────────────────────────────────────────────────────────
function togglePublish(video: any) {
    if (video.transcoding_status === 'transcoding') return;
    router.patch(`/admin/videos/${video.id}/publish`, {}, { preserveScroll: true });
}

function deleteVideo(video: any) {
    if (video.transcoding_status === 'transcoding') return;
    showConfirmDialog({
        title: 'Hapus Video Materi?',
        message: `Apakah Anda yakin ingin menghapus video "${video.title}"?\nSeluruh data kuis dan statistik penonton video ini akan ikut terhapus.`,
        type: 'danger',
        confirmText: 'Hapus Video',
        action: () => {
            router.delete(`/admin/videos/${video.id}`, { preserveScroll: true });
        },
    });
}

function triggerGenerateQuiz(video: any) {
    if (video.transcoding_status === 'transcoding') return;
    const isRegen = video.quiz_status === 'aktif';
    showConfirmDialog({
        title: isRegen ? 'Regenerate Kuis AI?' : 'Generate Kuis AI?',
        message: isRegen
            ? `Apakah Anda yakin ingin meregenerasi kuis untuk video "${video.title}"?\nSeluruh soal yang ada akan diganti dengan 6 soal baru.`
            : `Buat 6 soal kuis evaluasi otomatis menggunakan Gemini 2.0 Flash berdasarkan naskah transcript untuk video "${video.title}"?`,
        type: isRegen ? 'warning' : 'info',
        confirmText: isRegen ? 'Regenerate Kuis' : 'Generate Kuis',
        action: () => {
            isGeneratingQuiz.value = true;
            router.post(`/admin/videos/${video.id}/generate-quiz`, {}, {
                preserveScroll: true,
                onFinish: () => {
                    isGeneratingQuiz.value = false;
                },
            });
        },
    });
}

const quizStatusMap: Record<string, { label: string; class: string }> = {
    aktif:  { label: 'Generated',  class: 'status--green' },
    proses: { label: 'Processing', class: 'status--amber' },
    gagal:  { label: 'Failed',     class: 'status--red' },
    belum:  { label: 'Pending',    class: 'status--slate' },
};

function getQuizBadge(status: string) {
    return quizStatusMap[status] ?? { label: status, class: 'status--slate' };
}

function formatNum(n: number) {
    return n ? n.toLocaleString('id-ID') : '0';
}
</script>

<template>
    <Head title="Manajemen Video — Admin DigestEdu" />

    <AdminLayout title="Manajemen Video Edukasi">
        <!-- Topbar Actions Slot -->
        <template #topbar-actions>
            <div class="topbar-filter-group">
                <!-- Search Input -->
                <div class="search-input-wrap">
                    <Icon icon="lucide:search" width="15" height="15" class="search-icon" />
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Cari video..."
                        class="search-input"
                    />
                </div>

                <!-- Upload Button -->
                <button
                    type="button"
                    class="btn-upload"
                    :disabled="hasTranscodingVideo"
                    :title="hasTranscodingVideo ? 'Tunggu proses transcoding video selesai...' : 'Unggah Video Baru'"
                    @click="openUploadModal"
                >
                    <Icon :icon="hasTranscodingVideo ? 'lucide:loader-circle' : 'lucide:plus'" width="16" height="16" :class="{ 'spin-icon': hasTranscodingVideo }" />
                    {{ hasTranscodingVideo ? 'Proses Transcoding...' : 'Unggah Video Baru' }}
                </button>
            </div>
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
                    <p class="stat-card__num">{{ formatNum(stats.total_views) }}</p>
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

        <!-- ══ TABLE PANEL ══ -->
        <div class="panel">
            <div class="panel__header">
                <div>
                    <h3 class="panel__title">Daftar Video</h3>
                    <p class="panel__sub">
                        {{ stats.total_videos }} video total · {{ stats.published }} dipublikasikan
                    </p>
                </div>

                <!-- Category Filter Pills -->
                <div class="category-filter-pills">
                    <button
                        :class="['filter-pill', { active: selectedCategory === '' }]"
                        @click="selectedCategory = ''"
                    >
                        Semua
                    </button>
                    <button
                        v-for="cat in categories"
                        :key="cat.id"
                        :class="['filter-pill', { active: selectedCategory === String(cat.id) }]"
                        @click="selectedCategory = String(cat.id)"
                    >
                        {{ cat.name }}
                    </button>
                </div>
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
                        <tr v-if="videos.data.length === 0">
                            <td colspan="7" class="table-empty">
                                <Icon icon="lucide:video-off" width="28" height="28" />
                                Tidak ada video yang ditemukan.
                            </td>
                        </tr>
                        <tr v-for="video in videos.data" :key="video.id" :class="{ 'row-transcoding': video.transcoding_status === 'transcoding' }">
                            <!-- Judul & Kategori -->
                            <td class="col-title">
                                <div class="title-cell">
                                    <p class="title-text">{{ video.title }}</p>
                                    <div class="meta-row">
                                        <span class="duration-text">{{ video.duration_formatted }}</span>
                                        <span class="cat-pill">{{ video.category_name }}</span>
                                    </div>
                                </div>
                            </td>

                            <!-- Dyntube ID -->
                            <td>
                                <code class="dyntube-id">{{ video.dyntube_video_id }}</code>
                            </td>

                            <!-- Penonton -->
                            <td class="col-num">
                                {{ formatNum(video.view_count) }}
                            </td>

                            <!-- Status Kuis AI / Transcoding -->
                            <td>
                                <span v-if="video.transcoding_status === 'transcoding'" class="status-badge status--amber" title="Video sedang dalam proses transcoding Dyntube">
                                    <Icon icon="lucide:loader-circle" width="12" height="12" class="spin-icon" />
                                    Transcoding...
                                </span>
                                <span v-else :class="['status-badge', getQuizBadge(video.quiz_status).class]">
                                    {{ getQuizBadge(video.quiz_status).label }}
                                </span>
                            </td>

                            <!-- Diunggah -->
                            <td class="col-date">{{ video.created_at }}</td>

                            <!-- Publikasi Toggle -->
                            <td>
                                <div
                                    :class="['pub-toggle', { 'pub-toggle--on': video.is_published, 'pub-toggle--disabled': video.transcoding_status === 'transcoding' }]"
                                    @click="togglePublish(video)"
                                >
                                    <div class="pub-toggle__knob"></div>
                                </div>
                                <span class="pub-label">{{ video.is_published ? 'Tayang' : 'Draft' }}</span>
                            </td>

                            <!-- Aksi -->
                            <td>
                                <div class="action-group">
                                    <Link
                                        v-if="video.transcoding_status !== 'transcoding'"
                                        :href="`/admin/videos/${video.id}`"
                                        class="action-btn"
                                        title="Detail Video"
                                    >
                                        <Icon icon="lucide:eye" width="14" height="14" />
                                    </Link>
                                    <span
                                        v-else
                                        class="action-btn action-btn--disabled"
                                        title="Video sedang transcoding..."
                                    >
                                        <Icon icon="lucide:eye" width="14" height="14" />
                                    </span>
                                    <button
                                        class="action-btn"
                                        :disabled="video.transcoding_status === 'transcoding'"
                                        :title="video.transcoding_status === 'transcoding' ? 'Video sedang transcoding...' : 'Edit Video'"
                                        @click="openEditModal(video)"
                                    >
                                        <Icon icon="lucide:pencil" width="14" height="14" />
                                    </button>
                                    <button
                                        v-if="video.quiz_status !== 'aktif'"
                                        class="action-btn action-btn--ai"
                                        :disabled="video.transcoding_status === 'transcoding'"
                                        :title="video.transcoding_status === 'transcoding' ? 'Video sedang transcoding...' : 'Generate Kuis AI'"
                                        @click="triggerGenerateQuiz(video)"
                                    >
                                        <Icon icon="lucide:sparkles" width="14" height="14" />
                                    </button>
                                    <button
                                        class="action-btn action-btn--danger"
                                        :disabled="video.transcoding_status === 'transcoding'"
                                        :title="video.transcoding_status === 'transcoding' ? 'Video sedang transcoding...' : 'Hapus Video'"
                                        @click="deleteVideo(video)"
                                    >
                                        <Icon icon="lucide:trash-2" width="14" height="14" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="videos.links.length > 3" class="pagination-wrap">
                <Component
                    v-for="(link, i) in videos.links"
                    :key="i"
                    :is="link.url ? 'Link' : 'span'"
                    :href="link.url || '#'"
                    :class="['page-link', { active: link.active, disabled: !link.url }]"
                    v-html="link.label"
                />
            </div>
        </div>

        <!-- ══ UPLOAD / EDIT MODAL ══ -->
        <UploadVideoModal
            :show="isUploadModalOpen"
            :categories="categories"
            :edit-video="selectedVideoToEdit"
            @close="isUploadModalOpen = false"
        />

        <!-- ══ AI PROCESSING MODAL OVERLAY ══ -->
        <ProcessingModal :show="isGeneratingQuiz" />

        <!-- ══ REUSABLE CONFIRM MODAL ══ -->
        <ConfirmModal
            :show="confirmModalShow"
            :title="confirmModalTitle"
            :message="confirmModalMessage"
            :type="confirmModalType"
            :confirm-text="confirmModalConfirmText"
            @confirm="handleModalConfirm"
            @cancel="confirmModalShow = false"
        />
    </AdminLayout>
</template>

<style scoped>
.topbar-filter-group {
    display: flex;
    align-items: center;
    gap: 12px;
}
.search-input-wrap {
    position: relative;
    display: flex;
    align-items: center;
}
.search-icon {
    position: absolute;
    left: 12px;
    color: #94a3b8;
}
.search-input {
    background-color: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 100px;
    padding: 7px 16px 7px 34px;
    font-size: 13px;
    outline: none;
    transition: all 0.2s;
    width: 220px;
    font-family: inherit;
}
.search-input:focus {
    background-color: #ffffff;
    border-color: #0d9488;
    box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.12);
}

.btn-upload {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    background: #0d9488;
    color: #ffffff;
    font-size: 13.5px;
    font-weight: 700;
    padding: 8px 18px;
    border-radius: 100px;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: 0 4px 14px rgba(13, 148, 136, 0.25);
    font-family: inherit;
}
.btn-upload:hover {
    background: #0f766e;
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(13, 148, 136, 0.35);
}

/* Stat Grid */
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

.stat-card__num { font-size: 26px; font-weight: 800; color: #0f172a; margin: 0; line-height: 1; }
.stat-card__label { font-size: 13px; font-weight: 600; color: #475569; margin: 4px 0 2px; }
.stat-card__sub { font-size: 11.5px; color: #94a3b8; margin: 0; }

/* Panel */
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
.panel__title { font-size: 15px; font-weight: 800; color: #0f172a; margin: 0 0 2px; }
.panel__sub { font-size: 12.5px; color: #64748b; margin: 0; }

.category-filter-pills {
    display: flex;
    gap: 6px;
}
.filter-pill {
    padding: 4px 12px;
    border-radius: 100px;
    font-size: 12px;
    font-weight: 600;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    color: #64748b;
    cursor: pointer;
    transition: all 0.15s;
}
.filter-pill.active {
    background: #0d9488;
    color: #ffffff;
    border-color: #0d9488;
}

/* Table */
.table-wrap { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.data-table th {
    padding: 10px 16px;
    text-align: left;
    font-size: 11px;
    font-weight: 700;
    color: #94a3b8;
    letter-spacing: 0.5px;
    border-bottom: 1px solid #f1f5f9;
}
.data-table td {
    padding: 13px 16px;
    border-bottom: 1px solid #f8fafc;
    vertical-align: middle;
    color: #0f172a;
}
.data-table tbody tr:hover td { background: #f8fafc; }

.col-title { min-width: 240px; }
.title-text { font-weight: 700; margin: 0 0 4px; line-height: 1.35; }
.meta-row { display: flex; align-items: center; gap: 8px; }
.duration-text { font-size: 11px; color: #94a3b8; }
.cat-pill { font-size: 10.5px; background: #f1f5f9; color: #475569; padding: 2px 7px; border-radius: 4px; font-weight: 600; }

.dyntube-id {
    font-size: 11.5px;
    background: #f1f5f9;
    color: #475569;
    padding: 2px 7px;
    border-radius: 5px;
    font-family: 'Courier New', monospace;
}
.col-num { font-weight: 700; }
.col-date { color: #64748b; font-size: 12px; white-space: nowrap; }

/* Status Badges */
.status-badge { display: inline-block; font-size: 11.5px; font-weight: 700; padding: 3px 9px; border-radius: 100px; }
.status--green { background: #dcfce7; color: #16a34a; }
.status--amber { background: #fef3c7; color: #d97706; }
.status--red   { background: #fee2e2; color: #dc2626; }
.status--slate { background: #f1f5f9; color: #64748b; }

/* Publish Toggle */
.pub-toggle {
    width: 36px;
    height: 20px;
    border-radius: 100px;
    background: #e2e8f0;
    position: relative;
    cursor: pointer;
    transition: background 0.2s;
    display: inline-block;
    vertical-align: middle;
}
.pub-toggle--on { background: #0d9488; }
.pub-toggle__knob {
    position: absolute;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: #ffffff;
    top: 3px;
    left: 3px;
    transition: transform 0.2s;
}
.pub-toggle--on .pub-toggle__knob { transform: translateX(16px); }
.pub-label { font-size: 11.5px; font-weight: 600; color: #64748b; margin-left: 6px; }

/* Actions */
.action-group { display: flex; gap: 4px; }
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
    cursor: pointer;
    text-decoration: none;
    transition: all 0.15s;
}
.action-btn:hover { background: #f0fdfa; border-color: #99f6e4; color: #0d9488; }
.action-btn--ai:hover { background: #fef3c7; border-color: #fde68a; color: #d97706; }
.action-btn--danger:hover { background: #fff1f2; border-color: #fecdd3; color: #ef4444; }

.btn-upload:disabled { opacity: 0.65; cursor: not-allowed; }
.row-transcoding { opacity: 0.8; background-color: #fffbeb !important; }
.pub-toggle--disabled { opacity: 0.5; cursor: not-allowed !important; pointer-events: none; }
.action-btn:disabled, .action-btn--disabled { opacity: 0.35; cursor: not-allowed !important; pointer-events: none; }
.spin-icon { animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.table-empty { text-align: center; padding: 40px !important; color: #94a3b8; }

/* Pagination */
.pagination-wrap { display: flex; justify-content: center; gap: 4px; padding: 16px; border-top: 1px solid #f1f5f9; }
.page-link { padding: 6px 12px; border-radius: 8px; border: 1px solid #e2e8f0; color: #64748b; text-decoration: none; font-size: 12px; }
.page-link.active { background: #0d9488; color: #ffffff; border-color: #0d9488; font-weight: 700; }
</style>
