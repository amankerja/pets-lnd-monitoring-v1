<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    /**
     * Format item:
     * [{ skill_id, code, name, category, current_level (1-5), target_level (1-5), gap }]
     */
    skills: {
        type: Array,
        default: () => [],
    },
    maxLevel: {
        type: Number,
        default: 5,
    },
});

const activeIndex = ref(null);

// Dimensi ViewBox SVG
const size = 340;
const center = size / 2;
const maxRadius = 110;
const levels = [1, 2, 3, 4, 5];

/**
 * Kalkulasi koordinat Kartesian (x, y) dari sudut polar (index ke-i dari total N titik)
 * Sudut dimulai dari -PI/2 (pukul 12.00 tepat di atas)
 */
const getPoint = (index, total, levelValue, radiusScale = maxRadius) => {
    const angle = (Math.PI * 2 * index) / total - Math.PI / 2;
    const r = (levelValue / props.maxLevel) * radiusScale;
    const x = center + r * Math.cos(angle);
    const y = center + r * Math.sin(angle);
    return { x: Number(x.toFixed(2)), y: Number(y.toFixed(2)), angle };
};

// Poligon Grid Konsentris (Level 1 s/d 5)
const gridPolygons = computed(() => {
    const count = props.skills.length;
    if (count < 3) return [];

    return levels.map((lvl) => {
        const points = props.skills
            .map((_, idx) => {
                const pt = getPoint(idx, count, lvl);
                return `${pt.x},${pt.y}`;
            })
            .join(' ');

        return {
            level: lvl,
            points,
            labelY: Number((center - (lvl / props.maxLevel) * maxRadius).toFixed(2)),
        };
    });
});

// Garis Sumbu Radial & Label Sudut
const axes = computed(() => {
    const count = props.skills.length;
    if (count < 3) return [];

    return props.skills.map((skill, idx) => {
        const outerPt = getPoint(idx, count, props.maxLevel);
        const labelPt = getPoint(idx, count, props.maxLevel, maxRadius + 28);

        let textAnchor = 'middle';
        if (labelPt.x < center - 15) textAnchor = 'end';
        else if (labelPt.x > center + 15) textAnchor = 'start';

        return {
            skill,
            index: idx,
            x2: outerPt.x,
            y2: outerPt.y,
            labelX: labelPt.x,
            labelY: labelPt.y,
            textAnchor,
        };
    });
});

// Poligon Target Level (Garis Putus-putus)
const targetPolygonPoints = computed(() => {
    const count = props.skills.length;
    if (count < 3) return '';

    return props.skills
        .map((skill, idx) => {
            const pt = getPoint(idx, count, Number(skill.target_level || 3));
            return `${pt.x},${pt.y}`;
        })
        .join(' ');
});

// Poligon Current Level (Area Berwarna Transparan)
const currentPolygonPoints = computed(() => {
    const count = props.skills.length;
    if (count < 3) return '';

    return props.skills
        .map((skill, idx) => {
            const pt = getPoint(idx, count, Number(skill.current_level || 1));
            return `${pt.x},${pt.y}`;
        })
        .join(' ');
});

// Titik Vertex Interaktif
const vertices = computed(() => {
    const count = props.skills.length;
    if (count < 3) return [];

    return props.skills.map((skill, idx) => {
        const currentPt = getPoint(idx, count, Number(skill.current_level || 1));
        const targetPt = getPoint(idx, count, Number(skill.target_level || 3));

        return {
            ...skill,
            currentX: currentPt.x,
            currentY: currentPt.y,
            targetX: targetPt.x,
            targetY: targetPt.y,
        };
    });
});
</script>

