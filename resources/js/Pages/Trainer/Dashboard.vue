<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    trainer: {
        type: Object,
        required: true,
    },
    todayRoster: {
        type: Object,
        default: () => ({
            status: 'unassigned',
            label: 'BELUM ADA ROSTER',
            shift: null,
            shift_label: 'Belum ada shift ditetapkan',
            location: '-',
            notes: null,
        }),
    },
    metrics: {
        type: Object,
        default: () => ({
            hours_this_month: 0,
            hours_last_month: 0,
            hours_diff: 0,
            hours_growth_pct: 0,
            this_month_label: '',
            last_month_label: '',
            total_scanned_participants: 0,
            average_rating: 0,
            evaluations_count: 0,
            active_competencies_count: 0,
        }),
    },
    todaySessions: {
        type: Array,
        default: () => [],
    },
    upcomingSessions: {
        type: Array,
        default: () => [],
    },
    completedHistory: {
        type: Array,
        default: () => [],
    },
    kpiRecords: {
        type: Array,
        default: () => [],
    },
    assignedCourses: {
        type: Array,
        default: () => [],
    },
    recentReviews: {
        type: Array,
        default: () => [],
    },
});

// Active Tab ('live_session' | 'kpi_targets' | 'evaluations')
const activeTab = ref('live_session');

// Dynamic Greeting based on time
const greeting = computed(() => {
    const hour = new Date().getHours();
    if (hour >= 4 && hour < 11) return 'Selamat Pagi';
    if (hour >= 11 && hour < 15) return 'Selamat Siang';
    if (hour >= 15 && hour < 18) return 'Selamat Sore';
    return 'Selamat Malam';
});

const nowFormatted = computed(() => {
    const d = new Date();
    return d.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'short', year: 'numeric' });
});

