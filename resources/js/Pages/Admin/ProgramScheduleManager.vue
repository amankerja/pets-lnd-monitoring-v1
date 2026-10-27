<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    sessions: {
        type: Object,
        required: true,
    },
    summary: {
        type: Object,
        default: () => ({ total: 0, approved: 0, pending: 0, live: 0 }),
    },
    filters: {
        type: Object,
        default: () => ({
            search: '',
            plan_status: '',
            month: '',
            date_from: '',
            date_to: '',
            training_category: '',
        }),
    },
    courses: {
        type: Array,
        default: () => [],
    },
    trainers: {
        type: Array,
        default: () => [],
    },
    categories: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const filters = ref({ ...props.filters });
const showScheduleModal = ref(false);
const editingSession = ref(null);

const scheduleForm = useForm({
    course_id: '',
    title: '',
    session_date: '',
    start_time: '',
    end_time: '',
    trainer_id: '',
    location: '',
    quota: 30,
    plan_status: 'pending',
});

watch(() => props.filters, (value) => {
    filters.value = { ...value };
}, { deep: true });

const globalError = computed(() => page.props.errors?.session || page.props.flash?.error || '');
const flashSuccess = computed(() => page.props.flash?.success || '');
const sessionRows = computed(() => props.sessions.data || []);

const exportUrl = computed(() => {
    const params = new URLSearchParams();
    if (filters.value.search) params.set('search', filters.value.search);
    if (filters.value.plan_status) params.set('plan_status', filters.value.plan_status);
    if (filters.value.month) params.set('month', filters.value.month);
    if (filters.value.date_from) params.set('date_from', filters.value.date_from);
    if (filters.value.date_to) params.set('date_to', filters.value.date_to);
    if (filters.value.training_category) params.set('training_category', filters.value.training_category);
    const qs = params.toString();
    return `/admin/program-schedules/export${qs ? '?' + qs : ''}`;
});

const availabilityState = ref({
    loading: false,
    result: null,
});

let availabilityTimer = null;

const checkAvailability = () => {
    if (availabilityTimer) clearTimeout(availabilityTimer);

    if (!scheduleForm.trainer_id || !scheduleForm.session_date) {
        availabilityState.value = { loading: false, result: null };
        return;
    }

    availabilityState.value.loading = true;

    availabilityTimer = setTimeout(async () => {
        try {
            const params = new URLSearchParams({
                trainer_id: scheduleForm.trainer_id,
                session_date: scheduleForm.session_date,
                start_time: scheduleForm.start_time || '',
                end_time: scheduleForm.end_time || '',
                session_id: editingSession.value?.id || '',
            });

            const res = await fetch(`/admin/program-schedules/check-availability?${params.toString()}`, {
                headers: { Accept: 'application/json' },
            });

            if (res.ok) {
                availabilityState.value.result = await res.json();
            }
        } catch {
            // Silently handle fetch failure
        } finally {
            availabilityState.value.loading = false;
        }
    }, 250);
};

watch(
    [
        () => scheduleForm.trainer_id,
        () => scheduleForm.session_date,
        () => scheduleForm.start_time,
        () => scheduleForm.end_time,
    ],
    () => {
        if (showScheduleModal.value) {
            checkAvailability();
        }
    }
);

const hasActiveFilters = computed(() => {
    return Boolean(
        filters.value.search ||
        filters.value.plan_status ||
        filters.value.month ||
        filters.value.date_from ||
        filters.value.date_to ||
        filters.value.training_category
    );
});

