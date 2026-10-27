<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    /**
     * Format item:
     * {
     *   id: number|string,
     *   session_title: string,
     *   short_date: string,
     *   course_code: string,
     *   present_count: number,
     *   permit_count: number,
     *   absent_count: number,
     *   present_rate: number
     * }
     */
    items: {
        type: Array,
        default: () => [],
    },
});

const hoveredIndex = ref(null);

// Koordinat ViewBox SVG (Responsif via viewBox="0 0 720 280")
const svgWidth = 720;
const svgHeight = 280;
const padding = { top: 28, right: 24, bottom: 56, left: 46 };
const chartWidth = svgWidth - padding.left - padding.right;
const chartHeight = svgHeight - padding.top - padding.bottom;

const maxVal = computed(() => {
    if (!props.items.length) return 20;
    const highest = Math.max(
        ...props.items.map(
            (i) => Number(i.present_count || 0) + Number(i.permit_count || 0) + Number(i.absent_count || 0)
        )
    );
    return Math.max(10, Math.ceil(highest / 5) * 5);
});

const yTicks = computed(() => {
    const steps = 4;
    const result = [];
    for (let i = 0; i <= steps; i++) {
        const value = Math.round((maxVal.value / steps) * i);
        const y = padding.top + chartHeight - (value / maxVal.value) * chartHeight;
        result.push({ value, y });
    }
    return result;
});

const bars = computed(() => {
    const count = props.items.length;
    if (count === 0) return [];

    const slotWidth = chartWidth / count;
    const barWidth = Math.min(48, Math.max(20, slotWidth * 0.52));

    return props.items.map((item, index) => {
        const present = Number(item.present_count || 0);
        const permit = Number(item.permit_count || 0);
        const absent = Number(item.absent_count || 0);
        const total = present + permit + absent;

        const presentHeight = (present / maxVal.value) * chartHeight;
        const permitHeight = (permit / maxVal.value) * chartHeight;
        const absentHeight = (absent / maxVal.value) * chartHeight;

        const x = padding.left + index * slotWidth + (slotWidth - barWidth) / 2;
        const baseY = padding.top + chartHeight;

        const presentY = baseY - presentHeight;
        const permitY = presentY - permitHeight;
        const absentY = permitY - absentHeight;

        return {
            ...item,
            x,
            centerX: x + barWidth / 2,
            barWidth,
            present,
            permit,
            absent,
            total,
            presentY,
            presentHeight,
            permitY,
            permitHeight,
            absentY,
            absentHeight,
        };
    });
});
</script>

<template>
    <div class="w-full">
        <!-- Legend -->
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3 text-xs text-slate-600">
            <div class="flex items-center gap-4">
                <span class="inline-flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded-sm bg-blue-600 inline-block"></span>
                    Hadir (Present)
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded-sm bg-amber-400 inline-block"></span>
                    Izin (Permit)
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded-sm bg-rose-500 inline-block"></span>
                    Alpa (Absent)
                </span>
            </div>

            <div v-if="hoveredIndex !== null && bars[hoveredIndex]" class="text-xs font-medium text-slate-700 bg-slate-100 px-2.5 py-1 rounded">
                <span class="font-bold">{{ bars[hoveredIndex].course_code }}</span> &bull;
                {{ bars[hoveredIndex].session_title }}:
                <span class="text-blue-700 font-semibold">{{ bars[hoveredIndex].present }} Hadir</span>,
                <span class="text-amber-700 font-semibold">{{ bars[hoveredIndex].permit }} Izin</span>,
                <span class="text-rose-700 font-semibold">{{ bars[hoveredIndex].absent }} Alpa</span>
                ({{ bars[hoveredIndex].present_rate }}%)
            </div>
        </div>

        <!-- Empty State -->
        <div
            v-if="!bars.length"
            class="h-60 flex items-center justify-center border border-dashed border-slate-200 rounded text-xs text-slate-400"
        >
            Belum ada data sesi presensi.
        </div>

        <!-- Native Raw SVG Bar Chart -->
        <svg
            v-else
            :viewBox="`0 0 ${svgWidth} ${svgHeight}`"
            class="w-full h-auto overflow-visible select-none"
            role="img"
            aria-label="Grafik Tren Kehadiran per Sesi Pelatihan"
        >
            <!-- Horizontal Grid Lines & Y-Axis Labels -->
            <g v-for="tick in yTicks" :key="tick.value">
                <line
                    :x1="padding.left"
                    :y1="tick.y"
                    :x2="svgWidth - padding.right"
                    :y2="tick.y"
                    stroke="#e2e8f0"
                    stroke-dasharray="3 3"
                    stroke-width="1"
                />
                <text
                    :x="padding.left - 8"
                    :y="tick.y + 4"
                    text-anchor="end"
                    class="fill-slate-400 text-[11px]"
                >
                    {{ tick.value }}
                </text>
            </g>

            <!-- Base X-Axis Line -->
            <line
                :x1="padding.left"
                :y1="padding.top + chartHeight"
                :x2="svgWidth - padding.right"
                :y2="padding.top + chartHeight"
                stroke="#cbd5e1"
                stroke-width="1.5"
            />

            <!-- Stacked Bars -->
            <g
                v-for="(bar, idx) in bars"
                :key="bar.id || idx"
                class="cursor-pointer transition-opacity"
                :opacity="hoveredIndex !== null && hoveredIndex !== idx ? 0.65 : 1"
                @mouseenter="hoveredIndex = idx"
                @mouseleave="hoveredIndex = null"
            >
                <!-- Present Bar (Blue) -->
                <rect
                    v-if="bar.presentHeight > 0"
                    :x="bar.x"
                    :y="bar.presentY"
                    :width="bar.barWidth"
                    :height="bar.presentHeight"
                    fill="#2563eb"
                    rx="2"
                />

                <!-- Permit Bar (Amber) -->
                <rect
                    v-if="bar.permitHeight > 0"
                    :x="bar.x"
                    :y="bar.permitY"
                    :width="bar.barWidth"
                    :height="bar.permitHeight"
                    fill="#fbbf24"
                    rx="2"
                />

                <!-- Absent Bar (Rose) -->
                <rect
                    v-if="bar.absentHeight > 0"
                    :x="bar.x"
                    :y="bar.absentY"
                    :width="bar.barWidth"
                    :height="bar.absentHeight"
                    fill="#f43f5e"
                    rx="2"
                />

                <!-- Value Label Above Bar -->
                <text
                    :x="bar.centerX"
                    :y="Math.min(bar.presentY, bar.permitY, bar.absentY) - 6"
                    text-anchor="middle"
                    class="fill-slate-700 text-[11px] font-bold"
                >
                    {{ bar.present_rate }}%
                </text>

                <!-- X-Axis Labels (Course Code + Date) -->
                <text
                    :x="bar.centerX"
                    :y="padding.top + chartHeight + 18"
                    text-anchor="middle"
                    class="fill-slate-700 text-[11px] font-semibold"
                >
                    {{ bar.short_date }}
                </text>
                <text
                    :x="bar.centerX"
                    :y="padding.top + chartHeight + 34"
                    text-anchor="middle"
                    class="fill-slate-400 text-[10px]"
                >
                    {{ bar.course_code }}
                </text>
            </g>
        </svg>
    </div>
</template>

