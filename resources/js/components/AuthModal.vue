<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { useForm } from '@inertiajs/vue3';
import { onMounted, ref, watch } from 'vue';

// ─── Props & Emits ────────────────────────────────────────────────────────────
const props = defineProps<{
    show: boolean;
    initialMode?: 'login' | 'register';
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

// ─── State ────────────────────────────────────────────────────────────────────
const mode = ref<'login' | 'register'>(props.initialMode ?? 'login');

// Gate Teleport to client-only to prevent SSR hydration mismatch
const isMounted = ref(false);
onMounted(() => {
    isMounted.value = true;
});

watch(
    () => props.initialMode,
    (val) => {
        if (val) mode.value = val;
    },
);

// ─── Forms ────────────────────────────────────────────────────────────────────
const loginForm = useForm({
    email: '',
    password: '',
    remember: false,
});

const registerForm = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

// ─── Actions ──────────────────────────────────────────────────────────────────
function submitLogin() {
    loginForm.post('/login', {
        onSuccess: () => {
            loginForm.reset();
            emit('close');
        },
    });
}

function submitRegister() {
    registerForm.post('/register', {
        onSuccess: () => {
            registerForm.reset();
            emit('close');
        },
    });
}

function switchMode(target: 'login' | 'register') {
    mode.value = target;
    loginForm.clearErrors();
    registerForm.clearErrors();
}

function closeModal() {
    emit('close');
}
</script>

<template>
    <!-- Only teleport after client mount to avoid SSR hydration mismatch -->
    <Teleport v-if="isMounted" to="body">
        <!-- ── Backdrop (fade only) ── -->
        <Transition name="fade-backdrop">
            <div v-if="show" class="auth-backdrop" @click.self="closeModal">
                <!-- ── Modal Card (scale + fade) ── -->
                <Transition name="scale-card" appear>
                    <div v-if="show" class="auth-card" role="dialog" aria-modal="true">
                        <!-- Close -->
                        <button class="auth-close" aria-label="Tutup" @click="closeModal">
                            <Icon icon="lucide:x" width="18" height="18" />
                        </button>

                        <!-- Brand Header -->
                        <div class="auth-brand">
                            <div class="brand-logo">
                                <Icon icon="healthicons:intestine" width="20" height="20" />
                            </div>
                            <span class="brand-name">Digest<span class="brand-accent">Edu</span></span>
                        </div>
                        <p class="auth-tagline">Platform edukasi kesehatan pencernaan interaktif</p>

                        <!-- Title + Mode Switcher -->
                        <div class="auth-title-row">
                            <h2 class="auth-title">
                                {{ mode === 'login' ? 'Masuk ke Akun' : 'Buat Akun Baru' }}
                            </h2>
                            <p class="auth-switch-text">
                                <template v-if="mode === 'login'">
                                    Belum punya akun?
                                    <button type="button" class="btn-switch" @click="switchMode('register')">
                                        Daftar di sini
                                    </button>
                                </template>
                                <template v-else>
                                    Sudah punya akun?
                                    <button type="button" class="btn-switch" @click="switchMode('login')">
                                        Masuk di sini
                                    </button>
                                </template>
                            </p>
                        </div>

                        <!-- ── Login Form ── -->
                        <form v-if="mode === 'login'" class="auth-form" @submit.prevent="submitLogin">
                            <div class="field-group">
                                <label class="field-label">
                                    <Icon icon="lucide:mail" width="13" height="13" />
                                    Alamat Email
                                </label>
                                <input
                                    v-model="loginForm.email"
                                    type="email"
                                    required
                                    placeholder="nama@email.com"
                                    class="field-input"
                                    :class="{ 'field-input--error': loginForm.errors.email }"
                                />
                                <span v-if="loginForm.errors.email" class="field-error">
                                    {{ loginForm.errors.email }}
                                </span>
                            </div>

                            <div class="field-group">
                                <label class="field-label">
                                    <Icon icon="lucide:lock-keyhole" width="13" height="13" />
                                    Kata Sandi
                                </label>
                                <input
                                    v-model="loginForm.password"
                                    type="password"
                                    required
                                    placeholder="Masukkan kata sandi"
                                    class="field-input"
                                    :class="{ 'field-input--error': loginForm.errors.password }"
                                />
                                <span v-if="loginForm.errors.password" class="field-error">
                                    {{ loginForm.errors.password }}
                                </span>
                            </div>

                            <label class="remember-row">
                                <input v-model="loginForm.remember" type="checkbox" />
                                <span>Ingat saya di perangkat ini</span>
                            </label>

                            <button type="submit" class="btn-submit" :disabled="loginForm.processing">
                                <Icon
                                    :icon="loginForm.processing ? 'lucide:loader-circle' : 'lucide:log-in'"
                                    width="17"
                                    height="17"
                                    :class="{ 'icon-spin': loginForm.processing }"
                                />
                                {{ loginForm.processing ? 'Memproses...' : 'Masuk Sekarang' }}
                            </button>
                        </form>

                        <!-- ── Register Form ── -->
                        <form v-else class="auth-form" @submit.prevent="submitRegister">
                            <div class="field-group">
                                <label class="field-label">
                                    <Icon icon="lucide:user" width="13" height="13" />
                                    Nama Lengkap
                                </label>
                                <input
                                    v-model="registerForm.name"
                                    type="text"
                                    required
                                    placeholder="Nama lengkap Anda"
                                    class="field-input"
                                    :class="{ 'field-input--error': registerForm.errors.name }"
                                />
                                <span v-if="registerForm.errors.name" class="field-error">
                                    {{ registerForm.errors.name }}
                                </span>
                            </div>

                            <div class="field-group">
                                <label class="field-label">
                                    <Icon icon="lucide:mail" width="13" height="13" />
                                    Alamat Email
                                </label>
                                <input
                                    v-model="registerForm.email"
                                    type="email"
                                    required
                                    placeholder="nama@email.com"
                                    class="field-input"
                                    :class="{ 'field-input--error': registerForm.errors.email }"
                                />
                                <span v-if="registerForm.errors.email" class="field-error">
                                    {{ registerForm.errors.email }}
                                </span>
                            </div>

                            <div class="field-group">
                                <label class="field-label">
                                    <Icon icon="lucide:lock-keyhole" width="13" height="13" />
                                    Kata Sandi
                                </label>
                                <input
                                    v-model="registerForm.password"
                                    type="password"
                                    required
                                    placeholder="Minimal 8 karakter"
                                    class="field-input"
                                    :class="{ 'field-input--error': registerForm.errors.password }"
                                />
                                <span v-if="registerForm.errors.password" class="field-error">
                                    {{ registerForm.errors.password }}
                                </span>
                            </div>

                            <div class="field-group">
                                <label class="field-label">
                                    <Icon icon="lucide:shield-check" width="13" height="13" />
                                    Konfirmasi Kata Sandi
                                </label>
                                <input
                                    v-model="registerForm.password_confirmation"
                                    type="password"
                                    required
                                    placeholder="Ulangi kata sandi Anda"
                                    class="field-input"
                                    :class="{ 'field-input--error': registerForm.errors.password_confirmation }"
                                />
                                <span v-if="registerForm.errors.password_confirmation" class="field-error">
                                    {{ registerForm.errors.password_confirmation }}
                                </span>
                            </div>

                            <button type="submit" class="btn-submit" :disabled="registerForm.processing">
                                <Icon
                                    :icon="registerForm.processing ? 'lucide:loader-circle' : 'lucide:user-plus'"
                                    width="17"
                                    height="17"
                                    :class="{ 'icon-spin': registerForm.processing }"
                                />
                                {{ registerForm.processing ? 'Membuat Akun...' : 'Daftar Sekarang' }}
                            </button>
                        </form>

                        <p class="auth-footer-note">
                            <Icon icon="lucide:shield" width="12" height="12" />
                            Sesi ini bersifat sementara — data Anda tersimpan secara aman.
                        </p>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
/* ─── Backdrop ────────────────────────────────────────────────────────────── */
.auth-backdrop {
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

/* ─── Card ────────────────────────────────────────────────────────────────── */
.auth-card {
    position: relative;
    background-color: #ffffff;
    border-radius: 24px;
    width: 100%;
    max-width: 440px;
    padding: 32px 32px 28px;
    box-shadow: 0 20px 50px rgba(15, 23, 42, 0.2);
    box-sizing: border-box;
    font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
}

/* ─── Close Button ────────────────────────────────────────────────────────── */
.auth-close {
    position: absolute;
    top: 18px;
    right: 18px;
    background: none;
    border: none;
    color: #94a3b8;
    cursor: pointer;
    padding: 6px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background-color 0.2s, color 0.2s;
}
.auth-close:hover {
    background-color: #f1f5f9;
    color: #0f172a;
}

/* ─── Brand ───────────────────────────────────────────────────────────────── */
.auth-brand {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 4px;
}
.brand-logo {
    width: 30px;
    height: 30px;
    background-color: #0d9488;
    color: #ffffff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}
.brand-name {
    font-size: 17px;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.3px;
}
.brand-accent {
    color: #0d9488;
}
.auth-tagline {
    font-size: 12.5px;
    color: #64748b;
    margin: 0 0 20px;
}

/* ─── Title & Switcher ────────────────────────────────────────────────────── */
.auth-title-row {
    margin-bottom: 20px;
}
.auth-title {
    font-size: 20px;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 4px;
    letter-spacing: -0.4px;
}
.auth-switch-text {
    font-size: 13px;
    color: #64748b;
    margin: 0;
}
.btn-switch {
    background: none;
    border: none;
    color: #0d9488;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    padding: 0;
    font-family: inherit;
    transition: color 0.2s;
}
.btn-switch:hover {
    color: #0f766e;
    text-decoration: underline;
}

/* ─── Form ────────────────────────────────────────────────────────────────── */
.auth-form {
    display: flex;
    flex-direction: column;
    gap: 14px;
}
.field-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}
.field-label {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 12.5px;
    font-weight: 600;
    color: #334155;
}
.field-input {
    width: 100%;
    background-color: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 10px 14px;
    font-size: 13.5px;
    color: #0f172a;
    font-family: inherit;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s, background-color 0.2s;
    box-sizing: border-box;
}
.field-input::placeholder {
    color: #94a3b8;
}
.field-input:focus {
    background-color: #ffffff;
    border-color: #0d9488;
    box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.12);
}
.field-input--error {
    border-color: #ef4444 !important;
}
.field-error {
    font-size: 11.5px;
    color: #ef4444;
}

/* ─── Remember Checkbox ───────────────────────────────────────────────────── */
.remember-row {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    font-size: 12.5px;
    color: #64748b;
}
.remember-row input {
    accent-color: #0d9488;
}

/* ─── Submit Button ───────────────────────────────────────────────────────── */
.btn-submit {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    background-color: #0d9488;
    color: #ffffff;
    border: none;
    border-radius: 12px;
    padding: 11px 20px;
    font-size: 14px;
    font-weight: 700;
    font-family: inherit;
    cursor: pointer;
    transition: background-color 0.2s, transform 0.2s, box-shadow 0.2s;
    box-shadow: 0 4px 14px rgba(13, 148, 136, 0.25);
    margin-top: 6px;
}
.btn-submit:hover:not(:disabled) {
    background-color: #0f766e;
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(13, 148, 136, 0.35);
}
.btn-submit:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

/* ─── Footer Note ─────────────────────────────────────────────────────────── */
.auth-footer-note {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    text-align: center;
    font-size: 11.5px;
    color: #94a3b8;
    margin: 18px 0 0;
}

/* ─── Spin Animation ──────────────────────────────────────────────────────── */
.icon-spin {
    animation: spin 0.8s linear infinite;
}
@keyframes spin {
    to { transform: rotate(360deg); }
}

/* ─── Backdrop Transition (fade only) ────────────────────────────────────── */
.fade-backdrop-enter-active { transition: opacity 0.25s ease; }
.fade-backdrop-leave-active { transition: opacity 0.2s ease; }
.fade-backdrop-enter-from,
.fade-backdrop-leave-to { opacity: 0; }

/* ─── Card Transition (scale + fade, independent) ────────────────────────── */
.scale-card-enter-active {
    transition: opacity 0.28s ease, transform 0.28s cubic-bezier(0.34, 1.3, 0.64, 1);
}
.scale-card-leave-active {
    transition: opacity 0.18s ease, transform 0.18s ease;
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