<template>
    <div class="w-full flex flex-col items-center">
        <!-- Legend Radar -->
        <div class="flex items-center justify-center gap-5 text-xs mb-2">
            <div class="flex items-center gap-1.5">
                <span class="w-3.5 h-3.5 rounded-sm bg-blue-600/30 border-2 border-blue-600 inline-block"></span>
                <span class="font-semibold text-slate-700">Current Level (Aktual)</span>
            </div>
            <div class="flex items-center gap-1.5">
                <span class="w-4 h-0.5 border-b-2 border-dashed border-amber-500 inline-block"></span>
                <span class="font-semibold text-slate-700">Target Level (Standar)</span>
            </div>
        </div>

        <div
            v-if="skills.length < 3"
            class="h-56 w-full flex items-center justify-center border border-dashed border-slate-200 rounded text-xs text-slate-400"
        >
            Minimal 3 kompetensi dibutuhkan untuk merender Radar Chart.
        </div>

        <!-- Native SVG Radar / Spider Chart -->
        <svg
            v-else
            :viewBox="`0 0 ${size} ${size}`"
            class="w-full max-w-[340px] h-auto select-none overflow-visible"
            role="img"
            aria-label="Radar Chart Kompetensi Peserta"
        >
            <!-- 1. Concentric Polygon Rings (Level 1 - 5) -->
            <g v-for="grid in gridPolygons" :key="`grid-${grid.level}`">
                <polygon
                    :points="grid.points"
                    :fill="grid.level % 2 === 0 ? '#f8fafc' : 'none'"
                    stroke="#e2e8f0"
                    stroke-width="1"
                />
                <text
                    :x="center + 4"
                    :y="grid.labelY + 9"
                    class="fill-slate-400 text-[9px] font-semibold"
                >
                    {{ grid.level }}
                </text>
            </g>

            <!-- 2. Radial Axis Lines & Skill Code Labels -->
            <g v-for="axis in axes" :key="`axis-${axis.index}`">
                <line
                    :x1="center"
                    :y1="center"
                    :x2="axis.x2"
                    :y2="axis.y2"
                    stroke="#cbd5e1"
                    stroke-width="1"
                />
                <text
                    :x="axis.labelX"
                    :y="axis.labelY"
                    :text-anchor="axis.textAnchor"
                    dominant-baseline="middle"
                    class="fill-slate-700 text-[10px] font-bold cursor-pointer"
                    @mouseenter="activeIndex = axis.index"
                    @mouseleave="activeIndex = null"
                >
                    {{ axis.skill.code }}
                </text>
            </g>

            <!-- 3. Target Level Polygon (Dashed Stroke) -->
            <polygon
                :points="targetPolygonPoints"
                fill="rgba(245, 158, 11, 0.08)"
                stroke="#f59e0b"
                stroke-width="2"
                stroke-dasharray="5 3"
            />

            <!-- 4. Current Level Polygon (Semi-Transparent Fill) -->
            <polygon
                :points="currentPolygonPoints"
                fill="rgba(37, 99, 235, 0.24)"
                stroke="#2563eb"
                stroke-width="2.2"
            />

            <!-- 5. Vertex Dots (Target & Current) -->
            <g v-for="(v, idx) in vertices" :key="`vertex-${v.skill_id}`">
                <!-- Target Dot -->
                <circle
                    :cx="v.targetX"
                    :cy="v.targetY"
                    r="3.2"
                    fill="#ffffff"
                    stroke="#f59e0b"
                    stroke-width="2"
                />
                <!-- Current Dot -->
                <circle
                    :cx="v.currentX"
                    :cy="v.currentY"
                    :r="activeIndex === idx ? 5.5 : 4"
                    :fill="v.current_level < v.target_level ? '#e11d48' : '#2563eb'"
                    stroke="#ffffff"
                    stroke-width="1.5"
                    class="cursor-pointer transition-all"
                    @mouseenter="activeIndex = idx"
                    @mouseleave="activeIndex = null"
                />
            </g>
        </svg>

        <!-- Active Skill Readout Pill -->
        <div
            v-if="activeIndex !== null && vertices[activeIndex]"
            class="mt-1 px-3 py-1.5 rounded bg-slate-900 text-white text-xs text-center"
        >
            <span class="font-bold text-blue-300">{{ vertices[activeIndex].code }}</span>:
            {{ vertices[activeIndex].name }} &mdash;
            Aktual <span class="font-bold">{{ vertices[activeIndex].current_level }}</span> /
            Target <span class="font-bold">{{ vertices[activeIndex].target_level }}</span>
        </div>
    </div>
</template>

