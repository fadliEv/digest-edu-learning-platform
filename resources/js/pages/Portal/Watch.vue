<script setup lang="ts">
import DyntubePlayer from '@/components/DyntubePlayer.vue';
import UserLayout from '@/layouts/UserLayout.vue';
import { Icon } from '@iconify/vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

// ─── Props ─────────────────────────────────────────────────────────────────────
const props = defineProps<{
    video: {
        id: number;
        title: string;
        description: string | null;
        category: string;
        category_id: number;
        dyntube_video_id: string;
        dyntube_iframe_link: string | null;
        dyntube_thumbnail: string | null;
        duration_seconds: number | null;
        duration: string;
        view_count: string;
        quiz_status: string;
        created_at: string;
    };
    questions: Array<{
        id: number;
        question_text: string;
        options: Record<string, string>;
        correct_answer: string;
        order_number: number;
    }>;
    objectives: Array<{ id: number; objective_text: string; order_number: number }>;
    resume: { last_position: number; progress: number };
    bestScore: number | null;
    relatedVideos: Array<{
        id: number;
        title: string;
        thumbnail: string | null;
        category: string;
        duration: string;
        views: string;
    }>;
}>();

// ─── Flash ─────────────────────────────────────────────────────────────────────
const page = usePage();
const quizResult = computed(() => (page.props.flash as any)?.quizResult ?? null);

// ─── Tab ───────────────────────────────────────────────────────────────────────
const activeTab = ref<'info' | 'quiz'>('info');

// Switch to quiz tab if result just came in
watch(quizResult, (v) => { if (v) activeTab.value = 'quiz'; }, { immediate: true });

// ─── Quiz State ────────────────────────────────────────────────────────────────
const currentQ    = ref(0);
const answers     = ref<Record<number, string>>({}); // questionId → chosen option
const submitting  = ref(false);
const showResult  = ref(!!quizResult.value);
const lastResult  = ref(quizResult.value);

function selectOption(questionId: number, option: string) {
    answers.value[questionId] = option;
}

function nextQ() {
    if (currentQ.value < props.questions.length - 1) currentQ.value++;
}
function prevQ() {
    if (currentQ.value > 0) currentQ.value--;
}

const allAnswered = computed(() =>
    props.questions.every((q) => answers.value[q.id] !== undefined)
);

function submitQuiz() {
    if (!allAnswered.value || submitting.value) return;
    submitting.value = true;

    const payload = props.questions.map((q) => ({
        question_id: q.id,
        chosen: answers.value[q.id],
    }));

    router.post(
        `/video/${props.video.id}/quiz/submit`,
        { answers: payload },
        {
            onSuccess: () => {
                submitting.value = false;
                showResult.value = true;
                lastResult.value = (usePage().props.flash as any)?.quizResult;
            },
            onError: () => { submitting.value = false; },
        }
    );
}

function retakeQuiz() {
    answers.value  = {};
    currentQ.value = 0;
    showResult.value = false;
    lastResult.value = null;
}

// ─── Watch-gating: quiz unlocks only after the user has watched enough ───────
// Threshold: 90% of the video counts as "watched". Reactive so the quiz
// unlocks live (without a page reload) once the watched position passes it.
const WATCH_THRESHOLD = 0.9;
const watchedSeconds = ref(props.resume.last_position || 0);
const watchProgressPct = computed(() => {
    const dur = props.video.duration_seconds || 0;
    if (dur <= 0) return 0;
    return Math.min(100, Math.round((watchedSeconds.value / dur) * 100));
});
const hasWatchedEnough = computed(() => {
    const dur = props.video.duration_seconds || 0;
    // If we don't know the duration, don't hard-lock the user out.
    if (dur <= 0) return true;
    return watchedSeconds.value >= dur * WATCH_THRESHOLD;
});

// ─── Resume / progress tracking ─────────────────────────────────────────────
let resumeInterval: ReturnType<typeof setInterval> | null = null;
let currentPos = props.resume.last_position || 15;

