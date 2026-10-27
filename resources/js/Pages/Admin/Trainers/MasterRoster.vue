<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    month: { type: String, required: true },
    weeks: { type: Array, default: () => [] },
    calendarDates: { type: Array, default: () => [] },
    filters: {
        type: Object,
        default: () => ({ department: '', trainer_type: '', roster_status: '', activity_type: '' }),
    },
    departments: { type: Array, default: () => [] },
    trainers: { type: Array, default: () => [] },
    trainerRows: { type: Array, default: () => [] },
    courses: { type: Array, default: () => [] },
});

const monthValue = ref(props.month);
const department = ref(props.filters.department || '');
const trainerType = ref(props.filters.trainer_type || '');
const rosterStatus = ref(props.filters.roster_status || '');
const activityType = ref(props.filters.activity_type || '');
const activeView = ref('calendar');

const monthLabel = computed(() => {
    const [year, month] = props.month.split('-').map(Number);
    return new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric' }).format(new Date(year, month - 1, 1));
});

const activityLabels = {
    TRAINING_CLASS: 'Mengajar Kelas',
    FIELD_OBSERVATION: 'Observasi Lapangan',
    LND_ADMIN: 'Tugas LnD',
    STANDBY: 'Standby',
    OFF: 'Off / Cuti',
};

const activityClasses = {
    TRAINING_CLASS: 'bg-blue-50/90 text-blue-900 border-blue-200/90 ring-1 ring-blue-500/15',
    FIELD_OBSERVATION: 'bg-purple-50/90 text-purple-900 border-purple-200/90 ring-1 ring-purple-500/15',
    LND_ADMIN: 'bg-amber-50/90 text-amber-900 border-amber-200/90 ring-1 ring-amber-500/15',
    STANDBY: 'bg-cyan-50/90 text-cyan-900 border-cyan-200/90 ring-1 ring-cyan-500/15',
    OFF: 'bg-slate-100 text-slate-700 border-slate-200/80 ring-1 ring-slate-400/15',
};

const statusLabel = (status) => ({
    on_duty: 'On-Duty',
    off_roster: 'Off Roster',
    annual_leave: 'Cuti Tahunan',
    sick: 'Sakit (SKD)',
    site_visit: 'Site Visit',
}[status] || (status ? status.toUpperCase() : 'ON-DUTY'));

