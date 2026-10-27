<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import GlobalTableFilterBar from '@/Components/GlobalTableFilterBar.vue';
import CourseFormModal from '@/Components/CourseFormModal.vue';
import ExternalLogisticsPanel from '@/Components/ExternalLogisticsPanel.vue';

const props = defineProps({
    ledger: {
        type: Object,
        required: true,
    },
    kpi: {
        type: Object,
        default: () => ({
            total_records: 0,
            valid_active: 0,
            warning_h60: 0,
            non_compliant: 0,
        }),
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    departments: {
        type: Array,
        default: () => [],
    },
    companies: {
        type: Array,
        default: () => [],
    },
    courseCodes: {
        type: Array,
        default: () => [],
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
    courses: {
        type: Array,
        default: () => [],
    },
    workers: {
        type: Array,
        default: () => [],
    },
    selectedSession: {
        type: Object,
        default: null,
    },
});

const page = usePage();
const userRole = computed(() => {
    const r = page.props.auth?.user?.role || 'lnd';
    return r === 'admin' ? 'lnd' : r;
});
const canCertify = computed(() => ['lnd', 'trainer'].includes(userRole.value));
const externalCourses = computed(() => props.courses.filter((course) => course.training_source === 'external'));
const selectedExternalCourseId = ref(String(props.filters?.course_id || ''));
const selectedExternalCourse = computed(() =>
    externalCourses.value.find((course) => String(course.id) === selectedExternalCourseId.value)
    || externalCourses.value[0]
    || null
);
watch(
    externalCourses,
    (courses) => {
        if (!courses.some((course) => String(course.id) === selectedExternalCourseId.value)) {
            selectedExternalCourseId.value = String(courses[0]?.id || '');
        }
    },
    { immediate: true }
);

// Modal 0: Master Course Form (One Course grants One Competency)
const showCourseModal = ref(false);
const editingCourse = ref(null);

const openCreateCourseModal = () => {
    editingCourse.value = null;
    showCourseModal.value = true;
};

// State untuk Batch Graduation & Auto-Certification
const selectedIds = ref([]);
const batchCompletedAt = ref(new Date().toISOString().slice(0, 10));
const isProcessingBatch = ref(false);

const toggleSelectAll = (e) => {
    if (e.target.checked) {
        selectedIds.value = (props.ledger?.data || [])
            .filter((row) => !row.is_superseded)
            .map((row) => row.id);
    } else {
        selectedIds.value = [];
    }
};

const submitBatchGraduation = () => {
    if (!selectedIds.value.length) return;
    isProcessingBatch.value = true;

    router.post(
        '/training/administration/batch-certify',
        {
            enrollment_ids: selectedIds.value,
            completed_at: batchCompletedAt.value,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                isProcessingBatch.value = false;
                selectedIds.value = [];
            },
        }
    );
};

// Modal 1: Pendaftaran Peserta Batch (Dilengkapi Deteksi Otomatis Sertifikat Aktif > H-60)
const showEnrollModal = ref(false);
const workerSearch = ref('');
const enrollForm = useForm({
    course_id: '',
    attendance_session_id: '',
    participant_ids: [],
    csv_file: null,
    force_enroll: false,
});
const enrollmentParams = new URLSearchParams((page.url || '').split('?')[1] || '');
const requestedCourseId = Number(enrollmentParams.get('course_id'));
const requestedParticipantId = Number(enrollmentParams.get('participant_id'));
if (enrollmentParams.get('open_enrollment') === '1') {
    showEnrollModal.value = true;
}
if (props.selectedSession) {
    enrollForm.course_id = props.selectedSession.course_id;
    enrollForm.attendance_session_id = props.selectedSession.id;
    showEnrollModal.value = true;
}
if (
    !props.selectedSession &&
    Number.isInteger(requestedCourseId) &&
    requestedCourseId > 0 &&
    Number.isInteger(requestedParticipantId) &&
    requestedParticipantId > 0 &&
    props.courses.some((course) => Number(course.id) === requestedCourseId) &&
    props.workers.some((worker) => Number(worker.id) === requestedParticipantId)
) {
    enrollForm.course_id = requestedCourseId;
    enrollForm.participant_ids = [requestedParticipantId];
    showEnrollModal.value = true;
}

const filteredWorkersForEnroll = computed(() => {
    const q = workerSearch.value.trim().toLowerCase();
    if (!q) return props.workers || [];
    return (props.workers || []).filter(
        (w) =>
            w.name.toLowerCase().includes(q) ||
            w.nik.toLowerCase().includes(q) ||
            (w.organization_name || '').toLowerCase().includes(q)
    );
});

const getWorkerCertStateForCourse = (worker, courseId) => {
    if (!courseId || !worker?.course_cert_map) return null;
    return worker.course_cert_map[courseId] || null;
};

const selectedWorkersWithActiveCertWarning = computed(() => {
    if (!enrollForm.course_id) return [];
    return (props.workers || []).filter((w) => {
        if (!enrollForm.participant_ids.includes(w.id)) return false;
        const cert = getWorkerCertStateForCourse(w, enrollForm.course_id);
        return cert && ['active_permanent', 'active_valid'].includes(cert.state);
    });
});

const submitBatchEnroll = () => {
    enrollForm.post('/training/administration/enroll', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            showEnrollModal.value = false;
            enrollForm.reset();
        },
    });
};