function saveResume(positionSeconds: number) {
    fetch(`/video/${props.video.id}/resume`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
        },
        body: JSON.stringify({ position: positionSeconds }),
    }).catch(() => {});
}

function handleWindowMessage(e: MessageEvent) {
    try {
        const data = typeof e.data === 'string' ? JSON.parse(e.data) : e.data;
        if (data && (data.currentTime !== undefined || data.time !== undefined)) {
            const time = data.currentTime ?? data.time;
            if (typeof time === 'number' && time > 0) {
                currentPos = Math.round(time);
                if (currentPos > watchedSeconds.value) watchedSeconds.value = currentPos;
            }
        }
    } catch {}
}

onMounted(() => {
    // Immediately save initial position on page visit so video is marked as in-progress
    saveResume(currentPos);

    window.addEventListener('message', handleWindowMessage);

    // Save & advance position every 5s while user stays on page
    resumeInterval = setInterval(() => {
        currentPos += 5;
        if (props.video.duration_seconds && currentPos > props.video.duration_seconds) {
            currentPos = props.video.duration_seconds;
        }
        if (currentPos > watchedSeconds.value) watchedSeconds.value = currentPos;
        saveResume(currentPos);
    }, 5_000);
});

onUnmounted(() => {
    window.removeEventListener('message', handleWindowMessage);
    if (resumeInterval) clearInterval(resumeInterval);
});

// ─── Category pill colors ────────────────────────────────────────────────────
const categoryClassMap: Record<string, string> = {
    Nutrisi: 'pill--emerald', Anatomi: 'pill--cyan',
    Pencegahan: 'pill--amber', Penyakit: 'pill--rose',
    'Gaya Hidup': 'pill--teal', Umum: 'pill--slate',
};
function getCategoryClass(cat: string) {
    return categoryClassMap[cat] ?? 'pill--slate';
}

const fallbackThumb = 'https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?w=500&h=300&fit=crop';
</script>

