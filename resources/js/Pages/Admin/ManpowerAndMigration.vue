<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import GlobalTableFilterBar from '@/Components/GlobalTableFilterBar.vue';

const props = defineProps({
    manpowers: {
        type: Array,
        default: () => [],
    },
    manpowersPagination: {
        type: Object,
        default: null,
    },
    organizations: {
        type: Array,
        default: () => [],
    },
    courses: {
        type: Array,
        default: () => [],
    },
    jobPositions: {
        type: Array,
        default: () => [],
    },
    anomalies: {
        type: Array,
        default: () => [],
    },
    selfReports: {
        type: Array,
        default: () => [],
    },
    selfReportsPagination: {
        type: Object,
        default: null,
    },
    filters: {
        type: Object,
        default: () => ({
            group_type: '',
            organization_id: null,
            search: '',
        }),
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
    tab: {
        type: String,
        default: 'manpower',
    },
});

const activeTab = ref(
    (typeof window !== 'undefined' && new URLSearchParams(window.location.search).get('tab')) ||
    props.tab ||
    'manpower'
); // 'manpower' | 'batch_enroll' | 'historical' | 'self_reports' | 'anomalies'

const page = usePage();
watch(
    () => page.url,
    (newUrl) => {
        const tabParam = new URLSearchParams((newUrl || '').split('?')[1] || '').get('tab');
        if (tabParam) {
            activeTab.value = tabParam;
        }
    }
);

const pageTitle = computed(() => {
    switch (activeTab.value) {
        case 'historical':
            return 'Migrasi Riwayat Pelatihan Masa Lalu (Mode A & B)';
        case 'self_reports':
            return 'Verifikasi Self-Report Sertifikat Mandiri Peserta';
        case 'batch_enroll':
            return 'Pendaftaran Training (Individu & Batch)';
        case 'anomalies':
            return 'Audit Data Anomali Organisasi & Posisi';
        default:
            return 'Data Manpower, NIK & Batch Import CSV';
    }
});

// State & Form Cepat Tab 5: Audit Data Anomali (Unmapped Position / Dept)
const mappingForms = ref({});

const getMappingForm = (anomaly) => {
    if (!mappingForms.value[anomaly.id]) {
        mappingForms.value[anomaly.id] = {
            job_position_id: anomaly.job_position_id || '',
            organization_id: anomaly.organization_id || '',
            processing: false,
        };
    }
    return mappingForms.value[anomaly.id];
};

const submitMapping = (workerId) => {
    const f = mappingForms.value[workerId];
    if (!f) return;
    f.processing = true;
    router.post(`/admin/manpower/${workerId}/map-position-org`, {
        job_position_id: f.job_position_id || null,
        organization_id: f.organization_id || null,
    }, {
        preserveScroll: true,
        onFinish: () => {
            f.processing = false;
        },
    });
};

// ============================================================================
// TAB 1: MANPOWER CRUD (Internal & External Contractor) + BATCH CSV IMPORT
// ============================================================================
const localFilterGroup = ref(props.filters.group_type || '');
const localFilterOrg = ref(props.filters.organization_id || '');
const localSearch = ref(props.filters.search || '');
const editingManpowerId = ref(null);

const filteredManpowers = computed(() => {
    return props.manpowers.filter((m) => {
        if (localFilterGroup.value && m.organization_type !== localFilterGroup.value) return false;
        if (localFilterOrg.value && Number(m.organization_id) !== Number(localFilterOrg.value)) return false;
        if (localSearch.value.trim()) {
            const q = localSearch.value.trim().toLowerCase();
            if (
                !m.name.toLowerCase().includes(q) &&
                !m.nik.toLowerCase().includes(q) &&
                !m.email.toLowerCase().includes(q)
            ) {
                return false;
            }
        }
        return true;
    });
});

const manpowerForm = useForm({
    name: '',
    nik: '',
    email: '',
    role: 'peserta',
    organization_id: props.organizations[0]?.id || '',
    password: 'password123',
});

const startEditManpower = (worker) => {
    editingManpowerId.value = worker.id;
    manpowerForm.name = worker.name;
    manpowerForm.nik = worker.nik;
    manpowerForm.email = worker.email;
    manpowerForm.role = worker.role;
    manpowerForm.organization_id = worker.organization_id;
    manpowerForm.password = '';
};

const cancelEditManpower = () => {
    editingManpowerId.value = null;
    manpowerForm.reset();
    manpowerForm.organization_id = props.organizations[0]?.id || '';
    manpowerForm.password = 'password123';
};

const submitManpower = () => {
    if (editingManpowerId.value) {
        manpowerForm.put(`/admin/manpower/${editingManpowerId.value}`, {
            preserveScroll: true,
            onSuccess: () => cancelEditManpower(),
        });
    } else {
        manpowerForm.post('/admin/manpower', {
            preserveScroll: true,
            onSuccess: () => cancelEditManpower(),
        });
    }
};

const deleteManpower = (worker) => {
    if (!confirm(`Hapus data pekerja ${worker.name} (${worker.nik})?`)) return;
    router.delete(`/admin/manpower/${worker.id}`, { preserveScroll: true });
};

const csvImportForm = useForm({
    csv_file: null,
    default_organization_id: props.organizations[0]?.id || '',
});

const handleCsvChange = (e) => {
    csvImportForm.csv_file = e.target.files[0] || null;
};

const submitCsvImport = () => {
    if (!csvImportForm.csv_file) return;
    csvImportForm.post('/admin/manpower/import-csv', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            csvImportForm.reset('csv_file');
        },
    });
};

// ============================================================================
// TAB 2: PENDAFTARAN TRAINING (Individu & Batch Multi-Select Checkbox)
// ============================================================================
const enrollForm = useForm({
    course_id: props.courses[0]?.id || '',
    participant_ids: [],
});

const toggleAllEnrollWorkers = () => {
    if (enrollForm.participant_ids.length === filteredManpowers.value.length) {
        enrollForm.participant_ids = [];
    } else {
        enrollForm.participant_ids = filteredManpowers.value.map((m) => m.id);
    }
};

