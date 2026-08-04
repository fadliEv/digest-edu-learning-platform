<script setup lang="ts">
import ConfirmModal from '@/components/Admin/ConfirmModal.vue';
import ProcessingModal from '@/components/Admin/ProcessingModal.vue';
import QuestionModal from '@/components/Admin/QuestionModal.vue';
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
        category_name: string;
        quiz_status: string;
        is_published: boolean;
        questions_count: number;
        duration_seconds?: number | null;
    };
    questions: Array<{
        id: number;
        video_id: number;
        question_text: string;
        options: { A: string; B: string; C: string; D: string };
        correct_answer: string;
        order_number: number;
        video_timestamp_seconds: number | null;
    }>;
}>();

// ─── Modal State ─────────────────────────────────────────────────────────────
const isQuestionModalOpen = ref(false);
const selectedQuestionToEdit = ref<any | null>(null);
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

function openAddQuestionModal() {
    selectedQuestionToEdit.value = null;
    isQuestionModalOpen.value = true;
}

function openEditQuestionModal(q: any) {
    selectedQuestionToEdit.value = q;
    isQuestionModalOpen.value = true;
}

// ─── Actions ─────────────────────────────────────────────────────────────────
function triggerGenerateQuiz() {
    const isRegen = props.questions.length > 0;
    showConfirmDialog({
        title: isRegen ? 'Regenerate Kuis AI?' : 'Generate Kuis AI?',
        message: isRegen
            ? `Apakah Anda yakin ingin meregenerasi kuis untuk video "${props.video.title}"?\nSemua ${props.questions.length} soal yang ada akan diganti dengan 10 soal baru.`
            : `Buat 10 soal kuis evaluasi otomatis menggunakan Gemini 2.0 Flash untuk video "${props.video.title}"?`,
        type: isRegen ? 'warning' : 'info',
        confirmText: isRegen ? 'Regenerate Kuis' : 'Generate Kuis',
        action: () => {
            isGeneratingQuiz.value = true;
            router.post(`/admin/videos/${props.video.id}/quiz/generate`, {}, {
                preserveScroll: true,
                onFinish: () => {
                    isGeneratingQuiz.value = false;
                },
            });
        },
    });
}

function deleteQuestion(q: any) {
    showConfirmDialog({
        title: 'Hapus Soal Kuis?',
        message: `Apakah Anda yakin ingin menghapus Soal #${q.order_number}?\nAksi ini tidak dapat dibatalkan.`,
        type: 'danger',
        confirmText: 'Hapus Soal',
        action: () => {
            router.delete(`/admin/questions/${q.id}`, { preserveScroll: true });
        },
    });
}

// ─── Helpers ─────────────────────────────────────────────────────────────────
const quizStatusMap: Record<string, { label: string; class: string }> = {
    aktif:  { label: 'Aktif (Generated)', class: 'status--green' },
    proses: { label: 'Proses AI',         class: 'status--amber' },
    gagal:  { label: 'Gagal',             class: 'status--red' },
    belum:  { label: 'Belum Ada Kuis',    class: 'status--slate' },
};

function getQuizBadge(status: string) {
    return quizStatusMap[status] ?? { label: status, class: 'status--slate' };
}

/**
 * Convert seconds to "MM:SS" string for display badge.
 * e.g. 125 → "02:05"
 */
function formatTimestamp(secs: number | null): string | null {
    if (secs === null || secs === undefined) return null;
    const m = Math.floor(secs / 60);
    const s = secs % 60;
    return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}
</script>

