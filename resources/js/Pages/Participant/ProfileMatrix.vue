<script setup>
import { Head } from '@inertiajs/vue3';
import ParticipantLayout from '@/Layouts/ParticipantLayout.vue';
import SvgRadarChart from '@/Components/Charts/SvgRadarChart.vue';

defineProps({
    skills: {
        type: Array,
        default: () => [],
    },
    summary: {
        type: Object,
        default: () => ({
            total_skills: 0,
            competent_count: 0,
            gap_count: 0,
            avg_current: 0,
            avg_target: 0,
        }),
    },
});
</script>

<template>
    <Head title="Profil & Radar Kompetensi Saya" />

    <ParticipantLayout title="Profil & Radar Kompetensi">
        <!-- 1. Ringkasan Skor Kompetensi Pribadi -->
        <div class="grid grid-cols-3 gap-2.5 mb-4">
            <div class="bg-white border border-slate-200 rounded-md p-3 text-center">
                <div class="text-[10px] font-bold uppercase text-slate-400">Rata-rata</div>
                <div class="text-base font-extrabold text-blue-600 mt-0.5">
                    {{ summary.avg_current }} <span class="text-xs text-slate-400 font-normal">/ {{ summary.avg_target }}</span>
                </div>
            </div>
            <div class="bg-white border border-emerald-200 rounded-md p-3 text-center">
                <div class="text-[10px] font-bold uppercase text-emerald-700">Kompeten</div>
                <div class="text-base font-extrabold text-emerald-700 mt-0.5">{{ summary.competent_count }} Skill</div>
            </div>
            <div class="bg-white border border-rose-200 rounded-md p-3 text-center">
                <div class="text-[10px] font-bold uppercase text-rose-700">Perlu Training</div>
                <div class="text-base font-extrabold text-rose-700 mt-0.5">{{ summary.gap_count }} Skill</div>
            </div>
        </div>

        <!-- 2. Native SVG Radar / Spider Chart -->
        <div class="bg-white border border-slate-200 rounded-md p-4 mb-4">
            <div class="mb-2 text-center">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-900">
                    Peta Radar Kompetensi Pribadi
                </h2>
                <p class="text-[11px] text-slate-500">
                    Visualisasi perbandingan level aktual Anda vs standar target jabatan
                </p>
            </div>

            <SvgRadarChart :skills="skills" />
        </div>

        <!-- 3. Rincian Daftar Kompetensi & Gap -->
        <div class="bg-white border border-slate-200 rounded-md p-4">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-900 mb-3">
                Rincian Matriks Kompetensi Saya
            </h3>

            <div class="space-y-2.5">
                <div
                    v-for="item in skills"
                    :key="item.skill_id"
                    class="p-3 rounded border border-slate-200 bg-slate-50/60"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <span class="text-[10px] font-bold text-blue-600 uppercase">{{ item.code }} &bull; {{ item.category }}</span>
                            <div class="text-xs font-bold text-slate-900 mt-0.5">{{ item.name }}</div>
                        </div>

                        <span
                            :class="[
                                'px-2 py-0.5 rounded text-[10px] font-extrabold shrink-0 border',
                                item.current_level < item.target_level
                                    ? 'bg-rose-50 text-rose-700 border-rose-200'
                                    : 'bg-emerald-50 text-emerald-700 border-emerald-200',
                            ]"
                        >
                            Lv. {{ item.current_level }} / {{ item.target_level }}
                        </span>
                    </div>

                    <!-- Mini Level Dots (1-5) -->
                    <div class="mt-2.5 flex items-center justify-between text-[10px] text-slate-500">
                        <div class="flex items-center gap-1">
                            <span
                                v-for="lvl in [1, 2, 3, 4, 5]"
                                :key="lvl"
                                :class="[
                                    'w-5 h-1.5 rounded-sm',
                                    lvl <= item.current_level
                                        ? item.current_level < item.target_level
                                            ? 'bg-rose-500'
                                            : 'bg-emerald-500'
                                        : lvl <= item.target_level
                                          ? 'bg-amber-300'
                                          : 'bg-slate-200',
                                ]"
                            ></span>
                        </div>
                        <span>Evaluator: {{ item.updated_by_name }}</span>
                    </div>
                </div>
            </div>
        </div>
    </ParticipantLayout>
</template>

