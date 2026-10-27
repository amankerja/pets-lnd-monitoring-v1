<script setup>
import { ref, reactive, computed, watch, onBeforeUnmount } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    competencies: {
        type: Object,
        required: true,
    },
    kpi: {
        type: Object,
        default: () => ({
            total: 0,
            external_mandatory: 0,
            internal_mandatory: 0,
            non_mandatory: 0,
        }),
    },
    categories: {
        type: Array,
        default: () => [
            'Safety',
            'Operation',
            'Plant',
            'Pengawas',
            'Engineering',
            'Leadership',
            'General',
        ],
    },
    competencyTypes: {
        type: Array,
        default: () => [
            {
                value: 'EXTERNAL_MANDATORY',
                label: 'EXTERNAL - MANDATORY (Sertifikasi Lembaga Luar & Wajib)',
                short_label: 'EXTERNAL - MANDATORY',
            },
            {
                value: 'INTERNAL_MANDATORY',
                label: 'INTERNAL - MANDATORY (Pelatihan Internal Perusahaan & Wajib)',
                short_label: 'INTERNAL - MANDATORY',
            },
            {
                value: 'NON_MANDATORY',
                label: 'NON-MANDATORY (Pelatihan Opsional / Pilihan)',
                short_label: 'NON-MANDATORY',
            },
        ],
    },
    filters: {
        type: Object,
        default: () => ({
            search: '',
            competency_type: '',
            category: '',
        }),
    },
    canManage: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const isLndAdmin = computed(() => {
    const role = page.props.auth?.user?.role || 'lnd';
    return props.canManage || ['lnd', 'admin'].includes(role);
});

// ============================================================================
// Global Filter Bar State (Server-Side Inertia router.get dengan Debounce 350ms)
// ============================================================================
const filterState = reactive({
    search: props.filters?.search || '',
    competency_type: props.filters?.competency_type || '',
    category: props.filters?.category || '',
});

watch(
    () => props.filters,
    (val) => {
        if (!val) return;
        filterState.search = val.search ?? '';
        filterState.competency_type = val.competency_type ?? '';
        filterState.category = val.category ?? '';
    },
    { deep: true }
);

const hasActiveFilter = computed(() => {
    return Boolean(filterState.search || filterState.competency_type || filterState.category);
});

let searchDebounce = null;

