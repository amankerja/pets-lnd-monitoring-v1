<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    summary: {
        type: Object,
        default: () => ({
            total_trainers: 0,
            active_trainers: 0,
            total_target_hours: 0,
            total_plan_hours: 0,
            total_realisasi_hours: 0,
            total_gap_hours: 0,
            overall_vs_target_pct: 0,
            overall_vs_plan_pct: 0,
            total_sessions_completed: 0,
            total_participants_served: 0,
            avg_realisasi_per_trainer: 0,
            period_label: '',
            period_type: 'month',
            start_date: '',
            end_date: '',
        }),
    },
    trainers: {
        type: Array,
        default: () => [],
    },
    historyTrend: {
        type: Array,
        default: () => [],
    },
    recentSessionLogs: {
        type: Array,
        default: () => [],
    },
    departments: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({
            period_type: 'month',
            date: '',
            period_label: '',
            prev_date: '',
            next_date: '',
            search: '',
            trainer_type: '',
            department: '',
        }),
    },
});

// -------------------------------------------------------------
// State Filter & Tampilan
// -------------------------------------------------------------
const activeTab = ref('summary'); // 'summary' | 'history'
const searchQuery = ref(props.filters.search || '');
const selectedTrainerType = ref(props.filters.trainer_type || '');
const selectedDepartment = ref(props.filters.department || '');
const periodType = ref(props.filters.period_type || 'month');
const currentDate = ref(props.filters.date || new Date().toISOString().substring(0, 10));

// Expandable Rows untuk Program Breakdown
const expandedTrainerIds = ref(new Set());
const toggleExpandTrainer = (id) => {
    if (expandedTrainerIds.value.has(id)) {
        expandedTrainerIds.value.delete(id);
    } else {
        expandedTrainerIds.value.add(id);
    }
};

// Modal Program Breakdown Terpisah
const selectedTrainerForModal = ref(null);
const showProgramModal = ref(false);
const openProgramModal = (trainer) => {
    selectedTrainerForModal.value = trainer;
    showProgramModal.value = true;
};
const closeProgramModal = () => {
    showProgramModal.value = false;
    selectedTrainerForModal.value = null;
};

// Modal Set / Tambah Target Training Hours
const showTargetModal = ref(false);
const targetForm = useForm({
    trainer_id: '',
    period: 'monthly',
    target_teaching_hours: 40,
    target_participants_count: 50,
    target_rating: 4.5,
    start_date: '',
    due_date: '',
    title: '',
    description: '',
});

const openTargetModal = (trainer = null) => {
    if (trainer) {
        targetForm.trainer_id = trainer.id;
        targetForm.period = periodType.value === 'week' ? 'weekly' : (periodType.value === 'year' ? 'yearly' : 'monthly');
        targetForm.target_teaching_hours = trainer.target_hours > 0 ? trainer.target_hours : 40;
        targetForm.target_participants_count = trainer.target_kpi?.target_participants_count || 50;
        targetForm.target_rating = trainer.target_kpi?.target_rating || 4.5;
        targetForm.start_date = props.summary.start_date || '';
        targetForm.due_date = props.summary.end_date || '';
        targetForm.title = trainer.target_kpi?.title || `Target Jam Mengajar - ${trainer.name} (${props.summary.period_label})`;
        targetForm.description = '';
    } else {
        targetForm.trainer_id = props.trainers[0]?.id || '';
        targetForm.period = periodType.value === 'week' ? 'weekly' : (periodType.value === 'year' ? 'yearly' : 'monthly');
        targetForm.target_teaching_hours = 40;
        targetForm.target_participants_count = 50;
        targetForm.target_rating = 4.5;
        targetForm.start_date = props.summary.start_date || '';
        targetForm.due_date = props.summary.end_date || '';
        targetForm.title = `Target Jam Mengajar (${props.summary.period_label})`;
        targetForm.description = '';
    }
    showTargetModal.value = true;
};

const closeTargetModal = () => {
    showTargetModal.value = false;
    targetForm.reset();
};

const submitTarget = () => {
    targetForm.post('/trainer/hours/target', {
        preserveScroll: true,
        onSuccess: () => {
            closeTargetModal();
        },
    });
};

// Quick preset hours helper
const setPresetHours = (hours) => {
    targetForm.target_teaching_hours = hours;
};

// -------------------------------------------------------------
// Filter Navigation & Actions
// -------------------------------------------------------------
const changePeriodType = (newType) => {
    if (periodType.value === newType) return;
    periodType.value = newType;
    applyFilters(newType, currentDate.value);
};

const navigatePeriod = (targetDate) => {
    currentDate.value = targetDate;
    applyFilters(periodType.value, targetDate);
};

const resetToCurrent = () => {
    const today = new Date().toISOString().substring(0, 10);
    currentDate.value = today;
    applyFilters(periodType.value, today);
};

let searchDebounceTimer = null;
const onSearchInput = () => {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => {
        applyFilters(periodType.value, currentDate.value);
    }, 400);
};

