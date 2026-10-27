<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    trainers: {
        type: Array,
        default: () => [],
    },
    summary: {
        type: Object,
        default: () => ({
            total_trainers: 0,
            on_duty_today: 0,
            off_today: 0,
            total_hours_this_month: 0,
            total_participants_served: 0,
            overall_avg_rating: 5.0,
            selected_month_label: '',
        }),
    },
    filters: {
        type: Object,
        default: () => ({
            search: '',
            trainer_type: '',
            roster_status: '',
            department: '',
            month: '',
        }),
    },
    eligibleManpower: {
        type: Array,
        default: () => [],
    },
    masterSkills: {
        type: Array,
        default: () => [],
    },
    departments: {
        type: Array,
        default: () => [],
    },
    trainerSchedules: {
        type: Object,
        default: () => ({
            week: [],
            month: [],
            weekDays: [],
            stats: {
                week: { total_sessions: 0, active_trainers: 0, total_hours: 0, range_label: '', week_number: 1 },
                month: { total_sessions: 0, active_trainers: 0, total_hours: 0, range_label: '', month_name: '', year: '' },
            },
        }),
    },
});

const searchQuery = ref(props.filters.search || '');
const selectedTrainerType = ref(props.filters.trainer_type || '');
const selectedRosterStatus = ref(props.filters.roster_status || '');
const selectedDepartment = ref(props.filters.department || '');
const getCurrentMonth = () => {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
};
const selectedMonth = ref(props.filters.month || getCurrentMonth());

const applyFilters = () => {
    router.get(
        '/admin/trainers',
        {
            search: searchQuery.value || undefined,
            trainer_type: selectedTrainerType.value || undefined,
            roster_status: selectedRosterStatus.value || undefined,
            department: selectedDepartment.value || undefined,
            month: selectedMonth.value || undefined,
        },
        { preserveState: true, replace: true }
    );
};

const resetFilters = () => {
    searchQuery.value = '';
    selectedTrainerType.value = '';
    selectedRosterStatus.value = '';
    selectedDepartment.value = '';
    selectedMonth.value = getCurrentMonth();
    applyFilters();
};

const hasActiveFilters = computed(() => {
    return Boolean(
        searchQuery.value ||
        selectedTrainerType.value ||
        selectedRosterStatus.value ||
        selectedDepartment.value ||
        (selectedMonth.value && selectedMonth.value !== getCurrentMonth())
    );
});

// =========================================================================
// JADWAL TRAINER MINGGU INI & BULAN INI (KALENDER, LIST & DETAIL)
// =========================================================================
const schedulePeriodTab = ref('week'); // 'week' | 'month'
const scheduleDisplayMode = ref('calendar'); // 'calendar' | 'list'
const selectedScheduleDay = ref(null); // Filter tanggal spesifik 'YYYY-MM-DD'
const selectedScheduleDetail = ref(null); // Objek jadwal untuk modal detail

const activeScheduleList = computed(() => {
    const list = schedulePeriodTab.value === 'week'
        ? (props.trainerSchedules?.week || [])
        : (props.trainerSchedules?.month || []);

    if (selectedScheduleDay.value) {
        return list.filter((item) => item.date === selectedScheduleDay.value);
    }
    return list;
});

const currentPeriodStats = computed(() => {
    return props.trainerSchedules?.stats?.[schedulePeriodTab.value] || {
        total_sessions: 0,
        active_trainers: 0,
        total_hours: 0,
        range_label: '',
    };
});

const schedulesGroupedByDate = computed(() => {
    const map = {};
    const list = schedulePeriodTab.value === 'week'
        ? (props.trainerSchedules?.week || [])
        : (props.trainerSchedules?.month || []);

    list.forEach((item) => {
        if (!map[item.date]) {
            map[item.date] = [];
        }
        map[item.date].push(item);
    });
    return map;
});

// Grid Kalender Bulanan (7 Kolom: Senin s/d Minggu)
const monthCalendarGrid = computed(() => {
    const monthStr = selectedMonth.value || getCurrentMonth();
    const [yearStr, monthNumStr] = monthStr.split('-');
    const year = parseInt(yearStr, 10);
    const month = parseInt(monthNumStr, 10) - 1;

    const firstDate = new Date(year, month, 1);
    const lastDate = new Date(year, month + 1, 0);
    const daysInMonth = lastDate.getDate();

    let startDayOfWeek = firstDate.getDay() - 1;
    if (startDayOfWeek === -1) startDayOfWeek = 6;

    const now = new Date();
    const todayStr = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;

    const cells = [];

    // Hari kosong sebelum tanggal 1
    for (let i = 0; i < startDayOfWeek; i++) {
        cells.push({
            date: null,
            day_number: null,
            is_empty: true,
            is_today: false,
            sessions: [],
        });
    }

    // Hari 1 s/d daysInMonth
    for (let day = 1; day <= daysInMonth; day++) {
        const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const sessions = (props.trainerSchedules?.month || []).filter((s) => s.date === dateStr);
        cells.push({
            date: dateStr,
            day_number: day,
            is_empty: false,
            is_today: dateStr === todayStr,
            sessions: sessions,
            has_sessions: sessions.length > 0,
        });
    }

    return cells;
});

const getScheduleStatusPill = (status, isActive) => {
    if (isActive) {
        return {
            bg: 'bg-emerald-50 text-emerald-800 border-emerald-200 ring-1 ring-emerald-500/20',
            dot: 'bg-emerald-500 animate-pulse',
            label: 'PRESENSI QR LIVE',
        };
    }
    switch (status) {
        case 'approved':
        case 'on_duty':
            return {
                bg: 'bg-blue-50 text-blue-700 border-blue-200 ring-1 ring-blue-500/15',
                dot: 'bg-blue-600',
                label: 'TERJADWAL',
            };
        case 'draft':
            return {
                bg: 'bg-amber-50 text-amber-800 border-amber-200 ring-1 ring-amber-500/15',
                dot: 'bg-amber-600',
                label: 'DRAFT RENCANA',
            };
        case 'completed':
            return {
                bg: 'bg-slate-100 text-slate-700 border-slate-200 ring-1 ring-slate-400/20',
                dot: 'bg-slate-500',
                label: 'SELESAI',
            };
        default:
            return {
                bg: 'bg-slate-50 text-slate-700 border-slate-200',
                dot: 'bg-slate-400',
                label: status ? status.toUpperCase() : 'TERJADWAL',
            };
    }
};

const getCategoryColor = (category) => {
    switch (category) {
        case 'Safety':
            return 'bg-rose-50 text-rose-700 border-rose-200';
        case 'Operation':
            return 'bg-amber-50 text-amber-800 border-amber-200';
        case 'Plant':
            return 'bg-cyan-50 text-cyan-800 border-cyan-200';
        case 'Pengawas':
            return 'bg-indigo-50 text-indigo-700 border-indigo-200';
        case 'Engineering':
            return 'bg-purple-50 text-purple-700 border-purple-200';
        case 'Leadership':
            return 'bg-emerald-50 text-emerald-800 border-emerald-200';
        default:
            return 'bg-slate-100 text-slate-700 border-slate-200';
    }
};

const openScheduleModal = (item) => {
    selectedScheduleDetail.value = item;
};

const closeScheduleModal = () => {
    selectedScheduleDetail.value = null;
};

// Modal Angkat Manpower Jadi Trainer
const showPromoteModal = ref(false);
const promoteForm = useForm({
    user_id: '',
    trainer_type: 'INTERNAL_SME',
    roster_pattern: '5-2 (Senin - Jumat)',
    skill_ids: [],
    license_type: 'ToT BNSP Level 4',
    license_number: '',
    valid_until: '',
});

const openPromoteModal = () => {
    promoteForm.reset();
    promoteForm.trainer_type = 'INTERNAL_SME';
    promoteForm.roster_pattern = '5-2 (Senin - Jumat)';
    promoteForm.license_type = 'ToT BNSP Level 4';
    showPromoteModal.value = true;
};

const submitPromote = () => {
    promoteForm.post('/admin/trainers/promote', {
        preserveScroll: true,
        onSuccess: () => {
            showPromoteModal.value = false;
            promoteForm.reset();
        },
    });
};