const submitBatchEnrollment = () => {
    if (!enrollForm.course_id || !enrollForm.participant_ids.length) return;
    enrollForm.post('/admin/manpower/enrollments', {
        preserveScroll: true,
        onSuccess: () => {
            enrollForm.reset('participant_ids');
        },
    });
};

// ============================================================================
// TAB 3: INPUT RIWAYAT TRAINING MASA LALU (Historical Migration Mode A & B)
// ============================================================================
const migrationMode = ref('single_course_multi_worker'); // 'single_worker_multi_cert' | 'single_course_multi_worker'

// Mode A: 1 Orang menginput banyak sertifikat sekaligus
const modeAForm = useForm({
    migration_mode: 'single_worker_multi_cert',
    user_id: props.manpowers[0]?.id || '',
    certificates: [
        {
            course_id: props.courses[0]?.id || '',
            certificate_number: '',
            certificate_issued_at: '2024-06-15',
            certificate_expires_at: '2026-06-15',
        },
    ],
});

const addCertificateRow = () => {
    modeAForm.certificates.push({
        course_id: props.courses[0]?.id || '',
        certificate_number: '',
        certificate_issued_at: '2024-06-15',
        certificate_expires_at: '2026-06-15',
    });
};

const removeCertificateRow = (idx) => {
    if (modeAForm.certificates.length > 1) {
        modeAForm.certificates.splice(idx, 1);
    }
};

const submitModeAMigration = () => {
    modeAForm.post('/admin/historical-migrations', {
        preserveScroll: true,
    });
};

// Mode B: 1 Training dipilih lalu dicentang massal banyak pekerja sekaligus
const modeBForm = useForm({
    migration_mode: 'single_course_multi_worker',
    course_id: props.courses[0]?.id || '',
    certificate_issued_at: '2024-05-10',
    certificate_expires_at: '2026-05-10',
    certificate_prefix: 'HIST/PANI-LND/2024',
    participant_ids: [],
});

const submitModeBMigration = () => {
    if (!modeBForm.course_id || !modeBForm.participant_ids.length) return;
    modeBForm.post('/admin/historical-migrations', {
        preserveScroll: true,
        onSuccess: () => {
            modeBForm.reset('participant_ids');
        },
    });
};

// ============================================================================
// TAB 4: VERIFIKASI SELF-REPORT SERTIFIKAT MANDIRI PESERTA
// ============================================================================
const reviewNotesMap = ref({});
const reviewCourseMap = ref({});

// In-Browser Document Previewer Modal State & Helpers
const previewModalOpen = ref(false);
const previewItem = ref(null);
const previewZoom = ref(1.0);

const openDocumentPreview = (report) => {
    previewItem.value = report;
    previewZoom.value = 1.0;
    previewModalOpen.value = true;
};

const closeDocumentPreview = () => {
    previewModalOpen.value = false;
    previewItem.value = null;
};

const zoomInPreview = () => {
    previewZoom.value = Math.min(2.5, Math.round((previewZoom.value + 0.25) * 100) / 100);
};

const zoomOutPreview = () => {
    previewZoom.value = Math.max(0.5, Math.round((previewZoom.value - 0.25) * 100) / 100);
};

const resetZoomPreview = () => {
    previewZoom.value = 1.0;
};

const getDocumentUrl = (path) => {
    if (!path) return '';
    if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/')) {
        return path;
    }
    return `/storage/${path}`;
};

const isPdfDocument = (path) => {
    return String(path || '').toLowerCase().endsWith('.pdf');
};

const isImageDocument = (path) => {
    const lower = String(path || '').toLowerCase();
    return lower.endsWith('.jpg') || lower.endsWith('.jpeg') || lower.endsWith('.png') || lower.endsWith('.webp');
};

const submitSelfReportReview = (report, status) => {
    router.patch(
        `/admin/self-reports/${report.id}/review`,
        {
            status,
            review_notes: reviewNotesMap.value[report.id] || (status === 'approved' ? 'Dokumen sertifikat valid & diverifikasi.' : 'Dokumen sertifikat kurang jelas/tidak sesuai.'),
            course_id: reviewCourseMap.value[report.id] || props.courses[0]?.id || null,
        },
        { preserveScroll: true }
    );
};

const submitPreviewReview = (status) => {
    if (!previewItem.value) return;
    const report = previewItem.value;
    submitSelfReportReview(report, status);
    closeDocumentPreview();
};

const pendingSelfReportsCount = computed(() => {
    return props.selfReports.filter((r) => r.status === 'pending').length;
});
</script>

