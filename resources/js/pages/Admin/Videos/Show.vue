<script setup lang="ts">
import ConfirmModal from '@/components/Admin/ConfirmModal.vue';
import DyntubePlayer from '@/components/DyntubePlayer.vue';
import ProcessingModal from '@/components/Admin/ProcessingModal.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Icon } from '@iconify/vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    video: {
        id: number;
        title: string;
        description: string | null;
        dyntube_video_id: string;
        dyntube_iframe_link: string | null;
        dyntube_thumbnail: string | null;
        category_name: string;
        view_count: number;
        quiz_status: string;
        is_published: boolean;
        duration_formatted: string;
        created_at: string;
        objectives: Array<{ id: number; objective_text: string; order_number: number }>;
        questions_count: number;
    };
}>();

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

function togglePublish() {
    router.patch(`/admin/videos/${props.video.id}/publish`, {}, { preserveScroll: true });
}

function triggerGenerateQuiz() {
    const isRegen = props.video.questions_count > 0;
    showConfirmDialog({
        title: isRegen ? 'Regenerate Kuis AI?' : 'Generate Kuis AI?',
        message: isRegen
            ? `Apakah Anda yakin ingin meregenerasi kuis untuk video "${props.video.title}"?\nSemua ${props.video.questions_count} soal yang ada akan diganti dengan 10 soal baru.`
            : `Buat 10 soal kuis evaluasi otomatis menggunakan Gemini 2.0 Flash untuk video "${props.video.title}"?`,
        type: isRegen ? 'warning' : 'info',
        confirmText: isRegen ? 'Regenerate Kuis' : 'Generate Kuis',
        action: () => {
            isGeneratingQuiz.value = true;
            router.post(`/admin/videos/${props.video.id}/generate-quiz`, {}, {
                preserveScroll: true,
                onFinish: () => {
                    isGeneratingQuiz.value = false;
                },
            });
        },
    });
}

function deleteVideo() {
    showConfirmDialog({
        title: 'Hapus Video Materi?',
        message: `Apakah Anda yakin ingin menghapus video "${props.video.title}"?\nSeluruh data kuis dan statistik penonton video ini akan ikut terhapus.`,
        type: 'danger',
        confirmText: 'Hapus Video',
        action: () => {
            router.delete(`/admin/videos/${props.video.id}`);
        },
    });
}
</script>

<template>
    <Head :title="`${video.title} — Detail Video`" />

    <AdminLayout title="Detail Video Edukasi">
        <!-- Topbar Actions Slot -->
        <template #topbar-actions>
            <div class="topbar-actions">
                <Link href="/admin/videos" class="btn-back">
                    <Icon icon="lucide:arrow-left" width="16" height="16" />
                    Kembali
                </Link>
                <button type="button" class="btn-toggle-pub" @click="togglePublish">
                    <Icon :icon="video.is_published ? 'lucide:eye-off' : 'lucide:eye'" width="16" height="16" />
                    {{ video.is_published ? 'Sembunyikan (Draft)' : 'Publikasikan' }}
                </button>
            </div>
        </template>

        <div class="detail-grid">
            <!-- Left Main Column: Player & Info -->
            <div class="main-col">
                <!-- Video Player Preview -->
                <div class="player-card">
                    <DyntubePlayer
                        :video-key="video.dyntube_video_id"
                        :iframe-link="video.dyntube_iframe_link"
                        :title="video.title"
                    />
                </div>

                <!-- Video Header & Description -->
                <div class="info-card">
                    <div class="info-header">
                        <span class="cat-pill">{{ video.category_name }}</span>
                        <span :class="['status-pill', video.is_published ? 'pub-on' : 'pub-off']">
                            {{ video.is_published ? 'Tayang' : 'Draft' }}
                        </span>
                    </div>

                    <h2 class="video-title">{{ video.title }}</h2>

                    <div class="meta-row">
                        <span class="meta-item">
                            <Icon icon="lucide:clock" width="14" height="14" />
                            {{ video.duration_formatted }}
                        </span>
                        <span class="meta-item">
                            <Icon icon="lucide:eye" width="14" height="14" />
                            {{ video.view_count.toLocaleString('id-ID') }} Penonton
                        </span>
                        <span class="meta-item">
                            <Icon icon="lucide:calendar" width="14" height="14" />
                            {{ video.created_at }}
                        </span>
                    </div>

                    <div class="divider"></div>

                    <h4 class="section-title">Deskripsi Materi</h4>
                    <p class="desc-text">{{ video.description || 'Tidak ada deskripsi.' }}</p>

                    <div v-if="video.transcript" class="transcript-box">
                        <h4 class="section-title">
                            <Icon icon="lucide:file-text" width="16" height="16" class="title-icon" />
                            Naskah / Transkrip / Timeline Video per Menit
                        </h4>
                        <pre class="transcript-text">{{ video.transcript }}</pre>
                    </div>
                </div>
            </div>

            <!-- Right Sidebar Column: Metadata & AI Quiz Status -->
            <div class="side-col">
                <!-- Dyntube Info Card -->
                <div class="card side-card">
                    <h4 class="side-title">
                        <Icon icon="lucide:video" width="16" height="16" />
                        Dyntube Integration
                    </h4>
                    <div class="info-list">
                        <div class="info-pair">
                            <span class="info-label">Dyntube ID</span>
                            <code class="info-val code-font">{{ video.dyntube_video_id }}</code>
                        </div>
                        <div class="info-pair">
                            <span class="info-label">Iframe Link</span>
                            <span class="info-val truncate">{{ video.dyntube_iframe_link || 'Standard' }}</span>
                        </div>
                    </div>
                </div>

                <!-- AI Quiz Status Card -->
                <div class="card side-card">
                    <h4 class="side-title">
                        <Icon icon="lucide:brain-circuit" width="16" height="16" />
                        Kuis Evaluasi AI
                    </h4>
                    <div class="quiz-info-box">
                        <div class="quiz-status-row">
                            <span class="quiz-label">Status Kuis:</span>
                            <span :class="['quiz-badge', `quiz--${video.quiz_status}`]">
                                {{ video.quiz_status.toUpperCase() }}
                            </span>
                        </div>
                        <p class="quiz-desc">
                            Total {{ video.questions_count }} soal evaluasi piihan ganda.
                        </p>
                        <Link
                            :href="`/admin/videos/${video.id}/quiz`"
                            class="btn-gen-quiz"
                        >
                            <Icon icon="lucide:brain-circuit" width="15" height="15" />
                            Kelola Kuis Evaluasi ({{ video.questions_count }} Soal)
                        </Link>
                    </div>
                </div>

                <!-- Actions Card -->
                <div class="card side-card">
                    <h4 class="side-title">Aksi Manajemen</h4>
                    <div class="actions-stack">
                        <button type="button" class="btn-action-danger" @click="deleteVideo">
                            <Icon icon="lucide:trash-2" width="16" height="16" />
                            Hapus Video Ini
                        </button>
                    </div>
                </div>
            </div>
        </div>

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
.topbar-actions { display: flex; gap: 10px; }
.btn-back {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 16px; border-radius: 100px; font-size: 13px; font-weight: 600;
    border: 1px solid #e2e8f0; background: #ffffff; color: #64748b; text-decoration: none;
}
.btn-toggle-pub {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 18px; border-radius: 100px; font-size: 13px; font-weight: 700;
    border: none; background: #0d9488; color: #ffffff; cursor: pointer;
}

