<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, usePage, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import GlobalTableFilterBar from '@/Components/GlobalTableFilterBar.vue';

const props = defineProps({
    courses: {
        type: Array,
        default: () => [],
    },
    enrollments: {
        type: Object,
        required: true,
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
    kpi: {
        type: Object,
        default: () => ({
            total: 0,
            pending: 0,
            approved: 0,
            rejected: 0,
            linked_to_session: 0,
        }),
    },
    activeRunningSessions: {
        type: Array,
        default: () => [],
    },
    scopedWorkers: {
        type: Array,
        default: () => [],
    },
    selectedSession: {
        type: Object,
        default: null,
    },
});

const page = usePage();
const userRole = computed(() => page.props.auth?.user?.role || 'peserta');
const isLndAdmin = computed(() => ['lnd', 'admin'].includes(userRole.value));

// Multi-row Selection for Bulk Moderation
const selectedEnrollmentIds = ref([]);
const selectAllRows = computed({
    get: () => {
        const rows = props.enrollments.data || [];
        return rows.length > 0 && rows.every(r => selectedEnrollmentIds.value.includes(r.id));
    },
    set: (val) => {
        const rows = props.enrollments.data || [];
        if (val) {
            selectedEnrollmentIds.value = rows.map(r => r.id);
        } else {
            selectedEnrollmentIds.value = [];
        }
    },
});

const toggleSelectRow = (id) => {
    const idx = selectedEnrollmentIds.value.indexOf(id);
    if (idx > -1) {
        selectedEnrollmentIds.value.splice(idx, 1);
    } else {
        selectedEnrollmentIds.value.push(id);
    }
};

// Course Modules Checklist view
const activeCourseForModules = computed(() => {
    if (props.filters?.course_id) {
        const byId = props.courses.find((c) => Number(c.id) === Number(props.filters.course_id));
        if (byId) return byId;
    }
    if (props.filters?.course_code) {
        const byCode = props.courses.find((c) => c.code === props.filters.course_code);
        if (byCode) return byCode;
    }
    return props.courses[0] || null;
});

const activeCourseModules = computed(() => {
    return activeCourseForModules.value?.modules || [];
});

// Tab and Running Programs State
const enrollmentTab = ref('running_programs'); // 'running_programs' | 'all_participants'
const expandedProgramId = ref(null);

const toggleExpandProgram = (sessionId) => {
    expandedProgramId.value = expandedProgramId.value === sessionId ? null : sessionId;
};

const runningProgramsFilter = ref({
    search: '',
    status: '',
    category: '',
});

const runningPrograms = computed(() => {
    const sessions = props.activeRunningSessions || [];
    const enrollmentsList = props.enrollments?.data || [];

    return sessions.map(s => {
        const course = s.course || props.courses.find(c => c.id === s.course_id) || {};
        const participants = enrollmentsList.filter(e => e.attendance_session_id === s.id);
        const pendingForCourse = enrollmentsList.filter(e => e.course_id === s.course_id && e.status === 'pending');
        const approvedCount = participants.filter(e => e.status === 'approved').length;
        const pendingCount = pendingForCourse.length;
        const rejectedCount = participants.filter(e => e.status === 'rejected').length;
        const quota = s.quota || 30;
        const fillPercentage = Math.min(100, Math.round((approvedCount / quota) * 100));

        let statusLabel = 'Terjadwal';
        let statusBadge = 'bg-blue-50 text-blue-700 border-blue-200';
        if (s.is_active) {
            statusLabel = 'Sedang Berjalan (Live)';
            statusBadge = 'bg-emerald-50 text-emerald-800 border-emerald-300';
        } else if (quota > approvedCount) {
            statusLabel = 'Pendaftaran Dibuka';
            statusBadge = 'bg-teal-50 text-teal-800 border-teal-200';
        }

        return {
            id: s.id,
            course_id: s.course_id,
            course_code: course.code || s.course_code || '-',
            course_title: course.title || s.title || '-',
            training_category: course.training_category || 'Umum',
            is_mandatory: course.is_mandatory,
            duration_hours: course.duration_hours || s.duration_hours || 4,
            training_source: course.training_source || 'internal',
            session_title: s.title,
            session_date: s.session_date,
            start_time: s.start_time,
            end_time: s.end_time,
            location: s.location,
            trainer_name: s.trainer?.name || 'Instruktur L&D',
            is_active: s.is_active,
            statusLabel,
            statusBadge,
            quota,
            approvedCount,
            pendingCount,
            rejectedCount,
            totalCount: approvedCount + pendingCount,
            fillPercentage,
            participants: [...participants, ...pendingForCourse.filter(p => !participants.some(x => x.id === p.id))],
        };
    });
});

const filteredRunningPrograms = computed(() => {
    return runningPrograms.value.filter(p => {
        if (runningProgramsFilter.value.status) {
            if (runningProgramsFilter.value.status === 'running' && !p.is_active) return false;
        }
        if (runningProgramsFilter.value.category && p.training_category !== runningProgramsFilter.value.category) return false;
        if (runningProgramsFilter.value.search) {
            const q = runningProgramsFilter.value.search.toLowerCase();
            const match =
                p.course_title.toLowerCase().includes(q) ||
                p.course_code.toLowerCase().includes(q) ||
                p.session_title.toLowerCase().includes(q) ||
                p.location.toLowerCase().includes(q) ||
                p.trainer_name.toLowerCase().includes(q);
            if (!match) return false;
        }
        return true;
    });
});

// Rejection Modal State
const rejectionModal = ref({
    open: false,
    enrollment: null,
    isBulk: false,
    reasonPreset: '',
    customReason: '',
});

const rejectionPresets = [
    'Kuota peserta pelatihan periode ini sudah penuh.',
    'Prasyarat kompetensi dasar atau sertifikasi prasyarat belum terpenuhi.',
    'Jadwal pelatihan bertabrakan dengan shift operasional kritis unit.',
    'Karyawan bersangkutan telah memiliki sertifikasi aktif yang masih berlaku panjang.',
    'Materi pelatihan tidak sesuai dengan Matrix Kompetensi standar jabatan saat ini.',
];

const openRejectModal = (enrollment) => {
    rejectionModal.value = {
        open: true,
        enrollment,
        isBulk: false,
        reasonPreset: rejectionPresets[0],
        customReason: '',
    };
};

const openBulkRejectModal = () => {
    if (!selectedEnrollmentIds.value.length) return;
    rejectionModal.value = {
        open: true,
        enrollment: null,
        isBulk: true,
        reasonPreset: rejectionPresets[0],
        customReason: '',
    };
};

const submitRejection = () => {
    const finalReason = rejectionModal.value.customReason.trim() || rejectionModal.value.reasonPreset;
    if (!finalReason) return;

    if (rejectionModal.value.isBulk) {
        router.post('/admin/enrollments/bulk-status', {
            enrollment_ids: selectedEnrollmentIds.value,
            action: 'reject',
            rejection_reason: finalReason,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                rejectionModal.value.open = false;
                selectedEnrollmentIds.value = [];
            },
        });
    } else if (rejectionModal.value.enrollment) {
        router.patch(`/admin/enrollments/${rejectionModal.value.enrollment.id}/status`, {
            status: 'rejected',
            rejection_reason: finalReason,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                rejectionModal.value.open = false;
            },
        });
    }
};

