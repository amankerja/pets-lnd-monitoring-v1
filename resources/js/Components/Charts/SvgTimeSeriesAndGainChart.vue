<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    /**
     * Format monthlyTimeSeries:
     * [{ period_key, year, month, label, enrollments_count, teaching_hours, self_reports_approved, self_reports_pending }]
     */
    monthlyTimeSeries: {
        type: Array,
        default: () => [],
    },
    /**
     * Format prePostGainAnalytics:
     * [{ course_id, course_code, course_title, avg_pre_test, avg_post_test, raw_gain, n_gain_score, gain_category }]
     */
    prePostGainAnalytics: {
        type: Array,
        default: () => [],
    },
});

const hoveredMonthIdx = ref(null);
const hoveredGainIdx = ref(null);

// ============================================================================
// 1. NATIVE SVG MULTI-BAR CHART: TREN BULANAN TIME-SERIES (GROUP BY YEAR, MONTH)
// ============================================================================
const tsWidth = 720;
const tsHeight = 260;
const tsPad = { top: 28, right: 24, bottom: 52, left: 44 };
const tsChartW = tsWidth - tsPad.left - tsPad.right;
const tsChartH = tsHeight - tsPad.top - tsPad.bottom;

const tsMaxVal = computed(() => {
    if (!props.monthlyTimeSeries.length) return 20;
    const highest = Math.max(
        ...props.monthlyTimeSeries.map((m) =>
            Math.max(
                Number(m.enrollments_count || 0),
                Number(m.teaching_hours || 0),
                Number(m.self_reports_approved || 0)
            )
        )
    );
    return Math.max(10, Math.ceil(highest / 5) * 5);
});

const tsYTicks = computed(() => {
    const steps = 4;
    const res = [];
    for (let i = 0; i <= steps; i++) {
        const val = Math.round((tsMaxVal.value / steps) * i);
        const y = tsPad.top + tsChartH - (val / tsMaxVal.value) * tsChartH;
        res.push({ val, y });
    }
    return res;
});

const tsBars = computed(() => {
    const count = props.monthlyTimeSeries.length;
    if (!count) return [];

    const slotW = tsChartW / count;
    const groupW = Math.min(66, Math.max(24, slotW * 0.68));
    const singleBarW = Math.floor((groupW - 6) / 3);
    const baseY = tsPad.top + tsChartH;

    return props.monthlyTimeSeries.map((item, idx) => {
        const enrollVal = Number(item.enrollments_count || 0);
        const hoursVal = Number(item.teaching_hours || 0);
        const srVal = Number(item.self_reports_approved || 0);

        const enrollH = (enrollVal / tsMaxVal.value) * tsChartH;
        const hoursH = (hoursVal / tsMaxVal.value) * tsChartH;
        const srH = (srVal / tsMaxVal.value) * tsChartH;

        const groupX = tsPad.left + idx * slotW + (slotW - groupW) / 2;

        return {
            ...item,
            centerX: groupX + groupW / 2,
            singleBarW,
            enrollX: groupX,
            enrollY: baseY - enrollH,
            enrollH,
            hoursX: groupX + singleBarW + 3,
            hoursY: baseY - hoursH,
            hoursH,
            srX: groupX + (singleBarW + 3) * 2,
            srY: baseY - srH,
            srH,
        };
    });
});

// ============================================================================
// 2. NATIVE SVG PAIRED BAR CHART: PRE-TEST VS POST-TEST & GAIN SCORE (0 - 100)
// ============================================================================
const gnWidth = 720;
const gnHeight = 260;
const gnPad = { top: 30, right: 24, bottom: 54, left: 44 };
const gnChartW = gnWidth - gnPad.left - gnPad.right;
const gnChartH = gnHeight - gnPad.top - gnPad.bottom;

const gnYTicks = [0, 25, 50, 75, 100].map((val) => ({
    val,
    y: gnPad.top + gnChartH - (val / 100) * gnChartH,
}));

const gainBars = computed(() => {
    const count = props.prePostGainAnalytics.length;
    if (!count) return [];

    const slotW = gnChartW / count;
    const pairW = Math.min(76, Math.max(32, slotW * 0.56));
    const barW = Math.floor((pairW - 6) / 2);
    const baseY = gnPad.top + gnChartH;

    return props.prePostGainAnalytics.map((item, idx) => {
        const pre = Math.min(100, Math.max(0, Number(item.avg_pre_test || 0)));
        const post = Math.min(100, Math.max(0, Number(item.avg_post_test || 0)));

        const preH = (pre / 100) * gnChartH;
        const postH = (post / 100) * gnChartH;

        const pairX = gnPad.left + idx * slotW + (slotW - pairW) / 2;

        return {
            ...item,
            centerX: pairX + pairW / 2,
            barW,
            preX: pairX,
            preY: baseY - preH,
            preH,
            postX: pairX + barW + 6,
            postY: baseY - postH,
            postH,
        };
    });
});
</script>

