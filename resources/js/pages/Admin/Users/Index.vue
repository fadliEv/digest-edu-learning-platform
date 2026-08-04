<script setup lang="ts">
import ConfirmModal from '@/components/Admin/ConfirmModal.vue';
import UserModal from '@/components/Admin/UserModal.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Icon } from '@iconify/vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps<{
    users: {
        data: Array<{
            id: number;
            name: string;
            email: string;
            role: string;
            is_active: boolean;
            email_verified_at: string | null;
            created_at: string;
        }>;
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
        current_page: number;
        last_page: number;
        total: number;
    };
    filters: {
        search: string;
        role: string;
        status: string;
    };
    stats: {
        total_users: number;
        total_admins: number;
        total_students: number;
        active_users: number;
    };
    currentUser: {
        id: number;
    };
}>();

// ─── Search & Filters ─────────────────────────────────────────────────────────
const search = ref(props.filters.search || '');
const selectedRole = ref(props.filters.role || '');
const selectedStatus = ref(props.filters.status || '');

let searchTimeout: ReturnType<typeof setTimeout> | null = null;

watch([search, selectedRole, selectedStatus], ([newSearch, newRole, newStatus]) => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(
            '/admin/users',
            { search: newSearch, role: newRole, status: newStatus },
            { preserveState: true, replace: true, preserveScroll: true }
        );
    }, 300);
});

// ─── Add / Edit Modal ─────────────────────────────────────────────────────────
const userModalShow = ref(false);
const editingUser = ref<{
    id: number;
    name: string;
    email: string;
    role: string;
    is_active: boolean;
} | null>(null);

function openAddModal() {
    editingUser.value = null;
    userModalShow.value = true;
}

function openEditModal(user: any) {
    editingUser.value = {
        id: user.id,
        name: user.name,
        email: user.email,
        role: user.role,
        is_active: user.is_active,
    };
    userModalShow.value = true;
}

// ─── Status Toggle ────────────────────────────────────────────────────────────
function toggleUserStatus(user: any) {
    if (user.id === props.currentUser.id) return;
    router.patch(`/admin/users/${user.id}/status`, {}, { preserveScroll: true });
}

// ─── Delete Confirmation Modal ────────────────────────────────────────────────
const confirmModalShow = ref(false);
const confirmModalTitle = ref('');
const confirmModalMessage = ref('');
const confirmModalType = ref<'danger' | 'warning' | 'info'>('danger');
const confirmModalConfirmText = ref('Ya, Hapus');
const onConfirmAction = ref<(() => void) | null>(null);