// Link Session Modal State (Hubungkan ke Training Program Berjalan)
const linkSessionModal = ref({
    open: false,
    enrollment: null,
    isBulk: false,
    selectedSessionId: null,
});

const availableSessionsForModal = computed(() => {
    if (!linkSessionModal.value.open) return [];
    if (linkSessionModal.value.isBulk) {
        return props.activeRunningSessions;
    }
    const courseId = linkSessionModal.value.enrollment?.course_id;
    if (!courseId) return props.activeRunningSessions;
    return props.activeRunningSessions.filter(s => Number(s.course_id) === Number(courseId));
});

const openLinkSessionModal = (enrollment) => {
    linkSessionModal.value = {
        open: true,
        enrollment,
        isBulk: false,
        selectedSessionId: enrollment?.attendance_session_id || null,
    };
};

const openBulkLinkSessionModal = () => {
    if (!selectedEnrollmentIds.value.length) return;
    linkSessionModal.value = {
        open: true,
        enrollment: null,
        isBulk: true,
        selectedSessionId: null,
    };
};

const submitLinkSession = () => {
    if (!linkSessionModal.value.selectedSessionId) return;

    if (linkSessionModal.value.isBulk) {
        router.post('/admin/enrollments/bulk-status', {
            enrollment_ids: selectedEnrollmentIds.value,
            action: 'link_session',
            attendance_session_id: linkSessionModal.value.selectedSessionId,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                linkSessionModal.value.open = false;
                selectedEnrollmentIds.value = [];
            },
        });
    } else if (linkSessionModal.value.enrollment) {
        router.patch(`/admin/enrollments/${linkSessionModal.value.enrollment.id}/link-session`, {
            attendance_session_id: linkSessionModal.value.selectedSessionId,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                linkSessionModal.value.open = false;
            },
        });
    }
};

// Quick Approve
const quickApprove = (enrollmentId) => {
    router.patch(`/admin/enrollments/${enrollmentId}/status`, {
        status: 'approved',
    }, {
        preserveScroll: true,
    });
};

const bulkApprove = () => {
    if (!selectedEnrollmentIds.value.length) return;
    router.post('/admin/enrollments/bulk-status', {
        enrollment_ids: selectedEnrollmentIds.value,
        action: 'approve',
    }, {
        preserveScroll: true,
        onSuccess: () => {
            selectedEnrollmentIds.value = [];
        },
    });
};

// Bulk Registration Modal Form (Mode 1 & Mode 2)
const bulkEnrollModal = ref({
    open: false,
    mode: 'single', // 'single' (1 training banyak karyawan) | 'multi' (banyak training ke karyawan)
    singleCourseId: '',
    multiCourseIds: [],
    workerSearch: '',
    selectedWorkerIds: [],
    attendanceSessionId: '',
});

const filteredWorkersForModal = computed(() => {
    const q = bulkEnrollModal.value.workerSearch.trim().toLowerCase();
    if (!q) return props.scopedWorkers;
    return props.scopedWorkers.filter(w =>
        (w.name && w.name.toLowerCase().includes(q)) ||
        (w.nik && w.nik.toLowerCase().includes(q)) ||
        (w.department && w.department.toLowerCase().includes(q)) ||
        (w.position && w.position.toLowerCase().includes(q)) ||
        (w.entity && w.entity.toLowerCase().includes(q))
    );
});

const activeSessionsForSingleCourse = computed(() => {
    if (!bulkEnrollModal.value.singleCourseId) return [];
    return props.activeRunningSessions.filter(s => Number(s.course_id) === Number(bulkEnrollModal.value.singleCourseId));
});

const openBulkEnrollModal = () => {
    bulkEnrollModal.value = {
        open: true,
        mode: 'single',
        singleCourseId: props.courses[0]?.id || '',
        multiCourseIds: [],
        workerSearch: '',
        selectedWorkerIds: [],
        attendanceSessionId: '',
    };
};

const toggleSelectAllModalWorkers = () => {
    const visibleIds = filteredWorkersForModal.value.map(w => w.id);
    const allSelected = visibleIds.every(id => bulkEnrollModal.value.selectedWorkerIds.includes(id));
    if (allSelected) {
        bulkEnrollModal.value.selectedWorkerIds = bulkEnrollModal.value.selectedWorkerIds.filter(id => !visibleIds.includes(id));
    } else {
        const merged = new Set([...bulkEnrollModal.value.selectedWorkerIds, ...visibleIds]);
        bulkEnrollModal.value.selectedWorkerIds = Array.from(merged);
    }
};

const submitBulkEnroll = () => {
    if (!bulkEnrollModal.value.selectedWorkerIds.length) return;

    const payload = {
        participant_ids: bulkEnrollModal.value.selectedWorkerIds,
    };

    if (bulkEnrollModal.value.mode === 'single') {
        if (!bulkEnrollModal.value.singleCourseId) return;
        payload.course_id = bulkEnrollModal.value.singleCourseId;
        if (bulkEnrollModal.value.attendanceSessionId) {
            payload.attendance_session_id = bulkEnrollModal.value.attendanceSessionId;
        }
    } else {
        if (!bulkEnrollModal.value.multiCourseIds.length) return;
        payload.course_ids = bulkEnrollModal.value.multiCourseIds;
    }

    router.post('/admin/enrollments/bulk-enroll', payload, {
        preserveScroll: true,
        onSuccess: () => {
            bulkEnrollModal.value.open = false;
        },
    });
};

// Module Toggle Checkbox
const toggleModuleCompletion = (enrollment, moduleId, currentCompleted) => {
    if (enrollment.status !== 'approved') return;

    router.patch(
        `/admin/enrollments/${enrollment.id}/toggle-module`,
        {
            course_module_id: moduleId,
            is_completed: !currentCompleted,
        },
        {
            preserveScroll: true,
            preserveState: true,
        }
    );
};
</script>