// Modal 2: Pencatatan Sertifikat Training Eksternal / Historis (+ Upload PDF)
const showExternalModal = ref(false);
const externalForm = useForm({
    course_id: '',
    enrollment_type: 'regular',
    participant_ids: [],
    certificate_number: '',
    certificate_issued_at: new Date().toISOString().slice(0, 10),
    certificate_expires_at: '',
    certificate_file: null,
});

const submitExternalCert = () => {
    externalForm.post('/training/administration/external-certificate', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            showExternalModal.value = false;
            externalForm.reset();
        },
    });
};
</script>

<template>
    <Head title="Administrasi Pelatihan & Buku Induk Sertifikat" />

    <AdminLayout
        title="Pusat Administrasi Pelatihan & Buku Induk Sertifikat"
        subtitle="Kelola pendaftaran, kelulusan massal, nomor sertifikat otomatis, dan pemantauan masa berlaku H-60"
    >
        <div class="space-y-4">
            <!-- Header Action Buttons -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h1 class="text-base font-bold text-slate-800">
                        Buku Induk Sertifikat (Certificate Ledger) &amp; Siklus Masa Berlaku
                    </h1>
                    <p class="text-xs text-slate-500">
                        Otomatisasi penomoran korporat, penandaan sertifikat lama (Superseded), dan sinkronisasi masa berlaku ke Skill Matrix
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        v-if="userRole === 'lnd'"
                        href="/admin/enrollments"
                        class="px-3.5 py-2 rounded-lg border border-amber-300 bg-amber-50 text-amber-900 hover:bg-amber-100 text-xs font-bold shadow-sm transition-colors"
                    >
                        Review Pendaftaran
                    </Link>
                    <button
                        type="button"
                        @click="showEnrollModal = true"
                        class="px-3.5 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold shadow-sm transition-colors"
                    >
                        + Daftarkan Peserta
                    </button>
                    <button
                        v-if="userRole === 'lnd'"
                        type="button"
                        @click="openCreateCourseModal"
                        class="px-3.5 py-2 rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 text-xs font-bold shadow-sm transition-colors"
                    >
                        + Tambah Training Master
                    </button>
                    <button
                        type="button"
                        @click="showExternalModal = true"
                        class="px-3.5 py-2 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold shadow-sm transition-colors"
                    >
                        + Input Sertifikat Eksternal / Historis
                    </button>
                </div>
            </div>

            <!-- 4 KPI Ringkasan Kepatuhan Sertifikat (Hanya Menghitung Sertifikat Aktif Non-Superseded) -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="bg-white border border-slate-200 rounded-xl p-3.5 shadow-sm">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                        Total Baris Buku Induk
                    </p>
                    <p class="text-xl font-bold text-slate-800 mt-1">{{ kpi.total_records }}</p>
                </div>

                <div class="bg-emerald-50/70 border border-emerald-200 rounded-xl p-3.5 shadow-sm">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-emerald-700">
                        Sertifikat Aktif &amp; Aman
                    </p>
                    <p class="text-xl font-bold text-emerald-800 mt-1">{{ kpi.valid_active }}</p>
                </div>

                <div class="bg-amber-50/80 border border-amber-200 rounded-xl p-3.5 shadow-sm">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-amber-700">
                        Warning H-60 (Wajib Refreshment)
                    </p>
                    <p class="text-xl font-bold text-amber-800 mt-1">{{ kpi.warning_h60 }}</p>
                </div>

                <div class="bg-rose-50/80 border border-rose-200 rounded-xl p-3.5 shadow-sm">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-rose-700">
                        Expired / Gugur Kepatuhan
                    </p>
                    <p class="text-xl font-bold text-rose-800 mt-1">{{ kpi.non_compliant }}</p>
                </div>
            </div>

            <!-- Komponen Standar Global Filter Bar -->
            <GlobalTableFilterBar
                route-url="/training/administration"
                :filters="filters"
                :extra-params="selectedSession ? { session_id: selectedSession.id } : {}"
                :filter-options="filterOptions"
                :departments="departments"
                :companies="companies"
                :course-codes="courseCodes"
                export-url="/training/administration/export-csv"
                export-label="Export Buku Induk CSV"
            />

            <section v-if="userRole === 'lnd'" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900">Pilih program yang akan dikonfigurasi</h2>
                        <p class="mt-1 text-xs text-slate-600">Kelola vendor, biaya, persyaratan berkas, dan kesiapan akomodasi.</p>
                    </div>
                    <div v-if="externalCourses.length" class="w-full sm:max-w-md">
                        <label for="logistics-course" class="mb-1 block text-xs font-semibold text-slate-700">Pilih program eksternal</label>
                        <select
                            id="logistics-course"
                            v-model="selectedExternalCourseId"
                            class="min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20"
                        >
                            <option v-for="course in externalCourses" :key="course.id" :value="String(course.id)">
                                {{ course.code }} — {{ course.title }}
                            </option>
                        </select>
                    </div>
                </div>
                <ExternalLogisticsPanel
                    v-if="selectedExternalCourse"
                    :course="selectedExternalCourse"
                    :can-manage="true"
                />
                <p v-else class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-600">
                    Belum ada program eksternal. Buat program dengan penyelenggara “External” terlebih dahulu.
                </p>
            </section>

            <!-- Action Bar: Batch Graduation & Auto-Certify -->
            <div
                v-if="selectedIds.length > 0 && canCertify"
                class="bg-blue-50 border border-blue-200 rounded-xl p-3 flex flex-wrap items-center justify-between gap-3"
            >
                <div class="text-xs font-medium text-blue-900">
                    <span class="font-bold">{{ selectedIds.length }} peserta</span> dipilih untuk kelulusan, penomoran sertifikat otomatis, &amp; upgrade Skill Matrix.
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <label class="text-xs text-blue-800 font-medium">Tgl Lulus / Terbit:</label>
                    <input
                        type="date"
                        v-model="batchCompletedAt"
                        class="text-xs border-blue-300 rounded-lg px-2.5 py-1.5 bg-white"
                    />
                    <button
                        type="button"
                        @click="submitBatchGraduation"
                        :disabled="isProcessingBatch"
                        class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-3.5 py-1.5 rounded-lg shadow-sm transition disabled:opacity-50"
                    >
                        {{ isProcessingBatch ? 'Memproses...' : 'Nyatakan Lulus & Terbitkan Sertifikat' }}
                    </button>
                </div>
            </div>

            <!-- Tabel Buku Induk Sertifikat (10 Kolom Inti) -->
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                                <th class="p-3 w-8">
                                    <input
                                        v-if="canCertify"
                                        type="checkbox"
                                        @change="toggleSelectAll"
                                        class="rounded border-slate-300"
                                    />
                                </th>
                                <th class="p-3">Pekerja &amp; NIK</th>
                                <th class="p-3">Departemen / Perusahaan</th>
                                <th class="p-3">Kode &amp; Judul Training</th>
                                <th class="p-3">Klasifikasi</th>
                                <th class="p-3">Tipe Daftar</th>
                                <th class="p-3">No. Sertifikat</th>
                                <th class="p-3">Tgl Terbit</th>
                                <th class="p-3">Tgl Kadaluarsa</th>
                                <th class="p-3">Status Masa Berlaku</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                            <tr
                                v-for="row in ledger.data"
                                :key="row.id"
                                :class="row.is_superseded ? 'bg-slate-50/70 opacity-60' : 'hover:bg-slate-50/80'"
                            >
                                <td class="p-3">
                                    <input
                                        v-if="canCertify"
                                        type="checkbox"
                                        :value="row.id"
                                        v-model="selectedIds"
                                        :disabled="row.is_superseded"
                                        class="rounded border-slate-300"
                                    />
                                </td>

                                <!-- 1. Pekerja & NIK -->
                                <td class="p-3">
                                    <div class="font-semibold text-slate-800">{{ row.user.name }}</div>
                                    <div class="text-[11px] text-slate-400 font-mono">NIK: {{ row.user.nik }}</div>
                                </td>

                                <!-- 2. Departemen / Perusahaan -->
                                <td class="p-3">
                                    <div class="font-medium text-slate-700">{{ row.user.org_name }}</div>
                                    <span
                                        class="inline-block mt-0.5 px-1.5 py-0.5 text-[10px] rounded font-medium"
                                        :class="
                                            row.user.org_type === 'contractor'
                                                ? 'bg-purple-50 text-purple-700 border border-purple-200'
                                                : 'bg-sky-50 text-sky-700 border border-sky-200'
                                        "
                                    >
                                        {{ row.user.org_type === 'contractor' ? 'Kontraktor' : 'Internal Dept' }}
                                    </span>
                                </td>

                                <!-- 3. Kode & Judul Training -->
                                <td class="p-3">
                                    <span class="font-mono text-[11px] font-bold text-blue-700 bg-blue-50 px-1.5 py-0.5 rounded">
                                        {{ row.course.code }}
                                    </span>
                                    <div class="font-medium text-slate-800 mt-1">{{ row.course.title }}</div>
                                    <div v-if="row.course.skill_name" class="text-[10px] text-emerald-700 mt-0.5 font-medium">
                                        +Skill: {{ row.course.skill_name }} (Lv.{{ row.course.granted_level }})
                                    </div>
                                </td>

                                <!-- 4. Klasifikasi (Mandatory/Non, Internal/External, Jenis Bidang) -->
                                <td class="p-3 space-y-1">
                                    <div class="flex flex-wrap gap-1">
                                        <span
                                            class="px-1.5 py-0.5 text-[10px] font-semibold rounded"
                                            :class="
                                                row.course.is_mandatory
                                                    ? 'bg-rose-50 text-rose-700 border border-rose-200'
                                                    : 'bg-slate-100 text-slate-600'
                                            "
                                        >
                                            {{ row.course.is_mandatory ? 'Mandatory' : 'Non-Mandatory' }}
                                        </span>
                                        <span
                                            class="px-1.5 py-0.5 text-[10px] font-semibold rounded uppercase"
                                            :class="
                                                row.course.training_source === 'external'
                                                    ? 'bg-indigo-50 text-indigo-700 border border-indigo-200'
                                                    : 'bg-teal-50 text-teal-700 border border-teal-200'
                                            "
                                        >
                                            {{ row.course.training_source }}
                                        </span>
                                    </div>
                                    <div class="text-[11px] text-slate-500">Bidang: {{ row.course.training_category }}</div>
                                </td>

                                <!-- 5. Tipe Pendaftaran -->
                                <td class="p-3">
                                    <span class="capitalize px-2 py-0.5 rounded bg-slate-100 text-slate-700 text-[11px] font-medium">
                                        {{ row.enrollment_type.replace('_', ' ') }}
                                    </span>
                                </td>

                                <!-- 6. Nomor Sertifikat & Link PDF / Print A4 -->
                                <td class="p-3 font-mono text-[11px]">
                                    <div v-if="row.certificate_number" class="font-semibold text-slate-800">
                                        {{ row.certificate_number }}
                                    </div>
                                    <span v-else class="text-slate-400 italic">Belum Terbit</span>

                                    <div class="flex flex-wrap items-center gap-2 mt-1 font-sans">
                                        <a
                                            v-if="row.certificate_file_path"
                                            :href="row.certificate_file_url || `/storage/${row.certificate_file_path}`"
                                            target="_blank"
                                            class="text-[10px] text-blue-600 hover:underline font-semibold"
                                        >
                                            Lihat PDF Eksternal
                                        </a>
                                        <Link
                                            v-if="row.print_report_url"
                                            :href="row.print_report_url"
                                            class="text-[10px] text-slate-600 hover:text-blue-700 hover:underline font-semibold"
                                        >
                                            Cetak A4
                                        </Link>
                                    </div>
                                </td>

                                <!-- 7. Tanggal Terbit -->
                                <td class="p-3 whitespace-nowrap">{{ row.issued_at }}</td>

                                <!-- 8. Tanggal Kadaluarsa -->
                                <td class="p-3 whitespace-nowrap font-medium">{{ row.expires_at }}</td>

                                <!-- 9. Badge Masa Berlaku (Hijau / Kuning H-60 / Merah Expired / Superseded) -->
                                <td class="p-3 whitespace-nowrap">
                                    <span
                                        v-if="row.validity_badge === 'VALID'"
                                        class="px-2 py-1 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800"
                                    >
                                        AKTIF ({{ row.days_remaining }} Hari)
                                    </span>
                                    <span
                                        v-else-if="row.validity_badge === 'PERMANENT'"
                                        class="px-2 py-1 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800"
                                    >
                                        AKTIF SELAMANYA
                                    </span>
                                    <span
                                        v-else-if="row.validity_badge === 'WARNING_H60'"
                                        class="px-2 py-1 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800"
                                    >
                                        SEGERA HABIS (H-{{ row.days_remaining }} Hari)
                                    </span>
                                    <span
                                        v-else-if="row.validity_badge === 'EXPIRED'"
                                        class="px-2 py-1 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800"
                                    >
                                        EXPIRED (Terlewat {{ Math.abs(row.days_remaining) }} Hari)
                                    </span>
                                    <span
                                        v-else-if="row.validity_badge === 'SUPERSEDED'"
                                        class="px-2 py-1 rounded-full text-[10px] font-bold bg-slate-200 text-slate-600"
                                    >
                                        TELAH DIPERBARUI (Superseded)
                                    </span>
                                    <span
                                        v-else
                                        class="px-2 py-1 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-600"
                                    >
                                        DALAM PROSES ({{ row.progress_percentage }}%)
                                    </span>
                                </td>
                            </tr>

                            <tr v-if="!ledger.data?.length">
                                <td colspan="10" class="py-8 text-center text-slate-400">
                                    Tidak ada data pendaftaran atau sertifikat yang sesuai dengan filter.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div
                    v-if="ledger.links?.length > 3"
                    class="px-4 py-3 bg-slate-50 border-t border-slate-200 flex flex-wrap items-center justify-between gap-2 text-xs"
                >
                    <div class="text-slate-500">
                        Menampilkan <span class="font-semibold text-slate-800">{{ ledger.from }}</span> -
                        <span class="font-semibold text-slate-800">{{ ledger.to }}</span> dari
                        <span class="font-semibold text-slate-800">{{ ledger.total }}</span> baris buku induk
                    </div>
                    <div class="flex items-center gap-1">
                        <template v-for="(link, idx) in ledger.links" :key="idx">
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
        <!-- MODAL 0: FORM MASTER COURSE (HUBUNGKAN KE MASTER KOMPETENSI)     -->
        <!-- ================================================================ -->
        <CourseFormModal
            :show="showCourseModal"
            :course="editingCourse"
            :competencies="skills"
            @close="showCourseModal = false"
        />

        <!-- ================================================================ -->
        <!-- MODAL 1: PENDAFTARAN PESERTA BATCH (+ DETEKSI SERTIFIKAT AKTIF)   -->
        <!-- ================================================================ -->
        <div
            v-if="showEnrollModal"
            class="fixed inset-0 z-50 bg-slate-900/50 flex items-center justify-center p-4"
            @click.self="showEnrollModal = false"
        >
            <div class="bg-white border border-slate-200 rounded-xl w-full max-w-2xl shadow-xl overflow-hidden">
                <div class="px-5 py-3.5 bg-blue-50 border-b border-blue-100 flex items-center justify-between">
                    <div>
                        <div class="text-[10px] font-bold uppercase tracking-wider text-blue-600">
                            Batch Enrollment &amp; Anti-Duplication Check
                        </div>
                        <h3 class="text-sm font-bold text-slate-900">
                            Pendaftaran Peserta Pelatihan (Individu / Multi-Select / CSV)
                        </h3>
                    </div>
                    <button
                        type="button"
                        @click="showEnrollModal = false"
                        class="text-slate-400 hover:text-slate-700 font-bold px-2"
                    >
                        &#10005;
                    </button>
                </div>

                <form @submit.prevent="submitBatchEnroll" class="p-5 space-y-4 max-h-[80vh] overflow-y-auto">
                    <div v-if="selectedSession" class="rounded-lg border border-blue-100 bg-blue-50 px-3 py-2 text-xs text-blue-900">
                        Pendaftaran untuk batch <strong>{{ selectedSession.title }}</strong>
                        <span v-if="selectedSession.course"> — [{{ selectedSession.course.code }}] {{ selectedSession.course.title }}</span>
                    </div>
                    <p v-if="enrollForm.errors.attendance_session_id" class="text-xs font-semibold text-rose-600">
                        {{ enrollForm.errors.attendance_session_id }}
                    </p>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Pilih Program Training</label>
                        <select
                            v-model="enrollForm.course_id"
                            :disabled="Boolean(selectedSession)"
                            required
                            class="w-full rounded-lg border-slate-300 text-xs py-2 px-3 disabled:bg-slate-100"
                        >
                            <option value="">-- Pilih Kode &amp; Judul Training --</option>
                            <option v-for="c in courses" :key="c.id" :value="c.id">
                                [{{ c.code }}] {{ c.title }} ({{ c.is_mandatory ? 'Mandatory' : 'Non-Mandatory' }} |
                                {{ c.validity_months ? `Berlaku ${c.validity_months} Bln` : 'Permanent' }})
                            </option>
                        </select>
                    </div>

                    <!-- Peringatan Deteksi Otomatis Sertifikat Aktif (> H-60) -->
                    <div
                        v-if="selectedWorkersWithActiveCertWarning.length > 0"
                        class="p-3 rounded-lg bg-amber-50 border border-amber-200 text-xs text-amber-900 space-y-1.5"
                    >
                        <div class="font-bold">
                            Peringatan Anti-Duplikasi: {{ selectedWorkersWithActiveCertWarning.length }} pekerja terpilih masih memiliki Sertifikat Aktif (&gt; H-60):
                        </div>
                        <ul class="list-disc list-inside text-[11px] space-y-0.5">
                            <li v-for="w in selectedWorkersWithActiveCertWarning" :key="w.id">
                                <span class="font-semibold">{{ w.name }} ({{ w.nik }})</span> &mdash;
                                No: {{ getWorkerCertStateForCourse(w, enrollForm.course_id)?.certificate_number }}
                                (Aktif s/d {{ getWorkerCertStateForCourse(w, enrollForm.course_id)?.expires_at }})
                            </li>
                        </ul>
                        <label class="flex items-center gap-2 pt-1 font-semibold text-amber-800 cursor-pointer">
                            <input type="checkbox" v-model="enrollForm.force_enroll" class="rounded border-amber-400" />
                            <span>Tetap paksa daftarkan ulang pekerja di atas (Force Override)</span>
                        </label>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="text-xs font-bold text-slate-700">
                                Pilih Pekerja ({{ enrollForm.participant_ids.length }} dipilih)
                            </label>
                            <input
                                v-model="workerSearch"
                                type="search"
                                placeholder="Cari nama / NIK pekerja..."
                                class="rounded-lg border-slate-300 text-xs py-1 px-2.5 w-56"
                            />
                        </div>

                        <div class="border border-slate-200 rounded-lg max-h-48 overflow-y-auto divide-y divide-slate-100">
                            <label
                                v-for="w in filteredWorkersForEnroll"
                                :key="w.id"
                                class="flex items-center justify-between px-3 py-2 hover:bg-slate-50 cursor-pointer text-xs"
                            >
                                <div class="flex items-center gap-2.5">
                                    <input
                                        type="checkbox"
                                        :value="w.id"
                                        v-model="enrollForm.participant_ids"
                                        class="rounded border-slate-300"
                                    />
                                    <div>
                                        <span class="font-semibold text-slate-800">{{ w.name }}</span>
                                        <span class="text-slate-400 font-mono ml-1.5">({{ w.nik }})</span>
                                        <span class="mt-0.5 block text-[11px] text-slate-500">
                                            {{ w.department || '-' }} &bull; {{ w.position || '-' }} &bull;
                                            {{ w.organization_name || w.entity || '-' }}
                                        </span>
                                    </div>
                                </div>

                                <div v-if="enrollForm.course_id && getWorkerCertStateForCourse(w, enrollForm.course_id)">
                                    <span
                                        v-if="['active_permanent', 'active_valid'].includes(getWorkerCertStateForCourse(w, enrollForm.course_id).state)"
                                        class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 text-[10px] font-bold"
                                    >
                                        Masih Aktif ({{ getWorkerCertStateForCourse(w, enrollForm.course_id).expires_at }})
                                    </span>
                                    <span
                                        v-else-if="getWorkerCertStateForCourse(w, enrollForm.course_id).state === 'expiring_h60'"
                                        class="px-2 py-0.5 rounded bg-amber-100 text-amber-800 text-[10px] font-bold"
                                    >
                                        Wajib Refreshment (H-{{ getWorkerCertStateForCourse(w, enrollForm.course_id).days_left }})
                                    </span>
                                    <span
                                        v-else
                                        class="px-2 py-0.5 rounded bg-rose-100 text-rose-800 text-[10px] font-bold"
                                    >
                                        Expired (Wajib Refreshment)
                                    </span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">
                            Atau Upload File CSV Daftar NIK Peserta (Opsional)
                        </label>
                        <input
                            type="file"
                            accept=".csv,.txt"
                            @change="(e) => (enrollForm.csv_file = e.target.files[0])"
                            class="w-full text-xs text-slate-600"
                        />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200">
                        <button
                            type="button"
                            @click="showEnrollModal = false"
                            class="px-3.5 py-1.5 rounded-lg border border-slate-300 text-xs font-semibold text-slate-700"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="enrollForm.processing"
                            class="px-4 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold"
                        >
                            {{ enrollForm.processing ? 'Memproses...' : 'Simpan Pendaftaran' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ================================================================ -->
        <!-- MODAL 2: INPUT SERTIFIKAT EKSTERNAL / HISTORIS (+ UPLOAD PDF)     -->
        <!-- ================================================================ -->
        <div
            v-if="showExternalModal"
            class="fixed inset-0 z-50 bg-slate-900/50 flex items-center justify-center p-4"
            @click.self="showExternalModal = false"
        >
            <div class="bg-white border border-slate-200 rounded-xl w-full max-w-xl shadow-xl overflow-hidden">
                <div class="px-5 py-3.5 bg-slate-800 text-white flex items-center justify-between">
                    <div>
                        <div class="text-[10px] font-bold uppercase tracking-wider text-blue-300">
                            External &amp; Historical Certificate Recording
                        </div>
                        <h3 class="text-sm font-bold">
                            Catat Sertifikat Training Eksternal / Historis (+ Scan PDF)
                        </h3>
                    </div>
                    <button
                        type="button"
                        @click="showExternalModal = false"
                        class="text-slate-300 hover:text-white font-bold px-2"
                    >
                        &#10005;
                    </button>
                </div>

                <form @submit.prevent="submitExternalCert" class="p-5 space-y-3.5 max-h-[80vh] overflow-y-auto">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Program Training</label>
                            <select
                                v-model="externalForm.course_id"
                                required
                                class="w-full rounded-lg border-slate-300 text-xs py-1.5 px-2.5"
                            >
                                <option value="">-- Pilih Training --</option>
                                <option v-for="c in courses" :key="c.id" :value="c.id">
                                    [{{ c.code }}] {{ c.title }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Tipe Pencatatan</label>
                            <select
                                v-model="externalForm.enrollment_type"
                                class="w-full rounded-lg border-slate-300 text-xs py-1.5 px-2.5"
                            >
                                <option value="regular">Sertifikat Baru (Regular)</option>
                                <option value="refreshment">Pembaruan (Refreshment)</option>
                                <option value="historical_migration">Migrasi Data Historis</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">
                            Pilih Pekerja ({{ externalForm.participant_ids.length }} dipilih)
                        </label>
                        <div class="border border-slate-200 rounded-lg max-h-36 overflow-y-auto divide-y divide-slate-100">
                            <label
                                v-for="w in workers"
                                :key="w.id"
                                class="flex items-center gap-2.5 px-3 py-1.5 hover:bg-slate-50 cursor-pointer text-xs"
                            >
                                <input
                                    type="checkbox"
                                    :value="w.id"
                                    v-model="externalForm.participant_ids"
                                    class="rounded border-slate-300"
                                />
                                <span class="font-semibold text-slate-800">{{ w.name }}</span>
                                <span class="text-slate-400 font-mono">({{ w.nik }})</span>
                            </label>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">
                                No. Sertifikat (Kosong = Auto)
                            </label>
                            <input
                                v-model="externalForm.certificate_number"
                                type="text"
                                placeholder="CERT/..."
                                class="w-full rounded-lg border-slate-300 text-xs py-1.5 px-2.5"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Tanggal Terbit</label>
                            <input
                                v-model="externalForm.certificate_issued_at"
                                type="date"
                                required
                                class="w-full rounded-lg border-slate-300 text-xs py-1.5 px-2.5"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">
                                Tgl Kadaluarsa (Opsional)
                            </label>
                            <input
                                v-model="externalForm.certificate_expires_at"
                                type="date"
                                class="w-full rounded-lg border-slate-300 text-xs py-1.5 px-2.5"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">
                            Upload Scan PDF / Gambar Sertifikat Fisik
                        </label>
                        <input
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png"
                            @change="(e) => (externalForm.certificate_file = e.target.files[0])"
                            class="w-full text-xs text-slate-600"
                        />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200">
                        <button
                            type="button"
                            @click="showExternalModal = false"
                            class="px-3.5 py-1.5 rounded-lg border border-slate-300 text-xs font-semibold text-slate-700"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="externalForm.processing"
                            class="px-4 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold"
                        >
                            {{ externalForm.processing ? 'Menyimpan...' : 'Simpan & Sinkronkan Skill Matrix' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