const applyFilters = () => {
    router.get('/admin/program-schedules', {
        search: filters.value.search || undefined,
        plan_status: filters.value.plan_status || undefined,
        month: filters.value.month || undefined,
        date_from: filters.value.date_from || undefined,
        date_to: filters.value.date_to || undefined,
        training_category: filters.value.training_category || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
};

const resetFilters = () => {
    filters.value = {
        search: '',
        plan_status: '',
        month: '',
        date_from: '',
        date_to: '',
        training_category: '',
    };
    applyFilters();
};

const openCreateModal = () => {
    editingSession.value = null;
    availabilityState.value = { loading: false, result: null };
    scheduleForm.reset();
    scheduleForm.clearErrors();
    scheduleForm.quota = 30;
    scheduleForm.plan_status = 'pending';
    scheduleForm.start_time = '08:00';
    scheduleForm.end_time = '10:00';
    const today = new Date();
    scheduleForm.session_date = [
        today.getFullYear(),
        String(today.getMonth() + 1).padStart(2, '0'),
        String(today.getDate()).padStart(2, '0'),
    ].join('-');
    showScheduleModal.value = true;
};

const openEditModal = (session) => {
    editingSession.value = session;
    availabilityState.value = { loading: false, result: null };
    scheduleForm.clearErrors();
    scheduleForm.course_id = session.course_id;
    scheduleForm.title = session.title;
    scheduleForm.session_date = session.session_date ? session.session_date.slice(0, 10) : '';
    scheduleForm.start_time = session.start_time ? session.start_time.slice(0, 5) : '';
    scheduleForm.end_time = session.end_time ? session.end_time.slice(0, 5) : '';
    scheduleForm.trainer_id = session.trainer_id || '';
    scheduleForm.location = session.location || '';
    scheduleForm.quota = session.quota || 30;
    scheduleForm.plan_status = session.plan_status || 'pending';
    showScheduleModal.value = true;
    checkAvailability();
};

const submitSchedule = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showScheduleModal.value = false;
            scheduleForm.reset();
        },
    };

    if (editingSession.value) {
        scheduleForm.put(`/admin/program-schedules/${editingSession.value.id}`, options);
    } else {
        scheduleForm.post('/admin/program-schedules', options);
    }
};

const togglePlanStatus = (session) => {
    const nextStatus = session.plan_status === 'approved' ? 'pending' : 'approved';
    router.patch(`/admin/program-schedules/${session.id}/status`, {
        plan_status: nextStatus,
    }, { preserveScroll: true });
};

const deleteSession = (session) => {
    if (!window.confirm(`Hapus jadwal sesi "${session.title}"? Sesi dengan data presensi tidak dapat dihapus.`)) return;

    router.delete(`/admin/program-schedules/${session.id}`, { preserveScroll: true });
};