// Helper Status Badge Roster
const getRosterBadge = (status) => {
    switch (status) {
        case 'on_duty':
            return {
                bg: 'bg-emerald-50 text-emerald-800 border-emerald-200 ring-1 ring-emerald-500/20',
                dot: 'bg-emerald-500 animate-pulse',
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
                bg: 'bg-amber-50 text-amber-800 border-amber-200 ring-1 ring-amber-500/20',
                dot: 'bg-amber-500',
                label: 'CUTI TAHUNAN',
            };
        case 'sick':
            return {
                bg: 'bg-rose-50 text-rose-800 border-rose-200 ring-1 ring-rose-500/20',
                dot: 'bg-rose-500',
                label: 'SAKIT (SKD)',
            };
        case 'site_visit':
            return {
                bg: 'bg-indigo-50 text-indigo-800 border-indigo-200 ring-1 ring-indigo-500/20',
                dot: 'bg-indigo-500',
                label: 'SITE VISIT',
            };
        case 'unassigned':
            return {
                bg: 'bg-slate-50 text-slate-600 border-slate-200',
                dot: 'bg-slate-400',
                label: 'BELUM ADA ROSTER',
            };
        default:
            return {
                bg: 'bg-slate-50 text-slate-700 border-slate-200',
                dot: 'bg-slate-400',
                label: status ? status.toUpperCase() : 'STANDBY',
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

const getProgramStatusBadge = (status) => {
    switch (status) {
        case 'running':
            return {
                bg: 'bg-emerald-50 text-emerald-700 border-emerald-200',
                dot: 'bg-emerald-500 animate-pulse',
                label: 'Sedang Berjalan',
            };
        case 'upcoming':
            return {
                bg: 'bg-blue-50 text-blue-700 border-blue-200',
                dot: 'bg-blue-500',
                label: 'Akan Datang',
            };
        case 'completed':
            return {
                bg: 'bg-slate-100 text-slate-600 border-slate-200',
                dot: 'bg-slate-400',
                label: 'Selesai',
            };
        default:
            return {
                bg: 'bg-slate-50 text-slate-600 border-slate-200',
                dot: 'bg-slate-400',
                label: status,
            };
    }
};
</script>

<template>
    <Head :title="`Portal Instruktur — ${trainer.name}`" />

    <AdminLayout
        title="Command Center Instruktur"
        subtitle="Pusat kendali sesi pelatihan langsung, penayangan QR presensi, pencatatan jam mengajar sah, target KPI, dan evaluasi peserta."
    >
        <div class="space-y-6">
            <!-- ============================================================= -->
            <!-- 1. HEADER SAPAAN & STATUS ROSTER HARI INI                     -->
            <!-- ============================================================= -->
            <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col justify-between gap-4 rounded-xl p-5 sm:p-6 lg:flex-row lg:items-center">
                    <div class="space-y-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-blue-100 text-blue-800 border border-blue-200">
                                Command Center Trainer
                            </span>
                            <span
                                :class="getRosterBadge(todayRoster.status).bg"
                                class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold border"
                            >
                                <span :class="getRosterBadge(todayRoster.status).dot" class="w-2 h-2 rounded-full"></span>
                                <span>{{ getRosterBadge(todayRoster.status).label }}</span>
                            </span>
                            <span class="text-xs text-slate-500 font-medium">
                                {{ todayRoster.shift_label }} · {{ todayRoster.location }}
                            </span>
                        </div>

                        <div>
                            <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">
                                {{ greeting }}, {{ trainer.name }}
                            </h1>
                            <p class="text-xs text-slate-500 mt-1 flex flex-wrap items-center gap-2 font-medium">
                                <span>NIK: <strong class="text-slate-800 font-mono">{{ trainer.nik }}</strong></span>
                                <span>·</span>
                                <span>Departemen: <strong class="text-slate-800">{{ trainer.department }}</strong></span>
                                <span>·</span>
                                <span>Peran: <strong class="text-slate-800">{{ trainer.job_position }}</strong></span>
                                <span>·</span>
                                <span class="bg-slate-200/80 px-2 py-0.2 rounded text-[10px] font-bold text-slate-700 uppercase">
                                    {{ trainer.trainer_type || 'Internal SME' }}
                                </span>
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <Link
                            href="/trainer/schedule-and-hours"
                            class="px-4 py-2.5 text-xs font-bold text-blue-700 bg-white hover:bg-blue-50 border border-blue-200/90 rounded-2xl shadow-sm transition-all flex items-center gap-2 active:scale-[0.98]"
                        >
                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Buku Induk Jam Mengajar</span>
                        </Link>

                        <Link
                            href="/training/calendar"
                            class="px-4 py-2.5 text-xs font-bold text-slate-700 bg-white hover:bg-slate-100 border border-slate-200 rounded-2xl shadow-sm transition-all flex items-center gap-2 active:scale-[0.98]"
                        >
                            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span>Kalender ATMP</span>
                        </Link>
                    </div>
                </div>
            </section>

            <!-- ============================================================= -->
            <!-- 2. 4 KARTU METRIK PERFORMA INSTRUKTUR                         -->
            <!-- ============================================================= -->
            <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Card 1: Jam Mengajar Bulan Ini vs Bulan Lalu -->
                <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="h-full bg-white rounded-[calc(1.5rem-1px)] p-4 flex flex-col justify-between">
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-black uppercase tracking-wider bg-blue-50 text-blue-700 border border-blue-200">
                                Jam Mengajar Sah
                            </span>
                            <div class="w-7 h-7 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center font-bold">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        </div>

                        <div class="mt-3">
                            <div class="flex items-baseline gap-1.5">
                                <span class="text-3xl font-black text-blue-700 tracking-tight">{{ metrics.hours_this_month }}</span>
                                <span class="text-xs font-bold text-blue-600/70">Jam Sah</span>
                            </div>

                            <div class="mt-2 pt-2 border-t border-slate-100 flex items-center justify-between text-[11px]">
                                <span class="text-slate-500">
                                    Bulan lalu: <strong>{{ metrics.hours_last_month }} Jam</strong>
                                </span>
                                <span
                                    :class="metrics.hours_growth_pct >= 0 ? 'text-emerald-700 bg-emerald-50 border-emerald-200' : 'text-slate-600 bg-slate-100 border-slate-200'"
                                    class="px-1.5 py-0.2 rounded font-black text-[10px] border flex items-center gap-0.5"
                                >
                                    <span>{{ metrics.hours_growth_pct >= 0 ? '▲ +' : '▼ ' }}{{ metrics.hours_growth_pct }}%</span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Total Peserta Terlayani (Scan QR Sah) -->
                <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="h-full bg-white rounded-[calc(1.5rem-1px)] p-4 flex flex-col justify-between">
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-black uppercase tracking-wider bg-indigo-50 text-indigo-700 border border-indigo-200">
                                Peserta Terlayani
                            </span>
                            <div class="w-7 h-7 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                            </div>
                        </div>

                        <div class="mt-3">
                            <div class="flex items-baseline gap-1.5">
                                <span class="text-3xl font-black text-indigo-700 tracking-tight">{{ metrics.total_scanned_participants }}</span>
                                <span class="text-xs font-bold text-indigo-500">Scan QR</span>
                            </div>

                            <div class="mt-2 pt-2 border-t border-slate-100 text-[11px] text-slate-500 flex items-center gap-1">
                                <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>Kehadiran Terverifikasi Digital</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Rata-Rata Rating Kepuasan Peserta -->
                <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="h-full bg-white rounded-[calc(1.5rem-1px)] p-4 flex flex-col justify-between">
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-black uppercase tracking-wider bg-amber-50 text-amber-800 border border-amber-200">
                                Indeks Kepuasan
                            </span>
                            <div class="w-7 h-7 rounded-full bg-amber-50 text-amber-500 flex items-center justify-center font-bold text-sm">
                                ★
                            </div>
                        </div>

                        <div class="mt-3">
                            <div class="flex items-center gap-1.5">
                                <span class="text-amber-500 text-2xl font-black">★</span>
                                <span class="text-3xl font-black text-amber-600 tracking-tight">
                                    {{ metrics.evaluations_count ? metrics.average_rating : '—' }}
                                </span>
                                <span class="text-xs font-bold text-slate-400">/ 5.0</span>
                            </div>

                            <div class="mt-2 pt-2 border-t border-slate-100 text-[11px] text-slate-500 flex items-center justify-between">
                                <span>Ulasan Peserta</span>
                                <span class="font-bold text-slate-800">{{ metrics.evaluations_count }} Ulasan</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 4: Kompetensi Ajar Aktif -->
                <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="h-full bg-white rounded-[calc(1.5rem-1px)] p-4 flex flex-col justify-between">
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-black uppercase tracking-wider bg-purple-50 text-purple-700 border border-purple-200">
                                Otorisasi Mengajar
                            </span>
                            <div class="w-7 h-7 rounded-full bg-purple-50 text-purple-600 flex items-center justify-center font-bold">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </div>
                        </div>

                        <div class="mt-3">
                            <div class="flex items-baseline gap-1.5">
                                <span class="text-3xl font-black text-purple-700 tracking-tight">{{ metrics.active_competencies_count }}</span>
                                <span class="text-xs font-bold text-purple-500">Materi Pelatihan</span>
                            </div>

                            <div class="mt-2 pt-2 border-t border-slate-100 text-[11px] text-slate-500 flex items-center justify-between">
                                <span>Lisensi Resmi BNSP/ToT</span>
                                <span :class="metrics.active_competencies_count > 0 ? 'text-emerald-600' : 'text-slate-500'" class="font-bold">
                                    {{ metrics.active_competencies_count > 0 ? 'Tercatat' : 'Belum ada' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ============================================================= -->
            <!-- 3. TAB CONTROLS: 3 PILAR PORTAL TRAINER                       -->
            <!-- ============================================================= -->
            <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="space-y-6 p-4 sm:p-6">
                    <!-- Tab Switcher Navigation -->
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-4">
                        <div class="p-1 bg-slate-100 rounded-2xl flex items-center gap-1.5 text-xs font-bold">
                            <!-- Tab 1 -->
                            <button
                                type="button"
                                @click="activeTab = 'live_session'"
                                :class="activeTab === 'live_session' ? 'bg-white text-blue-700 shadow-sm ring-1 ring-slate-900/5' : 'text-slate-600 hover:text-slate-900'"
                                class="px-4 py-2 rounded-xl transition-all flex items-center gap-2"
                            >
                                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1z" />
                                </svg>
                                <span>Jadwal &amp; Barcode Sesi Berjalan</span>
                                <span class="px-2 py-0.2 rounded-full text-[10px] font-black bg-blue-100 text-blue-800">
                                    {{ todaySessions.length }} Hari Ini
                                </span>
                            </button>

                            <!-- Tab 2 -->
                            <button
                                type="button"
                                @click="activeTab = 'kpi_targets'"
                                :class="activeTab === 'kpi_targets' ? 'bg-white text-blue-700 shadow-sm ring-1 ring-slate-900/5' : 'text-slate-600 hover:text-slate-900'"
                                class="px-4 py-2 rounded-xl transition-all flex items-center gap-2"
                            >
                                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                                <span>KPI &amp; Target Ajar Program</span>
                                <span class="px-2 py-0.2 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-800">
                                    {{ assignedCourses.length }} Program
                                </span>
                            </button>

                            <!-- Tab 3 -->
                            <button
                                type="button"
                                @click="activeTab = 'evaluations'"
                                :class="activeTab === 'evaluations' ? 'bg-white text-blue-700 shadow-sm ring-1 ring-slate-900/5' : 'text-slate-600 hover:text-slate-900'"
                                class="px-4 py-2 rounded-xl transition-all flex items-center gap-2"
                            >
                                <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                                </svg>
                                <span>Laporan Hasil &amp; Ulasan Peserta</span>
                                <span class="px-2 py-0.2 rounded-full text-[10px] font-black bg-amber-100 text-amber-800">
                                    {{ recentReviews.length }} Ulasan
                                </span>
                            </button>
                        </div>

                        <span class="text-xs text-slate-500 font-medium hidden sm:block">
                            Waktu Sistem: <strong>{{ nowFormatted || 'WIB' }}</strong>
                        </span>
                    </div>

                    <!-- ========================================================= -->
                    <!-- TAB 1: JADWAL & BARCODE SESI BERJALAN                     -->
                    <!-- ========================================================= -->
                    <div v-if="activeTab === 'live_session'" class="space-y-6">
                        <!-- Sesi Hari Ini -->
                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-xs font-black uppercase tracking-wider text-slate-800 flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                    <span>Sesi Kelas Pelatihan Hari Ini</span>
                                </h3>
                                <span class="text-xs font-semibold text-slate-500">
                                    {{ todaySessions.length }} Sesi Terjadwal
                                </span>
                            </div>

                            <!-- List Sesi Hari Ini -->
                            <div v-if="todaySessions.length === 0" class="p-8 rounded-2xl border border-dashed border-slate-200 bg-slate-50/50 text-center">
                                <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 mx-auto flex items-center justify-center mb-3">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <h4 class="text-sm font-bold text-slate-800">Tidak ada sesi kelas aktif hari ini</h4>
                                <p class="text-xs text-slate-500 mt-1 max-w-md mx-auto">
                                    Anda sedang berstatus <strong>{{ todayRoster.label }}</strong>. Sesi pelatihan berikutnya dapat dilihat pada daftar sesi mendatang di bawah.
                                </p>
                            </div>

                            <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                <div
                                    v-for="s in todaySessions"
                                    :key="s.id"
                                    class="flex flex-col justify-between gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition-shadow hover:shadow-md"
                                >
                                    <div>
                                        <div class="flex items-center justify-between gap-2 mb-2">
                                            <div class="flex items-center gap-1.5">
                                                <span class="font-mono text-[10px] font-bold text-blue-700 bg-blue-50 px-2 py-0.5 rounded border border-blue-200">
                                                    {{ s.course_code }}
                                                </span>
                                                <span :class="getCategoryColor(s.training_category)" class="text-xs font-bold px-2 py-0.5 rounded border">
                                                    {{ s.training_category }}
                                                </span>
                                            </div>
                                            <span
                                                :class="s.is_live
                                                    ? 'text-emerald-700 bg-emerald-50 border-emerald-200'
                                                    : 'text-slate-600 bg-slate-100 border-slate-200'"
                                                class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full border"
                                            >
                                                <span
                                                    :class="s.is_live ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400'"
                                                    class="w-1.5 h-1.5 rounded-full"
                                                ></span>
                                                <span>{{ s.is_live ? 'LIVE PRESENSI QR' : 'PRESENSI NONAKTIF' }}</span>
                                            </span>
                                        </div>

                                        <h4 class="text-sm font-black text-slate-900 leading-snug">
                                            {{ s.title || s.course_title }}
                                        </h4>
                                        <p class="text-xs text-slate-600 mt-0.5 line-clamp-1">
                                            {{ s.course_title }}
                                        </p>

                                        <div class="grid grid-cols-2 gap-2 mt-3 pt-3 border-t border-slate-100 text-xs">
                                            <div>
                                                <div class="text-[10px] font-semibold text-slate-400 uppercase">Waktu</div>
                                                <div class="font-mono font-bold text-blue-700">{{ s.time_range }}</div>
                                            </div>
                                            <div>
                                                <div class="text-[10px] font-semibold text-slate-400 uppercase">Ruangan / Lokasi</div>
                                                <div class="font-bold text-slate-800 truncate">{{ s.location }}</div>
                                            </div>
                                        </div>

                                        <!-- Attendance Mini Bar -->
                                        <div class="mt-3 p-2.5 rounded-xl bg-slate-100/70 border border-slate-200/80">
                                            <div class="flex items-center justify-between text-xs mb-1">
                                                <span class="font-medium text-slate-600">Realisasi Kehadiran:</span>
                                                <span class="font-bold text-slate-900">
                                                    {{ s.present_count }} <span class="text-slate-400 font-normal">/ {{ s.quota }} Peserta</span>
                                                </span>
                                            </div>
                                            <div class="w-full h-1.5 bg-slate-200 rounded-full overflow-hidden">
                                                <div
                                                    class="h-full bg-emerald-600 rounded-full"
                                                    :style="{ width: Math.min(100, Math.round(((s.present_count || 0) / s.quota) * 100)) + '%' }"
                                                ></div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="flex items-center gap-2 pt-2 border-t border-slate-100">
                                        <Link
                                            v-if="s.is_live"
                                            :href="`/admin/attendance-sessions?session_id=${s.id}`"
                                            class="flex-1 min-h-11 px-4 py-2.5 text-xs font-bold text-white bg-blue-700 hover:bg-blue-800 rounded-xl transition-colors flex items-center justify-center gap-2 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2"
                                            title="Buka Layar Proyektor Penuh"
                                            :aria-label="`Buka Layar Proyektor QR 15 detik untuk ${s.title || s.course_title}`"
                                        >
                                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                                            </svg>
                                            <span>Layar Proyektor QR 15d</span>
                                        </Link>
                                        <span
                                            v-else
                                            class="flex-1 min-h-11 px-4 py-2.5 text-xs font-semibold text-slate-500 bg-slate-100 rounded-xl flex items-center justify-center"
                                        >
                                            Sesi presensi tidak aktif
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sesi Mendatang -->
                        <div class="pt-4 border-t border-slate-100">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-xs font-black uppercase tracking-wider text-slate-800 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <span>Jadwal Kelas Mendatang</span>
                                </h3>
                                <Link href="/training/calendar" class="text-xs font-bold text-blue-600 hover:text-blue-800">
                                    Buka Kalender Lengkap &rarr;
                                </Link>
                            </div>

                            <div v-if="upcomingSessions.length === 0" class="p-6 text-center text-xs text-slate-400 bg-slate-50 rounded-2xl border border-dashed border-slate-200">
                                Belum ada jadwal sesi mengajar mendatang.
                            </div>

                            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                <div
                                    v-for="us in upcomingSessions"
                                    :key="us.id"
                                    class="p-3.5 rounded-2xl bg-slate-50/70 border border-slate-200/80 hover:bg-white hover:shadow-sm transition-all flex flex-col justify-between gap-2"
                                >
                                    <div>
                                        <div class="flex items-center justify-between gap-1 mb-1.5">
                                            <span class="font-bold text-xs text-slate-900">{{ us.day_name }}, {{ us.session_date }}</span>
                                            <span class="text-xs font-mono font-bold text-blue-700 bg-blue-50 px-1.5 py-0.2 rounded border border-blue-200">
                                                {{ us.time_range }}
                                            </span>
                                        </div>
                                        <div class="text-xs font-bold text-slate-900 line-clamp-1">{{ us.title }}</div>
                                        <div class="text-[11px] text-slate-500 line-clamp-1">{{ us.course_title }}</div>
                                    </div>

                                    <div class="flex items-center justify-between pt-2 border-t border-slate-200/60 text-[10px] text-slate-600">
                                        <span class="font-medium truncate max-w-[150px]">{{ us.location }}</span>
                                        <span class="font-bold text-blue-700">{{ us.duration_hours }} Jam Ajar</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ========================================================= -->
                    <!-- TAB 2: KPI & TARGET AJAR PROGRAM                          -->
                    <!-- ========================================================= -->
                    <div v-else-if="activeTab === 'kpi_targets'" class="space-y-6">
                        <!-- KPI Targets Section -->
                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-xs font-black uppercase tracking-wider text-slate-800">
                                    Target Kinerja Individu (KPI Trainer)
                                </h3>
                                <span class="text-xs font-medium text-slate-500">Evaluasi &amp; Monitoring Berkala</span>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div
                                    v-if="kpiRecords.length === 0"
                                    class="md:col-span-2 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center"
                                >
                                    <p class="text-sm font-semibold text-slate-700">Belum ada target KPI yang ditetapkan.</p>
                                    <p class="mt-1 text-xs text-slate-500">Hubungi tim LnD untuk membuat target periode dan indikator realisasi.</p>
                                </div>
                                <div
                                    v-for="kpi in kpiRecords"
                                    :key="kpi.id"
                                    class="p-4 rounded-xl bg-white border border-slate-200 shadow-sm space-y-4"
                                >
                                    <div class="flex items-center justify-between gap-2">
                                        <div>
                                            <h4 class="text-sm font-black text-slate-900">{{ kpi.title }}</h4>
                                            <p class="text-xs text-slate-500 mt-0.5">{{ kpi.description }}</p>
                                            <p class="mt-1 text-[11px] text-slate-500">
                                                Periode realisasi: {{ kpi.start_date }}{{ kpi.due_date ? ` – ${kpi.due_date}` : ' – sekarang' }}
                                            </p>
                                        </div>
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-blue-50 text-blue-700 border border-blue-200 uppercase shrink-0">
                                            {{ kpi.period }}
                                        </span>
                                    </div>

                                    <!-- Progress 1: Teaching Hours -->
                                    <div class="space-y-1.5">
                                        <div class="flex items-center justify-between text-xs">
                                            <span class="font-semibold text-slate-700">Jam Mengajar Sah:</span>
                                            <span class="font-black text-blue-700">
                                                {{ kpi.actual_teaching_hours }} / {{ kpi.target_teaching_hours }} Jam ({{ kpi.hours_progress_pct }}%)
                                            </span>
                                        </div>
                                        <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden border border-slate-200">
                                            <div
                                                class="h-full bg-blue-600 rounded-full transition-all"
                                                :style="{ width: kpi.hours_progress_pct + '%' }"
                                            ></div>
                                        </div>
                                    </div>

                                    <!-- Progress 2: Participants Count -->
                                    <div class="space-y-1.5">
                                        <div class="flex items-center justify-between text-xs">
                                            <span class="font-semibold text-slate-700">Peserta Dilatih:</span>
                                            <span class="font-black text-emerald-700">
                                                {{ kpi.actual_participants_count }} / {{ kpi.target_participants_count }} Orang ({{ kpi.participants_progress_pct }}%)
                                            </span>
                                        </div>
                                        <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden border border-slate-200">
                                            <div
                                                class="h-full bg-emerald-600 rounded-full transition-all"
                                                :style="{ width: kpi.participants_progress_pct + '%' }"
                                            ></div>
                                        </div>
                                    </div>

                                    <!-- Target Rating -->
                                    <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-xs">
                                        <span class="text-slate-500">Target Skor Rating:</span>
                                        <span class="font-black text-amber-600 flex items-center gap-1">
                                            <span>★ {{ kpi.evaluation_count ? kpi.actual_rating : '—' }}</span>
                                            <span class="text-slate-400 font-normal">/ target {{ kpi.target_rating }}</span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Assigned Courses Section -->
                        <div class="pt-4 border-t border-slate-100">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-xs font-black uppercase tracking-wider text-slate-800">
                                    Daftar Program Pelatihan yang Ditugaskan
                                </h3>
                                <span class="text-xs font-semibold text-slate-500">
                                    Total {{ assignedCourses.length }} Kursus
                                </span>
                            </div>

                            <div v-if="assignedCourses.length === 0" class="p-6 text-center text-xs text-slate-400 bg-slate-50 rounded-2xl border border-dashed border-slate-200">
                                Belum ada penugasan kursus untuk instruktur ini.
                            </div>

                            <div v-else class="overflow-x-auto rounded-2xl border border-slate-200">
                                <table class="w-full text-left text-xs text-slate-700">
                                    <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500 border-b border-slate-200">
                                        <tr>
                                            <th class="py-3 px-4">Kode &amp; Pelatihan</th>
                                            <th class="py-3 px-3">Kategori</th>
                                            <th class="py-3 px-3">Periode</th>
                                            <th class="py-3 px-3 text-center">Modul</th>
                                            <th class="py-3 px-3 text-center">Peserta</th>
                                            <th class="py-3 px-3 text-center">Status Program</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <tr v-for="c in assignedCourses" :key="c.id" class="hover:bg-slate-50/80 transition-colors">
                                            <td class="py-3 px-4">
                                                <div class="font-mono font-bold text-blue-700">{{ c.code }}</div>
                                                <div class="font-bold text-slate-900">{{ c.title }}</div>
                                            </td>
                                            <td class="py-3 px-3">
                                                <span :class="getCategoryColor(c.training_category)" class="px-2 py-0.5 rounded text-xs font-bold border">
                                                    {{ c.training_category }}
                                                </span>
                                            </td>
                                            <td class="py-3 px-3 text-slate-600">
                                                {{ c.start_date }} s/d {{ c.end_date }}
                                            </td>
                                            <td class="py-3 px-3 text-center font-bold text-slate-800">
                                                {{ c.modules_count }} Modul
                                            </td>
                                            <td class="py-3 px-3 text-center font-bold text-indigo-700">
                                                {{ c.participants_count }} Peserta
                                            </td>
                                            <td class="py-3 px-3 text-center">
                                                <span
                                                    :class="getProgramStatusBadge(c.program_status).bg"
                                                    class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold border"
                                                >
                                                    <span :class="getProgramStatusBadge(c.program_status).dot" class="w-1.5 h-1.5 rounded-full"></span>
                                                    <span>{{ getProgramStatusBadge(c.program_status).label }}</span>
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- ========================================================= -->
                    <!-- TAB 3: LAPORAN HASIL TRAINING & ULASAN PESERTA            -->
                    <!-- ========================================================= -->
                    <div v-else-if="activeTab === 'evaluations'" class="space-y-6">
                        <!-- Completed Classes History -->
                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-xs font-black uppercase tracking-wider text-slate-800">
                                    Riwayat Kelas yang Selesai Diajar
                                </h3>
                                <span class="text-xs font-semibold text-slate-500">
                                    {{ completedHistory.length }} Sesi Terlaksana
                                </span>
                            </div>

                            <div v-if="completedHistory.length === 0" class="p-6 text-center text-xs text-slate-400 bg-slate-50 rounded-2xl border border-dashed border-slate-200">
                                Belum ada riwayat kelas yang diselesaikan.
                            </div>

                            <div v-else class="overflow-x-auto rounded-2xl border border-slate-200">
                                <table class="w-full text-left text-xs text-slate-700">
                                    <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500 border-b border-slate-200">
                                        <tr>
                                            <th class="py-3 px-4">Tanggal Pelaksanaan</th>
                                            <th class="py-3 px-4">Pelatihan &amp; Sesi</th>
                                            <th class="py-3 px-3">Lokasi</th>
                                            <th class="py-3 px-3 text-right">Durasi Sah</th>
                                            <th class="py-3 px-3 text-center">Kehadiran Peserta</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <tr v-for="ch in completedHistory" :key="ch.id" class="hover:bg-slate-50/80 transition-colors">
                                            <td class="py-3 px-4 font-bold text-slate-900">
                                                {{ ch.session_date }}
                                            </td>
                                            <td class="py-3 px-4">
                                                <span class="font-mono text-[10px] font-bold text-blue-700 bg-blue-50 px-1.5 py-0.2 rounded border border-blue-200">
                                                    {{ ch.course_code }}
                                                </span>
                                                <div class="font-semibold text-slate-800 mt-0.5">{{ ch.title }}</div>
                                            </td>
                                            <td class="py-3 px-3 text-slate-600">
                                                {{ ch.location }}
                                            </td>
                                            <td class="py-3 px-3 text-right font-black text-blue-700">
                                                {{ ch.duration_hours }} Jam
                                            </td>
                                            <td class="py-3 px-3 text-center font-bold text-emerald-700">
                                                {{ ch.present_count }} / {{ ch.quota }} Hadir
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Real Reviews from Participants -->
                        <div class="pt-4 border-t border-slate-100">
                            <div class="flex items-center justify-between mb-3">
                                <div>
                                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-800">
                                        Ulasan &amp; Komentar Asli dari Peserta
                                    </h3>
                                    <p class="text-[11px] text-slate-500 mt-0.5">
                                        <template v-if="metrics.evaluations_count">
                                            Rata-rata rating kepuasan: <strong class="text-amber-600">★ {{ metrics.average_rating }} / 5.0</strong> dari {{ metrics.evaluations_count }} feedback.
                                        </template>
                                        <template v-else>Belum ada ulasan peserta.</template>
                                    </p>
                                </div>
                            </div>

                            <div v-if="recentReviews.length === 0" class="p-8 text-center text-xs text-slate-400 bg-slate-50 rounded-2xl border border-dashed border-slate-200">
                                Belum ada ulasan atau feedback yang masuk dari peserta.
                            </div>

                            <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
                                <div
                                    v-for="rev in recentReviews"
                                    :key="rev.id"
                                    class="p-4 rounded-2xl bg-slate-50/70 border border-slate-200/80 hover:bg-white hover:shadow-sm transition-all space-y-2.5"
                                >
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-8 h-8 rounded-full bg-blue-700 text-white flex items-center justify-center font-bold text-xs uppercase shrink-0">
                                                {{ (rev.participant_name || 'P').substring(0, 2) }}
                                            </div>
                                            <div>
                                                <div class="font-bold text-xs text-slate-900">{{ rev.participant_name }}</div>
                                                <div class="text-[10px] text-slate-500">
                                                    NIK: {{ rev.participant_nik }} · {{ rev.participant_dept }}
                                                </div>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-0.5 text-amber-500 font-black text-xs shrink-0">
                                            <span>★</span>
                                            <span>{{ rev.rating }}.0</span>
                                        </div>
                                    </div>

                                    <!-- Review Comment -->
                                    <p class="text-xs text-slate-700 italic bg-white p-3 rounded-xl border border-slate-100 leading-relaxed">
                                        "{{ rev.comment }}"
                                    </p>

                                    <div class="flex items-center justify-between text-[10px] text-slate-400 pt-1">
                                        <span class="truncate max-w-[200px]">{{ rev.course_title }}</span>
                                        <span>{{ rev.date_formatted }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <!-- ============================================================= -->
    </AdminLayout>
</template>