<template>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- ================================================================ -->
        <!-- KIRI: ANALITIK TIME-SERIES BULANAN (GROUP BY YEAR, MONTH)        -->
        <!-- ================================================================ -->
        <div class="bg-white border border-slate-200 rounded-md p-5">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">
                        Analitik Time-Series Bulanan (`GROUP BY YEAR, MONTH`)
                    </h2>
                    <p class="text-xs text-slate-500">
                        Tren Partisipasi Pendaftaran, Jam Mengajar Sah Trainer, &amp; Validasi Self-Report
                    </p>
                </div>
                <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-slate-100 text-slate-600">
                    Native SVG
                </span>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2 mb-2 text-xs text-slate-600">
                <div class="flex items-center gap-3.5">
                    <span class="inline-flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-sm bg-blue-600 inline-block"></span>
                        Partisipasi Peserta
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-sm bg-emerald-600 inline-block"></span>
                        Teaching Hours (Jam)
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-sm bg-amber-500 inline-block"></span>
                        Self-Report Approved
                    </span>
                </div>

                <div
                    v-if="hoveredMonthIdx !== null && tsBars[hoveredMonthIdx]"
                    class="text-[11px] font-semibold bg-slate-100 px-2.5 py-1 rounded"
                >
                    <strong>{{ tsBars[hoveredMonthIdx].label }}</strong>:
                    <span class="text-blue-700">{{ tsBars[hoveredMonthIdx].enrollments_count }} Peserta</span> &bull;
                    <span class="text-emerald-700">{{ tsBars[hoveredMonthIdx].teaching_hours }} Jam</span> &bull;
                    <span class="text-amber-700">{{ tsBars[hoveredMonthIdx].self_reports_approved }} Self-Report</span>
                </div>
            </div>

            <div
                v-if="!tsBars.length"
                class="h-56 flex items-center justify-center border border-dashed border-slate-200 rounded text-xs text-slate-400"
            >
                Belum ada data time-series bulanan.
            </div>

            <svg
                v-else
                :viewBox="`0 0 ${tsWidth} ${tsHeight}`"
                class="w-full h-auto select-none overflow-visible"
                role="img"
                aria-label="Grafik Time-Series Bulanan Partisipasi dan Jam Mengajar"
            >
                <g v-for="t in tsYTicks" :key="`ts-y-${t.val}`">
                    <line
                        :x1="tsPad.left"
                        :y1="t.y"
                        :x2="tsWidth - tsPad.right"
                        :y2="t.y"
                        stroke="#e2e8f0"
                        stroke-dasharray="3 3"
                    />
                    <text :x="tsPad.left - 8" :y="t.y + 4" text-anchor="end" class="fill-slate-400 text-[11px]">
                        {{ t.val }}
                    </text>
                </g>

                <line
                    :x1="tsPad.left"
                    :y1="tsPad.top + tsChartH"
                    :x2="tsWidth - tsPad.right"
                    :y2="tsPad.top + tsChartH"
                    stroke="#cbd5e1"
                    stroke-width="1.5"
                />

                <g
                    v-for="(b, idx) in tsBars"
                    :key="b.period_key || idx"
                    class="cursor-pointer transition-opacity"
                    :opacity="hoveredMonthIdx !== null && hoveredMonthIdx !== idx ? 0.6 : 1"
                    @mouseenter="hoveredMonthIdx = idx"
                    @mouseleave="hoveredMonthIdx = null"
                >
                    <!-- Bar 1: Partisipasi Pendaftaran (Blue) -->
                    <rect
                        :x="b.enrollX"
                        :y="b.enrollY"
                        :width="b.singleBarW"
                        :height="Math.max(2, b.enrollH)"
                        fill="#2563eb"
                        rx="2"
                    />
                    <!-- Bar 2: Teaching Hours Sah (Emerald) -->
                    <rect
                        :x="b.hoursX"
                        :y="b.hoursY"
                        :width="b.singleBarW"
                        :height="Math.max(2, b.hoursH)"
                        fill="#059669"
                        rx="2"
                    />
                    <!-- Bar 3: Self-Report Approved (Amber) -->
                    <rect
                        :x="b.srX"
                        :y="b.srY"
                        :width="b.singleBarW"
                        :height="Math.max(2, b.srH)"
                        fill="#f59e0b"
                        rx="2"
                    />

                    <text
                        :x="b.centerX"
                        :y="tsPad.top + tsChartH + 18"
                        text-anchor="middle"
                        class="fill-slate-700 text-[11px] font-bold"
                    >
                        {{ b.label }}
                    </text>
                    <text
                        :x="b.centerX"
                        :y="tsPad.top + tsChartH + 33"
                        text-anchor="middle"
                        class="fill-slate-400 text-[10px]"
                    >
                        {{ b.teaching_hours }}j &bull; {{ b.enrollments_count }}p
                    </text>
                </g>
            </svg>
        </div>

        <!-- ================================================================ -->
        <!-- KANAN: PERBANDINGAN PRE-TEST VS POST-TEST & GAIN SCORE           -->
        <!-- ================================================================ -->
        <div class="bg-white border border-slate-200 rounded-md p-5">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">
                        Perbandingan Nilai Pre-Test vs Post-Test (Gain Score)
                    </h2>
                    <p class="text-xs text-slate-500">
                        Evaluasi peningkatan skor peserta sebelum &amp; sesudah pelatihan (Hake's Normalized Gain)
                    </p>
                </div>
                <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200">
                    Gain Analytics
                </span>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2 mb-2 text-xs text-slate-600">
                <div class="flex items-center gap-4">
                    <span class="inline-flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-sm bg-slate-400 inline-block"></span>
                        Rata-rata Pre-Test
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-sm bg-blue-600 inline-block"></span>
                        Rata-rata Post-Test
                    </span>
                </div>

                <div
                    v-if="hoveredGainIdx !== null && gainBars[hoveredGainIdx]"
                    class="text-[11px] font-semibold bg-slate-100 px-2.5 py-1 rounded"
                >
                    <strong>{{ gainBars[hoveredGainIdx].course_code }}</strong>:
                    Pre <span class="text-slate-700">{{ gainBars[hoveredGainIdx].avg_pre_test }}</span> &rarr;
                    Post <span class="text-blue-700 font-bold">{{ gainBars[hoveredGainIdx].avg_post_test }}</span>
                    (<span class="text-emerald-700 font-extrabold">+{{ gainBars[hoveredGainIdx].raw_gain }} pts | N-Gain {{ gainBars[hoveredGainIdx].n_gain_score }}</span>)
                </div>
            </div>

            <div
                v-if="!gainBars.length"
                class="h-56 flex items-center justify-center border border-dashed border-slate-200 rounded text-xs text-slate-400"
            >
                Belum ada data ujian Pre-Test / Post-Test yang dinilai.
            </div>

            <svg
                v-else
                :viewBox="`0 0 ${gnWidth} ${gnHeight}`"
                class="w-full h-auto select-none overflow-visible"
                role="img"
                aria-label="Grafik Perbandingan Pre-Test dan Post-Test"
            >
                <g v-for="t in gnYTicks" :key="`gn-y-${t.val}`">
                    <line
                        :x1="gnPad.left"
                        :y1="t.y"
                        :x2="gnWidth - gnPad.right"
                        :y2="t.y"
                        stroke="#e2e8f0"
                        stroke-dasharray="3 3"
                    />
                    <text :x="gnPad.left - 8" :y="t.y + 4" text-anchor="end" class="fill-slate-400 text-[11px]">
                        {{ t.val }}
                    </text>
                </g>

                <line
                    :x1="gnPad.left"
                    :y1="gnPad.top + gnChartH"
                    :x2="gnWidth - gnPad.right"
                    :y2="gnPad.top + gnChartH"
                    stroke="#cbd5e1"
                    stroke-width="1.5"
                />

                <g
                    v-for="(g, idx) in gainBars"
                    :key="g.course_id || idx"
                    class="cursor-pointer transition-opacity"
                    :opacity="hoveredGainIdx !== null && hoveredGainIdx !== idx ? 0.6 : 1"
                    @mouseenter="hoveredGainIdx = idx"
                    @mouseleave="hoveredGainIdx = null"
                >
                    <!-- Pre-Test Bar (Slate) -->
                    <rect
                        :x="g.preX"
                        :y="g.preY"
                        :width="g.barW"
                        :height="Math.max(2, g.preH)"
                        fill="#94a3b8"
                        rx="2"
                    />
                    <!-- Post-Test Bar (Blue) -->
                    <rect
                        :x="g.postX"
                        :y="g.postY"
                        :width="g.barW"
                        :height="Math.max(2, g.postH)"
                        fill="#2563eb"
                        rx="2"
                    />

                    <!-- Gain Score Pill Above Post-Test Bar -->
                    <text
                        :x="g.centerX"
                        :y="Math.min(g.preY, g.postY) - 7"
                        text-anchor="middle"
                        class="fill-emerald-700 text-[11px] font-extrabold"
                    >
                        +{{ g.raw_gain }} (g={{ g.n_gain_score }})
                    </text>

                    <text
                        :x="g.centerX"
                        :y="gnPad.top + gnChartH + 18"
                        text-anchor="middle"
                        class="fill-slate-800 text-[11px] font-bold"
                    >
                        {{ g.course_code }}
                    </text>
                    <text
                        :x="g.centerX"
                        :y="gnPad.top + gnChartH + 33"
                        text-anchor="middle"
                        class="fill-slate-500 text-[10px]"
                    >
                        {{ g.avg_pre_test }} &rarr; {{ g.avg_post_test }}
                    </text>
                </g>
            </svg>
        </div>
    </div>
</template>

