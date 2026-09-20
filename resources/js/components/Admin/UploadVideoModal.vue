<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { useForm } from '@inertiajs/vue3';
import { onMounted, ref, watch } from 'vue';

const MAX_FILE_SIZE_MB = 200;
const MAX_FILE_SIZE_BYTES = MAX_FILE_SIZE_MB * 1024 * 1024;

const props = defineProps<{
    show: boolean;
    categories: Array<{ id: number; name: string }>;
    editVideo?: {
        id: number;
        title: string;
        dyntube_video_id: string;
        category_id: number;
        description: string | null;
        transcript: string | null;
        is_published: boolean;
    } | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const isMounted = ref(false);
onMounted(() => {
    isMounted.value = true;
});

const fileInputRef = ref<HTMLInputElement | null>(null);
const selectedFileName = ref('');
const selectedFileSizeMB = ref('');
const isDragging = ref(false);
const uploadError = ref('');

const form = useForm({
    title: '',
    video_file: null as File | null,
    dyntube_video_id: '',
    category_id: '' as number | string,
    description: '',
    generate_quiz: true,
    is_published: false,
});

watch(
    () => props.editVideo,
    (val) => {
        removeFile();
        if (val) {
            form.title = val.title;
            form.dyntube_video_id = val.dyntube_video_id;
            form.category_id = val.category_id;
            form.description = val.description || '';
            form.is_published = val.is_published;
            form.generate_quiz = false;
        } else {
            form.reset();
            form.generate_quiz = true;
            form.is_published = false;
        }
    },
    { immediate: true }
);

function handleFileSelect(e: Event) {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        setVideoFile(target.files[0]);
    }
}

function handleFileDrop(e: DragEvent) {
    isDragging.value = false;
    if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]) {
        setVideoFile(e.dataTransfer.files[0]);
    }
}

function setVideoFile(file: File) {
    uploadError.value = '';
    if (file.size > MAX_FILE_SIZE_BYTES) {
        uploadError.value = `File terlalu besar (${(file.size / 1024 / 1024).toFixed(1)} MB). Maksimal ${MAX_FILE_SIZE_MB} MB.`;
        return;
    }
    form.video_file = file;
    selectedFileName.value = file.name;
    selectedFileSizeMB.value = (file.size / (1024 * 1024)).toFixed(1);
    if (!form.title) {
        // Auto-fill title from filename without extension
        form.title = file.name.replace(/\.[^/.]+$/, '').replace(/[-_]/g, ' ');
    }
}

function removeFile() {
    form.video_file = null;
    selectedFileName.value = '';
    selectedFileSizeMB.value = '';
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
}

