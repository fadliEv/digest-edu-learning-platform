<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { onMounted, ref } from 'vue';

defineProps<{
    show: boolean;
    title?: string;
    description?: string;
}>();

const isMounted = ref(false);
onMounted(() => {
    isMounted.value = true;
});
</script>

<template>
    <Teleport v-if="isMounted" to="body">
        <Transition name="fade-backdrop">
            <div v-if="show" class="processing-backdrop">
                <Transition name="scale-modal" appear>
                    <div v-if="show" class="processing-card" role="dialog" aria-modal="true">
                        <!-- AI Animated Glow Orbit -->
                        <div class="ai-glow-wrapper">
                            <div class="ai-glow-ring"></div>
                            <div class="ai-icon-box">
                                <Icon icon="lucide:sparkles" width="32" height="32" class="ai-sparkle-icon" />
                            </div>
                        </div>

                        <!-- Header -->
                        <h3 class="processing-title">
                            {{ title || 'Sedang Memproses Kuis Evaluasi AI' }}
                        </h3>
                        <p class="processing-desc">
                            {{ description || 'Google Gemini 2.0 Flash sedang menganalisis naskah materi video dan merumuskan 6 soal kuis evaluasi pilihan ganda...' }}
                        </p>

                        <!-- Progress Bar & Steps -->
                        <div class="progress-bar-track">
                            <div class="progress-bar-fill"></div>
                        </div>

                        <div class="steps-list">
                            <div class="step-item step-item--active">
                                <span class="step-dot"></span>
                                <span>Analisis naskah transkrip video</span>
                            </div>
                            <div class="step-item step-item--active">
                                <span class="step-dot"></span>
                                <span>Proses AI Gemini 2.0 Flash (6 Soal Evaluasi)</span>
                            </div>
                            <div class="step-item step-item--active">
                                <span class="step-dot"></span>
                                <span>Penyusunan 6 soal kuis evaluasi otomatis</span>
                            </div>
                        </div>

                        <!-- Footer Badge -->
                        <div class="processing-footer">
                            <Icon icon="lucide:loader-circle" width="14" height="14" class="spin-icon" />
                            <span>Mohon tunggu beberapa detik, jangan tutup halaman ini</span>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.processing-backdrop {
    position: fixed; inset: 0; z-index: 9999;
    background-color: rgba(15, 23, 42, 0.72); backdrop-filter: blur(8px);
    display: flex; align-items: center; justify-content: center; padding: 20px;
}

.processing-card {
    background: #ffffff; border-radius: 28px; width: 100%; max-width: 480px;
    padding: 36px 32px 28px; text-align: center;
    box-shadow: 0 25px 60px rgba(15, 23, 42, 0.3); box-sizing: border-box;
    font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
    position: relative; overflow: hidden;
}

/* AI Glow Icon Orbit */
.ai-glow-wrapper {
    position: relative; width: 80px; height: 80px; margin: 0 auto 20px;
    display: flex; align-items: center; justify-content: center;
}
.ai-glow-ring {
    position: absolute; inset: -6px; border-radius: 50%;
    background: conic-gradient(from 0deg, #0d9488, #d97706, #3b82f6, #0d9488);
    animation: rotateGlow 3s linear infinite; filter: blur(6px); opacity: 0.85;
}
.ai-icon-box {
    position: relative; z-index: 2; width: 68px; height: 68px; border-radius: 50%;
    background: linear-gradient(135deg, #0f766e, #0d9488); color: #ffffff;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 8px 24px rgba(13, 148, 136, 0.35);
}
.ai-sparkle-icon {
    animation: pulseSparkle 1.8s ease-in-out infinite;
}

@keyframes rotateGlow {
    to { transform: rotate(360deg); }
}
@keyframes pulseSparkle {
    0%, 100% { transform: scale(1); opacity: 0.9; }
    50% { transform: scale(1.18); opacity: 1; }
}

.processing-title {
    font-size: 19px; font-weight: 800; color: #0f172a; margin: 0 0 8px; letter-spacing: -0.4px;
}
.processing-desc {
    font-size: 13px; color: #64748b; margin: 0 0 24px; line-height: 1.55;
}

/* Progress bar animation */
.progress-bar-track {
    width: 100%; height: 6px; background: #f1f5f9; border-radius: 100px; overflow: hidden; margin-bottom: 20px;
}
.progress-bar-fill {
    height: 100%; width: 40%; background: linear-gradient(90deg, #0d9488, #d97706);
    border-radius: 100px; animation: shimmyProgress 2s ease-in-out infinite;
}
@keyframes shimmyProgress {
    0% { transform: translateX(-100%); }
    50% { transform: translateX(100%); }
    100% { transform: translateX(250%); }
}

/* Steps list */
.steps-list {
    display: flex; flex-direction: column; gap: 8px; margin-bottom: 24px; text-align: left;
}
.step-item {
    display: flex; align-items: center; gap: 10px; font-size: 12.5px; color: #64748b;
    background: #f8fafc; padding: 8px 12px; border-radius: 10px; border: 1px solid #f1f5f9;
}
.step-dot {
    width: 8px; height: 8px; border-radius: 50%; background: #0d9488;
    box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.2); flex-shrink: 0;
    animation: blinkDot 1.2s ease-in-out infinite alternate;
}
@keyframes blinkDot {
    from { opacity: 0.4; }
    to { opacity: 1; transform: scale(1.2); }
}

.processing-footer {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 12px; font-weight: 700; color: #0d9488; background: #f0fdfa;
    border: 1px solid #ccfbf1; padding: 6px 14px; border-radius: 100px;
}
.spin-icon { animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Transition animations */
.fade-backdrop-enter-active { transition: opacity 0.25s ease; }
.fade-backdrop-leave-active { transition: opacity 0.2s ease; }
.fade-backdrop-enter-from, .fade-backdrop-leave-to { opacity: 0; }

.scale-modal-enter-active { transition: opacity 0.3s ease, transform 0.3s cubic-bezier(0.34, 1.4, 0.64, 1); }
.scale-modal-leave-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.scale-modal-enter-from { opacity: 0; transform: scale(0.85) translateY(16px); }
.scale-modal-leave-to { opacity: 0; transform: scale(0.92) translateY(8px); }
</style>
