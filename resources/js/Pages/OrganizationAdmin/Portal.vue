<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ExpiringCertificatesAlert from '@/Components/ExpiringCertificatesAlert.vue';
import GlobalTableFilterBar from '@/Components/GlobalTableFilterBar.vue';

const props = defineProps({
    organizationInfo: {
        type: Object,
        default: () => ({
            id: null,
            code: 'DEPT-ENG',
            name: 'Departemen Engineering',
            type: 'internal_dept',
            actor_role: 'dept_admin',
        }),
    },
    workers: {
        type: Array,
        default: () => [],
    },
    workersPagination: {
        type: Object,
        default: null,
    },
    expiringList: {
        type: Array,
        default: () => [],
    },
    expiringCertificates: {
        type: Array,
        default: () => [],
    },
    summary: {
        type: Object,
        default: () => ({
            expired_count: 0,
            critical_count: 0,
            warning_count: 0,
        }),
    },
    trainingRequests: {
        type: Array,
        default: () => [],
    },
    trainingRequestsPagination: {
        type: Object,
        default: null,
    },
    activeCourses: {
        type: Array,
        default: () => [],
    },
    skills: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    filterOptions: {
        type: Object,
        default: () => ({
            departments: [],
            companies: [],
            courses: [],
            categories: [],
        }),
    },
});

const activeTab = ref('collective'); // 'collective' | 'tna' | 'certificates'
const workerSearch = ref('');

const filteredWorkers = computed(() => {
    const q = workerSearch.value.trim().toLowerCase();
    if (!q) return props.workers;
    return props.workers.filter(
        (w) =>
            w.name.toLowerCase().includes(q) ||
            w.nik.toLowerCase().includes(q) ||
            (w.department || '').toLowerCase().includes(q) ||
            (w.position || '').toLowerCase().includes(q) ||
            (w.organization_name || '').toLowerCase().includes(q)
    );
});

// Form Tab 1: Pendaftaran Kolektif Pekerja
const bulkEnrollForm = useForm({
    course_ids: [],
    participant_ids: [],
});

const toggleSelectAllBulk = () => {
    if (bulkEnrollForm.participant_ids.length === filteredWorkers.value.length) {
        bulkEnrollForm.participant_ids = [];
    } else {
        bulkEnrollForm.participant_ids = filteredWorkers.value.map((w) => w.id);
    }
};

const submitBulkEnroll = () => {
    if (!bulkEnrollForm.course_ids.length || !bulkEnrollForm.participant_ids.length) return;
    bulkEnrollForm.post('/organization/bulk-enroll', {
        preserveScroll: true,
        onSuccess: () => {
            bulkEnrollForm.reset('participant_ids', 'course_ids');
        },
    });
};

// Form Tab 2: Pengajuan TNA Baru / Pembaruan Sertifikat (Refreshment)
const tnaForm = useForm({
    request_type: 'new_tna',
    skill_id: props.skills[0]?.id || '',
    course_id: props.activeCourses[0]?.id || '',
    participant_ids: [],
    reason_notes: '',
});

const prefillExpiringRefreshment = () => {
    const expiringWorkerIds = [...new Set(props.expiringCertificates.map((c) => c.user_id))];
    tnaForm.request_type = 'renewal_refreshment';
    tnaForm.participant_ids = expiringWorkerIds;
    if (props.expiringCertificates[0]?.course_id) {
        tnaForm.course_id = props.expiringCertificates[0].course_id;
    }
    tnaForm.reason_notes = `Pengajuan Refreshment / Pembaruan Sertifikat kolektif untuk ${expiringWorkerIds.length} pekerja yang masa berlaku sertifikatnya akan habis (< 60 hari).`;
    activeTab.value = 'tna';
};

const submitTnaRequest = () => {
    if (!tnaForm.participant_ids.length || !tnaForm.reason_notes.trim()) return;
    tnaForm.post('/organization/training-requests', {
        preserveScroll: true,
        onSuccess: () => {
            tnaForm.reset('participant_ids', 'reason_notes');
        },
    });
};

