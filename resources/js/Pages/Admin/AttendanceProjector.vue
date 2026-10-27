<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    courses: {
        type: Array,
        default: () => [],
    },
    sessions: {
        type: Array,
        default: () => [],
    },
    activeSession: {
        type: Object,
        default: null,
    },
    dynamicQrPayload: {
        type: Object,
        default: () => ({
            payload: '0:000000000000',
            manual_code: '------',
            time_window: 0,
            rotation_interval: 15,
        }),
    },
    presentParticipants: {
        type: Array,
        default: () => [],
    },
    stats: {
        type: Object,
        default: () => ({
            enrolled_approved: 0,
            present_count: 0,
            permit_count: 0,
            absent_count: 0,
            remaining_count: 0,
            attendance_percentage: 0,
        }),
    },
});

const ROTATION_SECONDS = 15;

const isProjectorFullscreen = ref(false);
const isPollingEnabled = ref(true);
const showCreateModal = ref(false);

// Countdown 15 detik untuk rotasi Stateless QR
const rotationCountdown = ref(ROTATION_SECONDS);
// Countdown total durasi sesi kelas
const sessionRemainingSeconds = ref(0);

const createForm = useForm({
    course_id: props.courses[0]?.id || '',
    title: '',
    duration_minutes: 30,
});

const submitCreateSession = () => {
    createForm.post('/admin/attendance-sessions', {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset('title');
        },
    });
};

const switchSession = (sessionId) => {
    router.get('/admin/attendance-sessions', { session_id: sessionId }, { preserveState: true, preserveScroll: true });
};

const regenerateSecretKey = () => {
    if (!props.activeSession) return;
    router.patch(
        `/admin/attendance-sessions/${props.activeSession.id}/regenerate`,
        { duration_minutes: 30 },
        { preserveScroll: true }
    );
};

const toggleSessionActive = () => {
    if (!props.activeSession) return;
    router.patch(`/admin/attendance-sessions/${props.activeSession.id}/toggle`, {}, { preserveScroll: true });
};

const updateSessionTotalCountdown = () => {
    if (!props.activeSession?.expires_at || !props.activeSession?.is_active) {
        sessionRemainingSeconds.value = 0;
        return;
    }
    const diff = Math.floor((new Date(props.activeSession.expires_at).getTime() - Date.now()) / 1000);
    sessionRemainingSeconds.value = Math.max(0, diff);
};

const formattedSessionCountdown = computed(() => {
    if (!props.activeSession?.is_active) return 'NONAKTIF';
    if (sessionRemainingSeconds.value <= 0) return 'EXPIRED';
    const hrs = Math.floor(sessionRemainingSeconds.value / 3600);
    const mins = Math.floor((sessionRemainingSeconds.value % 3600) / 60);
    const secs = sessionRemainingSeconds.value % 60;
    const pad = (n) => String(n).padStart(2, '0');
    return hrs > 0 ? `${pad(hrs)}:${pad(mins)}:${pad(secs)}` : `${pad(mins)}:${pad(secs)}`;
});

// Koordinat Native SVG Progress Bar 15 Detik (Lebar ViewBox = 300)
const svgBarMaxWidth = 300;
const svgRotationBarWidth = computed(() => {
    const ratio = Math.max(0, Math.min(1, rotationCountdown.value / ROTATION_SECONDS));
    return Number((ratio * svgBarMaxWidth).toFixed(1));
});

// Mekanisme Polling Dual-Cycle:
// - Setiap 3 detik: Partial reload `presentParticipants` & `stats`
// - Setiap 15 detik (saat countdown habis): Partial reload `dynamicQrPayload`, `presentParticipants`, & `stats`
let secondTicker = null;
let liveAttendancePoller = null;

const triggerQrRotationReload = () => {
    if (!isPollingEnabled.value || !props.activeSession?.is_active || document.hidden) {
        rotationCountdown.value = ROTATION_SECONDS;
        return;
    }

    router.reload({
        only: ['dynamicQrPayload', 'presentParticipants', 'stats'],
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            rotationCountdown.value = ROTATION_SECONDS;
        },
    });
};

