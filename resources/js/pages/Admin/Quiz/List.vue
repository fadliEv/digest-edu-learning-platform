<script setup lang="ts">
import ConfirmModal from '@/components/Admin/ConfirmModal.vue';
import ProcessingModal from '@/components/Admin/ProcessingModal.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Icon } from '@iconify/vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps<{
    videos: Array<{
        id: number;
        title: string;
        category_name: string;
        quiz_status: string;
        is_published: boolean;
        questions_count: number;
        created_at: string;
    }>;
    stats: {
        total_videos: number;
        quiz_active: number;
        quiz_proses: number;
        quiz_belum: number;
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

function triggerGenerateQuiz(video: any) {
    const isRegen = video.questions_count > 0;
    confirmModalTitle.value = isRegen ? 'Regenerate Kuis AI?' : 'Generate Kuis AI?';
    confirmModalMessage.value = isRegen
        ? `Apakah Anda yakin ingin meregenerasi kuis untuk video "${video.title}"?\nSemua ${video.questions_count} soal yang ada akan diganti dengan 10 soal baru.`
        : `Buat 10 soal kuis evaluasi otomatis menggunakan Gemini 2.0 Flash untuk video "${video.title}"?`;
    confirmModalType.value = isRegen ? 'warning' : 'info';
    confirmModalConfirmText.value = isRegen ? 'Regenerate Kuis' : 'Generate Kuis';
    onConfirmAction.value = () => {
        isGeneratingQuiz.value = true;
        router.post(`/admin/videos/${video.id}/quiz/generate`, {}, {
            preserveScroll: true,
            onFinish: () => {
                isGeneratingQuiz.value = false;
            },
        });
    };
    confirmModalShow.value = true;
}

function handleModalConfirm() {
    confirmModalShow.value = false;
    if (onConfirmAction.value) {
        onConfirmAction.value();
        onConfirmAction.value = null;
    }
}

const quizStatusMap: Record<string, { label: string; class: string }> = {
    aktif:  { label: 'Aktif (Generated)', class: 'status--green' },
    proses: { label: 'Proses AI',         class: 'status--amber' },
    gagal:  { label: 'Gagal',             class: 'status--red' },
    belum:  { label: 'Belum Ada Kuis',    class: 'status--slate' },
};

function getQuizBadge(status: string) {
    return quizStatusMap[status] ?? { label: status, class: 'status--slate' };
}
</script>

<template>
    <Head title="Manajemen Kuis Evaluasi — Admin DigestEdu" />

    <AdminLayout title="Manajemen Kuis Evaluasi AI">
        <!-- ══ STAT CARDS ══ -->
        <div class="stat-grid">
            <div class="stat-card">
                <div class="stat-card__icon stat-card__icon--green">
                    <Icon icon="lucide:brain-circuit" width="20" height="20" />
                </div>
                <div class="stat-card__body">
                    <p class="stat-card__num">{{ stats.quiz_active }}</p>
                    <p class="stat-card__label">Kuis AI Aktif</p>
                    <p class="stat-card__sub">Siap dikerjakan siswa</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-card__icon stat-card__icon--amber">
                    <Icon icon="lucide:loader-circle" width="20" height="20" />
                </div>
                <div class="stat-card__body">
                    <p class="stat-card__num">{{ stats.quiz_proses }}</p>
                    <p class="stat-card__label">Proses AI Gemini</p>
                    <p class="stat-card__sub">Sedang dibuat</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-card__icon stat-card__icon--slate">
                    <Icon icon="lucide:help-circle" width="20" height="20" />
                </div>
                <div class="stat-card__body">
                    <p class="stat-card__num">{{ stats.quiz_belum }}</p>
                    <p class="stat-card__label">Belum Ada Kuis</p>
                    <p class="stat-card__sub">Butuh penambahan kuis</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-card__icon stat-card__icon--teal">
                    <Icon icon="lucide:video" width="20" height="20" />
                </div>
                <div class="stat-card__body">
                    <p class="stat-card__num">{{ stats.total_videos }}</p>
                    <p class="stat-card__label">Total Video</p>
                    <p class="stat-card__sub">Modul materi edukasi</p>
                </div>
            </div>
        </div>

        <!-- ══ PANEL TABLE ══ -->
        <div class="panel">
            <div class="panel__header">
                <div>
                    <h3 class="panel__title">Daftar Kuis Evaluasi Materi</h3>
                    <p class="panel__sub">Kelola kuis AI Gemini dan soal manual per video</p>
                </div>
            </div>

            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>JUDUL VIDEO MATERI</th>
                            <th>KATEGORI</th>
                            <th>JUMLAH SOAL</th>
                            <th>STATUS KUIS AI</th>
                            <th>AKSI MANAJEMEN</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="videos.length === 0">
                            <td colspan="5" class="table-empty">
                                <Icon icon="lucide:brain-circuit" width="28" height="28" />
                                Belum ada video materi terdaftar.
                            </td>
                        </tr>
                        <tr v-for="v in videos" :key="v.id">
                            <td class="col-title">
                                <p class="title-text">{{ v.title }}</p>
                                <span class="date-sub">{{ v.created_at }}</span>
                            </td>
                            <td>
                                <span class="cat-pill">{{ v.category_name }}</span>
                            </td>
                            <td class="col-num">
                                <span class="questions-count-badge">{{ v.questions_count }} Soal</span>
                            </td>
                            <td>
                                <span :class="['status-badge', getQuizBadge(v.quiz_status).class]">
                                    {{ getQuizBadge(v.quiz_status).label }}
                                </span>
                            </td>
                            <td>
                                <div class="action-group">
                                    <Link :href="`/admin/videos/${v.id}/quiz`" class="btn-manage-quiz">
                                        <Icon icon="lucide:brain-circuit" width="14" height="14" />
                                        Kelola Kuis
                                    </Link>
                                    <button
                                        type="button"
                                        class="btn-gen-ai"
                                        :title="v.questions_count > 0 ? 'Regenerate Kuis AI Gemini' : 'Generate Kuis AI Gemini'"
                                        @click="triggerGenerateQuiz(v)"
                                    >
                                        <Icon icon="lucide:sparkles" width="14" height="14" />
                                        {{ v.questions_count > 0 ? 'Regenerate Kuis' : 'Generate Kuis' }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
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
.stat-grid {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; margin-bottom: 28px;
}
.stat-card {
    background: #ffffff; border: 1px solid #f1f5f9; border-radius: 18px; padding: 22px 20px;
    display: flex; align-items: flex-start; gap: 16px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
}
.stat-card__icon {
    width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.stat-card__icon--green { background: #f0fdf4; color: #16a34a; }
.stat-card__icon--amber { background: #fffbeb; color: #d97706; }
.stat-card__icon--slate { background: #f8fafc; color: #64748b; }
.stat-card__icon--teal  { background: #f0fdfa; color: #0d9488; }

.stat-card__num { font-size: 26px; font-weight: 800; color: #0f172a; margin: 0; line-height: 1; }
.stat-card__label { font-size: 13px; font-weight: 600; color: #475569; margin: 4px 0 2px; }
.stat-card__sub { font-size: 11.5px; color: #94a3b8; margin: 0; }

.panel { background: #ffffff; border: 1px solid #f1f5f9; border-radius: 18px; overflow: hidden; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03); }
.panel__header { padding: 20px 24px 16px; border-bottom: 1px solid #f1f5f9; }
.panel__title { font-size: 15px; font-weight: 800; color: #0f172a; margin: 0 0 2px; }
.panel__sub { font-size: 12.5px; color: #64748b; margin: 0; }

.table-wrap { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.data-table th { padding: 10px 16px; text-align: left; font-size: 11px; font-weight: 700; color: #94a3b8; letter-spacing: 0.5px; border-bottom: 1px solid #f1f5f9; }
.data-table td { padding: 13px 16px; border-bottom: 1px solid #f8fafc; vertical-align: middle; color: #0f172a; }
.data-table tbody tr:hover td { background: #f8fafc; }

.title-text { font-weight: 700; margin: 0 0 2px; }
.date-sub { font-size: 11px; color: #94a3b8; }
.cat-pill { font-size: 10.5px; background: #f0fdfa; color: #0d9488; border: 1px solid #99f6e4; padding: 2px 8px; border-radius: 100px; font-weight: 600; }
.questions-count-badge { font-weight: 700; color: #0f172a; background: #f1f5f9; padding: 3px 9px; border-radius: 6px; font-size: 12px; }

.status-badge { display: inline-block; font-size: 11.5px; font-weight: 700; padding: 3px 9px; border-radius: 100px; }
.status--green { background: #dcfce7; color: #16a34a; }
.status--amber { background: #fef3c7; color: #d97706; }
.status--red   { background: #fee2e2; color: #dc2626; }
.status--slate { background: #f1f5f9; color: #64748b; }

.action-group { display: flex; gap: 8px; }
.btn-manage-quiz {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 6px 14px; border-radius: 8px; background: #0d9488; color: #ffffff;
    font-size: 12.5px; font-weight: 700; text-decoration: none; transition: background 0.15s;
}
.btn-manage-quiz:hover { background: #0f766e; }

.btn-gen-ai {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 6px 14px; border-radius: 8px; background: #fffbeb; color: #d97706;
    border: 1px solid #fde68a; font-size: 12.5px; font-weight: 700; cursor: pointer; transition: all 0.15s;
}
.btn-gen-ai:hover { background: #fef3c7; }

.table-empty { text-align: center; padding: 40px !important; color: #94a3b8; }
</style>