const formatDate = (date) => {
    if (!date) return '-';
    try {
        const clean = date.slice(0, 10);
        return new Intl.DateTimeFormat('id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        }).format(new Date(`${clean}T00:00:00`));
    } catch {
        return date;
    }
};

const formatTimeRange = (start, end) => {
    const s = start ? start.slice(0, 5) : '--:--';
    const e = end ? end.slice(0, 5) : '--:--';
    return `${s} – ${e}`;
};

const projectorUrl = (session) => `/admin/attendance-sessions?session_id=${session.id}`;
</script>

<template>
    <Head title="Manajemen Jadwal Program" />

    <AdminLayout
        title="Manajemen Jadwal Program"
        subtitle="Pusat kendali jadwal dan batch training, penugasan trainer, kapasitas kelas, serta status pelaksanaan."
    >
        <div class="space-y-6">
            <!-- ============================================================= -->
            <!-- 1. HEADER SECTION & ACTION BUTTONS                            -->
            <!-- ============================================================= -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-base sm:text-lg font-black text-slate-900 tracking-tight">
                        Program Schedule &amp; Session Manager
                    </h1>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Kelola seluruh jadwal pelaksanaan kelas pelatihan, penugasan instruktur, kapasitas kuota, dan status persetujuan.
                    </p>
                </div>

                <div class="flex items-center gap-2.5 shrink-0">
                    <a
                        :href="exportUrl"
                        class="px-3.5 py-2 text-xs font-bold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 hover:border-slate-300 rounded-xl transition-all flex items-center gap-2 shadow-sm"
                        title="Export Laporan Jadwal ke Format CSV / Excel"
                    >
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <span>Export CSV</span>
                    </a>

                    <Link
                        href="/training/calendar"
                        class="px-3.5 py-2 text-xs font-bold text-blue-700 bg-blue-50/80 border border-blue-200 hover:bg-blue-100 rounded-xl transition-all flex items-center gap-2 shadow-sm"
                    >
                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <span>Kalender Korporat</span>
                    </Link>

                    <button
                        type="button"
                        @click="openCreateModal"
                        class="group px-4 py-2 text-xs font-bold text-white bg-gradient-to-r from-blue-700 via-blue-600 to-indigo-600 hover:from-blue-800 hover:to-indigo-700 active:scale-[0.98] rounded-xl shadow-sm shadow-blue-500/25 flex items-center gap-2 transition-all duration-200 ring-1 ring-white/20"
                    >
                        <span>+ Buat Jadwal Program Baru</span>
                        <div class="w-5 h-5 rounded-full bg-white/20 text-white flex items-center justify-center group-hover:rotate-90 transition-transform">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                            </svg>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Global Alert Banner -->
            <div
                v-if="globalError"
                class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold flex items-center gap-3 shadow-sm"
                role="alert"
            >
                <span class="text-base">⚠️</span>
                <span>{{ globalError }}</span>
            </div>

            <!-- ============================================================= -->
            <!-- 2. EXECUTIVE KPI SUMMARY METRICS (DOUBLE-BEZEL BENTO)         -->
            <!-- ============================================================= -->
            <section class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <!-- Card 1: Total Sesi -->
                <div class="p-1 rounded-2xl bg-gradient-to-b from-slate-200/70 to-slate-100 ring-1 ring-slate-900/5 shadow-sm transition-all duration-300 hover:shadow-md">
                    <div class="h-full bg-white rounded-[calc(1rem-1px)] p-4 flex flex-col justify-between">
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-slate-100 text-slate-700 border border-slate-200/80">
                                Total Jadwal Sesi
                            </span>
                            <div class="w-7 h-7 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="text-2xl font-black text-slate-900 tracking-tight">{{ summary.total }}</div>
                            <div class="text-[11px] font-medium text-slate-500 mt-0.5">Sesi Dalam Rencana &amp; Berjalan</div>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Approved -->
                <div class="p-1 rounded-2xl bg-gradient-to-b from-emerald-200/60 to-emerald-100/40 ring-1 ring-emerald-500/20 shadow-sm transition-all duration-300 hover:shadow-md">
                    <div class="h-full bg-white rounded-[calc(1rem-1px)] p-4 flex flex-col justify-between">
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200/80">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                Sesi Approved
                            </span>
                            <div class="w-7 h-7 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="text-2xl font-black text-emerald-700 tracking-tight">{{ summary.approved }}</div>
                            <div class="text-[11px] font-semibold text-emerald-600 mt-0.5">Resmi Masuk Kalender Korporat</div>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Pending -->
                <div class="p-1 rounded-2xl bg-gradient-to-b from-amber-200/60 to-amber-100/40 ring-1 ring-amber-500/20 shadow-sm transition-all duration-300 hover:shadow-md">
                    <div class="h-full bg-white rounded-[calc(1rem-1px)] p-4 flex flex-col justify-between">
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-amber-50 text-amber-800 border border-amber-200/80">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                Sesi Pending
                            </span>
                            <div class="w-7 h-7 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="text-2xl font-black text-amber-700 tracking-tight">{{ summary.pending }}</div>
                            <div class="text-[11px] font-medium text-amber-700 mt-0.5">Usulan / Menunggu Verifikasi</div>
                        </div>
                    </div>
                </div>

                <!-- Card 4: Sedang Berjalan (LIVE) -->
                <div class="p-1 rounded-2xl bg-gradient-to-b from-rose-200/60 to-rose-100/40 ring-1 ring-rose-500/20 shadow-sm transition-all duration-300 hover:shadow-md">
                    <div class="h-full bg-white rounded-[calc(1rem-1px)] p-4 flex flex-col justify-between">
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-rose-50 text-rose-700 border border-rose-200/80">
                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500 animate-pulse"></span>
                                Sedang Berjalan
                            </span>
                            <div class="w-7 h-7 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center font-bold text-xs">
                                LIVE
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="text-2xl font-black text-rose-700 tracking-tight">{{ summary.live }}</div>
                            <div class="text-[11px] font-medium text-rose-600 mt-0.5">Sesi Hari Ini &amp; QR Aktif</div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ============================================================= -->
            <!-- 3. GLOBAL FILTER BAR (DOUBLE-BEZEL DECK)                      -->
            <!-- ============================================================= -->
            <section class="p-1 rounded-3xl bg-slate-100/80 ring-1 ring-slate-900/5 shadow-sm">
                <div class="bg-white rounded-[calc(1.5rem-1px)] p-4 sm:p-5 shadow-sm">
                    <form @submit.prevent="applyFilters" class="space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-2.5">
                            <!-- 1. Search Query -->
                            <div class="lg:col-span-2 relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>
                                <input
                                    v-model="filters.search"
                                    type="text"
                                    placeholder="Cari judul sesi atau kode training..."
                                    class="w-full pl-9 pr-3 py-2 text-xs font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-800"
                                />
                            </div>

                            <!-- 2. Status Rencana -->
                            <div>
                                <select
                                    v-model="filters.plan_status"
                                    @change="applyFilters"
                                    class="w-full px-3 py-2 text-xs font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-800"
                                >
                                    <option value="">Semua Status Rencana</option>
                                    <option value="approved">Approved</option>
                                    <option value="pending">Pending</option>
                                </select>
                            </div>

                            <!-- 3. Kategori Training -->
                            <div>
                                <select
                                    v-model="filters.training_category"
                                    @change="applyFilters"
                                    class="w-full px-3 py-2 text-xs font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-800"
                                >
                                    <option value="">Semua Kategori</option>
                                    <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
                                </select>
                            </div>

                            <!-- 4. Filter Bulan -->
                            <div>
                                <input
                                    v-model="filters.month"
                                    @change="applyFilters"
                                    type="month"
                                    title="Pilih Bulan Sesi"
                                    class="w-full px-3 py-2 text-xs font-mono font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 transition-all text-slate-800"
                                />
                            </div>

                            <!-- 5. Action Buttons -->
                            <div class="flex items-center gap-2">
                                <button
                                    type="submit"
                                    class="flex-1 px-4 py-2 text-xs font-bold text-white bg-slate-900 hover:bg-slate-800 active:scale-[0.98] rounded-xl transition-all"
                                >
                                    Terapkan
                                </button>
                                <button
                                    type="button"
                                    @click="resetFilters"
                                    class="px-3 py-2 text-xs font-semibold text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-xl transition-all"
                                    title="Reset filter"
                                >
                                    Reset
                                </button>
                            </div>
                        </div>

                        <!-- Date range sub-row -->
                        <div class="flex flex-wrap items-center gap-3 pt-2 border-t border-slate-100 text-xs">
                            <span class="text-slate-500 font-bold text-[11px] uppercase tracking-wider">Rentang Hari:</span>
                            <div class="flex items-center gap-2">
                                <input
                                    v-model="filters.date_from"
                                    type="date"
                                    placeholder="Dari Tanggal"
                                    class="px-2.5 py-1 text-xs font-mono rounded-lg border border-slate-200 bg-slate-50/50 text-slate-800"
                                    @change="applyFilters"
                                />
                                <span class="text-slate-400">s/d</span>
                                <input
                                    v-model="filters.date_to"
                                    type="date"
                                    placeholder="Sampai Tanggal"
                                    class="px-2.5 py-1 text-xs font-mono rounded-lg border border-slate-200 bg-slate-50/50 text-slate-800"
                                    @change="applyFilters"
                                />
                            </div>
                        </div>
                    </form>
                </div>
            </section>

            <!-- ============================================================= -->
            <!-- 4. FLAT TABLE MANAJEMEN PROGRAM BERJALAN & JADWAL             -->
            <!-- ============================================================= -->
            <section class="p-1 rounded-3xl bg-slate-100/80 ring-1 ring-slate-900/5 shadow-sm overflow-hidden">
                <div class="bg-white rounded-[calc(1.5rem-1px)] overflow-hidden shadow-sm">
                    <!-- Table header strip -->
                    <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                        <div>
                            <h2 class="text-xs font-black uppercase tracking-wider text-slate-800">
                                Jadwal Sesi Pelatihan Terdaftar
                            </h2>
                            <p class="text-[11px] text-slate-500 mt-0.5">
                                Menampilkan {{ sessionRows.length }} dari total {{ sessions.total }} agenda sesi pelatihan.
                            </p>
                        </div>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600 border border-slate-200">
                            Paginasi: 25 Item / Halaman
                        </span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-[1450px] w-full text-left text-xs text-slate-700">
                            <thead class="bg-slate-50/80 border-b border-slate-200/80 text-[10px] font-black uppercase tracking-wider text-slate-500 select-none">
                                <tr>
                                    <th class="py-3.5 px-3">Judul Sesi / Batch</th>
                                    <th class="py-3.5 px-5">Kode &amp; Nama Training</th>
                                    <th class="py-3.5 px-3">Tanggal Pelaksanaan</th>
                                    <th class="py-3.5 px-3">Jam Mulai - Selesai</th>
                                    <th class="py-3.5 px-3">Nama Trainer</th>
                                    <th class="py-3.5 px-3">Lokasi</th>
                                    <th class="py-3.5 px-3 text-center">Kapasitas / Kuota</th>
                                    <th class="py-3.5 px-3 text-center">Status Jadwal</th>
                                    <th class="py-3.5 px-5 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="session in sessionRows"
                                    :key="session.id"
                                    class="hover:bg-slate-50/90 transition-colors group align-top"
                                >
                                    <td class="py-3.5 px-3">
                                        <div class="font-extrabold text-slate-900 text-xs">
                                            {{ session.title }}
                                        </div>
                                        <div v-if="session.attendances_count > 0" class="mt-1 text-[10px] text-indigo-700 font-bold">
                                            {{ session.attendances_count }} Presensi
                                        </div>
                                    </td>

                                    <td class="py-3.5 px-5">
                                        <div class="flex items-start gap-2.5">
                                            <div class="min-w-0">
                                                <span class="inline-flex rounded-md bg-blue-50 text-blue-700 px-2 py-0.5 font-mono text-[10px] font-black border border-blue-200">
                                                    {{ session.course?.code || '—' }}
                                                </span>
                                                <div class="mt-1 font-bold text-slate-900 text-xs leading-snug">
                                                    {{ session.course?.title || 'Training tidak terdaftar' }}
                                                </div>
                                                <div class="text-[10px] text-slate-400 mt-0.5 font-medium">
                                                    {{ session.course?.training_category || 'Umum' }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="py-3.5 px-3 whitespace-nowrap font-bold text-slate-900 text-xs">
                                        {{ formatDate(session.session_date) }}
                                    </td>

                                    <td class="py-3.5 px-3 whitespace-nowrap">
                                        <div class="text-[11px] font-mono text-slate-700 font-semibold">
                                            {{ formatTimeRange(session.start_time, session.end_time) }}
                                        </div>
                                    </td>

                                    <td class="py-3.5 px-3">
                                        <div class="font-bold text-slate-900 text-xs">
                                            {{ session.trainer?.name || 'Belum Ditugaskan' }}
                                        </div>
                                        <div v-if="session.trainer?.nik" class="text-[10px] font-mono text-slate-500 mt-0.5">
                                            NIK: {{ session.trainer.nik }}
                                        </div>
                                    </td>

                                    <td class="py-3.5 px-3">
                                        <div class="font-semibold text-slate-800 text-xs truncate max-w-[180px]" :title="session.location">
                                            📍 {{ session.location || 'Site Training Room' }}
                                        </div>
                                    </td>

                                    <td class="py-3.5 px-3 text-center whitespace-nowrap">
                                        <div class="font-mono text-xs font-bold text-blue-700">
                                            {{ session.registered_count }}/{{ session.quota ?? 'Tanpa Batas' }}
                                        </div>
                                    </td>

                                    <td class="py-3.5 px-3 text-center">
                                        <span
                                            v-if="session.is_live"
                                            class="inline-flex items-center gap-1.5 rounded-full border border-rose-200 bg-rose-50 px-3 py-1 text-[10px] font-black text-rose-700"
                                        >
                                            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-rose-500"></span>
                                            Live
                                        </span>
                                        <button
                                            v-else
                                            type="button"
                                            @click="togglePlanStatus(session)"
                                            class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-[10px] font-black transition-all"
                                            :class="session.plan_status === 'approved'
                                                ? 'border-emerald-300 bg-emerald-50 text-emerald-800 hover:bg-emerald-100'
                                                : 'border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100'"
                                            :title="`Klik untuk mengubah status menjadi ${session.plan_status === 'approved' ? 'Pending' : 'Approved'}`"
                                        >
                                            {{ session.plan_status === 'approved' ? 'Approved' : 'Pending' }}
                                        </button>
                                    </td>

                                    <td class="py-3.5 px-5 text-right whitespace-nowrap">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <Link
                                                :href="`/admin/enrollments?session_id=${session.id}`"
                                                class="rounded-xl border border-blue-200 bg-blue-50 px-2.5 py-1.5 text-xs font-bold text-blue-700 transition-all hover:bg-blue-600 hover:text-white"
                                            >
                                                Kelola Peserta
                                            </Link>
                                            <!-- Buka Proyektor QR -->
                                            <a
                                                :href="projectorUrl(session)"
                                                class="px-2.5 py-1.5 rounded-xl bg-blue-50 text-blue-700 hover:bg-blue-600 hover:text-white border border-blue-200 text-xs font-bold transition-all shadow-sm flex items-center gap-1"
                                                title="Buka Layar Proyektor QR Sesi"
                                            >
                                                <span>QR Proyektor</span>
                                            </a>

                                            <!-- Edit Jadwal -->
                                            <button
                                                type="button"
                                                @click="openEditModal(session)"
                                                class="px-2.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-all"
                                                title="Edit Informasi Jadwal Sesi"
                                            >
                                                Edit
                                            </button>

                                            <!-- Hapus Jadwal -->
                                            <button
                                                type="button"
                                                @click="deleteSession(session)"
                                                :disabled="session.is_active || session.attendances_count > 0"
                                                :class="[
                                                    'px-2.5 py-1.5 rounded-xl text-xs font-bold transition-all',
                                                    session.is_active || session.attendances_count > 0
                                                        ? 'bg-slate-100 text-slate-400 cursor-not-allowed border border-slate-200'
                                                        : 'bg-rose-50 hover:bg-rose-600 text-rose-700 hover:text-white border border-rose-200'
                                                ]"
                                                :title="session.is_active || session.attendances_count > 0 ? 'Sesi sedang aktif atau memiliki riwayat presensi' : 'Hapus Sesi Jadwal'"
                                            >
                                                Hapus
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <tr v-if="sessionRows.length === 0">
                                    <td colspan="9" class="py-16 text-center text-slate-400">
                                        <div class="max-w-sm mx-auto space-y-2">
                                            <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 mx-auto flex items-center justify-center">
                                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                </svg>
                                            </div>
                                            <div class="font-bold text-slate-700 text-xs">Belum Ada Jadwal Sesi yang Cocok</div>
                                            <div class="text-[11px] text-slate-400">
                                                Tidak ditemukan sesi yang sesuai dengan filter pencarian atau rentang tanggal yang dipilih.
                                            </div>
                                            <div class="pt-2 flex items-center justify-center gap-3">
                                                <button
                                                    type="button"
                                                    @click="resetFilters"
                                                    class="text-xs font-bold text-blue-600 hover:text-blue-700 underline"
                                                >
                                                    Bersihkan Filter
                                                </button>
                                                <button
                                                    type="button"
                                                    @click="openCreateModal"
                                                    class="text-xs font-bold text-emerald-600 hover:text-emerald-700 underline"
                                                >
                                                    + Buat Jadwal Program Baru
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div
                        v-if="sessions.links && sessions.links.length > 3"
                        class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-5 py-4 text-xs"
                    >
                        <p class="text-slate-500 font-medium">
                            Menampilkan halaman <strong class="text-slate-800">{{ sessions.current_page }}</strong> dari <strong class="text-slate-800">{{ sessions.last_page }}</strong>
                        </p>
                        <div class="flex flex-wrap items-center gap-1">
                            <button
                                v-for="link in sessions.links"
                                :key="`${link.label}-${link.url || 'na'}`"
                                type="button"
                                :disabled="!link.url"
                                @click="link.url && router.visit(link.url, { preserveScroll: true })"
                                class="min-h-8 min-w-8 px-2.5 rounded-lg text-xs font-bold transition-all"
                                :class="link.active
                                    ? 'bg-blue-700 text-white shadow-sm'
                                    : link.url
                                        ? 'bg-slate-100 text-slate-700 hover:bg-slate-200'
                                        : 'text-slate-400 cursor-not-allowed bg-transparent'"
                                v-html="link.label"
                            />
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <!-- ============================================================= -->
        <!-- 5. MODAL FORM: TAMBAH / EDIT JADWAL SESI                      -->
        <!-- ============================================================= -->
        <div
            v-if="showScheduleModal"
            class="fixed inset-0 z-50 bg-slate-950/50 backdrop-blur-md flex items-center justify-center p-4 overflow-y-auto"
        >
            <div class="p-1 rounded-3xl bg-gradient-to-b from-slate-200 to-slate-300 w-full max-w-xl shadow-2xl animate-in fade-in zoom-in-95 duration-200">
                <div class="rounded-[calc(1.5rem-2px)] bg-white p-6 shadow-sm max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-5">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center border border-blue-200 shadow-sm">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-sm font-black text-slate-900 tracking-tight">
                                    {{ editingSession ? 'Edit Jadwal Sesi Pelatihan' : 'Buat Jadwal Program Baru' }}
                                </h3>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    Lengkapi data agenda agar otomatis tampil di Kalender Korporat.
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            @click="showScheduleModal = false"
                            class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 hover:text-slate-800 flex items-center justify-center transition-colors"
                        >
                            ✕
                        </button>
                    </div>

                    <form @submit.prevent="submitSchedule" class="space-y-4">
                        <!-- Error Banner Form -->
                        <div
                            v-if="scheduleForm.errors.session || scheduleForm.errors.trainer_id"
                            class="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold leading-relaxed"
                            role="alert"
                        >
                            ⚠️ {{ scheduleForm.errors.session || scheduleForm.errors.trainer_id }}
                        </div>

                        <!-- 1. Dropdown Course -->
                        <div>
                            <label class="block text-xs font-bold text-slate-800 mb-1">
                                Course / Program Pelatihan <span class="text-rose-500">*</span>
                            </label>
                            <select
                                v-model="scheduleForm.course_id"
                                required
                                class="w-full text-xs font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 text-slate-800 transition-all"
                            >
                                <option value="">-- Pilih Course / Training --</option>
                                <option v-for="c in courses" :key="c.id" :value="c.id">
                                    [{{ c.code }}] {{ c.title }} ({{ c.training_category || 'Umum' }})
                                </option>
                            </select>
                            <p v-if="scheduleForm.errors.course_id" class="text-xs text-rose-600 mt-1 font-semibold">
                                {{ scheduleForm.errors.course_id }}
                            </p>
                        </div>

                        <!-- 2. Judul Sesi / Batch -->
                        <div>
                            <label class="block text-xs font-bold text-slate-800 mb-1">
                                Judul Sesi / Batch Pertemuan <span class="text-rose-500">*</span>
                            </label>
                            <input
                                v-model="scheduleForm.title"
                                required
                                maxlength="150"
                                type="text"
                                placeholder="Contoh: Batch 1 - Sesi Pagi Teori K3"
                                class="w-full text-xs font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 text-slate-800 transition-all"
                            />
                            <p v-if="scheduleForm.errors.title" class="text-xs text-rose-600 mt-1 font-semibold">
                                {{ scheduleForm.errors.title }}
                            </p>
                        </div>

                        <!-- 3. Tanggal Sesi & Jam Pelaksanaan -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-800 mb-1">
                                    Tanggal Sesi <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    v-model="scheduleForm.session_date"
                                    required
                                    type="date"
                                    class="w-full text-xs font-mono font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 text-slate-800 transition-all"
                                />
                                <p v-if="scheduleForm.errors.session_date" class="text-xs text-rose-600 mt-1 font-semibold">
                                    {{ scheduleForm.errors.session_date }}
                                </p>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-800 mb-1">
                                    Jam Mulai <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    v-model="scheduleForm.start_time"
                                    required
                                    type="time"
                                    class="w-full text-xs font-mono font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 text-slate-800 transition-all"
                                />
                                <p v-if="scheduleForm.errors.start_time" class="text-xs text-rose-600 mt-1 font-semibold">
                                    {{ scheduleForm.errors.start_time }}
                                </p>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-800 mb-1">
                                    Jam Selesai <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    v-model="scheduleForm.end_time"
                                    required
                                    type="time"
                                    class="w-full text-xs font-mono font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 text-slate-800 transition-all"
                                />
                                <p v-if="scheduleForm.errors.end_time" class="text-xs text-rose-600 mt-1 font-semibold">
                                    {{ scheduleForm.errors.end_time }}
                                </p>
                            </div>
                        </div>

                        <!-- 4. Instruktur Trainer -->
                        <div>
                            <label class="block text-xs font-bold text-slate-800 mb-1">
                                Instruktur / Trainer Bertugas <span class="text-rose-500">*</span>
                            </label>
                            <select
                                v-model="scheduleForm.trainer_id"
                                required
                                class="w-full text-xs font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 text-slate-800 transition-all"
                            >
                                <option value="">-- Pilih Trainer --</option>
                                <option v-for="t in trainers" :key="t.id" :value="t.id">
                                    {{ t.name }} {{ t.nik ? `(NIK: ${t.nik})` : '' }} — {{ t.department || 'LnD' }}
                                </option>
                            </select>
                            <p v-if="scheduleForm.errors.trainer_id" class="text-xs text-rose-600 mt-1 font-semibold">
                                {{ scheduleForm.errors.trainer_id }}
                            </p>

                            <!-- Live Trainer Availability Indicator -->
                            <div v-if="scheduleForm.trainer_id && scheduleForm.session_date" class="mt-2">
                                <div v-if="availabilityState.loading" class="flex items-center gap-2 px-3 py-2 rounded-xl bg-slate-100 text-slate-600 text-xs font-medium animate-pulse">
                                    <svg class="w-3.5 h-3.5 animate-spin text-slate-500" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span>Memeriksa ketersediaan jadwal & status roster trainer...</span>
                                </div>
                                <div
                                    v-else-if="availabilityState.result"
                                    :class="[
                                        'px-3 py-2 rounded-xl text-xs font-semibold flex items-start gap-2 border transition-all',
                                        availabilityState.result.available && availabilityState.result.conflict_type === 'none'
                                            ? 'bg-emerald-50 border-emerald-200 text-emerald-800'
                                            : availabilityState.result.available && availabilityState.result.conflict_type === 'different_hours'
                                                ? 'bg-amber-50 border-amber-200 text-amber-800'
                                                : 'bg-rose-50 border-rose-200 text-rose-800',
                                    ]"
                                >
                                    <span v-if="availabilityState.result.available && availabilityState.result.conflict_type === 'none'" class="text-emerald-600 font-bold shrink-0">✓</span>
                                    <span v-else-if="availabilityState.result.available" class="text-amber-600 font-bold shrink-0">ℹ</span>
                                    <span v-else class="text-rose-600 font-bold shrink-0">⛔</span>
                                    <div class="space-y-0.5">
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-bold uppercase tracking-wider text-[10px]">{{ availabilityState.result.status }}</span>
                                        </div>
                                        <p class="text-[11px] font-normal leading-relaxed">{{ availabilityState.result.message }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 5. Lokasi, Kuota, & Status Rencana -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-800 mb-1">
                                    Lokasi Ruangan <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    v-model="scheduleForm.location"
                                    required
                                    maxlength="120"
                                    type="text"
                                    placeholder="Contoh: Diklat Room 201"
                                    class="w-full text-xs font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 text-slate-800 transition-all"
                                />
                                <p v-if="scheduleForm.errors.location" class="text-xs text-rose-600 mt-1 font-semibold">
                                    {{ scheduleForm.errors.location }}
                                </p>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-800 mb-1">
                                    Kuota Peserta <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    v-model.number="scheduleForm.quota"
                                    required
                                    min="1"
                                    max="1000"
                                    type="number"
                                    class="w-full text-xs font-mono font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 text-slate-800 transition-all"
                                />
                                <p v-if="scheduleForm.errors.quota" class="text-xs text-rose-600 mt-1 font-semibold">
                                    {{ scheduleForm.errors.quota }}
                                </p>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-800 mb-1">
                                    Status Rencana <span class="text-rose-500">*</span>
                                </label>
                                <select
                                    v-model="scheduleForm.plan_status"
                                    required
                                    class="w-full text-xs font-medium rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 text-slate-800 transition-all"
                                >
                                    <option value="pending">Pending (Usulan)</option>
                                    <option value="approved">Approved (Resmi)</option>
                                </select>
                                <p v-if="scheduleForm.errors.plan_status" class="text-xs text-rose-600 mt-1 font-semibold">
                                    {{ scheduleForm.errors.plan_status }}
                                </p>
                            </div>
                        </div>

                        <!-- Footer Actions -->
                        <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
                            <button
                                type="button"
                                @click="showScheduleModal = false"
                                class="px-4 py-2 text-xs font-bold text-slate-600 hover:text-slate-800 rounded-xl bg-slate-100 hover:bg-slate-200 active:scale-[0.98] transition-all"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                :disabled="scheduleForm.processing || (availabilityState.result && !availabilityState.result.available)"
                                :class="[
                                    'px-5 py-2 text-xs font-bold rounded-xl shadow-sm active:scale-[0.98] transition-all flex items-center gap-2',
                                    availabilityState.result && !availabilityState.result.available
                                        ? 'bg-slate-200 text-slate-400 cursor-not-allowed shadow-none'
                                        : 'text-white bg-gradient-to-r from-blue-700 to-indigo-600 hover:from-blue-800 hover:to-indigo-700 shadow-blue-500/25',
                                ]"
                            >
                                <span>{{ scheduleForm.processing ? 'Menyimpan...' : editingSession ? 'Simpan Perubahan' : 'Jadwalkan Program' }}</span>
                                <svg v-if="!scheduleForm.processing" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