const applyServerFilter = () => {
    const payload = {};
    if (filterState.search) payload.search = filterState.search;
    if (filterState.competency_type) payload.competency_type = filterState.competency_type;
    if (filterState.category) payload.category = filterState.category;

    router.get('/competency/list', payload, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const onSearchInput = () => {
    if (searchDebounce) clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => {
        applyServerFilter();
    }, 350);
};

const resetFilters = () => {
    if (searchDebounce) clearTimeout(searchDebounce);
    filterState.search = '';
    filterState.competency_type = '';
    filterState.category = '';
    router.get('/competency/list', {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

onBeforeUnmount(() => {
    if (searchDebounce) clearTimeout(searchDebounce);
});

// ============================================================================
// Modal Form Tambah / Edit Master Kompetensi
// ============================================================================
const showFormModal = ref(false);
const isEditMode = ref(false);
const editingId = ref(null);

const form = useForm({
    code: '',
    name: '',
    category: 'Safety',
    competency_type: 'INTERNAL_MANDATORY',
    description: '',
});

const openCreateModal = () => {
    isEditMode.value = false;
    editingId.value = null;
    form.reset();
    form.clearErrors();
    form.category = 'Safety';
    form.competency_type = 'INTERNAL_MANDATORY';
    showFormModal.value = true;
};

const openEditModal = (item) => {
    isEditMode.value = true;
    editingId.value = item.id;
    form.clearErrors();
    form.code = item.code || '';
    form.name = item.name || '';
    form.category = item.category || 'Safety';
    form.competency_type = item.competency_type || 'INTERNAL_MANDATORY';
    form.description = item.description || '';
    showFormModal.value = true;
};

const closeFormModal = () => {
    showFormModal.value = false;
    editingId.value = null;
    form.clearErrors();
};

const onCodeInput = (e) => {
    form.code = (e.target.value || '').toUpperCase();
};

const submitForm = () => {
    form.code = (form.code || '').trim().toUpperCase();

    if (isEditMode.value && editingId.value) {
        form.put(`/competency/list/${editingId.value}`, {
            preserveScroll: true,
            onSuccess: () => closeFormModal(),
        });
    } else {
        form.post('/competency/list', {
            preserveScroll: true,
            onSuccess: () => closeFormModal(),
        });
    }
};

// ============================================================================
// Hapus Master Kompetensi (dengan Pengecekan Ikatan Aktif)
// ============================================================================
const confirmDelete = (item) => {
    if (item.positions_count > 0 || item.total_workers_count > 0 || item.courses_count > 0) {
        const msg = `Kompetensi [${item.code}] sedang terikat aktif pada ${item.positions_count} Posisi Jabatan, ${item.total_workers_count} Pekerja di Skill Matrix, dan ${item.courses_count} Program Training. Tetap coba hapus?`;
        if (!window.confirm(msg)) return;
    } else {
        if (!window.confirm(`Apakah Anda yakin ingin menghapus Master Kompetensi [${item.code}] ${item.name}?`)) {
            return;
        }
    }

    router.delete(`/competency/list/${item.id}`, {
        preserveScroll: true,
    });
};

// Helper Badge Kategori / Bidang
const getCategoryBadgeClass = (cat) => {
    switch (cat) {
        case 'Safety':
            return 'bg-blue-50 text-blue-700 border-blue-200';
        case 'Operation':
            return 'bg-sky-50 text-sky-700 border-sky-200';
        case 'Plant':
            return 'bg-amber-50 text-amber-800 border-amber-200';
        case 'Pengawas':
            return 'bg-purple-50 text-purple-700 border-purple-200';
        case 'Engineering':
            return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        default:
            return 'bg-slate-100 text-slate-700 border-slate-200';
    }
};
</script>

<template>
    <Head title="Master Data Daftar Kompetensi (Competency List)" />

    <AdminLayout
        title="Master Data Daftar Kompetensi (Competency List)"
        subtitle="Acuan dasar pusat kompetensi yang direlasikan ke Training Matrix, Level x Position, dan Program Pelatihan"
    >
        <div class="space-y-4">
            <!-- 1. Header & Tombol Aksi Kanan Atas -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
                <div>
                    <h1 class="text-base font-bold text-slate-800">
                        Master Data Daftar Kompetensi (Competency List)
                    </h1>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Standarisasi kode kompetensi, bidang keahlian, dan klasifikasi kewajiban (External Mandatory, Internal Mandatory, Non-Mandatory)
                    </p>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <Link
                        href="/admin/skill-matrix"
                        class="px-3 py-2 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold transition-colors"
                    >
                        Lihat Training Matrix
                    </Link>
                    <button
                        v-if="isLndAdmin"
                        type="button"
                        @click="openCreateModal"
                        class="px-3.5 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold shadow-sm transition-colors"
                    >
                        + Tambah Kompetensi Baru
                    </button>
                </div>
            </div>

            <!-- Ringkasan 4 Kartu KPI Master Kompetensi -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="bg-white border border-slate-200 rounded-xl p-3.5 shadow-sm">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                        Total Master Kompetensi
                    </p>
                    <p class="text-xl font-bold text-slate-800 mt-1">{{ kpi.total }}</p>
                </div>

                <div class="bg-indigo-50/70 border border-indigo-200 rounded-xl p-3.5 shadow-sm">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-indigo-800">
                        External - Mandatory
                    </p>
                    <p class="text-xl font-bold text-indigo-950 mt-1">{{ kpi.external_mandatory }}</p>
                </div>

                <div class="bg-slate-100/90 border border-slate-300 rounded-xl p-3.5 shadow-sm">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-700">
                        Internal - Mandatory
                    </p>
                    <p class="text-xl font-bold text-slate-900 mt-1">{{ kpi.internal_mandatory }}</p>
                </div>

                <div class="bg-slate-50 border border-slate-200 rounded-xl p-3.5 shadow-sm">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                        Non-Mandatory (Opsional)
                    </p>
                    <p class="text-xl font-bold text-slate-700 mt-1">{{ kpi.non_mandatory }}</p>
                </div>
            </div>

            <!-- 2. Global Filter Bar di Atas Tabel -->
            <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-end">
                    <!-- Search Input (Cari Kode atau Nama Kompetensi) -->
                    <div class="sm:col-span-2 lg:col-span-5">
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">
                            Cari Kode atau Nama Kompetensi
                        </label>
                        <div class="relative">
                            <input
                                v-model="filterState.search"
                                @input="onSearchInput"
                                type="search"
                                placeholder="Ketik kode (misal: SFT-K3-01) atau nama kompetensi..."
                                class="w-full rounded-lg border-slate-300 text-xs py-1.5 pl-8 pr-3 focus:border-blue-600 focus:ring-blue-600"
                            />
                            <svg
                                class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M21 21l-4.35-4.35m1.85-5.15a7 7 0 11-14 0 7 7 0 0114 0z"
                                />
                            </svg>
                        </div>
                    </div>

                    <!-- Filter Dropdown Jenis Kompetensi -->
                    <div class="lg:col-span-3">
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">
                            Jenis Kompetensi (Sifat &amp; Sumber)
                        </label>
                        <select
                            v-model="filterState.competency_type"
                            @change="applyServerFilter"
                            class="w-full rounded-lg border-slate-300 text-xs py-1.5 px-2.5 focus:border-blue-600 focus:ring-blue-600 font-medium"
                        >
                            <option value="">Semua Jenis Kompetensi</option>
                            <option value="EXTERNAL_MANDATORY">External - Mandatory</option>
                            <option value="INTERNAL_MANDATORY">Internal - Mandatory</option>
                            <option value="NON_MANDATORY">Non-Mandatory</option>
                        </select>
                    </div>

                    <!-- Filter Dropdown Bidang / Kategori -->
                    <div class="lg:col-span-3">
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">
                            Bidang / Kategori
                        </label>
                        <select
                            v-model="filterState.category"
                            @change="applyServerFilter"
                            class="w-full rounded-lg border-slate-300 text-xs py-1.5 px-2.5 focus:border-blue-600 focus:ring-blue-600 font-medium"
                        >
                            <option value="">Semua Bidang / Kategori</option>
                            <option v-for="cat in categories" :key="cat" :value="cat">
                                {{ cat }}
                            </option>
                        </select>
                    </div>

                    <!-- Tombol Reset Filter -->
                    <div class="lg:col-span-1 flex justify-end">
                        <button
                            v-if="hasActiveFilter"
                            type="button"
                            @click="resetFilters"
                            class="w-full px-2.5 py-1.5 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-xs font-bold transition-colors"
                        >
                            Reset
                        </button>
                    </div>
                </div>
            </div>

            <!-- 3. Tabel Master Kompetensi (Flat Table Bersih) -->
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                                <th class="p-3.5 w-36">Kode Kompetensi</th>
                                <th class="p-3.5">Nama Kompetensi &amp; Deskripsi</th>
                                <th class="p-3.5 w-36">Bidang / Kategori</th>
                                <th class="p-3.5 w-52">Jenis Kompetensi</th>
                                <th class="p-3.5 w-64">Statistik Penggunaan &amp; Kepatuhan</th>
                                <th class="p-3.5 w-32 text-right">Aksi</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                            <tr
                                v-for="item in competencies.data"
                                :key="item.id"
                                class="hover:bg-slate-50/80 transition-colors"
                            >
                                <!-- Kolom 1: Kode Kompetensi (Font Monospace, tebal) -->
                                <td class="p-3.5 align-top">
                                    <span class="inline-block font-mono text-xs font-extrabold text-blue-800 bg-blue-50 border border-blue-200 px-2.5 py-1 rounded-md">
                                        {{ item.code }}
                                    </span>
                                </td>

                                <!-- Kolom 2: Nama Kompetensi & Deskripsi -->
                                <td class="p-3.5 align-top">
                                    <div class="font-bold text-slate-900 text-xs sm:text-sm">
                                        {{ item.name }}
                                    </div>
                                    <p v-if="item.description" class="text-[11px] text-slate-500 mt-0.5 leading-relaxed">
                                        {{ item.description }}
                                    </p>
                                    <p v-else class="text-[11px] text-slate-400 italic mt-0.5">
                                        Standar kompetensi operasional &amp; teknis perusahaan.
                                    </p>

                                    <!-- Daftar Course yang terhubung ke Kompetensi ini -->
                                    <div v-if="item.linked_courses?.length" class="flex flex-wrap items-center gap-1.5 mt-2">
                                        <span class="text-[10px] font-semibold text-slate-400">Training Terkait:</span>
                                        <span
                                            v-for="crs in item.linked_courses"
                                            :key="crs.id"
                                            class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-700 border border-slate-200 font-mono text-[10px] font-semibold"
                                            :title="crs.title"
                                        >
                                            {{ crs.code }}
                                        </span>
                                    </div>
                                </td>

                                <!-- Kolom 3: Bidang / Kategori -->
                                <td class="p-3.5 align-top">
                                    <span
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-md text-[11px] font-semibold border"
                                        :class="getCategoryBadgeClass(item.category)"
                                    >
                                        {{ item.category }}
                                    </span>
                                </td>

                                <!-- Kolom 4: Jenis Kompetensi (3 Badge Khusus) -->
                                <td class="p-3.5 align-top">
                                    <!-- EXTERNAL - MANDATORY (Warna Ungu/Biru Tua) -->
                                    <span
                                        v-if="item.competency_type === 'EXTERNAL_MANDATORY'"
                                        class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-extrabold tracking-wide bg-indigo-900 text-white border border-indigo-950 shadow-sm"
                                    >
                                        EXTERNAL - MANDATORY
                                    </span>

                                    <!-- INTERNAL - MANDATORY (Warna Hitam/Slate-900) -->
                                    <span
                                        v-else-if="item.competency_type === 'INTERNAL_MANDATORY'"
                                        class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-extrabold tracking-wide bg-slate-900 text-white border border-slate-950 shadow-sm"
                                    >
                                        INTERNAL - MANDATORY
                                    </span>

                                    <!-- NON-MANDATORY (Warna Abu-abu terang) -->
                                    <span
                                        v-else
                                        class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold tracking-wide bg-slate-100 text-slate-600 border border-slate-300"
                                    >
                                        NON-MANDATORY
                                    </span>
                                </td>

                                <!-- Kolom 5: Statistik Penggunaan (Level x Position & Kepatuhan Pekerja) -->
                                <td class="p-3.5 align-top space-y-1.5">
                                    <div class="flex items-center justify-between text-[11px]">
                                        <span class="text-slate-500">Standar Posisi (`Level x Pos`):</span>
                                        <span class="font-bold text-slate-800 bg-slate-100 px-2 py-0.5 rounded">
                                            {{ item.positions_count }} Posisi
                                        </span>
                                    </div>

                                    <div class="flex items-center justify-between text-[11px]">
                                        <span class="text-slate-500">Total Karyawan Terdaftar:</span>
                                        <span class="font-bold text-slate-800">
                                            {{ item.total_workers_count }} Orang
                                        </span>
                                    </div>

                                    <div class="flex items-center gap-1.5 pt-0.5">
                                        <span class="px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-bold">
                                            &#10003; {{ item.competent_workers_count }} Kompeten
                                        </span>
                                        <span class="px-2 py-0.5 rounded bg-rose-50 text-rose-700 border border-rose-200 text-[10px] font-bold">
                                            ! {{ item.gap_workers_count }} Defisit Gap
                                        </span>
                                    </div>
                                </td>

                                <!-- Kolom 6: Aksi (Edit & Hapus khusus Tim LnD) -->
                                <td class="p-3.5 align-top text-right whitespace-nowrap">
                                    <div v-if="isLndAdmin" class="inline-flex items-center gap-1.5">
                                        <button
                                            type="button"
                                            @click="openEditModal(item)"
                                            class="px-2.5 py-1 rounded-md bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 text-xs font-semibold transition-colors"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            type="button"
                                            @click="confirmDelete(item)"
                                            class="px-2.5 py-1 rounded-md bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-xs font-semibold transition-colors"
                                        >
                                            Hapus
                                        </button>
                                    </div>
                                    <span
                                        v-else
                                        class="px-2 py-0.5 rounded bg-slate-100 text-slate-500 text-[10px] font-semibold"
                                    >
                                        Read-Only
                                    </span>
                                </td>
                            </tr>

                            <tr v-if="!competencies.data?.length">
                                <td colspan="6" class="py-10 text-center text-slate-400">
                                    Tidak ada data master kompetensi yang sesuai dengan filter pencarian.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer (25 Item per Halaman) -->
                <div
                    v-if="competencies.links?.length > 3"
                    class="px-4 py-3 bg-slate-50 border-t border-slate-200 flex flex-wrap items-center justify-between gap-2 text-xs"
                >
                    <div class="text-slate-500">
                        Menampilkan <span class="font-semibold text-slate-800">{{ competencies.from }}</span> -
                        <span class="font-semibold text-slate-800">{{ competencies.to }}</span> dari
                        <span class="font-semibold text-slate-800">{{ competencies.total }}</span> kompetensi
                    </div>

                    <div class="flex items-center gap-1">
                        <template v-for="(link, idx) in competencies.links" :key="idx">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                preserve-scroll
                                preserve-state
                                v-html="link.label"
                                :class="[
                                    'px-2.5 py-1 rounded border text-xs font-medium',
                                    link.active
                                        ? 'bg-blue-600 text-white border-blue-600'
                                        : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100',
                                ]"
                            />
                            <span
                                v-else
                                v-html="link.label"
                                class="px-2.5 py-1 rounded border border-slate-200 text-slate-300 text-xs"
                            />
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================================================================ -->
        <!-- 4. MODAL FORM TAMBAH / EDIT MASTER KOMPETENSI                    -->
        <!-- ================================================================ -->
        <div
            v-if="showFormModal"
            class="fixed inset-0 z-50 bg-slate-900/50 flex items-center justify-center p-4"
            @click.self="closeFormModal"
        >
            <div class="bg-white border border-slate-200 rounded-xl w-full max-w-lg shadow-xl overflow-hidden">
                <div class="px-5 py-3.5 bg-blue-50 border-b border-blue-100 flex items-center justify-between">
                    <div>
                        <div class="text-[10px] font-bold uppercase tracking-wider text-blue-600">
                            {{ isEditMode ? 'Edit Master Kompetensi' : 'Tambah Master Kompetensi Baru' }}
                        </div>
                        <h3 class="text-sm font-bold text-slate-900">
                            {{ isEditMode ? `Perbarui Kompetensi [${form.code}]` : 'Form Standar Kompetensi Perusahaan' }}
                        </h3>
                    </div>
                    <button
                        type="button"
                        @click="closeFormModal"
                        class="text-slate-400 hover:text-slate-700 font-bold px-2"
                    >
                        &#10005;
                    </button>
                </div>

                <form @submit.prevent="submitForm" class="p-5 space-y-4">
                    <!-- Baris 1: Kode Kompetensi (Auto-Uppercase) & Kategori / Bidang -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">
                                Kode Kompetensi <span class="text-rose-600">*</span>
                            </label>
                            <input
                                :value="form.code"
                                @input="onCodeInput"
                                type="text"
                                required
                                maxlength="30"
                                placeholder="Contoh: SFT-K3-01"
                                class="w-full rounded-lg border-slate-300 font-mono uppercase text-xs py-2 px-3 focus:border-blue-600 focus:ring-blue-600"
                            />
                            <p v-if="form.errors.code" class="text-[11px] text-rose-600 mt-1">
                                {{ form.errors.code }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">
                                Bidang / Kategori <span class="text-rose-600">*</span>
                            </label>
                            <select
                                v-model="form.category"
                                required
                                class="w-full rounded-lg border-slate-300 text-xs py-2 px-3 focus:border-blue-600 focus:ring-blue-600"
                            >
                                <option v-for="cat in categories" :key="cat" :value="cat">
                                    {{ cat }}
                                </option>
                            </select>
                            <p v-if="form.errors.category" class="text-[11px] text-rose-600 mt-1">
                                {{ form.errors.category }}
                            </p>
                        </div>
                    </div>

                    <!-- Baris 2: Nama Kompetensi -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">
                            Nama Kompetensi <span class="text-rose-600">*</span>
                        </label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            maxlength="255"
                            placeholder="Contoh: Keselamatan & Kesehatan Kerja Umum (K3)"
                            class="w-full rounded-lg border-slate-300 text-xs py-2 px-3 focus:border-blue-600 focus:ring-blue-600"
                        />
                        <p v-if="form.errors.name" class="text-[11px] text-rose-600 mt-1">
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <!-- Baris 3: Jenis Kompetensi (3 Opsi Baku) -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">
                            Jenis Kompetensi (Sifat &amp; Sumber) <span class="text-rose-600">*</span>
                        </label>
                        <select
                            v-model="form.competency_type"
                            required
                            class="w-full rounded-lg border-slate-300 text-xs py-2 px-3 focus:border-blue-600 focus:ring-blue-600 font-semibold"
                        >
                            <option
                                v-for="t in competencyTypes"
                                :key="t.value"
                                :value="t.value"
                            >
                                {{ t.label }}
                            </option>
                        </select>
                        <p v-if="form.errors.competency_type" class="text-[11px] text-rose-600 mt-1">
                            {{ form.errors.competency_type }}
                        </p>
                    </div>

                    <!-- Baris 4: Deskripsi / Standar Kompetensi -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">
                            Deskripsi / Acuan Standar Kompetensi
                        </label>
                        <textarea
                            v-model="form.description"
                            rows="3"
                            placeholder="Jelaskan ruang lingkup keahlian, regulasi acuan (BNSP/Kemnaker/SOP Internal), atau kriteria penilaian..."
                            class="w-full rounded-lg border-slate-300 text-xs py-2 px-3 focus:border-blue-600 focus:ring-blue-600"
                        ></textarea>
                        <p v-if="form.errors.description" class="text-[11px] text-rose-600 mt-1">
                            {{ form.errors.description }}
                        </p>
                    </div>

                    <!-- Tombol Batal & Simpan -->
                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200">
                        <button
                            type="button"
                            @click="closeFormModal"
                            class="px-3.5 py-2 rounded-lg border border-slate-300 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold shadow-sm disabled:opacity-50"
                        >
                            {{ form.processing ? 'Menyimpan...' : isEditMode ? 'Simpan Perubahan' : 'Simpan Kompetensi' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>