const getStatusBadge = (status) => {
    switch (status) {
        case 'on_duty':
            return {
                bg: 'bg-emerald-50/90 text-emerald-800 border-emerald-200/90 ring-1 ring-emerald-500/20',
                dot: 'bg-emerald-500 shadow-sm shadow-emerald-400',
                label: 'ON DUTY',
            };
        case 'off_roster':
            return {
                bg: 'bg-slate-100 text-slate-700 border-slate-200 ring-1 ring-slate-400/20',
                dot: 'bg-slate-400',
                label: 'OFF ROSTER',
            };
        case 'annual_leave':
            return {
                bg: 'bg-amber-50/90 text-amber-800 border-amber-200/90 ring-1 ring-amber-500/20',
                dot: 'bg-amber-500 shadow-sm shadow-amber-400',
                label: 'CUTI TAHUNAN',
            };
        case 'sick':
            return {
                bg: 'bg-rose-50/90 text-rose-800 border-rose-200/90 ring-1 ring-rose-500/20',
                dot: 'bg-rose-500 shadow-sm shadow-rose-400',
                label: 'SAKIT (SKD)',
            };
        case 'site_visit':
            return {
                bg: 'bg-indigo-50/90 text-indigo-800 border-indigo-200/90 ring-1 ring-indigo-500/20',
                dot: 'bg-indigo-500 shadow-sm shadow-indigo-400',
                label: 'SITE VISIT',
            };
        default:
            return {
                bg: 'bg-slate-50 text-slate-700 border-slate-200',
                dot: 'bg-slate-400',
                label: status ? status.toUpperCase() : 'STANDBY',
            };
    }
};

const getTrainerTypeLabel = (type) => {
    switch (type) {
        case 'INTERNAL_SME':
            return {
                label: 'Internal SME',
                class: 'bg-blue-50/90 text-blue-700 border-blue-200 ring-1 ring-blue-500/15',
                dot: 'bg-blue-600',
            };
        case 'FULL_TIME_LND':
            return {
                label: 'Full-Time LnD',
                class: 'bg-purple-50/90 text-purple-700 border-purple-200 ring-1 ring-purple-500/15',
                dot: 'bg-purple-600',
            };
        case 'EXTERNAL_VENDOR':
            return {
                label: 'External Vendor',
                class: 'bg-amber-50/90 text-amber-700 border-amber-200 ring-1 ring-amber-500/15',
                dot: 'bg-amber-600',
            };
        default:
            return {
                label: type || 'SME',
                class: 'bg-slate-50 text-slate-600 border-slate-200',
                dot: 'bg-slate-400',
            };
    }
};

const avatarGradients = [
    'from-blue-600 to-indigo-700',
    'from-indigo-600 to-sky-600',
    'from-slate-700 to-slate-900',
    'from-sky-600 to-blue-800',
    'from-emerald-600 to-teal-700',
];

const getAvatarGradient = (id = 0) => {
    return avatarGradients[id % avatarGradients.length];
};
</script>