onMounted(() => {
    updateSessionTotalCountdown();

    // Sinkronkan sisa detik awal dengan boundary 15 detik jam sistem
    const initialRemainder = ROTATION_SECONDS - (Math.floor(Date.now() / 1000) % ROTATION_SECONDS);
    rotationCountdown.value = initialRemainder > 0 ? initialRemainder : ROTATION_SECONDS;

    // Interval 1 detik untuk countdown UI & trigger rotasi QR saat habis (15 detik)
    secondTicker = setInterval(() => {
        updateSessionTotalCountdown();

        if (!props.activeSession?.is_active || sessionRemainingSeconds.value <= 0) {
            rotationCountdown.value = 0;
            return;
        }

        if (rotationCountdown.value <= 1) {
            triggerQrRotationReload();
        } else {
            rotationCountdown.value -= 1;
        }
    }, 1000);

    // Interval 3 detik untuk Partial Reload daftar peserta hadir (`presentParticipants` & `stats`)
    liveAttendancePoller = setInterval(() => {
        if (
            isPollingEnabled.value &&
            props.activeSession?.is_active &&
            sessionRemainingSeconds.value > 0 &&
            rotationCountdown.value > 2 &&
            !document.hidden
        ) {
            router.reload({
                only: ['presentParticipants', 'stats'],
                preserveScroll: true,
                preserveState: true,
            });
        }
    }, 3000);
});

onUnmounted(() => {
    if (secondTicker) clearInterval(secondTicker);
    if (liveAttendancePoller) clearInterval(liveAttendancePoller);
});

/**
 * Generator Native SVG QR Code Standar ISO/IEC 18004 (Version 2-L, 25x25 Modules)
 * Meng-encode `dynamicQrPayload.payload` ("{session_id}:{hmac_signature}") secara real-time.
 */