function submit() {
    uploadError.value = '';
    if (props.editVideo) {
        form.patch(`/admin/videos/${props.editVideo.id}`, {
            onSuccess: () => {
                form.reset();
                removeFile();
                emit('close');
            },
            onError: (errors) => {
                uploadError.value = errors.video_file ?? errors.message ?? 'Terjadi kesalahan saat menyimpan perubahan.';
            },
        });
    } else {
        if (!form.video_file) {
            uploadError.value = 'Silakan pilih file video MP4 terlebih dahulu.';
            return;
        }
        form.post('/admin/videos', {
            forceFormData: true,
            onSuccess: () => {
                form.reset();
                removeFile();
                uploadError.value = '';
                emit('close');
            },
            onError: (errors) => {
                const msg = errors.video_file ?? errors.title ?? errors.category_id ?? '';
                uploadError.value = msg || 'Gagal mengunggah video. Pastikan file MP4 valid dan ukuran di bawah 200 MB.';
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
                            {{ editVideo ? 'Edit Video Edukasi' : 'Unggah Video Baru' }}
                        </h2>

                        <!-- Form -->
                        <form class="modal-form" @submit.prevent="submit">

                            <!-- Upload Error Banner -->
                            <div v-if="uploadError" class="upload-error-banner">
                                <Icon icon="lucide:alert-circle" width="16" height="16" class="flex-shrink-0" />
                                <span>{{ uploadError }}</span>
                            </div>

                            <!-- Upload File Video MP4 (Drag & Drop Zone) -->
                            <div v-if="!editVideo" class="form-group">
                                <label class="form-label">
                                    File Video MP4 <span class="required">*</span>
                                </label>
                                <p class="form-help">Video materi edukasi (maksimal durasi 10 menit)</p>

                                <div
                                    class="upload-dropzone"
                                    :class="{ 'is-dragging': isDragging, 'has-file': !!form.video_file }"
                                    @dragover.prevent="isDragging = true"
                                    @dragleave.prevent="isDragging = false"
                                    @drop.prevent="handleFileDrop"
                                    @click="fileInputRef?.click()"
                                >
                                    <input
                                        ref="fileInputRef"
                                        type="file"
                                        accept="video/mp4,video/quicktime,video/x-msvideo,video/webm"
                                        class="hidden-file-input"
                                        @change="handleFileSelect"
                                    />

                                    <template v-if="form.video_file">
                                        <div class="file-info-preview">
                                            <div class="file-icon-box">
                                                <Icon icon="lucide:file-video" width="28" height="28" />
                                            </div>
                                            <div class="file-details">
                                                <p class="file-name">{{ selectedFileName }}</p>
                                                <p class="file-size">{{ selectedFileSizeMB }} MB • Siap diunggah ke Dyntube</p>
                                            </div>
                                            <button type="button" class="btn-remove-file" title="Ganti File" @click.stop="removeFile">
                                                <Icon icon="lucide:trash-2" width="16" height="16" />
                                            </button>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <div class="dropzone-content">
                                            <div class="dropzone-icon">
                                                <Icon icon="lucide:upload-cloud" width="32" height="32" />
                                            </div>
                                            <p class="dropzone-title">Klik atau seret file video di sini</p>
                                            <p class="dropzone-sub">Format MP4, MOV, WEBM (Maks 100MB)</p>
                                        </div>
                                    </template>
                                </div>
                                <span v-if="form.errors.video_file" class="error-text">{{ form.errors.video_file }}</span>
                            </div>

                            <!-- Judul Video -->
                            <div class="form-group">
                                <label class="form-label">
                                    Judul Video <span class="required">*</span>
                                </label>
                                <p class="form-help">Judul yang jelas dan deskriptif untuk materi ini</p>
                                <input
                                    v-model="form.title"
                                    type="text"
                                    required
                                    placeholder="cth. Pentingnya Serat untuk Lambung Sehat"
                                    class="form-input"
                                    :class="{ 'input-error': form.errors.title }"
                                />
                                <span v-if="form.errors.title" class="error-text">{{ form.errors.title }}</span>
                            </div>

                            <!-- Kategori -->
                            <div class="form-group">
                                <label class="form-label">
                                    Kategori <span class="required">*</span>
                                </label>
                                <select
                                    v-model="form.category_id"
                                    required
                                    class="form-select"
                                    :class="{ 'input-error': form.errors.category_id }"
                                >
                                    <option value="" disabled>Pilih kategori...</option>
                                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                                        {{ cat.name }}
                                    </option>
                                </select>
                                <span v-if="form.errors.category_id" class="error-text">{{ form.errors.category_id }}</span>
                            </div>

                            <!-- Deskripsi Materi -->
                            <div class="form-group">
                                <label class="form-label">Deskripsi Materi</label>
                                <textarea
                                    v-model="form.description"
                                    rows="2"
                                    placeholder="Jelaskan isi materi video ini secara singkat..."
                                    class="form-textarea"
                                    :class="{ 'input-error': form.errors.description }"
                                ></textarea>
                                <span v-if="form.errors.description" class="error-text">{{ form.errors.description }}</span>
                            </div>

                             <!-- Generate Quiz AI Card -->
                             <div v-if="!editVideo" class="ai-box">
                                <label class="ai-checkbox-label">
                                    <input
                                        v-model="form.generate_quiz"
                                        type="checkbox"
                                        class="ai-checkbox"
                                    />
                                    <div>
                                        <p class="ai-box-title">
                                            <Icon icon="lucide:sparkles" width="15" height="15" class="ai-sparkle-icon" />
                                            Generate Kuis AI Otomatis
                                        </p>
                                        <p class="ai-box-desc">
                                            6 soal kuis evaluasi pilihan ganda akan dibuat secara otomatis menggunakan AI Gemini 2.0 Flash berdasarkan naskah transcript video ini.
                                        </p>
                                    </div>
                                </label>
                            </div>

                            <!-- Footer Action Buttons -->
                            <div class="modal-actions">
                                <button type="button" class="btn-cancel" @click="closeModal">
                                    Batal
                                </button>
                                <button type="submit" class="btn-save" :disabled="form.processing || (!editVideo && !form.video_file)">
                                    <Icon
                                        :icon="form.processing ? 'lucide:loader-circle' : (editVideo ? 'lucide:check' : 'lucide:upload-cloud')"
                                        width="16"
                                        height="16"
                                        :class="{ 'spin-icon': form.processing }"
                                    />
                                    {{ form.processing ? 'Mengunggah & Transcoding...' : (editVideo ? 'Simpan Perubahan' : 'Unggah Video') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </Transition>
            </div>
        </Transition>

        <!-- ══ UPLOAD / EDIT COMPLETE ══ -->
        <!-- Note: AI processing (transcript + quiz) now runs asynchronously via queue jobs -->
    </Teleport>
</template>

<style scoped>
.modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 999;
    background-color: rgba(15, 23, 42, 0.55);
    backdrop-filter: blur(6px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.modal-card {
    position: relative;
    background-color: #ffffff;
    border-radius: 24px;
    width: 100%;
    max-width: 480px;
    padding: 32px;
    box-shadow: 0 20px 50px rgba(15, 23, 42, 0.2);
    box-sizing: border-box;
    font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
}
.modal-close-btn {
    position: absolute;
    top: 20px;
    right: 20px;
    background: none;
    border: none;
    color: #94a3b8;
    cursor: pointer;
    padding: 6px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}
.modal-close-btn:hover {
    background-color: #f1f5f9;
    color: #0f172a;
}
.modal-title {
    font-size: 20px;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 20px;
    letter-spacing: -0.4px;
}
.modal-form {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.form-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.form-label {
    font-size: 13px;
    font-weight: 700;
    color: #0f172a;
}
.required {
    color: #ef4444;
}
.optional-tag {
    font-size: 10.5px; font-weight: 600; background: #f0fdfa; color: #0d9488;
    border: 1px solid #99f6e4; padding: 1px 6px; border-radius: 100px; margin-left: 4px;
}
.field-hint {
    font-size: 11.5px; color: #64748b; margin: 2px 0 0; line-height: 1.4;
}
.form-help {
    font-size: 11.5px;
    color: #94a3b8;
    margin: -2px 0 4px;
}
.form-input,
.form-select,
.form-textarea {
    width: 100%;
    background-color: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 10px 14px;
    font-size: 13.5px;
    color: #0f172a;
    font-family: inherit;
    outline: none;
    transition: all 0.2s;
    box-sizing: border-box;
}
.code-font {
    font-family: 'Courier New', monospace;
}
.form-input:focus,
.form-select:focus,
.form-textarea:focus {
    background-color: #ffffff;
    border-color: #0d9488;
    box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.12);
}
.input-error {
    border-color: #ef4444 !important;
}
.error-text {
    font-size: 11.5px;
    color: #ef4444;
 }

/* Upload Error Banner */
.upload-error-banner {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    background: #fef2f2;
    border: 1px solid #fca5a5;
    border-radius: 12px;
    padding: 12px 14px;
    color: #dc2626;
    font-size: 13px;
    font-weight: 500;
    line-height: 1.45;
}
.flex-shrink-0 { flex-shrink: 0; margin-top: 1px; }

/* Dropzone */
.hidden-file-input { display: none; }
.upload-dropzone {
    border: 2px dashed #cbd5e1;
    background-color: #f8fafc;
    border-radius: 16px;
    padding: 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
}
.upload-dropzone:hover,
.upload-dropzone.is-dragging {
    border-color: #0d9488;
    background-color: #f0fdfa;
}
.upload-dropzone.has-file {
    border-style: solid;
    border-color: #99f6e4;
    background-color: #f0fdfa;
    padding: 14px 18px;
}
.dropzone-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
}
.dropzone-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background-color: #f0fdfa;
    color: #0d9488;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 4px;
}
.dropzone-title {
    font-size: 13.5px;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
}
.dropzone-sub {
    font-size: 11.5px;
    color: #64748b;
    margin: 0;
}
.file-info-preview {
    display: flex;
    align-items: center;
    gap: 12px;
    text-align: left;
}
.file-icon-box {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    background-color: #ccfbf1;
    color: #0d9488;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.file-details {
    flex: 1;
    min-width: 0;
}
.file-name {
    font-size: 13px;
    font-weight: 700;
    color: #0f172a;
    margin: 0 0 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.file-size {
    font-size: 11.5px;
    color: #0d9488;
    margin: 0;
}
.btn-remove-file {
    background: none;
    border: none;
    color: #ef4444;
    cursor: pointer;
    padding: 6px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s;
}
.btn-remove-file:hover {
    background-color: #fee2e2;
}

/* AI Box Card */
.ai-box {
    background-color: #f0fdfa;
    border: 1px solid #ccfbf1;
    border-radius: 16px;
    padding: 14px 16px;
}
.ai-checkbox-label {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    cursor: pointer;
}
.ai-checkbox {
    margin-top: 3px;
    accent-color: #0d9488;
    width: 16px;
    height: 16px;
    cursor: pointer;
}
.ai-box-title {
    font-size: 13.5px;
    font-weight: 700;
    color: #0f766e;
    margin: 0 0 3px;
    display: flex;
    align-items: center;
    gap: 5px;
}
.ai-sparkle-icon {
    color: #0d9488;
}
.ai-box-desc {
    font-size: 11.5px;
    color: #0d9488;
    margin: 0;
    line-height: 1.45;
}

/* Actions */
.modal-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 8px;
}
.btn-cancel {
    padding: 9px 20px;
    border: 1px solid #e2e8f0;
    background-color: #ffffff;
    color: #64748b;
    border-radius: 100px;
    font-size: 13.5px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    font-family: inherit;
}
.btn-cancel:hover {
    background-color: #f1f5f9;
    color: #0f172a;
}
.btn-save {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 9.5px 22px;
    background-color: #0d9488;
    color: #ffffff;
    border: none;
    border-radius: 100px;
    font-size: 13.5px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: 0 4px 14px rgba(13, 148, 136, 0.25);
    font-family: inherit;
}
.btn-save:hover:not(:disabled) {
    background-color: #0f766e;
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(13, 148, 136, 0.35);
}
.btn-save:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

.spin-icon {
    animation: spin 0.8s linear infinite;
}
@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Transitions */
.fade-backdrop-enter-active { transition: opacity 0.25s ease; }
.fade-backdrop-leave-active { transition: opacity 0.2s ease; }
.fade-backdrop-enter-from, .fade-backdrop-leave-to { opacity: 0; }

.scale-card-enter-active { transition: opacity 0.28s ease, transform 0.28s cubic-bezier(0.34, 1.3, 0.64, 1); }
.scale-card-leave-active { transition: opacity 0.18s ease, transform 0.18s ease; }
.scale-card-enter-from { opacity: 0; transform: scale(0.88) translateY(12px); }
.scale-card-leave-to { opacity: 0; transform: scale(0.94) translateY(6px); }
</style>
