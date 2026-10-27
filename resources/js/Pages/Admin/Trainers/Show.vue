<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    trainer: {
        type: Object,
        required: true,
    },
    currentMonth: {
        type: String,
        default: () => new Date().toISOString().substring(0, 7),
    },
    calendarDays: {
        type: Array,
        default: () => [],
    },
    scheduleConflicts: {
        type: Array,
        default: () => [],
    },
    authorizedSkills: {
        type: Array,
        default: () => [],
    },
    availableSkills: {
        type: Array,
        default: () => [],
    },
    teachingLogs: {
        type: Array,
        default: () => [],
    },
    activeCourses: {
        type: Array,
        default: () => [],
    },
    kpiTasks: {
        type: Array,
        default: () => [],
    },
    evaluationSummary: {
        type: Object,
        default: () => ({
            overall_avg: null,
            avg_material_mastery: null,
            avg_delivery_method: null,
            avg_punctuality: null,
            total_evaluations: 0,
        }),
    },
    feedbackList: {
        type: Array,
        default: () => [],
    },
});

// Tab Aktif (Default: 'skills')
const activeTab = ref('skills');

// --- TAB 1: MODAL TAMBAH KOMPETENSI AJAR ---
const showAddSkillModal = ref(false);
const skillForm = useForm({
    skill_id: '',
    license_type: 'ToT BNSP Level 4',
    license_number: '',
    valid_until: '',
    notes: '',
});

const submitAddSkill = () => {
    skillForm.post(`/admin/trainers/${props.trainer.id}/authorized-skills`, {
        preserveScroll: true,
        onSuccess: () => {
            showAddSkillModal.value = false;
            skillForm.reset();
        },
    });
};

const deleteSkill = (skill) => {
    if (confirm(`Hapus kompetensi "${skill.code} - ${skill.name}" dari daftar ajar Trainer ini?`)) {
        router.delete(`/admin/trainers/${props.trainer.id}/authorized-skills/${skill.id}`, {
            preserveScroll: true,
        });
    }
};

// --- TAB 2: KALENDER ROSTER & BULK SET ---
const showSingleRosterModal = ref(false);
const selectedDateForRoster = ref(null);
const singleRosterForm = useForm({
    roster_date: '',
    status: 'on_duty',
    activity_type: 'TRAINING_CLASS',
    course_id: '',
    location: '',
    shift: 'day',
    notes: '',
});

const normalizeRosterForm = (form, changedField) => {
    const unavailableStatuses = ['off_roster', 'annual_leave', 'sick'];
    if (changedField === 'status') {
        if (unavailableStatuses.includes(form.status)) {
            form.activity_type = 'OFF';
        } else if (form.activity_type === 'OFF') {
            form.activity_type = form.status === 'site_visit' ? 'FIELD_OBSERVATION' : 'STANDBY';
        }
    } else if (form.activity_type === 'OFF') {
        form.status = 'off_roster';
    } else if (unavailableStatuses.includes(form.status)) {
        form.status = 'on_duty';
    }
    if (form.activity_type !== 'TRAINING_CLASS') form.course_id = '';
};

const openSingleRosterModal = (day) => {
    selectedDateForRoster.value = day;
    singleRosterForm.roster_date = day.date;
    singleRosterForm.status = day.status;
    singleRosterForm.activity_type = day.activity_type || (day.status === 'off_roster' ? 'OFF' : 'TRAINING_CLASS');
    singleRosterForm.course_id = day.course_id || '';
    singleRosterForm.location = day.location || '';
    singleRosterForm.shift = day.shift || 'day';
    singleRosterForm.notes = day.notes || '';
    showSingleRosterModal.value = true;
};

const submitSingleRoster = () => {
    if (singleRosterForm.activity_type !== 'TRAINING_CLASS') singleRosterForm.course_id = '';
    singleRosterForm.post(`/admin/trainers/${props.trainer.id}/roster/set`, {
        preserveScroll: true,
        onSuccess: () => {
            showSingleRosterModal.value = false;
        },
    });
};

const showBulkRosterModal = ref(false);
const bulkRosterForm = useForm({
    start_date: '',
    end_date: '',
    status: 'on_duty',
    activity_type: 'TRAINING_CLASS',
    course_id: '',
    location: '',
    shift: 'day',
    notes: '',
});

const submitBulkRoster = () => {
    if (bulkRosterForm.activity_type !== 'TRAINING_CLASS') bulkRosterForm.course_id = '';
    bulkRosterForm.post(`/admin/trainers/${props.trainer.id}/roster/bulk`, {
        preserveScroll: true,
        onSuccess: () => {
            showBulkRosterModal.value = false;
            bulkRosterForm.reset();
        },
    });
};

const changeMonth = (e) => {
    router.get(
        `/admin/trainers/${props.trainer.id}`,
        { month: e.target.value },
        { preserveState: true }
    );
};

// --- TAB 3: LOG MENGAJAR MANUAL & MODAL DETAIL PESERTA QR ---
const showManualLogModal = ref(false);
const manualLogForm = useForm({
    training_title: '',
    course_id: '',
    log_date: new Date().toISOString().substring(0, 10),
    duration_hours: 2.0,
    scanned_participants_count: 0,
    location: 'Site PBJ Training Room',
    notes: '',
});

const submitManualLog = () => {
    manualLogForm.post(`/admin/trainers/${props.trainer.id}/teaching-logs/manual`, {
        preserveScroll: true,
        onSuccess: () => {
            showManualLogModal.value = false;
            manualLogForm.reset();
        },
    });
};

const showParticipantsDrawer = ref(false);
const currentSessionParticipants = ref([]);
const currentSessionInfo = ref(null);
const isLoadingParticipants = ref(false);

const openParticipantsDrawer = async (log) => {
    if (!log.attendance_session_id) {
        currentSessionInfo.value = {
            title: log.training_title,
            session_date: log.log_date_formatted,
            duration_hours: log.duration_hours,
            total_scanned: log.scanned_participants_count,
            is_manual: true,
        };
        currentSessionParticipants.value = [];
        showParticipantsDrawer.value = true;
        return;
    }

    isLoadingParticipants.value = true;
    showParticipantsDrawer.value = true;
    try {
        const response = await fetch(`/admin/attendance-sessions/${log.attendance_session_id}/scanned-participants`);
        const data = await response.json();
        currentSessionInfo.value = data.session;
        currentSessionParticipants.value = data.participants;
    } catch (err) {
        console.error('Gagal mengambil daftar peserta:', err);
    } finally {
        isLoadingParticipants.value = false;
    }
};

// --- TAB 4: TARGET KPI & PENUGASAN ---
const showKpiModal = ref(false);
const kpiForm = useForm({
    title: '',
    description: '',
    period: 'monthly',
    start_date: new Date().toISOString().substring(0, 10),
    due_date: '',
    target_teaching_hours: 20,
    target_participants_count: 50,
    target_rating: 4.5,
});

const submitKpi = () => {
    kpiForm.post(`/admin/trainers/${props.trainer.id}/kpis`, {
        preserveScroll: true,
        onSuccess: () => {
            showKpiModal.value = false;
            kpiForm.reset();
        },
    });
};

const toggleKpiStatus = (kpi, newStatus) => {
    router.patch(
        `/admin/trainers/${props.trainer.id}/kpis/${kpi.id}`,
        { status: newStatus },
        { preserveScroll: true }
    );
};

const deleteKpi = (kpi) => {
    if (confirm(`Hapus target KPI "${kpi.title}"?`)) {
        router.delete(`/admin/trainers/${props.trainer.id}/kpis/${kpi.id}`, {
            preserveScroll: true,
        });
    }
};

const filterKpiPeriod = ref('all');
const filteredKpiTasks = computed(() => {
    if (filterKpiPeriod.value === 'all') return props.kpiTasks;
    return props.kpiTasks.filter((k) => k.period === filterKpiPeriod.value);
});

// Helper Status Badge
const getRosterStatusBadge = (status) => {
    switch (status) {
        case 'on_duty':
            return { bg: 'bg-emerald-50 text-emerald-800 border-emerald-300 ring-1 ring-emerald-500/20', label: 'ON DUTY' };
        case 'off_roster':
            return { bg: 'bg-slate-100 text-slate-700 border-slate-300 ring-1 ring-slate-400/20', label: 'OFF ROSTER' };
        case 'annual_leave':
            return { bg: 'bg-amber-100 text-amber-800 border-amber-300 ring-1 ring-amber-500/20', label: 'CUTI' };
        case 'sick':
            return { bg: 'bg-rose-100 text-rose-800 border-rose-300 ring-1 ring-rose-500/20', label: 'SAKIT' };
        case 'site_visit':
            return { bg: 'bg-indigo-100 text-indigo-800 border-indigo-300 ring-1 ring-indigo-500/20', label: 'SITE' };
        default:
            return { bg: 'bg-slate-50 text-slate-700 border-slate-200', label: status };
    }
};