<template>
    <Head :title="`Manajemen Kuis — ${video.title}`" />

    <AdminLayout title="Manajemen Kuis Evaluasi AI">
        <!-- Topbar Actions Slot -->
        <template #topbar-actions>
            <div class="topbar-actions">
                <Link :href="`/admin/videos/${video.id}`" class="btn-back">
                    <Icon icon="lucide:arrow-left" width="16" height="16" />
                    Detail Video
                </Link>
                <button type="button" class="btn-ai" @click="triggerGenerateQuiz">
                    <Icon icon="lucide:sparkles" width="16" height="16" />
                    {{ questions.length > 0 ? 'Regenerate Kuis' : 'Generate Kuis' }}
                </button>
                <button type="button" class="btn-add" @click="openAddQuestionModal">
                    <Icon icon="lucide:plus" width="16" height="16" />
                    Tambah Soal Manual
                </button>
            </div>
        </template>

        <!-- ══ HEADER CARD ══ -->
        <div class="video-quiz-header">
            <div class="header-main">
                <div class="meta-pills">
                    <span class="cat-pill">{{ video.category_name }}</span>
                    <span :class="['status-badge', getQuizBadge(video.quiz_status).class]">
                        {{ getQuizBadge(video.quiz_status).label }}
                    </span>
                </div>
                <h2 class="video-title">{{ video.title }}</h2>
                <p class="video-desc">{{ video.description || 'Tidak ada deskripsi materi.' }}</p>
            </div>
            <div class="header-stats">
                <div class="stat-circle">
                    <span class="stat-num">{{ questions.length }}</span>
                    <span class="stat-lbl">Soal Kuis</span>
                </div>
                <div v-if="video.duration_seconds" class="stat-circle stat-circle--teal">
                    <span class="stat-num">{{ Math.floor(video.duration_seconds / 60) }}</span>
                    <span class="stat-lbl">Menit Video</span>
                </div>
            </div>
        </div>

        <!-- ══ QUESTIONS LIST ══ -->
        <div class="questions-section">
            <div class="section-title-bar">
                <h3 class="section-title">Daftar Soal Evaluasi (Pilihan Ganda)</h3>
                <span class="section-count">{{ questions.length }} Soal Terdaftar</span>
            </div>

            <!-- Empty State -->
            <div v-if="questions.length === 0" class="empty-card">
                <Icon icon="lucide:file-question" width="44" height="44" class="empty-icon" />
                <h4 class="empty-title">Belum ada soal kuis untuk video ini</h4>
                <p class="empty-desc">
                    Klik tombol "Generate Kuis" untuk membuat 6 soal evaluasi otomatis menggunakan Gemini 2.0 Flash, atau buat soal secara manual.
                </p>
                <div class="empty-actions">
                    <button type="button" class="btn-ai" @click="triggerGenerateQuiz">
                        <Icon icon="lucide:sparkles" width="16" height="16" />
                        {{ questions.length > 0 ? 'Regenerate Kuis' : 'Generate Kuis' }}
                    </button>
                    <button type="button" class="btn-add" @click="openAddQuestionModal">
                        <Icon icon="lucide:plus" width="16" height="16" />
                        Tambah Soal Manual
                    </button>
                </div>
            </div>

            <!-- Questions Cards -->
            <div v-else class="questions-grid">
                <div v-for="(q, idx) in questions" :key="q.id" class="question-card">
                    <!-- Question Header -->
                    <div class="q-card-header">
                        <div class="q-header-left">
                            <div class="q-badge">Soal #{{ idx + 1 }}</div>
                        </div>
                        <div class="q-actions">
                            <button class="q-act-btn" title="Edit Soal" @click="openEditQuestionModal(q)">
                                <Icon icon="lucide:pencil" width="14" height="14" />
                            </button>
                            <button class="q-act-btn q-act-btn--danger" title="Hapus Soal" @click="deleteQuestion(q)">
                                <Icon icon="lucide:trash-2" width="14" height="14" />
                            </button>
                        </div>
                    </div>

                    <!-- Question Text -->
                    <p class="q-text">{{ q.question_text }}</p>

                    <!-- Options Grid A, B, C, D -->
                    <div class="options-list">
                        <div
                            v-for="(optText, key) in q.options"
                            :key="key"
                            :class="['option-row', { 'option-row--correct': key === q.correct_answer }]"
                        >
                            <span class="opt-key">{{ key }}</span>
                            <span class="opt-text">{{ optText }}</span>
                            <span v-if="key === q.correct_answer" class="correct-badge">
                                <Icon icon="lucide:check-circle-2" width="14" height="14" />
                                Jawaban Benar
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ QUESTION MODAL ══ -->
        <QuestionModal
            :show="isQuestionModalOpen"
            :video-id="video.id"
            :video-duration-seconds="video.duration_seconds ?? undefined"
            :edit-question="selectedQuestionToEdit"
            @close="isQuestionModalOpen = false"
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
.topbar-actions { display: flex; gap: 10px; }
.btn-back {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 16px; border-radius: 100px; font-size: 13px; font-weight: 600;
    border: 1px solid #e2e8f0; background: #ffffff; color: #64748b; text-decoration: none;
}
.btn-ai {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 18px; border-radius: 100px; font-size: 13px; font-weight: 700;
    border: none; background: #d97706; color: #ffffff; cursor: pointer;
    box-shadow: 0 4px 14px rgba(217, 119, 6, 0.25);
}
.btn-ai:hover { background: #b45309; }
.btn-add {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 18px; border-radius: 100px; font-size: 13px; font-weight: 700;
    border: none; background: #0d9488; color: #ffffff; cursor: pointer;
    box-shadow: 0 4px 14px rgba(13, 148, 136, 0.25);
}
.btn-add:hover { background: #0f766e; }

/* Header Card */
.video-quiz-header {
    background: #ffffff; border: 1px solid #f1f5f9; border-radius: 20px;
    padding: 24px 28px; display: flex; align-items: center; justify-content: space-between;
    gap: 24px; margin-bottom: 16px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
}
.meta-pills { display: flex; gap: 8px; margin-bottom: 8px; }
.cat-pill { font-size: 11px; font-weight: 700; background: #f0fdfa; color: #0d9488; border: 1px solid #99f6e4; padding: 3px 10px; border-radius: 100px; }
.status-badge { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 100px; }
.status--green { background: #dcfce7; color: #16a34a; }
.status--amber { background: #fef3c7; color: #d97706; }
.status--red   { background: #fee2e2; color: #dc2626; }
.status--slate { background: #f1f5f9; color: #64748b; }

.video-title { font-size: 20px; font-weight: 800; color: #0f172a; margin: 0 0 6px; letter-spacing: -0.4px; }
.video-desc { font-size: 13.5px; color: #64748b; margin: 0; line-height: 1.5; }

.header-stats { display: flex; gap: 12px; flex-shrink: 0; }
.stat-circle {
    width: 76px; height: 76px; border-radius: 50%; background: #f0fdfa; border: 2px solid #ccfbf1;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
}
.stat-circle--teal { background: #f0fdf4; border-color: #bbf7d0; }
.stat-num { font-size: 22px; font-weight: 800; color: #0d9488; line-height: 1; }
.stat-circle--teal .stat-num { color: #16a34a; }
.stat-lbl { font-size: 9.5px; font-weight: 700; color: #0f766e; margin-top: 2px; text-align: center; padding: 0 4px; }

/* Info Box */
.info-box {
    display: flex; align-items: flex-start; gap: 10px;
    background: #f0fdfa; border: 1px solid #99f6e4; border-radius: 12px;
    padding: 12px 16px; margin-bottom: 20px;
}
.info-icon { color: #0d9488; flex-shrink: 0; margin-top: 1px; }
.info-box p { font-size: 12.5px; color: #0f766e; margin: 0; line-height: 1.55; }
.info-box strong { font-weight: 800; }

/* Questions Section */
.questions-section { }
.section-title-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.section-title { font-size: 16px; font-weight: 800; color: #0f172a; margin: 0; }
.section-count { font-size: 12.5px; color: #64748b; font-weight: 600; }

.empty-card {
    background: #ffffff; border: 1px solid #f1f5f9; border-radius: 20px; padding: 48px 32px;
    display: flex; flex-direction: column; align-items: center; text-align: center; gap: 12px;
}
.empty-icon { color: #cbd5e1; }
.empty-title { font-size: 16px; font-weight: 800; color: #0f172a; margin: 0; }
.empty-desc { font-size: 13.5px; color: #64748b; max-width: 480px; margin: 0; line-height: 1.5; }
.empty-actions { display: flex; gap: 12px; margin-top: 8px; }

/* Questions Grid */
.questions-grid { display: flex; flex-direction: column; gap: 16px; }
.question-card {
    background: #ffffff; border: 1px solid #f1f5f9; border-radius: 18px; padding: 20px 24px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03); transition: transform 0.2s, box-shadow 0.2s;
}
.question-card:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(0, 0, 0, 0.05); }

.q-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; gap: 8px; }
.q-header-left { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.q-badge { font-size: 11.5px; font-weight: 800; background: #f1f5f9; color: #475569; padding: 3px 10px; border-radius: 6px; flex-shrink: 0; }

/* Timestamp Badge */
.timestamp-badge {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 11px; font-weight: 700;
    background: #fefce8; color: #a16207;
    border: 1px solid #fde68a;
    padding: 3px 9px; border-radius: 100px;
    cursor: default;
}
.timestamp-badge--empty {
    background: #f8fafc; color: #94a3b8; border-color: #e2e8f0;
    font-weight: 500; font-style: italic;
}

.q-actions { display: flex; gap: 4px; flex-shrink: 0; }
.q-act-btn {
    width: 30px; height: 30px; border-radius: 8px; background: #f8fafc; border: 1px solid #e2e8f0;
    color: #64748b; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.15s;
}
.q-act-btn:hover { background: #f0fdfa; border-color: #99f6e4; color: #0d9488; }
.q-act-btn--danger:hover { background: #fff1f2; border-color: #fecdd3; color: #ef4444; }

.q-text { font-size: 15px; font-weight: 700; color: #0f172a; margin: 0 0 14px; line-height: 1.45; }

.options-list { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.option-row {
    display: flex; align-items: center; gap: 10px; padding: 10px 14px;
    background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 13px; color: #334155;
}
.option-row--correct { background: #f0fdf4; border-color: #bbf7d0; color: #16a34a; font-weight: 600; }

.opt-key {
    width: 22px; height: 22px; border-radius: 50%; background: #e2e8f0; color: #475569;
    font-size: 11px; font-weight: 800; display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.option-row--correct .opt-key { background: #16a34a; color: #ffffff; }

.opt-text { flex: 1; }
.correct-badge { display: flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 700; color: #16a34a; white-space: nowrap; }

@media (max-width: 768px) {
    .options-list { grid-template-columns: 1fr; }
    .video-quiz-header { flex-direction: column; align-items: flex-start; }
}
</style>
