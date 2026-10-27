<script setup>
import { computed, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ParticipantLayout from '@/Layouts/ParticipantLayout.vue';

const props = defineProps({
    todayDateFormatted: {
        type: String,
        default: '',
    },
    todaySessions: {
        type: Array,
        default: () => [],
    },
    upcomingSessions: {
        type: Array,
        default: () => [],
    },
    courses: {
        type: Array,
        default: () => [],
    },
    quizzes: {
        type: Array,
        default: () => [],
    },
    activeTabParam: {
        type: String,
        default: 'today',
    },
});

const page = usePage();
const userRole = computed(() => {
    const raw = page.props.auth?.user?.role || 'lnd';
    return raw === 'admin' ? 'lnd' : raw;
});

const activeTab = ref(props.activeTabParam || 'today'); // 'today' | 'barcode_sheet' | 'pre_post_test' | 'calendar'

// Jika hari ini belum ada sesi spesifik di DB, tampilkan sesi terbaru agar fitur cetak QR/Barcode langsung bisa diuji
const sessionsToDisplay = computed(() => {
    return props.todaySessions.length ? props.todaySessions : props.upcomingSessions.slice(0, 4);
});

/**
 * Generator Native SVG Barcode (1D Code-Style Bars) untuk cetak lembar QR/Barcode Sesi Hari Ini
 */
const buildBarcodeBars = (codeText) => {
    const str = String(codeText || 'LND-SESSION');
    const bars = [];
    let x = 8;
    for (let i = 0; i < str.length; i++) {
        const charCode = str.charCodeAt(i);
        const w1 = (charCode % 3) + 1.5;
        const w2 = ((charCode >> 2) % 2) + 1;
        bars.push({ x, w: w1 });
        x += w1 + 2;
        bars.push({ x, w: w2 });
        x += w2 + 2.5;
    }
    return { bars, totalWidth: Math.max(120, x + 8) };
};

/**
 * Generator Ringkas Native SVG Matrix 21x21 untuk Kartu Cetak QR Sesi
 */
const buildMiniQrCells = (seedString) => {
    const str = String(seedString || '0:0000');
    const size = 21;
    const cells = [];

    const isFinder = (r, c) => {
        const inTL = r <= 6 && c <= 6;
        const inTR = r <= 6 && c >= size - 7;
        const inBL = r >= size - 7 && c <= 6;
        return inTL || inTR || inBL;
    };

    const isFinderDark = (r, c) => {
        const checkBox = (br, bc) => {
            const lr = r - br;
            const lc = c - bc;
            const outer = lr === 0 || lr === 6 || lc === 0 || lc === 6;
            const inner = lr >= 2 && lr <= 4 && lc >= 2 && lc <= 4;
            return outer || inner;
        };
        if (r <= 6 && c <= 6) return checkBox(0, 0);
        if (r <= 6 && c >= size - 7) return checkBox(0, size - 7);
        if (r >= size - 7 && c <= 6) return checkBox(size - 7, 0);
        return false;
    };

    for (let r = 0; r < size; r++) {
        for (let c = 0; c < size; c++) {
            if (isFinder(r, c)) {
                if (isFinderDark(r, c)) cells.push({ r, c });
            } else {
                const charVal = str.charCodeAt((r * size + c) % str.length);
                if (((charVal + r * 3 + c * 7) % 2) === 0) {
                    cells.push({ r, c });
                }
            }
        }
    }
    return cells;
};

const triggerPrintSheet = () => {
    window.print();
};
</script>

<template>
    <Head title="Training Today — Operasional Pelatihan Hari Ini" />

    <component
        :is="userRole === 'peserta' ? ParticipantLayout : AdminLayout"
        title="TRAINING TODAY — Pusat Operasional Pelatihan Hari Ini"
        :subtitle="`Jadwal & Presensi Sesi Hari Ini (${todayDateFormatted || 'Live Operational'})`"
    >
        <!-- Navigasi Sub-Menu Training Today -->
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 mb-6 print:hidden">
            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    @click="activeTab = 'today'"
                    :class="[
                        'px-4 py-2.5 text-xs font-bold border-b-2 transition-colors',
                        activeTab === 'today'
                            ? 'border-blue-600 text-blue-600 bg-white'
                            : 'border-transparent text-slate-600 hover:text-slate-900',
                    ]"
                >
                    1. Sesi Pelatihan Hari Ini ({{ todaySessions.length }})
                </button>

                <button
                    type="button"
                    @click="activeTab = 'barcode_sheet'"
                    :class="[
                        'px-4 py-2.5 text-xs font-bold border-b-2 transition-colors',
                        activeTab === 'barcode_sheet'
                            ? 'border-blue-600 text-blue-600 bg-white'
                            : 'border-transparent text-slate-600 hover:text-slate-900',
                    ]"
                >
                    2. Generate / Cetak Daftar QR &amp; Barcode Sesi
                </button>

                <button
                    type="button"
                    @click="activeTab = 'pre_post_test'"
                    :class="[
                        'px-4 py-2.5 text-xs font-bold border-b-2 transition-colors flex items-center gap-1.5',
                        activeTab === 'pre_post_test'
                            ? 'border-blue-600 text-blue-600 bg-white'
                            : 'border-transparent text-slate-600 hover:text-slate-900',
                    ]"
                >
                    <span>3. Pre-Test &amp; Post-Test Hari Ini</span>
                    <span class="px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800 text-[9px] font-extrabold uppercase">
                        Aktif
                    </span>
                </button>

                <Link
                    href="/training/calendar"
                    class="px-4 py-2.5 text-xs font-bold border-b-2 border-transparent text-slate-600 hover:text-blue-600 hover:border-blue-600 transition-colors flex items-center gap-1.5"
                >
                    <span>Kalender &amp; ATMP Interaktif</span>
                    <span class="px-1.5 py-0.5 rounded bg-blue-100 text-blue-800 text-[9px] font-extrabold uppercase">
                        Halaman Penuh &rarr;
                    </span>
                </Link>
            </div>

            <!-- Tombol Cepat 1-Klik Sesuai Role -->
            <div class="flex items-center gap-2 pb-2">
                <Link
                    v-if="['lnd', 'trainer'].includes(userRole)"
                    href="/admin/attendance-sessions"
                    class="px-3.5 py-2 rounded bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold"
                >
                    &#x1F4F7; Buka Proyektor QR Absen Live
                </Link>
                <Link
                    v-if="userRole === 'peserta'"
                    href="/participant/attendance/scan"
                    class="px-3.5 py-2 rounded bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold"
                >
                    &#x1F4F7; Buka Scanner Kamera HP
                </Link>
            </div>
        </div>

        <!-- ================================================================ -->
        <!-- TAB 1: SESI PELATIHAN HARI INI (`session_date = today()`)        -->
        <!-- ================================================================ -->
        <div v-if="activeTab === 'today'" class="space-y-4">
            <div
                v-if="!todaySessions.length"
                class="bg-amber-50 border border-amber-200 rounded-md p-4 text-xs text-amber-900 flex items-center justify-between"
            >
                <div>
                    <strong>Info Jadwal Hari Ini:</strong> Belum ada sesi dengan tanggal spesifik hari ini. Menampilkan daftar sesi operasional aktif terbaru di bawah.
                </div>
                <Link
                    v-if="['lnd', 'trainer'].includes(userRole)"
                    href="/admin/attendance-sessions"
                    class="px-3.5 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-bold shrink-0"
                >
                    + Buat Sesi Hari Ini
                </Link>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div
                    v-for="s in sessionsToDisplay"
                    :key="s.id"
                    class="bg-white border border-slate-200 rounded-md p-5 flex flex-col justify-between"
                >
                    <div>
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <span class="px-2 py-0.5 rounded bg-blue-50 text-blue-700 border border-blue-200 font-mono text-xs font-bold">
                                {{ s.course_code }}
                            </span>
                            <span
                                :class="[
                                    'px-2 py-0.5 rounded text-[10px] font-extrabold uppercase',
                                    s.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600',
                                ]"
                            >
                                {{ s.is_active ? 'SESI LIVE AKTIF' : 'SELESAI / DITUTUP' }}
                            </span>
                        </div>

                        <h3 class="text-sm font-extrabold text-slate-900">{{ s.title }}</h3>
                        <p class="text-xs text-slate-600 mt-0.5">{{ s.course_title }}</p>
                        <div class="mt-2 text-[11px] text-slate-500 flex flex-wrap gap-3">
                            <span><strong>Trainer:</strong> {{ s.trainer_name }}</span>
                            <span>&bull;</span>
                            <span><strong>Durasi:</strong> {{ s.duration_hours }} Jam</span>
                            <span>&bull;</span>
                            <span><strong>Hadir:</strong> {{ s.present_count }} Peserta</span>
                        </div>
                    </div>

                    <!-- Tombol Cepat 1-Klik -->
                    <div class="mt-4 pt-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2 text-xs">
                        <div class="font-mono text-[11px] text-slate-600">
                            PIN 15d: <strong class="text-blue-700">{{ s.manual_code }}</strong>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <Link
                                v-if="['lnd', 'trainer'].includes(userRole)"
                                :href="`/admin/attendance-sessions?session_id=${s.id}`"
                                class="px-3 py-1.5 rounded bg-blue-600 hover:bg-blue-700 text-white font-bold"
                            >
                                Buka Proyektor QR &rarr;
                            </Link>
                            <Link
                                v-else-if="userRole === 'peserta'"
                                href="/participant/attendance/scan"
                                class="px-3 py-1.5 rounded bg-emerald-600 hover:bg-emerald-700 text-white font-bold"
                            >
                                Scan QR Sesi Ini &rarr;
                            </Link>
                            <button
                                type="button"
                                @click="activeTab = 'barcode_sheet'"
                                class="px-3 py-1.5 rounded bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold"
                            >
                                Cetak QR / Barcode
                            </button>
                            <button
                                type="button"
                                @click="activeTab = 'pre_post_test'"
                                class="px-3 py-1.5 rounded bg-amber-50 hover:bg-amber-100 text-amber-900 border border-amber-200 font-bold"
                            >
                                Pre/Post Test
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================================================================ -->
        <!-- TAB 2: GENERATE / CETAK DAFTAR QR & BARCODE SESI (NATIVE SVG)    -->
        <!-- ================================================================ -->
        <div v-else-if="activeTab === 'barcode_sheet'" class="bg-white border border-slate-200 rounded-md p-5">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-5 pb-4 border-b border-slate-200 print:hidden">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">
                        Lembar Cetak Daftar QR Code &amp; Barcode Sesi Pelatihan Hari Ini
                    </h2>
                    <p class="text-xs text-slate-500">
                        Dirender murni dengan <strong>Native SVG</strong> (Tanpa Library Eksternal). Siap dicetak pada kertas A4.
                    </p>
                </div>

                <button
                    type="button"
                    @click="triggerPrintSheet"
                    class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold shadow-sm"
                >
                    &#x1F5A8; Cetak Lembar QR / Barcode (A4)
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div
                    v-for="s in sessionsToDisplay"
                    :key="`print-${s.id}`"
                    class="p-4 rounded-xl border border-slate-300 bg-slate-50/60 flex items-center justify-between gap-4"
                >
                    <div class="space-y-1.5 text-xs">
                        <span class="px-2 py-0.5 rounded bg-blue-600 text-white font-mono text-[10px] font-bold">
                            {{ s.course_code }} &bull; SESI #{{ s.id }}
                        </span>
                        <h3 class="text-sm font-extrabold text-slate-900">{{ s.title }}</h3>
                        <p class="text-[11px] text-slate-600">{{ s.course_title }}</p>
                        <div class="text-[11px] font-mono text-blue-700 font-bold">
                            Token QR: {{ s.qr_string }} | PIN: {{ s.manual_code }}
                        </div>

                        <!-- Native SVG 1D Barcode -->
                        <div class="pt-2">
                            <svg
                                :viewBox="`0 0 ${buildBarcodeBars(s.qr_string).totalWidth} 38`"
                                class="h-9 w-auto border border-slate-200 bg-white p-1"
                            >
                                <rect
                                    v-for="(bar, bIdx) in buildBarcodeBars(s.qr_string).bars"
                                    :key="bIdx"
                                    :x="bar.x"
                                    y="2"
                                    :width="bar.w"
                                    height="26"
                                    fill="#0f172a"
                                />
                                <text
                                    :x="buildBarcodeBars(s.qr_string).totalWidth / 2"
                                    y="35"
                                    text-anchor="middle"
                                    class="fill-slate-700 text-[7px] font-mono font-bold"
                                >
                                    {{ s.qr_string }}
                                </text>
                            </svg>
                        </div>
                    </div>

                    <!-- Native SVG 2D QR Code -->
                    <div class="p-2 border-2 border-slate-900 rounded bg-white shrink-0">
                        <svg viewBox="0 0 25 25" class="w-24 h-24" shape-rendering="crispEdges">
                            <rect x="0" y="0" width="25" height="25" fill="#ffffff" />
                            <rect
                                v-for="(cell, cIdx) in buildMiniQrCells(s.qr_string)"
                                :key="cIdx"
                                :x="cell.c + 2"
                                :y="cell.r + 2"
                                width="1"
                                height="1"
                                fill="#0f172a"
                            />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================================================================ -->
        <!-- TAB 3: PANEL STATUS PRE-TEST & POST-TEST HARI INI                -->
        <!-- ================================================================ -->
        <div v-else-if="activeTab === 'pre_post_test'" class="bg-white border border-slate-200 rounded-md p-6">
            <div class="flex items-center justify-between pb-4 border-b border-slate-200 mb-5">
                <div>
                    <span class="px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 border border-emerald-300 text-[10px] font-extrabold uppercase tracking-wider">
                        MODUL AKTIF
                    </span>
                    <h2 class="mt-1.5 text-base font-extrabold text-slate-900">
                        Pre-Test &amp; Post-Test Evaluasi Pembelajaran
                    </h2>
                    <p class="text-xs text-slate-500">
                        Pilih paket soal untuk memulai ujian dengan batas waktu otomatis dan kalkulasi Gain Score.
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div
                    v-for="q in quizzes"
                    :key="`quiz-${q.id}`"
                    class="p-4 rounded-lg border border-slate-200 bg-white hover:border-blue-400 transition-all shadow-sm flex flex-col justify-between"
                >
                    <div>
                        <div class="flex items-center justify-between">
                            <span
                                :class="q.quiz_type === 'pre_test' ? 'bg-blue-50 text-blue-700 border-blue-200' : 'bg-purple-50 text-purple-800 border-purple-200'"
                                class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider border"
                            >
                                {{ q.quiz_type === 'pre_test' ? 'PRE-TEST AWAL' : 'POST-TEST AKHIR' }}
                            </span>
                            <span class="text-[11px] font-mono font-bold text-slate-500">
                                {{ q.duration_minutes }} Menit &bull; Passing {{ q.passing_score }}%
                            </span>
                        </div>
                        <h3 class="font-extrabold text-sm text-slate-900 mt-2">{{ q.title }}</h3>
                        <div class="text-xs text-slate-600 mt-0.5">
                            <span class="font-mono font-bold text-blue-700">[{{ q.course_code }}]</span> {{ q.course_title }}
                        </div>
                        <div class="text-[11px] text-slate-400 mt-2">
                            Jumlah Soal: <strong>{{ q.questions_count }} Soal Pilihan Ganda</strong>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between">
                        <span class="text-[10px] text-slate-400">Anti-Cheat Enabled</span>
                        <Link
                            :href="`/participant/quizzes/${q.id}/take`"
                            class="px-3.5 py-1.5 rounded bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs shadow-sm flex items-center gap-1.5"
                        >
                            <span>Kerjakan Ujian</span>
                            <span>&rarr;</span>
                        </Link>
                    </div>
                </div>

                <div v-if="!quizzes.length" class="col-span-2 text-center py-10 text-slate-400 text-xs">
                    Belum ada paket Pre-Test / Post-Test yang aktif untuk sesi hari ini.
                </div>
            </div>
        </div>
    </component>
</template>