.detail-grid { display: grid; grid-template-columns: 1fr 340px; gap: 24px; }
.main-col { display: flex; flex-direction: column; gap: 20px; }

.player-card { border-radius: 18px; overflow: hidden; }
.info-card { background: #ffffff; border: 1px solid #f1f5f9; border-radius: 18px; padding: 24px; }
.info-header { display: flex; gap: 8px; margin-bottom: 12px; }
.cat-pill { font-size: 11px; font-weight: 700; background: #f0fdfa; color: #0d9488; border: 1px solid #99f6e4; padding: 3px 10px; border-radius: 100px; }
.status-pill { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 100px; }
.pub-on { background: #dcfce7; color: #16a34a; }
.pub-off { background: #f1f5f9; color: #64748b; }

.video-title { font-size: 22px; font-weight: 800; color: #0f172a; margin: 0 0 10px; line-height: 1.3; }
.meta-row { display: flex; gap: 16px; color: #64748b; font-size: 12.5px; font-weight: 500; }
.meta-item { display: flex; align-items: center; gap: 5px; }

.divider { height: 1px; background: #f1f5f9; margin: 20px 0; }
.section-title { font-size: 15px; font-weight: 800; color: #0f172a; margin: 0 0 8px; }
.desc-text { font-size: 14px; color: #475569; line-height: 1.6; margin: 0; }

.transcript-box { margin-top: 20px; padding-top: 16px; border-top: 1px dashed #e2e8f0; }
.title-icon { color: #0d9488; vertical-align: middle; margin-right: 4px; }
.transcript-text {
    background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px;
    padding: 14px 16px; font-size: 13px; color: #334155; line-height: 1.6;
    font-family: inherit; white-space: pre-wrap; word-break: break-word; margin: 8px 0 0;
}

.side-col { display: flex; flex-direction: column; gap: 18px; }
.side-card { background: #ffffff; border: 1px solid #f1f5f9; border-radius: 18px; padding: 20px; }
.side-title { font-size: 14px; font-weight: 800; color: #0f172a; margin: 0 0 14px; display: flex; align-items: center; gap: 6px; }

.info-list { display: flex; flex-direction: column; gap: 10px; }
.info-pair { display: flex; justify-content: space-between; align-items: center; font-size: 12.5px; }
.info-label { color: #64748b; }
.info-val { font-weight: 600; color: #0f172a; }
.code-font { font-family: 'Courier New', monospace; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; }
.truncate { max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.quiz-info-box { display: flex; flex-direction: column; gap: 10px; }
.quiz-status-row { display: flex; justify-content: space-between; align-items: center; font-size: 13px; font-weight: 600; }
.quiz-label { color: #475569; }
.quiz-badge { font-size: 11px; font-weight: 800; padding: 3px 9px; border-radius: 100px; }
.quiz--aktif { background: #dcfce7; color: #16a34a; }
.quiz--proses { background: #fef3c7; color: #d97706; }
.quiz--gagal { background: #fee2e2; color: #dc2626; }
.quiz--belum { background: #f1f5f9; color: #64748b; }
.quiz-desc { font-size: 12px; color: #64748b; margin: 0; }

.btn-gen-quiz {
    display: flex; align-items: center; justify-content: center; gap: 6px;
    width: 100%; padding: 9px; background: #0d9488; color: #ffffff;
    border: none; border-radius: 10px; font-size: 13px; font-weight: 700; cursor: pointer;
}

.actions-stack { display: flex; flex-direction: column; gap: 8px; }
.btn-action-danger {
    display: flex; align-items: center; justify-content: center; gap: 6px;
    width: 100%; padding: 9px; background: #fff1f2; color: #ef4444;
    border: 1px solid #fecdd3; border-radius: 10px; font-size: 13px; font-weight: 700; cursor: pointer;
}

@media (max-width: 1024px) {
    .detail-grid { grid-template-columns: 1fr; }
}
</style>
