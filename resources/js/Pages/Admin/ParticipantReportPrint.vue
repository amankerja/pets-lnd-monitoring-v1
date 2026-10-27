<script setup>
import { computed, onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import SvgRadarChart from '@/Components/Charts/SvgRadarChart.vue';

const props = defineProps({
    participant: {
        type: Object,
        required: true,
    },
    skills: {
        type: Array,
        default: () => [],
    },
    enrollments: {
        type: Array,
        default: () => [],
    },
    summary: {
        type: Object,
        required: true,
    },
    documentMeta: {
        type: Object,
        required: true,
    },
});

const triggerBrowserPrint = () => {
    window.print();
};

onMounted(() => {
    // Beri jeda 350ms agar seluruh poligon Native SVG Radar & QR Code selesai dirender sebelum dialog print muncul
    setTimeout(() => {
        window.print();
    }, 350);
});

/**
 * Generator Native SVG QR Code Standar ISO/IEC 18004 (Version 2-L, 25x25 Modules)
 * Meng-encode `documentMeta.verification_payload` untuk verifikasi keaslian dokumen di footer A4.
 */
const verificationQrMatrix = computed(() => {
    const text = props.documentMeta?.verification_url || props.documentMeta?.verification_payload || 'VERIFY:PANI-LND';
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
    <Head :title="`Rapor Kompetensi - ${participant.name}`" />

    <!-- Wrapper Layar & Print A4 -->
    <div
        class="min-h-screen bg-slate-200 py-8 px-4 print:bg-white print:p-0 print:m-0 [-webkit-print-color-adjust:exact] [print-color-adjust:exact]"
    >
        <!-- Floating Action Bar (Disembunyikan saat Print) -->
        <div
            class="fixed bottom-6 right-6 z-50 flex items-center gap-2.5 print:hidden bg-slate-900/95 text-white p-2.5 rounded-lg shadow-xl border border-slate-700"
        >
            <Link
                href="/admin/skill-matrix"
                class="px-3.5 py-2 rounded bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-slate-200 transition-colors"
            >
                &larr; Kembali ke Matrix
            </Link>
            <button
                type="button"
                @click="triggerBrowserPrint"
                class="px-4 py-2 rounded bg-blue-600 hover:bg-blue-500 text-xs font-bold text-white flex items-center gap-2 transition-colors shadow"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"
                    />
                </svg>
                <span>Cetak / Simpan PDF (A4)</span>
            </button>
        </div>

        <!-- Kanvas Kertas A4 Presisi (210mm x 297mm) -->
        <div
            class="mx-auto bg-white text-slate-900 w-full max-w-[210mm] min-h-[297mm] p-8 sm:p-10 shadow-lg border border-slate-300 print:shadow-none print:border-0 print:w-[210mm] print:min-h-[285mm] print:p-6 print-portrait flex flex-col justify-between"
        >
            <div>
                <!-- 1. BAGIAN ATAS: Header Rapor & Sertifikat Resmi + Identitas Peserta -->
                <div class="border-b-2 border-slate-900 pb-4 mb-5 flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3.5">
                        <div
                            class="w-12 h-12 rounded bg-slate-900 text-white flex items-center justify-center font-black text-base tracking-tight shrink-0"
                        >
                            PANI
                        </div>
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-[0.2em] text-blue-700">
                                Learning &amp; Development Division
                            </div>
                            <h1 class="text-lg font-black uppercase tracking-tight text-slate-900 leading-snug">
                                Sertifikat &amp; Rapor Evaluasi Kompetensi Karyawan
                            </h1>
                            <div class="text-[11px] text-slate-500 font-medium">
                                Nomor Dokumen: <span class="font-mono font-bold text-slate-800">{{ documentMeta.certificate_number }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="text-right shrink-0">
                        <span
                            :class="[
                                'inline-block px-3 py-1 rounded text-xs font-extrabold uppercase tracking-wider border',
                                summary.gap_count === 0
                                    ? 'bg-emerald-50 text-emerald-800 border-emerald-300'
                                    : 'bg-amber-50 text-amber-800 border-amber-300',
                            ]"
                        >
                            {{ summary.gap_count === 0 ? 'KOMPETEN & LULUS' : 'DALAM PENGEMBANGAN' }}
                        </span>
                        <div class="text-[10px] text-slate-400 mt-1">Tanggal Terbit: {{ documentMeta.issued_date }}</div>
                    </div>
                </div>

                <!-- Grid Identitas Peserta & Ringkasan Kehadiran -->
                <div class="grid grid-cols-12 gap-3 bg-slate-50 border border-slate-200 rounded p-3.5 mb-5 text-xs">
                    <div class="col-span-5 border-r border-slate-200 pr-3">
                        <div class="text-[10px] font-bold uppercase text-slate-400">Nama Lengkap Peserta</div>
                        <div class="text-sm font-extrabold text-slate-900 mt-0.5">{{ participant.name }}</div>
                        <div class="text-[11px] text-slate-600 mt-1">
                            NIK: <span class="font-mono font-bold text-slate-900">{{ participant.nik }}</span>
                        </div>
                    </div>

                    <div class="col-span-4 border-r border-slate-200 px-2">
                        <div class="text-[10px] font-bold uppercase text-slate-400">Departemen / Unit Kerja</div>
                        <div class="text-xs font-bold text-slate-900 mt-0.5">{{ participant.department }}</div>
                        <div class="text-[11px] text-slate-600 mt-1">
                            Progres Modul: <span class="font-bold text-blue-700">{{ summary.avg_module_progress }}%</span>
                        </div>
                    </div>

                    <div class="col-span-3 pl-2 flex flex-col justify-center">
                        <div class="text-[10px] font-bold uppercase text-slate-400">Tingkat Kehadiran</div>
                        <div class="text-base font-black text-emerald-700 mt-0.5">
                            {{ summary.attendance_percentage }}%
                        </div>
                        <div class="text-[10px] text-slate-500">
                            ({{ summary.present_sessions }} / {{ summary.total_sessions }} Sesi Hadir)
                        </div>
                    </div>
                </div>

                <!-- 2. BAGIAN TENGAH: Kiri = Native SVG Radar Chart | Kanan = Tabel Nilai Kompetensi & Gap Analysis -->
                <div class="grid grid-cols-12 gap-5 items-start mb-5">
                    <!-- Tengah Kiri: Native SVG Radar Chart -->
                    <div class="col-span-5 border border-slate-200 rounded p-3.5 bg-white flex flex-col items-center">
                        <div class="w-full text-left border-b border-slate-100 pb-2 mb-2">
                            <h2 class="text-xs font-extrabold uppercase tracking-wider text-slate-800">
                                Peta Radar Kompetensi
                            </h2>
                            <p class="text-[10px] text-slate-500">Perbandingan Kurva Aktual vs Standar Target</p>
                        </div>

                        <SvgRadarChart :skills="skills" />

                        <div class="w-full mt-2 pt-2 border-t border-slate-100 flex items-center justify-between text-[11px]">
                            <span class="text-slate-500">Rata-rata Skor Kompetensi:</span>
                            <span class="font-extrabold text-slate-900">
                                {{ summary.avg_current }} <span class="text-slate-400 font-normal">/ {{ summary.avg_target }}</span>
                            </span>
                        </div>
                    </div>

                    <!-- Tengah Kanan: Tabel Rincian Nilai Kompetensi & Status Gap Analysis -->
                    <div class="col-span-7 border border-slate-200 rounded overflow-hidden">
                        <div class="bg-slate-900 text-white px-3.5 py-2.5 flex items-center justify-between">
                            <h2 class="text-xs font-extrabold uppercase tracking-wider">
                                Rincian Evaluasi Skill &amp; Gap Analysis
                            </h2>
                            <span class="text-[10px] text-slate-300 font-semibold">
                                Kompeten: {{ summary.competent_count }} / {{ summary.total_skills }} Skill
                            </span>
                        </div>

                        <table class="w-full border-collapse text-[11px]">
                            <thead>
                                <tr class="bg-slate-100 text-slate-700 border-b border-slate-200 font-bold">
                                    <th class="py-2 px-2.5 text-left">Kode &amp; Kompetensi</th>
                                    <th class="py-2 px-2 text-center">Target</th>
                                    <th class="py-2 px-2 text-center">Current</th>
                                    <th class="py-2 px-2 text-center">Gap</th>
                                    <th class="py-2 px-2.5 text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                <tr v-for="skill in skills" :key="skill.skill_id">
                                    <td class="py-2 px-2.5">
                                        <div class="font-bold text-slate-900 leading-tight">{{ skill.name }}</div>
                                        <div class="text-[10px] text-slate-500 font-mono">
                                            {{ skill.code }} &bull; {{ skill.category }}
                                        </div>
                                    </td>
                                    <td class="py-2 px-2 text-center font-semibold text-slate-700">
                                        {{ skill.target_level }}
                                    </td>
                                    <td class="py-2 px-2 text-center font-extrabold text-blue-700">
                                        {{ skill.current_level }}
                                    </td>
                                    <td class="py-2 px-2 text-center font-mono font-bold">
                                        <span :class="skill.gap < 0 ? 'text-rose-600' : 'text-emerald-700'">
                                            {{ skill.gap > 0 ? `+${skill.gap}` : skill.gap }}
                                        </span>
                                    </td>
                                    <td class="py-2 px-2.5 text-right">
                                        <span
                                            :class="[
                                                'inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase border',
                                                skill.current_level < skill.target_level
                                                    ? 'bg-rose-50 text-rose-700 border-rose-200'
                                                    : 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                            ]"
                                        >
                                            {{ skill.current_level < skill.target_level ? 'Gap Minus' : 'Kompeten' }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Ringkasan Program Pelatihan yang Diikuti -->
                <div class="border border-slate-200 rounded overflow-hidden mb-5">
                    <div class="bg-slate-100 px-3.5 py-2 border-b border-slate-200 text-xs font-extrabold uppercase tracking-wider text-slate-700">
                        Riwayat Program Pelatihan yang Diikuti
                    </div>
                    <table class="w-full border-collapse text-[11px]">
                        <thead>
                            <tr class="border-b border-slate-200 text-slate-500 font-semibold bg-slate-50">
                                <th class="py-1.5 px-3 text-left">Kode &amp; Nama Program</th>
                                <th class="py-1.5 px-3 text-left">Instruktur / Trainer</th>
                                <th class="py-1.5 px-3 text-left">Periode Pelaksanaan</th>
                                <th class="py-1.5 px-3 text-right">Penyelesaian Modul</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <tr v-for="enr in enrollments" :key="enr.id">
                                <td class="py-2 px-3">
                                    <span class="font-mono font-bold text-blue-700">{{ enr.course_code }}</span> &mdash;
                                    <span class="font-bold text-slate-900">{{ enr.course_title }}</span>
                                </td>
                                <td class="py-2 px-3 text-slate-700">{{ enr.trainer_name }}</td>
                                <td class="py-2 px-3 text-slate-500">{{ enr.period }}</td>
                                <td class="py-2 px-3 text-right font-extrabold text-slate-900">
                                    {{ enr.progress_percentage }}%
                                </td>
                            </tr>
                            <tr v-if="!enrollments.length">
                                <td colspan="4" class="py-3 text-center text-slate-400">
                                    Belum ada riwayat kelas pelatihan aktif.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 3. BAGIAN BAWAH: Area Tanda Tangan Resmi & QR Code Verifikasi Keaslian Dokumen -->
            <div class="pt-4 border-t-2 border-slate-900 grid grid-cols-12 gap-4 items-end text-xs">
                <!-- Kolom Tanda Tangan Trainer / Assessor -->
                <div class="col-span-4 text-center">
                    <div class="text-[11px] text-slate-500 font-medium">Dievaluasi Oleh,</div>
                    <div class="text-[10px] font-bold uppercase text-slate-400">Lead Trainer / Assessor</div>
                    <div class="h-16"></div>
                    <div class="font-extrabold text-slate-900 underline decoration-slate-400 underline-offset-4">
                        {{ documentMeta.trainer_name }}
                    </div>
                    <div class="text-[10px] text-slate-500 mt-0.5">Technical Instructor L&amp;D</div>
                </div>

                <!-- Kolom QR Code Verifikasi Keaslian Dokumen (Tengah) -->
                <div class="col-span-4 flex flex-col items-center justify-center text-center">
                    <div class="p-1.5 border border-slate-300 rounded bg-white inline-block">
                        <svg
                            viewBox="0 0 29 29"
                            class="w-20 h-20"
                            shape-rendering="crispEdges"
                            role="img"
                            :aria-label="`Verification QR ${documentMeta.verification_payload}`"
                        >
                            <rect x="0" y="0" width="29" height="29" fill="#ffffff" />
                            <template v-for="(row, rIdx) in verificationQrMatrix" :key="`vr-${rIdx}`">
                                <template v-for="(cell, cIdx) in row" :key="`vc-${rIdx}-${cIdx}`">
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
                    <div class="text-[9px] font-mono font-bold text-slate-800 mt-1">
                        NO: {{ documentMeta.certificate_number }}
                    </div>
                    <div class="text-[8px] font-mono text-slate-500">
                        HASH: {{ documentMeta.verification_hash }}
                    </div>
                    <div class="text-[9px] text-blue-700 font-semibold mt-0.5">
                        Scan QR / Buka /verify-certificate
                    </div>
                </div>

                <!-- Kolom Tanda Tangan Manajer L&D -->
                <div class="col-span-4 text-center">
                    <div class="text-[11px] text-slate-500 font-medium">Disahkan Oleh,</div>
                    <div class="text-[10px] font-bold uppercase text-slate-400">Head of Learning &amp; Development</div>
                    <div class="h-16"></div>
                    <div class="font-extrabold text-slate-900 underline decoration-slate-400 underline-offset-4">
                        {{ documentMeta.manager_name }}
                    </div>
                    <div class="text-[10px] text-slate-500 mt-0.5">Human Capital &amp; LND Department</div>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
@page {
    size: A4 portrait;
    margin: 10mm;
}
</style>