const qrMatrix = computed(() => {
    const text = props.dynamicQrPayload?.payload || '0:000000000000';
    const bytes = Array.from(new TextEncoder().encode(text)).slice(0, 32);

    const size = 25;
    const matrix = Array.from({ length: size }, () => Array(size).fill(null));
    const reserved = Array.from({ length: size }, () => Array(size).fill(false));

    const placeFinder = (row, col) => {
        for (let r = -1; r <= 7; r++) {
            for (let c = -1; c <= 7; c++) {
                const rr = row + r;
                const cc = col + c;
                if (rr >= 0 && rr < size && cc >= 0 && cc < size) {
                    const inOuter = r >= 0 && r <= 6 && c >= 0 && c <= 6 && (r === 0 || r === 6 || c === 0 || c === 6);
                    const inInner = r >= 2 && r <= 4 && c >= 2 && c <= 4;
                    matrix[rr][cc] = inOuter || inInner ? 1 : 0;
                    reserved[rr][cc] = true;
                }
            }
        }
    };

    placeFinder(0, 0);
    placeFinder(0, size - 7);
    placeFinder(size - 7, 0);

    for (let r = -2; r <= 2; r++) {
        for (let c = -2; c <= 2; c++) {
            const isBorder = Math.abs(r) === 2 || Math.abs(c) === 2;
            const isCenter = r === 0 && c === 0;
            matrix[18 + r][18 + c] = isBorder || isCenter ? 1 : 0;
            reserved[18 + r][18 + c] = true;
        }
    }

    for (let i = 8; i < size - 8; i++) {
        if (!reserved[6][i]) {
            matrix[6][i] = i % 2 === 0 ? 1 : 0;
            reserved[6][i] = true;
        }
        if (!reserved[i][6]) {
            matrix[i][6] = i % 2 === 0 ? 1 : 0;
            reserved[i][6] = true;
        }
    }

    matrix[size - 8][8] = 1;
    reserved[size - 8][8] = true;
    for (let i = 0; i < 9; i++) {
        if (i < size) {
            reserved[8][i] = true;
            reserved[i][8] = true;
        }
    }
    for (let i = 0; i < 8; i++) {
        reserved[8][size - 1 - i] = true;
        reserved[size - 1 - i][8] = true;
    }

    const bits = [];
    const pushBits = (val, len) => {
        for (let i = len - 1; i >= 0; i--) bits.push((val >> i) & 1);
    };

    pushBits(0b0100, 4);
    pushBits(bytes.length, 8);
    bytes.forEach((b) => pushBits(b, 8));
    pushBits(0, Math.min(4, 34 * 8 - bits.length));
    while (bits.length % 8 !== 0) bits.push(0);

    const dataWords = [];
    for (let i = 0; i < bits.length; i += 8) {
        let byte = 0;
        for (let b = 0; b < 8; b++) byte = (byte << 1) | bits[i + b];
        dataWords.push(byte);
    }
    const padBytes = [0xec, 0x11];
    let padIdx = 0;
    while (dataWords.length < 34) {
        dataWords.push(padBytes[padIdx % 2]);
        padIdx++;
    }

    const gfExp = new Uint8Array(512);
    const gfLog = new Uint8Array(256);
    let x = 1;
    for (let i = 0; i < 255; i++) {
        gfExp[i] = x;
        gfLog[x] = i;
        x <<= 1;
        if (x & 0x100) x ^= 0x11d;
    }
    for (let i = 255; i < 512; i++) gfExp[i] = gfExp[i - 255];

    const gfMul = (a, b) => (a === 0 || b === 0 ? 0 : gfExp[gfLog[a] + gfLog[b]]);

    let gen = [1];
    for (let i = 0; i < 10; i++) {
        const next = new Array(gen.length + 1).fill(0);
        for (let j = 0; j < gen.length; j++) {
            next[j] ^= gen[j];
            next[j + 1] ^= gfMul(gen[j], gfExp[i]);
        }
        gen = next;
    }

    const msg = [...dataWords, ...new Array(10).fill(0)];
    for (let i = 0; i < dataWords.length; i++) {
        const coef = msg[i];
        if (coef !== 0) {
            for (let j = 0; j < gen.length; j++) {
                msg[i + j] ^= gfMul(gen[j], coef);
            }
        }
    }
    const ecWords = msg.slice(dataWords.length);
    const allWords = [...dataWords, ...ecWords];

    const finalBits = [];
    allWords.forEach((w) => {
        for (let i = 7; i >= 0; i--) finalBits.push((w >> i) & 1);
    });

    let bitIdx = 0;
    let upward = true;
    for (let col = size - 1; col > 0; col -= 2) {
        if (col === 6) col--;
        for (let step = 0; step < size; step++) {
            const row = upward ? size - 1 - step : step;
            for (let cOffset = 0; cOffset < 2; cOffset++) {
                const cc = col - cOffset;
                if (!reserved[row][cc]) {
                    const bit = bitIdx < finalBits.length ? finalBits[bitIdx++] : 0;
                    const mask = (row + cc) % 2 === 0 ? 1 : 0;
                    matrix[row][cc] = bit ^ mask;
                }
            }
        }
        upward = !upward;
    }

    const formatBits = [1, 1, 1, 0, 1, 1, 1, 1, 1, 0, 0, 0, 1, 0, 0];
    const formatCoords1 = [
        [8, 0], [8, 1], [8, 2], [8, 3], [8, 4], [8, 5], [8, 7], [8, 8],
        [7, 8], [5, 8], [4, 8], [3, 8], [2, 8], [1, 8], [0, 8],
    ];
    const formatCoords2 = [
        [size - 1, 8], [size - 2, 8], [size - 3, 8], [size - 4, 8], [size - 5, 8], [size - 6, 8], [size - 7, 8],
        [8, size - 8], [8, size - 7], [8, size - 6], [8, size - 5], [8, size - 4], [8, size - 3], [8, size - 2], [8, size - 1],
    ];
    for (let i = 0; i < 15; i++) {
        const [r1, c1] = formatCoords1[i];
        const [r2, c2] = formatCoords2[i];
        matrix[r1][c1] = formatBits[i];
        matrix[r2][c2] = formatBits[i];
    }

    return matrix;
});
</script>

