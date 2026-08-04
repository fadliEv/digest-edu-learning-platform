<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { useForm } from '@inertiajs/vue3';
import { onMounted, ref, watch } from 'vue';

const props = defineProps<{
    show: boolean;
    editUser?: {
        id: number;
        name: string;
        email: string;
        role: string;
        is_active: boolean;
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
    name: '',
    email: '',
    password: '',
    role: 'user',
    is_active: true,
});

watch(
    () => props.editUser,
    (val) => {
        if (val) {
            form.name = val.name;
            form.email = val.email;
            form.password = '';
            form.role = val.role;
            form.is_active = val.is_active;
        } else {
            form.reset();
            form.role = 'user';
            form.is_active = true;
        }
        form.clearErrors();
    },
    { immediate: true }
);

function submit() {
    if (props.editUser) {
        form.patch(`/admin/users/${props.editUser.id}`, {
            onSuccess: () => {
                form.reset();
                emit('close');
            },
        });
    } else {
        form.post('/admin/users', {
            onSuccess: () => {
                form.reset();
                emit('close');
            },
        });
    }
}

function closeModal() {
    form.clearErrors();
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
                            {{ editUser ? 'Edit Data Pengguna' : 'Tambah Pengguna Baru' }}
                        </h2>
                        <p class="modal-subtitle">
                            {{ editUser ? 'Perbarui informasi akun pengguna di sistem.' : 'Lengkapi formulir di bawah ini untuk membuat akun pengguna baru.' }}
                        </p>

                        <!-- Form -->
                        <form class="modal-form" @submit.prevent="submit">
                            <!-- Nama Lengkap -->
                            <div class="form-group">
                                <label class="form-label">
                                    Nama Lengkap <span class="required">*</span>
                                </label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    required
                                    placeholder="Contoh: Dr. Budi Santoso"
                                    class="form-input"
                                    :class="{ 'input-error': form.errors.name }"
                                />
                                <span v-if="form.errors.name" class="error-text">{{ form.errors.name }}</span>
                            </div>

                            <!-- Alamat Email -->
                            <div class="form-group">
                                <label class="form-label">
                                    Alamat Email <span class="required">*</span>
                                </label>
                                <input
                                    v-model="form.email"
                                    type="email"
                                    required
                                    placeholder="contoh: budi@digest.edu"
                                    class="form-input"
                                    :class="{ 'input-error': form.errors.email }"
                                />
                                <span v-if="form.errors.email" class="error-text">{{ form.errors.email }}</span>
                            </div>

                            <!-- Kata Sandi -->
                            <div class="form-group">
                                <label class="form-label">
                                    Kata Sandi <span v-if="!editUser" class="required">*</span>
                                </label>
                                <input
                                    v-model="form.password"
                                    type="password"
                                    :required="!editUser"
                                    :placeholder="editUser ? 'Kosongkan jika tidak ingin mengubah kata sandi' : 'Minimal 8 karakter'"
                                    class="form-input"
                                    :class="{ 'input-error': form.errors.password }"
                                />
                                <span v-if="form.errors.password" class="error-text">{{ form.errors.password }}</span>
                                <p v-if="editUser" class="field-hint">
                                    Isi hanya jika Anda ingin mereset/mengganti kata sandi pengguna ini.
                                </p>
                            </div>

                            <!-- Peran / Role -->
                            <div class="form-group">
                                <label class="form-label">
                                    Peran Pengguna <span class="required">*</span>
                                </label>
                                <div class="role-selector-grid">
                                    <label
                                        :class="['role-card', { 'role-card--active': form.role === 'user' }]"
                                        @click="form.role = 'user'"
                                    >
                                        <input type="radio" v-model="form.role" value="user" class="hidden-radio" />
                                        <div class="role-card-icon role-card-icon--student">
                                            <Icon icon="lucide:graduation-cap" width="20" height="20" />
                                        </div>
                                        <div>
                                            <p class="role-card-title">Siswa / User</p>
                                            <p class="role-card-desc">Dapat menonton video & mengerjakan kuis evaluasi.</p>
                                        </div>
                                    </label>

                                    <label
                                        :class="['role-card', { 'role-card--active': form.role === 'admin' }]"
                                        @click="form.role = 'admin'"
                                    >
                                        <input type="radio" v-model="form.role" value="admin" class="hidden-radio" />
                                        <div class="role-card-icon role-card-icon--admin">
                                            <Icon icon="lucide:shield-check" width="20" height="20" />
                                        </div>
                                        <div>
                                            <p class="role-card-title">Administrator</p>
                                            <p class="role-card-desc">Akses penuh manajemen video, kuis & pengguna.</p>
                                        </div>
                                    </label>
                                </div>
                                <span v-if="form.errors.role" class="error-text">{{ form.errors.role }}</span>
                            </div>

                            <!-- Status Akun -->
                            <div class="form-group">
                                <label class="status-toggle-label">
                                    <input
                                        v-model="form.is_active"
                                        type="checkbox"
                                        class="toggle-checkbox"
                                    />
                                    <div class="toggle-track" :class="{ 'toggle-track--active': form.is_active }">
                                        <div class="toggle-thumb"></div>
                                    </div>
                                    <span class="toggle-text">
                                        Akun {{ form.is_active ? 'Aktif (Dapat Login)' : 'Nonaktif (Terblokir)' }}
                                    </span>
                                </label>
                            </div>

                            <!-- Footer Action Buttons -->
                            <div class="modal-actions">
                                <button type="button" class="btn-cancel" @click="closeModal">
                                    Batal
                                </button>
                                <button type="submit" class="btn-save" :disabled="form.processing">
                                    <Icon
                                        :icon="form.processing ? 'lucide:loader-circle' : (editUser ? 'lucide:check' : 'lucide:user-plus')"
                                        width="16"
                                        height="16"
                                        :class="{ 'spin-icon': form.processing }"
                                    />
                                    {{ form.processing ? 'Menyimpan...' : (editUser ? 'Simpan Perubahan' : 'Tambah Pengguna') }}
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
    position: fixed;
    inset: 0;
    z-index: 100;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    background: rgba(15, 23, 42, 0.45);
    backdrop-filter: blur(4px);
}
.modal-card {
    position: relative;
    width: 100%;
    max-width: 520px;
    background: #ffffff;
    border-radius: 20px;
    padding: 28px;
    box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.15);
    font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
}
.modal-close-btn {
    position: absolute;
    top: 20px;
    right: 20px;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: none;
    background: #f1f5f9;
    color: #64748b;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
}
.modal-close-btn:hover {
    background: #e2e8f0;
    color: #0f172a;
}
.modal-title {
    font-size: 18px;
    font-weight: 700;
    color: #0f172a;
    margin: 0 0 4px;
}
.modal-subtitle {
    font-size: 13px;
    color: #64748b;
    margin: 0 0 20px;
    line-height: 1.4;
}
.modal-form {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.form-label {
    font-size: 12.5px;
    font-weight: 600;
    color: #334155;
}
.required { color: #ef4444; }
.form-input {
    width: 100%;
    height: 42px;
    padding: 0 14px;
    border-radius: 10px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    font-size: 13.5px;
    color: #0f172a;
    outline: none;
    transition: all 0.2s ease;
    box-sizing: border-box;
}
.form-input:focus {
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
.field-hint {
    font-size: 11.5px;
    color: #64748b;
    margin: 2px 0 0;
}

/* Role Selector */
.role-selector-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
.hidden-radio { display: none; }
.role-card {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 12px;
    border: 1.5px solid #e2e8f0;
    border-radius: 12px;
    background: #f8fafc;
    cursor: pointer;
    transition: all 0.2s ease;
}
.role-card:hover {
    border-color: #cbd5e1;
    background: #ffffff;
}
.role-card--active {
    border-color: #0d9488 !important;
    background: #f0fdfa !important;
    box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.08);
}
.role-card-icon {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.role-card-icon--student {
    background: #e0f2fe;
    color: #0284c7;
}
.role-card-icon--admin {
    background: #ccfbf1;
    color: #0d9488;
}
.role-card-title {
    font-size: 13px;
    font-weight: 700;
    color: #0f172a;
    margin: 0 0 2px;
}
.role-card-desc {
    font-size: 11px;
    color: #64748b;
    margin: 0;
    line-height: 1.35;
}

/* Status Toggle */
.status-toggle-label {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    user-select: none;
}
.toggle-checkbox { display: none; }
.toggle-track {
    width: 38px;
    height: 22px;
    background: #cbd5e1;
    border-radius: 12px;
    padding: 2px;
    transition: background-color 0.2s ease;
    box-sizing: border-box;
}
.toggle-track--active {
    background: #10b981;
}
.toggle-thumb {
    width: 18px;
    height: 18px;
    background: #ffffff;
    border-radius: 50%;
    transition: transform 0.2s ease;
    box-shadow: 0 1px 3px rgba(0,0,0,0.15);
}
.toggle-track--active .toggle-thumb {
    transform: translateX(16px);
}
.toggle-text {
    font-size: 13px;
    font-weight: 600;
    color: #334155;
}

/* Modal Actions */
.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 12px;
    padding-top: 16px;
    border-top: 1px solid #f1f5f9;
}
.btn-cancel {
    height: 40px;
    padding: 0 18px;
    border-radius: 10px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #475569;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}
.btn-cancel:hover {
    background: #f8fafc;
    color: #0f172a;
}
.btn-save {
    height: 40px;
    padding: 0 20px;
    border-radius: 10px;
    border: none;
    background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);
    color: #ffffff;
    font-size: 13px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 4px 12px rgba(13, 148, 136, 0.2);
}
.btn-save:hover {
    opacity: 0.95;
    box-shadow: 0 6px 16px rgba(13, 148, 136, 0.3);
}
.btn-save:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Transitions */
.fade-backdrop-enter-active,
.fade-backdrop-leave-active {
    transition: opacity 0.2s ease;
}
.fade-backdrop-enter-from,
.fade-backdrop-leave-to {
    opacity: 0;
}
.scale-card-enter-active,
.scale-card-leave-active {
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}
.scale-card-enter-from,
.scale-card-leave-to {
    opacity: 0;
    transform: scale(0.95) translateY(10px);
}
.spin-icon {
    animation: spin 1s linear infinite;
}
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>