<template>
    <Head title="Pusat Manajemen Trainer & Roster LnD" />

    <AdminLayout
        title="Pusat Manajemen Trainer &amp; Roster"
        subtitle="Pusat komando Admin LnD: penugasan instruktur, monitoring kehadiran, kalender roster, akumulasi jam mengajar & skor kepuasan."
    >
        <div class="space-y-6">
            <!-- ============================================================= -->
            <!-- 1. EXECUTIVE KPI SUMMARY METRICS (DOUBLE-BEZEL BENTO GRID)     -->
            <!-- ============================================================= -->
            <section class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                <!-- Card 1: Total Trainer -->
                <div class="p-1 rounded-2xl bg-gradient-to-b from-slate-200/70 to-slate-100 ring-1 ring-slate-900/5 shadow-sm transition-all duration-300 hover:shadow-md">
                    <div class="h-full bg-white rounded-[calc(1rem-1px)] p-3.5 flex flex-col justify-between">
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-slate-100 text-slate-700 border border-slate-200/80">
                                Total Instruktur
                            </span>
                            <div class="w-6 h-6 rounded-full bg-slate-100 flex items-center justify-center text-slate-500">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="text-2xl font-black text-slate-900 tracking-tight">{{ summary.total_trainers }}</div>
                            <div class="text-[11px] font-medium text-slate-500 mt-0.5">SME &amp; Instruktur Aktif</div>
                        </div>
                    </div>
                </div>

                <!-- Card 2: On Duty Hari Ini -->
                <div class="p-1 rounded-2xl bg-gradient-to-b from-emerald-200/60 to-emerald-100/40 ring-1 ring-emerald-500/20 shadow-sm transition-all duration-300 hover:shadow-md">
                    <div class="h-full bg-white rounded-[calc(1rem-1px)] p-3.5 flex flex-col justify-between">
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200/80">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                On Duty Hari Ini
                            </span>
                            <div class="w-6 h-6 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="text-2xl font-black text-emerald-700 tracking-tight">{{ summary.on_duty_today }}</div>
                            <div class="text-[11px] font-semibold text-emerald-600 mt-0.5">Siap Memberikan Sesi</div>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Off / Cuti Hari Ini -->
                <div class="p-1 rounded-2xl bg-gradient-to-b from-amber-200/60 to-amber-100/40 ring-1 ring-amber-500/20 shadow-sm transition-all duration-300 hover:shadow-md">
                    <div class="h-full bg-white rounded-[calc(1rem-1px)] p-3.5 flex flex-col justify-between">
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-amber-50 text-amber-700 border border-amber-200/80">
                                Off / Izin Cuti
                            </span>
                            <div class="w-6 h-6 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="text-2xl font-black text-amber-700 tracking-tight">{{ summary.off_today }}</div>
                            <div class="text-[11px] font-medium text-amber-600 mt-0.5">Off Roster &amp; Sakit/Cuti</div>
                        </div>
                    </div>
                </div>

                <!-- Card 4: Jam Mengajar Bulan Ini -->
                <div class="p-1 rounded-2xl bg-gradient-to-b from-blue-200/60 to-blue-100/40 ring-1 ring-blue-500/20 shadow-sm transition-all duration-300 hover:shadow-md">
                    <div class="h-full bg-white rounded-[calc(1rem-1px)] p-3.5 flex flex-col justify-between">
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-blue-50 text-blue-700 border border-blue-200/80 truncate max-w-[125px]">
                                {{ summary.selected_month_label || 'Bulan Ini' }}
                            </span>
                            <div class="w-6 h-6 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253" />
                                </svg>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="text-2xl font-black text-blue-700 tracking-tight flex items-baseline gap-1">
                                <span>{{ summary.total_hours_this_month }}</span>
                                <span class="text-xs font-bold text-blue-600/70">Jam</span>
                            </div>
                            <div class="text-[11px] font-medium text-blue-600 mt-0.5">Teaching Hours Sah</div>
                        </div>
                    </div>
                </div>

                <!-- Card 5: Peserta QR -->
                <div class="p-1 rounded-2xl bg-gradient-to-b from-indigo-200/60 to-indigo-100/40 ring-1 ring-indigo-500/20 shadow-sm transition-all duration-300 hover:shadow-md">
                    <div class="h-full bg-white rounded-[calc(1rem-1px)] p-3.5 flex flex-col justify-between">
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-indigo-50 text-indigo-700 border border-indigo-200/80">
                                Peserta Terlayani
                            </span>
                            <div class="w-6 h-6 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1z" />
                                </svg>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="text-2xl font-black text-indigo-700 tracking-tight flex items-baseline gap-1">
                                <span>{{ summary.total_participants_served }}</span>
                                <span class="text-xs font-bold text-indigo-500">Scan</span>
                            </div>
                            <div class="text-[11px] font-medium text-indigo-600 mt-0.5">Terkonfirmasi Hadir QR</div>
                        </div>
                    </div>
                </div>

                <!-- Card 6: Rating Peserta -->
                <div class="p-1 rounded-2xl bg-gradient-to-b from-amber-200/70 to-amber-100/50 ring-1 ring-amber-500/20 shadow-sm transition-all duration-300 hover:shadow-md">
                    <div class="h-full bg-white rounded-[calc(1rem-1px)] p-3.5 flex flex-col justify-between">
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-amber-50 text-amber-800 border border-amber-200/80">
                                Indeks Kepuasan
                            </span>
                            <div class="w-6 h-6 rounded-full bg-amber-50 text-amber-500 flex items-center justify-center font-bold text-xs">
                                ★
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="text-2xl font-black text-amber-600 tracking-tight flex items-center gap-1">
                                <span class="text-amber-500 text-lg">★</span>
                                <span>{{ summary.overall_avg_rating }}</span>
                                <span class="text-xs font-medium text-slate-400">/ 5.0</span>
                            </div>
                            <div class="text-[11px] font-medium text-slate-500 mt-0.5">Rata-Rata Feedback</div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ============================================================= -->
            <!-- 2. JADWAL TRAINER MINGGU INI & BULAN INI (KALENDER & LIST)     -->
            <!-- ============================================================= -->
            <section class="p-1 rounded-3xl bg-slate-100/80 ring-1 ring-slate-900/5 shadow-sm">
                <div class="bg-white rounded-[calc(1.5rem-1px)] p-4 sm:p-6 shadow-sm space-y-5">
                    <!-- Top Bar: Title, Eyebrow & Controls -->
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 border-b border-slate-100 pb-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-blue-50 text-blue-700 border border-blue-200/80">
                                    Agenda &amp; Roster Mengajar
                                </span>
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                    <svg class="w-3 h-3 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    <span>{{ currentPeriodStats.range_label }}</span>
                                </span>
                            </div>
                            <h2 class="text-base font-black text-slate-900 tracking-tight mt-1 flex items-center gap-2">
                                <span>Jadwal Trainer &amp; Roster</span>
                                <span class="text-xs font-semibold text-slate-400">· Monitoring Sesi Kelas &amp; Ketersediaan</span>
                            </h2>
                        </div>

                        <!-- Controls: Period Tabs & View Mode Switcher -->
                        <div class="flex flex-wrap items-center gap-2.5">
                            <!-- Period Switcher (Minggu Ini / Bulan Ini) -->
                            <div class="p-0.5 bg-slate-100 rounded-xl ring-1 ring-slate-200/70 flex items-center gap-1 text-xs font-bold">
                                <button
                                    type="button"
                                    @click="schedulePeriodTab = 'week'; selectedScheduleDay = null"
                                    :class="schedulePeriodTab === 'week' ? 'bg-white text-blue-700 shadow-sm ring-1 ring-slate-900/5' : 'text-slate-600 hover:text-slate-900'"
                                    class="px-3 py-1.5 rounded-lg transition-all flex items-center gap-1.5"
                                >
                                    <span>Minggu Ini</span>
                                    <span
                                        :class="schedulePeriodTab === 'week' ? 'bg-blue-100 text-blue-800' : 'bg-slate-200 text-slate-600'"
                                        class="px-1.5 py-0.2 rounded-full text-[10px] font-black"
                                    >
                                        {{ props.trainerSchedules?.stats?.week?.total_sessions ?? 0 }}
                                    </span>
                                </button>
                                <button
                                    type="button"
                                    @click="schedulePeriodTab = 'month'; selectedScheduleDay = null"
                                    :class="schedulePeriodTab === 'month' ? 'bg-white text-blue-700 shadow-sm ring-1 ring-slate-900/5' : 'text-slate-600 hover:text-slate-900'"
                                    class="px-3 py-1.5 rounded-lg transition-all flex items-center gap-1.5"
                                >
                                    <span>Bulan Ini</span>
                                    <span
                                        :class="schedulePeriodTab === 'month' ? 'bg-blue-100 text-blue-800' : 'bg-slate-200 text-slate-600'"
                                        class="px-1.5 py-0.2 rounded-full text-[10px] font-black"
                                    >
                                        {{ props.trainerSchedules?.stats?.month?.total_sessions ?? 0 }}
                                    </span>
                                </button>
                            </div>

                            <!-- Display Mode (Kalender / List) -->
                            <div class="p-0.5 bg-slate-100 rounded-xl ring-1 ring-slate-200/70 flex items-center gap-1 text-xs font-bold">
                                <button
                                    type="button"
                                    @click="scheduleDisplayMode = 'calendar'"
                                    :class="scheduleDisplayMode === 'calendar' ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-900/5' : 'text-slate-500 hover:text-slate-800'"
                                    class="px-2.5 py-1.5 rounded-lg transition-all flex items-center gap-1.5"
                                    title="Tampilan Kalender"
                                >
                                    <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    <span>Kalender</span>
                                </button>
                                <button
                                    type="button"
                                    @click="scheduleDisplayMode = 'list'"
                                    :class="scheduleDisplayMode === 'list' ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-900/5' : 'text-slate-500 hover:text-slate-800'"
                                    class="px-2.5 py-1.5 rounded-lg transition-all flex items-center gap-1.5"
                                    title="Tampilan List Tabel"
                                >
                                    <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                                    </svg>
                                    <span>List</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Micro Metric Ribbon -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-slate-50/70 p-3 rounded-2xl border border-slate-200/70 text-xs">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-blue-100 text-blue-700 flex items-center justify-center font-bold shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <div>
                                <div class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Total Sesi {{ schedulePeriodTab === 'week' ? 'Minggu Ini' : 'Bulan Ini' }}</div>
                                <div class="text-sm font-black text-slate-900">{{ currentPeriodStats.total_sessions }} <span class="text-[10px] font-normal text-slate-500">Kelas</span></div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            </div>
                            <div>
                                <div class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Instruktur Mengajar</div>
                                <div class="text-sm font-black text-slate-900">{{ currentPeriodStats.active_trainers }} <span class="text-[10px] font-normal text-slate-500">Orang</span></div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-purple-100 text-purple-700 flex items-center justify-center font-bold shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div>
                                <div class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Akumulasi Jam Mengajar</div>
                                <div class="text-sm font-black text-slate-900">{{ currentPeriodStats.total_hours }} <span class="text-[10px] font-normal text-slate-500">Jam Sah</span></div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between sm:justify-end gap-2">
                            <div v-if="selectedScheduleDay" class="flex items-center gap-2">
                                <span class="text-[11px] text-slate-600 font-medium">Filter: <span class="font-bold text-blue-700">{{ selectedScheduleDay }}</span></span>
                                <button
                                    type="button"
                                    @click="selectedScheduleDay = null"
                                    class="px-2 py-1 text-[10px] font-bold text-rose-600 bg-rose-50 hover:bg-rose-100 rounded-lg transition-colors"
                                >
                                    Reset
                                </button>
                            </div>
                            <div v-else class="text-[11px] text-slate-500 flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                <span>Sinkron dengan Master Roster</span>
                            </div>
                        </div>
                    </div>

                    <!-- ========================================================= -->
                    <!-- SUB-VIEW A: KALENDER MINGGU INI (7 KOLOM SENIN-MINGGU)   -->
                    <!-- ========================================================= -->
                    <div v-if="scheduleDisplayMode === 'calendar' && schedulePeriodTab === 'week'" class="space-y-3">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-7 gap-3">
                            <div
                                v-for="day in (props.trainerSchedules?.weekDays || [])"
                                :key="day.date"
                                :class="day.is_today ? 'ring-2 ring-blue-500 bg-blue-50/20' : 'ring-1 ring-slate-200/80 bg-slate-50/40'"
                                class="rounded-2xl p-3 flex flex-col justify-between transition-all hover:bg-white hover:shadow-sm min-h-[220px]"
                            >
                                <!-- Day Header -->
                                <div class="border-b border-slate-200/60 pb-2 mb-2 flex items-center justify-between">
                                    <div>
                                        <div class="text-[11px] font-black uppercase tracking-wider text-slate-500">
                                            {{ day.day_name }}
                                        </div>
                                        <div class="text-sm font-extrabold text-slate-900">
                                            {{ day.day_number }} <span class="text-xs font-semibold text-slate-500">{{ day.month_name }}</span>
                                        </div>
                                    </div>
                                    <span
                                        v-if="day.is_today"
                                        class="px-1.5 py-0.5 rounded text-[9px] font-black tracking-wider bg-blue-600 text-white uppercase shadow-sm"
                                    >
                                        Hari Ini
                                    </span>
                                </div>

                                <!-- Sessions in this day -->
                                <div class="flex-1 space-y-2">
                                    <div
                                        v-if="(schedulesGroupedByDate[day.date] || []).length === 0"
                                        class="h-full min-h-[120px] rounded-xl border border-dashed border-slate-200 flex flex-col items-center justify-center p-2 text-center text-slate-400"
                                    >
                                        <svg class="w-5 h-5 mb-1 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                        </svg>
                                        <span class="text-[10px]">Tidak ada sesi</span>
                                    </div>

                                    <div
                                        v-for="session in (schedulesGroupedByDate[day.date] || [])"
                                        :key="session.id"
                                        @click="openScheduleModal(session)"
                                        class="p-2.5 rounded-xl bg-white border border-slate-200/90 shadow-sm hover:shadow-md hover:border-blue-300 hover:-translate-y-0.5 transition-all duration-200 cursor-pointer group flex flex-col justify-between gap-1.5"
                                    >
                                        <!-- Time & Status Pill -->
                                        <div class="flex items-center justify-between gap-1">
                                            <span class="inline-flex items-center gap-1 font-mono text-[10px] font-bold text-blue-700 bg-blue-50 px-1.5 py-0.5 rounded border border-blue-200/80">
                                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                                <span>{{ session.start_time }} - {{ session.end_time }}</span>
                                            </span>
                                            <span
                                                :class="getScheduleStatusPill(session.plan_status, session.is_active).bg"
                                                class="inline-flex items-center gap-1 px-1.5 py-0.2 rounded-full text-[9px] font-bold border"
                                            >
                                                <span :class="getScheduleStatusPill(session.plan_status, session.is_active).dot" class="w-1.5 h-1.5 rounded-full"></span>
                                                <span class="truncate max-w-[65px]">{{ getScheduleStatusPill(session.plan_status, session.is_active).label }}</span>
                                            </span>
                                        </div>

                                        <!-- Trainer Info -->
                                        <div class="flex items-center gap-1.5 pt-0.5">
                                            <div
                                                :class="getAvatarGradient(session.trainer_id || 0)"
                                                class="w-5 h-5 rounded-md bg-gradient-to-tr text-white flex items-center justify-center font-bold text-[9px] uppercase shrink-0"
                                            >
                                                {{ (session.trainer_name || 'T').substring(0, 2) }}
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div class="text-[11px] font-bold text-slate-900 truncate group-hover:text-blue-700 transition-colors">
                                                    {{ session.trainer_name }}
                                                </div>
                                                <div class="text-[9px] font-medium text-slate-500 truncate">
                                                    NIK: {{ session.trainer_nik }} · {{ session.trainer_dept }}
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Course Title -->
                                        <div class="pt-0.5">
                                            <div class="text-[11px] font-semibold text-slate-800 line-clamp-2 leading-tight">
                                                {{ session.course_title }}
                                            </div>
                                            <div class="flex items-center gap-1 mt-1 text-[9px] text-slate-500">
                                                <svg class="w-2.5 h-2.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                                </svg>
                                                <span class="truncate">{{ session.location }}</span>
                                            </div>
                                        </div>

                                        <!-- Attendance or Hours Badge -->
                                        <div class="pt-1.5 border-t border-slate-100 flex items-center justify-between text-[9px]">
                                            <span class="font-bold text-slate-600">
                                                {{ session.duration_hours }} Jam Pelatihan
                                            </span>
                                            <span
                                                v-if="session.quota"
                                                class="font-medium text-indigo-700 bg-indigo-50 px-1.5 py-0.2 rounded border border-indigo-200"
                                            >
                                                {{ session.present_count }}/{{ session.quota }} Hadir
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ========================================================= -->
                    <!-- SUB-VIEW B: KALENDER BULAN INI (GRID BULANAN + DETAIL)    -->
                    <!-- ========================================================= -->
                    <div v-else-if="scheduleDisplayMode === 'calendar' && schedulePeriodTab === 'month'" class="space-y-4">
                        <!-- 7 Days Weekday Header -->
                        <div class="grid grid-cols-7 gap-1.5 text-center text-[10px] font-black uppercase tracking-wider text-slate-500 py-1 bg-slate-50 rounded-xl border border-slate-200/60">
                            <div>Sen</div>
                            <div>Sel</div>
                            <div>Rab</div>
                            <div>Kam</div>
                            <div>Jum</div>
                            <div>Sab</div>
                            <div>Min</div>
                        </div>

                        <!-- 7xN Calendar Month Grid -->
                        <div class="grid grid-cols-7 gap-1.5">
                            <div
                                v-for="(cell, cIdx) in monthCalendarGrid"
                                :key="cIdx"
                                :class="[
                                    cell.is_empty ? 'opacity-30 bg-slate-50/30 border-transparent' : 'bg-white border-slate-200 hover:border-blue-400 cursor-pointer',
                                    cell.is_today ? 'ring-2 ring-blue-500 bg-blue-50/20' : '',
                                    selectedScheduleDay === cell.date ? 'ring-2 ring-indigo-600 bg-indigo-50/30' : '',
                                ]"
                                @click="!cell.is_empty && (selectedScheduleDay = selectedScheduleDay === cell.date ? null : cell.date)"
                                class="min-h-[78px] sm:min-h-[92px] p-2 rounded-xl border flex flex-col justify-between transition-all duration-150 group"
                            >
                                <div class="flex items-center justify-between">
                                    <span
                                        v-if="!cell.is_empty"
                                        :class="cell.is_today ? 'bg-blue-600 text-white font-black' : 'text-slate-800 font-bold'"
                                        class="w-5 h-5 rounded-full flex items-center justify-center text-[11px]"
                                    >
                                        {{ cell.day_number }}
                                    </span>
                                    <span
                                        v-if="cell.has_sessions"
                                        class="px-1.5 py-0.2 rounded-full text-[9px] font-black bg-blue-100 text-blue-700 border border-blue-200"
                                    >
                                        {{ cell.sessions.length }} Sesi
                                    </span>
                                </div>

                                <!-- Session mini preview chips -->
                                <div v-if="cell.has_sessions" class="mt-1 space-y-1">
                                    <div
                                        v-for="s in cell.sessions.slice(0, 2)"
                                        :key="s.id"
                                        @click.stop="openScheduleModal(s)"
                                        class="px-1.5 py-0.5 rounded text-[9px] font-medium bg-slate-100 hover:bg-blue-100 text-slate-800 hover:text-blue-900 border border-slate-200 truncate transition-colors flex items-center gap-1"
                                        :title="s.trainer_name + ' · ' + s.course_title"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full bg-blue-600 shrink-0"></span>
                                        <span class="font-bold shrink-0">{{ s.start_time }}</span>
                                        <span class="truncate">{{ s.trainer_name.split(' ')[0] }}</span>
                                    </div>
                                    <div v-if="cell.sessions.length > 2" class="text-[8px] text-slate-500 font-bold px-1">
                                        +{{ cell.sessions.length - 2 }} lainnya
                                    </div>
                                </div>
                                <div v-else-if="!cell.is_empty" class="text-[9px] text-slate-300">
                                    -
                                </div>
                            </div>
                        </div>

                        <!-- Agenda List Filtered / Bulan Ini -->
                        <div class="mt-4 pt-4 border-t border-slate-100">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-xs font-black uppercase tracking-wider text-slate-800 flex items-center gap-2">
                                    <span>Daftar Sesi Terjadwal Bulan Ini</span>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600">
                                        {{ activeScheduleList.length }} Sesi
                                    </span>
                                </h3>
                                <button
                                    v-if="selectedScheduleDay"
                                    type="button"
                                    @click="selectedScheduleDay = null"
                                    class="text-xs font-bold text-blue-600 hover:text-blue-800 underline"
                                >
                                    Tampilkan Semua Hari di Bulan Ini
                                </button>
                            </div>

                            <div v-if="activeScheduleList.length === 0" class="p-6 text-center text-slate-400 bg-slate-50 rounded-2xl border border-dashed border-slate-200 text-xs">
                                Tidak ada sesi pada tanggal/periode yang dipilih.
                            </div>

                            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                <div
                                    v-for="s in activeScheduleList"
                                    :key="s.id"
                                    @click="openScheduleModal(s)"
                                    class="p-3 rounded-2xl bg-slate-50/80 hover:bg-white border border-slate-200/80 hover:border-blue-300 hover:shadow-sm transition-all cursor-pointer flex flex-col justify-between gap-2"
                                >
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-bold text-xs text-slate-900">{{ s.day_name }}, {{ s.date_formatted }}</span>
                                            <span class="text-[10px] font-mono text-blue-700 bg-blue-50 px-1.5 py-0.2 rounded border border-blue-200 font-bold">{{ s.time_range }}</span>
                                        </div>
                                        <span
                                            :class="getScheduleStatusPill(s.plan_status, s.is_active).bg"
                                            class="inline-flex items-center gap-1 px-1.5 py-0.2 rounded-full text-[9px] font-bold border"
                                        >
                                            <span :class="getScheduleStatusPill(s.plan_status, s.is_active).dot" class="w-1.5 h-1.5 rounded-full"></span>
                                            <span>{{ getScheduleStatusPill(s.plan_status, s.is_active).label }}</span>
                                        </span>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <div
                                            :class="getAvatarGradient(s.trainer_id || 0)"
                                            class="w-7 h-7 rounded-lg bg-gradient-to-tr text-white flex items-center justify-center font-bold text-xs uppercase shrink-0"
                                        >
                                            {{ (s.trainer_name || 'T').substring(0, 2) }}
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="text-xs font-bold text-slate-900 truncate">{{ s.trainer_name }}</div>
                                            <div class="text-[10px] text-slate-500 truncate">NIK: {{ s.trainer_nik }} · {{ s.trainer_dept }}</div>
                                        </div>
                                    </div>

                                    <div>
                                        <div class="text-xs font-semibold text-slate-800 line-clamp-1">{{ s.course_title }}</div>
                                        <div class="text-[10px] text-slate-500 flex items-center gap-1 mt-0.5">
                                            <span class="font-mono font-bold text-slate-700">{{ s.course_code }}</span>
                                            <span>·</span>
                                            <span>{{ s.location }}</span>
                                        </div>
                                    </div>

                                    <div class="flex items-center justify-between pt-2 border-t border-slate-200/60 text-[10px]">
                                        <span class="font-semibold text-slate-600">{{ s.duration_hours }} Jam Pelatihan</span>
                                        <button
                                            type="button"
                                            class="font-bold text-blue-600 hover:text-blue-800 flex items-center gap-1"
                                        >
                                            <span>Lihat Detail</span>
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ========================================================= -->
                    <!-- SUB-VIEW C: LIST VIEW (TABEL LENGKAP JADWAL TRAINER)      -->
                    <!-- ========================================================= -->
                    <div v-else-if="scheduleDisplayMode === 'list'" class="space-y-3">
                        <div v-if="activeScheduleList.length === 0" class="p-8 text-center text-slate-400 bg-slate-50 rounded-2xl border border-dashed border-slate-200 text-xs">
                            Belum ada jadwal sesi mengajar untuk periode {{ schedulePeriodTab === 'week' ? 'minggu ini' : 'bulan ini' }}.
                        </div>

                        <div v-else class="overflow-x-auto rounded-2xl border border-slate-200/80">
                            <table class="w-full text-left text-xs text-slate-700">
                                <thead class="bg-slate-50/90 border-b border-slate-200/80 text-[10px] font-black uppercase tracking-wider text-slate-500 select-none">
                                    <tr>
                                        <th class="py-3 px-4">Tanggal &amp; Waktu</th>
                                        <th class="py-3 px-4">Instruktur / Trainer</th>
                                        <th class="py-3 px-4">Pelatihan / Kursus</th>
                                        <th class="py-3 px-3">Ruangan &amp; Lokasi</th>
                                        <th class="py-3 px-3 text-center">Presensi / Kuota</th>
                                        <th class="py-3 px-3 text-center">Status Sesi</th>
                                        <th class="py-3 px-4 text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr
                                        v-for="s in activeScheduleList"
                                        :key="s.id"
                                        class="hover:bg-slate-50/80 transition-colors group cursor-pointer"
                                        @click="openScheduleModal(s)"
                                    >
                                        <!-- Tanggal & Waktu -->
                                        <td class="py-3.5 px-4">
                                            <div class="font-bold text-slate-900">{{ s.day_name }}, {{ s.date_formatted }}</div>
                                            <div class="flex items-center gap-1.5 mt-0.5 text-[11px] font-mono font-medium text-blue-700">
                                                <svg class="w-3 h-3 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                                <span>{{ s.start_time }} - {{ s.end_time }} WIB</span>
                                                <span class="text-slate-400">·</span>
                                                <span class="text-slate-600 font-sans font-bold">{{ s.duration_hours }} Jam</span>
                                            </div>
                                        </td>

                                        <!-- Instruktur -->
                                        <td class="py-3.5 px-4">
                                            <div class="flex items-center gap-2.5">
                                                <div
                                                    :class="getAvatarGradient(s.trainer_id || 0)"
                                                    class="w-8 h-8 rounded-xl bg-gradient-to-tr text-white flex items-center justify-center font-black text-xs uppercase shrink-0 ring-2 ring-white shadow-sm"
                                                >
                                                    {{ (s.trainer_name || 'T').substring(0, 2) }}
                                                </div>
                                                <div class="min-w-0">
                                                    <div class="font-bold text-slate-900 group-hover:text-blue-700 transition-colors truncate">
                                                        {{ s.trainer_name }}
                                                    </div>
                                                    <div class="text-[10px] text-slate-500 flex items-center gap-1">
                                                        <span>NIK: {{ s.trainer_nik }}</span>
                                                        <span>·</span>
                                                        <span>{{ s.trainer_dept }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Materi -->
                                        <td class="py-3.5 px-4 max-w-[240px]">
                                            <div class="flex items-center gap-1.5 mb-0.5">
                                                <span class="font-mono text-[10px] font-bold text-blue-700 bg-blue-50 px-1.5 py-0.2 rounded border border-blue-200">
                                                    {{ s.course_code }}
                                                </span>
                                                <span
                                                    :class="getCategoryColor(s.training_category)"
                                                    class="text-[9px] font-bold px-1.5 py-0.2 rounded border"
                                                >
                                                    {{ s.training_category }}
                                                </span>
                                            </div>
                                            <div class="font-semibold text-slate-800 truncate" :title="s.course_title">
                                                {{ s.course_title }}
                                            </div>
                                        </td>

                                        <!-- Lokasi -->
                                        <td class="py-3.5 px-3">
                                            <div class="flex items-center gap-1 text-slate-700">
                                                <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                                </svg>
                                                <span class="truncate max-w-[140px]">{{ s.location }}</span>
                                            </div>
                                        </td>

                                        <!-- Presensi QR / Kuota -->
                                        <td class="py-3.5 px-3 text-center">
                                            <div v-if="s.quota" class="inline-flex flex-col items-center">
                                                <span class="font-bold text-slate-900 text-[11px]">
                                                    {{ s.present_count }} <span class="text-slate-400 font-normal">/ {{ s.quota }}</span>
                                                </span>
                                                <div class="w-16 h-1.5 bg-slate-100 rounded-full overflow-hidden mt-1 border border-slate-200/60">
                                                    <div
                                                        class="h-full bg-blue-600 rounded-full"
                                                        :style="{ width: Math.min(100, Math.round(((s.present_count || 0) / s.quota) * 100)) + '%' }"
                                                    ></div>
                                                </div>
                                            </div>
                                            <span v-else class="text-slate-400 text-[11px]">-</span>
                                        </td>

                                        <!-- Status Sesi -->
                                        <td class="py-3.5 px-3 text-center">
                                            <span
                                                :class="getScheduleStatusPill(s.plan_status, s.is_active).bg"
                                                class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold border"
                                            >
                                                <span :class="getScheduleStatusPill(s.plan_status, s.is_active).dot" class="w-1.5 h-1.5 rounded-full"></span>
                                                <span>{{ getScheduleStatusPill(s.plan_status, s.is_active).label }}</span>
                                            </span>
                                        </td>

                                        <!-- Aksi -->
                                        <td class="py-3.5 px-4 text-center" @click.stop>
                                            <button
                                                type="button"
                                                @click="openScheduleModal(s)"
                                                class="px-2.5 py-1.5 text-[11px] font-bold text-blue-700 bg-blue-50 hover:bg-blue-100 active:scale-[0.98] rounded-xl border border-blue-200/80 transition-all flex items-center gap-1 mx-auto"
                                            >
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                </svg>
                                                <span>Detail</span>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ============================================================= -->
            <!-- 3. HIGH-END FILTER DECK & ACTION CTAS                          -->
            <!-- ============================================================= -->
            <section class="p-1 rounded-3xl bg-slate-100/80 ring-1 ring-slate-900/5 shadow-sm">
                <div class="bg-white rounded-[calc(1.5rem-1px)] p-4 sm:p-5 shadow-sm">
                    <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-4">
                        <!-- Filters Group -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-2.5 flex-1">
                            <!-- Search -->
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>
                                <input
                                    v-model="searchQuery"
                                    @keyup.enter="applyFilters"
                                    type="text"
                                    placeholder="Cari nama, NIK, email..."
                                    class="w-full pl-9 pr-3 py-2 text-xs font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all"
                                />
                            </div>

                            <!-- Bulan Kinerja -->
                            <div class="relative">
                                <input
                                    id="trainer-performance-month"
                                    v-model="selectedMonth"
                                    @change="applyFilters"
                                    type="month"
                                    title="Pilih Bulan Kinerja Trainer"
                                    class="w-full px-3 py-2 text-xs font-mono font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-700"
                                />
                            </div>

                            <!-- Tipe Trainer -->
                            <div>
                                <select
                                    v-model="selectedTrainerType"
                                    @change="applyFilters"
                                    class="w-full px-3 py-2 text-xs font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-700"
                                >
                                    <option value="">Semua Tipe Instruktur</option>
                                    <option value="INTERNAL_SME">Internal SME</option>
                                    <option value="FULL_TIME_LND">Full-Time LnD</option>
                                    <option value="EXTERNAL_VENDOR">External Vendor</option>
                                </select>
                            </div>

                            <!-- Status Roster Hari Ini -->
                            <div>
                                <select
                                    v-model="selectedRosterStatus"
                                    @change="applyFilters"
                                    class="w-full px-3 py-2 text-xs font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-700"
                                >
                                    <option value="">Semua Status Roster</option>
                                    <option value="on_duty">ON DUTY (Masuk Kerja)</option>
                                    <option value="off_roster">OFF ROSTER (Libur)</option>
                                    <option value="annual_leave">CUTI TAHUNAN</option>
                                    <option value="sick">SAKIT (SKD Dokter)</option>
                                    <option value="site_visit">SITE VISIT (Dinas)</option>
                                </select>
                            </div>

                            <!-- Departemen -->
                            <div>
                                <select
                                    v-model="selectedDepartment"
                                    @change="applyFilters"
                                    class="w-full px-3 py-2 text-xs font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-700"
                                >
                                    <option value="">Semua Departemen</option>
                                    <option v-for="dept in departments" :key="dept" :value="dept">{{ dept }}</option>
                                </select>
                            </div>
                        </div>

                        <!-- Action Island Buttons -->
                        <div class="flex items-center gap-2 shrink-0 flex-wrap justify-end">
                            <button
                                type="button"
                                @click="applyFilters"
                                class="px-3.5 py-2 text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 active:scale-[0.98] rounded-xl transition-all duration-200"
                            >
                                Terapkan
                            </button>

                            <button
                                v-if="hasActiveFilters"
                                type="button"
                                @click="resetFilters"
                                class="px-3 py-2 text-xs font-semibold text-rose-600 hover:text-rose-700 hover:bg-rose-50 active:scale-[0.98] rounded-xl transition-all duration-200"
                                title="Reset semua filter"
                            >
                                Reset Filter
                            </button>

                            <!-- Master Roster Link (Pill CTA) -->
                            <Link
                                href="/admin/trainers/master-roster"
                                class="group px-3.5 py-2 text-xs font-bold text-blue-700 bg-blue-50/80 border border-blue-200 hover:bg-blue-100/90 active:scale-[0.98] rounded-xl transition-all duration-200 flex items-center gap-2 shadow-sm"
                            >
                                <div class="w-5 h-5 rounded-full bg-blue-600/10 text-blue-700 flex items-center justify-center group-hover:scale-105 transition-transform">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                                <span>Master Kalender Roster</span>
                            </Link>

                            <!-- Angkat Manpower Button (High-End Nested CTA) -->
                            <button
                                type="button"
                                @click="openPromoteModal"
                                class="group px-4 py-2 text-xs font-bold text-white bg-gradient-to-r from-blue-700 via-blue-600 to-indigo-600 hover:from-blue-800 hover:to-indigo-700 active:scale-[0.98] rounded-xl shadow-sm shadow-blue-500/25 flex items-center gap-2 transition-all duration-200 ring-1 ring-white/20"
                            >
                                <span>Angkat Manpower Jadi Trainer</span>
                                <div class="w-5 h-5 rounded-full bg-white/20 text-white flex items-center justify-center group-hover:rotate-90 transition-transform">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                                    </svg>
                                </div>
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ============================================================= -->
            <!-- 3. TRAINER MANAGEMENT TABLE (DOUBLE-BEZEL CONTAINER)          -->
            <!-- ============================================================= -->
            <section class="p-1 rounded-3xl bg-slate-100/80 ring-1 ring-slate-900/5 shadow-sm overflow-hidden">
                <div class="bg-white rounded-[calc(1.5rem-1px)] overflow-hidden shadow-sm">
                    <!-- Table Header Eyebrow -->
                    <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                        <div>
                            <h2 class="text-xs font-black uppercase tracking-wider text-slate-800">
                                Daftar Instruktur Terdaftar
                            </h2>
                            <p class="text-[11px] text-slate-500 mt-0.5">
                                Menampilkan {{ trainers.length }} trainer aktif dengan riwayat kompetensi dan evaluasi.
                            </p>
                        </div>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600 border border-slate-200">
                            {{ summary.selected_month_label || 'Bulan Berjalan' }}
                        </span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-slate-700">
                            <thead class="bg-slate-50/80 border-b border-slate-200/80 text-[10px] font-black uppercase tracking-wider text-slate-500 select-none">
                                <tr>
                                    <th class="py-3.5 px-5">Instruktur / Trainer</th>
                                    <th class="py-3.5 px-3">Tipe &amp; Pola Roster</th>
                                    <th class="py-3.5 px-3">Status Hari Ini</th>
                                    <th class="py-3.5 px-3 text-center">Kompetensi Ajar</th>
                                    <th class="py-3.5 px-3 text-right">Jam · {{ summary.selected_month_label }}</th>
                                    <th class="py-3.5 px-3 text-right">Peserta QR</th>
                                    <th class="py-3.5 px-3 text-center">Kepuasan Peserta</th>
                                    <th class="py-3.5 px-5 text-center">Aksi Kendali</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="t in trainers"
                                    :key="t.id"
                                    class="hover:bg-slate-50/90 transition-colors group"
                                >
                                    <!-- Kolom 1: Profil Trainer -->
                                    <td class="py-3.5 px-5">
                                        <div class="flex items-center gap-3">
                                            <div
                                                :class="getAvatarGradient(t.id)"
                                                class="w-9 h-9 rounded-xl bg-gradient-to-tr text-white flex items-center justify-center font-black text-xs uppercase shrink-0 shadow-sm ring-2 ring-white"
                                            >
                                                {{ t.name ? t.name.charAt(0) : 'T' }}
                                            </div>
                                            <div class="min-w-0">
                                                <div class="font-extrabold text-slate-900 text-xs truncate group-hover:text-blue-600 transition-colors">
                                                    {{ t.name }}
                                                </div>
                                                <div class="text-[11px] text-slate-500 font-mono mt-0.5 flex items-center gap-1.5 flex-wrap">
                                                    <span class="font-bold text-slate-700">{{ t.nik || '-' }}</span>
                                                    <span>&bull;</span>
                                                    <span class="truncate">{{ t.department }}</span>
                                                </div>
                                                <div class="text-[10px] text-slate-400 truncate mt-0.5">{{ t.job_position }}</div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Kolom 2: Tipe & Pola Roster -->
                                    <td class="py-3.5 px-3">
                                        <span
                                            :class="getTrainerTypeLabel(t.trainer_type).class"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[10px] font-bold border"
                                        >
                                            <span :class="getTrainerTypeLabel(t.trainer_type).dot" class="w-1.5 h-1.5 rounded-full inline-block"></span>
                                            {{ getTrainerTypeLabel(t.trainer_type).label }}
                                        </span>
                                        <div class="text-[11px] text-slate-500 mt-1 font-mono">
                                            Pola: {{ t.roster_pattern }}
                                        </div>
                                    </td>

                                    <!-- Kolom 3: Roster Hari Ini -->
                                    <td class="py-3.5 px-3">
                                        <span
                                            :class="getStatusBadge(t.today_roster.status).bg"
                                            class="px-2.5 py-1 rounded-full text-[10px] font-extrabold border inline-flex items-center gap-1.5"
                                        >
                                            <span :class="getStatusBadge(t.today_roster.status).dot" class="w-1.5 h-1.5 rounded-full inline-block"></span>
                                            {{ getStatusBadge(t.today_roster.status).label }}
                                        </span>
                                        <div v-if="t.today_roster.notes" class="text-[10px] text-slate-400 mt-1 italic max-w-[140px] truncate">
                                            {{ t.today_roster.notes }}
                                        </div>
                                    </td>

                                    <!-- Kolom 4: Jumlah Kompetensi Ajar -->
                                    <td class="py-3.5 px-3 text-center">
                                        <span class="px-2.5 py-1 rounded-full bg-blue-50/90 text-blue-700 font-black text-[11px] border border-blue-200/90 ring-1 ring-blue-500/15">
                                            {{ t.authorized_skills_count }} Skill
                                        </span>
                                        <div v-if="t.authorized_skills_preview.length" class="flex flex-wrap justify-center gap-1 mt-1.5 max-w-[140px] mx-auto">
                                            <span
                                                v-for="sk in t.authorized_skills_preview"
                                                :key="sk.id"
                                                class="text-[9px] font-mono px-1.5 py-0.5 bg-slate-100 rounded-md text-slate-700 border border-slate-200"
                                            >
                                                {{ sk.code }}
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Kolom 5: Jam Mengajar Bulan Ini -->
                                    <td class="py-3.5 px-3 text-right">
                                        <div class="font-black text-slate-900 text-xs">
                                            {{ t.month_teaching_hours }} <span class="text-[10px] font-semibold text-slate-500">Jam</span>
                                        </div>
                                        <div class="text-[10px] text-slate-400 mt-0.5">Bulan Ini</div>
                                    </td>

                                    <!-- Kolom 6: Total Peserta QR -->
                                    <td class="py-3.5 px-3 text-right">
                                        <div class="font-black text-indigo-700 text-xs">
                                            {{ t.total_scanned_participants }} <span class="text-[10px] font-semibold text-indigo-500">Org</span>
                                        </div>
                                        <div class="text-[10px] text-slate-400 mt-0.5">Total Scan QR</div>
                                    </td>

                                    <!-- Kolom 7: Rata-Rata Rating Peserta -->
                                    <td class="py-3.5 px-3 text-center">
                                        <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-200 font-extrabold text-xs">
                                            <span class="text-amber-500">★</span> {{ t.average_rating }}
                                        </div>
                                        <div class="text-[10px] text-slate-400 mt-0.5">({{ t.evaluations_count }} ulasan)</div>
                                    </td>

                                    <!-- Kolom 8: Aksi Kendali (Nested Button) -->
                                    <td class="py-3.5 px-5 text-center">
                                        <Link
                                            :href="`/admin/trainers/${t.id}`"
                                            class="group/btn inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-blue-50/80 hover:bg-blue-600 text-blue-700 hover:text-white text-xs font-bold border border-blue-200/90 hover:border-blue-600 active:scale-[0.98] transition-all duration-200 shadow-sm"
                                        >
                                            <span>Detail 360°</span>
                                            <div class="w-4 h-4 rounded-full bg-blue-600/10 group-hover/btn:bg-white/20 flex items-center justify-center transition-colors">
                                                <svg class="w-2.5 h-2.5 group-hover/btn:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                                                </svg>
                                            </div>
                                        </Link>
                                    </td>
                                </tr>

                                <tr v-if="trainers.length === 0">
                                    <td colspan="8" class="py-16 text-center text-slate-400">
                                        <div class="max-w-sm mx-auto space-y-2">
                                            <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 mx-auto flex items-center justify-center">
                                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                                </svg>
                                            </div>
                                            <div class="font-bold text-slate-700 text-xs">Tidak Ada Data Trainer</div>
                                            <div class="text-[11px] text-slate-400">
                                                Tidak ditemukan trainer yang cocok dengan kata kunci atau filter yang Anda terapkan.
                                            </div>
                                            <button
                                                type="button"
                                                @click="resetFilters"
                                                class="mt-2 text-xs font-bold text-blue-600 hover:text-blue-700 underline"
                                            >
                                                Bersihkan Filter
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>

        <!-- ============================================================= -->
        <!-- MODAL: ANGKAT MANPOWER JADI TRAINER (DOUBLE-BEZEL HARDWARE)   -->
        <!-- ============================================================= -->
        <div
            v-if="showPromoteModal"
            class="fixed inset-0 z-50 bg-slate-950/50 backdrop-blur-md flex items-center justify-center p-4 overflow-y-auto"
        >
            <div class="p-1 rounded-3xl bg-gradient-to-b from-slate-200 to-slate-300 max-w-xl w-full shadow-2xl animate-in fade-in zoom-in-95 duration-200">
                <div class="bg-white rounded-[calc(1.5rem-2px)] p-6 max-h-[90vh] overflow-y-auto shadow-sm">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-5">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center border border-blue-200">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-sm font-black text-slate-900 tracking-tight">Angkat Manpower Jadi Trainer</h3>
                                <p class="text-xs text-slate-500 mt-0.5">Pilih karyawan terdaftar, tentukan status instruktur &amp; lisensi</p>
                            </div>
                        </div>
                        <button
                            type="button"
                            @click="showPromoteModal = false"
                            class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 hover:text-slate-800 flex items-center justify-center transition-colors"
                        >
                            ✕
                        </button>
                    </div>

                    <form @submit.prevent="submitPromote" class="space-y-4">
                        <!-- 1. Pilih Manpower -->
                        <div>
                            <label class="block text-xs font-bold text-slate-800 mb-1">
                                Pilih Karyawan Manpower <span class="text-rose-500">*</span>
                            </label>
                            <select
                                v-model="promoteForm.user_id"
                                required
                                class="w-full text-xs font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-800"
                            >
                                <option value="">-- Pilih Karyawan Terdaftar di Database --</option>
                                <option
                                    v-for="emp in eligibleManpower"
                                    :key="emp.id"
                                    :value="emp.id"
                                >
                                    {{ emp.name }} (NIK: {{ emp.nik || '-' }}) — Dept: {{ emp.department || '-' }}
                                </option>
                            </select>
                            <p v-if="promoteForm.errors.user_id" class="text-xs text-rose-600 mt-1 font-semibold">
                                {{ promoteForm.errors.user_id }}
                            </p>
                        </div>

                        <!-- 2. Tipe Trainer & Pola Roster -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-800 mb-1">
                                    Tipe Trainer <span class="text-rose-500">*</span>
                                </label>
                                <select
                                    v-model="promoteForm.trainer_type"
                                    required
                                    class="w-full text-xs font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-800"
                                >
                                    <option value="INTERNAL_SME">INTERNAL_SME (Subject Matter Expert)</option>
                                    <option value="FULL_TIME_LND">FULL_TIME_LND (Instruktur Tetap LnD)</option>
                                    <option value="EXTERNAL_VENDOR">EXTERNAL_VENDOR (Lembaga/Vendor Luar)</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-800 mb-1">
                                    Pola Roster Kerja <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    v-model="promoteForm.roster_pattern"
                                    required
                                    type="text"
                                    placeholder="Contoh: 5-2, 14-7 Site Roster, dll"
                                    class="w-full text-xs font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-800"
                                />
                            </div>
                        </div>

                        <!-- 3. Penetapan Kompetensi Ajar (Certified Skills) -->
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="block text-xs font-bold text-slate-800">
                                    Kompetensi Yang Berhak Diajarkan
                                </label>
                                <span class="text-[10px] text-slate-400 font-medium">Bisa memilih lebih dari satu</span>
                            </div>
                            <div class="max-h-44 overflow-y-auto border border-slate-200 rounded-xl p-2.5 bg-slate-50/70 space-y-1">
                                <label
                                    v-for="skill in masterSkills"
                                    :key="skill.id"
                                    class="flex items-center gap-2.5 text-xs text-slate-700 hover:bg-white p-1.5 rounded-lg cursor-pointer transition-colors border border-transparent hover:border-slate-200/80"
                                >
                                    <input
                                        type="checkbox"
                                        :value="skill.id"
                                        v-model="promoteForm.skill_ids"
                                        class="rounded text-blue-600 focus:ring-blue-500 w-4 h-4"
                                    />
                                    <span class="font-black text-blue-700 font-mono text-[11px] bg-blue-50 px-1.5 py-0.5 rounded border border-blue-200">{{ skill.code }}</span>
                                    <span class="font-medium text-slate-900 truncate">{{ skill.name }}</span>
                                    <span class="text-[10px] text-slate-400 ml-auto shrink-0">({{ skill.category }})</span>
                                </label>
                            </div>
                        </div>

                        <!-- 4. Lisensi ToT / BNSP -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-800 mb-1">
                                    Jenis Lisensi ToT
                                </label>
                                <input
                                    v-model="promoteForm.license_type"
                                    type="text"
                                    placeholder="ToT BNSP Level 4"
                                    class="w-full text-xs font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-800"
                                />
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-800 mb-1">
                                    Nomor Lisensi / SK
                                </label>
                                <input
                                    v-model="promoteForm.license_number"
                                    type="text"
                                    placeholder="BNSP-TOT/2026/..."
                                    class="w-full text-xs font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-800"
                                />
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-800 mb-1">
                                    Berlaku Hingga
                                </label>
                                <input
                                    v-model="promoteForm.valid_until"
                                    type="date"
                                    class="w-full text-xs font-mono font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-800"
                                />
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
                            <button
                                type="button"
                                @click="showPromoteModal = false"
                                class="px-4 py-2 text-xs font-bold text-slate-600 hover:text-slate-800 rounded-xl bg-slate-100 hover:bg-slate-200 active:scale-[0.98] transition-all"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                :disabled="promoteForm.processing || !promoteForm.user_id"
                                class="px-5 py-2 text-xs font-bold text-white bg-gradient-to-r from-blue-700 to-indigo-600 hover:from-blue-800 hover:to-indigo-700 rounded-xl shadow-sm shadow-blue-500/25 active:scale-[0.98] disabled:opacity-50 transition-all flex items-center gap-2"
                            >
                                <span>{{ promoteForm.processing ? 'Menyimpan...' : 'Simpan & Angkat Jadi Trainer' }}</span>
                                <svg v-if="!promoteForm.processing" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ============================================================= -->
            <!-- MODAL DETAIL JADWAL MENGAJAR & ROSTER TRAINER (RICH DIALOG)   -->
            <!-- ============================================================= -->
            <div
                v-if="selectedScheduleDetail"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs transition-opacity duration-200"
                @click.self="closeScheduleModal"
            >
                <div class="relative w-full max-w-2xl bg-white rounded-3xl shadow-2xl ring-1 ring-slate-900/10 overflow-hidden transform transition-all">
                    <!-- Modal Header -->
                    <div class="p-5 bg-gradient-to-r from-slate-900 via-blue-950 to-indigo-950 text-white flex items-center justify-between">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-blue-500/20 text-blue-200 border border-blue-400/30">
                                    Detail Agenda Mengajar
                                </span>
                                <span
                                    :class="getScheduleStatusPill(selectedScheduleDetail.plan_status, selectedScheduleDetail.is_active).bg"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-bold border"
                                >
                                    <span :class="getScheduleStatusPill(selectedScheduleDetail.plan_status, selectedScheduleDetail.is_active).dot" class="w-1.5 h-1.5 rounded-full"></span>
                                    <span>{{ getScheduleStatusPill(selectedScheduleDetail.plan_status, selectedScheduleDetail.is_active).label }}</span>
                                </span>
                            </div>
                            <h3 class="text-base font-black text-white mt-1.5 tracking-tight">
                                {{ selectedScheduleDetail.title || selectedScheduleDetail.course_title }}
                            </h3>
                        </div>

                        <button
                            type="button"
                            @click="closeScheduleModal"
                            class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="p-6 space-y-4 max-h-[80vh] overflow-y-auto text-xs text-slate-700">
                        <!-- Trainer Profile Spotlight -->
                        <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80 flex items-center gap-4">
                            <div
                                :class="getAvatarGradient(selectedScheduleDetail.trainer_id || 0)"
                                class="w-14 h-14 rounded-2xl bg-gradient-to-tr text-white flex items-center justify-center font-black text-lg uppercase shrink-0 shadow-md ring-4 ring-white"
                            >
                                {{ (selectedScheduleDetail.trainer_name || 'T').substring(0, 2) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-black text-slate-900">{{ selectedScheduleDetail.trainer_name }}</span>
                                    <span
                                        :class="getTrainerTypeLabel(selectedScheduleDetail.trainer_type).class"
                                        class="px-2 py-0.5 rounded-full text-[9px] font-bold border"
                                    >
                                        {{ getTrainerTypeLabel(selectedScheduleDetail.trainer_type).label }}
                                    </span>
                                </div>
                                <div class="text-[11px] text-slate-500 mt-0.5">
                                    NIK: <span class="font-mono font-bold text-slate-700">{{ selectedScheduleDetail.trainer_nik }}</span> · Departemen: <span class="font-bold text-slate-700">{{ selectedScheduleDetail.trainer_dept }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Grid Detail: Waktu & Lokasi -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="p-3.5 rounded-2xl bg-slate-50/70 border border-slate-200/80">
                                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1 flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    <span>Waktu Pelaksanaan</span>
                                </div>
                                <div class="font-black text-slate-900 text-sm">
                                    {{ selectedScheduleDetail.day_name }}, {{ selectedScheduleDetail.date_formatted }}
                                </div>
                                <div class="font-mono text-blue-700 font-bold mt-0.5">
                                    {{ selectedScheduleDetail.time_range }}
                                </div>
                                <div class="text-[10px] text-slate-500 mt-1">
                                    Durasi Terhitung: <span class="font-bold text-slate-800">{{ selectedScheduleDetail.duration_hours }} Jam Mengajar Sah</span>
                                </div>
                            </div>

                            <div class="p-3.5 rounded-2xl bg-slate-50/70 border border-slate-200/80">
                                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1 flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    </svg>
                                    <span>Lokasi &amp; Fasilitas</span>
                                </div>
                                <div class="font-black text-slate-900 text-sm">
                                    {{ selectedScheduleDetail.location }}
                                </div>
                                <div class="text-[10px] text-slate-500 mt-1">
                                    Site PBJ Pani Gold Project Training Facility
                                </div>
                            </div>
                        </div>

                        <!-- Materi & Kursus Pelatihan -->
                        <div class="p-4 rounded-2xl bg-slate-50/70 border border-slate-200/80 space-y-2">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                Informasi Materi Pelatihan
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-xs font-black text-blue-700 bg-blue-50 px-2 py-0.5 rounded border border-blue-200">
                                    {{ selectedScheduleDetail.course_code }}
                                </span>
                                <span
                                    :class="getCategoryColor(selectedScheduleDetail.training_category)"
                                    class="px-2 py-0.5 rounded text-[10px] font-bold border"
                                >
                                    {{ selectedScheduleDetail.training_category }}
                                </span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-600 border border-slate-200 uppercase">
                                    {{ selectedScheduleDetail.training_source }}
                                </span>
                            </div>
                            <div class="font-bold text-slate-900 text-sm">
                                {{ selectedScheduleDetail.course_title }}
                            </div>
                        </div>

                        <!-- Kehadiran & Partisipan (Jika Sesi Pelatihan) -->
                        <div v-if="selectedScheduleDetail.quota" class="p-4 rounded-2xl bg-indigo-50/40 border border-indigo-200/70 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-black uppercase tracking-wider text-indigo-900">
                                    Realisasi Kehadiran &amp; Kapasitas Sesi
                                </span>
                                <span class="font-mono font-bold text-xs text-indigo-700">
                                    {{ selectedScheduleDetail.present_count }} / {{ selectedScheduleDetail.quota }} Peserta Hadir
                                </span>
                            </div>

                            <!-- Progress Bar -->
                            <div class="w-full h-2.5 bg-indigo-100 rounded-full overflow-hidden border border-indigo-200/80">
                                <div
                                    class="h-full bg-gradient-to-r from-blue-600 to-indigo-600 rounded-full transition-all duration-500"
                                    :style="{ width: Math.min(100, Math.round(((selectedScheduleDetail.present_count || 0) / selectedScheduleDetail.quota) * 100)) + '%' }"
                                ></div>
                            </div>

                            <div class="grid grid-cols-3 gap-2 text-center pt-1">
                                <div class="p-2 rounded-xl bg-white border border-indigo-100">
                                    <div class="text-[9px] font-semibold text-slate-400 uppercase">Hadir QR</div>
                                    <div class="text-sm font-black text-emerald-600">{{ selectedScheduleDetail.present_count }}</div>
                                </div>
                                <div class="p-2 rounded-xl bg-white border border-indigo-100">
                                    <div class="text-[9px] font-semibold text-slate-400 uppercase">Izin / Sakit</div>
                                    <div class="text-sm font-black text-amber-600">{{ selectedScheduleDetail.permit_count || 0 }}</div>
                                </div>
                                <div class="p-2 rounded-xl bg-white border border-indigo-100">
                                    <div class="text-[9px] font-semibold text-slate-400 uppercase">Alpa</div>
                                    <div class="text-sm font-black text-rose-600">{{ selectedScheduleDetail.absent_count || 0 }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="p-4 bg-slate-50 border-t border-slate-100 flex items-center justify-between">
                        <Link
                            v-if="selectedScheduleDetail.course_id"
                            href="/admin/training-administration"
                            class="px-4 py-2 text-xs font-bold text-blue-700 hover:text-blue-800 bg-blue-50 hover:bg-blue-100 border border-blue-200 rounded-xl transition-all flex items-center gap-1.5"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                            </svg>
                            <span>Presensi QR &amp; Kelas</span>
                        </Link>
                        <div v-else></div>

                        <button
                            type="button"
                            @click="closeScheduleModal"
                            class="px-5 py-2 text-xs font-bold text-slate-700 bg-white hover:bg-slate-100 border border-slate-200 rounded-xl transition-all active:scale-[0.98] shadow-sm"
                        >
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