function deleteUser(user: any) {
    if (user.id === props.currentUser.id) return;
    confirmModalTitle.value = 'Hapus Akun Pengguna?';
    confirmModalMessage.value = `Apakah Anda yakin ingin menghapus akun "${user.name}" (${user.email})?\nTindakan ini tidak dapat dibatalkan.`;
    confirmModalType.value = 'danger';
    confirmModalConfirmText.value = 'Hapus Akun';
    onConfirmAction.value = () => {
        router.delete(`/admin/users/${user.id}`, { preserveScroll: true });
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

// Helper to get initials from user name
function getInitials(name: string): string {
    if (!name) return 'U';
    const parts = name.trim().split(' ');
    if (parts.length >= 2) {
        return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return name.slice(0, 2).toUpperCase();
}
</script>

<template>
    <Head title="Manajemen Pengguna — Admin DigestEdu" />

    <AdminLayout title="Manajemen Data Pengguna">
        <!-- Topbar Actions Slot -->
        <template #topbar-actions>
            <div class="topbar-filter-group">
                <!-- Search Input -->
                <div class="search-input-wrap">
                    <Icon icon="lucide:search" width="15" height="15" class="search-icon" />
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Cari nama atau email..."
                        class="search-input"
                    />
                </div>

                <!-- Add User Button -->
                <button type="button" class="btn-primary-add" @click="openAddModal">
                    <Icon icon="lucide:user-plus" width="16" height="16" />
                    Tambah Pengguna
                </button>
            </div>
        </template>

        <!-- ══ STAT CARDS ══ -->
        <div class="stat-grid">
            <!-- Total Pengguna -->
            <div class="stat-card">
                <div class="stat-card__icon stat-card__icon--blue">
                    <Icon icon="lucide:users" width="20" height="20" />
                </div>
                <div class="stat-card__body">
                    <p class="stat-card__num">{{ stats.total_users }}</p>
                    <p class="stat-card__label">Total Pengguna</p>
                    <p class="stat-card__sub">Terdaftar di platform</p>
                </div>
            </div>

            <!-- Total Admin -->
            <div class="stat-card">
                <div class="stat-card__icon stat-card__icon--teal">
                    <Icon icon="lucide:shield-check" width="20" height="20" />
                </div>
                <div class="stat-card__body">
                    <p class="stat-card__num">{{ stats.total_admins }}</p>
                    <p class="stat-card__label">Administrator</p>
                    <p class="stat-card__sub">Pengelola sistem</p>
                </div>
            </div>

            <!-- Total Siswa -->
            <div class="stat-card">
                <div class="stat-card__icon stat-card__icon--violet">
                    <Icon icon="lucide:graduation-cap" width="20" height="20" />
                </div>
                <div class="stat-card__body">
                    <p class="stat-card__num">{{ stats.total_students }}</p>
                    <p class="stat-card__label">Siswa / User</p>
                    <p class="stat-card__sub">Peserta pembelajar</p>
                </div>
            </div>

            <!-- Akun Aktif -->
            <div class="stat-card">
                <div class="stat-card__icon stat-card__icon--green">
                    <Icon icon="lucide:user-check" width="20" height="20" />
                </div>
                <div class="stat-card__body">
                    <p class="stat-card__num">{{ stats.active_users }}</p>
                    <p class="stat-card__label">Akun Aktif</p>
                    <p class="stat-card__sub">Status dapat akses</p>
                </div>
            </div>
        </div>

        <!-- ══ TABLE & FILTERS SECTION ══ -->
        <div class="table-card">
            <!-- Filter Bar -->
            <div class="filter-bar">
                <div class="filter-group">
                    <span class="filter-label">PERAN:</span>
                    <button
                        :class="['filter-pill', { active: selectedRole === '' }]"
                        @click="selectedRole = ''"
                    >
                        Semua
                    </button>
                    <button
                        :class="['filter-pill', { active: selectedRole === 'admin' }]"
                        @click="selectedRole = 'admin'"
                    >
                        Administrator
                    </button>
                    <button
                        :class="['filter-pill', { active: selectedRole === 'user' }]"
                        @click="selectedRole = 'user'"
                    >
                        Siswa / User
                    </button>
                </div>

                <div class="filter-group">
                    <span class="filter-label">STATUS:</span>
                    <button
                        :class="['filter-pill', { active: selectedStatus === '' }]"
                        @click="selectedStatus = ''"
                    >
                        Semua
                    </button>
                    <button
                        :class="['filter-pill', { active: selectedStatus === 'active' }]"
                        @click="selectedStatus = 'active'"
                    >
                        Aktif
                    </button>
                    <button
                        :class="['filter-pill', { active: selectedStatus === 'inactive' }]"
                        @click="selectedStatus = 'inactive'"
                    >
                        Nonaktif
                    </button>
                </div>
            </div>

            <!-- Table -->
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>NAMA PENGGUNA</th>
                            <th>ALAMAT EMAIL</th>
                            <th>PERAN AKUN</th>
                            <th>STATUS AKUN</th>
                            <th>TERDAFTAR</th>
                            <th>AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="users.data.length === 0">
                            <td colspan="6" class="table-empty">
                                <Icon icon="lucide:user-x" width="28" height="28" />
                                Tidak ada data pengguna yang ditemukan.
                            </td>
                        </tr>
                        <tr v-for="user in users.data" :key="user.id">
                            <!-- Nama & Avatar -->
                            <td class="col-user">
                                <div class="user-cell">
                                    <div :class="['user-avatar', user.role === 'admin' ? 'user-avatar--admin' : 'user-avatar--user']">
                                        {{ getInitials(user.name) }}
                                    </div>
                                    <div>
                                        <p class="user-name">
                                            {{ user.name }}
                                            <span v-if="user.id === currentUser.id" class="self-tag">(Anda)</span>
                                        </p>
                                    </div>
                                </div>
                            </td>

                            <!-- Email -->
                            <td>
                                <span class="email-text">{{ user.email }}</span>
                            </td>

                            <!-- Role Badge -->
                            <td>
                                <span :class="['role-badge', user.role === 'admin' ? 'role-badge--admin' : 'role-badge--user']">
                                    <Icon :icon="user.role === 'admin' ? 'lucide:shield-check' : 'lucide:graduation-cap'" width="12" height="12" />
                                    {{ user.role === 'admin' ? 'Admin' : 'Siswa' }}
                                </span>
                            </td>

                            <!-- Status Toggle Switch -->
                            <td>
                                <div
                                    :class="['status-toggle', { 'status-toggle--active': user.is_active, 'status-toggle--disabled': user.id === currentUser.id }]"
                                    :title="user.id === currentUser.id ? 'Tidak dapat mengubah status akun sendiri' : 'Klik untuk ubah status akun'"
                                    @click="toggleUserStatus(user)"
                                >
                                    <div class="status-knob"></div>
                                    <span class="status-text">{{ user.is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                </div>
                            </td>

                            <!-- Date Created -->
                            <td class="col-date">{{ user.created_at }}</td>

                            <!-- Actions -->
                            <td>
                                <div class="action-group">
                                    <button
                                        class="action-btn"
                                        title="Edit Pengguna"
                                        @click="openEditModal(user)"
                                    >
                                        <Icon icon="lucide:pencil" width="14" height="14" />
                                    </button>
                                    <button
                                        class="action-btn action-btn--danger"
                                        :disabled="user.id === currentUser.id"
                                        :title="user.id === currentUser.id ? 'Anda tidak dapat menghapus akun sendiri' : 'Hapus Pengguna'"
                                        @click="deleteUser(user)"
                                    >
                                        <Icon icon="lucide:trash-2" width="14" height="14" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Footer -->
            <div v-if="users.last_page > 1" class="pagination-bar">
                <p class="pagination-info">
                    Menampilkan <strong>{{ users.data.length }}</strong> dari <strong>{{ users.total }}</strong> pengguna
                </p>
                <div class="pagination-links">
                    <Component
                        v-for="(link, key) in users.links"
                        :key="key"
                        :is="link.url ? 'Link' : 'span'"
                        :href="link.url || '#'"
                        :class="['page-link', { 'page-link--active': link.active, 'page-link--disabled': !link.url }]"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>

        <!-- Add / Edit User Modal -->
        <UserModal
            :show="userModalShow"
            :edit-user="editingUser"
            @close="userModalShow = false"
        />

        <!-- Confirm Modal -->
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
.topbar-filter-group {
    display: flex;
    align-items: center;
    gap: 12px;
}
.search-input-wrap {
    position: relative;
    display: flex;
    align-items: center;
}
.search-icon {
    position: absolute;
    left: 12px;
    color: #94a3b8;
}
.search-input {
    height: 38px;
    padding: 0 14px 0 34px;
    border-radius: 10px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    font-size: 13px;
    color: #0f172a;
    width: 220px;
    outline: none;
    transition: all 0.2s ease;
}
.search-input:focus {
    border-color: #0d9488;
    box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.12);
    width: 260px;
}
.btn-primary-add {
    height: 38px;
    padding: 0 16px;
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
.btn-primary-add:hover {
    opacity: 0.95;
    box-shadow: 0 6px 16px rgba(13, 148, 136, 0.3);
}

/* Stat Grid */
.stat-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}
.stat-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 18px;
    display: flex;
    align-items: flex-start;
    gap: 14px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
}
.stat-card__icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.stat-card__icon--blue { background: #e0f2fe; color: #0284c7; }
.stat-card__icon--teal { background: #ccfbf1; color: #0d9488; }
.stat-card__icon--violet { background: #f3e8ff; color: #9333ea; }
.stat-card__icon--green { background: #dcfce7; color: #16a34a; }

.stat-card__num {
    font-size: 24px;
    font-weight: 800;
    color: #0f172a;
    margin: 0;
    line-height: 1;
}
.stat-card__label {
    font-size: 12.5px;
    font-weight: 600;
    color: #475569;
    margin: 4px 0 2px;
}
.stat-card__sub {
    font-size: 11px;
    color: #94a3b8;
    margin: 0;
}

/* Table Card */
.table-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    overflow: hidden;
}
.filter-bar {
    padding: 16px 20px;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
}
.filter-group {
    display: flex;
    align-items: center;
    gap: 6px;
}
.filter-label {
    font-size: 11px;
    font-weight: 700;
    color: #94a3b8;
    letter-spacing: 0.5px;
    margin-right: 4px;
}
.filter-pill {
    padding: 5px 12px;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    cursor: pointer;
    transition: all 0.2s ease;
}
.filter-pill:hover {
    border-color: #cbd5e1;
    color: #0f172a;
}
.filter-pill.active {
    border-color: #0d9488;
    background: #f0fdfa;
    color: #0d9488;
}

/* Table */
.table-wrap {
    overflow-x: auto;
}
.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    text-align: left;
}
.data-table th {
    padding: 12px 20px;
    background: #f8fafc;
    color: #64748b;
    font-weight: 700;
    font-size: 11px;
    letter-spacing: 0.5px;
    border-bottom: 1px solid #e2e8f0;
}
.data-table td {
    padding: 14px 20px;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
    vertical-align: middle;
}
.data-table tr:hover td {
    background: #f8fafc;
}
.table-empty {
    text-align: center;
    padding: 40px !important;
    color: #94a3b8;
    font-size: 14px;
}
.table-empty svg {
    display: block;
    margin: 0 auto 10px;
    color: #cbd5e1;
}

/* User cell */
.user-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}
.user-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 12.5px;
    flex-shrink: 0;
}
.user-avatar--admin {
    background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);
    color: #ffffff;
}
.user-avatar--user {
    background: #e0f2fe;
    color: #0284c7;
}
.user-name {
    font-weight: 700;
    color: #0f172a;
    margin: 0;
}
.self-tag {
    font-size: 11px;
    font-weight: 600;
    color: #0d9488;
    margin-left: 4px;
}
.email-text {
    color: #475569;
    font-family: monospace;
    font-size: 12.5px;
}

/* Role badge */
.role-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11.5px;
    font-weight: 600;
}
.role-badge--admin {
    background: #ccfbf1;
    color: #0f766e;
}
.role-badge--user {
    background: #f1f5f9;
    color: #475569;
}

/* Status switch */
.status-toggle {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 4px 10px 4px 6px;
    border-radius: 20px;
    background: #f1f5f9;
    cursor: pointer;
    transition: all 0.2s ease;
}
.status-toggle--active {
    background: #dcfce7;
}
.status-knob {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #94a3b8;
    transition: all 0.2s ease;
}
.status-toggle--active .status-knob {
    background: #16a34a;
}
.status-text {
    font-size: 11.5px;
    font-weight: 600;
    color: #475569;
}
.status-toggle--active .status-text {
    color: #15803d;
}
.status-toggle--disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.col-date {
    font-size: 12.5px;
    color: #64748b;
}

/* Actions */
.action-group {
    display: flex;
    align-items: center;
    gap: 6px;
}
.action-btn {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    color: #64748b;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
}
.action-btn:hover:not(:disabled) {
    border-color: #0d9488;
    color: #0d9488;
    background: #f0fdfa;
}
.action-btn--danger:hover:not(:disabled) {
    border-color: #ef4444;
    color: #ef4444;
    background: #fef2f2;
}
.action-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

/* Pagination */
.pagination-bar {
    padding: 14px 20px;
    border-top: 1px solid #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.pagination-info {
    font-size: 12.5px;
    color: #64748b;
    margin: 0;
}
.pagination-links {
    display: flex;
    gap: 4px;
}
.page-link {
    padding: 6px 12px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    font-size: 12px;
    font-weight: 600;
    color: #475569;
    text-decoration: none;
    transition: all 0.2s ease;
}
.page-link:hover:not(.page-link--disabled) {
    border-color: #0d9488;
    color: #0d9488;
}
.page-link--active {
    border-color: #0d9488;
    background: #0d9488;
    color: #ffffff !important;
}
.page-link--disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

@media (max-width: 1024px) {
    .stat-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>