<template>
    <Head title="Manpower CRUD, Pendaftaran Batch, & Migrasi Riwayat" />

    <AdminLayout
        :title="pageTitle"
        subtitle="Kelola data pekerja internal & kontraktor, verifikasi sertifikat mandiri, dan integrasi riwayat pelatihan"
    >
        <!-- Global Search & Multi-Attribute Filter Bar -->
        <GlobalTableFilterBar
            route-url="/admin/manpower"
            :filters="filters"
            :filter-options="filterOptions"
            :extra-params="{ tab: activeTab }"
            export-url="/admin/exports/skill-matrix"
            export-label="Export Matrix CSV"
            search-placeholder="Cari Nama Pekerja, NIK, Kode/Judul Training, atau No. Sertifikat..."
        />

        <!-- ================================================================ -->
        <!-- TAB 1: MANPOWER CRUD (Internal & External) + BATCH CSV IMPORT     -->
        <!-- ================================================================ -->
        <div v-if="activeTab === 'manpower'" class="space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <!-- Form Input / Edit Individual Manpower -->
                <div class="lg:col-span-7 bg-white border border-slate-200 rounded-md p-5">
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <h2 class="text-sm font-bold text-slate-900">
                                {{ editingManpowerId ? 'Edit Data Pekerja (Manpower)' : 'Tambah Pekerja Baru (Individual)' }}
                            </h2>
                            <p class="text-xs text-slate-500">
                                Mendukung grup Departemen Internal maupun Perusahaan Kontraktor Eksternal.
                            </p>
                        </div>
                        <button
                            v-if="editingManpowerId"
                            type="button"
                            @click="cancelEditManpower"
                            class="text-xs font-bold text-rose-600 hover:underline"
                        >
                            Batal Edit
                        </button>
                    </div>

                    <form @submit.prevent="submitManpower" class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 text-xs">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">NIK / No. Badge Unik</label>
                            <input
                                v-model="manpowerForm.nik"
                                type="text"
                                required
                                placeholder="Contoh: PANI-0205 atau VND-088"
                                class="w-full rounded border-slate-300 text-xs py-2 px-3 uppercase font-mono"
                            />
                            <p v-if="manpowerForm.errors.nik" class="text-rose-600 mt-1">{{ manpowerForm.errors.nik }}</p>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Nama Lengkap Pekerja</label>
                            <input
                                v-model="manpowerForm.name"
                                type="text"
                                required
                                placeholder="Nama lengkap sesuai KTP/ID..."
                                class="w-full rounded border-slate-300 text-xs py-2 px-3"
                            />
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Email Akun</label>
                            <input
                                v-model="manpowerForm.email"
                                type="email"
                                required
                                placeholder="nama@perusahaan.co.id"
                                class="w-full rounded border-slate-300 text-xs py-2 px-3"
                            />
                            <p v-if="manpowerForm.errors.email" class="text-rose-600 mt-1">{{ manpowerForm.errors.email }}</p>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Unit Departemen / PT Kontraktor</label>
                            <select
                                v-model="manpowerForm.organization_id"
                                required
                                class="w-full rounded border-slate-300 text-xs py-2 px-3"
                            >
                                <option v-for="org in organizations" :key="org.id" :value="org.id">
                                    [{{ org.type === 'contractor' ? 'KONTRAKTOR' : 'INTERNAL' }}] {{ org.code }} - {{ org.name }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Role Sistem</label>
                            <select v-model="manpowerForm.role" class="w-full rounded border-slate-300 text-xs py-2 px-3">
                                <option value="peserta">Peserta (Karyawan / Pekerja Kontraktor)</option>
                                <option value="dept_admin">Admin Departemen Internal</option>
                                <option value="contractor_admin">Admin Perusahaan Kontraktor</option>
                                <option value="trainer">Trainer / Instruktur</option>
                                <option value="lnd">Super Admin LnD</option>
                            </select>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1">
                                Password {{ editingManpowerId ? '(Kosongkan jika tetap)' : 'Awal' }}
                            </label>
                            <input
                                v-model="manpowerForm.password"
                                type="text"
                                class="w-full rounded border-slate-300 text-xs py-2 px-3 font-mono"
                            />
                        </div>

                        <div class="sm:col-span-2 flex justify-end pt-1">
                            <button
                                type="submit"
                                :disabled="manpowerForm.processing"
                                class="px-4 py-2 rounded bg-blue-600 hover:bg-blue-700 text-white font-bold"
                            >
                                {{ editingManpowerId ? 'Simpan Perubahan Manpower' : '+ Simpan Pekerja Baru' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Form Upload Masal (Batch Import via CSV/Excel) -->
                <div class="lg:col-span-5 bg-white border border-slate-200 rounded-md p-5 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <h2 class="text-sm font-bold text-slate-900">Upload Masal (Batch Import CSV/Excel)</h2>
                            <a
                                href="/admin/manpower/template-csv"
                                class="text-xs font-bold text-blue-600 hover:underline"
                            >
                                Unduh Template CSV &darr;
                            </a>
                        </div>
                        <p class="text-xs text-slate-500 mb-4">
                            Format kolom: <code class="font-mono bg-slate-100 px-1 py-0.5">NIK, Nama, Email, Kode_Organisasi</code>.
                            Otomatis memvalidasi keunikan NIK dan mengunci cakupan organisasi.
                        </p>

                        <form @submit.prevent="submitCsvImport" class="space-y-3 text-xs">
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Organisasi Default (Jika kolom ke-4 kosong)</label>
                                <select
                                    v-model="csvImportForm.default_organization_id"
                                    class="w-full rounded border-slate-300 text-xs py-2 px-3"
                                >
                                    <option v-for="org in organizations" :key="org.id" :value="org.id">
                                        {{ org.code }} - {{ org.name }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Pilih File CSV (.csv)</label>
                                <input
                                    type="file"
                                    accept=".csv,.txt"
                                    @change="handleCsvChange"
                                    required
                                    class="w-full text-xs text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-xs file:font-bold file:bg-slate-900 file:text-white hover:file:bg-slate-800"
                                />
                                <p v-if="csvImportForm.errors.csv_file" class="text-rose-600 mt-1">
                                    {{ csvImportForm.errors.csv_file }}
                                </p>
                            </div>

                            <button
                                type="submit"
                                :disabled="csvImportForm.processing || !csvImportForm.csv_file"
                                class="w-full py-2.5 px-4 rounded bg-emerald-600 hover:bg-emerald-700 text-white font-bold disabled:opacity-50"
                            >
                                Jalankan Batch Import Manpower
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Tabel Daftar Manpower (Internal Group & External Contractor Group) -->
            <div class="bg-white border border-slate-200 rounded-md p-5">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <div class="flex flex-wrap items-center gap-2 text-xs font-bold">
                        <button
                            type="button"
                            @click="localFilterGroup = ''"
                            :class="localFilterGroup === '' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                            class="px-3.5 py-1.5 rounded-lg"
                        >
                            Semua Grup ({{ manpowers.length }})
                        </button>
                        <button
                            type="button"
                            @click="localFilterGroup = 'internal_dept'"
                            :class="localFilterGroup === 'internal_dept' ? 'bg-blue-600 text-white' : 'bg-blue-50 text-blue-700'"
                            class="px-3 py-1.5 rounded"
                        >
                            Internal Group
                        </button>
                        <button
                            type="button"
                            @click="localFilterGroup = 'contractor'"
                            :class="localFilterGroup === 'contractor' ? 'bg-purple-700 text-white' : 'bg-purple-50 text-purple-800'"
                            class="px-3 py-1.5 rounded"
                        >
                            External Contractor Group
                        </button>
                    </div>

                    <div class="flex items-center gap-2">
                        <select v-model="localFilterOrg" class="rounded border-slate-300 text-xs py-1.5 px-2.5">
                            <option value="">Semua Organisasi</option>
                            <option v-for="org in organizations" :key="org.id" :value="org.id">
                                {{ org.code }} - {{ org.name }}
                            </option>
                        </select>
                        <input
                            v-model="localSearch"
                            type="text"
                            placeholder="Cari NIK / Nama / Email..."
                            class="rounded border-slate-300 text-xs py-1.5 px-3"
                        />
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50 uppercase text-[10px] font-bold text-slate-600">
                                <th class="py-2.5 px-3">NIK</th>
                                <th class="py-2.5 px-3">Nama Lengkap &amp; Email</th>
                                <th class="py-2.5 px-3">Grup &amp; Organisasi</th>
                                <th class="py-2.5 px-3 text-center">Role</th>
                                <th class="py-2.5 px-3 text-center">Kelas &amp; Sertifikat</th>
                                <th class="py-2.5 px-3 text-right">Aksi CRUD</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="worker in filteredManpowers" :key="worker.id" class="hover:bg-slate-50">
                                <td class="py-2.5 px-3 font-mono font-bold text-blue-700">{{ worker.nik }}</td>
                                <td class="py-2.5 px-3">
                                    <div class="font-bold text-slate-900">{{ worker.name }}</div>
                                    <div class="text-[11px] text-slate-500">{{ worker.email }}</div>
                                </td>
                                <td class="py-2.5 px-3">
                                    <span
                                        :class="worker.organization_type === 'contractor' ? 'bg-purple-50 text-purple-800 border-purple-200' : 'bg-blue-50 text-blue-700 border-blue-200'"
                                        class="px-2 py-0.5 border text-[10px] font-bold uppercase"
                                    >
                                        {{ worker.organization_type === 'contractor' ? 'EXTERNAL CONTRACTOR' : 'INTERNAL DEPT' }}
                                    </span>
                                    <div class="font-medium text-slate-800 mt-0.5">{{ worker.organization_name }}</div>
                                </td>
                                <td class="py-2.5 px-3 text-center uppercase font-mono text-[10px] font-bold text-slate-600">
                                    {{ worker.role }}
                                </td>
                                <td class="py-2.5 px-3 text-center">
                                    <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 font-semibold text-[11px]">
                                        {{ worker.enrollments_count }} Kelas &bull; {{ worker.certificates_count }} Sertifikat
                                    </span>
                                </td>
                                <td class="py-2.5 px-3 text-right space-x-1.5">
                                    <button
                                        type="button"
                                        @click="startEditManpower(worker)"
                                        class="px-2.5 py-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-[11px]"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        type="button"
                                        @click="deleteManpower(worker)"
                                        class="px-2.5 py-1 rounded bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold text-[11px]"
                                    >
                                        Hapus
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ================================================================ -->
        <!-- TAB 2: PENDAFTARAN TRAINING (Individu & Batch Multi-Select)       -->
        <!-- ================================================================ -->
        <div v-if="activeTab === 'batch_enroll'" class="bg-white border border-slate-200 rounded-md p-5">
            <div class="mb-4">
                <h2 class="text-sm font-bold text-slate-900">
                    Pendaftaran Peserta ke Kelas Pelatihan (Satuan &amp; Batch Multi-Select)
                </h2>
                <p class="text-xs text-slate-500">
                    Setiap pendaftaran otomatis mencatat <code class="font-mono">registered_by</code> dan timestamp waktu pendaftaran untuk audit time-series.
                </p>
            </div>

            <form @submit.prevent="submitBatchEnrollment" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Pilih Kelas Pelatihan Tujuan</label>
                        <select v-model="enrollForm.course_id" required class="w-full rounded border-slate-300 text-xs py-2 px-3">
                            <option v-for="c in courses" :key="c.id" :value="c.id">
                                [{{ c.code }}] {{ c.title }} (Masa Berlaku: {{ c.validity_years || 2 }} Tahun)
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Filter Nama / NIK Pekerja</label>
                        <input
                            v-model="localSearch"
                            type="text"
                            placeholder="Ketik nama atau NIK pekerja..."
                            class="w-full rounded border-slate-300 text-xs py-2 px-3"
                        />
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <button
                        type="button"
                        @click="toggleAllEnrollWorkers"
                        class="text-xs font-bold text-blue-600 hover:underline"
                    >
                        {{
                            enrollForm.participant_ids.length === filteredManpowers.length
                                ? 'Batalkan Semua Centang'
                                : `Centang Semua (${filteredManpowers.length} Pekerja)`
                        }}
                    </button>
                    <span class="text-xs font-bold text-slate-700">
                        Dipilih: <strong class="text-blue-600">{{ enrollForm.participant_ids.length }}</strong> Pekerja
                    </span>
                </div>

                <div class="border border-slate-200 rounded max-h-80 overflow-y-auto divide-y divide-slate-100">
                    <label
                        v-for="worker in filteredManpowers"
                        :key="worker.id"
                        class="flex items-center justify-between p-3 hover:bg-slate-50 cursor-pointer text-xs"
                    >
                        <div class="flex items-center gap-3">
                            <input
                                v-model="enrollForm.participant_ids"
                                type="checkbox"
                                :value="worker.id"
                                class="rounded border-slate-300 text-blue-600"
                            />
                            <div>
                                <span class="font-mono font-bold text-blue-700">{{ worker.nik }}</span>
                                <span class="ml-2 font-bold text-slate-900">{{ worker.name }}</span>
                            </div>
                        </div>
                        <span class="text-[11px] text-slate-500">{{ worker.organization_name }}</span>
                    </label>
                </div>

                <div class="flex justify-end">
                    <button
                        type="submit"
                        :disabled="enrollForm.processing || !enrollForm.participant_ids.length"
                        class="px-5 py-2.5 rounded bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold disabled:opacity-50"
                    >
                        Simpan Pendaftaran ({{ enrollForm.participant_ids.length }} Peserta)
                    </button>
                </div>
            </form>
        </div>

        <!-- ================================================================ -->
        <!-- TAB 3: MIGRASI RIWAYAT TRAINING MASA LALU (MODE A & MODE B)       -->
        <!-- ================================================================ -->
        <div v-if="activeTab === 'historical'" class="bg-white border border-slate-200 rounded-md p-5">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-5 pb-4 border-b border-slate-200">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">
                        Input Riwayat Training Masa Lalu (Historical Data Migration)
                    </h2>
                    <p class="text-xs text-slate-500">
                        Pilih metode migrasi sesuai kebutuhan: 1 pekerja banyak sertifikat (Mode A) atau 1 training banyak pekerja (Mode B).
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2 text-xs font-bold">
                    <button
                        type="button"
                        @click="migrationMode = 'single_worker_multi_cert'"
                        :class="migrationMode === 'single_worker_multi_cert' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                        class="px-3.5 py-2 rounded-lg"
                    >
                        Mode A: 1 Pekerja &rarr; Banyak Sertifikat
                    </button>
                    <button
                        type="button"
                        @click="migrationMode = 'single_course_multi_worker'"
                        :class="migrationMode === 'single_course_multi_worker' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                        class="px-3.5 py-2 rounded-lg"
                    >
                        Mode B: 1 Training &rarr; Banyak Pekerja
                    </button>
                </div>
            </div>

            <!-- MODE A: 1 Orang menginput banyak sertifikat sekaligus -->
            <form v-if="migrationMode === 'single_worker_multi_cert'" @submit.prevent="submitModeAMigration" class="space-y-4 text-xs">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Pilih Pekerja (1 Orang)</label>
                    <select v-model="modeAForm.user_id" required class="w-full max-w-md rounded border-slate-300 text-xs py-2 px-3">
                        <option v-for="w in filteredManpowers" :key="w.id" :value="w.id">
                            {{ w.nik }} - {{ w.name }} ({{ w.organization_name }})
                        </option>
                    </select>
                </div>

                <div class="space-y-2.5">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-slate-800">Daftar Sertifikat Masa Lalu yang Dimiliki Pekerja Ini:</span>
                        <button
                            type="button"
                            @click="addCertificateRow"
                            class="px-3 py-1 rounded bg-blue-50 text-blue-700 border border-blue-200 font-bold"
                        >
                            + Tambah Baris Sertifikat
                        </button>
                    </div>

                    <div
                        v-for="(cert, idx) in modeAForm.certificates"
                        :key="idx"
                        class="grid grid-cols-1 md:grid-cols-12 gap-3 p-3 rounded border border-slate-200 bg-slate-50 items-end"
                    >
                        <div class="md:col-span-4">
                            <label class="block text-[11px] font-bold text-slate-600 mb-1">Program Pelatihan</label>
                            <select v-model="cert.course_id" required class="w-full rounded border-slate-300 text-xs py-1.5 px-2.5">
                                <option v-for="c in courses" :key="c.id" :value="c.id">[{{ c.code }}] {{ c.title }}</option>
                            </select>
                        </div>
                        <div class="md:col-span-3">
                            <label class="block text-[11px] font-bold text-slate-600 mb-1">No. Sertifikat Resmi</label>
                            <input
                                v-model="cert.certificate_number"
                                type="text"
                                placeholder="Kosongkan untuk auto-generate"
                                class="w-full rounded border-slate-300 text-xs py-1.5 px-2.5 font-mono"
                            />
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-[11px] font-bold text-slate-600 mb-1">Tgl Terbit</label>
                            <input v-model="cert.certificate_issued_at" type="date" required class="w-full rounded border-slate-300 text-xs py-1.5 px-2" />
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-[11px] font-bold text-slate-600 mb-1">Tgl Kadaluarsa</label>
                            <input v-model="cert.certificate_expires_at" type="date" class="w-full rounded border-slate-300 text-xs py-1.5 px-2" />
                        </div>
                        <div class="md:col-span-1 text-right">
                            <button
                                type="button"
                                @click="removeCertificateRow(idx)"
                                class="px-2.5 py-1.5 rounded bg-rose-100 text-rose-700 font-bold"
                            >
                                ✕
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" :disabled="modeAForm.processing" class="px-5 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-bold">
                        Simpan {{ modeAForm.certificates.length }} Sertifikat ke Riwayat Resmi
                    </button>
                </div>
            </form>

            <!-- MODE B: 1 Training dipilih lalu dicentang massal banyak pekerja sekaligus -->
            <form v-else @submit.prevent="submitModeBMigration" class="space-y-4 text-xs">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                    <div class="md:col-span-2">
                        <label class="block font-bold text-slate-700 mb-1">Pilih 1 Program Pelatihan</label>
                        <select v-model="modeBForm.course_id" required class="w-full rounded border-slate-300 text-xs py-2 px-3">
                            <option v-for="c in courses" :key="c.id" :value="c.id">[{{ c.code }}] {{ c.title }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Tanggal Terbit Sertifikat</label>
                        <input v-model="modeBForm.certificate_issued_at" type="date" required class="w-full rounded border-slate-300 text-xs py-2 px-3" />
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Tanggal Kadaluarsa</label>
                        <input v-model="modeBForm.certificate_expires_at" type="date" class="w-full rounded border-slate-300 text-xs py-2 px-3" />
                    </div>
                </div>

                <div class="border border-slate-200 rounded max-h-72 overflow-y-auto divide-y divide-slate-100">
                    <label
                        v-for="worker in filteredManpowers"
                        :key="worker.id"
                        class="flex items-center justify-between p-3 hover:bg-slate-50 cursor-pointer"
                    >
                        <div class="flex items-center gap-3">
                            <input
                                v-model="modeBForm.participant_ids"
                                type="checkbox"
                                :value="worker.id"
                                class="rounded border-slate-300 text-blue-600"
                            />
                            <div>
                                <span class="font-mono font-bold text-blue-700">{{ worker.nik }}</span>
                                <span class="ml-2 font-bold text-slate-900">{{ worker.name }}</span>
                            </div>
                        </div>
                        <span class="text-[11px] text-slate-500">{{ worker.organization_name }}</span>
                    </label>
                </div>

                <div class="flex justify-end">
                    <button
                        type="submit"
                        :disabled="modeBForm.processing || !modeBForm.participant_ids.length"
                        class="px-5 py-2.5 rounded bg-emerald-600 hover:bg-emerald-700 text-white font-bold disabled:opacity-50"
                    >
                        Migrasi Massal {{ modeBForm.participant_ids.length }} Pekerja ke Riwayat Resmi
                    </button>
                </div>
            </form>
        </div>

        <!-- ================================================================ -->
        <!-- TAB 4: VERIFIKASI SELF-REPORT SERTIFIKAT MANDIRI PESERTA         -->
        <!-- ================================================================ -->
        <div v-if="activeTab === 'self_reports'" class="bg-white border border-slate-200 rounded-md p-5">
            <div class="mb-4">
                <h2 class="text-sm font-bold text-slate-900">
                    Verifikasi Pelaporan Mandiri Sertifikat Peserta (Self-Reported Trainings)
                </h2>
                <p class="text-xs text-slate-500">
                    Jika disetujui (<code class="font-mono">approved</code>), sertifikat mandiri otomatis disinkronkan ke riwayat pelatihan resmi pekerja.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 uppercase text-[10px] font-bold text-slate-600">
                            <th class="py-2.5 px-3">Pekerja &amp; Unit</th>
                            <th class="py-2.5 px-3">Pelatihan &amp; Penyelenggara</th>
                            <th class="py-2.5 px-3">No. Sertifikat &amp; Masa Berlaku</th>
                            <th class="py-2.5 px-3">File Bukti</th>
                            <th class="py-2.5 px-3">Pemetaan Kelas &amp; Catatan Review</th>
                            <th class="py-2.5 px-3 text-right">Status / Keputusan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="rep in selfReports" :key="rep.id" class="hover:bg-slate-50">
                            <td class="py-3 px-3">
                                <div class="font-bold text-slate-900">{{ rep.user_name }}</div>
                                <div class="font-mono text-[11px] text-slate-500">{{ rep.user_nik }} &bull; {{ rep.organization_name }}</div>
                            </td>
                            <td class="py-3 px-3">
                                <div class="font-bold text-slate-900">{{ rep.course_name }}</div>
                                <div class="text-[11px] text-slate-500">Penyelenggara: {{ rep.organizer }}</div>
                            </td>
                            <td class="py-3 px-3">
                                <div class="font-mono font-bold text-blue-700">{{ rep.certificate_number }}</div>
                                <div class="text-[11px] text-slate-500">
                                    Terbit: {{ rep.issued_at_formatted }} &bull; Exp: {{ rep.expires_at_formatted }}
                                </div>
                            </td>
                            <td class="py-3 px-3">
                                <button
                                    type="button"
                                    @click="openDocumentPreview(rep)"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded bg-blue-50 hover:bg-blue-100 text-blue-700 font-bold text-[11px] border border-blue-200 transition-colors shadow-sm"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    <span>Lihat &amp; Verifikasi</span>
                                </button>
                            </td>
                            <td class="py-3 px-3">
                                <div v-if="rep.status === 'pending'" class="space-y-1.5">
                                    <select
                                        v-model="reviewCourseMap[rep.id]"
                                        class="w-full rounded border-slate-300 text-[11px] py-1 px-2"
                                    >
                                        <option v-for="c in courses" :key="c.id" :value="c.id">
                                            Sinkron ke: [{{ c.code }}] {{ c.title }}
                                        </option>
                                    </select>
                                    <input
                                        v-model="reviewNotesMap[rep.id]"
                                        type="text"
                                        placeholder="Catatan verifikasi LnD..."
                                        class="w-full rounded border-slate-300 text-[11px] py-1 px-2"
                                    />
                                </div>
                                <div v-else class="text-[11px] text-slate-600">
                                    <div><strong>Catatan:</strong> {{ rep.review_notes || '-' }}</div>
                                    <div v-if="rep.reviewer_name" class="text-[10px] text-slate-400">
                                        Oleh: {{ rep.reviewer_name }}
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 px-3 text-right">
                                <div v-if="rep.status === 'pending'" class="flex items-center justify-end gap-1.5">
                                    <button
                                        type="button"
                                        @click="submitSelfReportReview(rep, 'approved')"
                                        class="px-2.5 py-1 rounded bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[11px]"
                                    >
                                        Approve
                                    </button>
                                    <button
                                        type="button"
                                        @click="submitSelfReportReview(rep, 'rejected')"
                                        class="px-2.5 py-1 rounded bg-rose-600 hover:bg-rose-700 text-white font-bold text-[11px]"
                                    >
                                        Reject
                                    </button>
                                </div>
                                <span
                                    v-else
                                    :class="[
                                        'px-2.5 py-1 rounded text-[10px] font-bold uppercase inline-block',
                                        rep.status === 'approved'
                                            ? 'bg-emerald-100 text-emerald-800'
                                            : 'bg-rose-100 text-rose-800',
                                    ]"
                                >
                                    {{ rep.status }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="!selfReports.length">
                            <td colspan="6" class="py-8 text-center text-slate-400">
                                Belum ada pengajuan sertifikat mandiri dari peserta.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ================================================================ -->
        <!-- MODAL IN-BROWSER DOCUMENT PREVIEWER & VERIFIKASI LANGSUNG         -->
        <!-- ================================================================ -->
        <div
            v-if="previewModalOpen && previewItem"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-sm p-3 sm:p-5"
        >
            <div class="bg-white rounded-xl shadow-2xl border border-slate-300 w-full max-w-5xl h-[92vh] flex flex-col overflow-hidden">
                <!-- Header Modal (Calm Light Theme) -->
                <div class="bg-blue-50/90 border-b border-blue-100 text-slate-800 px-5 py-3.5 flex items-center justify-between shrink-0">
                    <div>
                        <div class="text-[10px] font-bold uppercase tracking-wider text-blue-600">
                            Verifikasi Bukti Dokumen Pelatihan Mandiri
                        </div>
                        <h3 class="text-sm font-bold text-slate-900 mt-0.5">
                            {{ previewItem.course_name }} &bull; <span class="font-mono text-blue-700">{{ previewItem.certificate_number }}</span>
                        </h3>
                        <div class="text-[11px] text-slate-600 mt-0.5">
                            Pekerja: <strong class="text-slate-900">{{ previewItem.user_name }}</strong> ({{ previewItem.user_nik }}) &bull; {{ previewItem.organization_name }}
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <a
                            :href="getDocumentUrl(previewItem.document_path)"
                            target="_blank"
                            download
                            class="px-3 py-1.5 rounded-lg bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold flex items-center gap-1 border border-slate-200 shadow-sm"
                            title="Download File Asli"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            <span class="hidden sm:inline">Unduh File</span>
                        </a>
                        <button
                            type="button"
                            @click="closeDocumentPreview"
                            class="p-1.5 rounded-lg bg-white hover:bg-rose-50 text-slate-500 hover:text-rose-700 border border-slate-200 transition-colors"
                            title="Tutup Preview"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Body Previewer (PDF vs Image vs Other) -->
                <div class="flex-1 bg-slate-100 overflow-hidden relative flex flex-col">
                    <!-- Zoom Controls for Images -->
                    <div
                        v-if="isImageDocument(previewItem.document_path)"
                        class="absolute top-3 right-3 z-10 bg-slate-900/90 text-white rounded-lg shadow-lg border border-slate-700 p-1 flex items-center gap-1 text-xs"
                    >
                        <button
                            type="button"
                            @click="zoomOutPreview"
                            class="px-2 py-1 hover:bg-slate-800 rounded font-bold"
                            title="Zoom Out"
                        >
                            -
                        </button>
                        <span class="px-1 text-[11px] font-mono">{{ Math.round(previewZoom * 100) }}%</span>
                        <button
                            type="button"
                            @click="zoomInPreview"
                            class="px-2 py-1 hover:bg-slate-800 rounded font-bold"
                            title="Zoom In"
                        >
                            +
                        </button>
                        <button
                            type="button"
                            @click="resetZoomPreview"
                            class="px-2 py-1 hover:bg-slate-800 rounded text-[10px] text-slate-300"
                            title="Reset Zoom"
                        >
                            Reset
                        </button>
                    </div>

                    <!-- PDF Iframe Preview -->
                    <div v-if="isPdfDocument(previewItem.document_path)" class="w-full h-full p-2">
                        <iframe
                            :src="getDocumentUrl(previewItem.document_path)"
                            class="w-full h-full rounded border border-slate-300 bg-white shadow-inner"
                        />
                    </div>

                    <!-- Image Preview with Smooth Zoom & Scroll -->
                    <div
                        v-else-if="isImageDocument(previewItem.document_path)"
                        class="w-full h-full overflow-auto p-4 flex items-center justify-center bg-slate-900"
                    >
                        <img
                            :src="getDocumentUrl(previewItem.document_path)"
                            :style="{ transform: `scale(${previewZoom})`, transformOrigin: 'center center' }"
                            alt="Bukti Dokumen Sertifikat"
                            class="transition-transform duration-150 max-h-full max-w-full object-contain rounded shadow-lg border border-slate-700"
                        />
                    </div>

                    <!-- Fallback Dokumen Lainnya -->
                    <div v-else class="w-full h-full flex flex-col items-center justify-center text-center p-6 text-slate-600">
                        <div class="w-12 h-12 rounded bg-slate-200 text-slate-500 flex items-center justify-center mb-2 font-black text-lg">
                            DOC
                        </div>
                        <div class="text-sm font-bold text-slate-800">Pratinjau Langsung Tidak Didukung</div>
                        <p class="text-xs text-slate-500 mt-1 max-w-sm">
                            File ini dapat Anda unduh langsung untuk diperiksa pada aplikasi pembaca dokumen lokal Anda.
                        </p>
                        <a
                            :href="getDocumentUrl(previewItem.document_path)"
                            target="_blank"
                            download
                            class="mt-3 px-4 py-2 rounded bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs shadow"
                        >
                            Unduh File Sertifikat ({{ previewItem.document_path.split('.').pop().toUpperCase() }})
                        </a>
                    </div>
                </div>

                <!-- Footer Verifikasi Langsung (Approve / Reject) -->
                <div class="bg-white border-t border-slate-200 p-4 shrink-0">
                    <div v-if="previewItem.status === 'pending'" class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3 text-xs">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2 flex-1">
                            <div>
                                <label class="block text-[10px] font-bold uppercase text-slate-500 mb-0.5">
                                    Sinkronkan ke Program Pelatihan Resmi:
                                </label>
                                <select
                                    v-model="reviewCourseMap[previewItem.id]"
                                    class="w-full rounded border-slate-300 text-xs py-1.5 px-2"
                                >
                                    <option v-for="c in courses" :key="c.id" :value="c.id">
                                        [{{ c.code }}] {{ c.title }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold uppercase text-slate-500 mb-0.5">
                                    Catatan Verifikasi / Alasan Penolakan:
                                </label>
                                <input
                                    v-model="reviewNotesMap[previewItem.id]"
                                    type="text"
                                    placeholder="Contoh: Dokumen asli dan sah sesuai nomor SK..."
                                    class="w-full rounded border-slate-300 text-xs py-1.5 px-2.5"
                                />
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-2 shrink-0 pt-2 md:pt-0">
                            <button
                                type="button"
                                @click="submitPreviewReview('rejected')"
                                class="px-4 py-2 rounded bg-rose-600 hover:bg-rose-700 text-white font-extrabold text-xs flex items-center gap-1.5 shadow-sm"
                            >
                                <span>✕</span>
                                <span>Tolak (Reject)</span>
                            </button>
                            <button
                                type="button"
                                @click="submitPreviewReview('approved')"
                                class="px-5 py-2 rounded bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs flex items-center gap-1.5 shadow-sm"
                            >
                                <span>✓</span>
                                <span>Setujui (Approve)</span>
                            </button>
                        </div>
                    </div>

                    <div v-else class="flex items-center justify-between text-xs">
                        <div class="flex items-center gap-2">
                            <span
                                :class="previewItem.status === 'approved' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                                class="px-2.5 py-1 rounded text-[10px] font-bold uppercase"
                            >
                                Status: {{ previewItem.status }}
                            </span>
                            <span class="text-slate-600">
                                Catatan: <strong>{{ previewItem.review_notes || '-' }}</strong>
                            </span>
                        </div>
                        <button
                            type="button"
                            @click="closeDocumentPreview"
                            class="px-4 py-1.5 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold"
                        >
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================================================================ -->
        <!-- TAB 5: AUDIT DATA ANOMALI (UNMAPPED POSITION / DEPT TIDAK ADA)   -->
        <!-- ================================================================ -->
        <div v-if="activeTab === 'anomalies'" class="space-y-6">
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 sm:p-5">
                <div class="flex items-start gap-3">
                    <span class="text-2xl">⚠️</span>
                    <div>
                        <h2 class="text-sm font-bold text-amber-950 uppercase tracking-wide">
                            Audit Data Anomali &amp; Rekonsiliasi Training Matrix (MDKA Standard)
                        </h2>
                        <p class="text-xs text-amber-800 mt-1 leading-relaxed">
                            Pekerja pada daftar ini belum memiliki <strong>Job Position (No Match)</strong> atau departemennya belum terhubung ke <strong>Organisasi resmi</strong> (seperti sheet <em>'Dept Tidak Terdaftar'</em> di Excel).
                            Lakukan pemetaan posisi dan organisasi di bawah agar standar kompetensi jabatan otomatis tersinkronisasi ke Skill Matrix.
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
                <div class="flex justify-between items-center border-b border-slate-100 pb-3 mb-4">
                    <div>
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-900">
                            Daftar Karyawan Belum Terpetakan ({{ anomalies.length }} Pekerja)
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">Pilih jabatan dan organisasi yang tepat, lalu klik tombol Simpan &amp; Sinkronkan.</p>
                    </div>
                </div>

                <div v-if="anomalies && anomalies.length > 0" class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold uppercase tracking-wider text-slate-600">
                                <th class="p-3">Karyawan</th>
                                <th class="p-3">Site &amp; Dept Saat Ini</th>
                                <th class="p-3">Jenis Anomali</th>
                                <th class="p-3 min-w-[220px]">Petakan Jabatan (Job Position)</th>
                                <th class="p-3 min-w-[220px]">Petakan Organisasi Resmi</th>
                                <th class="p-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="worker in anomalies" :key="worker.id" class="hover:bg-slate-50 transition">
                                <td class="p-3">
                                    <div class="font-bold text-slate-900">{{ worker.name }}</div>
                                    <div class="font-mono text-[11px] text-slate-500 mt-0.5">{{ worker.nik }}</div>
                                </td>
                                <td class="p-3">
                                    <div class="text-slate-800 font-medium">{{ worker.department }}</div>
                                    <div class="text-[10px] text-slate-400 mt-0.5">{{ worker.site }}</div>
                                </td>
                                <td class="p-3">
                                    <span
                                        class="inline-block px-2 py-0.5 rounded text-[10px] font-extrabold uppercase"
                                        :class="worker.issue_type === 'Belum Ada Posisi & Dept' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800'"
                                    >
                                        {{ worker.issue_type }}
                                    </span>
                                </td>
                                <td class="p-3">
                                    <select
                                        v-model="getMappingForm(worker).job_position_id"
                                        class="w-full text-xs rounded-lg border-slate-200 bg-slate-50 focus:bg-white focus:ring-1 focus:ring-slate-900 focus:border-slate-900 py-1.5 px-2"
                                    >
                                        <option value="">-- Pilih Standar Jabatan --</option>
                                        <option v-for="jp in jobPositions" :key="jp.id" :value="jp.id">
                                            [{{ jp.code }}] {{ jp.level_name }} - {{ jp.position_title }}
                                        </option>
                                    </select>
                                </td>
                                <td class="p-3">
                                    <select
                                        v-model="getMappingForm(worker).organization_id"
                                        class="w-full text-xs rounded-lg border-slate-200 bg-slate-50 focus:bg-white focus:ring-1 focus:ring-slate-900 focus:border-slate-900 py-1.5 px-2"
                                    >
                                        <option value="">-- Pilih Organisasi Resmi --</option>
                                        <option v-for="org in organizations" :key="org.id" :value="org.id">
                                            [{{ org.code }}] {{ org.name }} ({{ org.type === 'internal_dept' ? 'Internal' : 'Kontraktor' }})
                                        </option>
                                    </select>
                                </td>
                                <td class="p-3 text-right">
                                    <button
                                        type="button"
                                        @click="submitMapping(worker.id)"
                                        :disabled="getMappingForm(worker).processing"
                                        class="px-3 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs transition disabled:opacity-50"
                                    >
                                        {{ getMappingForm(worker).processing ? 'Menyimpan...' : 'Petakan & Sinkron' }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="text-center py-8 text-slate-400 text-xs">
                    🎉 Luar biasa! Seluruh pekerja telah memiliki Job Position dan terdaftar pada Organisasi resmi (Tidak ada data anomali).
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

