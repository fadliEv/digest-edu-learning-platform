<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { onMounted, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        show: boolean;
        title?: string;
        message?: string;
        confirmText?: string;
        cancelText?: string;
        type?: 'danger' | 'warning' | 'info';
        loading?: boolean;
    }>(),
    {
        title: 'Konfirmasi Aksi',
        message: 'Apakah Anda yakin ingin melanjutkan aksi ini?',
        confirmText: 'Ya, Lanjutkan',
        cancelText: 'Batal',
        type: 'danger',
        loading: false,
    }
);

const emit = defineEmits<{
    (e: 'confirm'): void;
    (e: 'cancel'): void;
}>();

const isMounted = ref(false);
onMounted(() => {
    isMounted.value = true;
});

function onConfirm() {
    emit('confirm');
}

function onCancel() {
    emit('cancel');
}
</script>

<template>
    <Teleport v-if="isMounted" to="body">
        <Transition name="fade-backdrop">
            <div v-if="show" class="confirm-backdrop" @click.self="onCancel">
                <Transition name="scale-card" appear>
                    <div v-if="show" class="confirm-card" role="dialog" aria-modal="true">
                        <!-- Icon Circle -->
                        <div :class="['icon-box', `icon-box--${type}`]">
                            <Icon
                                :icon="
                                    type === 'danger'
                                        ? 'lucide:trash-2'
                                        : type === 'warning'
                                        ? 'lucide:alert-triangle'
                                        : 'lucide:info'
                                "
                                width="28"
                                height="28"
                            />
                        </div>

                        <!-- Content -->
                        <h3 class="confirm-title">{{ title }}</h3>
                        <p class="confirm-message">{{ message }}</p>

                        <!-- Actions -->
                        <div class="confirm-actions">
                            <button type="button" class="btn-cancel" :disabled="loading" @click="onCancel">
                                {{ cancelText }}
                            </button>
                            <button
                                type="button"
                                :class="['btn-confirm', `btn-confirm--${type}`]"
                                :disabled="loading"
                                @click="onConfirm"
                            >
                                <Icon
                                    v-if="loading"
                                    icon="lucide:loader-circle"
                                    width="16"
                                    height="16"
                                    class="spin-icon"
                                />
                                <span>{{ loading ? 'Memproses...' : confirmText }}</span>
                            </button>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.confirm-backdrop {
    position: fixed;
    inset: 0;
    z-index: 9999;
    background-color: rgba(15, 23, 42, 0.65);
    backdrop-filter: blur(6px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.confirm-card {
    background-color: #ffffff;
    border-radius: 24px;
    width: 100%;
    max-width: 420px;
    padding: 32px 28px 24px;
    text-align: center;
    box-shadow: 0 25px 60px rgba(15, 23, 42, 0.25);
    box-sizing: border-box;
    font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
}

.icon-box {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    margin: 0 auto 18px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.icon-box--danger {
    background-color: #fff1f2;
    color: #ef4444;
    border: 1px solid #fecdd3;
}
.icon-box--warning {
    background-color: #fffbeb;
    color: #d97706;
    border: 1px solid #fde68a;
}
.icon-box--info {
    background-color: #f0fdfa;
    color: #0d9488;
    border: 1px solid #99f6e4;
}

.confirm-title {
    font-size: 18px;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 8px;
    letter-spacing: -0.3px;
}

.confirm-message {
    font-size: 13.5px;
    color: #64748b;
    margin: 0 0 24px;
    line-height: 1.5;
    white-space: pre-line;
}

.confirm-actions {
    display: flex;
    align-items: center;
    gap: 12px;
}

.btn-cancel {
    flex: 1;
    padding: 10px 16px;
    border: 1px solid #e2e8f0;
    background-color: #ffffff;
    color: #64748b;
    border-radius: 100px;
    font-size: 13.5px;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.15s;
}
.btn-cancel:hover:not(:disabled) {
    background-color: #f1f5f9;
    color: #0f172a;
}

.btn-confirm {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 10px 16px;
    border: none;
    border-radius: 100px;
    font-size: 13.5px;
    font-weight: 700;
    cursor: pointer;
    color: #ffffff;
    font-family: inherit;
    transition: all 0.15s;
}
.btn-confirm--danger {
    background-color: #ef4444;
    box-shadow: 0 4px 14px rgba(239, 68, 68, 0.25);
}
.btn-confirm--danger:hover:not(:disabled) {
    background-color: #dc2626;
    transform: translateY(-1px);
}

.btn-confirm--warning {
    background-color: #d97706;
    box-shadow: 0 4px 14px rgba(217, 119, 6, 0.25);
}
.btn-confirm--warning:hover:not(:disabled) {
    background-color: #b45309;
    transform: translateY(-1px);
}

.btn-confirm--info {
    background-color: #0d9488;
    box-shadow: 0 4px 14px rgba(13, 148, 136, 0.25);
}
.btn-confirm--info:hover:not(:disabled) {
    background-color: #0f766e;
    transform: translateY(-1px);
}

.btn-confirm:disabled,
.btn-cancel:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.spin-icon {
    animation: spin 0.8s linear infinite;
}
@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

.fade-backdrop-enter-active {
    transition: opacity 0.2s ease;
}
.fade-backdrop-leave-active {
    transition: opacity 0.15s ease;
}
.fade-backdrop-enter-from,
.fade-backdrop-leave-to {
    opacity: 0;
}

.scale-card-enter-active {
    transition: opacity 0.25s ease, transform 0.25s cubic-bezier(0.34, 1.4, 0.64, 1);
}
.scale-card-leave-active {
    transition: opacity 0.15s ease, transform 0.15s ease;
}
.scale-card-enter-from {
    opacity: 0;
    transform: scale(0.88) translateY(12px);
}
.scale-card-leave-to {
    opacity: 0;
    transform: scale(0.94) translateY(6px);
}
</style>