<template>
    <Head title="Administrasi Pendaftaran & Program Pelatihan Berjalan" />

    <AdminLayout
        title="Administrasi Pendaftaran & Program Pelatihan Berjalan"
        subtitle="Pusat Pendaftaran Training Internal, Moderasi Tim L&D, dan Manajemen Batch Program Berjalan"
    >
        <!-- 4 KPI SUMMARY CARDS -->
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-5">
            <div class="bg-white border border-slate-200 rounded-xl p-3.5 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-500">Total Pendaftar</span>
                <div class="text-2xl font-black text-slate-900 mt-1">{{ kpi.total }}</div>
                <div class="text-[11px] text-slate-500 mt-0.5">Semua entri pendaftaran</div>
            </div>
            <div class="bg-amber-50/60 border border-amber-200 rounded-xl p-3.5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-black uppercase tracking-wider text-amber-800">Menunggu Review L&amp;D</span>
                    <span v-if="kpi.pending > 0" class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                </div>
                <div class="text-2xl font-black text-amber-900 mt-1">{{ kpi.pending }}</div>
                <div class="text-[11px] text-amber-700 mt-0.5">Antrean persetujuan</div>
            </div>
            <div class="bg-emerald-50/60 border border-emerald-200 rounded-xl p-3.5 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-emerald-800">Disetujui (Approved)</span>
                <div class="text-2xl font-black text-emerald-900 mt-1">{{ kpi.approved }}</div>
                <div class="text-[11px] text-emerald-700 mt-0.5">Siap mengikuti modul</div>
            </div>
            <div class="bg-rose-50/60 border border-rose-200 rounded-xl p-3.5 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-rose-800">Ditolak (Rejected)</span>
                <div class="text-2xl font-black text-rose-900 mt-1">{{ kpi.rejected }}</div>
                <div class="text-[11px] text-rose-700 mt-0.5">Disertai alasan penolakan</div>
            </div>
            <div class="col-span-2 lg:col-span-1 bg-blue-50/60 border border-blue-200 rounded-xl p-3.5 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-blue-800">Terhubung Sesi Berjalan</span>
                <div class="text-2xl font-black text-blue-900 mt-1">{{ kpi.linked_to_session }}</div>
                <div class="text-[11px] text-blue-700 mt-0.5">Sinkron jadwal &amp; kuota kelas</div>
            </div>
        </div>

        <!-- TAB SWITCHER: PROGRAM BERJALAN VS REKAP PENDAFTAR -->
        <div class="flex items-center justify-between border-b border-slate-200 mb-4">
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    @click="enrollmentTab = 'running_programs'"
                    :class="['px-4 py-2.5 text-xs font-bold border-b-2 transition-all flex items-center gap-2', enrollmentTab === 'running_programs' ? 'border-blue-600 text-blue-700 bg-blue-50/50' : 'border-transparent text-slate-500 hover:text-slate-800']"
                >
                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span>Program Pelatihan Berjalan</span>
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-black" :class="enrollmentTab === 'running_programs' ? 'bg-blue-600 text-white' : 'bg-slate-200 text-slate-700'">{{ filteredRunningPrograms.length }} Program</span>
                </button>
                <button
                    type="button"
                    @click="enrollmentTab = 'all_participants'"
                    :class="['px-4 py-2.5 text-xs font-bold border-b-2 transition-all flex items-center gap-2', enrollmentTab === 'all_participants' ? 'border-blue-600 text-blue-700 bg-blue-50/50' : 'border-transparent text-slate-500 hover:text-slate-800']"
                >
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    <span>Rekap Entri Pendaftar (Audit)</span>
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-black" :class="enrollmentTab === 'all_participants' ? 'bg-blue-600 text-white' : 'bg-slate-200 text-slate-700'">{{ enrollments?.total || enrollments?.data?.length || 0 }} Pendaftar</span>
                </button>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- TAB 1: PROGRAM PELATIHAN BERJALAN                         -->
        <!-- ========================================================= -->
        <div v-if="enrollmentTab === 'running_programs'" class="space-y-4 mb-6">
            <!-- TABEL UTAMA PROGRAM BERJALAN -->
            <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50 text-slate-700 border-b border-slate-200 uppercase text-[10px] tracking-wider font-bold">
                                <th class="py-3.5 px-4 text-left min-w-[260px]">Program Pelatihan (Topik)</th>
                                <th class="py-3.5 px-4 text-left min-w-[240px]">Sesi &amp; Batch Berjalan (Kalender)</th>
                                <th class="py-3.5 px-3 text-left min-w-[170px]">Instruktur / Trainer</th>
                                <th class="py-3.5 px-3 text-center min-w-[140px]">Status Program</th>
                                <th class="py-3.5 px-3 text-left min-w-[180px]">Kapasitas &amp; Pendaftar</th>
                                <th class="py-3.5 px-4 text-right min-w-[190px]">Aksi Kelola</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template v-for="prog in filteredRunningPrograms" :key="'prog-' + prog.id">
                                <tr :class="['transition-colors', expandedProgramId === prog.id ? 'bg-blue-50/40' : 'hover:bg-slate-50']">
                                    <td class="py-3.5 px-4">
                                        <div class="font-bold text-slate-900 text-sm">
                                            <span class="text-blue-700 font-mono">[{{ prog.course_code }}]</span> {{ prog.course_title }}
                                        </div>
                                        <div class="mt-1 flex flex-wrap items-center gap-1.5 text-[10px]">
                                            <span :class="['px-1.5 py-0.5 rounded font-bold uppercase', prog.is_mandatory ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-700']">
                                                {{ prog.is_mandatory ? 'Mandatory' : 'Non-Mandatory' }}
                                            </span>
                                            <span class="text-slate-500">&bull; {{ prog.training_category }}</span>
                                            <span class="text-slate-600 font-bold">&bull; {{ prog.duration_hours }} JP</span>
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <div class="font-bold text-slate-800">{{ prog.session_title }}</div>
                                        <div class="text-[11px] text-slate-600 mt-0.5">
                                            <span>📅 {{ prog.session_date }} ({{ prog.start_time }}-{{ prog.end_time }})</span>
                                        </div>
                                        <div class="text-[10px] text-slate-500 mt-0.5">
                                            <span>📍 {{ prog.location }}</span>
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-3">
                                        <div class="font-semibold text-slate-800">{{ prog.trainer_name }}</div>
                                    </td>
                                    <td class="py-3.5 px-3 text-center">
                                        <span :class="['inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold border', prog.statusBadge]">
                                            <span v-if="prog.is_active" class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                            {{ prog.statusLabel }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-3">
                                        <div class="flex items-center justify-between text-[11px] font-bold">
                                            <span class="text-slate-800">{{ prog.approvedCount }} / {{ prog.quota }} Peserta</span>
                                            <span :class="prog.fillPercentage >= 100 ? 'text-rose-600' : 'text-blue-600'">{{ prog.fillPercentage }}%</span>
                                        </div>
                                        <div class="w-full bg-slate-100 h-2 rounded mt-1 overflow-hidden">
                                            <div :class="['h-full transition-all duration-300', prog.fillPercentage >= 100 ? 'bg-rose-500' : 'bg-blue-600']" :style="{ width: prog.fillPercentage + '%' }"></div>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-1 mt-1 text-[9px]">
                                            <span class="px-1.5 py-0.2 rounded bg-emerald-100 text-emerald-800 font-semibold">{{ prog.approvedCount }} Disetujui</span>
                                            <span v-if="prog.pendingCount > 0" class="px-1.5 py-0.2 rounded bg-amber-100 text-amber-800 font-bold animate-pulse">{{ prog.pendingCount }} Pending</span>
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <button
                                                type="button"
                                                @click="toggleExpandProgram(prog.id)"
                                                :class="['px-3 py-1.5 rounded-lg text-[11px] font-bold shadow-xs transition-all flex items-center gap-1', expandedProgramId === prog.id ? 'bg-blue-700 text-white' : 'bg-blue-50 hover:bg-blue-100 text-blue-800 border border-blue-200']"
                                            >
                                                <span>Kelola Peserta ({{ prog.totalCount }})</span>
                                                <span class="text-[9px]">{{ expandedProgramId === prog.id ? '▲' : '▼' }}</span>
                                            </button>
                                            <button
                                                type="button"
                                                @click="openBulkEnrollModal"
                                                class="px-2.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-bold shadow-xs transition-colors"
                                            >
                                                + Daftarkan
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <!-- EXPANDED ACCORDION: DETAIL PESERTA -->
                                <tr v-if="expandedProgramId === prog.id" :key="'expand-' + prog.id" class="bg-blue-50/30">
                                    <td colspan="6" class="p-4 border-t border-b border-blue-200">
                                        <div class="bg-white rounded-xl border border-blue-200 p-4 shadow-sm space-y-3">
                                            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                                                <div>
                                                    <h3 class="font-bold text-sm text-slate-900">
                                                        Daftar Peserta: [{{ prog.course_code }}] {{ prog.session_title }}
                                                    </h3>
                                                    <p class="text-[11px] text-slate-500 mt-0.5">
                                                        Kapasitas: {{ prog.approvedCount }} / {{ prog.quota }} Peserta &bull; Jadwal: {{ prog.session_date }} &bull; Trainer: {{ prog.trainer_name }}
                                                    </p>
                                                </div>
                                                <button
                                                    type="button"
                                                    @click="expandedProgramId = null"
                                                    class="px-2.5 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-100 text-slate-600 text-xs font-semibold"
                                                >
                                                    Tutup Rincian ▲
                                                </button>
                                            </div>

                                            <div class="overflow-x-auto">
                                                <table class="w-full border-collapse text-xs">
                                                    <thead>
                                                        <tr class="bg-slate-50 text-slate-700 border-b border-slate-200 uppercase text-[9px] font-bold">
                                                            <th class="py-2.5 px-3 text-center w-10">No</th>
                                                            <th class="py-2.5 px-3 text-left min-w-[220px]">Karyawan &amp; Organisasi</th>
                                                            <th class="py-2.5 px-3 text-center min-w-[130px]">Status</th>
                                                            <th class="py-2.5 px-3 text-center min-w-[100px]">Progres</th>
                                                            <th class="py-2.5 px-3 text-right min-w-[160px]">Aksi L&amp;D</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-slate-100">
                                                        <tr v-for="(pEnr, pIdx) in prog.participants" :key="'p-' + pEnr.id" class="hover:bg-slate-50">
                                                            <td class="py-2.5 px-3 text-center text-slate-400 font-mono">{{ pIdx + 1 }}</td>
                                                            <td class="py-2.5 px-3">
                                                                <div class="font-bold text-slate-900">{{ pEnr.user?.name }}</div>
                                                                <div class="text-[10px] text-slate-500">ID: {{ pEnr.user?.nik }} &bull; {{ pEnr.user?.department }}</div>
                                                            </td>
                                                            <td class="py-2.5 px-3 text-center">
                                                                <span :class="['px-2 py-0.5 rounded-full text-[9px] font-bold uppercase border', pEnr.status === 'approved' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-amber-50 text-amber-800 border-amber-200']">
                                                                    {{ pEnr.status }}
                                                                </span>
                                                            </td>
                                                            <td class="py-2.5 px-3 text-center font-bold text-blue-600">{{ pEnr.progress_percentage || 0 }}%</td>
                                                            <td class="py-2.5 px-3 text-right">
                                                                <button v-if="pEnr.status !== 'approved' && isLndAdmin" type="button" @click="quickApprove(pEnr)" class="px-2 py-0.5 rounded bg-emerald-600 text-white text-[10px] font-bold mr-1">Setujui</button>
                                                                <button v-if="isLndAdmin" type="button" @click="openRejectModal(pEnr)" class="px-2 py-0.5 rounded bg-rose-50 text-rose-700 border border-rose-200 text-[10px] font-bold">Tolak</button>
                                                            </td>
                                                        </tr>
                                                        <tr v-if="!prog.participants.length">
                                                            <td colspan="5" class="py-6 text-center text-slate-400">Belum ada peserta yang didaftarkan ke sesi ini.</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            <tr v-if="!filteredRunningPrograms.length">
                                <td colspan="6" class="py-10 text-center text-slate-400">Tidak ada program pelatihan berjalan yang ditemukan.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- TAB 2: REKAP AUDIT SEMUA PENDAFTAR                        -->
        <!-- ========================================================= -->
        <div v-else-if="enrollmentTab === 'all_participants'" class="space-y-4">
        <!-- HEADER AKSI & BANNER ROLE -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white border border-slate-200 rounded-xl p-4 mb-4 shadow-sm">
            <div>
                <h1 class="text-base font-bold text-slate-900">
                    Pusat Administrasi Pendaftaran &amp; Kelola Pendaftar
                </h1>
                <p class="text-xs text-slate-500 mt-0.5">
                    {{ isLndAdmin
                        ? 'Tinjau, setujui atau tolak pengajuan pendaftaran, dan hubungkan peserta ke batch/program kalender berjalan.'
                        : 'Daftarkan karyawan internal atau kontraktor Anda ke 1 topik pelatihan atau banyak program sekaligus.' }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    @click="openBulkEnrollModal"
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold shadow-sm transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>+ Formulir Pendaftaran Training (Bulk Add)</span>
                </button>
            </div>
        </div>

        <!-- BARIS AKSI MASSAL (KETIKA BARIS DICENTANG) -->
        <div v-if="selectedEnrollmentIds.length && isLndAdmin" class="mb-4 p-3 rounded-xl bg-amber-500 text-white flex flex-wrap items-center justify-between gap-3 shadow-md animate-in fade-in duration-200">
            <div class="flex items-center gap-2 text-xs font-bold">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>{{ selectedEnrollmentIds.length }} Pendaftaran Terpilih</span>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    @click="bulkApprove"
                    class="px-3 py-1.5 rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold shadow-sm transition-colors"
                >
                    Setujui Terpilih (Bulk Approve)
                </button>
                <button
                    type="button"
                    @click="openBulkLinkSessionModal"
                    class="px-3 py-1.5 rounded-lg bg-blue-800 hover:bg-blue-900 text-white text-xs font-bold shadow-sm transition-colors"
                >
                    Hubungkan ke Sesi Berjalan
                </button>
                <button
                    type="button"
                    @click="openBulkRejectModal"
                    class="px-3 py-1.5 rounded-lg bg-rose-700 hover:bg-rose-800 text-white text-xs font-bold shadow-sm transition-colors"
                >
                    Tolak Terpilih (Bulk Reject)
                </button>
                <button
                    type="button"
                    @click="selectedEnrollmentIds = []"
                    class="px-2.5 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-amber-100 text-xs font-semibold"
                >
                    Batal
                </button>
            </div>
        </div>

        <!-- GLOBAL MULTI-ATTRIBUTE FILTER BAR -->
        <GlobalTableFilterBar
            route-url="/admin/enrollments"
            :filters="filters"
            :extra-params="selectedSession ? { session_id: selectedSession.id } : {}"
            :filter-options="filterOptions"
            :show-status-filter="true"
            export-label="Export Ledger CSV"
            search-placeholder="Cari Nama Karyawan, NIK, Posisi, Departemen, atau Program Training..."
        />

        <!-- TABEL KELOLA PENDAFTAR & APPROVAL QUEUE -->
        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm mb-6">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-50 text-slate-700 border-b border-slate-200 uppercase text-[10px] tracking-wider font-bold">
                            <th v-if="isLndAdmin" class="py-3.5 px-3 text-center w-10">
                                <input
                                    type="checkbox"
                                    v-model="selectAllRows"
                                    class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                    title="Pilih Semua di Halaman Ini"
                                />
                            </th>
                            <th class="py-3.5 px-4 text-left min-w-[240px]">Karyawan &amp; Organisasi</th>
                            <th class="py-3.5 px-4 text-left min-w-[200px]">Program Pelatihan</th>
                            <th class="py-3.5 px-3 text-center min-w-[130px]">Status &amp; Alasan</th>
                            <th class="py-3.5 px-3 text-left min-w-[210px]">Sesi Program Berjalan (Kalender)</th>
                            <th class="py-3.5 px-3 text-center min-w-[110px]">Progres Modul</th>
                            <th class="py-3.5 px-4 text-right min-w-[160px]">Aksi Kelola</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <tr
                            v-for="enr in enrollments.data"
                            :key="enr.id"
                            :class="['group transition-colors', selectedEnrollmentIds.includes(enr.id) ? 'bg-blue-50/50' : 'hover:bg-slate-50']"
                        >
                            <!-- Checkbox Select -->
                            <td v-if="isLndAdmin" class="py-3 px-3 text-center">
                                <input
                                    type="checkbox"
                                    :checked="selectedEnrollmentIds.includes(enr.id)"
                                    @change="toggleSelectRow(enr.id)"
                                    class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                />
                            </td>

                            <!-- Karyawan & Organisasi (NIK, Dept, Posisi, Entitas) -->
                            <td class="py-3 px-4">
                                <div class="font-bold text-slate-900 text-sm">{{ enr.user.name }}</div>
                                <div class="text-[11px] text-slate-600 mt-0.5 flex flex-wrap gap-x-2">
                                    <span class="font-mono text-slate-800 font-semibold">ID: {{ enr.user.nik }}</span>
                                    <span>&bull; {{ enr.user.department || '-' }}</span>
                                    <span v-if="enr.user.position">&bull; {{ enr.user.position }}</span>
                                </div>
                                <div class="text-[10px] text-slate-500 mt-0.5">
                                    <span :class="['font-semibold px-1.5 py-0.2 rounded', enr.user.organization_type === 'contractor' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700']">
                                        {{ enr.user.organization_name || enr.user.entity || '-' }}
                                    </span>
                                    <span class="ml-1 text-slate-400">Diajukan: {{ enr.registrar?.name || 'Admin' }} ({{ enr.created_at }})</span>
                                </div>
                            </td>

                            <!-- Program Pelatihan -->
                            <td class="py-3 px-4">
                                <div class="font-bold text-slate-900">
                                    <span class="text-blue-700 font-mono">[{{ enr.course.code }}]</span> {{ enr.course.title }}
                                </div>
                                <div class="mt-1 flex items-center gap-1.5 text-[10px]">
                                    <span :class="['px-1.5 py-0.5 rounded font-bold uppercase', enr.course.is_mandatory ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-700']">
                                        {{ enr.course.is_mandatory ? 'Mandatory' : 'Non-Mandatory' }}
                                    </span>
                                    <span class="text-slate-500">&bull; {{ enr.course.training_category }}</span>
                                </div>
                            </td>

                            <!-- Status & Alasan Penolakan -->
                            <td class="py-3 px-3 text-center">
                                <span
                                    :class="[
                                        'px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider border',
                                        enr.status === 'approved' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' :
                                        enr.status === 'pending' ? 'bg-amber-50 text-amber-800 border-amber-200' :
                                        'bg-rose-50 text-rose-800 border-rose-200'
                                    ]"
                                >
                                    {{ enr.status === 'approved' ? 'Disetujui' : enr.status === 'pending' ? 'Menunggu L&D' : 'Ditolak' }}
                                </span>

                                <!-- Alasan jika Ditolak -->
                                <div v-if="enr.status === 'rejected'" class="mt-1.5 p-1.5 rounded bg-rose-50 border border-rose-100 text-left text-[10px] text-rose-800">
                                    <strong class="font-bold block text-rose-900">Alasan:</strong>
                                    <span>{{ enr.rejection_reason || 'Tidak memenuhi kualifikasi.' }}</span>
                                    <span v-if="enr.reviewed_by" class="block text-[9px] text-rose-600 mt-0.5">Oleh: {{ enr.reviewed_by }}</span>
                                </div>
                            </td>

                            <!-- Sesi Program Berjalan (Kalender) -->
                            <td class="py-3 px-3 text-left">
                                <div v-if="enr.attendance_session" class="p-2 rounded-lg bg-blue-50/70 border border-blue-200 text-blue-900">
                                    <div class="font-bold text-[11px] truncate">{{ enr.attendance_session.title }}</div>
                                    <div class="text-[10px] text-blue-800 mt-0.5 flex flex-wrap gap-x-2">
                                        <span>📅 {{ enr.attendance_session.session_date }} ({{ enr.attendance_session.start_time }}-{{ enr.attendance_session.end_time }})</span>
                                        <span>📍 {{ enr.attendance_session.location }}</span>
                                    </div>
                                    <div class="text-[10px] text-blue-700 mt-0.5 font-medium">
                                        Trainer: {{ enr.attendance_session.trainer_name }}
                                    </div>
                                    <button
                                        v-if="isLndAdmin"
                                        type="button"
                                        @click="openLinkSessionModal(enr)"
                                        class="mt-1 text-[9px] font-bold text-blue-700 hover:text-blue-900 underline"
                                    >
                                        Ubah Sesi &rarr;
                                    </button>
                                </div>
                                <div v-else class="text-slate-400 text-[11px] italic">
                                    <span>Belum terhubung ke sesi</span>
                                    <div v-if="isLndAdmin && enr.status === 'approved'">
                                        <button
                                            type="button"
                                            @click="openLinkSessionModal(enr)"
                                            class="mt-1 inline-flex items-center gap-1 px-2 py-0.5 rounded bg-blue-100 hover:bg-blue-200 text-blue-800 text-[10px] font-bold"
                                        >
                                            + Hubungkan ke Sesi Berjalan
                                        </button>
                                    </div>
                                </div>
                            </td>

                            <!-- Progres Modul -->
                            <td class="py-3 px-3 text-center">
                                <div class="font-extrabold text-xs" :class="enr.progress_percentage === 100 ? 'text-emerald-700' : 'text-blue-600'">
                                    {{ enr.progress_percentage }}%
                                </div>
                                <div class="w-16 mx-auto bg-slate-100 h-1.5 rounded mt-1 overflow-hidden">
                                    <div
                                        :class="['h-full transition-all duration-300', enr.progress_percentage === 100 ? 'bg-emerald-500' : 'bg-blue-600']"
                                        :style="{ width: `${enr.progress_percentage}%` }"
                                    ></div>
                                </div>
                            </td>

                            <!-- Aksi Kelola (Approve, Reject, Link) -->
                            <td class="py-3 px-4 text-right">
                                <div v-if="isLndAdmin" class="flex items-center justify-end gap-1.5">
                                    <!-- Tombol Setujui -->
                                    <button
                                        v-if="enr.status !== 'approved'"
                                        type="button"
                                        @click="quickApprove(enr.id)"
                                        class="px-2.5 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-[10px] font-bold shadow-xs transition-colors"
                                        title="Setujui pendaftaran peserta ini"
                                    >
                                        Setujui
                                    </button>

                                    <!-- Tombol Hubungkan Sesi -->
                                    <button
                                        type="button"
                                        @click="openLinkSessionModal(enr)"
                                        class="px-2.5 py-1 rounded-lg border border-blue-300 bg-blue-50 hover:bg-blue-100 text-blue-800 text-[10px] font-bold transition-colors"
                                        title="Hubungkan ke sesi kelas program berjalan"
                                    >
                                        Hubungkan Sesi
                                    </button>

                                    <!-- Tombol Tolak -->
                                    <button
                                        v-if="enr.status !== 'rejected'"
                                        type="button"
                                        @click="openRejectModal(enr)"
                                        class="px-2.5 py-1 rounded-lg border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 text-[10px] font-bold transition-colors"
                                        title="Tolak pendaftaran peserta ini dengan alasan"
                                    >
                                        Tolak
                                    </button>
                                </div>
                                <div v-else class="text-right text-[11px] text-slate-500">
                                    <span v-if="enr.status === 'pending'" class="text-amber-700 font-medium">Dalam Review</span>
                                    <span v-else-if="enr.status === 'approved'" class="text-emerald-700 font-medium">Terverifikasi</span>
                                    <span v-else class="text-rose-700 font-medium">Ditolak L&amp;D</span>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="!enrollments.data?.length">
                            <td colspan="7" class="py-10 text-center text-slate-400">
                                Belum ada data pendaftaran training untuk filter yang dipilih.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div
                v-if="enrollments.links?.length > 3"
                class="px-4 py-3 bg-slate-50 border-t border-slate-200 flex items-center justify-between text-xs"
            >
                <div class="text-slate-500">
                    Menampilkan {{ enrollments.from }} - {{ enrollments.to }} dari {{ enrollments.total }} pendaftaran
                </div>
                <div class="flex items-center gap-1">
                    <template v-for="(link, idx) in enrollments.links" :key="idx">
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
                    </template>
                </div>
            </div>
        </div>

        <!-- ==================================================================== -->
        <!-- MODAL FORMULIR PENDAFTARAN TRAINING (BULK ADD MODE 1 & MODE 2)      -->
        <!-- ==================================================================== -->
        <div v-if="bulkEnrollModal.open" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4" @click.self="bulkEnrollModal.open = false">
            <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-3xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
                <!-- Modal Header -->
                <div class="px-5 py-4 bg-slate-900 text-white flex items-center justify-between shrink-0">
                    <div>
                        <span class="text-[10px] font-black uppercase tracking-widest text-blue-400">Administrasi Training Internal</span>
                        <h2 class="text-base font-bold text-white">Formulir Pendaftaran Training Massal (Bulk Add)</h2>
                    </div>
                    <button type="button" @click="bulkEnrollModal.open = false" class="text-slate-400 hover:text-white font-bold p-1">✕</button>
                </div>

                <!-- Mode Switcher: Mode 1 (1 Training ke Banyak) vs Mode 2 (Banyak Training Sekaligus) -->
                <div class="px-5 pt-4 pb-2 bg-slate-50 border-b border-slate-200 shrink-0">
                    <div class="grid grid-cols-2 gap-2 p-1 bg-slate-200/80 rounded-xl text-xs font-bold">
                        <button
                            type="button"
                            @click="bulkEnrollModal.mode = 'single'"
                            :class="['py-2 px-3 rounded-lg transition-all', bulkEnrollModal.mode === 'single' ? 'bg-white text-blue-700 shadow-sm' : 'text-slate-600 hover:text-slate-900']"
                        >
                            Mode 1: 1 Training untuk Banyak Karyawan
                        </button>
                        <button
                            type="button"
                            @click="bulkEnrollModal.mode = 'multi'"
                            :class="['py-2 px-3 rounded-lg transition-all', bulkEnrollModal.mode === 'multi' ? 'bg-white text-blue-700 shadow-sm' : 'text-slate-600 hover:text-slate-900']"
                        >
                            Mode 2: Banyak Training Sekaligus (Multi-Training)
                        </button>
                    </div>
                </div>

                <!-- Modal Body Scrollable -->
                <div class="p-5 overflow-y-auto space-y-4 text-xs flex-1">
                    <!-- SECTION PILIH TRAINING -->
                    <div v-if="bulkEnrollModal.mode === 'single'" class="space-y-3">
                        <div>
                            <label class="block font-bold text-slate-800 mb-1">Pilih Program Training <span class="text-rose-600">*</span></label>
                            <select
                                v-model="bulkEnrollModal.singleCourseId"
                                class="w-full rounded-lg border-slate-300 text-xs py-2 px-3 bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                            >
                                <option value="" disabled>Pilih salah satu program pelatihan</option>
                                <option v-for="c in courses" :key="c.id" :value="c.id">
                                    [{{ c.code }}] {{ c.title }} ({{ c.is_mandatory ? 'Mandatory' : 'Non-Mandatory' }} - {{ c.training_category }})
                                </option>
                            </select>
                        </div>

                        <!-- Hubungkan ke Sesi Berjalan Langsung (Opsional saat pendaftaran) -->
                        <div v-if="activeSessionsForSingleCourse.length" class="p-3 rounded-xl bg-blue-50/80 border border-blue-200">
                            <label class="block font-bold text-blue-900 mb-1">Hubungkan ke Sesi Program Berjalan (Opsional):</label>
                            <select
                                v-model="bulkEnrollModal.attendanceSessionId"
                                class="w-full rounded-lg border-blue-300 text-xs py-2 px-3 bg-white focus:border-blue-600 focus:ring-1 focus:ring-blue-600 text-slate-800"
                            >
                                <option value="">Tanpa Sesi Sekarang (Dihubungkan kemudian oleh Tim L&amp;D)</option>
                                <option v-for="s in activeSessionsForSingleCourse" :key="s.id" :value="s.id">
                                    {{ s.title }} &bull; {{ s.session_date }} ({{ s.start_time }}-{{ s.end_time }}) &bull; Trainer: {{ s.trainer_name }} &bull; Sisa Kuota: {{ s.remaining_quota }}
                                </option>
                            </select>
                            <p class="text-[10px] text-blue-700 mt-1">
                                Jika dipilih, peserta yang didaftarkan akan otomatis terhubung ke kalender dan kuota kelas sesi ini.
                            </p>
                        </div>
                    </div>

                    <!-- MODE 2: PILIH BANYAK TRAINING -->
                    <div v-else class="space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="font-bold text-slate-800">Pilih Topik Training yang Akan Diambil <span class="text-rose-600">*</span></label>
                            <span class="text-[11px] text-blue-700 font-bold">{{ bulkEnrollModal.multiCourseIds.length }} program dipilih</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-48 overflow-y-auto border border-slate-200 rounded-xl p-2.5 bg-slate-50">
                            <label
                                v-for="c in courses"
                                :key="`multi-course-${c.id}`"
                                :class="['flex items-start gap-2 p-2 rounded-lg border cursor-pointer transition-colors', bulkEnrollModal.multiCourseIds.includes(c.id) ? 'bg-blue-50 border-blue-300' : 'bg-white border-slate-200 hover:bg-slate-100']"
                            >
                                <input
                                    type="checkbox"
                                    :value="c.id"
                                    v-model="bulkEnrollModal.multiCourseIds"
                                    class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 mt-0.5"
                                />
                                <div class="min-w-0">
                                    <div class="font-bold text-slate-900 truncate">[{{ c.code }}] {{ c.title }}</div>
                                    <div class="text-[10px] text-slate-500">{{ c.training_category }} &bull; {{ c.is_mandatory ? 'Mandatory' : 'Non-Mandatory' }}</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- SECTION PILIH KARYAWAN (NAMA, ID, DEPT, POSISI, ENTITAS) -->
                    <div class="space-y-2 pt-2 border-t border-slate-200">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <div>
                                <label class="font-bold text-slate-800">Pilih Karyawan yang Didaftarkan <span class="text-rose-600">*</span></label>
                                <span class="ml-2 text-[11px] text-slate-500">
                                    {{ bulkEnrollModal.selectedWorkerIds.length }} dari {{ scopedWorkers.length }} karyawan dipilih
                                </span>
                            </div>
                            <div class="flex items-center gap-2">
                                <input
                                    type="search"
                                    v-model="bulkEnrollModal.workerSearch"
                                    placeholder="Cari Nama / NIK / Posisi..."
                                    class="w-48 rounded-lg border-slate-300 text-xs py-1.5 px-2.5"
                                />
                                <button
                                    type="button"
                                    @click="toggleSelectAllModalWorkers"
                                    class="px-2 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-[10px] font-bold text-slate-700"
                                >
                                    Pilih Semua
                                </button>
                            </div>
                        </div>

                        <!-- Daftar Pekerja Scoped -->
                        <div class="max-h-60 overflow-y-auto border border-slate-200 rounded-xl divide-y divide-slate-100 bg-white">
                            <label
                                v-for="w in filteredWorkersForModal"
                                :key="`worker-${w.id}`"
                                :class="['flex items-center gap-3 p-2.5 cursor-pointer hover:bg-slate-50 transition-colors', bulkEnrollModal.selectedWorkerIds.includes(w.id) ? 'bg-blue-50/60' : '']"
                            >
                                <input
                                    type="checkbox"
                                    :value="w.id"
                                    v-model="bulkEnrollModal.selectedWorkerIds"
                                    class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                />
                                <div class="min-w-0 flex-1">
                                    <div class="font-bold text-slate-900">{{ w.name }}</div>
                                    <div class="text-[10px] text-slate-600 flex flex-wrap gap-x-2">
                                        <span class="font-mono text-slate-800">ID: {{ w.nik }}</span>
                                        <span>&bull; Dept: {{ w.department }}</span>
                                        <span v-if="w.position">&bull; Posisi: {{ w.position }}</span>
                                        <span>&bull; Perusahaan: {{ w.entity }}</span>
                                    </div>
                                </div>
                            </label>
                            <div v-if="!filteredWorkersForModal.length" class="p-6 text-center text-slate-400">
                                Karyawan tidak ditemukan untuk kata kunci tersebut.
                            </div>
                        </div>
                    </div>

                    <!-- RINGKASAN SUBMIT -->
                    <div class="p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 flex items-center justify-between">
                        <div>
                            <span class="font-bold block">Status Pendaftaran:</span>
                            <span class="text-[11px]">
                                {{ isLndAdmin
                                    ? 'Pendaftaran akan langsung terkonfirmasi & disetujui (L&D Approval).'
                                    : 'Pendaftaran akan berstatus Pending dan masuk antrean persetujuan Tim L&D.' }}
                            </span>
                        </div>
                        <div class="text-right font-black text-amber-900 text-sm">
                            {{ bulkEnrollModal.mode === 'single'
                                ? `${bulkEnrollModal.selectedWorkerIds.length} Pendaftaran`
                                : `${bulkEnrollModal.selectedWorkerIds.length * bulkEnrollModal.multiCourseIds.length} Total Pendaftaran` }}
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="px-5 py-3.5 bg-slate-50 border-t border-slate-200 flex items-center justify-end gap-2 shrink-0">
                    <button
                        type="button"
                        @click="bulkEnrollModal.open = false"
                        class="px-4 py-2 rounded-xl border border-slate-300 text-slate-700 text-xs font-bold hover:bg-slate-100"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        @click="submitBulkEnroll"
                        :disabled="!bulkEnrollModal.selectedWorkerIds.length || (bulkEnrollModal.mode === 'single' ? !bulkEnrollModal.singleCourseId : !bulkEnrollModal.multiCourseIds.length)"
                        class="px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white text-xs font-bold shadow-sm transition-colors"
                    >
                        Kirim Pendaftaran
                    </button>
                </div>
            </div>
        </div>

        <!-- ==================================================================== -->
        <!-- MODAL TOLAK PENDAFTARAN (DENGAN ALASAN PENOLAKAN L&D)                -->
        <!-- ==================================================================== -->
        <div v-if="rejectionModal.open" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4" @click.self="rejectionModal.open = false">
            <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden">
                <div class="px-5 py-3.5 bg-rose-600 text-white flex items-center justify-between">
                    <div>
                        <span class="text-[10px] font-black uppercase tracking-widest text-rose-200">Moderasi Pendaftaran L&amp;D</span>
                        <h3 class="text-sm font-bold text-white">Tolak Pendaftaran Pelatihan</h3>
                    </div>
                    <button type="button" @click="rejectionModal.open = false" class="text-white hover:text-rose-200 font-bold p-1">✕</button>
                </div>
                <div class="p-5 space-y-3.5 text-xs">
                    <p class="text-slate-600">
                        {{ rejectionModal.isBulk
                            ? `Anda akan menolak ${selectedEnrollmentIds.length} pendaftaran sekaligus. Masukkan alasan penolakan untuk diinformasikan ke pengaju.`
                            : `Anda akan menolak pendaftaran ${rejectionModal.enrollment?.user?.name} pada [${rejectionModal.enrollment?.course?.code}] ${rejectionModal.enrollment?.course?.title}.` }}
                    </p>

                    <div>
                        <label class="block font-bold text-slate-800 mb-1">Pilih Alasan Standar:</label>
                        <select
                            v-model="rejectionModal.reasonPreset"
                            class="w-full rounded-lg border-slate-300 text-xs py-2 px-3 bg-white focus:border-rose-500 focus:ring-1 focus:ring-rose-500"
                        >
                            <option v-for="preset in rejectionPresets" :key="preset" :value="preset">
                                {{ preset }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-800 mb-1">Catatan Tambahan / Alasan Khusus (Opsional):</label>
                        <textarea
                            v-model="rejectionModal.customReason"
                            rows="3"
                            placeholder="Tuliskan keterangan detail alasan penolakan jika diperlukan..."
                            class="w-full rounded-lg border-slate-300 text-xs p-3 focus:border-rose-500 focus:ring-1 focus:ring-rose-500"
                        ></textarea>
                    </div>

                    <div class="p-3 rounded-lg bg-rose-50 border border-rose-200 text-rose-900 text-[11px]">
                        <strong>Perhatian:</strong> Karyawan atau Admin pengaju dapat melihat alasan penolakan ini pada riwayat pendaftaran mereka.
                    </div>
                </div>
                <div class="px-5 py-3.5 bg-slate-50 border-t border-slate-200 flex items-center justify-end gap-2">
                    <button
                        type="button"
                        @click="rejectionModal.open = false"
                        class="px-4 py-2 rounded-xl border border-slate-300 text-slate-700 text-xs font-bold hover:bg-slate-100"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        @click="submitRejection"
                        class="px-5 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold shadow-sm transition-colors"
                    >
                        Konfirmasi Tolak Pendaftaran
                    </button>
                </div>
            </div>
        </div>

        <!-- ==================================================================== -->
        <!-- MODAL HUBUNGKAN KE TRAINING PROGRAM BERJALAN (CALENDAR SESSION LINK) -->
        <!-- ==================================================================== -->
        <div v-if="linkSessionModal.open" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4" @click.self="linkSessionModal.open = false">
            <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-xl shadow-2xl overflow-hidden">
                <div class="px-5 py-3.5 bg-blue-700 text-white flex items-center justify-between">
                    <div>
                        <span class="text-[10px] font-black uppercase tracking-widest text-blue-200">Koneksi Jadwal &amp; Kuota Kalender</span>
                        <h3 class="text-sm font-bold text-white">Hubungkan ke Sesi Program Berjalan</h3>
                    </div>
                    <button type="button" @click="linkSessionModal.open = false" class="text-white hover:text-blue-200 font-bold p-1">✕</button>
                </div>
                <div class="p-5 space-y-3.5 text-xs">
                    <p class="text-slate-600">
                        {{ linkSessionModal.isBulk
                            ? `Pilih sesi pelatihan aktif untuk ${selectedEnrollmentIds.length} peserta terpilih.`
                            : `Tempatkan ${linkSessionModal.enrollment?.user?.name} ke sesi kelas pelatihan berjalan:` }}
                    </p>

                    <div class="space-y-2 max-h-72 overflow-y-auto">
                        <label
                            v-for="s in availableSessionsForModal"
                            :key="`modal-session-${s.id}`"
                            :class="[
                                'block p-3 rounded-xl border cursor-pointer transition-all',
                                linkSessionModal.selectedSessionId === s.id ? 'bg-blue-50 border-blue-500 ring-2 ring-blue-500/20' : 'bg-white border-slate-200 hover:bg-slate-50'
                            ]"
                        >
                            <div class="flex items-start gap-3">
                                <input
                                    type="radio"
                                    :value="s.id"
                                    v-model="linkSessionModal.selectedSessionId"
                                    class="mt-1 text-blue-600 focus:ring-blue-500"
                                />
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center justify-between">
                                        <span class="font-bold text-slate-900 text-xs">{{ s.title }}</span>
                                        <span :class="['px-2 py-0.5 rounded text-[10px] font-extrabold', s.remaining_quota > 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800']">
                                            Sisa: {{ s.remaining_quota }} / {{ s.quota ?? '∞' }}
                                        </span>
                                    </div>
                                    <div class="text-[11px] text-blue-700 font-semibold mt-0.5">
                                        [{{ s.course_code }}] {{ s.course_title }}
                                    </div>
                                    <div class="text-[10px] text-slate-500 mt-1 flex flex-wrap gap-x-2">
                                        <span>📅 {{ s.session_date }} ({{ s.start_time }} - {{ s.end_time }})</span>
                                        <span>📍 {{ s.location }}</span>
                                        <span>👨‍🏫 Trainer: {{ s.trainer_name }}</span>
                                    </div>
                                </div>
                            </div>
                        </label>

                        <div v-if="!availableSessionsForModal.length" class="p-6 text-center text-slate-400 bg-slate-50 rounded-xl">
                            Belum ada jadwal sesi aktif untuk program pelatihan ini. Anda dapat membuat sesi baru pada menu Jadwal Training (Kalender).
                        </div>
                    </div>
                </div>
                <div class="px-5 py-3.5 bg-slate-50 border-t border-slate-200 flex items-center justify-end gap-2">
                    <button
                        type="button"
                        @click="linkSessionModal.open = false"
                        class="px-4 py-2 rounded-xl border border-slate-300 text-slate-700 text-xs font-bold hover:bg-slate-100"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        @click="submitLinkSession"
                        :disabled="!linkSessionModal.selectedSessionId"
                        class="px-5 py-2 rounded-xl bg-blue-700 hover:bg-blue-800 disabled:opacity-50 text-white text-xs font-bold shadow-sm transition-colors"
                    >
                        Hubungkan &amp; Setujui
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