const applyFilters = () => {
    router.get('/admin/trainers/master-roster', {
        month: monthValue.value,
        department: department.value || undefined,
        trainer_type: trainerType.value || undefined,
        roster_status: rosterStatus.value || undefined,
        activity_type: activityType.value || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
};

const changeMonth = (offset) => {
    const [year, month] = monthValue.value.split('-').map(Number);
    const next = new Date(year, month - 1 + offset, 1);
    monthValue.value = `${next.getFullYear()}-${String(next.getMonth() + 1).padStart(2, '0')}`;
    applyFilters();
};

const rosterCount = computed(() => props.weeks.flat().reduce((count, day) => count + (day?.items?.length || 0), 0));

// --- MODAL: SET ROSTER SINGLE DATE ---
const showSingleRosterModal = ref(false);
const singleRosterForm = useForm({
    trainer_id: '',
    roster_date: '',
    status: 'on_duty',
    activity_type: 'TRAINING_CLASS',
    course_id: '',
    location: '',
    shift: 'day',
    notes: '',
});

const openSingleRosterModal = (trainerId = '', date = '', item = null) => {
    singleRosterForm.reset();
    singleRosterForm.clearErrors();
    singleRosterForm.trainer_id = trainerId || (props.trainers[0]?.id || '');
    singleRosterForm.roster_date = date || new Date().toISOString().substring(0, 10);
    singleRosterForm.status = item?.status || 'on_duty';
    singleRosterForm.activity_type = item?.activity_type || 'TRAINING_CLASS';
    singleRosterForm.course_id = item?.course?.id || '';
    singleRosterForm.location = item?.location || '';
    singleRosterForm.shift = item?.shift || 'day';
    singleRosterForm.notes = item?.notes || '';
    showSingleRosterModal.value = true;
};

const onSingleStatusChange = () => {
    if (['off_roster', 'annual_leave', 'sick'].includes(singleRosterForm.status)) {
        singleRosterForm.activity_type = 'OFF';
        singleRosterForm.course_id = '';
    } else if (singleRosterForm.activity_type === 'OFF') {
        singleRosterForm.activity_type = 'TRAINING_CLASS';
    }
};

const onSingleActivityChange = () => {
    if (singleRosterForm.activity_type === 'OFF') {
        singleRosterForm.status = 'off_roster';
        singleRosterForm.course_id = '';
    } else if (['off_roster', 'annual_leave', 'sick'].includes(singleRosterForm.status)) {
        singleRosterForm.status = 'on_duty';
    }
    if (singleRosterForm.activity_type !== 'TRAINING_CLASS') {
        singleRosterForm.course_id = '';
    }
};

const submitSingleRoster = () => {
    if (!singleRosterForm.trainer_id) return;
    singleRosterForm.post(`/admin/trainers/${singleRosterForm.trainer_id}/roster/set`, {
        preserveScroll: true,
        onSuccess: () => {
            showSingleRosterModal.value = false;
        },
    });
};

// --- MODAL: BULK SET ROSTER ---
const showBulkRosterModal = ref(false);
const bulkRosterForm = useForm({
    trainer_id: '',
    start_date: '',
    end_date: '',
    status: 'on_duty',
    activity_type: 'TRAINING_CLASS',
    course_id: '',
    location: '',
    shift: 'day',
    notes: '',
});

const openBulkRosterModal = (trainerId = '', defaultDate = '') => {
    bulkRosterForm.reset();
    bulkRosterForm.clearErrors();
    bulkRosterForm.trainer_id = trainerId || (props.trainers[0]?.id || '');
    bulkRosterForm.start_date = defaultDate || new Date().toISOString().substring(0, 10);
    bulkRosterForm.end_date = defaultDate || new Date().toISOString().substring(0, 10);
    bulkRosterForm.status = 'on_duty';
    bulkRosterForm.activity_type = 'TRAINING_CLASS';
    bulkRosterForm.course_id = '';
    bulkRosterForm.location = '';
    bulkRosterForm.shift = 'day';
    bulkRosterForm.notes = '';
    showBulkRosterModal.value = true;
};

const onBulkStatusChange = () => {
    if (['off_roster', 'annual_leave', 'sick'].includes(bulkRosterForm.status)) {
        bulkRosterForm.activity_type = 'OFF';
        bulkRosterForm.course_id = '';
    } else if (bulkRosterForm.activity_type === 'OFF') {
        bulkRosterForm.activity_type = 'TRAINING_CLASS';
    }
};

const onBulkActivityChange = () => {
    if (bulkRosterForm.activity_type === 'OFF') {
        bulkRosterForm.status = 'off_roster';
        bulkRosterForm.course_id = '';
    } else if (['off_roster', 'annual_leave', 'sick'].includes(bulkRosterForm.status)) {
        bulkRosterForm.status = 'on_duty';
    }
    if (bulkRosterForm.activity_type !== 'TRAINING_CLASS') {
        bulkRosterForm.course_id = '';
    }
};

const submitBulkRoster = () => {
    if (!bulkRosterForm.trainer_id) return;
    bulkRosterForm.post(`/admin/trainers/${bulkRosterForm.trainer_id}/roster/bulk`, {
        preserveScroll: true,
        onSuccess: () => {
            showBulkRosterModal.value = false;
        },
    });
};
</script>

<template>
    <Head title="Master Kalender Roster Seluruh Trainer" />

    <AdminLayout
        title="Master Kalender Roster &amp; Aktivitas"
        subtitle="Pantau penugasan harian, kegiatan lapangan, kelas pelatihan, dan ketersediaan seluruh instruktur LnD."
    >
        <main class="space-y-5">
            <!-- ============================================================= -->
            <!-- 1. FILTER & ACTION CONTROL DECK (DOUBLE-BEZEL)                -->
            <!-- ============================================================= -->
            <section class="p-1 rounded-3xl bg-slate-100/80 ring-1 ring-slate-900/5 shadow-sm">
                <div class="bg-white rounded-[calc(1.5rem-1px)] p-4 sm:p-5 shadow-sm">
                    <div class="flex flex-col xl:flex-row xl:items-end justify-between gap-4">
                        <!-- Filters -->
                        <div class="grid flex-1 grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            <div>
                                <label class="mb-1.5 block text-xs font-bold text-slate-700">Departemen</label>
                                <select
                                    v-model="department"
                                    @change="applyFilters"
                                    class="w-full text-xs font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-700"
                                >
                                    <option value="">Semua Departemen</option>
                                    <option v-for="item in departments" :key="item" :value="item">{{ item }}</option>
                                </select>
                            </div>

                            <div>
                                <label class="mb-1.5 block text-xs font-bold text-slate-700">Tipe Trainer</label>
                                <select
                                    v-model="trainerType"
                                    @change="applyFilters"
                                    class="w-full text-xs font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-700"
                                >
                                    <option value="">Semua Tipe Trainer</option>
                                    <option value="INTERNAL_SME">Internal SME</option>
                                    <option value="FULL_TIME_LND">Full-Time LnD</option>
                                    <option value="EXTERNAL_VENDOR">External Vendor</option>
                                </select>
                            </div>

                            <div>
                                <label class="mb-1.5 block text-xs font-bold text-slate-700">Status Roster</label>
                                <select
                                    v-model="rosterStatus"
                                    @change="applyFilters"
                                    class="w-full text-xs font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-700"
                                >
                                    <option value="">Semua Status Kehadiran</option>
                                    <option value="on_duty">On-Duty (Masuk Kerja)</option>
                                    <option value="off_roster">Off Roster (Libur)</option>
                                    <option value="annual_leave">Cuti Tahunan</option>
                                    <option value="sick">Sakit (SKD Dokter)</option>
                                    <option value="site_visit">Site Visit (Dinas Luar)</option>
                                </select>
                            </div>

                            <div>
                                <label class="mb-1.5 block text-xs font-bold text-slate-700">Tipe Aktivitas</label>
                                <select
                                    v-model="activityType"
                                    @change="applyFilters"
                                    class="w-full text-xs font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-700"
                                >
                                    <option value="">Semua Aktivitas</option>
                                    <option value="TRAINING_CLASS">Mengajar Kelas</option>
                                    <option value="FIELD_OBSERVATION">Observasi Lapangan</option>
                                    <option value="LND_ADMIN">Tugas LnD</option>
                                    <option value="STANDBY">Standby</option>
                                    <option value="OFF">Off / Cuti</option>
                                </select>
                            </div>
                        </div>

                        <!-- Action Island Controls -->
                        <div class="flex flex-wrap items-center gap-2">
                            <!-- Atur Roster Single Button -->
                            <button
                                type="button"
                                @click="openSingleRosterModal()"
                                class="group inline-flex min-h-10 items-center gap-2 rounded-xl bg-gradient-to-r from-blue-700 to-blue-600 px-4 text-xs font-bold text-white shadow-sm shadow-blue-500/25 hover:from-blue-800 hover:to-blue-700 active:scale-[0.98] transition-all duration-200 ring-1 ring-white/20"
                            >
                                <div class="w-5 h-5 rounded-full bg-white/20 flex items-center justify-center group-hover:rotate-90 transition-transform">
                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                                    </svg>
                                </div>
                                <span>Atur Roster</span>
                            </button>

                            <!-- Bulk Set Button -->
                            <button
                                type="button"
                                @click="openBulkRosterModal()"
                                class="inline-flex min-h-10 items-center gap-1.5 rounded-xl bg-slate-900 px-3.5 text-xs font-bold text-white shadow-sm hover:bg-slate-800 active:scale-[0.98] transition-all duration-200"
                            >
                                <span>⚡ Bulk Set Roster</span>
                            </button>

                            <!-- Back to Trainers -->
                            <Link
                                href="/admin/trainers"
                                class="inline-flex min-h-10 items-center rounded-xl border border-slate-200 bg-slate-50/70 hover:bg-white px-3 text-xs font-bold text-slate-700 active:scale-[0.98] transition-all duration-200 shadow-sm"
                            >
                                &larr; Daftar Trainer
                            </Link>

                            <!-- Month Navigator Capsule -->
                            <div class="inline-flex items-center rounded-xl border border-slate-200 bg-slate-50/70 p-1 shadow-sm">
                                <button
                                    type="button"
                                    class="min-h-8 rounded-lg px-2.5 text-xs font-bold text-slate-600 hover:text-slate-900 hover:bg-white active:scale-[0.95] transition-all"
                                    @click="changeMonth(-1)"
                                    aria-label="Bulan sebelumnya"
                                >
                                    &lsaquo; Prev
                                </button>
                                <input
                                    v-model="monthValue"
                                    type="month"
                                    class="min-h-8 border-0 bg-transparent text-xs font-mono font-bold text-slate-900 focus:ring-0 px-2 cursor-pointer"
                                    aria-label="Pilih bulan"
                                    @change="applyFilters"
                                />
                                <button
                                    type="button"
                                    class="min-h-8 rounded-lg px-2.5 text-xs font-bold text-slate-600 hover:text-slate-900 hover:bg-white active:scale-[0.95] transition-all"
                                    @click="changeMonth(1)"
                                    aria-label="Bulan berikutnya"
                                >
                                    Next &rsaquo;
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <div class="flex flex-wrap gap-2" role="group" aria-label="Mode tampilan kalender roster">
                <button
                    type="button"
                    @click="activeView = 'calendar'"
                    :aria-pressed="activeView === 'calendar'"
                    :class="activeView === 'calendar' ? 'bg-slate-900 text-white' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50'"
                    class="min-h-10 rounded-lg px-4 text-xs font-bold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2"
                >
                    Kalender Bulanan
                </button>
                <button
                    type="button"
                    @click="activeView = 'matrix'"
                    :aria-pressed="activeView === 'matrix'"
                    :class="activeView === 'matrix' ? 'bg-slate-900 text-white' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50'"
                    class="min-h-10 rounded-lg px-4 text-xs font-bold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2"
                >
                    Matriks Seluruh Trainer
                </button>
            </div>

            <!-- ============================================================= -->
            <!-- 2. CALENDAR BOARD (DOUBLE-BEZEL WITH INTERACTIVE CELLS)       -->
            <!-- ============================================================= -->
            <section v-if="activeView === 'calendar'" class="p-1 rounded-3xl bg-slate-100/80 ring-1 ring-slate-900/5 shadow-sm overflow-hidden">
                <div class="bg-white rounded-[calc(1.5rem-1px)] overflow-hidden shadow-sm">
                    <!-- Calendar Header Bar with Legend -->
                    <header class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="flex items-center gap-2.5">
                                <h2 class="text-base font-black text-slate-900 tracking-tight">{{ monthLabel }}</h2>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-blue-50 text-blue-700 border border-blue-200 font-mono">
                                    {{ monthValue }}
                                </span>
                            </div>
                            <p class="text-[11px] text-slate-500 mt-0.5">
                                Terdata {{ rosterCount }} penugasan aktivitas dan sesi pelatihan terdaftar di kalender.
                            </p>
                        </div>

                        <!-- Legend Pills -->
                        <div class="flex flex-wrap items-center gap-1.5 text-[10px] font-bold">
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-blue-200 bg-blue-50/80 px-2.5 py-0.5 text-blue-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-blue-600"></span> Mengajar
                            </span>
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-purple-200 bg-purple-50/80 px-2.5 py-0.5 text-purple-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-purple-600"></span> Observasi
                            </span>
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50/80 px-2.5 py-0.5 text-amber-800">
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-600"></span> Tugas LnD
                            </span>
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-cyan-200 bg-cyan-50/80 px-2.5 py-0.5 text-cyan-800">
                                <span class="h-1.5 w-1.5 rounded-full bg-cyan-600"></span> Standby
                            </span>
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-100 px-2.5 py-0.5 text-slate-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span> Off / Cuti
                            </span>
                        </div>
                    </header>

                    <!-- Calendar Grid Structure -->
                    <div class="overflow-x-auto">
                        <div class="min-w-[1020px]">
                            <!-- Weekdays Strip -->
                            <div class="grid grid-cols-7 border-b border-slate-200/80 bg-slate-50/80">
                                <div
                                    v-for="(weekday, idx) in ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu']"
                                    :key="weekday"
                                    :class="[
                                        'px-3 py-3 text-center text-[11px] font-black uppercase tracking-wider',
                                        idx === 0 || idx === 6 ? 'text-rose-600 bg-rose-50/30' : 'text-slate-600'
                                    ]"
                                >
                                    {{ weekday }}
                                </div>
                            </div>

                            <!-- Weeks Grid -->
                            <div
                                v-for="(week, weekIndex) in weeks"
                                :key="weekIndex"
                                class="grid grid-cols-7 border-b border-slate-200/60 last:border-b-0 divide-x divide-slate-100"
                            >
                                <div
                                    v-for="(day, dayIndex) in week"
                                    :key="day?.date || `empty-${weekIndex}-${dayIndex}`"
                                    class="min-h-44 p-2 transition-colors relative group"
                                    :class="[
                                        day
                                            ? (day.is_today
                                                ? 'bg-blue-50/40 ring-1 ring-inset ring-blue-500/30'
                                                : (dayIndex === 0 || dayIndex === 6 ? 'bg-slate-50/40 hover:bg-slate-50/80' : 'bg-white hover:bg-slate-50/70'))
                                            : 'bg-slate-50/20'
                                    ]"
                                >
                                    <template v-if="day">
                                        <!-- Cell Top Row: Day Number + Quick Add -->
                                        <div class="mb-2 flex items-center justify-between">
                                            <div class="flex items-center gap-1.5">
                                                <span
                                                    class="flex h-6 w-6 items-center justify-center rounded-lg text-xs font-black transition-transform group-hover:scale-105"
                                                    :class="day.is_today ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-800 bg-slate-100/80'"
                                                >
                                                    {{ day.day_number }}
                                                </span>
                                                <span v-if="day.is_today" class="text-[9px] font-black uppercase text-blue-600 tracking-wider">
                                                    Hari ini
                                                </span>
                                            </div>

                                            <button
                                                type="button"
                                                @click="openSingleRosterModal('', day.date)"
                                                class="opacity-0 group-hover:opacity-100 rounded-md p-1 text-slate-400 hover:bg-blue-50 hover:text-blue-600 transition-all"
                                                title="Atur Roster pada tanggal ini"
                                            >
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                                                </svg>
                                            </button>
                                        </div>

                                        <!-- Day Activity Items List -->
                                        <div v-if="day.items.length" class="space-y-1.5">
                                            <article
                                                v-for="item in day.items"
                                                :key="item.trainer_id"
                                                @click="openSingleRosterModal(item.trainer_id, day.date, item)"
                                                class="cursor-pointer rounded-xl border p-2 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 select-none"
                                                :class="item.has_conflict
                                                    ? 'border-rose-400 bg-rose-50/95 text-rose-950 ring-2 ring-rose-500/40 animate-pulse'
                                                    : (activityClasses[item.activity_type] || activityClasses.OFF)"
                                            >
                                                <!-- Trainer Info Header -->
                                                <div class="flex items-center justify-between gap-1">
                                                    <span class="min-w-0 truncate text-[11px] font-extrabold leading-tight">
                                                        {{ item.trainer_name }}
                                                    </span>
                                                    <span class="shrink-0 rounded-md bg-white/90 px-1.5 py-0.2 text-[8px] font-black uppercase tracking-wider shadow-sm border border-black/5">
                                                        {{ statusLabel(item.status) }}
                                                    </span>
                                                </div>

                                                <!-- Activity Content -->
                                                <div class="mt-1 text-[10px] leading-tight font-medium">
                                                    <div v-if="item.has_conflict" class="font-black text-rose-700 flex items-center gap-1">
                                                        <span>⚠️</span> Bentrok Sesi Mengajar!
                                                    </div>

                                                    <div v-if="item.activity_type === 'TRAINING_CLASS'" class="truncate">
                                                        <span class="font-bold">Mengajar:</span> {{ item.course ? `${item.course.code} - ${item.course.title}` : (item.sessions?.[0]?.title || 'Kelas Terjadwal') }}
                                                    </div>
                                                    <div v-else-if="item.activity_type === 'FIELD_OBSERVATION'" class="truncate">
                                                        <span class="font-bold">Observasi:</span> {{ item.location || 'Pit Tambang' }}
                                                    </div>
                                                    <div v-else-if="item.activity_type === 'LND_ADMIN'" class="truncate">
                                                        <span class="font-bold">LnD:</span> {{ item.notes || 'Tugas Administrasi' }}
                                                    </div>
                                                    <div v-else-if="item.activity_type === 'STANDBY'" class="truncate">
                                                        <span class="font-bold">Standby:</span> {{ item.location || 'Kantor LnD' }}
                                                    </div>
                                                    <div v-else class="text-slate-500 italic text-[9px]">
                                                        {{ statusLabel(item.status) }}
                                                    </div>
                                                </div>

                                                <div v-if="item.location && item.activity_type === 'TRAINING_CLASS'" class="mt-0.5 truncate text-[9px] opacity-80 flex items-center gap-0.5">
                                                    <span>📍</span> {{ item.location }}
                                                </div>
                                            </article>
                                        </div>

                                        <div v-else class="mt-8 text-center text-[10px] text-slate-300 select-none">
                                            -
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section v-else class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-4 py-3">
                    <h2 class="text-sm font-bold text-slate-900">Matriks Roster — {{ monthLabel }}</h2>
                    <p class="mt-1 text-xs text-slate-600">
                        Status harian seluruh trainer pada filter terpilih. Pilih sel untuk mengatur atau memperbarui aktivitas.
                    </p>
                </div>
                <div class="overflow-auto">
                    <table class="w-full min-w-max border-collapse text-left text-xs">
                        <thead class="sticky top-0 z-10 bg-slate-50">
                            <tr>
                                <th class="sticky left-0 z-20 min-w-52 border-b border-r border-slate-200 bg-slate-50 px-3 py-2 text-left font-bold text-slate-700">
                                    Trainer
                                </th>
                                <th
                                    v-for="date in calendarDates"
                                    :key="date.date"
                                    :class="date.is_today ? 'bg-blue-50 text-blue-800' : 'text-slate-600'"
                                    class="min-w-20 border-b border-r border-slate-200 px-2 py-2 text-center font-bold"
                                >
                                    <span class="block">{{ date.day_number }}</span>
                                    <span class="text-[10px] font-medium">{{ new Date(`${date.date}T00:00:00`).toLocaleDateString('id-ID', { weekday: 'short' }) }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="!trainerRows.length">
                                <td :colspan="calendarDates.length + 1" class="px-4 py-8 text-center text-slate-500">
                                    Tidak ada trainer untuk filter ini.
                                </td>
                            </tr>
                            <tr v-for="trainer in trainerRows" :key="trainer.id" class="hover:bg-slate-50/60">
                                <th class="sticky left-0 z-[1] border-r border-slate-200 bg-white px-3 py-2 text-left">
                                    <span class="block font-bold text-slate-900">{{ trainer.name }}</span>
                                    <span class="block text-[10px] font-medium text-slate-500">{{ trainer.department || 'Tanpa departemen' }}</span>
                                </th>
                                <td v-for="cell in trainer.cells" :key="cell.date" class="border-r border-slate-100 p-1">
                                    <button
                                        type="button"
                                        @click="openSingleRosterModal(trainer.id, cell.date, cell.item)"
                                        :aria-label="`${trainer.name}, ${cell.date}: ${cell.item ? activityLabels[cell.item.activity_type] : 'belum diatur'}. Atur roster`"
                                        :title="cell.item ? `${activityLabels[cell.item.activity_type]} · ${statusLabel(cell.item.status)}${cell.item.location ? ` · ${cell.item.location}` : ''}` : 'Roster belum diatur'"
                                        :class="cell.item
                                            ? (cell.item.has_conflict ? 'border-rose-300 bg-rose-50 text-rose-800' : activityClasses[cell.item.activity_type])
                                            : 'border-dashed border-slate-200 bg-white text-slate-400 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700'"
                                        class="flex min-h-11 w-full flex-col items-center justify-center rounded-md border px-1 text-center focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-1"
                                    >
                                        <span class="max-w-full truncate text-[10px] font-bold">
                                            {{ cell.item ? activityLabels[cell.item.activity_type] : 'Belum diatur' }}
                                        </span>
                                        <span v-if="cell.item" class="max-w-full truncate text-[9px]">
                                            {{ statusLabel(cell.item.status) }}
                                        </span>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>

        <!-- ============================================================= -->
        <!-- MODAL: SET ROSTER SINGLE DATE (DOUBLE-BEZEL HARDWARE)         -->
        <!-- ============================================================= -->
        <div
            v-if="showSingleRosterModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 backdrop-blur-md p-4 overflow-y-auto"
        >
            <div class="p-1 rounded-3xl bg-gradient-to-b from-slate-200 to-slate-300 w-full max-w-md shadow-2xl animate-in fade-in zoom-in-95 duration-200">
                <div class="rounded-[calc(1.5rem-2px)] bg-white p-5 shadow-sm">
                    <div class="mb-4 flex items-center justify-between border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center border border-blue-200">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <h3 class="text-sm font-black text-slate-900 tracking-tight">
                                Atur Roster Harian Trainer
                            </h3>
                        </div>
                        <button
                            type="button"
                            @click="showSingleRosterModal = false"
                            class="w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 hover:text-slate-800 flex items-center justify-center transition-colors"
                        >
                            ✕
                        </button>
                    </div>

                    <form @submit.prevent="submitSingleRoster" class="space-y-3.5">
                        <p v-if="singleRosterForm.errors.status" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs font-semibold text-rose-800" role="alert">
                            ⚠️ {{ singleRosterForm.errors.status }}
                        </p>

                        <div>
                            <label class="mb-1 block text-xs font-bold text-slate-800">Pilih Trainer <span class="text-rose-500">*</span></label>
                            <select
                                v-model="singleRosterForm.trainer_id"
                                required
                                class="w-full rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 text-xs font-medium text-slate-800 transition-all"
                            >
                                <option value="">-- Pilih Trainer --</option>
                                <option v-for="t in trainers" :key="t.id" :value="t.id">
                                    {{ t.name }} ({{ t.department || '-' }})
                                </option>
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1 block text-xs font-bold text-slate-800">Tanggal <span class="text-rose-500">*</span></label>
                                <input
                                    v-model="singleRosterForm.roster_date"
                                    type="date"
                                    required
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 text-xs font-mono font-medium text-slate-800 transition-all"
                                />
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-bold text-slate-800">Shift</label>
                                <select
                                    v-model="singleRosterForm.shift"
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 text-xs font-medium text-slate-800 transition-all"
                                >
                                    <option value="day">Day Shift (Siang)</option>
                                    <option value="night">Night Shift (Malam)</option>
                                    <option value="full">Full Day (Penuh)</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-bold text-slate-800">Status Kehadiran <span class="text-rose-500">*</span></label>
                            <select
                                v-model="singleRosterForm.status"
                                @change="onSingleStatusChange"
                                required
                                class="w-full rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 text-xs font-medium text-slate-800 transition-all"
                            >
                                <option value="on_duty">ON DUTY (Masuk Kerja / Siap Mengajar)</option>
                                <option value="off_roster">OFF ROSTER (Libur Roster)</option>
                                <option value="annual_leave">CUTI TAHUNAN</option>
                                <option value="sick">SAKIT (SKD Dokter)</option>
                                <option value="site_visit">SITE VISIT (Dinas Luar)</option>
                            </select>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-bold text-slate-800">Jenis Aktivitas <span class="text-rose-500">*</span></label>
                            <select
                                v-model="singleRosterForm.activity_type"
                                @change="onSingleActivityChange"
                                required
                                class="w-full rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 text-xs font-medium text-slate-800 transition-all"
                            >
                                <option value="TRAINING_CLASS">Mengajar Kelas (Training Class)</option>
                                <option value="FIELD_OBSERVATION">Observasi Lapangan (Field Observation)</option>
                                <option value="LND_ADMIN">Tugas LnD (Administrasi)</option>
                                <option value="STANDBY">Standby</option>
                                <option value="OFF">Off / Libur</option>
                            </select>
                        </div>

                        <div v-if="singleRosterForm.activity_type === 'TRAINING_CLASS'">
                            <label class="mb-1 block text-xs font-bold text-slate-800">Pilih Modul / Kelas Kursus</label>
                            <select
                                v-model="singleRosterForm.course_id"
                                class="w-full rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 text-xs font-medium text-slate-800 transition-all"
                            >
                                <option value="">-- Tanpa Kursus Terikat / Umum --</option>
                                <option v-for="c in courses" :key="c.id" :value="c.id">
                                    {{ c.code }} - {{ c.title }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-bold text-slate-800">Lokasi Penugasan (Pit / Ruang)</label>
                            <input
                                v-model="singleRosterForm.location"
                                type="text"
                                placeholder="Contoh: Pit A / Gedung Diklat R.201"
                                class="w-full rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 text-xs font-medium text-slate-800 transition-all"
                            />
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-bold text-slate-800">Catatan Khusus</label>
                            <input
                                v-model="singleRosterForm.notes"
                                type="text"
                                placeholder="Keterangan tambahan..."
                                class="w-full rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 text-xs font-medium text-slate-800 transition-all"
                            />
                        </div>

                        <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                            <button
                                type="button"
                                @click="showSingleRosterModal = false"
                                class="rounded-xl bg-slate-100 px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-200 active:scale-[0.98] transition-all"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                :disabled="singleRosterForm.processing"
                                class="rounded-xl bg-gradient-to-r from-blue-700 to-indigo-600 hover:from-blue-800 hover:to-indigo-700 px-5 py-2 text-xs font-bold text-white shadow-sm shadow-blue-500/25 active:scale-[0.98] disabled:opacity-50 transition-all"
                            >
                                {{ singleRosterForm.processing ? 'Menyimpan...' : 'Simpan Roster' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- MODAL: BULK SET ROSTER DATE RANGE (DOUBLE-BEZEL HARDWARE)     -->
        <!-- ============================================================= -->
        <div
            v-if="showBulkRosterModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 backdrop-blur-md p-4 overflow-y-auto"
        >
            <div class="p-1 rounded-3xl bg-gradient-to-b from-slate-200 to-slate-300 w-full max-w-lg shadow-2xl animate-in fade-in zoom-in-95 duration-200">
                <div class="rounded-[calc(1.5rem-2px)] bg-white p-6 shadow-sm">
                    <div class="mb-4 flex items-center justify-between border-b border-slate-100 pb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-slate-900 text-white flex items-center justify-center shadow-sm">
                                ⚡
                            </div>
                            <div>
                                <h3 class="text-sm font-black text-slate-900 tracking-tight">Bulk Set Roster Rentang Tanggal</h3>
                                <p class="text-xs text-slate-500 mt-0.5">Tetapkan status On-Duty/Off untuk periode hari sekaligus.</p>
                            </div>
                        </div>
                        <button
                            type="button"
                            @click="showBulkRosterModal = false"
                            class="w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 hover:text-slate-800 flex items-center justify-center transition-colors"
                        >
                            ✕
                        </button>
                    </div>

                    <form @submit.prevent="submitBulkRoster" class="space-y-3.5">
                        <p v-if="bulkRosterForm.errors.status" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs font-semibold text-rose-800" role="alert">
                            ⚠️ {{ bulkRosterForm.errors.status }}
                        </p>

                        <div>
                            <label class="mb-1 block text-xs font-bold text-slate-800">Pilih Trainer <span class="text-rose-500">*</span></label>
                            <select
                                v-model="bulkRosterForm.trainer_id"
                                required
                                class="w-full rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 text-xs font-medium text-slate-800 transition-all"
                            >
                                <option value="">-- Pilih Trainer --</option>
                                <option v-for="t in trainers" :key="t.id" :value="t.id">
                                    {{ t.name }} ({{ t.department || '-' }})
                                </option>
                            </select>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1 block text-xs font-bold text-slate-800">Dari Tanggal <span class="text-rose-500">*</span></label>
                                <input
                                    v-model="bulkRosterForm.start_date"
                                    type="date"
                                    required
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 text-xs font-mono font-medium text-slate-800 transition-all"
                                />
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-bold text-slate-800">Sampai Tanggal <span class="text-rose-500">*</span></label>
                                <input
                                    v-model="bulkRosterForm.end_date"
                                    type="date"
                                    required
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 text-xs font-mono font-medium text-slate-800 transition-all"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1 block text-xs font-bold text-slate-800">Status Kehadiran <span class="text-rose-500">*</span></label>
                                <select
                                    v-model="bulkRosterForm.status"
                                    @change="onBulkStatusChange"
                                    required
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 text-xs font-medium text-slate-800 transition-all"
                                >
                                    <option value="on_duty">ON DUTY (Masuk Kerja)</option>
                                    <option value="off_roster">OFF ROSTER (Libur Roster)</option>
                                    <option value="annual_leave">CUTI TAHUNAN</option>
                                    <option value="sick">SAKIT (SKD Dokter)</option>
                                    <option value="site_visit">SITE VISIT (Dinas Luar)</option>
                                </select>
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-bold text-slate-800">Jenis Aktivitas <span class="text-rose-500">*</span></label>
                                <select
                                    v-model="bulkRosterForm.activity_type"
                                    @change="onBulkActivityChange"
                                    required
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 text-xs font-medium text-slate-800 transition-all"
                                >
                                    <option value="TRAINING_CLASS">Mengajar Kelas</option>
                                    <option value="FIELD_OBSERVATION">Observasi Lapangan</option>
                                    <option value="LND_ADMIN">Tugas LnD (Admin)</option>
                                    <option value="STANDBY">Standby</option>
                                    <option value="OFF">Off / Libur</option>
                                </select>
                            </div>
                        </div>

                        <div v-if="bulkRosterForm.activity_type === 'TRAINING_CLASS'">
                            <label class="mb-1 block text-xs font-bold text-slate-800">Pilih Modul / Kelas Kursus</label>
                            <select
                                v-model="bulkRosterForm.course_id"
                                class="w-full rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 text-xs font-medium text-slate-800 transition-all"
                            >
                                <option value="">-- Tanpa Kursus Terikat / Umum --</option>
                                <option v-for="c in courses" :key="c.id" :value="c.id">
                                    {{ c.code }} - {{ c.title }}
                                </option>
                            </select>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1 block text-xs font-bold text-slate-800">Lokasi Penugasan</label>
                                <input
                                    v-model="bulkRosterForm.location"
                                    type="text"
                                    placeholder="Area pit / ruang kelas"
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 text-xs font-medium text-slate-800 transition-all"
                                />
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-bold text-slate-800">Shift</label>
                                <select
                                    v-model="bulkRosterForm.shift"
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 text-xs font-medium text-slate-800 transition-all"
                                >
                                    <option value="day">Day Shift (Siang)</option>
                                    <option value="night">Night Shift (Malam)</option>
                                    <option value="full">Full Day (Penuh)</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-bold text-slate-800">Keterangan / Alasan</label>
                            <input
                                v-model="bulkRosterForm.notes"
                                type="text"
                                placeholder="Contoh: Roster 14-7 Site Pit..."
                                class="w-full rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 text-xs font-medium text-slate-800 transition-all"
                            />
                        </div>

                        <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                            <button
                                type="button"
                                @click="showBulkRosterModal = false"
                                class="rounded-xl bg-slate-100 px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-200 active:scale-[0.98] transition-all"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                :disabled="bulkRosterForm.processing"
                                class="rounded-xl bg-gradient-to-r from-blue-700 to-indigo-600 hover:from-blue-800 hover:to-indigo-700 px-5 py-2 text-xs font-bold text-white shadow-sm shadow-blue-500/25 active:scale-[0.98] disabled:opacity-50 transition-all"
                            >
                                {{ bulkRosterForm.processing ? 'Menerapkan...' : 'Terapkan Rentang Roster' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