<template>
    <Head title="Proyektor Stateless Rotating QR" />

    <component
        :is="isProjectorFullscreen ? 'div' : AdminLayout"
        title="Proyektor Stateless Rotating QR (HMAC-SHA256)"
        subtitle="QR Code & PIN 6-Digit berotasi otomatis setiap 15 detik tanpa beban query UPDATE ke database"
        :class="isProjectorFullscreen ? 'min-h-screen bg-slate-950 text-white p-6' : ''"
    >
        <!-- Top Action Bar -->
        <div
            :class="[
                'rounded-md p-4 mb-6 flex flex-wrap items-center justify-between gap-4 border',
                isProjectorFullscreen
                    ? 'bg-slate-900 border-slate-800 text-white'
                    : 'bg-white border-slate-200 text-slate-800',
            ]"
        >
            <div class="flex flex-wrap items-center gap-3">
                <label class="text-xs font-bold uppercase tracking-wider text-slate-400">Pilih Sesi:</label>
                <select
                    :value="activeSession?.id"
                    @change="switchSession($event.target.value)"
                    :class="[
                        'rounded text-xs py-2 px-3 font-semibold border',
                        isProjectorFullscreen
                            ? 'bg-slate-800 border-slate-700 text-white'
                            : 'bg-slate-50 border-slate-300 text-slate-800',
                    ]"
                >
                    <option v-for="s in sessions" :key="s.id" :value="s.id">
                        [{{ s.course_code }}] {{ s.title }} ({{ s.session_date }}) {{ s.is_active ? '• AKTIF' : '• TUTUP' }}
                    </option>
                </select>

                <button
                    type="button"
                    @click="showCreateModal = true"
                    class="px-3 py-2 rounded bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold"
                >
                    + Buat Sesi QR Baru
                </button>
            </div>

            <div class="flex items-center gap-2.5">
                <button
                    type="button"
                    @click="isPollingEnabled = !isPollingEnabled"
                    :class="[
                        'inline-flex items-center gap-2 px-3 py-1.5 rounded text-xs font-semibold border',
                        isPollingEnabled
                            ? 'bg-emerald-500/10 text-emerald-500 border-emerald-500/30'
                            : 'bg-slate-200 text-slate-600 border-slate-300',
                    ]"
                >
                    <span
                        :class="[
                            'w-2 h-2 rounded-full',
                            isPollingEnabled ? 'bg-emerald-500 animate-ping' : 'bg-slate-400',
                        ]"
                    ></span>
                    <span>{{ isPollingEnabled ? 'Live Sync (3s / 15s)' : 'Polling Dijeda' }}</span>
                </button>

                <button
                    type="button"
                    @click="isProjectorFullscreen = !isProjectorFullscreen"
                    class="px-3.5 py-1.5 rounded bg-slate-800 hover:bg-slate-700 text-white text-xs font-semibold border border-slate-700"
                >
                    {{ isProjectorFullscreen ? 'Keluar Mode Proyektor' : 'Mode Layar Proyektor' }}
                </button>
            </div>
        </div>

        <!-- Main Projector Grid -->
        <div v-if="activeSession" class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Kolom Kiri: Stateless Rotating QR & Native SVG 15s Progress Bar -->
            <div
                :class="[
                    'lg:col-span-5 rounded-md p-6 border flex flex-col items-center text-center',
                    isProjectorFullscreen
                        ? 'bg-slate-900 border-slate-800'
                        : 'bg-white border-slate-200',
                ]"
            >
                <div class="flex items-center gap-2">
                    <span class="px-2.5 py-0.5 rounded text-xs font-bold uppercase tracking-wider bg-blue-600 text-white">
                        {{ activeSession.course_code }}
                    </span>
                    <span class="px-2 py-0.5 rounded text-[10px] font-mono font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                        Window #{{ dynamicQrPayload.time_window }}
                    </span>
                </div>

                <h2
                    :class="[
                        'mt-2 text-lg font-extrabold',
                        isProjectorFullscreen ? 'text-white' : 'text-slate-900',
                    ]"
                >
                    {{ activeSession.title }}
                </h2>
                <p class="text-xs text-slate-400 mt-0.5">{{ activeSession.course_title }}</p>

                <!-- Native SVG QR Code Box -->
                <div class="my-4 p-4 bg-white rounded-lg border-4 border-slate-900 shadow-sm inline-block">
                    <svg
                        viewBox="0 0 29 29"
                        class="w-60 h-60 sm:w-64 sm:h-64"
                        shape-rendering="crispEdges"
                        role="img"
                        :aria-label="`Dynamic QR ${dynamicQrPayload.payload}`"
                    >
                        <rect x="0" y="0" width="29" height="29" fill="#ffffff" />
                        <template v-for="(row, rIdx) in qrMatrix" :key="`r-${rIdx}`">
                            <template v-for="(cell, cIdx) in row" :key="`c-${rIdx}-${cIdx}`">
                                <rect
                                    v-if="cell === 1"
                                    :x="cIdx + 2"
                                    :y="rIdx + 2"
                                    width="1"
                                    height="1"
                                    fill="#0f172a"
                                />
                            </template>
                        </template>
                    </svg>
                </div>

                <!-- Native SVG Progress Bar Hitung Mundur 15 Detik -->
                <div class="w-full max-w-xs mb-4">
                    <div class="flex items-center justify-between text-xs mb-1.5">
                        <span class="font-semibold text-slate-400">Rotasi QR Berikutnya:</span>
                        <span
                            :class="[
                                'font-mono font-extrabold',
                                rotationCountdown <= 4 ? 'text-amber-500' : 'text-blue-500',
                            ]"
                        >
                            {{ rotationCountdown }} detik
                        </span>
                    </div>

                    <svg :viewBox="`0 0 ${svgBarMaxWidth} 10`" class="w-full h-2.5 rounded overflow-hidden">
                        <rect x="0" y="0" :width="svgBarMaxWidth" height="10" fill="#e2e8f0" rx="5" />
                        <rect
                            x="0"
                            y="0"
                            :width="svgRotationBarWidth"
                            height="10"
                            :fill="rotationCountdown <= 4 ? '#f59e0b' : '#2563eb'"
                            rx="5"
                            class="transition-all duration-700 ease-linear"
                        />
                    </svg>
                </div>

                <!-- Kode Manual 6-Digit Dinamis & Payload HMAC -->
                <div class="w-full max-w-xs p-3.5 rounded bg-slate-100 border border-slate-200 text-slate-900">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                        Kode PIN Manual 6-Digit (Berubah Tiap 15d)
                    </div>
                    <div class="text-2xl font-mono font-extrabold tracking-[0.25em] text-blue-700 mt-1 select-all">
                        {{ dynamicQrPayload.manual_code }}
                    </div>
                    <div class="mt-1 text-[10px] font-mono text-slate-400 truncate select-all">
                        Payload: {{ dynamicQrPayload.payload }}
                    </div>
                </div>

                <!-- Sisa Waktu Sesi & Kontrol Sesi -->
                <div class="mt-4 w-full flex items-center justify-between pt-4 border-t border-slate-200/60 text-xs">
                    <div class="text-left">
                        <div class="text-[10px] uppercase text-slate-400 font-bold">Durasi Sesi Kelas</div>
                        <div
                            :class="[
                                'text-base font-mono font-extrabold',
                                sessionRemainingSeconds > 60 ? 'text-emerald-500' : 'text-rose-500',
                            ]"
                        >
                            {{ formattedSessionCountdown }}
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="regenerateSecretKey"
                            class="px-3 py-1.5 rounded bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-200 font-semibold"
                        >
                            Reset Secret
                        </button>
                        <button
                            type="button"
                            @click="toggleSessionActive"
                            :class="[
                                'px-3 py-1.5 rounded font-semibold border',
                                activeSession.is_active
                                    ? 'bg-rose-50 text-rose-700 border-rose-200 hover:bg-rose-100'
                                    : 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100',
                            ]"
                        >
                            {{ activeSession.is_active ? 'Tutup Sesi' : 'Aktifkan' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Kolom Kanan: Statistik Live & Daftar Peserta yang Baru Absen -->
            <div
                :class="[
                    'lg:col-span-7 rounded-md p-6 border flex flex-col',
                    isProjectorFullscreen
                        ? 'bg-slate-900 border-slate-800'
                        : 'bg-white border-slate-200',
                ]"
            >
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
                    <div class="p-3 rounded border border-slate-200/80 bg-slate-50/50">
                        <div class="text-[10px] font-bold uppercase text-slate-400">Terdaftar</div>
                        <div class="text-xl font-extrabold text-slate-800 mt-0.5">{{ stats.enrolled_approved }}</div>
                    </div>
                    <div class="p-3 rounded border border-emerald-200 bg-emerald-50/60">
                        <div class="text-[10px] font-bold uppercase text-emerald-700">Sudah Hadir</div>
                        <div class="text-xl font-extrabold text-emerald-700 mt-0.5">{{ stats.present_count }}</div>
                    </div>
                    <div class="p-3 rounded border border-amber-200 bg-amber-50/60">
                        <div class="text-[10px] font-bold uppercase text-amber-700">Belum Scan</div>
                        <div class="text-xl font-extrabold text-amber-700 mt-0.5">{{ stats.remaining_count }}</div>
                    </div>
                    <div class="p-3 rounded border border-blue-200 bg-blue-50/60">
                        <div class="text-[10px] font-bold uppercase text-blue-700">Persentase</div>
                        <div class="text-xl font-extrabold text-blue-700 mt-0.5">{{ stats.attendance_percentage }}%</div>
                    </div>
                </div>

                <div class="flex items-center justify-between mb-3">
                    <h3
                        :class="[
                            'text-sm font-bold',
                            isProjectorFullscreen ? 'text-white' : 'text-slate-900',
                        ]"
                    >
                        Log Kehadiran Masuk ({{ presentParticipants.length }} Peserta)
                    </h3>
                    <span class="text-[11px] text-slate-400">
                        List hadir di-reload tiap 3d &bull; QR di-rotasi tiap 15d
                    </span>
                </div>

                <div class="flex-1 overflow-y-auto max-h-[420px] border border-slate-200 rounded">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead class="bg-slate-100 text-slate-600 sticky top-0">
                            <tr>
                                <th class="py-2.5 px-3">Waktu Scan</th>
                                <th class="py-2.5 px-3">Nama &amp; NIK Peserta</th>
                                <th class="py-2.5 px-3">Departemen</th>
                                <th class="py-2.5 px-3 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <tr
                                v-for="att in presentParticipants"
                                :key="att.id"
                                :class="isProjectorFullscreen ? 'text-slate-200 hover:bg-slate-800' : 'hover:bg-slate-50'"
                            >
                                <td class="py-2.5 px-3 font-mono font-semibold text-blue-600">
                                    {{ att.scanned_at || '--:--' }}
                                </td>
                                <td class="py-2.5 px-3">
                                    <div class="font-bold">{{ att.name }}</div>
                                    <div class="text-[11px] text-slate-400 font-mono">{{ att.nik }}</div>
                                </td>
                                <td class="py-2.5 px-3 text-slate-500">{{ att.department }}</td>
                                <td class="py-2.5 px-3 text-right">
                                    <span
                                        :class="[
                                            'px-2 py-0.5 rounded text-[10px] font-bold uppercase',
                                            att.status === 'present'
                                                ? 'bg-emerald-100 text-emerald-800'
                                                : att.status === 'permit'
                                                  ? 'bg-amber-100 text-amber-800'
                                                  : 'bg-rose-100 text-rose-800',
                                        ]"
                                    >
                                        {{ att.status }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="!presentParticipants.length">
                                <td colspan="4" class="py-10 text-center text-slate-400">
                                    Menunggu peserta melakukan scan QR Code...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal Buat Sesi QR Baru -->
        <div
            v-if="showCreateModal"
            class="fixed inset-0 z-50 bg-slate-900/50 flex items-center justify-center p-4"
            @click.self="showCreateModal = false"
        >
            <div class="bg-white border border-slate-200 rounded-md w-full max-w-md overflow-hidden shadow-xl text-slate-800">
                <div class="px-5 py-3.5 bg-slate-900 text-white flex items-center justify-between">
                    <h3 class="text-sm font-bold">Buka Sesi Presensi Rotating QR Baru</h3>
                    <button type="button" @click="showCreateModal = false" class="text-slate-400 hover:text-white">&#10005;</button>
                </div>

                <form @submit.prevent="submitCreateSession" class="p-5 space-y-4 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Kelas Pelatihan</label>
                        <select
                            v-model="createForm.course_id"
                            required
                            class="w-full rounded border-slate-300 text-xs py-2 px-2.5"
                        >
                            <option v-for="c in courses" :key="c.id" :value="c.id">
                                {{ c.code }} - {{ c.title }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Judul Sesi Pertemuan</label>
                        <input
                            v-model="createForm.title"
                            type="text"
                            required
                            placeholder="Contoh: Sesi 3 - Evaluasi Praktek Lapangan"
                            class="w-full rounded border-slate-300 text-xs py-2 px-2.5"
                        />
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Durasi Sesi Kelas (Menit)</label>
                        <input
                            v-model.number="createForm.duration_minutes"
                            type="number"
                            min="5"
                            max="480"
                            required
                            class="w-full rounded border-slate-300 text-xs py-2 px-2.5"
                        />
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-slate-200">
                        <button
                            type="button"
                            @click="showCreateModal = false"
                            class="px-3.5 py-2 rounded border border-slate-300 font-semibold text-slate-700"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="createForm.processing"
                            class="px-4 py-2 rounded bg-blue-600 text-white font-semibold hover:bg-blue-700"
                        >
                            Buat &amp; Tampilkan QR
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </component>
</template>