const applyFilters = (type = periodType.value, date = currentDate.value) => {
    router.get(
        '/trainer/hours',
        {
            period_type: type,
            date: date,
            search: searchQuery.value || undefined,
            trainer_type: selectedTrainerType.value || undefined,
            department: selectedDepartment.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    );
};

// Max value untuk Grafik Riwayat Trend
const maxTrendValue = computed(() => {
    if (!props.historyTrend || props.historyTrend.length === 0) return 50;
    const maxVal = Math.max(
        ...props.historyTrend.map((item) =>
            Math.max(Number(item.realisasi_hours || 0), Number(item.plan_hours || 0), Number(item.target_hours || 0))
        )
    );
    return Math.max(20, Math.ceil(maxVal * 1.15));
});
</script>

<template>
    <Head title="Training Hours - Summary All Trainers" />

    <AdminLayout
        title="Training Hours - Summary All Trainers"
        subtitle="Rekapitulasi jam pelatihan seluruh instruktur, monitoring target vs rencana (plan) vs realisasi, dan riwayat jam mengajar per program."
    >
        <div class="space-y-6 pb-12">
            <!-- ============================================================= -->
            <!-- 1. HEADER CONTROLS: PERIOD PICKER, TABS & ACTION BUTTONS     -->
            <!-- ============================================================= -->
            <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4 bg-white dark:bg-slate-900 p-4 sm:p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm">
                <!-- Left: Period Switcher (Minggu, Bulan, Tahun) -->
                <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider hidden sm:inline">Periode:</span>
                    <div class="inline-flex p-1 bg-slate-100 dark:bg-slate-800/80 rounded-xl border border-slate-200/60 dark:border-slate-700/60">
                        <button
                            type="button"
                            @click="changePeriodType('week')"
                            :class="[
                                'px-3.5 py-1.5 text-xs font-bold rounded-lg transition-all duration-200',
                                periodType === 'week'
                                    ? 'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-sm'
                                    : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white',
                            ]"
                        >
                            📅 Mingguan (Weekly)
                        </button>
                        <button
                            type="button"
                            @click="changePeriodType('month')"
                            :class="[
                                'px-3.5 py-1.5 text-xs font-bold rounded-lg transition-all duration-200',
                                periodType === 'month'
                                    ? 'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-sm'
                                    : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white',
                            ]"
                        >
                            🗓️ Bulanan (Monthly)
                        </button>
                        <button
                            type="button"
                            @click="changePeriodType('year')"
                            :class="[
                                'px-3.5 py-1.5 text-xs font-bold rounded-lg transition-all duration-200',
                                periodType === 'year'
                                    ? 'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-sm'
                                    : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white',
                            ]"
                        >
                            📊 Tahunan (Yearly)
                        </button>
                    </div>

                    <!-- Date Stepper (Prev / Next) -->
                    <div class="flex items-center gap-1.5 bg-slate-50 dark:bg-slate-800/50 p-1 rounded-xl border border-slate-200/80 dark:border-slate-700/80">
                        <button
                            type="button"
                            @click="navigatePeriod(filters.prev_date)"
                            title="Periode Sebelumnya"
                            class="p-1.5 text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-slate-700 rounded-lg transition"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                            </svg>
                        </button>

                        <div class="px-3 py-1 text-xs font-black text-slate-800 dark:text-slate-100 flex items-center gap-2">
                            <span>{{ summary.period_label }}</span>
                        </div>

                        <button
                            type="button"
                            @click="navigatePeriod(filters.next_date)"
                            title="Periode Berikutnya"
                            class="p-1.5 text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-slate-700 rounded-lg transition"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>

                        <button
                            type="button"
                            @click="resetToCurrent"
                            class="ml-1 px-2.5 py-1 text-[11px] font-bold text-slate-600 dark:text-slate-300 hover:text-blue-600 bg-white dark:bg-slate-700/70 border border-slate-200 dark:border-slate-600 rounded-lg transition"
                        >
                            Hari Ini
                        </button>
                    </div>
                </div>

                <!-- Right: Action CTA & Quick Add Target -->
                <div class="flex items-center gap-2.5">
                    <button
                        type="button"
                        @click="openTargetModal(null)"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-xl text-xs font-black shadow-md shadow-blue-500/20 active:scale-[0.98] transition-all"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>+ Atur Target Trainer</span>
                    </button>

                    <Link
                        href="/trainer/schedule"
                        class="inline-flex items-center gap-1.5 px-3 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-bold border border-slate-200 dark:border-slate-700 transition"
                    >
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <span>Master Roster</span>
                    </Link>
                </div>
            </div>

            <!-- ============================================================= -->
            <!-- 2. EXECUTIVE SUMMARY CARDS (ALL TRAINERS COMBINED)            -->
            <!-- ============================================================= -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Card 1: Realisasi Training Hours -->
                <div class="relative overflow-hidden rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-black uppercase tracking-wider text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30 px-2.5 py-1 rounded-lg">
                            Realisasi Hours
                        </span>
                        <div class="w-9 h-9 rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="text-3xl font-black text-slate-900 dark:text-white tracking-tight flex items-baseline gap-1.5">
                            <span>{{ summary.total_realisasi_hours }}</span>
                            <span class="text-xs font-bold text-slate-500 dark:text-slate-400">Jam Mengajar</span>
                        </div>
                        <div class="mt-2 flex items-center justify-between text-xs">
                            <span class="text-slate-500 dark:text-slate-400">vs Plan Sesi:</span>
                            <span :class="[
                                'font-bold px-1.5 py-0.5 rounded',
                                summary.overall_vs_plan_pct >= 90 ? 'text-emerald-700 bg-emerald-50 dark:bg-emerald-900/30 dark:text-emerald-300' :
                                summary.overall_vs_plan_pct >= 60 ? 'text-blue-700 bg-blue-50 dark:bg-blue-900/30 dark:text-blue-300' :
                                'text-amber-700 bg-amber-50 dark:bg-amber-900/30 dark:text-amber-300'
                            ]">
                                {{ summary.overall_vs_plan_pct }}%
                            </span>
                        </div>
                    </div>
                    <div class="mt-3 w-full bg-slate-100 dark:bg-slate-800 rounded-full h-1.5 overflow-hidden">
                        <div
                            class="bg-blue-600 h-1.5 rounded-full transition-all duration-500"
                            :style="{ width: `${Math.min(100, summary.overall_vs_plan_pct)}%` }"
                        ></div>
                    </div>
                </div>

                <!-- Card 2: Target Training Hours -->
                <div class="relative overflow-hidden rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-black uppercase tracking-wider text-purple-600 dark:text-purple-400 bg-purple-50 dark:bg-purple-900/30 px-2.5 py-1 rounded-lg">
                            Target Training Hours
                        </span>
                        <div class="w-9 h-9 rounded-xl bg-purple-500/10 text-purple-600 dark:text-purple-400 flex items-center justify-center font-bold">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="text-3xl font-black text-slate-900 dark:text-white tracking-tight flex items-baseline gap-1.5">
                            <span>{{ summary.total_target_hours }}</span>
                            <span class="text-xs font-bold text-slate-500 dark:text-slate-400">Jam Target</span>
                        </div>
                        <div class="mt-2 flex items-center justify-between text-xs">
                            <span class="text-slate-500 dark:text-slate-400">Pencapaian Target:</span>
                            <span :class="[
                                'font-bold px-1.5 py-0.5 rounded',
                                summary.overall_vs_target_pct >= 100 ? 'text-emerald-700 bg-emerald-50 dark:bg-emerald-900/30 dark:text-emerald-300' :
                                summary.overall_vs_target_pct >= 70 ? 'text-indigo-700 bg-indigo-50 dark:bg-indigo-900/30 dark:text-indigo-300' :
                                'text-amber-700 bg-amber-50 dark:bg-amber-900/30 dark:text-amber-300'
                            ]">
                                {{ summary.overall_vs_target_pct }}%
                            </span>
                        </div>
                    </div>
                    <div class="mt-3 w-full bg-slate-100 dark:bg-slate-800 rounded-full h-1.5 overflow-hidden">
                        <div
                            class="bg-purple-600 h-1.5 rounded-full transition-all duration-500"
                            :style="{ width: `${Math.min(100, summary.overall_vs_target_pct)}%` }"
                        ></div>
                    </div>
                </div>

                <!-- Card 3: Plan Training Hours -->
                <div class="relative overflow-hidden rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-black uppercase tracking-wider text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/30 px-2.5 py-1 rounded-lg">
                            Plan Sesi (Jadwal)
                        </span>
                        <div class="w-9 h-9 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="text-3xl font-black text-slate-900 dark:text-white tracking-tight flex items-baseline gap-1.5">
                            <span>{{ summary.total_plan_hours }}</span>
                            <span class="text-xs font-bold text-slate-500 dark:text-slate-400">Jam Terjadwal</span>
                        </div>
                        <div class="mt-2 flex items-center justify-between text-xs">
                            <span class="text-slate-500 dark:text-slate-400">Sesi Terlaksana:</span>
                            <span class="font-bold text-slate-800 dark:text-slate-200">
                                {{ summary.total_sessions_completed }} Sesi Terverifikasi
                            </span>
                        </div>
                    </div>
                    <div class="mt-3 flex items-center gap-1.5 text-[11px] text-slate-500">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        <span>Total {{ summary.total_participants_served }} peserta terlayani</span>
                    </div>
                </div>

                <!-- Card 4: Trainer Aktif & Produktivitas -->
                <div class="relative overflow-hidden rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-black uppercase tracking-wider text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/30 px-2.5 py-1 rounded-lg">
                            Trainer Aktif
                        </span>
                        <div class="w-9 h-9 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="text-3xl font-black text-slate-900 dark:text-white tracking-tight flex items-baseline gap-1.5">
                            <span>{{ summary.active_trainers }}</span>
                            <span class="text-xs font-bold text-slate-500 dark:text-slate-400">/ {{ summary.total_trainers }} Trainer</span>
                        </div>
                        <div class="mt-2 flex items-center justify-between text-xs">
                            <span class="text-slate-500 dark:text-slate-400">Rata-rata Realisasi:</span>
                            <span class="font-bold text-slate-800 dark:text-slate-200">
                                {{ summary.avg_realisasi_per_trainer }} Jam / Trainer
                            </span>
                        </div>
                    </div>
                    <div class="mt-3 flex items-center gap-1.5 text-[11px] text-slate-500">
                        <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                        <span>Instruktur SME & Full-time</span>
                    </div>
                </div>
            </div>

            <!-- ============================================================= -->
            <!-- 3. RIWAYAT & TREN VISUALISASI (BREAKDOWN PERIODIK)             -->
            <!-- ============================================================= -->
            <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-4 border-b border-slate-100 dark:border-slate-800 gap-2">
                    <div>
                        <h3 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                            <span>📈 Riwayat & Tren Training Hours</span>
                            <span class="text-xs font-medium text-slate-500 dark:text-slate-400">
                                (Breakdown Berdasarkan {{ periodType === 'week' ? 'Hari (Minggu Ini)' : (periodType === 'year' ? 'Bulan (Tahun Berjalan)' : 'Minggu (Bulan Berjalan)') }})
                            </span>
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Komparasi Realisasi Jam Mengajar (Aktual) vs Rencana Sesi (Plan) lintas seluruh instruktur.
                        </p>
                    </div>

                    <div class="flex items-center gap-4 text-xs font-bold">
                        <div class="flex items-center gap-1.5">
                            <span class="w-3 h-3 rounded-sm bg-blue-600"></span>
                            <span class="text-slate-700 dark:text-slate-300">Realisasi Hours</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="w-3 h-3 rounded-sm bg-sky-200 dark:bg-sky-800"></span>
                            <span class="text-slate-700 dark:text-slate-300">Plan Hours</span>
                        </div>
                        <div v-if="periodType === 'year'" class="flex items-center gap-1.5">
                            <span class="w-3 h-3 rounded-sm bg-purple-500"></span>
                            <span class="text-slate-700 dark:text-slate-300">Target Hours</span>
                        </div>
                    </div>
                </div>

                <!-- Interactive Bar Visualizer -->
                <div class="mt-5 overflow-x-auto pb-2">
                    <div class="min-w-[620px] flex items-end justify-between gap-3 h-48 pt-6 px-2">
                        <div
                            v-for="(point, idx) in historyTrend"
                            :key="idx"
                            class="flex-1 flex flex-col items-center h-full justify-end group relative"
                        >
                            <!-- Tooltip Hover -->
                            <div class="absolute -top-12 opacity-0 group-hover:opacity-100 transition-all pointer-events-none z-20 bg-slate-900 text-white text-[11px] rounded-lg px-2.5 py-1.5 shadow-xl whitespace-nowrap">
                                <div class="font-bold">{{ point.full_label }}</div>
                                <div class="text-blue-300">Realisasi: {{ point.realisasi_hours }} Jam</div>
                                <div class="text-sky-300">Plan: {{ point.plan_hours }} Jam</div>
                                <div v-if="point.target_hours > 0" class="text-purple-300">Target: {{ point.target_hours }} Jam</div>
                            </div>

                            <!-- Bars Container -->
                            <div class="w-full flex items-end justify-center gap-1 h-36">
                                <!-- Bar Plan -->
                                <div
                                    class="w-3.5 bg-sky-200 dark:bg-sky-800/60 rounded-t transition-all duration-300 group-hover:bg-sky-300"
                                    :style="{ height: `${Math.max(4, (point.plan_hours / maxTrendValue) * 100)}%` }"
                                    :title="`Plan: ${point.plan_hours} Jam`"
                                ></div>

                                <!-- Bar Realisasi -->
                                <div
                                    class="w-3.5 bg-blue-600 dark:bg-blue-500 rounded-t shadow-sm transition-all duration-300 group-hover:bg-blue-700"
                                    :style="{ height: `${Math.max(4, (point.realisasi_hours / maxTrendValue) * 100)}%` }"
                                    :title="`Realisasi: ${point.realisasi_hours} Jam`"
                                ></div>

                                <!-- Bar Target (Yearly only) -->
                                <div
                                    v-if="periodType === 'year' && point.target_hours > 0"
                                    class="w-3.5 bg-purple-500/80 rounded-t transition-all duration-300"
                                    :style="{ height: `${Math.max(4, (point.target_hours / maxTrendValue) * 100)}%` }"
                                    :title="`Target: ${point.target_hours} Jam`"
                                ></div>
                            </div>

                            <!-- Label X-Axis -->
                            <div class="mt-2 text-[11px] font-bold text-slate-600 dark:text-slate-400 truncate max-w-[60px] text-center">
                                {{ point.label }}
                            </div>
                            <div class="text-[10px] font-semibold text-slate-400 dark:text-slate-500">
                                {{ point.realisasi_hours }}h
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================================= -->
            <!-- 4. FILTER BAR & SEARCH TOOLBAR                                -->
            <!-- ============================================================= -->
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm">
                <div class="flex-1 flex flex-wrap items-center gap-2.5">
                    <!-- Search Input -->
                    <div class="relative flex-1 min-w-[220px]">
                        <svg class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input
                            v-model="searchQuery"
                            @input="onSearchInput"
                            type="text"
                            placeholder="Cari Trainer (Nama, NIK, Email)..."
                            class="w-full pl-9 pr-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:outline-none transition"
                        />
                    </div>

                    <!-- Filter Tipe Trainer -->
                    <select
                        v-model="selectedTrainerType"
                        @change="applyFilters()"
                        class="px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-blue-500 focus:outline-none transition"
                    >
                        <option value="">Semua Tipe Trainer</option>
                        <option value="INTERNAL_SME">Internal SME</option>
                        <option value="FULL_TIME_LND">Full-Time L&D</option>
                        <option value="EXTERNAL_VENDOR">External Vendor</option>
                    </select>

                    <!-- Filter Department -->
                    <select
                        v-model="selectedDepartment"
                        @change="applyFilters()"
                        class="px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-blue-500 focus:outline-none transition"
                    >
                        <option value="">Semua Departemen</option>
                        <option v-for="dept in departments" :key="dept" :value="dept">{{ dept }}</option>
                    </select>
                </div>

                <!-- Tab Tampilan (Summary Table vs Sesi History) -->
                <div class="flex items-center gap-1.5 p-1 bg-slate-100 dark:bg-slate-800 rounded-xl">
                    <button
                        type="button"
                        @click="activeTab = 'summary'"
                        :class="[
                            'px-3 py-1.5 text-xs font-bold rounded-lg transition-all',
                            activeTab === 'summary'
                                ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-sm'
                                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900',
                        ]"
                    >
                        📋 Rekap Seluruh Trainer
                    </button>
                    <button
                        type="button"
                        @click="activeTab = 'history'"
                        :class="[
                            'px-3 py-1.5 text-xs font-bold rounded-lg transition-all',
                            activeTab === 'history'
                                ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-sm'
                                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900',
                        ]"
                    >
                        📑 Riwayat Log Sesi
                    </button>
                </div>
            </div>

            <!-- ============================================================= -->
            <!-- 5. TAB 1: TABEL SUMMARY ALL TRAINERS                          -->
            <!-- ============================================================= -->
            <div v-if="activeTab === 'summary'" class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50/75 dark:bg-slate-800/50 border-b border-slate-200/80 dark:border-slate-800 text-[11px] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                <th class="py-3.5 px-4">Trainer / Instruktur</th>
                                <th class="py-3.5 px-3 text-center">Status Roster</th>
                                <th class="py-3.5 px-3 text-right">Target (Jam)</th>
                                <th class="py-3.5 px-3 text-right">Plan (Jam)</th>
                                <th class="py-3.5 px-3 text-right">Realisasi (Jam)</th>
                                <th class="py-3.5 px-4 text-center">Pencapaian (% Capaian)</th>
                                <th class="py-3.5 px-3 text-center">Program Diampu</th>
                                <th class="py-3.5 px-4 text-right">Aksi &amp; Detail</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 text-xs">
                            <template v-for="trainer in trainers" :key="trainer.id">
                                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors">
                                    <!-- Trainer Info -->
                                    <td class="py-3.5 px-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-600 text-white font-black flex items-center justify-center text-sm shadow-sm flex-shrink-0">
                                                {{ trainer.name.substring(0, 2).toUpperCase() }}
                                            </div>
                                            <div class="min-w-0">
                                                <div class="font-black text-slate-900 dark:text-white truncate flex items-center gap-2">
                                                    <span>{{ trainer.name }}</span>
                                                    <span class="text-[10px] font-semibold text-slate-400 dark:text-slate-500 font-mono">({{ trainer.nik || 'NO-NIK' }})</span>
                                                </div>
                                                <div class="text-[11px] text-slate-500 dark:text-slate-400 truncate flex items-center gap-2 mt-0.5">
                                                    <span>{{ trainer.department }}</span>
                                                    <span>•</span>
                                                    <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                                                        {{ trainer.trainer_type }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Status Roster -->
                                    <td class="py-3.5 px-3 text-center whitespace-nowrap">
                                        <span :class="[
                                            'inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold tracking-wide',
                                            trainer.roster_status === 'on_duty' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 border border-emerald-200/60' :
                                            trainer.roster_status === 'off_roster' ? 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400' :
                                            'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 border border-amber-200/60'
                                        ]">
                                            {{ trainer.roster_label }}
                                        </span>
                                    </td>

                                    <!-- Target Hours -->
                                    <td class="py-3.5 px-3 text-right whitespace-nowrap">
                                        <div v-if="trainer.target_hours > 0" class="font-bold text-slate-800 dark:text-slate-200">
                                            <span>{{ trainer.target_hours }}</span> <span class="text-[10px] text-slate-400">Jam</span>
                                        </div>
                                        <button
                                            v-else
                                            type="button"
                                            @click="openTargetModal(trainer)"
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold text-purple-700 bg-purple-50 hover:bg-purple-100 dark:bg-purple-900/30 dark:text-purple-300 border border-purple-200/60 transition"
                                        >
                                            + Set Target
                                        </button>
                                    </td>

                                    <!-- Plan Hours -->
                                    <td class="py-3.5 px-3 text-right whitespace-nowrap">
                                        <div class="font-bold text-slate-700 dark:text-slate-300">
                                            <span>{{ trainer.plan_hours }}</span> <span class="text-[10px] text-slate-400">Jam</span>
                                        </div>
                                        <div class="text-[10px] text-slate-400">
                                            {{ trainer.planned_sessions_count }} sesi
                                        </div>
                                    </td>

                                    <!-- Realisasi Hours -->
                                    <td class="py-3.5 px-3 text-right whitespace-nowrap">
                                        <div class="font-black text-blue-600 dark:text-blue-400 text-sm">
                                            <span>{{ trainer.realisasi_hours }}</span> <span class="text-[10px] font-bold text-blue-500/80">Jam</span>
                                        </div>
                                        <div class="text-[10px] text-slate-400">
                                            {{ trainer.verified_sessions_count }} sesi sah
                                        </div>
                                    </td>

                                    <!-- Pencapaian Dual Progress -->
                                    <td class="py-3.5 px-4 text-center">
                                        <div class="max-w-[140px] mx-auto space-y-1">
                                            <!-- Realisasi vs Target -->
                                            <div class="flex items-center justify-between text-[10px]">
                                                <span class="text-slate-500">vs Target:</span>
                                                <span v-if="trainer.achievement_vs_target_pct !== null" :class="[
                                                    'font-bold',
                                                    trainer.achievement_vs_target_pct >= 100 ? 'text-emerald-600' :
                                                    trainer.achievement_vs_target_pct >= 70 ? 'text-blue-600' : 'text-amber-600'
                                                ]">
                                                    {{ trainer.achievement_vs_target_pct }}%
                                                </span>
                                                <span v-else class="text-slate-400 italic">-</span>
                                            </div>
                                            <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-1.5 overflow-hidden">
                                                <div
                                                    class="bg-purple-600 h-1.5 rounded-full"
                                                    :style="{ width: `${Math.min(100, trainer.achievement_vs_target_pct || 0)}%` }"
                                                ></div>
                                            </div>

                                            <!-- Realisasi vs Plan -->
                                            <div class="flex items-center justify-between text-[10px] pt-0.5">
                                                <span class="text-slate-500">vs Plan:</span>
                                                <span :class="[
                                                    'font-bold',
                                                    trainer.achievement_vs_plan_pct >= 90 ? 'text-emerald-600' : 'text-slate-700 dark:text-slate-300'
                                                ]">
                                                    {{ trainer.achievement_vs_plan_pct }}%
                                                </span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Program Count & Toggle Accordion -->
                                    <td class="py-3.5 px-3 text-center whitespace-nowrap">
                                        <button
                                            type="button"
                                            @click="toggleExpandTrainer(trainer.id)"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 border border-slate-200 dark:border-slate-700 transition"
                                        >
                                            <span>{{ trainer.programs_count }} Program</span>
                                            <svg
                                                :class="['w-3.5 h-3.5 transition-transform duration-200', expandedTrainerIds.has(trainer.id) ? 'rotate-180' : '']"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </button>
                                    </td>

                                    <!-- Action Buttons -->
                                    <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <button
                                                type="button"
                                                @click="openTargetModal(trainer)"
                                                title="Atur Target Training Hours"
                                                class="p-1.5 rounded-lg text-purple-600 hover:bg-purple-50 dark:hover:bg-purple-900/30 transition"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>

                                            <button
                                                type="button"
                                                @click="openProgramModal(trainer)"
                                                title="Buka Modal Program & Sesi"
                                                class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 transition"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </button>

                                            <Link
                                                :href="'/admin/trainers/' + trainer.id"
                                                title="Lihat Profil Lengkap Trainer 360°"
                                                class="p-1.5 rounded-lg text-slate-500 hover:text-slate-900 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                                </svg>
                                            </Link>
                                        </div>
                                    </td>
                                </tr>

                                <!-- EXPANDABLE ROW: REALISASI & PLAN TRAINING HOURS BERDASARKAN PROGRAM YANG DIA SEBAGAI TRAINERNYA -->
                                <tr v-if="expandedTrainerIds.has(trainer.id)" class="bg-slate-50/80 dark:bg-slate-800/60">
                                    <td colspan="8" class="p-4 sm:p-5 border-y border-blue-100 dark:border-blue-950/60">
                                        <div class="rounded-xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 p-4 shadow-sm">
                                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-3 border-b border-slate-100 dark:border-slate-800 gap-2">
                                                <div>
                                                    <h4 class="text-sm font-black text-slate-900 dark:text-white flex items-center gap-2">
                                                        <span>📚 Realisasi &amp; Plan Training Hours per Program</span>
                                                        <span class="text-xs font-bold text-blue-600 bg-blue-50 dark:bg-blue-900/30 px-2 py-0.5 rounded">
                                                            {{ trainer.name }}
                                                        </span>
                                                    </h4>
                                                    <p class="text-[11px] text-slate-500 mt-0.5">
                                                        Daftar seluruh program pelatihan di mana {{ trainer.name }} bertindak sebagai instruktur beserta rincian jam perencanaannya.
                                                    </p>
                                                </div>

                                                <span class="text-xs font-bold text-slate-500">
                                                    Total: {{ trainer.programs.length }} Program Terdaftar
                                                </span>
                                            </div>

                                            <!-- Program Items List -->
                                            <div v-if="trainer.programs.length === 0" class="py-6 text-center text-xs text-slate-400">
                                                Belum ada program pelatihan yang ditugaskan kepada instruktur ini pada periode terpilih.
                                            </div>

                                            <div v-else class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-3.5">
                                                <div
                                                    v-for="prog in trainer.programs"
                                                    :key="prog.id"
                                                    class="rounded-xl border border-slate-200 dark:border-slate-800 p-3.5 hover:border-blue-300 dark:hover:border-blue-700 transition bg-slate-50/40 dark:bg-slate-800/40"
                                                >
                                                    <div class="flex items-start justify-between gap-2">
                                                        <div>
                                                            <div class="flex items-center gap-2">
                                                                <span class="text-[10px] font-black font-mono px-1.5 py-0.5 bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 rounded">
                                                                    {{ prog.code }}
                                                                </span>
                                                                <span class="text-[11px] font-semibold text-slate-500">
                                                                    {{ prog.training_category }}
                                                                </span>
                                                            </div>
                                                            <div class="font-bold text-xs text-slate-900 dark:text-white mt-1 leading-snug">
                                                                {{ prog.title }}
                                                            </div>
                                                        </div>

                                                        <span :class="[
                                                            'text-[10px] font-black uppercase px-2 py-0.5 rounded-full',
                                                            prog.status === 'active' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-600'
                                                        ]">
                                                            {{ prog.status }}
                                                        </span>
                                                    </div>

                                                    <!-- Metrics Bar Plan vs Realisasi -->
                                                    <div class="mt-3 grid grid-cols-3 gap-2 bg-white dark:bg-slate-900 p-2.5 rounded-lg border border-slate-200/60 dark:border-slate-800/80 text-center">
                                                        <div>
                                                            <div class="text-[10px] text-slate-400 font-bold uppercase">Plan Hours</div>
                                                            <div class="text-xs font-black text-slate-800 dark:text-slate-200">
                                                                {{ prog.plan_hours }} Jam
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <div class="text-[10px] text-slate-400 font-bold uppercase">Realisasi</div>
                                                            <div class="text-xs font-black text-blue-600 dark:text-blue-400">
                                                                {{ prog.realisasi_hours }} Jam
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <div class="text-[10px] text-slate-400 font-bold uppercase">Capaian</div>
                                                            <div :class="[
                                                                'text-xs font-black',
                                                                prog.achievement_pct >= 100 ? 'text-emerald-600' : 'text-slate-700 dark:text-slate-300'
                                                            ]">
                                                                {{ prog.achievement_pct }}%
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Sessions mini timeline -->
                                                    <div class="mt-2.5 flex items-center justify-between text-[11px] text-slate-500">
                                                        <span>Sesi: {{ prog.verified_sessions_period }} / {{ prog.total_sessions_period || prog.total_sessions_all }} Terlaksana</span>
                                                        <span>{{ prog.participants_count }} Peserta Hadir</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>

                            <tr v-if="trainers.length === 0">
                                <td colspan="8" class="py-12 text-center text-slate-400 text-xs">
                                    Tidak ada data trainer yang sesuai dengan kriteria pencarian atau filter yang dipilih.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ============================================================= -->
            <!-- 6. TAB 2: TABEL RIWAYAT LOG SESI PENGAJARAN TERAKHIR           -->
            <!-- ============================================================= -->
            <div v-if="activeTab === 'history'" class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm overflow-hidden">
                <div class="p-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                    <div>
                        <h4 class="text-sm font-black text-slate-900 dark:text-white">
                            📑 Riwayat Sesi Pengajaran Terverifikasi
                        </h4>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Log historis seluruh sesi mengajar instruktur beserta verifikasi presensi peserta pada rentang {{ summary.period_label }}.
                        </p>
                    </div>
                    <span class="text-xs font-bold text-slate-500">
                        {{ recentSessionLogs.length }} Sesi Tercatat
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50/75 dark:bg-slate-800/50 border-b border-slate-200/80 dark:border-slate-800 text-[11px] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                <th class="py-3 px-4">Tanggal &amp; Waktu</th>
                                <th class="py-3 px-3">Instruktur / Trainer</th>
                                <th class="py-3 px-3">Program / Kursus</th>
                                <th class="py-3 px-3">Judul Sesi</th>
                                <th class="py-3 px-3 text-center">Durasi</th>
                                <th class="py-3 px-3 text-center">Kehadiran</th>
                                <th class="py-3 px-4 text-center">Status Sah</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                            <tr v-for="log in recentSessionLogs" :key="log.id" class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <div class="font-bold text-slate-900 dark:text-white">{{ log.session_date }}</div>
                                    <div class="text-[10px] text-slate-400">{{ log.time_range }}</div>
                                </td>
                                <td class="py-3 px-3">
                                    <div class="font-bold text-slate-900 dark:text-white">{{ log.trainer_name }}</div>
                                    <div class="text-[10px] text-slate-400">{{ log.trainer_nik }}</div>
                                </td>
                                <td class="py-3 px-3">
                                    <span class="text-[10px] font-mono font-bold text-blue-600 dark:text-blue-400">{{ log.course_code }}</span>
                                    <div class="font-medium text-slate-700 dark:text-slate-300 truncate max-w-[200px]">{{ log.course_title }}</div>
                                </td>
                                <td class="py-3 px-3 text-slate-800 dark:text-slate-200 font-medium">
                                    {{ log.title }}
                                </td>
                                <td class="py-3 px-3 text-center whitespace-nowrap font-black text-blue-600 dark:text-blue-400">
                                    {{ log.duration_hours }} Jam
                                </td>
                                <td class="py-3 px-3 text-center whitespace-nowrap font-bold text-slate-700 dark:text-slate-300">
                                    {{ log.present_count }} / {{ log.quota }} Hadir
                                </td>
                                <td class="py-3 px-4 text-center whitespace-nowrap">
                                    <span :class="[
                                        'inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black',
                                        log.is_verified ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300 border border-emerald-200' : 'bg-slate-100 text-slate-500'
                                    ]">
                                        {{ log.is_verified ? '✓ Terverifikasi QR' : 'Belum Ada Kehadiran' }}
                                    </span>
                                </td>
                            </tr>

                            <tr v-if="recentSessionLogs.length === 0">
                                <td colspan="7" class="py-10 text-center text-slate-400 text-xs">
                                    Belum ada sesi pelatihan yang terlaksana pada periode terpilih.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ============================================================= -->
            <!-- 7. MODAL: ATUR / TAMBAH TARGET TRAINING HOURS (ADMIN)          -->
            <!-- ============================================================= -->
            <div
                v-if="showTargetModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
                @click.self="closeTargetModal"
            >
                <div class="relative w-full max-w-lg bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 p-6 overflow-hidden">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
                        <div>
                            <h3 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                                <span>🎯 Atur Target Training Hours</span>
                            </h3>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Tetapkan target kuota jam mengajar untuk instruktur terpilih.
                            </p>
                        </div>
                        <button
                            type="button"
                            @click="closeTargetModal"
                            class="p-1.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form @submit.prevent="submitTarget" class="mt-4 space-y-4">
                        <!-- Pilih Trainer -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                Trainer / Instruktur <span class="text-red-500">*</span>
                            </label>
                            <select
                                v-model="targetForm.trainer_id"
                                required
                                class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:outline-none"
                            >
                                <option value="" disabled>Pilih Trainer</option>
                                <option v-for="t in trainers" :key="t.id" :value="t.id">
                                    {{ t.name }} ({{ t.department }} - {{ t.trainer_type }})
                                </option>
                            </select>
                            <div v-if="targetForm.errors.trainer_id" class="text-red-500 text-[11px] mt-1 font-medium">
                                {{ targetForm.errors.trainer_id }}
                            </div>
                        </div>

                        <!-- Periode & Target Hours -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                    Tipe Periode <span class="text-red-500">*</span>
                                </label>
                                <select
                                    v-model="targetForm.period"
                                    required
                                    class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                >
                                    <option value="weekly">Mingguan (Weekly)</option>
                                    <option value="monthly">Bulanan (Monthly)</option>
                                    <option value="yearly">Tahunan (Yearly)</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                    Target Jam Mengajar <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input
                                        v-model="targetForm.target_teaching_hours"
                                        type="number"
                                        step="0.5"
                                        min="0.5"
                                        max="500"
                                        required
                                        class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                    />
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 font-bold">Jam</span>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Presets -->
                        <div class="flex items-center gap-1.5 pt-1">
                            <span class="text-[11px] text-slate-400 font-semibold">Preset Jam:</span>
                            <button
                                v-for="preset in [10, 20, 30, 40, 60, 80]"
                                :key="preset"
                                type="button"
                                @click="setPresetHours(preset)"
                                :class="[
                                    'px-2 py-0.5 rounded text-[10px] font-bold border transition',
                                    targetForm.target_teaching_hours == preset
                                        ? 'bg-blue-600 text-white border-blue-600'
                                        : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:bg-slate-200'
                                ]"
                            >
                                {{ preset }}h
                            </button>
                        </div>

                        <!-- Tanggal Berlaku (Start Date & Due Date) -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                    Mulai Tanggal
                                </label>
                                <input
                                    v-model="targetForm.start_date"
                                    type="date"
                                    class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                />
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                    Hingga Tanggal
                                </label>
                                <input
                                    v-model="targetForm.due_date"
                                    type="date"
                                    class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                />
                            </div>
                        </div>

                        <!-- Judul / Keterangan -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                Judul Target / Penugasan
                            </label>
                            <input
                                v-model="targetForm.title"
                                type="text"
                                placeholder="Contoh: Target Jam Mengajar Q4 2026"
                                class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:outline-none"
                            />
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100 dark:border-slate-800">
                            <button
                                type="button"
                                @click="closeTargetModal"
                                class="px-4 py-2 text-xs font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                :disabled="targetForm.processing"
                                class="px-5 py-2 text-xs font-black text-white bg-blue-600 hover:bg-blue-700 rounded-xl shadow-md shadow-blue-500/20 disabled:opacity-50 transition"
                            >
                                {{ targetForm.processing ? 'Menyimpan...' : 'Simpan Target' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ============================================================= -->
            <!-- 8. MODAL: RINCIAN PROGRAM & REALISASI VS PLAN TRAINING HOURS  -->
            <!-- ============================================================= -->
            <div
                v-if="showProgramModal && selectedTrainerForModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
                @click.self="closeProgramModal"
            >
                <div class="relative w-full max-w-3xl bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 p-6 max-h-[85vh] flex flex-col overflow-hidden">
                    <!-- Header -->
                    <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800 flex-shrink-0">
                        <div>
                            <h3 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                                <span>📚 Portfolio Program &amp; Jam Mengajar</span>
                                <span class="text-xs font-bold text-blue-600 bg-blue-50 dark:bg-blue-900/30 px-2 py-0.5 rounded">
                                    {{ selectedTrainerForModal?.name || 'Trainer' }}
                                </span>
                            </h3>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Realisasi vs Plan Training Hours berdasarkan program yang diampu pada {{ summary.period_label }}.
                            </p>
                        </div>
                        <button
                            type="button"
                            @click="closeProgramModal"
                            class="p-1.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Body (Scrollable) -->
                    <div class="flex-1 overflow-y-auto py-4 space-y-4">
                        <div v-if="!selectedTrainerForModal?.programs || selectedTrainerForModal.programs.length === 0" class="py-12 text-center text-xs text-slate-400">
                            Belum ada program pelatihan yang ditugaskan kepada instruktur ini.
                        </div>

                        <div
                            v-for="prog in (selectedTrainerForModal?.programs || [])"
                            :key="prog.id"
                            class="rounded-xl border border-slate-200 dark:border-slate-800 p-4 bg-slate-50/50 dark:bg-slate-800/40"
                        >
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-[10px] font-black font-mono px-2 py-0.5 bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300 rounded">
                                            {{ prog.code }}
                                        </span>
                                        <span class="text-xs font-semibold text-slate-500">
                                            {{ prog.training_category }}
                                        </span>
                                    </div>
                                    <h5 class="text-sm font-black text-slate-900 dark:text-white mt-1">
                                        {{ prog.title }}
                                    </h5>
                                </div>

                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-black text-blue-600 bg-blue-50 dark:bg-blue-900/30 px-2.5 py-1 rounded-lg">
                                        Capaian: {{ prog.achievement_pct }}%
                                    </span>
                                </div>
                            </div>

                            <!-- Dual Hours Progress -->
                            <div class="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-2.5 bg-white dark:bg-slate-900 p-3 rounded-xl border border-slate-200/80 dark:border-slate-800 text-center text-xs">
                                <div>
                                    <div class="text-[10px] text-slate-400 font-bold uppercase">Plan Hours</div>
                                    <div class="text-sm font-black text-slate-800 dark:text-slate-200 mt-0.5">
                                        {{ prog.plan_hours }} Jam
                                    </div>
                                </div>
                                <div>
                                    <div class="text-[10px] text-slate-400 font-bold uppercase">Realisasi Hours</div>
                                    <div class="text-sm font-black text-blue-600 dark:text-blue-400 mt-0.5">
                                        {{ prog.realisasi_hours }} Jam
                                    </div>
                                </div>
                                <div>
                                    <div class="text-[10px] text-slate-400 font-bold uppercase">Sesi Selesai</div>
                                    <div class="text-sm font-black text-slate-800 dark:text-slate-200 mt-0.5">
                                        {{ prog.verified_sessions_period }} / {{ prog.total_sessions_period || prog.total_sessions_all }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-[10px] text-slate-400 font-bold uppercase">Peserta Hadir</div>
                                    <div class="text-sm font-black text-slate-800 dark:text-slate-200 mt-0.5">
                                        {{ prog.participants_count }} Peserta
                                    </div>
                                </div>
                            </div>

                            <!-- List of Sessions under Program -->
                            <div v-if="prog.sessions_list && prog.sessions_list.length > 0" class="mt-3">
                                <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">
                                    Rincian Sesi Pengajaran:
                                </div>
                                <div class="divide-y divide-slate-200/60 dark:divide-slate-800 rounded-lg border border-slate-200/60 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden text-[11px]">
                                    <div
                                        v-for="sess in prog.sessions_list"
                                        :key="sess.id"
                                        class="p-2.5 flex items-center justify-between hover:bg-slate-50 dark:hover:bg-slate-800/50 transition"
                                    >
                                        <div>
                                            <div class="font-bold text-slate-800 dark:text-slate-200">{{ sess.title }}</div>
                                            <div class="text-[10px] text-slate-400">{{ sess.session_date }} • {{ sess.time_range }} • {{ sess.location }}</div>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <span class="font-black text-blue-600">{{ sess.duration_hours }} Jam</span>
                                            <span :class="[
                                                'px-2 py-0.5 rounded text-[10px] font-bold',
                                                sess.is_verified ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'
                                            ]">
                                                {{ sess.is_verified ? `${sess.present_count} Hadir` : 'Belum Hadir' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex justify-end flex-shrink-0">
                        <button
                            type="button"
                            @click="closeProgramModal"
                            class="px-4 py-2 text-xs font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition"
                        >
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
