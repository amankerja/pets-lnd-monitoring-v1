<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    todos: {
        type: Array,
        default: () => [],
    },
    trainers: {
        type: Array,
        default: () => [],
    },
    summary: {
        type: Object,
        default: () => ({
            total: 0,
            completed: 0,
            in_progress: 0,
            pending: 0,
            overdue: 0,
            urgent: 0,
            completion_rate: 0,
        }),
    },
    filters: {
        type: Object,
        default: () => ({
            search: '',
            status: 'all',
            task_type: 'all',
            priority: 'all',
            trainer_id: '',
        }),
    },
    canManage: {
        type: Boolean,
        default: false,
    },
    userRole: {
        type: String,
        default: 'lnd',
    },
    currentTrainerId: {
        type: Number,
        default: null,
    },
});

// State Filter
const searchQuery = ref(props.filters.search || '');
const selectedStatus = ref(props.filters.status || 'all');
const selectedType = ref(props.filters.task_type || 'all');
const selectedPriority = ref(props.filters.priority || 'all');
const selectedTrainerId = ref(props.filters.trainer_id || '');

let searchDebounceTimer = null;
const onSearchInput = () => {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => {
        applyFilters();
    }, 400);
};

const applyFilters = () => {
    router.get(
        '/trainer/todos',
        {
            search: searchQuery.value || undefined,
            status: selectedStatus.value !== 'all' ? selectedStatus.value : undefined,
            task_type: selectedType.value !== 'all' ? selectedType.value : undefined,
            priority: selectedPriority.value !== 'all' ? selectedPriority.value : undefined,
            trainer_id: selectedTrainerId.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true }
    );
};

// Modal Buat Tugas / Perintah Baru (Admin L&D)
const showCreateModal = ref(false);
const createForm = useForm({
    trainer_id: props.trainers[0]?.id || '',
    title: '',
    task_type: 'perintah',
    priority: 'medium',
    start_date: new Date().toISOString().substring(0, 10),
    due_date: new Date(Date.now() + 3 * 86400000).toISOString().substring(0, 10),
    description: '',
    status: 'in_progress',
});

const openCreateModal = () => {
    createForm.reset();
    createForm.trainer_id = props.trainers[0]?.id || '';
    createForm.start_date = new Date().toISOString().substring(0, 10);
    createForm.due_date = new Date(Date.now() + 3 * 86400000).toISOString().substring(0, 10);
    showCreateModal.value = true;
};

const submitCreateTodo = () => {
    createForm.post('/trainer/todos', {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset();
        },
    });
};

// Modal Edit / Update Catatan & Status (Trainer / Admin)
const showEditModal = ref(false);
const selectedTodoForEdit = ref(null);
const editForm = useForm({
    id: null,
    title: '',
    task_type: 'task',
    priority: 'medium',
    start_date: '',
    due_date: '',
    description: '',
    trainer_notes: '',
    status: 'in_progress',
});

const openEditModal = (todo) => {
    selectedTodoForEdit.value = todo;
    editForm.id = todo.id;
    editForm.title = todo.title;
    editForm.task_type = todo.task_type;
    editForm.priority = todo.priority;
    editForm.start_date = todo.start_date || '';
    editForm.due_date = todo.due_date || '';
    editForm.description = todo.description || '';
    editForm.trainer_notes = todo.trainer_notes || '';
    editForm.status = todo.status;
    showEditModal.value = true;
};