const updateTnaStatus = (requestId, status) => {
    router.patch(`/organization/training-requests/${requestId}/status`, { status }, { preserveScroll: true });
};
</script>

<template>
    <Head :title="`Portal ${organizationInfo.type === 'contractor' ? 'Admin Kontraktor' : 'Admin Departemen'}`" />

    <AdminLayout
        :title="
            organizationInfo.type === 'contractor'
                ? `Portal Admin Perusahaan Kontraktor: ${organizationInfo.name}`
                : `Portal Admin Departemen Internal: ${organizationInfo.name}`
        "
        subtitle="Isolasi Data Multi-Tenant Aktif (scopeAccessibleBy) — Anda hanya dapat mengelola pekerja, TNA, dan sertifikat di dalam organisasi Anda"
    >
        <!-- Multi-Tenant Isolation Banner -->
        <div
            :class="[
                'rounded-md border p-4 mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-3',
                organizationInfo.type === 'contractor'
                    ? 'bg-amber-50/70 border-amber-300 text-amber-900'
                    : 'bg-blue-50/70 border-blue-200 text-blue-900',
            ]"
        >
            <div class="flex items-start gap-3">
                <div
                    :class="[
                        'px-2.5 py-1 rounded text-xs font-extrabold uppercase tracking-wider shrink-0',
                        organizationInfo.type === 'contractor'
                            ? 'bg-amber-600 text-white'
                            : 'bg-blue-600 text-white',
                    ]"
                >
                    {{ organizationInfo.type === 'contractor' ? 'VENDOR / KONTRAKTOR' : 'DEPARTEMEN INTERNAL' }}
                </div>
                <div>
                    <div class="text-sm font-bold">
                        {{ organizationInfo.code }} &mdash; {{ organizationInfo.name }}
                    </div>
                    <p class="text-xs opacity-80 mt-0.5">
                        Total Pekerja Terdaftar di Organisasi Ini: <strong>{{ workers.length }} Orang</strong> &bull;
                        Sertifikat Akan Expired (&lt; 60 Hari): <strong>{{ expiringCertificates.length }} Sertifikat</strong>
                    </p>
                </div>
            </div>

            <button
                v-if="expiringCertificates.length > 0"
                type="button"
                @click="prefillExpiringRefreshment"
                class="px-3.5 py-2 rounded bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold shrink-0"
            >
                Ajukan Refreshment ({{ expiringCertificates.length }} Sertifikat Expired) &rarr;
            </button>
        </div>

        <!-- Global Search & Multi-Attribute Filter Bar -->
        <GlobalTableFilterBar
            route-url="/organization/portal"
            :filters="filters"
            :filter-options="filterOptions"
            export-url="/admin/exports/skill-matrix"
            export-label="Export Matrix CSV"
            search-placeholder="Cari Nama Pekerja, NIK, Kode/Judul Training, atau No. Sertifikat..."
        />

        <!-- Navigasi 3 Tab Utama -->
        <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 mb-6">
            <button
                type="button"
                @click="activeTab = 'collective'"
                :class="[
                    'px-4 py-2.5 text-xs font-bold border-b-2 transition-colors',
                    activeTab === 'collective'
                        ? 'border-blue-600 text-blue-600 bg-white'
                        : 'border-transparent text-slate-600 hover:text-slate-900',
                ]"
            >
                1. Pendaftaran Kolektif Pekerja ({{ workers.length }})
            </button>

            <button
                type="button"
                @click="activeTab = 'tna'"
                :class="[
                    'px-4 py-2.5 text-xs font-bold border-b-2 transition-colors flex items-center gap-2',
                    activeTab === 'tna'
                        ? 'border-blue-600 text-blue-600 bg-white'
                        : 'border-transparent text-slate-600 hover:text-slate-900',
                ]"
            >
                <span>2. Pengajuan TNA &amp; Pembaruan Sertifikat</span>
                <span
                    v-if="expiringCertificates.length"
                    class="px-1.5 py-0.5 rounded-full bg-rose-600 text-white text-[10px]"
                >
                    {{ expiringCertificates.length }}
                </span>
            </button>

            <button
                type="button"
                @click="activeTab = 'certificates'"
                :class="[
                    'px-4 py-2.5 text-xs font-bold border-b-2 transition-colors',
                    activeTab === 'certificates'
                        ? 'border-blue-600 text-blue-600 bg-white'
                        : 'border-transparent text-slate-600 hover:text-slate-900',
                ]"
            >
                3. Unduh Sertifikat &amp; Rapor Tim
            </button>
        </div>

        <!-- TAB 1: PENDAFTARAN KOLEKTIF PEKERJA KE JADWAL TRAINING -->
        <div v-if="activeTab === 'collective'" class="bg-white border border-slate-200 rounded-md p-5">
            <div class="mb-4">
                <h2 class="text-sm font-bold text-slate-900">
                    Pendaftaran Kolektif Pekerja ke Jadwal Pelatihan Aktif
                </h2>
                <p class="text-xs text-slate-500">
                    Pilih kelas yang dibuka oleh Tim LnD, centang daftar pekerja dari organisasi Anda, lalu kirim pendaftaran sekaligus.
                </p>
            </div>

            <form @submit.prevent="submitBulkEnroll" class="space-y-4">
                <div>
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <label class="text-xs font-bold text-slate-700">
                            Pilih Program Training Berjalan ({{ bulkEnrollForm.course_ids.length }} dipilih)
                        </label>
                        <span class="text-[11px] text-slate-500">Pilih satu program atau beberapa sekaligus.</span>
                    </div>
                    <div class="grid max-h-56 grid-cols-1 gap-2 overflow-y-auto rounded-lg border border-slate-200 p-2 md:grid-cols-2">
                        <label
                            v-for="course in activeCourses"
                            :key="course.id"
                            class="flex min-h-11 cursor-pointer items-start gap-2.5 rounded-md border border-slate-100 p-3 text-xs hover:bg-blue-50"
                        >
                            <input
                                v-model="bulkEnrollForm.course_ids"
                                type="checkbox"
                                :value="course.id"
                                class="mt-0.5 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                            />
                            <span>
                                <span class="block font-bold text-slate-800">[{{ course.code }}] {{ course.title }}</span>
                                <span class="mt-1 block text-[11px] text-slate-500">
                                    {{ course.start_date }} – {{ course.end_date }} &bull; Trainer: {{ course.trainer_name }}
                                </span>
                            </span>
                        </label>
                        <p v-if="!activeCourses.length" class="p-3 text-xs text-slate-500">
                            Belum ada program training aktif yang dapat dipilih.
                        </p>
                    </div>
                    <p v-if="bulkEnrollForm.errors.course_ids || bulkEnrollForm.errors['course_ids.0']" class="mt-1 text-xs text-rose-600">
                        {{ bulkEnrollForm.errors.course_ids || bulkEnrollForm.errors['course_ids.0'] }}
                    </p>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-bold text-slate-700">
                        Cari Nama / ID Karyawan / Departemen
                    </label>
                    <input
                        v-model="workerSearch"
                        type="search"
                        placeholder="Ketik nama, ID karyawan, atau departemen..."
                        class="w-full rounded-lg border-slate-300 px-3 py-2 text-xs"
                    />
                </div>

                <div class="flex items-center justify-between pt-2">
                    <button
                        type="button"
                        @click="toggleSelectAllBulk"
                        class="text-xs font-bold text-blue-600 hover:underline"
                    >
                        {{
                            bulkEnrollForm.participant_ids.length === filteredWorkers.length
                                ? 'Batalkan Semua Pilihan'
                                : `Pilih Semua Pekerja (${filteredWorkers.length} Orang)`
                        }}
                    </button>
                    <span class="text-xs font-semibold text-slate-600">
                        Terpilih: <strong class="text-blue-600">{{ bulkEnrollForm.participant_ids.length }}</strong> Pekerja
                    </span>
                </div>
                <p v-if="bulkEnrollForm.errors.participant_ids" class="text-xs font-semibold text-rose-600">
                    {{ bulkEnrollForm.errors.participant_ids }}
                </p>

                <div class="border border-slate-200 rounded max-h-80 overflow-y-auto divide-y divide-slate-100">
                    <label
                        v-for="worker in filteredWorkers"
                        :key="worker.id"
                        class="flex items-center justify-between p-3 hover:bg-slate-50 cursor-pointer text-xs"
                    >
                        <div class="flex items-center gap-3">
                            <input
                                v-model="bulkEnrollForm.participant_ids"
                                type="checkbox"
                                :value="worker.id"
                                class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                            />
                            <div>
                                <div class="font-bold text-slate-900">{{ worker.name }}</div>
                                <div class="mt-0.5 flex flex-wrap gap-x-2 gap-y-0.5 text-[11px] text-slate-500">
                                    <span>ID: {{ worker.nik }}</span>
                                    <span>Departemen: {{ worker.department || '-' }}</span>
                                    <span>Posisi: {{ worker.position || '-' }}</span>
                                    <span>Entitas/Perusahaan: {{ worker.organization_name || worker.entity || '-' }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <span
                                v-if="worker.deficit_skills_count > 0"
                                class="px-2 py-0.5 rounded bg-rose-50 text-rose-700 border border-rose-200 text-[10px] font-bold"
                            >
                                {{ worker.deficit_skills_count }} Skill Gap
                            </span>
                            <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-600 text-[10px] font-semibold">
                                {{ worker.enrollments.length }} Kelas Diikuti
                            </span>
                        </div>
                    </label>
                </div>

                <div class="flex justify-end">
                    <button
                        type="submit"
                        :disabled="bulkEnrollForm.processing || !bulkEnrollForm.course_ids.length || !bulkEnrollForm.participant_ids.length"
                        class="px-5 py-2.5 rounded bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold disabled:opacity-50"
                    >
                        Kirim {{ bulkEnrollForm.participant_ids.length }} Pekerja ke {{ bulkEnrollForm.course_ids.length }} Program
                    </button>
                </div>
            </form>
        </div>

        <!-- TAB 2: PENGAJUAN TNA & DETEKSI OTOMATIS SERTIFIKAT EXPIRED (< 60 HARI) -->
        <div v-if="activeTab === 'tna'" class="space-y-6">
            <!-- Komponen Radar Masa Berlaku Sertifikat (H-60 & Expired) dengan Checklist Batch per Course -->
            <ExpiringCertificatesAlert
                :expiring-list="expiringList.length ? expiringList : expiringCertificates"
                :summary="summary"
            />

            <!-- Form Pengajuan TNA / Training Pembaruan ke Tim LnD -->
            <div class="bg-white border border-slate-200 rounded-md p-5">
                <h3 class="text-sm font-bold text-slate-900 mb-1">
                    Formulir Pengajuan TNA (Training Needs Analysis) &amp; Pembaruan Sertifikat
                </h3>
                <p class="text-xs text-slate-500 mb-4">
                    Ajukan pembukaan batch kelas baru atau jadwal refreshment ke Tim LnD.
                </p>

                <form @submit.prevent="submitTnaRequest" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Tipe Pengajuan</label>
                            <select v-model="tnaForm.request_type" class="w-full rounded border-slate-300 text-xs py-2 px-3">
                                <option value="new_tna">TNA Baru (Kebutuhan Kompetensi Baru)</option>
                                <option value="renewal_refreshment">Pembaruan / Refreshment Sertifikat Expired</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Target Kompetensi (Skill)</label>
                            <select v-model="tnaForm.skill_id" class="w-full rounded border-slate-300 text-xs py-2 px-3">
                                <option value="">-- Pilih Skill Terkait --</option>
                                <option v-for="s in skills" :key="s.id" :value="s.id">
                                    [{{ s.code }}] {{ s.name }} ({{ s.category }})
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Program Kelas Referensi</label>
                            <select v-model="tnaForm.course_id" class="w-full rounded border-slate-300 text-xs py-2 px-3">
                                <option value="">-- Usulkan Kelas Baru --</option>
                                <option v-for="c in activeCourses" :key="c.id" :value="c.id">
                                    [{{ c.code }}] {{ c.title }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">
                            Pilih Pekerja yang Diajukan ({{ tnaForm.participant_ids.length }} Terpilih)
                        </label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2 max-h-44 overflow-y-auto p-3 border border-slate-200 rounded bg-slate-50">
                            <label
                                v-for="w in workers"
                                :key="w.id"
                                class="flex items-center gap-2 text-xs bg-white p-2 rounded border border-slate-200 cursor-pointer"
                            >
                                <input
                                    v-model="tnaForm.participant_ids"
                                    type="checkbox"
                                    :value="w.id"
                                    class="rounded border-slate-300 text-blue-600"
                                />
                                <span class="truncate font-medium text-slate-800">{{ w.nik }} - {{ w.name }}</span>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">
                            Alasan Kebutuhan Pelatihan / Catatan Audit K3
                        </label>
                        <textarea
                            v-model="tnaForm.reason_notes"
                            rows="2"
                            required
                            placeholder="Contoh: Pemenuhan regulasi K3 proyek konstruksi Q4 atau perpanjangan lisensi operator alat berat..."
                            class="w-full rounded border-slate-300 text-xs p-2.5"
                        ></textarea>
                    </div>

                    <div class="flex justify-end">
                        <button
                            type="submit"
                            :disabled="tnaForm.processing || !tnaForm.participant_ids.length"
                            class="px-5 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold disabled:opacity-50"
                        >
                            Kirim Pengajuan TNA ke Tim LnD
                        </button>
                    </div>
                </form>
            </div>

            <!-- Tabel Riwayat Pengajuan TNA -->
            <div class="bg-white border border-slate-200 rounded-md p-5">
                <h3 class="text-sm font-bold text-slate-900 mb-3">Status Pengajuan TNA &amp; Refreshment</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50 text-slate-600 uppercase text-[10px] font-bold">
                                <th class="py-2.5 px-3">Tanggal</th>
                                <th class="py-2.5 px-3">Organisasi</th>
                                <th class="py-2.5 px-3">Tipe</th>
                                <th class="py-2.5 px-3">Skill / Kelas</th>
                                <th class="py-2.5 px-3 text-center">Jumlah Pekerja</th>
                                <th class="py-2.5 px-3">Alasan</th>
                                <th class="py-2.5 px-3 text-right">Status Approval LnD</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="req in trainingRequests" :key="req.id">
                                <td class="py-2.5 px-3 text-slate-500 whitespace-nowrap">{{ req.created_at }}</td>
                                <td class="py-2.5 px-3 font-bold text-slate-800">{{ req.organization_name }}</td>
                                <td class="py-2.5 px-3">
                                    <span
                                        :class="[
                                            'px-2 py-0.5 rounded text-[10px] font-bold uppercase',
                                            req.request_type === 'renewal_refreshment'
                                                ? 'bg-amber-50 text-amber-800 border border-amber-300'
                                                : 'bg-blue-50 text-blue-700 border border-blue-200',
                                        ]"
                                    >
                                        {{ req.request_type === 'renewal_refreshment' ? 'REFRESHMENT' : 'TNA BARU' }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3">
                                    <div class="font-bold text-slate-900">{{ req.course_title || req.skill_name || 'Program Baru' }}</div>
                                    <div class="text-[10px] text-slate-500">Oleh: {{ req.requester_name }}</div>
                                </td>
                                <td class="py-2.5 px-3 text-center font-bold">{{ req.target_participants_count }} Orang</td>
                                <td class="py-2.5 px-3 text-slate-600 max-w-xs truncate">{{ req.reason_notes }}</td>
                                <td class="py-2.5 px-3 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <span
                                            :class="[
                                                'px-2 py-0.5 rounded text-[10px] font-bold uppercase',
                                                req.status === 'approved' || req.status === 'scheduled'
                                                    ? 'bg-emerald-100 text-emerald-800'
                                                    : req.status === 'rejected'
                                                      ? 'bg-rose-100 text-rose-800'
                                                      : 'bg-slate-100 text-slate-700',
                                            ]"
                                        >
                                            {{ req.status }}
                                        </span>

                                        <!-- Tombol Eksekusi Khusus Super Admin LnD -->
                                        <template v-if="['lnd', 'admin'].includes(organizationInfo.actor_role) && req.status === 'pending_lnd'">
                                            <button
                                                type="button"
                                                @click="updateTnaStatus(req.id, 'approved')"
                                                class="px-2 py-0.5 rounded bg-emerald-600 text-white text-[10px] font-bold"
                                            >
                                                Approve
                                            </button>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!trainingRequests.length">
                                <td colspan="7" class="py-6 text-center text-slate-400">
                                    Belum ada pengajuan TNA dari organisasi ini.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 3: UNDUH SERTIFIKAT & RAPOR KOMPETENSI PEKERJA DI BAWAH ORGANISASINYA -->
        <div v-if="activeTab === 'certificates'" class="bg-white border border-slate-200 rounded-md p-5">
            <div class="mb-4">
                <h2 class="text-sm font-bold text-slate-900">
                    Arsip Sertifikat &amp; Rapor Kompetensi Pekerja ({{ organizationInfo.name }})
                </h2>
                <p class="text-xs text-slate-500">
                    Dilengkapi proteksi Anti-IDOR: Anda hanya dapat mengunduh sertifikat milik pekerja di bawah departemen/perusahaan Anda sendiri.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 text-slate-600 uppercase text-[10px] font-bold">
                            <th class="py-2.5 px-3">NIK</th>
                            <th class="py-2.5 px-3">Nama Pekerja</th>
                            <th class="py-2.5 px-3">Riwayat Kelas &amp; Masa Berlaku Sertifikat</th>
                            <th class="py-2.5 px-3 text-right">Aksi Cetak / Unduh A4</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="w in workers" :key="w.id" class="hover:bg-slate-50">
                            <td class="py-3 px-3 font-mono font-bold text-slate-700">{{ w.nik }}</td>
                            <td class="py-3 px-3">
                                <div class="font-bold text-slate-900">{{ w.name }}</div>
                                <div class="text-[11px] text-slate-500">{{ w.organization_name }}</div>
                            </td>
                            <td class="py-3 px-3">
                                <div v-if="w.enrollments.length" class="space-y-1">
                                    <div
                                        v-for="enr in w.enrollments"
                                        :key="enr.id"
                                        class="flex flex-wrap items-center gap-2 text-[11px]"
                                    >
                                        <span class="font-semibold text-slate-800">
                                            [{{ enr.course_code }}] {{ enr.course_title }}
                                        </span>
                                        <span class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-700 font-bold text-[10px]">
                                            Progres {{ enr.progress_percentage }}%
                                        </span>
                                        <span
                                            v-if="enr.certificate_expires_at"
                                            :class="[
                                                'px-1.5 py-0.5 rounded text-[10px] font-bold',
                                                enr.is_expiring_soon
                                                    ? 'bg-rose-50 text-rose-700 border border-rose-200'
                                                    : 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                                            ]"
                                        >
                                            Exp: {{ enr.certificate_expires_at }}
                                        </span>
                                    </div>
                                </div>
                                <span v-else class="text-slate-400">Belum ada kelas</span>
                            </td>
                            <td class="py-3 px-3 text-right">
                                <a
                                    :href="w.report_print_url"
                                    target="_blank"
                                    class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold"
                                >
                                    <span>Cetak Sertifikat &amp; Rapor A4</span>
                                </a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