<template>
    <Head :title="`${video.title} — DigestEdu`" />

    <UserLayout>
        <!-- ── Breadcrumb ── -->
        <div class="breadcrumb">
            <Link href="/home" class="bc-link">Beranda</Link>
            <Icon icon="lucide:chevron-right" width="13" height="13" class="bc-sep" />
            <span :class="['category-pill', getCategoryClass(video.category)]">{{ video.category }}</span>
            <Icon icon="lucide:chevron-right" width="13" height="13" class="bc-sep" />
            <span class="bc-current">{{ video.title }}</span>
        </div>

        <!-- ── Main Layout: Player + Sidebar ── -->
        <div class="watch-layout">
            <!-- ════ LEFT: VIDEO + TABS ════ -->
            <div class="watch-main">

                <!-- Video Player -->
                <div class="player-wrap">
                    <DyntubePlayer
                        v-if="video.dyntube_iframe_link || video.dyntube_video_id"
                        :video-key="video.dyntube_video_id"
                        :iframe-link="video.dyntube_iframe_link"
                        :title="video.title"
                        :start-position="resume.last_position"
                    />
                    <div v-else class="player-placeholder">
                        <Icon icon="lucide:video-off" width="48" height="48" />
                        <p>Video belum tersedia</p>
                    </div>
                </div>

                <!-- Video Meta -->
                <div class="video-meta">
                    <div class="video-meta__top">
                        <span :class="['category-pill', getCategoryClass(video.category)]">
                            {{ video.category }}
                        </span>
                        <div class="meta-stats">
                            <span>
                                <Icon icon="lucide:eye" width="13" height="13" />
                                {{ video.view_count }} ditonton
                            </span>
                            <span>
                                <Icon icon="lucide:clock" width="13" height="13" />
                                {{ video.duration }}
                            </span>
                            <span>
                                <Icon icon="lucide:calendar" width="13" height="13" />
                                {{ video.created_at }}
                            </span>
                        </div>
                    </div>
                    <h1 class="video-title">{{ video.title }}</h1>

                    <!-- Progress resume bar -->
                    <div v-if="resume.progress > 0 && resume.progress < 100" class="resume-bar-wrap">
                        <div class="resume-bar">
                            <div class="resume-bar__fill" :style="{ width: resume.progress + '%' }" />
                        </div>
                        <span class="resume-label">{{ resume.progress }}% selesai</span>
                    </div>
                </div>

                <!-- ── Tabs ── -->
                <div class="tabs">
                    <button
                        :class="['tab-btn', { 'tab-btn--active': activeTab === 'info' }]"
                        @click="activeTab = 'info'"
                    >
                        <Icon icon="lucide:info" width="15" height="15" />
                        Deskripsi & Tujuan
                    </button>
                    <button
                        :class="['tab-btn', { 'tab-btn--active': activeTab === 'quiz' }]"
                        @click="activeTab = 'quiz'"
                    >
                        <Icon icon="lucide:brain-circuit" width="15" height="15" />
                        Kuis
                        <span v-if="video.quiz_status === 'aktif'" class="tab-badge">{{ questions.length }}</span>
                        <span v-else-if="video.quiz_status === 'proses'" class="tab-badge tab-badge--gray">...</span>
                    </button>
                </div>

                <!-- ── Tab: Info ── -->
                <div v-show="activeTab === 'info'" class="tab-content">
                    <!-- Description -->
                    <div v-if="video.description" class="desc-box">
                        <h3 class="box-title">
                            <Icon icon="lucide:align-left" width="16" height="16" />
                            Deskripsi
                        </h3>
                        <p class="desc-text">{{ video.description }}</p>
                    </div>

                    <!-- Learning Objectives -->
                    <div v-if="objectives.length > 0" class="obj-box">
                        <h3 class="box-title">
                            <Icon icon="lucide:target" width="16" height="16" />
                            Tujuan Pembelajaran
                        </h3>
                        <ul class="obj-list">
                            <li v-for="obj in objectives" :key="obj.id" class="obj-item">
                                <div class="obj-check">
                                    <Icon icon="lucide:check" width="11" height="11" />
                                </div>
                                {{ obj.objective_text }}
                            </li>
                        </ul>
                    </div>

                    <div v-if="!video.description && objectives.length === 0" class="empty-info">
                        <Icon icon="lucide:file-text" width="28" height="28" />
                        <p>Belum ada deskripsi untuk video ini.</p>
                    </div>
                </div>

                <!-- ── Tab: Kuis ── -->
                <div v-show="activeTab === 'quiz'" class="tab-content">

                    <!-- Quiz not yet generated -->
                    <div v-if="video.quiz_status === 'belum'" class="quiz-empty">
                        <Icon icon="lucide:clock-4" width="32" height="32" />
                        <p>Kuis untuk video ini belum tersedia.</p>
                    </div>

                    <!-- Quiz processing -->
                    <div v-else-if="video.quiz_status === 'proses'" class="quiz-empty quiz-empty--processing">
                        <div class="spinner" />
                        <p>Kuis sedang dibuat oleh AI, silakan kembali sebentar lagi.</p>
                    </div>

                    <!-- Quiz failed -->
                    <div v-else-if="video.quiz_status === 'gagal'" class="quiz-empty quiz-empty--error">
                        <Icon icon="lucide:alert-triangle" width="32" height="32" />
                        <p>Pembuatan kuis gagal. Silakan hubungi admin.</p>
                    </div>

                    <!-- Quiz Result Screen -->
                    <div v-else-if="showResult && lastResult" class="quiz-result">
                        <div :class="['result-circle', lastResult.score >= 70 ? 'result-circle--pass' : 'result-circle--fail']">
                            <span class="result-score">{{ lastResult.score }}</span>
                            <span class="result-pct">%</span>
                        </div>
                        <h3 :class="['result-title', lastResult.score >= 70 ? 'result-title--pass' : 'result-title--fail']">
                            {{ lastResult.score >= 70 ? '🎉 Selamat! Kamu Lulus!' : '😔 Belum Lulus' }}
                        </h3>
                        <p class="result-detail">
                            Jawaban benar: <strong>{{ lastResult.correct }}</strong> dari <strong>{{ lastResult.total }}</strong> soal
                        </p>

                        <!-- Correct answer review -->
                        <div class="answer-review">
                            <h4 class="review-title">Pembahasan Jawaban</h4>
                            <div v-for="(q, idx) in questions" :key="q.id" class="review-item">
                                <p class="review-q">{{ idx + 1 }}. {{ q.question_text }}</p>
                                <div class="review-options">
                                    <div
                                        v-for="(text, opt) in q.options"
                                        :key="opt"
                                        :class="[
                                            'review-opt',
                                            opt === q.correct_answer ? 'review-opt--correct' : '',
                                            answers[q.id] === opt && opt !== q.correct_answer ? 'review-opt--wrong' : '',
                                        ]"
                                    >
                                        <span class="opt-key">{{ opt }}</span>
                                        {{ text }}
                                        <Icon v-if="opt === q.correct_answer" icon="lucide:check-circle" width="14" height="14" class="opt-icon opt-icon--correct" />
                                        <Icon v-if="answers[q.id] === opt && opt !== q.correct_answer" icon="lucide:x-circle" width="14" height="14" class="opt-icon opt-icon--wrong" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button class="btn-retake" @click="retakeQuiz">
                            <Icon icon="lucide:refresh-cw" width="15" height="15" />
                            Ulangi Kuis
                        </button>
                    </div>

                    <!-- Quiz locked until the video has been watched enough -->
                    <div v-else-if="!hasWatchedEnough" class="quiz-empty quiz-locked">
                        <Icon icon="lucide:lock" width="32" height="32" />
                        <p>Tonton video ini hingga selesai untuk membuka kuis.</p>
                        <div class="quiz-lock-bar">
                            <div class="quiz-lock-fill" :style="{ width: watchProgressPct + '%' }" />
                        </div>
                        <span class="quiz-lock-label">Progress menonton: {{ watchProgressPct }}% (minimal 90%)</span>
                    </div>

                    <!-- Active Quiz -->
                    <div v-else class="quiz-active">
                        <!-- Best score banner -->
                        <div v-if="bestScore !== null" class="best-score-banner">
                            <Icon icon="lucide:trophy" width="15" height="15" />
                            Skor terbaik kamu: <strong>{{ bestScore }}%</strong>
                            <span :class="['score-badge', bestScore >= 70 ? 'score-badge--pass' : 'score-badge--fail']">
                                {{ bestScore >= 70 ? 'Lulus' : 'Belum Lulus' }}
                            </span>
                        </div>

                        <!-- Progress bar of questions -->
                        <div class="quiz-progress">
                            <div class="quiz-progress-bar">
                                <div
                                    class="quiz-progress-fill"
                                    :style="{ width: ((currentQ + 1) / questions.length * 100) + '%' }"
                                />
                            </div>
                            <span class="quiz-progress-text">Soal {{ currentQ + 1 }} / {{ questions.length }}</span>
                        </div>

                        <!-- Question card -->
                        <div class="question-card">
                            <p class="question-text">
                                {{ questions[currentQ].question_text }}
                            </p>

                            <div class="options-grid">
                                <button
                                    v-for="(optText, optKey) in questions[currentQ].options"
                                    :key="optKey"
                                    :class="['option-btn', { 'option-btn--selected': answers[questions[currentQ].id] === optKey }]"
                                    @click="selectOption(questions[currentQ].id, optKey as string)"
                                >
                                    <span class="option-key">{{ optKey }}</span>
                                    <span class="option-text">{{ optText }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- Navigation -->
                        <div class="quiz-nav">
                            <button class="btn-nav" :disabled="currentQ === 0" @click="prevQ">
                                <Icon icon="lucide:chevron-left" width="16" height="16" />
                                Sebelumnya
                            </button>

                            <div class="q-dots">
                                <button
                                    v-for="(q, i) in questions"
                                    :key="q.id"
                                    :class="['q-dot', {
                                        'q-dot--active': i === currentQ,
                                        'q-dot--answered': answers[q.id] !== undefined,
                                    }]"
                                    @click="currentQ = i"
                                />
                            </div>

                            <button
                                v-if="currentQ < questions.length - 1"
                                class="btn-nav btn-nav--next"
                                @click="nextQ"
                            >
                                Selanjutnya
                                <Icon icon="lucide:chevron-right" width="16" height="16" />
                            </button>

                            <button
                                v-else
                                class="btn-submit"
                                :disabled="!allAnswered || submitting"
                                @click="submitQuiz"
                            >
                                <Icon icon="lucide:send" width="16" height="16" />
                                {{ submitting ? 'Mengirim...' : 'Kumpulkan Jawaban' }}
                            </button>
                        </div>

                        <!-- Unanswered warning -->
                        <p v-if="currentQ === questions.length - 1 && !allAnswered" class="unanswered-warn">
                            <Icon icon="lucide:alert-circle" width="13" height="13" />
                            Masih ada soal yang belum dijawab.
                        </p>
                    </div>
                </div>
            </div>

            <!-- ════ RIGHT: RELATED VIDEOS ════ -->
            <aside class="watch-sidebar">
                <h3 class="sidebar-title">
                    <Icon icon="lucide:list-video" width="16" height="16" />
                    Video Terkait
                </h3>

                <div v-if="relatedVideos.length > 0" class="related-list">
                    <Link
                        v-for="v in relatedVideos"
                        :key="v.id"
                        :href="`/video/${v.id}`"
                        class="related-card"
                    >
                        <div class="related-thumb">
                            <img
                                :src="v.thumbnail ?? fallbackThumb"
                                :alt="v.title"
                                class="related-img"
                                loading="lazy"
                            />
                            <span class="related-duration">{{ v.duration }}</span>
                        </div>
                        <div class="related-info">
                            <p class="related-title">{{ v.title }}</p>
                            <p class="related-views">
                                <Icon icon="lucide:eye" width="11" height="11" />
                                {{ v.views }} ditonton
                            </p>
                        </div>
                    </Link>
                </div>

                <div v-else class="no-related">
                    <Icon icon="lucide:video-off" width="24" height="24" />
                    <p>Tidak ada video terkait.</p>
                </div>
            </aside>
        </div>
    </UserLayout>