const submitUpdateTodo = () => {
    if (!editForm.id) return;
    editForm.put(`/trainer/todos/${editForm.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false;
            selectedTodoForEdit.value = null;
        },
    });
};

// Quick Toggle Selesai / Belum Selesai (Checkbox)
const toggleTodoStatus = (todo) => {
    router.patch(
        `/trainer/todos/${todo.id}/toggle`,
        {},
        { preserveScroll: true }
    );
};

// Hapus Tugas (Admin Only)
const deleteTodo = (todo) => {
    if (confirm(`Apakah Anda yakin ingin menghapus tugas "${todo.title}"?`)) {
        router.delete(`/trainer/todos/${todo.id}`, { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Trainer To-Do - Penugasan & Checklist Instruktur" />

    <AdminLayout
        title="Trainer To-Do: Perintah, Tugas &amp; Jadwal"
        subtitle="Manajemen penugasan operasional instruktur L&D, instruksi kerja, tanggal batas akhir (due date), verifikasi selesai, dan catatan tindak lanjut."
    >
        <div class="space-y-6 pb-12">
            <!-- ============================================================= -->
            <!-- 1. HEADER BAR: ROLE INDICATOR & ACTION CTA                    -->
            <!-- ============================================================= -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white dark:bg-slate-900 p-4 sm:p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm">
                <div>
                    <div class="flex items-center gap-2">
                        <span :class="[
                            'inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-black uppercase tracking-wider',
                            canManage ? 'bg-purple-50 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300 border border-purple-200' : 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300 border border-blue-200'
                        ]">
                            <span class="w-1.5 h-1.5 rounded-full" :class="canManage ? 'bg-purple-500' : 'bg-blue-500'"></span>
                            {{ canManage ? 'Mode Admin L&D: Kelola & Berikan Tugas' : 'Mode Instruktur: Daftar Tugas Saya' }}
                        </span>
                    </div>
                    <h2 class="text-base font-black text-slate-900 dark:text-white mt-1">
                        {{ canManage ? 'Command & Task Assignment Center' : 'Daftar Checklist & Tugas Mandiri' }}
                    </h2>
                </div>

                <div class="flex items-center gap-2.5">
                    <button
                        v-if="canManage"
                        type="button"
                        @click="openCreateModal"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-xl text-xs font-black shadow-md shadow-blue-500/20 active:scale-[0.98] transition-all"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>+ Berikan Perintah / Tugas Baru</span>
                    </button>
                </div>
            </div>

            <!-- ============================================================= -->
            <!-- 2. SUMMARY KPI METRICS                                        -->
            <!-- ============================================================= -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3.5">
                <!-- Card 1: Total Tugas -->
                <div class="rounded-xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm">
                    <div class="flex items-center justify-between text-slate-500">
                        <span class="text-[11px] font-bold uppercase tracking-wider">Total Tugas</span>
                        <div class="w-7 h-7 rounded-lg bg-blue-50 dark:bg-blue-900/30 text-blue-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                        </div>
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-black text-slate-900 dark:text-white">{{ summary.total }}</span>
                        <span class="text-xs text-slate-500">Item</span>
                    </div>
                </div>

                <!-- Card 2: Sedang Dikerjakan -->
                <div class="rounded-xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm">
                    <div class="flex items-center justify-between text-slate-500">
                        <span class="text-[11px] font-bold uppercase tracking-wider">In Progress</span>
                        <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </div>
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-black text-indigo-600 dark:text-indigo-400">{{ summary.in_progress }}</span>
                        <span class="text-xs text-slate-500">Berjalan</span>
                    </div>
                </div>

                <!-- Card 3: Selesai -->
                <div class="rounded-xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm">
                    <div class="flex items-center justify-between text-slate-500">
                        <span class="text-[11px] font-bold uppercase tracking-wider">Selesai</span>
                        <div class="w-7 h-7 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        </div>
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ summary.completed }}</span>
                        <span class="text-xs font-bold text-emerald-700 bg-emerald-50 dark:bg-emerald-900/30 px-1.5 py-0.5 rounded">
                            {{ summary.completion_rate }}%
                        </span>
                    </div>
                </div>

                <!-- Card 4: Terlambat / Overdue -->
                <div class="rounded-xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm">
                    <div class="flex items-center justify-between text-slate-500">
                        <span class="text-[11px] font-bold uppercase tracking-wider">Terlambat (Overdue)</span>
                        <div class="w-7 h-7 rounded-lg bg-red-50 dark:bg-red-900/30 text-red-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                        </div>
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span :class="['text-2xl font-black', summary.overdue > 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-800 dark:text-slate-200']">
                            {{ summary.overdue }}
                        </span>
                        <span class="text-xs text-slate-500">Melewati Due Date</span>
                    </div>
                </div>
            </div>

            <!-- ============================================================= -->
            <!-- 3. FILTER TOOLBAR                                             -->
            <!-- ============================================================= -->
            <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3 bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm">
                <!-- Search & Filters -->
                <div class="flex-1 flex flex-wrap items-center gap-2.5">
                    <div class="relative flex-1 min-w-[200px]">
                        <svg class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input
                            v-model="searchQuery"
                            @input="onSearchInput"
                            type="text"
                            placeholder="Cari tugas, keterangan, atau trainer..."
                            class="w-full pl-9 pr-3 py-1.5 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:outline-none"
                        />
                    </div>

                    <!-- Tipe Penugasan -->
                    <select
                        v-model="selectedType"
                        @change="applyFilters"
                        class="px-3 py-1.5 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-200"
                    >
                        <option value="all">Semua Tipe Tugas</option>
                        <option value="perintah">Perintah / Instruksi</option>
                        <option value="tugas">Tugas L&D</option>
                        <option value="jadwal">Jadwal Mengajar</option>
                        <option value="checklist">Checklist K3 / SOP</option>
                    </select>

                    <!-- Tingkat Prioritas -->
                    <select
                        v-model="selectedPriority"
                        @change="applyFilters"
                        class="px-3 py-1.5 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-200"
                    >
                        <option value="all">Semua Prioritas</option>
                        <option value="urgent">Mendesak (Urgent)</option>
                        <option value="high">Tinggi (High)</option>
                        <option value="medium">Sedang (Medium)</option>
                        <option value="low">Rendah (Low)</option>
                    </select>

                    <!-- Filter Trainer (Admin Only) -->
                    <select
                        v-if="canManage"
                        v-model="selectedTrainerId"
                        @change="applyFilters"
                        class="px-3 py-1.5 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-200"
                    >
                        <option value="">Semua Trainer</option>
                        <option v-for="t in trainers" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </select>
                </div>

                <!-- Status Tabs -->
                <div class="flex items-center gap-1 p-1 bg-slate-100 dark:bg-slate-800 rounded-xl overflow-x-auto">
                    <button
                        v-for="st in [
                            { key: 'all', label: 'Semua' },
                            { key: 'in_progress', label: 'In Progress' },
                            { key: 'completed', label: 'Selesai' },
                            { key: 'overdue', label: 'Terlambat' }
                        ]"
                        :key="st.key"
                        type="button"
                        @click="selectedStatus = st.key; applyFilters();"
                        :class="[
                            'px-3 py-1 text-xs font-bold rounded-lg transition-all whitespace-nowrap',
                            selectedStatus === st.key
                                ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-sm'
                                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'
                        ]"
                    >
                        {{ st.label }}
                    </button>
                </div>
            </div>

            <!-- ============================================================= -->
            <!-- 4. DAFTAR TUGAS (TODO CARDS LIST)                             -->
            <!-- ============================================================= -->
            <div class="space-y-3">
                <div
                    v-for="todo in todos"
                    :key="todo.id"
                    :class="[
                        'group rounded-2xl border transition-all duration-200 p-4 sm:p-5 bg-white dark:bg-slate-900 shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4',
                        todo.is_completed
                            ? 'border-slate-200/60 dark:border-slate-800/60 opacity-80 bg-slate-50/50 dark:bg-slate-900/50'
                            : (todo.is_overdue
                                ? 'border-red-200 dark:border-red-900/50 hover:border-red-300'
                                : 'border-slate-200 dark:border-slate-800 hover:border-blue-300 dark:hover:border-blue-700')
                    ]"
                >
                    <!-- Left: Checkbox Toggle & Main Content -->
                    <div class="flex items-start gap-3.5 flex-1 min-w-0">
                        <!-- Checkbox Button -->
                        <button
                            type="button"
                            @click="toggleTodoStatus(todo)"
                            title="Tandai Selesai / Belum Selesai"
                            :class="[
                                'mt-0.5 w-6 h-6 rounded-lg border-2 flex items-center justify-center transition-all shrink-0',
                                todo.is_completed
                                    ? 'bg-emerald-600 border-emerald-600 text-white'
                                    : 'border-slate-300 dark:border-slate-600 hover:border-blue-600 bg-white dark:bg-slate-800'
                            ]"
                        >
                            <svg v-if="todo.is_completed" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                            </svg>
                        </button>

                        <!-- Details -->
                        <div class="min-w-0 flex-1 space-y-1.5">
                            <!-- Badges -->
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200/80 dark:border-slate-700">
                                    {{ todo.task_type_label }}
                                </span>

                                <span :class="['px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider border', todo.priority_badge.class]">
                                    {{ todo.priority_badge.label }}
                                </span>

                                <span v-if="canManage" class="text-xs font-bold text-blue-600 dark:text-blue-400 flex items-center gap-1">
                                    <span>👤 {{ todo.trainer_name }}</span>
                                    <span class="text-[10px] text-slate-400 font-mono">({{ todo.trainer_nik }})</span>
                                </span>

                                <span v-if="todo.is_overdue" class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-red-100 text-red-700 dark:bg-red-950/60 dark:text-red-400 border border-red-200">
                                    ⚠️ Terlambat
                                </span>
                            </div>

                            <!-- Title -->
                            <h4 :class="[
                                'text-sm font-black transition-all leading-snug',
                                todo.is_completed ? 'line-through text-slate-400 dark:text-slate-500' : 'text-slate-900 dark:text-white'
                            ]">
                                {{ todo.title }}
                            </h4>

                            <!-- Description / Keterangan Admin -->
                            <p v-if="todo.description" class="text-xs text-slate-600 dark:text-slate-400 whitespace-pre-line leading-relaxed">
                                {{ todo.description }}
                            </p>

                            <!-- Trainer Notes (Catatan Tindak Lanjut dari Trainer) -->
                            <div v-if="todo.trainer_notes" class="mt-2 p-2.5 rounded-xl bg-blue-50/70 dark:bg-blue-950/30 border border-blue-200/80 dark:border-blue-900/40 text-xs">
                                <div class="font-bold text-blue-700 dark:text-blue-300 flex items-center gap-1.5">
                                    <span>💬 Catatan Tindak Lanjut Trainer:</span>
                                </div>
                                <p class="text-slate-700 dark:text-slate-300 mt-0.5 whitespace-pre-line">
                                    {{ todo.trainer_notes }}
                                </p>
                            </div>

                            <!-- Date & Schedule Footprint -->
                            <div class="flex flex-wrap items-center gap-4 text-[11px] text-slate-500 pt-1">
                                <span class="flex items-center gap-1">
                                    <span>🗓️ Mulai:</span>
                                    <strong class="text-slate-700 dark:text-slate-300">{{ todo.start_date_formatted }}</strong>
                                </span>
                                <span class="flex items-center gap-1">
                                    <span>⏰ Batas Akhir:</span>
                                    <strong :class="todo.is_overdue ? 'text-red-600 dark:text-red-400 font-black' : 'text-slate-700 dark:text-slate-300'">
                                        {{ todo.due_date_formatted }}
                                    </strong>
                                </span>
                                <span v-if="todo.is_completed && todo.completed_at_formatted" class="text-emerald-600 dark:text-emerald-400 font-semibold">
                                    ✓ Diselesaikan pada {{ todo.completed_at_formatted }}
                                </span>
                                <span v-if="canManage" class="text-slate-400">
                                    Diberikan oleh: {{ todo.creator_name }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Quick Actions -->
                    <div class="flex items-center gap-2 self-end sm:self-center shrink-0">
                        <button
                            type="button"
                            @click="openEditModal(todo)"
                            class="px-3 py-1.5 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 transition flex items-center gap-1.5"
                        >
                            <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            <span>{{ canManage ? 'Edit / Setting' : 'Update Note & Status' }}</span>
                        </button>

                        <button
                            v-if="canManage"
                            type="button"
                            @click="deleteTodo(todo)"
                            class="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40 rounded-xl transition"
                            title="Hapus Tugas"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="todos.length === 0" class="py-16 text-center bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-8 shadow-sm">
                    <div class="w-12 h-12 rounded-2xl bg-blue-50 dark:bg-blue-900/30 text-blue-600 flex items-center justify-center mx-auto text-xl">
                        📋
                    </div>
                    <h3 class="mt-3 text-sm font-black text-slate-800 dark:text-white">Tidak Ada Tugas yang Sesuai</h3>
                    <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                        {{ canManage ? 'Belum ada perintah atau tugas yang diberikan dengan kriteria filter saat ini.' : 'Semua checklist dan tugas Anda sudah selesai!' }}
                    </p>
                    <button
                        v-if="canManage"
                        type="button"
                        @click="openCreateModal"
                        class="mt-4 px-4 py-2 bg-blue-600 text-white text-xs font-bold rounded-xl shadow-sm hover:bg-blue-700 transition"
                    >
                        + Berikan Tugas Baru Sekarang
                    </button>
                </div>
            </div>

            <!-- ============================================================= -->
            <!-- 5. MODAL: BERIKAN PERINTAH / TUGAS BARU (ADMIN LND)           -->
            <!-- ============================================================= -->
            <div
                v-if="showCreateModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
                @click.self="showCreateModal = false"
            >
                <div class="relative w-full max-w-lg bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 p-6 overflow-hidden">
                    <div class="flex items-center justify-between pb-3.5 border-b border-slate-100 dark:border-slate-800">
                        <div>
                            <h3 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                                <span>📋 Berikan Perintah / Tugas ke Trainer</span>
                            </h3>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Masukkan detail instruksi, jadwal, tanggal batas akhir, dan tingkat prioritas.
                            </p>
                        </div>
                        <button type="button" @click="showCreateModal = false" class="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg">✕</button>
                    </div>

                    <form @submit.prevent="submitCreateTodo" class="mt-4 space-y-3.5 text-xs">
                        <!-- Pilih Trainer -->
                        <div>
                            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">
                                Tugaskan Kepada Trainer <span class="text-red-500">*</span>
                            </label>
                            <select
                                v-model="createForm.trainer_id"
                                required
                                class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:outline-none"
                            >
                                <option value="" disabled>Pilih Trainer</option>
                                <option v-for="t in trainers" :key="t.id" :value="t.id">
                                    {{ t.name }} ({{ t.department }} - {{ t.trainer_type }})
                                </option>
                            </select>
                        </div>

                        <!-- Tipe Tugas & Prioritas -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">
                                    Jenis Penugasan <span class="text-red-500">*</span>
                                </label>
                                <select
                                    v-model="createForm.task_type"
                                    required
                                    class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                                >
                                    <option value="perintah">Perintah / Instruksi</option>
                                    <option value="tugas">Tugas L&D</option>
                                    <option value="jadwal">Jadwal Mengajar</option>
                                    <option value="checklist">Checklist K3 / SOP</option>
                                </select>
                            </div>

                            <div>
                                <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">
                                    Prioritas <span class="text-red-500">*</span>
                                </label>
                                <select
                                    v-model="createForm.priority"
                                    required
                                    class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                                >
                                    <option value="urgent">Mendesak (Urgent)</option>
                                    <option value="high">Tinggi (High)</option>
                                    <option value="medium">Sedang (Medium)</option>
                                    <option value="low">Rendah (Low)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Judul Tugas -->
                        <div>
                            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">
                                Judul Perintah / Tugas <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="createForm.title"
                                type="text"
                                placeholder="Contoh: Verifikasi Berita Acara Presensi Kelas K3 Batch 4"
                                required
                                class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                            />
                        </div>

                        <!-- Tanggal Mulai & Due Date -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Tanggal Mulai</label>
                                <input
                                    v-model="createForm.start_date"
                                    type="date"
                                    class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                                />
                            </div>
                            <div>
                                <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Batas Akhir (Due Date)</label>
                                <input
                                    v-model="createForm.due_date"
                                    type="date"
                                    class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                                />
                            </div>
                        </div>

                        <!-- Keterangan / Note Admin -->
                        <div>
                            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">
                                Keterangan / Catatan Instruksi (Admin Note)
                            </label>
                            <textarea
                                v-model="createForm.description"
                                rows="3"
                                placeholder="Tuliskan catatan teknis atau instruksi detail untuk instruktur..."
                                class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                            ></textarea>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100 dark:border-slate-800">
                            <button
                                type="button"
                                @click="showCreateModal = false"
                                class="px-4 py-2 font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                :disabled="createForm.processing"
                                class="px-5 py-2 font-black text-white bg-blue-600 hover:bg-blue-700 rounded-xl shadow-md shadow-blue-500/20 disabled:opacity-50"
                            >
                                {{ createForm.processing ? 'Menyimpan...' : 'Tugaskan Sekarang' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ============================================================= -->
            <!-- 6. MODAL: EDIT TUGAS, SETTING DUE DATE & TRAINER NOTE         -->
            <!-- ============================================================= -->
            <div
                v-if="showEditModal && selectedTodoForEdit"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
                @click.self="showEditModal = false"
            >
                <div class="relative w-full max-w-lg bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 p-6 overflow-hidden">
                    <div class="flex items-center justify-between pb-3.5 border-b border-slate-100 dark:border-slate-800">
                        <div>
                            <h3 class="text-base font-black text-slate-900 dark:text-white">
                                {{ canManage ? '⚙️ Edit Tugas & Penugasan' : '📝 Tindak Lanjut Tugas Instruktur' }}
                            </h3>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Update status pengerjaan, batas akhir (due date), dan catatan tindak lanjut.
                            </p>
                        </div>
                        <button type="button" @click="showEditModal = false" class="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg">✕</button>
                    </div>

                    <form @submit.prevent="submitUpdateTodo" class="mt-4 space-y-3.5 text-xs">
                        <!-- Judul (Admin editable) -->
                        <div v-if="canManage">
                            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Judul Tugas</label>
                            <input
                                v-model="editForm.title"
                                type="text"
                                required
                                class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                            />
                        </div>
                        <div v-else class="p-3 bg-slate-50 dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700">
                            <div class="font-black text-slate-900 dark:text-white text-sm">{{ editForm.title }}</div>
                            <div v-if="editForm.description" class="text-slate-500 mt-1">{{ editForm.description }}</div>
                        </div>

                        <!-- Status Pengerjaan -->
                        <div>
                            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">
                                Status Pengerjaan <span class="text-red-500">*</span>
                            </label>
                            <div class="grid grid-cols-3 gap-2">
                                <button
                                    type="button"
                                    @click="editForm.status = 'in_progress'"
                                    :class="[
                                        'px-3 py-2 rounded-xl font-bold border transition text-center',
                                        editForm.status === 'in_progress' ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm' : 'bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200'
                                    ]"
                                >
                                    Sedang Berjalan
                                </button>
                                <button
                                    type="button"
                                    @click="editForm.status = 'completed'"
                                    :class="[
                                        'px-3 py-2 rounded-xl font-bold border transition text-center',
                                        editForm.status === 'completed' ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm' : 'bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200'
                                    ]"
                                >
                                    ✓ Selesai
                                </button>
                                <button
                                    type="button"
                                    @click="editForm.status = 'pending'"
                                    :class="[
                                        'px-3 py-2 rounded-xl font-bold border transition text-center',
                                        editForm.status === 'pending' ? 'bg-amber-600 text-white border-amber-600 shadow-sm' : 'bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200'
                                    ]"
                                >
                                    Menunggu
                                </button>
                            </div>
                        </div>

                        <!-- Due Date Setting -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Tanggal Mulai</label>
                                <input
                                    v-model="editForm.start_date"
                                    type="date"
                                    :disabled="!canManage"
                                    class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white disabled:opacity-60"
                                />
                            </div>
                            <div>
                                <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">Batas Akhir (Due Date)</label>
                                <input
                                    v-model="editForm.due_date"
                                    type="date"
                                    class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                                />
                            </div>
                        </div>

                        <!-- Catatan / Note dari Trainer (Instruktur) -->
                        <div>
                            <label class="block font-bold text-blue-700 dark:text-blue-300 mb-1">
                                💬 Catatan / Keterangan Tindak Lanjut Trainer (Note)
                            </label>
                            <textarea
                                v-model="editForm.trainer_notes"
                                rows="3"
                                placeholder="Contoh: Sudah selesai diverifikasi per tanggal 27 Okt, dokumen berita acara sudah diserahkan ke bagian LnD."
                                class="w-full px-3 py-2 rounded-xl border border-blue-200 dark:border-blue-800 bg-blue-50/50 dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:outline-none"
                            ></textarea>
                        </div>

                        <!-- Catatan Admin (jika canManage) -->
                        <div v-if="canManage">
                            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">
                                Catatan Instruksi Admin L&D
                            </label>
                            <textarea
                                v-model="editForm.description"
                                rows="2"
                                class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white"
                            ></textarea>
                        </div>

                        <!-- Submit -->
                        <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100 dark:border-slate-800">
                            <button
                                type="button"
                                @click="showEditModal = false"
                                class="px-4 py-2 font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                :disabled="editForm.processing"
                                class="px-5 py-2 font-black text-white bg-blue-600 hover:bg-blue-700 rounded-xl shadow-md shadow-blue-500/20 disabled:opacity-50"
                            >
                                {{ editForm.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

