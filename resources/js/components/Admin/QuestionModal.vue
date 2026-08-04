<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    show: boolean;
    videoId: number;
    videoDurationSeconds?: number;
    editQuestion?: {
        id: number;
        question_text: string;
        options: { A: string; B: string; C: string; D: string };
        correct_answer: string;
        video_timestamp_seconds: number | null;
    } | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const isMounted = ref(false);
onMounted(() => {
    isMounted.value = true;
});

const form = useForm({
    question_text: '',
    option_a: '',
    option_b: '',
    option_c: '',
    option_d: '',
    correct_answer: 'A',
    video_timestamp_seconds: null as number | null,
});

// ── Timestamp input helpers ────────────────────────────────────────────────────
// We expose a user-friendly "MM:SS" input string, convert to/from seconds internally.
const timestampInput = ref('');

function secondsToMmss(secs: number | null): string {
    if (secs === null || secs === undefined) return '';
    const m = Math.floor(secs / 60);
    const s = secs % 60;
    return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}

function mmssToSeconds(val: string): number | null {
    if (!val.trim()) return null;
    const parts = val.split(':');
    if (parts.length === 2) {
        const m = parseInt(parts[0], 10);
        const s = parseInt(parts[1], 10);
        if (!isNaN(m) && !isNaN(s)) return m * 60 + s;
    }
    if (parts.length === 1) {
        const total = parseInt(parts[0], 10);
        if (!isNaN(total)) return total;
    }
    return null;
}

// ── Formatted duration hint ────────────────────────────────────────────────────
const durationHint = computed(() => {
    if (!props.videoDurationSeconds) return '';
    return `Durasi video: ${secondsToMmss(props.videoDurationSeconds)}`;
});

watch(
    () => props.editQuestion,
    (val) => {
        if (val) {
            form.question_text = val.question_text;
            form.option_a = val.options?.A || '';
            form.option_b = val.options?.B || '';
            form.option_c = val.options?.C || '';
            form.option_d = val.options?.D || '';
            form.correct_answer = val.correct_answer || 'A';
            form.video_timestamp_seconds = val.video_timestamp_seconds ?? null;
            timestampInput.value = secondsToMmss(val.video_timestamp_seconds);
        } else {
            form.reset();
            form.correct_answer = 'A';
            form.video_timestamp_seconds = null;
            timestampInput.value = '';
        }
    },
    { immediate: true }
);

function onTimestampInput(e: Event) {
    const val = (e.target as HTMLInputElement).value;
    timestampInput.value = val;
    form.video_timestamp_seconds = mmssToSeconds(val);
}

function submit() {
    // Sync the seconds from the text input before submitting
    form.video_timestamp_seconds = mmssToSeconds(timestampInput.value);

    if (props.editQuestion) {
        form.patch(`/admin/questions/${props.editQuestion.id}`, {
            onSuccess: () => {
                form.reset();
                timestampInput.value = '';
                emit('close');
            },
        });
    } else {
        form.post(`/admin/videos/${props.videoId}/quiz/questions`, {
            onSuccess: () => {
                form.reset();
                timestampInput.value = '';
                emit('close');
            },
        });
    }
}

function closeModal() {
    emit('close');
}
</script>