</template>

<style scoped>
/* ─── Breadcrumb ──────────────────────────────────────────────────────────── */
.breadcrumb {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 20px;
    font-size: 12.5px;
    flex-wrap: wrap;
}
.bc-link { color: #0d9488; text-decoration: none; font-weight: 600; }
.bc-link:hover { text-decoration: underline; }
.bc-sep { color: #cbd5e1; }
.bc-current { color: #64748b; font-weight: 500; max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* ─── Watch Layout ────────────────────────────────────────────────────────── */
.watch-layout {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 28px;
    align-items: start;
}

/* ─── Player ──────────────────────────────────────────────────────────────── */
.player-wrap { margin-bottom: 16px; }
.player-container {
    position: relative;
    aspect-ratio: 16 / 9;
    background: #0f172a;
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 8px 32px rgba(0,0,0,0.16);
}
.player-iframe {
    width: 100%;
    height: 100%;
    border: none;
    display: block;
}
.player-placeholder {
    aspect-ratio: 16 / 9;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12px;
    background: #1e293b;
    border-radius: 18px;
    color: #64748b;
    font-size: 14px;
}

/* ─── Video Meta ──────────────────────────────────────────────────────────── */
.video-meta { margin-bottom: 20px; }
.video-meta__top {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 8px;
    flex-wrap: wrap;
}
.meta-stats {
    display: flex;
    align-items: center;
    gap: 14px;
    font-size: 12px;
    color: #94a3b8;
    font-weight: 500;
    margin-left: auto;
}
.meta-stats span {
    display: flex;
    align-items: center;
    gap: 4px;
}
.video-title {
    font-size: 22px;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 12px;
    letter-spacing: -0.4px;
    line-height: 1.3;
}

/* Resume bar */
.resume-bar-wrap {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 4px;
}
.resume-bar {
    flex: 1;
    height: 6px;
    background: #e2e8f0;
    border-radius: 100px;
    overflow: hidden;
}
.resume-bar__fill {
    height: 100%;
    background: linear-gradient(90deg, #0d9488, #0891b2);
    border-radius: 100px;
    transition: width 0.5s ease;
}
.resume-label { font-size: 12px; color: #0d9488; font-weight: 700; white-space: nowrap; }

/* ─── Tabs ────────────────────────────────────────────────────────────────── */
.tabs {
    display: flex;
    border-bottom: 2px solid #f1f5f9;
    margin-bottom: 24px;
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
.tab-badge--gray { background: #e2e8f0; color: #64748b; }

/* ─── Tab Content ─────────────────────────────────────────────────────────── */
.tab-content { animation: fadeIn 0.2s ease; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: none; } }

/* Info Tab */
.desc-box, .obj-box {
    background: #ffffff;
    border: 1px solid #f1f5f9;
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 16px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.03);
}
.box-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 14px;
}
.desc-text {
    font-size: 13.5px;
    color: #475569;
    line-height: 1.7;
    margin: 0;
    white-space: pre-line;
}
.obj-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 10px; }
.obj-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    font-size: 13.5px;
    color: #334155;
    line-height: 1.5;
}
.obj-check {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #ccfbf1;
    color: #0d9488;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    margin-top: 1px;
}
.empty-info {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    padding: 48px;
    color: #94a3b8;
    font-size: 14px;
}

/* ─── Quiz Empty States ───────────────────────────────────────────────────── */
.quiz-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    padding: 56px 40px;
    background: #f8fafc;
    border-radius: 16px;
    color: #94a3b8;
    font-size: 14px;
    text-align: center;
}
.quiz-empty--processing { color: #0d9488; }
.quiz-empty--error { color: #ef4444; }
.quiz-locked { color: #64748b; }
.quiz-lock-bar {
    width: 100%;
    max-width: 320px;
    height: 8px;
    background: #e2e8f0;
    border-radius: 999px;
    overflow: hidden;
    margin-top: 4px;
}
.quiz-lock-fill {
    height: 100%;
    background: #14b8a6;
    border-radius: 999px;
    transition: width 0.4s ease;
}
.quiz-lock-label { font-size: 12px; color: #94a3b8; }
.spinner {
    width: 32px;
    height: 32px;
    border: 3px solid #ccfbf1;
    border-top-color: #0d9488;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ─── Quiz Active ─────────────────────────────────────────────────────────── */
.quiz-active { display: flex; flex-direction: column; gap: 20px; }

.best-score-banner {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 12px;
    font-size: 13px;
    color: #92400e;
    font-weight: 600;
}
.score-badge {
    padding: 2px 8px;
    border-radius: 100px;
    font-size: 11px;
    font-weight: 700;
}
.score-badge--pass { background: #dcfce7; color: #15803d; }
.score-badge--fail { background: #fee2e2; color: #dc2626; }

.quiz-progress { display: flex; align-items: center; gap: 12px; }
.quiz-progress-bar {
    flex: 1;
    height: 6px;
    background: #e2e8f0;
    border-radius: 100px;
    overflow: hidden;
}
.quiz-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #0d9488, #0891b2);
    border-radius: 100px;
    transition: width 0.3s ease;
}
.quiz-progress-text { font-size: 12px; color: #64748b; font-weight: 700; white-space: nowrap; }

.question-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 18px;
    padding: 24px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.04);
}
.question-text {
    font-size: 15px;
    font-weight: 700;
    color: #0f172a;
    margin: 0 0 20px;
    line-height: 1.5;
}
.options-grid { display: flex; flex-direction: column; gap: 10px; }
.option-btn {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px 16px;
    border-radius: 12px;
    border: 2px solid #e2e8f0;
    background: #f8fafc;
    cursor: pointer;
    font-family: inherit;
    text-align: left;
    transition: all 0.15s;
    width: 100%;
}
.option-btn:hover { border-color: #0d9488; background: #f0fdfa; }
.option-btn--selected { border-color: #0d9488; background: #f0fdfa; box-shadow: 0 0 0 2px rgba(13,148,136,0.15); }
.option-key {
    width: 26px;
    height: 26px;
    border-radius: 8px;
    background: #e2e8f0;
    color: #475569;
    font-size: 12px;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: all 0.15s;
}
.option-btn--selected .option-key { background: #0d9488; color: #fff; }
.option-text { font-size: 13.5px; color: #334155; font-weight: 500; line-height: 1.5; padding-top: 2px; }

.quiz-nav {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
.btn-nav {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 9px 16px;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    background: #fff;
    font-size: 13px;
    font-weight: 700;
    color: #475569;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.15s;
}
.btn-nav:hover:not(:disabled) { border-color: #0d9488; color: #0d9488; }
.btn-nav:disabled { opacity: 0.4; cursor: not-allowed; }
.btn-nav--next { margin-left: auto; }

.q-dots { display: flex; gap: 6px; }
.q-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    border: none;
    background: #e2e8f0;
    cursor: pointer;
    transition: all 0.15s;
}
.q-dot--active { background: #0d9488; transform: scale(1.3); }
.q-dot--answered { background: #99f6e4; }
.q-dot--active.q-dot--answered { background: #0d9488; }

.btn-submit {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 22px;
    border-radius: 10px;
    border: none;
    background: linear-gradient(135deg, #0d9488, #0f766e);
    color: #fff;
    font-size: 13.5px;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.2s;
    box-shadow: 0 4px 12px rgba(13,148,136,0.25);
    margin-left: auto;
}
.btn-submit:hover:not(:disabled) { opacity: 0.93; box-shadow: 0 6px 18px rgba(13,148,136,0.35); }
.btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }

.unanswered-warn {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: #f59e0b;
    font-weight: 600;
    margin: 0;
}

/* ─── Quiz Result ─────────────────────────────────────────────────────────── */
.quiz-result {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 16px;
}
.result-circle {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border: 6px solid;
}
.result-circle--pass { border-color: #0d9488; background: #f0fdfa; }
.result-circle--fail { border-color: #ef4444; background: #fff1f2; }
.result-score {
    font-size: 30px;
    font-weight: 900;
    line-height: 1;
    color: inherit;
}
.result-circle--pass .result-score { color: #0d9488; }
.result-circle--fail .result-score { color: #ef4444; }
.result-pct { font-size: 13px; font-weight: 700; color: inherit; opacity: 0.7; }
.result-title { font-size: 18px; font-weight: 800; margin: 0; }
.result-title--pass { color: #0d9488; }
.result-title--fail { color: #ef4444; }
.result-detail { font-size: 14px; color: #64748b; margin: 0; }

.answer-review { width: 100%; margin-top: 4px; }
.review-title { font-size: 14px; font-weight: 800; color: #0f172a; margin: 0 0 14px; }
.review-item { margin-bottom: 20px; }
.review-q { font-size: 13.5px; font-weight: 700; color: #0f172a; margin: 0 0 10px; line-height: 1.5; }
.review-options { display: flex; flex-direction: column; gap: 6px; }
.review-opt {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 14px;
    border-radius: 10px;
    font-size: 13px;
    color: #475569;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
}
.review-opt--correct { background: #f0fdfa; border-color: #0d9488; color: #0f766e; font-weight: 600; }
.review-opt--wrong { background: #fff1f2; border-color: #ef4444; color: #dc2626; }
.opt-key {
    width: 22px;
    height: 22px;
    border-radius: 6px;
    background: #e2e8f0;
    font-size: 11px;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.review-opt--correct .opt-key { background: #0d9488; color: #fff; }
.review-opt--wrong .opt-key { background: #ef4444; color: #fff; }
.opt-icon { margin-left: auto; }
.opt-icon--correct { color: #0d9488; }
.opt-icon--wrong { color: #ef4444; }

.btn-retake {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 24px;
    border-radius: 10px;
    border: 2px solid #0d9488;
    background: transparent;
    color: #0d9488;
    font-size: 13.5px;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.15s;
    margin-top: 4px;
}
.btn-retake:hover { background: #f0fdfa; }

/* ─── Category Pills ──────────────────────────────────────────────────────── */
.category-pill {
    display: inline-block;
    font-size: 10.5px;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 100px;
    border: 1px solid;
}
.pill--emerald { background: #ecfdf5; color: #059669; border-color: #a7f3d0; }
.pill--cyan    { background: #ecfeff; color: #0891b2; border-color: #a5f3fc; }
.pill--rose    { background: #fff1f2; color: #e11d48; border-color: #fecdd3; }
.pill--amber   { background: #fffbeb; color: #d97706; border-color: #fde68a; }
.pill--teal    { background: #f0fdfa; color: #0d9488; border-color: #99f6e4; }
.pill--slate   { background: #f8fafc; color: #64748b; border-color: #e2e8f0; }

/* ─── Right Sidebar ───────────────────────────────────────────────────────── */
.watch-sidebar {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 18px;
    padding: 18px;
    position: sticky;
    top: 74px;
    max-height: calc(100vh - 100px);
    overflow-y: auto;
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
}
.sidebar-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 16px;
}
.related-list { display: flex; flex-direction: column; gap: 12px; }
.related-card {
    display: flex;
    gap: 12px;
    text-decoration: none;
    border-radius: 12px;
    padding: 8px;
    transition: background 0.15s;
}
.related-card:hover { background: #f8fafc; }
.related-thumb {
    position: relative;
    width: 100px;
    height: 62px;
    border-radius: 8px;
    overflow: hidden;
    flex-shrink: 0;
    background: #e2e8f0;
}
.related-img { width: 100%; height: 100%; object-fit: cover; }
.related-duration {
    position: absolute;
    bottom: 4px;
    right: 4px;
    background: rgba(15,23,42,0.75);
    color: #fff;
    font-size: 9.5px;
    font-weight: 700;
    padding: 2px 5px;
    border-radius: 4px;
}
.related-info { flex: 1; min-width: 0; }
.related-title {
    font-size: 12.5px;
    font-weight: 700;
    color: #0f172a;
    margin: 0 0 5px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    line-height: 1.4;
}
.related-views {
    font-size: 11px;
    color: #94a3b8;
    display: flex;
    align-items: center;
    gap: 3px;
    margin: 0;
    font-weight: 500;
}
.no-related {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 24px;
    color: #cbd5e1;
    font-size: 13px;
    text-align: center;
}

/* ─── Responsive ──────────────────────────────────────────────────────────── */
@media (max-width: 1024px) {
    .watch-layout {
        grid-template-columns: 1fr;
    }
    .watch-sidebar {
        position: static;
        max-height: none;
    }
    .related-list {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 640px) {
    .video-title { font-size: 18px; }
    .meta-stats { display: none; }
    .related-list { grid-template-columns: 1fr; }
}
</style>
