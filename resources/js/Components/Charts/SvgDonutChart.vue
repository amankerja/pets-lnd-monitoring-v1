<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    /**
     * Format segment:
     * [{ label: string, value: number, color: string }]
     */
    segments: {
        type: Array,
        default: () => [],
    },
    centerTitle: {
        type: String,
        default: 'Rata-rata',
    },
    centerValue: {
        type: [String, Number],
        default: '0%',
    },
});

const size = 200;
const strokeWidth = 24;
const center = size / 2;
const radius = center - strokeWidth;
const circumference = 2 * Math.PI * radius;

// State untuk hover interaktif ala Remotion/Framer Motion
const activeSegmentIndex = ref(null);

const total = computed(() => {
    return props.segments.reduce((acc, item) => acc + Number(item.value || 0), 0);
});

const computedSegments = computed(() => {
    if (total.value === 0) return [];

    let cumulativeFraction = 0;

    return props.segments.map((seg, idx) => {
        const val = Number(seg.value || 0);
        const fraction = val / total.value;
        const dashLength = fraction * circumference;
        const dashOffset = circumference * (1 - cumulativeFraction);
        cumulativeFraction += fraction;

        return {
            ...seg,
            index: idx,
            value: val,
            percentage: Math.round(fraction * 100),
            strokeDasharray: `${dashLength} ${circumference - dashLength}`,
            strokeDashoffset: dashOffset,
        };
    });
});

const activeSegment = computed(() => {
    if (activeSegmentIndex.value === null) return null;
    return computedSegments.value[activeSegmentIndex.value] || null;
});
</script>

<template>
    <div class="flex flex-col sm:flex-row items-center gap-6 w-full">
        <!-- Raw SVG Donut Chart dengan Kinetic Fluid Transitions -->
        <div class="relative w-44 h-44 shrink-0 group">
            <svg
                :viewBox="`0 0 ${size} ${size}`"
                class="w-full h-full -rotate-90 overflow-visible transition-transform duration-500 ease-out group-hover:scale-[1.02]"
            >
                <!-- Outer Glow Filter Definitions -->
                <defs>
                    <filter id="segmentGlow" x="-20%" y="-20%" width="140%" height="140%">
                        <feDropShadow dx="0" dy="2" stdDeviation="4" flood-opacity="0.3" />
                    </filter>
                </defs>

                <!-- Background Subtle Hairline Ring -->
                <circle
                    :cx="center"
                    :cy="center"
                    :r="radius"
                    fill="transparent"
                    stroke="#f1f5f9"
                    :stroke-width="strokeWidth"
                />

                <!-- Dynamic Reactive Segments (Remotion-inspired Spring & Fluid Motion) -->
                <circle
                    v-for="(seg, idx) in computedSegments"
                    :key="seg.label"
                    :cx="center"
                    :cy="center"
                    :r="radius"
                    fill="transparent"
                    :stroke="seg.color"
                    :stroke-width="activeSegmentIndex === idx ? 29 : strokeWidth"
                    :stroke-dasharray="seg.strokeDasharray"
                    :stroke-dashoffset="seg.strokeDashoffset"
                    stroke-linecap="butt"
                    class="cursor-pointer transition-all duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] origin-center"
                    :class="[
                        activeSegmentIndex !== null && activeSegmentIndex !== idx
                            ? 'opacity-35'
                            : 'opacity-100',
                    ]"
                    :style="{
                        filter: activeSegmentIndex === idx ? 'url(#segmentGlow)' : 'none',
                    }"
                    @mouseenter="activeSegmentIndex = idx"
                    @mouseleave="activeSegmentIndex = null"
                />
            </svg>

            <!-- Center Dynamic Readout (Morphing Content on Hover) -->
            <div class="absolute inset-0 flex flex-col items-center justify-center text-center pointer-events-none px-3 select-none">
                <template v-if="activeSegment">
                    <span
                        class="text-[9px] font-bold uppercase tracking-wider truncate max-w-[110px] transition-all duration-300"
                        :style="{ color: activeSegment.color }"
                    >
                        {{ activeSegment.label }}
                    </span>
                    <span class="text-2xl font-black text-slate-900 tracking-tight transition-all duration-300">
                        {{ activeSegment.percentage }}%
                    </span>
                    <span class="text-[10px] font-semibold text-slate-500">
                        {{ activeSegment.value }} Orang
                    </span>
                </template>
                <template v-else>
                    <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider transition-all duration-300">
                        {{ centerTitle }}
                    </span>
                    <span class="text-2xl font-black text-slate-900 tracking-tight transition-all duration-300">
                        {{ centerValue }}
                    </span>
                </template>
            </div>
        </div>

        <!-- Legend & Breakdown List with Micro-Bars & Two-Way Hover Highlight -->
        <div class="flex-1 w-full space-y-2">
            <div
                v-for="(seg, idx) in computedSegments"
                :key="seg.label"
                class="p-1.5 rounded-lg transition-all duration-200 cursor-pointer"
                :class="[
                    activeSegmentIndex === idx
                        ? 'bg-slate-100/90 shadow-sm'
                        : activeSegmentIndex !== null
                            ? 'opacity-40'
                            : 'hover:bg-slate-50/80',
                ]"
                @mouseenter="activeSegmentIndex = idx"
                @mouseleave="activeSegmentIndex = null"
            >
                <div class="flex items-center justify-between text-xs">
                    <div class="flex items-center gap-2 min-w-0">
                        <span
                            class="w-2.5 h-2.5 rounded-full shrink-0 transition-transform duration-200"
                            :class="activeSegmentIndex === idx ? 'scale-125' : ''"
                            :style="{ backgroundColor: seg.color }"
                        ></span>
                        <span class="text-slate-700 font-medium truncate text-[11px]">{{ seg.label }}</span>
                    </div>
                    <div class="font-bold text-slate-900 text-xs shrink-0 pl-2">
                        {{ seg.value }} <span class="text-slate-400 font-normal text-[10px]">({{ seg.percentage }}%)</span>
                    </div>
                </div>

                <!-- Micro Progress Track ala High-End Agency Standard -->
                <div class="mt-1 w-full bg-slate-100 h-1 rounded-full overflow-hidden">
                    <div
                        class="h-full rounded-full transition-all duration-500 ease-out"
                        :style="{
                            width: `${seg.percentage}%`,
                            backgroundColor: seg.color,
                        }"
                    ></div>
                </div>
            </div>

            <div v-if="total === 0" class="text-xs text-slate-400 py-2 italic text-center">
                Belum ada data pada filter ini.
            </div>
        </div>
    </div>
</template>