const totalTeachingHours = computed(() => {
    return props.teachingLogs.reduce((acc, curr) => acc + (Number(curr.duration_hours) || 0), 0);
});

const totalScannedParticipants = computed(() => {
    return props.teachingLogs.reduce((acc, curr) => acc + (Number(curr.scanned_participants_count) || 0), 0);
});
</script>

<template>
    <Head :title="`Detail 360° Trainer: ${trainer.name}`" />

    <AdminLayout
        :title="`Pusat Kendali Trainer: ${trainer.name}`"
        :subtitle="`NIK: ${trainer.nik || '-'} • Departemen: ${trainer.department} • Pola Roster: ${trainer.roster_pattern}`"
    >
        <div class="space-y-6">
            <!-- ============================================================= -->
            <!-- 1. HEADER PROFIL 360° TRAINER (DOUBLE-BEZEL EXECUTIVE HERO)   -->
            <!-- ============================================================= -->
            <section class="p-1 rounded-3xl bg-slate-100/80 ring-1 ring-slate-900/5 shadow-sm">
                <div class="bg-white rounded-[calc(1.5rem-1px)] p-5 sm:p-6 shadow-sm">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-5">
                        <!-- Profile identity info -->
                        <div class="flex items-start sm:items-center gap-4">
                            <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-blue-700 via-indigo-600 to-sky-500 text-white flex items-center justify-center font-black text-2xl uppercase shadow-md shadow-indigo-500/20 shrink-0 ring-4 ring-slate-50">
                                {{ trainer.name ? trainer.name.charAt(0) : 'T' }}
                            </div>

                            <div class="space-y-1.5">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h1 class="text-lg sm:text-xl font-black text-slate-900 tracking-tight">
                                        {{ trainer.name }}
                                    </h1>
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-blue-50 text-blue-700 border border-blue-200/90 ring-1 ring-blue-500/15">
                                        {{ trainer.trainer_type }}
                                    </span>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-black bg-emerald-50 text-emerald-700 border border-emerald-200/90 ring-1 ring-emerald-500/15">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                        Roster Hari Ini: {{ trainer.today_roster }}
                                    </span>
                                </div>

                                <div class="flex flex-wrap items-center gap-2 text-xs text-slate-600 font-medium">
                                    <span class="font-mono bg-slate-100 px-2 py-0.5 rounded-md text-slate-700 font-bold border border-slate-200">NIK: {{ trainer.nik || '-' }}</span>
                                    <span>&bull;</span>
                                    <span>{{ trainer.email }}</span>
                                    <span>&bull;</span>
                                    <span class="text-slate-500">{{ trainer.job_position }}</span>
                                </div>

                                <div class="flex items-center gap-2 text-[11px] text-slate-500">
                                    <span>Pola Kerja:</span>
                                    <span class="font-bold text-slate-800 bg-slate-100/90 px-2 py-0.5 rounded-md border border-slate-200/80">{{ trainer.roster_pattern }}</span>
                                    <span>&bull;</span>
                                    <span>Departemen:</span>
                                    <span class="font-bold text-slate-800">{{ trainer.department }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Back Button -->
                        <div class="flex items-center gap-2 shrink-0">
                            <Link
                                href="/admin/trainers"
                                class="px-4 py-2 text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 active:scale-[0.98] rounded-xl transition-all duration-200 flex items-center gap-2 shadow-sm"
                            >
                                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                                </svg>
                                <span>Kembali ke Daftar</span>
                            </Link>
                        </div>
                    </div>

                    <!-- ALERT BENTROK JADWAL (CONFLICT ALERT BANNER) -->
                    <div
                        v-if="scheduleConflicts.length"
                        class="mt-5 p-4 rounded-2xl bg-gradient-to-r from-rose-50 via-rose-50/80 to-amber-50/60 border border-rose-300 text-rose-950 text-xs shadow-sm"
                    >
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center shrink-0 font-bold text-base border border-rose-200">
                                ⚠️
                            </div>
                            <div class="space-y-1.5 flex-1">
                                <div class="font-black text-rose-950 text-xs uppercase tracking-wide">
                                    Peringatan Bentrok Jadwal Kelas dengan Hari Libur / Cuti Trainer ({{ scheduleConflicts.length }} Kejadian):
                                </div>
                                <ul class="list-disc list-inside space-y-1 text-[11px] text-rose-900 font-medium">
                                    <li v-for="(cf, idx) in scheduleConflicts" :key="idx">
                                        Tanggal <strong class="font-mono bg-white/70 px-1.5 py-0.2 rounded border border-rose-200">{{ cf.date }}</strong>: Sesi "<strong>{{ cf.session_title }}</strong>" dijadwalkan saat status Trainer adalah <span class="uppercase font-bold text-rose-800 bg-rose-200/60 px-1 rounded">{{ cf.roster_status }}</span>.
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- ===================================================== -->
                    <!-- 5 TAB NAVIGASI UTAMA (SEGMENTED HARDWARE DOCK)         -->
                    <!-- ===================================================== -->
                    <div class="mt-6 pt-5 border-t border-slate-100">
                        <nav class="flex items-center gap-1.5 bg-slate-100/90 p-1.5 rounded-2xl ring-1 ring-slate-200/80 overflow-x-auto select-none">
                            <button
                                type="button"
                                @click="activeTab = 'skills'"
                                :class="[
                                    'px-4 py-2 rounded-xl text-xs font-bold transition-all duration-200 whitespace-nowrap flex items-center gap-2',
                                    activeTab === 'skills'
                                        ? 'bg-white text-blue-700 shadow-sm ring-1 ring-black/5'
                                        : 'text-slate-600 hover:text-slate-900 hover:bg-white/60',
                                ]"
                            >
                                <span>1. Kompetensi Ajar</span>
                                <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono font-black" :class="activeTab === 'skills' ? 'bg-blue-100 text-blue-800' : 'bg-slate-200 text-slate-700'">
                                    {{ authorizedSkills.length }}
                                </span>
                            </button>

                            <button
                                type="button"
                                @click="activeTab = 'roster'"
                                :class="[
                                    'px-4 py-2 rounded-xl text-xs font-bold transition-all duration-200 whitespace-nowrap flex items-center gap-2',
                                    activeTab === 'roster'
                                        ? 'bg-white text-blue-700 shadow-sm ring-1 ring-black/5'
                                        : 'text-slate-600 hover:text-slate-900 hover:bg-white/60',
                                ]"
                            >
                                <span>2. Kalender Roster</span>
                            </button>

                            <button
                                type="button"
                                @click="activeTab = 'teaching_logs'"
                                :class="[
                                    'px-4 py-2 rounded-xl text-xs font-bold transition-all duration-200 whitespace-nowrap flex items-center gap-2',
                                    activeTab === 'teaching_logs'
                                        ? 'bg-white text-blue-700 shadow-sm ring-1 ring-black/5'
                                        : 'text-slate-600 hover:text-slate-900 hover:bg-white/60',
                                ]"
                            >
                                <span>3. Riwayat Mengajar &amp; QR</span>
                                <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono font-black" :class="activeTab === 'teaching_logs' ? 'bg-blue-100 text-blue-800' : 'bg-slate-200 text-slate-700'">
                                    {{ teachingLogs.length }}
                                </span>
                            </button>

                            <button
                                type="button"
                                @click="activeTab = 'kpis'"
                                :class="[
                                    'px-4 py-2 rounded-xl text-xs font-bold transition-all duration-200 whitespace-nowrap flex items-center gap-2',
                                    activeTab === 'kpis'
                                        ? 'bg-white text-blue-700 shadow-sm ring-1 ring-black/5'
                                        : 'text-slate-600 hover:text-slate-900 hover:bg-white/60',
                                ]"
                            >
                                <span>4. Penugasan &amp; KPI</span>
                                <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono font-black" :class="activeTab === 'kpis' ? 'bg-blue-100 text-blue-800' : 'bg-slate-200 text-slate-700'">
                                    {{ kpiTasks.length }}
                                </span>
                            </button>

                            <button
                                type="button"
                                @click="activeTab = 'evaluations'"
                                :class="[
                                    'px-4 py-2 rounded-xl text-xs font-bold transition-all duration-200 whitespace-nowrap flex items-center gap-2',
                                    activeTab === 'evaluations'
                                        ? 'bg-white text-blue-700 shadow-sm ring-1 ring-black/5'
                                        : 'text-slate-600 hover:text-slate-900 hover:bg-white/60',
                                ]"
                            >
                                <span>5. Penilaian Peserta</span>
                                <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold" :class="activeTab === 'evaluations' ? 'bg-amber-100 text-amber-800' : 'bg-slate-200 text-slate-700'">
                                    ★ {{ evaluationSummary.overall_avg }}
                                </span>
                            </button>
                        </nav>
                    </div>
                </div>
            </section>

            <!-- ============================================================= -->
            <!-- TAB 1: KOMPETENSI AJAR (CERTIFIED SKILLS)                     -->
            <!-- ============================================================= -->
            <section v-if="activeTab === 'skills'" class="p-1 rounded-3xl bg-slate-100/80 ring-1 ring-slate-900/5 shadow-sm overflow-hidden">
                <div class="bg-white rounded-[calc(1.5rem-1px)] p-5 sm:p-6 shadow-sm">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-100 mb-5">
                        <div>
                            <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">
                                Daftar Kompetensi &amp; Lisensi Mengajar Trainer
                            </h3>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Trainer hanya boleh mengampu kelas pelatihan yang kompetensinya tercantum di bawah ini.
                            </p>
                        </div>
                        <button
                            type="button"
                            @click="showAddSkillModal = true"
                            class="group px-4 py-2 text-xs font-bold text-white bg-gradient-to-r from-blue-700 to-indigo-600 hover:from-blue-800 hover:to-indigo-700 active:scale-[0.98] rounded-xl shadow-sm shadow-blue-500/25 flex items-center gap-2 transition-all duration-200"
                        >
                            <span>+ Tambah Kompetensi Ajar</span>
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-slate-700">
                            <thead class="bg-slate-50/80 border-b border-slate-200/80 text-[10px] font-black uppercase tracking-wider text-slate-500 select-none">
                                <tr>
                                    <th class="py-3 px-4">Kode &amp; Nama Kompetensi</th>
                                    <th class="py-3 px-3">Kategori</th>
                                    <th class="py-3 px-3">Jenis Lisensi ToT</th>
                                    <th class="py-3 px-3">Nomor Sertifikat/Lisensi</th>
                                    <th class="py-3 px-3">Masa Berlaku</th>
                                    <th class="py-3 px-3 text-center">Status</th>
                                    <th class="py-3 px-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="sk in authorizedSkills" :key="sk.id" class="hover:bg-slate-50/80 transition-colors">
                                    <td class="py-3.5 px-4">
                                        <div class="font-mono font-black text-blue-700 text-xs bg-blue-50 px-2 py-0.5 rounded border border-blue-200 inline-block mb-1">
                                            {{ sk.code }}
                                        </div>
                                        <div class="text-slate-900 font-bold">{{ sk.name }}</div>
                                    </td>
                                    <td class="py-3.5 px-3 text-slate-600 font-medium">{{ sk.category }}</td>
                                    <td class="py-3.5 px-3 font-semibold text-slate-800">{{ sk.license_type }}</td>
                                    <td class="py-3.5 px-3 font-mono text-slate-700">{{ sk.license_number }}</td>
                                    <td class="py-3.5 px-3 font-mono text-slate-700">{{ sk.valid_until_formatted }}</td>
                                    <td class="py-3.5 px-3 text-center">
                                        <span
                                            v-if="sk.status === 'active'"
                                            class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-emerald-50 text-emerald-700 border border-emerald-200"
                                        >
                                            Aktif
                                        </span>
                                        <span
                                            v-else-if="sk.status === 'expiring_soon'"
                                            class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-amber-50 text-amber-700 border border-amber-200"
                                        >
                                            Segera Habis (H-60)
                                        </span>
                                        <span
                                            v-else-if="sk.status === 'expired'"
                                            class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-rose-50 text-rose-700 border border-rose-200"
                                        >
                                            Kedaluwarsa
                                        </span>
                                        <span
                                            v-else
                                            class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600 border border-slate-200"
                                        >
                                            Tetap
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-center">
                                        <button
                                            type="button"
                                            @click="deleteSkill(sk)"
                                            class="px-2.5 py-1 text-xs font-bold text-rose-600 hover:text-rose-800 hover:bg-rose-50 rounded-lg transition-colors"
                                        >
                                            Hapus
                                        </button>
                                    </td>
                                </tr>

                                <tr v-if="authorizedSkills.length === 0">
                                    <td colspan="7" class="py-12 text-center text-slate-400">
                                        Belum ada kompetensi ajar yang ditetapkan untuk Trainer ini.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- ============================================================= -->
            <!-- TAB 2: KALENDER ROSTER (MASUK / OFF / CUTI)                   -->
            <!-- ============================================================= -->
            <section v-if="activeTab === 'roster'" class="p-1 rounded-3xl bg-slate-100/80 ring-1 ring-slate-900/5 shadow-sm overflow-hidden">
                <div class="bg-white rounded-[calc(1.5rem-1px)] p-5 sm:p-6 shadow-sm">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-100 mb-5">
                        <div>
                            <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">
                                Jadwal Kalender Roster Kerja Bulanan
                            </h3>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Klik tanggal untuk menyetel On-Duty / Off / Cuti, atau gunakan Bulk Set Date Range.
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <input
                                type="month"
                                :value="currentMonth"
                                @change="changeMonth"
                                class="text-xs font-mono font-bold rounded-xl border border-slate-200 bg-slate-50/70 focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 py-2 px-3 text-slate-800"
                            />
                            <button
                                type="button"
                                @click="showBulkRosterModal = true"
                                class="px-4 py-2 text-xs font-bold text-white bg-slate-900 hover:bg-slate-800 active:scale-[0.98] rounded-xl shadow-sm transition-all whitespace-nowrap"
                            >
                                ⚡ Bulk Set Date Range
                            </button>
                        </div>
                    </div>

                    <!-- Kalender Grid Interaktif -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-7 gap-2.5">
                        <div
                            v-for="day in calendarDays"
                            :key="day.date"
                            @click="openSingleRosterModal(day)"
                            class="p-3 rounded-2xl border cursor-pointer transition-all duration-200 hover:shadow-md hover:-translate-y-0.5 flex flex-col justify-between min-h-[105px] select-none"
                            :class="[
                                day.has_conflict
                                    ? 'border-rose-400 bg-rose-50/80 ring-1 ring-rose-400'
                                    : (day.is_weekend ? 'border-slate-200/80 bg-slate-50/50 hover:bg-white hover:border-blue-400' : 'border-slate-200 bg-white hover:border-blue-400'),
                            ]"
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-black text-slate-900">{{ day.day_number }}</span>
                                <span class="text-[10px] font-extrabold uppercase" :class="day.is_weekend ? 'text-rose-500' : 'text-slate-400'">
                                    {{ day.day_name }}
                                </span>
                            </div>

                            <div class="my-1.5">
                                <span
                                    :class="getRosterStatusBadge(day.status).bg"
                                    class="text-[9px] font-black px-2 py-0.5 rounded-full border block text-center uppercase tracking-wider"
                                >
                                    {{ getRosterStatusBadge(day.status).label }}
                                </span>
                            </div>

                            <div v-if="day.activity_type && day.activity_type !== 'OFF'" class="mt-1">
                                <span
                                    :class="[
                                        day.activity_type === 'TRAINING_CLASS' ? 'bg-blue-50 text-blue-700 border-blue-200' :
                                        day.activity_type === 'FIELD_OBSERVATION' ? 'bg-purple-50 text-purple-700 border-purple-200' :
                                        day.activity_type === 'LND_ADMIN' ? 'bg-amber-50 text-amber-800 border-amber-200' :
                                        'bg-cyan-50 text-cyan-800 border-cyan-200'
                                    ]"
                                    class="inline-block w-full truncate rounded-md border px-1.5 py-0.5 text-[9px] font-bold"
                                >
                                    {{
                                        day.activity_type === 'TRAINING_CLASS'
                                            ? `🎓 ${day.course_title || day.sessions?.[0]?.title || 'Kelas'}`
                                            : day.activity_type === 'FIELD_OBSERVATION'
                                                ? `🚜 ${day.location || 'Pit'}`
                                                : day.activity_type === 'LND_ADMIN'
                                                    ? '📋 Tugas LnD'
                                                    : '⏱️ Standby'
                                    }}
                                </span>
                            </div>

                            <div class="flex items-center justify-between text-[10px] mt-1 pt-1 border-t border-slate-100">
                                <span v-if="day.sessions_count > 0" class="text-blue-700 font-extrabold">
                                    {{ day.sessions_count }} Kelas
                                </span>
                                <span v-else class="text-slate-300">-</span>

                                <span v-if="day.has_conflict" class="text-rose-600 font-black animate-pulse" title="Bentrok Jadwal!">
                                    ⚠️ Bentrok
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ============================================================= -->
            <!-- TAB 3: RIWAYAT MENGAJAR & PESERTA QR                          -->
            <!-- ============================================================= -->
            <section v-if="activeTab === 'teaching_logs'" class="space-y-4">
                <!-- Summary bar -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="p-1 rounded-2xl bg-blue-100/70 ring-1 ring-blue-500/20 shadow-sm">
                        <div class="bg-white rounded-[calc(1rem-1px)] p-3.5">
                            <div class="text-[10px] font-black uppercase tracking-wider text-blue-600">Total Jam Mengajar</div>
                            <div class="text-xl font-black text-blue-700 mt-1">{{ totalTeachingHours }} <span class="text-xs font-semibold">Jam</span></div>
                        </div>
                    </div>
                    <div class="p-1 rounded-2xl bg-indigo-100/70 ring-1 ring-indigo-500/20 shadow-sm">
                        <div class="bg-white rounded-[calc(1rem-1px)] p-3.5">
                            <div class="text-[10px] font-black uppercase tracking-wider text-indigo-600">Total Peserta QR</div>
                            <div class="text-xl font-black text-indigo-700 mt-1">{{ totalScannedParticipants }} <span class="text-xs font-semibold">Peserta</span></div>
                        </div>
                    </div>
                    <div class="p-1 rounded-2xl bg-slate-200/70 ring-1 ring-slate-900/10 shadow-sm">
                        <div class="bg-white rounded-[calc(1rem-1px)] p-3.5">
                            <div class="text-[10px] font-black uppercase tracking-wider text-slate-600">Jumlah Sesi Tercatat</div>
                            <div class="text-xl font-black text-slate-800 mt-1">{{ teachingLogs.length }} <span class="text-xs font-semibold">Sesi</span></div>
                        </div>
                    </div>
                </div>

                <div class="p-1 rounded-3xl bg-slate-100/80 ring-1 ring-slate-900/5 shadow-sm overflow-hidden">
                    <div class="bg-white rounded-[calc(1.5rem-1px)] p-5 sm:p-6 shadow-sm">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-100 mb-5">
                            <div>
                                <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">
                                    Rekam Jejak Sesi Mengajar &amp; Akumulasi Jam (Teaching Hours)
                                </h3>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    Hasil sinkronisasi otomatis scan QR peserta dan pencatatan jam mengajar manual oleh Admin LnD.
                                </p>
                            </div>
                            <button
                                type="button"
                                @click="showManualLogModal = true"
                                class="px-4 py-2 text-xs font-bold text-white bg-gradient-to-r from-blue-700 to-indigo-600 hover:from-blue-800 hover:to-indigo-700 active:scale-[0.98] rounded-xl shadow-sm shadow-blue-500/25 flex items-center gap-2 transition-all shrink-0"
                            >
                                <span>+ Input Jam Mengajar Manual</span>
                            </button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs text-slate-700">
                                <thead class="bg-slate-50/80 border-b border-slate-200/80 text-[10px] font-black uppercase tracking-wider text-slate-500 select-none">
                                    <tr>
                                        <th class="py-3 px-4">Tanggal</th>
                                        <th class="py-3 px-3">Nama Pelatihan &amp; Kode Kelas</th>
                                        <th class="py-3 px-3">Sumber Input</th>
                                        <th class="py-3 px-3 text-right">Durasi Mengajar</th>
                                        <th class="py-3 px-3 text-center">Peserta Hadir (Scan QR)</th>
                                        <th class="py-3 px-4">Lokasi / Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="log in teachingLogs" :key="log.id" class="hover:bg-slate-50/80 transition-colors">
                                        <td class="py-3.5 px-4 font-mono font-bold text-slate-800">{{ log.log_date_formatted }}</td>
                                        <td class="py-3.5 px-3">
                                            <div class="font-bold text-slate-900">{{ log.training_title }}</div>
                                            <div class="text-[10px] text-blue-700 font-mono font-bold">{{ log.course_code }}</div>
                                        </td>
                                        <td class="py-3.5 px-3">
                                            <span
                                                v-if="log.entry_source === 'auto_qr_session'"
                                                class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-blue-50 text-blue-700 border border-blue-200"
                                            >
                                                Auto QR Session
                                            </span>
                                            <span
                                                v-else
                                                class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-purple-50 text-purple-700 border border-purple-200"
                                            >
                                                Input Manual LnD
                                            </span>
                                        </td>
                                        <td class="py-3.5 px-3 text-right font-black text-slate-900">
                                            {{ log.duration_hours }} Jam
                                        </td>
                                        <td class="py-3.5 px-3 text-center">
                                            <button
                                                type="button"
                                                @click="openParticipantsDrawer(log)"
                                                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-indigo-50 hover:bg-indigo-600 text-indigo-700 hover:text-white font-extrabold text-xs border border-indigo-200 hover:border-indigo-600 active:scale-[0.98] transition-all cursor-pointer shadow-sm"
                                                title="Klik untuk melihat rincian peserta"
                                            >
                                                <span>👥 {{ log.scanned_participants_count }} Peserta</span>
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                </svg>
                                            </button>
                                        </td>
                                        <td class="py-3.5 px-4 text-slate-600 text-[11px]">
                                            <div>{{ log.location }}</div>
                                            <div v-if="log.notes" class="text-slate-400 italic text-[10px]">{{ log.notes }}</div>
                                        </td>
                                    </tr>

                                    <tr v-if="teachingLogs.length === 0">
                                        <td colspan="6" class="py-12 text-center text-slate-400">
                                            Belum ada riwayat jam mengajar yang tercatat untuk Trainer ini.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ============================================================= -->
            <!-- TAB 4: PENUGASAN & KPI (HARIAN, MINGGUAN, BULANAN, TAHUNAN)    -->
            <!-- ============================================================= -->
            <section v-if="activeTab === 'kpis'" class="p-1 rounded-3xl bg-slate-100/80 ring-1 ring-slate-900/5 shadow-sm overflow-hidden">
                <div class="bg-white rounded-[calc(1.5rem-1px)] p-5 sm:p-6 shadow-sm">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-100 mb-5">
                        <div>
                            <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">
                                Target KPI &amp; Penugasan Admin LnD
                            </h3>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Pemantauan progres Jam Mengajar, Peserta Terlayani, &amp; Skor Rating Aktual terhadap Target.
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <select
                                v-model="filterKpiPeriod"
                                class="text-xs font-medium rounded-xl border border-slate-200 bg-slate-50/70 focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 py-2 px-3 text-slate-800"
                            >
                                <option value="all">Semua Periode</option>
                                <option value="daily">Harian (Daily)</option>
                                <option value="weekly">Mingguan (Weekly)</option>
                                <option value="monthly">Bulanan (Monthly)</option>
                                <option value="yearly">Tahunan (Yearly)</option>
                            </select>
                            <button
                                type="button"
                                @click="showKpiModal = true"
                                class="px-4 py-2 text-xs font-bold text-white bg-gradient-to-r from-blue-700 to-indigo-600 hover:from-blue-800 hover:to-indigo-700 active:scale-[0.98] rounded-xl shadow-sm shadow-blue-500/25 transition-all whitespace-nowrap"
                            >
                                + Target KPI Baru
                            </button>
                        </div>
                    </div>

                    <!-- List KPI Cards -->
                    <div class="space-y-4">
                        <div
                            v-for="kpi in filteredKpiTasks"
                            :key="kpi.id"
                            class="p-4 sm:p-5 rounded-2xl border border-slate-200 bg-slate-50/60 hover:bg-white transition-all duration-200 shadow-sm hover:shadow-sm"
                        >
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                                <div>
                                    <div class="flex items-center gap-2.5">
                                        <h4 class="text-xs font-black text-slate-900">{{ kpi.title }}</h4>
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-blue-50 text-blue-700 border border-blue-200">
                                            {{ kpi.period_label }}
                                        </span>
                                    </div>
                                    <p v-if="kpi.description" class="text-[11px] text-slate-600 mt-1 font-medium">{{ kpi.description }}</p>
                                    <div class="text-[10px] font-mono text-slate-400 mt-1">Batas Waktu: {{ kpi.due_date_formatted }}</div>
                                </div>

                                <div class="flex items-center gap-2 shrink-0">
                                    <span
                                        :class="kpi.status === 'completed' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200'"
                                        class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider border"
                                    >
                                        {{ kpi.status === 'completed' ? 'Tercapai' : 'Berjalan' }}
                                    </span>
                                    <button
                                        type="button"
                                        @click="toggleKpiStatus(kpi, kpi.status === 'completed' ? 'in_progress' : 'completed')"
                                        class="px-3 py-1.5 text-[10px] font-bold rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-800 active:scale-[0.98] transition-all"
                                    >
                                        {{ kpi.status === 'completed' ? 'Set Berjalan' : 'Tandai Selesai' }}
                                    </button>
                                    <button
                                        type="button"
                                        @click="deleteKpi(kpi)"
                                        class="text-rose-500 hover:text-rose-700 text-xs p-1.5 rounded-lg hover:bg-rose-50"
                                        title="Hapus Target KPI"
                                    >
                                        ✕
                                    </button>
                                </div>
                            </div>

                            <!-- Progress Bars Metrik -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-4 border-t border-slate-200">
                                <!-- Metrik 1: Jam Mengajar -->
                                <div>
                                    <div class="flex items-center justify-between text-[11px] font-bold text-slate-700 mb-1.5">
                                        <span>Jam Mengajar</span>
                                        <span class="font-mono">{{ kpi.actual_teaching_hours }} / {{ kpi.target_teaching_hours }} Jam</span>
                                    </div>
                                    <div class="w-full bg-slate-200 h-2.5 rounded-full overflow-hidden p-0.5">
                                        <div
                                            class="bg-blue-600 h-full rounded-full transition-all duration-500"
                                            :style="{ width: `${Math.min(100, (kpi.actual_teaching_hours / (kpi.target_teaching_hours || 1)) * 100)}%` }"
                                        ></div>
                                    </div>
                                </div>

                                <!-- Metrik 2: Total Peserta -->
                                <div>
                                    <div class="flex items-center justify-between text-[11px] font-bold text-slate-700 mb-1.5">
                                        <span>Peserta Terlayani</span>
                                        <span class="font-mono">{{ kpi.actual_participants_count }} / {{ kpi.target_participants_count }} Orang</span>
                                    </div>
                                    <div class="w-full bg-slate-200 h-2.5 rounded-full overflow-hidden p-0.5">
                                        <div
                                            class="bg-indigo-600 h-full rounded-full transition-all duration-500"
                                            :style="{ width: `${Math.min(100, (kpi.actual_participants_count / (kpi.target_participants_count || 1)) * 100)}%` }"
                                        ></div>
                                    </div>
                                </div>

                                <!-- Metrik 3: Rating Penilaian -->
                                <div>
                                    <div class="flex items-center justify-between text-[11px] font-bold text-slate-700 mb-1.5">
                                        <span>Rating Aktual</span>
                                        <span class="font-mono text-amber-600 font-black">★ {{ kpi.actual_rating ?? '—' }} / Target {{ kpi.target_rating }}</span>
                                    </div>
                                    <div class="w-full bg-slate-200 h-2.5 rounded-full overflow-hidden p-0.5">
                                        <div
                                            class="bg-amber-500 h-full rounded-full transition-all duration-500"
                                            :style="{ width: `${kpi.actual_rating === null ? 0 : Math.min(100, (kpi.actual_rating / (kpi.target_rating || 1)) * 100)}%` }"
                                        ></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="filteredKpiTasks.length === 0" class="py-12 text-center text-slate-400">
                            Belum ada target KPI yang ditetapkan untuk Trainer pada periode ini.
                        </div>
                    </div>
                </div>
            </section>

            <!-- ============================================================= -->
            <!-- TAB 5: PENILAIAN & FEEDBACK PESERTA                           -->
            <!-- ============================================================= -->
            <section v-if="activeTab === 'evaluations'" class="space-y-5">
                <!-- Evaluation Summary Metrics -->
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                    <div class="p-1 rounded-2xl bg-amber-100/70 ring-1 ring-amber-500/20 shadow-sm">
                        <div class="bg-white rounded-[calc(1rem-1px)] p-3.5">
                            <div class="text-[10px] font-black uppercase tracking-wider text-amber-700">Skor Keseluruhan</div>
                            <div class="text-2xl font-black text-amber-600 mt-1 flex items-center gap-1">
                                <span class="text-amber-500">★</span>
                                {{ evaluationSummary.total_evaluations ? evaluationSummary.overall_avg : '—' }}
                            </div>
                            <div class="text-[11px] font-medium text-slate-500 mt-0.5">{{ evaluationSummary.total_evaluations }} Ulasan Peserta</div>
                        </div>
                    </div>

                    <div class="p-1 rounded-2xl bg-blue-100/70 ring-1 ring-blue-500/20 shadow-sm">
                        <div class="bg-white rounded-[calc(1rem-1px)] p-3.5">
                            <div class="text-[10px] font-black uppercase tracking-wider text-blue-600">Penguasaan Materi</div>
                            <div class="text-2xl font-black text-blue-700 mt-1">
                                {{ evaluationSummary.total_evaluations ? evaluationSummary.avg_material_mastery : '—' }}
                                <span v-if="evaluationSummary.total_evaluations" class="text-xs text-slate-400 font-medium">/ 5</span>
                            </div>
                            <div class="text-[11px] font-medium text-slate-500 mt-0.5">Kedalaman Teori &amp; Praktik</div>
                        </div>
                    </div>

                    <div class="p-1 rounded-2xl bg-indigo-100/70 ring-1 ring-indigo-500/20 shadow-sm">
                        <div class="bg-white rounded-[calc(1rem-1px)] p-3.5">
                            <div class="text-[10px] font-black uppercase tracking-wider text-indigo-600">Cara Penyampaian</div>
                            <div class="text-2xl font-black text-indigo-700 mt-1">
                                {{ evaluationSummary.total_evaluations ? evaluationSummary.avg_delivery_method : '—' }}
                                <span v-if="evaluationSummary.total_evaluations" class="text-xs text-slate-400 font-medium">/ 5</span>
                            </div>
                            <div class="text-[11px] font-medium text-slate-500 mt-0.5">Komunikasi &amp; Interaksi</div>
                        </div>
                    </div>

                    <div class="p-1 rounded-2xl bg-emerald-100/70 ring-1 ring-emerald-500/20 shadow-sm">
                        <div class="bg-white rounded-[calc(1rem-1px)] p-3.5">
                            <div class="text-[10px] font-black uppercase tracking-wider text-emerald-600">Ketepatan Waktu</div>
                            <div class="text-2xl font-black text-emerald-700 mt-1">
                                {{ evaluationSummary.total_evaluations ? evaluationSummary.avg_punctuality : '—' }}
                                <span v-if="evaluationSummary.total_evaluations" class="text-xs text-slate-400 font-medium">/ 5</span>
                            </div>
                            <div class="text-[11px] font-medium text-slate-500 mt-0.5">Punctuality Sesi</div>
                        </div>
                    </div>

                    <div class="p-1 rounded-2xl bg-purple-100/70 ring-1 ring-purple-500/20 shadow-sm col-span-2 sm:col-span-1">
                        <div class="bg-white rounded-[calc(1rem-1px)] p-3.5">
                            <div class="text-[10px] font-black uppercase tracking-wider text-purple-600">Indeks Mutu</div>
                            <div class="text-xl font-black text-purple-700 mt-1">
                                {{ evaluationSummary.total_evaluations ? 'TERCATAT' : 'BELUM ADA DATA' }}
                            </div>
                            <div class="text-[11px] font-medium text-slate-500 mt-0.5">Standar Mutu LnD</div>
                        </div>
                    </div>
                </div>

                <!-- Feedback Real Comments -->
                <div class="p-1 rounded-3xl bg-slate-100/80 ring-1 ring-slate-900/5 shadow-sm overflow-hidden">
                    <div class="bg-white rounded-[calc(1.5rem-1px)] p-5 sm:p-6 shadow-sm">
                        <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 pb-4 border-b border-slate-100 mb-5">
                            Daftar Komentar Asli &amp; Ulasan Peserta Hadir ({{ feedbackList.length }})
                        </h3>

                        <div class="space-y-3.5">
                            <div
                                v-for="fb in feedbackList"
                                :key="fb.id"
                                class="p-4 sm:p-5 rounded-2xl border border-slate-200 bg-slate-50/50 space-y-2.5 hover:bg-white transition-all duration-200 shadow-sm"
                            >
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-7 h-7 rounded-full bg-slate-200 text-slate-700 font-bold text-xs flex items-center justify-center">
                                            {{ fb.participant_name ? fb.participant_name.charAt(0) : 'P' }}
                                        </div>
                                        <div>
                                            <span class="font-extrabold text-slate-900 text-xs">{{ fb.participant_name }}</span>
                                            <span class="text-[10px] text-slate-500 font-mono ml-2">NIK: {{ fb.participant_nik }} &bull; {{ fb.participant_dept }}</span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-1.5 text-amber-500 font-black text-xs">
                                        <span>★</span> {{ fb.rating }} / 5
                                        <span class="text-[10px] text-slate-400 font-normal ml-2 font-mono">{{ fb.created_at }}</span>
                                    </div>
                                </div>

                                <div class="text-[11px] text-blue-700 font-bold bg-blue-50/60 px-2.5 py-1 rounded-lg border border-blue-100 inline-block">
                                    Pelatihan: {{ fb.course_title }} (Tanggal: {{ fb.training_date }})
                                </div>

                                <div class="flex flex-wrap items-center gap-3 text-[10px] font-semibold text-slate-600">
                                    <span>Materi: <strong class="text-slate-900">{{ fb.material_mastery_rating }}/5</strong></span>
                                    <span>&bull;</span>
                                    <span>Penyampaian: <strong class="text-slate-900">{{ fb.delivery_method_rating }}/5</strong></span>
                                    <span>&bull;</span>
                                    <span>Ketepatan Waktu: <strong class="text-slate-900">{{ fb.punctuality_rating }}/5</strong></span>
                                </div>

                                <p class="text-xs text-slate-800 bg-white p-3 rounded-xl border border-slate-200/80 italic leading-relaxed shadow-sm">
                                    "{{ fb.comment }}"
                                </p>
                            </div>

                            <div v-if="feedbackList.length === 0" class="py-12 text-center text-slate-400">
                                Belum ada ulasan atau penilaian yang dikirimkan oleh peserta untuk sesi Trainer ini.
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <!-- ============================================================= -->
        <!-- MODAL: TAMBAH KOMPETENSI AJAR (TAB 1)                         -->
        <!-- ============================================================= -->
        <div
            v-if="showAddSkillModal"
            class="fixed inset-0 z-50 bg-slate-950/50 backdrop-blur-md flex items-center justify-center p-4 overflow-y-auto"
        >
            <div class="p-1 rounded-3xl bg-gradient-to-b from-slate-200 to-slate-300 max-w-lg w-full shadow-2xl animate-in fade-in zoom-in-95 duration-200">
                <div class="bg-white rounded-[calc(1.5rem-2px)] p-6 shadow-sm">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center border border-blue-200">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <h3 class="text-sm font-black text-slate-900 tracking-tight">Tambah Kompetensi Ajar Trainer</h3>
                        </div>
                        <button type="button" @click="showAddSkillModal = false" class="w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 hover:text-slate-800 flex items-center justify-center transition-colors">✕</button>
                    </div>

                    <form @submit.prevent="submitAddSkill" class="space-y-3.5">
                        <div>
                            <label class="block text-xs font-bold text-slate-800 mb-1">
                                Pilih Kompetensi dari Master List <span class="text-rose-500">*</span>
                            </label>
                            <select
                                v-model="skillForm.skill_id"
                                required
                                class="w-full text-xs font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-800"
                            >
                                <option value="">-- Pilih Kompetensi --</option>
                                <option v-for="sk in availableSkills" :key="sk.id" :value="sk.id">
                                    {{ sk.code }} - {{ sk.name }} ({{ sk.category }})
                                </option>
                            </select>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-800 mb-1">Jenis Lisensi</label>
                                <input
                                    v-model="skillForm.license_type"
                                    type="text"
                                    placeholder="ToT BNSP Level 4"
                                    class="w-full text-xs font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-800"
                                />
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-800 mb-1">Nomor Lisensi/Sertifikat</label>
                                <input
                                    v-model="skillForm.license_number"
                                    type="text"
                                    placeholder="BNSP-TOT/..."
                                    class="w-full text-xs font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-800"
                                />
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-800 mb-1">Masa Berlaku Hingga</label>
                            <input
                                v-model="skillForm.valid_until"
                                type="date"
                                class="w-full text-xs font-mono font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-800"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-800 mb-1">Catatan Tambahan</label>
                            <textarea
                                v-model="skillForm.notes"
                                rows="2"
                                placeholder="Catatan verifikasi..."
                                class="w-full text-xs font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-800"
                            ></textarea>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
                            <button type="button" @click="showAddSkillModal = false" class="px-4 py-2 text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 active:scale-[0.98] rounded-xl transition-all">Batal</button>
                            <button type="submit" :disabled="skillForm.processing" class="px-5 py-2 text-xs font-bold text-white bg-gradient-to-r from-blue-700 to-indigo-600 hover:from-blue-800 hover:to-indigo-700 active:scale-[0.98] rounded-xl shadow-sm disabled:opacity-50 transition-all">Simpan Kompetensi</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- MODAL: SET ROSTER TANGGAL TUNGGAL (TAB 2)                     -->
        <!-- ============================================================= -->
        <div
            v-if="showSingleRosterModal"
            class="fixed inset-0 z-50 bg-slate-950/50 backdrop-blur-md flex items-center justify-center p-4 overflow-y-auto"
        >
            <div class="p-1 rounded-3xl bg-gradient-to-b from-slate-200 to-slate-300 max-w-md w-full shadow-2xl animate-in fade-in zoom-in-95 duration-200">
                <div class="bg-white rounded-[calc(1.5rem-2px)] p-5 shadow-sm">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                        <h3 class="text-sm font-black text-slate-900 tracking-tight">
                            Set Roster: {{ singleRosterForm.roster_date }}
                        </h3>
                        <button type="button" @click="showSingleRosterModal = false" class="w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 hover:text-slate-800 flex items-center justify-center transition-colors">✕</button>
                    </div>

                    <form @submit.prevent="submitSingleRoster" class="space-y-3.5">
                        <p v-if="singleRosterForm.errors.status" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs font-semibold text-rose-800" role="alert">
                            {{ singleRosterForm.errors.status }}
                        </p>

                        <div>
                            <label class="block text-xs font-bold text-slate-800 mb-1">Status Kehadiran Roster</label>
                            <select
                                v-model="singleRosterForm.status"
                                @change="normalizeRosterForm(singleRosterForm, 'status')"
                                class="w-full text-xs font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-800"
                            >
                                <option value="on_duty">ON DUTY (Masuk Kerja / Siap Mengajar)</option>
                                <option value="off_roster">OFF ROSTER (Libur Roster)</option>
                                <option value="annual_leave">CUTI TAHUNAN</option>
                                <option value="sick">SAKIT (SKD Dokter)</option>
                                <option value="site_visit">SITE VISIT (Dinas Luar)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-800 mb-1">Jenis Aktivitas</label>
                            <select
                                v-model="singleRosterForm.activity_type"
                                @change="normalizeRosterForm(singleRosterForm, 'activity_type')"
                                class="w-full text-xs font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-800"
                            >
                                <option value="TRAINING_CLASS">Mengajar Kelas</option>
                                <option value="FIELD_OBSERVATION">Observasi Lapangan</option>
                                <option value="LND_ADMIN">Tugas LnD (Admin)</option>
                                <option value="STANDBY">Standby</option>
                                <option value="OFF">Off / Cuti</option>
                            </select>
                        </div>

                        <div v-if="singleRosterForm.activity_type === 'TRAINING_CLASS'">
                            <label class="block text-xs font-bold text-slate-800 mb-1">Course Terkait</label>
                            <select
                                v-model="singleRosterForm.course_id"
                                class="w-full text-xs font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-800"
                            >
                                <option value="">Pilih kelas (opsional)</option>
                                <option v-for="course in activeCourses" :key="course.id" :value="course.id">{{ course.code }} — {{ course.title }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-800 mb-1">Lokasi</label>
                            <input
                                v-model="singleRosterForm.location"
                                type="text"
                                maxlength="150"
                                placeholder="Ruang kelas / area pit"
                                class="w-full text-xs font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-800"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-800 mb-1">Shift</label>
                            <select
                                v-model="singleRosterForm.shift"
                                class="w-full text-xs font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-800"
                            >
                                <option value="day">Day Shift (Siang)</option>
                                <option value="night">Night Shift (Malam)</option>
                                <option value="full">Full Day (Penuh)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-800 mb-1">Catatan Khusus</label>
                            <input
                                v-model="singleRosterForm.notes"
                                type="text"
                                placeholder="Contoh: Pengganti shift B, dll..."
                                class="w-full text-xs font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-800"
                            />
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
                            <button type="button" @click="showSingleRosterModal = false" class="px-4 py-2 text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 active:scale-[0.98] rounded-xl transition-all">Batal</button>
                            <button type="submit" class="px-5 py-2 text-xs font-bold text-white bg-gradient-to-r from-blue-700 to-indigo-600 hover:from-blue-800 hover:to-indigo-700 active:scale-[0.98] rounded-xl shadow-sm transition-all">Simpan Roster</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- MODAL: BULK SET DATE RANGE ROSTER (TAB 2)                     -->
        <!-- ============================================================= -->
        <div
            v-if="showBulkRosterModal"
            class="fixed inset-0 z-50 bg-slate-950/50 backdrop-blur-md flex items-center justify-center p-4 overflow-y-auto"
        >
            <div class="p-1 rounded-3xl bg-gradient-to-b from-slate-200 to-slate-300 max-w-lg w-full shadow-2xl animate-in fade-in zoom-in-95 duration-200">
                <div class="bg-white rounded-[calc(1.5rem-2px)] p-6 shadow-sm">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-slate-900 text-white flex items-center justify-center">⚡</div>
                            <div>
                                <h3 class="text-sm font-black text-slate-900 tracking-tight">Bulk Set Date Range Roster</h3>
                                <p class="text-xs text-slate-500 mt-0.5">Tetapkan status On-Duty/Off untuk rentang tanggal sekaligus</p>
                            </div>
                        </div>
                        <button type="button" @click="showBulkRosterModal = false" class="w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 hover:text-slate-800 flex items-center justify-center transition-colors">✕</button>
                    </div>

                    <form @submit.prevent="submitBulkRoster" class="space-y-3.5">
                        <p v-if="bulkRosterForm.errors.status" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs font-semibold text-rose-800" role="alert">
                            {{ bulkRosterForm.errors.status }}
                        </p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-800 mb-1">Dari Tanggal <span class="text-rose-500">*</span></label>
                                <input
                                    v-model="bulkRosterForm.start_date"
                                    type="date"
                                    required
                                    class="w-full text-xs font-mono font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-800"
                                />
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-800 mb-1">Sampai Tanggal <span class="text-rose-500">*</span></label>
                                <input
                                    v-model="bulkRosterForm.end_date"
                                    type="date"
                                    required
                                    class="w-full text-xs font-mono font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-800"
                                />
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-800 mb-1">Status Roster <span class="text-rose-500">*</span></label>
                            <select
                                v-model="bulkRosterForm.status"
                                @change="normalizeRosterForm(bulkRosterForm, 'status')"
                                required
                                class="w-full text-xs font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-800"
                            >
                                <option value="on_duty">ON DUTY (Masuk Kerja)</option>
                                <option value="off_roster">OFF ROSTER (Libur Roster)</option>
                                <option value="annual_leave">CUTI TAHUNAN</option>
                                <option value="sick">SAKIT (SKD Dokter)</option>
                                <option value="site_visit">SITE VISIT (Dinas Luar)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-800 mb-1">Jenis Aktivitas</label>
                            <select
                                v-model="bulkRosterForm.activity_type"
                                @change="normalizeRosterForm(bulkRosterForm, 'activity_type')"
                                class="w-full text-xs font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-800"
                            >
                                <option value="TRAINING_CLASS">Mengajar Kelas</option>
                                <option value="FIELD_OBSERVATION">Observasi Lapangan</option>
                                <option value="LND_ADMIN">Tugas LnD (Admin)</option>
                                <option value="STANDBY">Standby</option>
                                <option value="OFF">Off / Cuti</option>
                            </select>
                        </div>

                        <div v-if="bulkRosterForm.activity_type === 'TRAINING_CLASS'">
                            <label class="block text-xs font-bold text-slate-800 mb-1">Course Terkait</label>
                            <select
                                v-model="bulkRosterForm.course_id"
                                class="w-full text-xs font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-800"
                            >
                                <option value="">Pilih kelas (opsional)</option>
                                <option v-for="course in activeCourses" :key="course.id" :value="course.id">{{ course.code }} — {{ course.title }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-800 mb-1">Lokasi</label>
                            <input
                                v-model="bulkRosterForm.location"
                                type="text"
                                maxlength="150"
                                placeholder="Ruang kelas / area pit"
                                class="w-full text-xs font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-800"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-800 mb-1">Keterangan / Alasan</label>
                            <input
                                v-model="bulkRosterForm.notes"
                                type="text"
                                placeholder="Contoh: Roster 14-7 Site Tahap 2..."
                                class="w-full text-xs font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-800"
                            />
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
                            <button type="button" @click="showBulkRosterModal = false" class="px-4 py-2 text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 active:scale-[0.98] rounded-xl transition-all">Batal</button>
                            <button type="submit" :disabled="bulkRosterForm.processing" class="px-5 py-2 text-xs font-bold text-white bg-gradient-to-r from-blue-700 to-indigo-600 hover:from-blue-800 hover:to-indigo-700 active:scale-[0.98] rounded-xl shadow-sm disabled:opacity-50 transition-all">Terapkan Rentang Roster</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- MODAL: INPUT JAM MENGAJAR MANUAL (TAB 3)                      -->
        <!-- ============================================================= -->
        <div
            v-if="showManualLogModal"
            class="fixed inset-0 z-50 bg-slate-950/50 backdrop-blur-md flex items-center justify-center p-4 overflow-y-auto"
        >
            <div class="p-1 rounded-3xl bg-gradient-to-b from-slate-200 to-slate-300 max-w-lg w-full shadow-2xl animate-in fade-in zoom-in-95 duration-200">
                <div class="bg-white rounded-[calc(1.5rem-2px)] p-6 shadow-sm">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center border border-purple-200">
                                ✍️
                            </div>
                            <div>
                                <h3 class="text-sm font-black text-slate-900 tracking-tight">Input Jam Mengajar Manual</h3>
                                <p class="text-xs text-slate-500 mt-0.5">Catat sesi pelatihan eksternal / offline tanpa scan QR</p>
                            </div>
                        </div>
                        <button type="button" @click="showManualLogModal = false" class="w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 hover:text-slate-800 flex items-center justify-center transition-colors">✕</button>
                    </div>

                    <form @submit.prevent="submitManualLog" class="space-y-3.5">
                        <div>
                            <label class="block text-xs font-bold text-slate-800 mb-1">Nama Sesi Pelatihan <span class="text-rose-500">*</span></label>
                            <input
                                v-model="manualLogForm.training_title"
                                required
                                type="text"
                                placeholder="Contoh: Refreshment K3 Tambang & Alat Berat"
                                class="w-full text-xs font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-800"
                            />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-800 mb-1">Tanggal Pelatihan <span class="text-rose-500">*</span></label>
                                <input
                                    v-model="manualLogForm.log_date"
                                    required
                                    type="date"
                                    class="w-full text-xs font-mono font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-800"
                                />
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-800 mb-1">Program Kursus (Opsional)</label>
                                <select
                                    v-model="manualLogForm.course_id"
                                    class="w-full text-xs font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-800"
                                >
                                    <option value="">-- Tanpa Kursus Terikat --</option>
                                    <option v-for="c in activeCourses" :key="c.id" :value="c.id">{{ c.code }} - {{ c.title }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-800 mb-1">Durasi Mengajar (Jam) <span class="text-rose-500">*</span></label>
                                <input
                                    v-model.number="manualLogForm.duration_hours"
                                    required
                                    type="number"
                                    step="0.5"
                                    min="0.5"
                                    max="24"
                                    class="w-full text-xs font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-800"
                                />
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-800 mb-1">Jumlah Peserta Hadir <span class="text-rose-500">*</span></label>
                                <input
                                    v-model.number="manualLogForm.scanned_participants_count"
                                    required
                                    type="number"
                                    min="0"
                                    class="w-full text-xs font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-800"
                                />
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-800 mb-1">Lokasi Sesi</label>
                            <input
                                v-model="manualLogForm.location"
                                type="text"
                                placeholder="Site PBJ Training Room / Workshop"
                                class="w-full text-xs font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-800"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-800 mb-1">Catatan Tambahan</label>
                            <textarea
                                v-model="manualLogForm.notes"
                                rows="2"
                                placeholder="Keterangan materi..."
                                class="w-full text-xs font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-800"
                            ></textarea>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
                            <button type="button" @click="showManualLogModal = false" class="px-4 py-2 text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 active:scale-[0.98] rounded-xl transition-all">Batal</button>
                            <button type="submit" :disabled="manualLogForm.processing" class="px-5 py-2 text-xs font-bold text-white bg-gradient-to-r from-blue-700 to-indigo-600 hover:from-blue-800 hover:to-indigo-700 active:scale-[0.98] rounded-xl shadow-sm disabled:opacity-50 transition-all">Simpan Jam Mengajar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- DRAWER / MODAL: DAFTAR LENGKAP PESERTA SCAN QR (TAB 3)        -->
        <!-- ============================================================= -->
        <div
            v-if="showParticipantsDrawer"
            class="fixed inset-0 z-50 bg-slate-950/50 backdrop-blur-md flex items-center justify-center p-4 overflow-y-auto"
        >
            <div class="p-1 rounded-3xl bg-gradient-to-b from-slate-200 to-slate-300 max-w-2xl w-full shadow-2xl animate-in fade-in zoom-in-95 duration-200 max-h-[88vh] flex flex-col">
                <div class="bg-white rounded-[calc(1.5rem-2px)] p-6 flex flex-col flex-1 overflow-hidden shadow-sm">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                        <div class="flex items-center gap-2.5">
                            <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center border border-indigo-200">
                                👥
                            </div>
                            <div>
                                <h3 class="text-sm font-black text-slate-900 tracking-tight">
                                    Daftar Lengkap Peserta Hadir (Scan QR)
                                </h3>
                                <p v-if="currentSessionInfo" class="text-xs text-slate-500 mt-0.5">
                                    {{ currentSessionInfo.title }} ({{ currentSessionInfo.session_date }}) &bull; Durasi: {{ currentSessionInfo.duration_hours }} Jam
                                </p>
                            </div>
                        </div>
                        <button type="button" @click="showParticipantsDrawer = false" class="w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 hover:text-slate-800 flex items-center justify-center transition-colors">✕</button>
                    </div>

                    <div class="overflow-y-auto flex-1 my-4">
                        <div v-if="isLoadingParticipants" class="py-12 text-center text-xs text-slate-500">
                            Memuat data peserta dari database...
                        </div>

                        <div v-else-if="currentSessionInfo && currentSessionInfo.is_manual" class="p-4 bg-purple-50 border border-purple-200 rounded-2xl text-xs text-purple-900 leading-relaxed">
                            Sesi ini dicatat melalui <strong>Input Manual Admin LnD</strong> dengan total <strong>{{ currentSessionInfo.total_scanned }} peserta</strong> tanpa log scan QR individu perorangan.
                        </div>

                        <table v-else class="w-full text-left text-xs text-slate-700">
                            <thead class="bg-slate-50/90 border-b border-slate-200 text-[10px] font-black uppercase text-slate-500 sticky top-0">
                                <tr>
                                    <th class="py-3 px-3">No</th>
                                    <th class="py-3 px-3">Nama Peserta</th>
                                    <th class="py-3 px-3">NIK</th>
                                    <th class="py-3 px-3">Departemen / PT</th>
                                    <th class="py-3 px-3 text-right">Waktu Scan QR</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="(p, idx) in currentSessionParticipants" :key="p.id" class="hover:bg-slate-50">
                                    <td class="py-3 px-3 font-mono text-slate-400">{{ idx + 1 }}</td>
                                    <td class="py-3 px-3 font-bold text-slate-900">{{ p.name }}</td>
                                    <td class="py-3 px-3 font-mono text-slate-600 font-semibold">{{ p.nik }}</td>
                                    <td class="py-3 px-3 text-slate-600">{{ p.department }} ({{ p.company }})</td>
                                    <td class="py-3 px-3 text-right font-mono text-emerald-700 font-bold">{{ p.scanned_at }}</td>
                                </tr>

                                <tr v-if="currentSessionParticipants.length === 0">
                                    <td colspan="5" class="py-10 text-center text-slate-400">
                                        Belum ada catatan scan QR peserta pada sesi ini.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex items-center justify-between pt-4 border-t border-slate-100 text-xs">
                        <span class="text-slate-600 font-medium">
                            Total Peserta Terverifikasi: <strong class="text-slate-900 font-bold">{{ currentSessionParticipants.length }} Orang</strong>
                        </span>
                        <button
                            type="button"
                            @click="showParticipantsDrawer = false"
                            class="px-4 py-2 text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl transition-all"
                        >
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- MODAL: TETAPKAN TARGET KPI BARU (TAB 4)                       -->
        <!-- ============================================================= -->
        <div
            v-if="showKpiModal"
            class="fixed inset-0 z-50 bg-slate-950/50 backdrop-blur-md flex items-center justify-center p-4 overflow-y-auto"
        >
            <div class="p-1 rounded-3xl bg-gradient-to-b from-slate-200 to-slate-300 max-w-lg w-full shadow-2xl animate-in fade-in zoom-in-95 duration-200">
                <div class="bg-white rounded-[calc(1.5rem-2px)] p-6 shadow-sm">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center border border-blue-200">
                                🎯
                            </div>
                            <div>
                                <h3 class="text-sm font-black text-slate-900 tracking-tight">Tetapkan Target KPI &amp; Penugasan</h3>
                                <p class="text-xs text-slate-500 mt-0.5">Tentukan target periode harian, mingguan, bulanan, atau tahunan</p>
                            </div>
                        </div>
                        <button type="button" @click="showKpiModal = false" class="w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 hover:text-slate-800 flex items-center justify-center transition-colors">✕</button>
                    </div>

                    <form @submit.prevent="submitKpi" class="space-y-3.5">
                        <div>
                            <label class="block text-xs font-bold text-slate-800 mb-1">Judul Target KPI <span class="text-rose-500">*</span></label>
                            <input
                                v-model="kpiForm.title"
                                required
                                type="text"
                                placeholder="Contoh: Pencapaian 30 Jam Mengajar Bulanan & Rating Min 4.5"
                                class="w-full text-xs font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-800"
                            />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-800 mb-1">Periode Evaluasi <span class="text-rose-500">*</span></label>
                                <select
                                    v-model="kpiForm.period"
                                    required
                                    class="w-full text-xs font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-800"
                                >
                                    <option value="daily">Harian (Daily)</option>
                                    <option value="weekly">Mingguan (Weekly)</option>
                                    <option value="monthly">Bulanan (Monthly)</option>
                                    <option value="yearly">Tahunan (Yearly)</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-800 mb-1">Batas Waktu (Due Date)</label>
                                <input
                                    v-model="kpiForm.due_date"
                                    type="date"
                                    class="w-full text-xs font-mono font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-800"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-800 mb-1">Target Jam</label>
                                <input
                                    v-model.number="kpiForm.target_teaching_hours"
                                    type="number"
                                    step="0.5"
                                    min="0"
                                    class="w-full text-xs font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-800"
                                />
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-800 mb-1">Target Peserta</label>
                                <input
                                    v-model.number="kpiForm.target_participants_count"
                                    type="number"
                                    min="0"
                                    class="w-full text-xs font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-800"
                                />
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-800 mb-1">Target Rating (1-5)</label>
                                <input
                                    v-model.number="kpiForm.target_rating"
                                    type="number"
                                    step="0.1"
                                    min="1"
                                    max="5"
                                    class="w-full text-xs font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-800"
                                />
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-800 mb-1">Deskripsi &amp; Instruksi Khusus</label>
                            <textarea
                                v-model="kpiForm.description"
                                rows="2"
                                placeholder="Instruksi penugasan dari Admin LnD..."
                                class="w-full text-xs font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-800"
                            ></textarea>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
                            <button type="button" @click="showKpiModal = false" class="px-4 py-2 text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 active:scale-[0.98] rounded-xl transition-all">Batal</button>
                            <button type="submit" :disabled="kpiForm.processing" class="px-5 py-2 text-xs font-bold text-white bg-gradient-to-r from-blue-700 to-indigo-600 hover:from-blue-800 hover:to-indigo-700 active:scale-[0.98] rounded-xl shadow-sm disabled:opacity-50 transition-all">Tetapkan Target</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