<template>
    <Teleport v-if="isMounted" to="body">
        <Transition name="fade-backdrop">
            <div v-if="show" class="modal-backdrop" @click.self="closeModal">
                <Transition name="scale-card" appear>
                    <div v-if="show" class="modal-card" role="dialog" aria-modal="true">
                        <!-- Close button -->
                        <button class="modal-close-btn" aria-label="Tutup" @click="closeModal">
                            <Icon icon="lucide:x" width="18" height="18" />
                        </button>

                        <!-- Header -->
                        <h2 class="modal-title">
                            {{ editQuestion ? 'Edit Soal Kuis' : 'Tambah Soal Manual' }}
                        </h2>

                        <!-- Form -->
                        <form class="modal-form" @submit.prevent="submit">
                            <!-- Question Text -->
                            <div class="form-group">
                                <label class="form-label">Teks Pertanyaan <span class="required">*</span></label>
                                <textarea
                                    v-model="form.question_text"
                                    rows="3"
                                    required
                                    placeholder="Tuliskan pertanyaan evaluasi di sini..."
                                    class="form-textarea"
                                    :class="{ 'input-error': form.errors.question_text }"
                                ></textarea>
                                <span v-if="form.errors.question_text" class="error-text">{{ form.errors.question_text }}</span>
                            </div>



                            <!-- Options A..D -->
                            <div class="options-grid">
                                <div class="form-group">
                                    <label class="form-label">Pilihan A <span class="required">*</span></label>
                                    <input v-model="form.option_a" type="text" required placeholder="Jawaban A" class="form-input" />
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Pilihan B <span class="required">*</span></label>
                                    <input v-model="form.option_b" type="text" required placeholder="Jawaban B" class="form-input" />
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Pilihan C <span class="required">*</span></label>
                                    <input v-model="form.option_c" type="text" required placeholder="Jawaban C" class="form-input" />
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Pilihan D <span class="required">*</span></label>
                                    <input v-model="form.option_d" type="text" required placeholder="Jawaban D" class="form-input" />
                                </div>
                            </div>

                            <!-- Correct Answer -->
                            <div class="form-group">
                                <label class="form-label">Kunci Jawaban Benar <span class="required">*</span></label>
                                <select v-model="form.correct_answer" required class="form-select">
                                    <option value="A">Opsi A</option>
                                    <option value="B">Opsi B</option>
                                    <option value="C">Opsi C</option>
                                    <option value="D">Opsi D</option>
                                </select>
                            </div>

                            <!-- Action Buttons -->
                            <div class="modal-actions">
                                <button type="button" class="btn-cancel" @click="closeModal">Batal</button>
                                <button type="submit" class="btn-save" :disabled="form.processing">
                                    <Icon
                                        :icon="form.processing ? 'lucide:loader-circle' : 'lucide:check'"
                                        width="16"
                                        height="16"
                                        :class="{ 'spin-icon': form.processing }"
                                    />
                                    {{ form.processing ? 'Memproses...' : (editQuestion ? 'Simpan Perubahan' : 'Tambah Soal') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.modal-backdrop {
    position: fixed; inset: 0; z-index: 999;
    background-color: rgba(15, 23, 42, 0.55); backdrop-filter: blur(6px);
    display: flex; align-items: center; justify-content: center; padding: 20px;
}
.modal-card {
    position: relative; background-color: #ffffff; border-radius: 24px;
    width: 100%; max-width: 540px; padding: 32px;
    box-shadow: 0 20px 50px rgba(15, 23, 42, 0.2); box-sizing: border-box;
    font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
    max-height: 90vh; overflow-y: auto;
}
.modal-close-btn {
    position: absolute; top: 20px; right: 20px; background: none; border: none;
    color: #94a3b8; cursor: pointer; padding: 6px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
}
.modal-close-btn:hover { background-color: #f1f5f9; color: #0f172a; }
.modal-title { font-size: 20px; font-weight: 800; color: #0f172a; margin: 0 0 20px; letter-spacing: -0.4px; }
.modal-form { display: flex; flex-direction: column; gap: 16px; }

.form-group { display: flex; flex-direction: column; gap: 4px; }
.form-label { font-size: 13px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 5px; }
.label-icon { color: #0d9488; }
.required { color: #ef4444; }
.optional-tag { font-size: 10.5px; font-weight: 600; background: #f0fdfa; color: #0d9488; border: 1px solid #99f6e4; padding: 1px 6px; border-radius: 100px; margin-left: 2px; }

.form-input, .form-select, .form-textarea {
    width: 100%; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px;
    padding: 10px 14px; font-size: 13.5px; color: #0f172a; font-family: inherit; outline: none; box-sizing: border-box;
}
.form-input:focus, .form-select:focus, .form-textarea:focus {
    background-color: #ffffff; border-color: #0d9488; box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.12);
}

/* Timestamp field */
.timestamp-field {
    position: relative; display: flex; align-items: center;
}
.timestamp-prefix {
    position: absolute; left: 12px; color: #0d9488; pointer-events: none; display: flex; align-items: center;
}
.timestamp-input {
    padding-left: 36px !important;
    font-variant-numeric: tabular-nums;
    letter-spacing: 0.5px;
    font-weight: 600;
}
.ts-hint { font-size: 11.5px; color: #94a3b8; margin: 0; line-height: 1.5; }
.ts-duration { display: inline-block; margin-left: 4px; font-weight: 600; color: #0d9488; }

.options-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.error-text { font-size: 11.5px; color: #ef4444; }

.modal-actions { display: flex; align-items: center; justify-content: flex-end; gap: 10px; margin-top: 8px; }
.btn-cancel {
    padding: 9px 20px; border: 1px solid #e2e8f0; background-color: #ffffff;
    color: #64748b; border-radius: 100px; font-size: 13.5px; font-weight: 600; cursor: pointer; font-family: inherit;
}
.btn-cancel:hover { background-color: #f1f5f9; color: #0f172a; }
.btn-save {
    display: flex; align-items: center; gap: 6px; padding: 9.5px 22px;
    background-color: #0d9488; color: #ffffff; border: none; border-radius: 100px;
    font-size: 13.5px; font-weight: 700; cursor: pointer; font-family: inherit;
    box-shadow: 0 4px 14px rgba(13, 148, 136, 0.25);
}
.btn-save:hover:not(:disabled) { background-color: #0f766e; transform: translateY(-1px); }

.spin-icon { animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.fade-backdrop-enter-active { transition: opacity 0.25s ease; }
.fade-backdrop-leave-active { transition: opacity 0.2s ease; }
.fade-backdrop-enter-from, .fade-backdrop-leave-to { opacity: 0; }

.scale-card-enter-active { transition: opacity 0.28s ease, transform 0.28s cubic-bezier(0.34, 1.3, 0.64, 1); }
.scale-card-leave-active { transition: opacity 0.18s ease, transform 0.18s ease; }
.scale-card-enter-from { opacity: 0; transform: scale(0.88) translateY(12px); }
.scale-card-leave-to { opacity: 0; transform: scale(0.94) translateY(6px); }
</style>
