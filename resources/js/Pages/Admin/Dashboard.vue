<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import SvgDonutChart from '@/Components/Charts/SvgDonutChart.vue';
import GlobalTableFilterBar from '@/Components/GlobalTableFilterBar.vue';

const props = defineProps({
    activeTab: {
        type: String,
        default: 'overview',
    },
    currentYear: {
        type: Number,
        default: () => new Date().getFullYear(),
    },
    donutMetrics: {
        type: Object,
        default: () => ({
            overall: [],
            internal: [],
            external: [],
        }),
    },
    cumulativeTrend: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({
            site: '',
            job_level: '',
            department_id: null,
            company_id: null,
            training_category: '',
            mandatory_type: '',
            tab: 'overview',
        }),
    },
    filterOptions: {
        type: Object,
        default: () => ({
            sites: [],
            jobLevels: [],
            departments: [],
            companies: [],
            categories: [],
        }),
    },
    metrics: {
        type: Object,
        default: () => ({
            participationTrend: [],
            trainerHoursTrend: [],
            selfReportTrend: [],
            topSkillGaps: [],
            monthlyTrainingVolume: [],
            manpowerCompetencyPerOrg: [],
            upcomingSchedules: [],
            complianceKpi: {},
            donutData: {},
            deptRankings: [],
            safetyInternalGap: [],
            externalMandatoryGap: [],
            nonMandatoryGap: [],
            overallDonutData: {},
            overallCompletionRate: 0,
            overallCumulativeTrend: [],
        }),
    },
});

const currentTab = ref(props.activeTab || 'overview');

const dashboardFilterParams = computed(() => ({ tab: currentTab.value }));

const switchTab = (tabName) => {
    currentTab.value = tabName;
    router.get('/dashboard', {
        ...props.filters,
        tab: tabName,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

// Helper Label Bulan Singkat
const monthsLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];

// Max value untuk grafik Tab 1
const maxParticipation = computed(() => Math.max(...(props.metrics?.participationTrend || [10]), 10));
const maxVolume = computed(() => {
    const list = (props.metrics?.monthlyTrainingVolume || []).map(i => i.participants);
    return Math.max(...list, 10);
});

// KPI Card data untuk Tab 2 (Mandatory) & Tab 3 (Non-Mandatory)
const kpi = computed(() => props.metrics?.complianceKpi || {});
const nmKpi = computed(() => props.metrics?.nonMandatoryKpi || {});

const displayDonutMetrics = computed(() => {
    if (props.donutMetrics && (props.donutMetrics.overall?.length || props.donutMetrics.internal?.length || props.donutMetrics.external?.length)) {
        return props.donutMetrics;
    }
    return props.metrics?.overallDonutData || { overall: [], internal: [], external: [] };
});

const displayCumulativeTrend = computed(() => {
    if (props.cumulativeTrend && props.cumulativeTrend.length > 0) {
        return props.cumulativeTrend.map(item => ({
            month: item.month,
            percentage: Number(item.cumulative_pct ?? item.percentage ?? 0),
        }));
    }
    if (props.metrics?.overallCumulativeTrend && props.metrics.overallCumulativeTrend.length > 0) {
        return props.metrics.overallCumulativeTrend.map(item => ({
            month: item.month,
            percentage: Number(item.percentage ?? item.cumulative_pct ?? 0),
        }));
    }
    return [];
});

const overallDonutCharts = [
    { key: 'overall', title: 'Overall Training Completion', center: 'Overall', note: 'Kepatuhan seluruh pelatihan wajib dan non-mandatory.' },
    { key: 'internal', title: 'Internal Training Completion', center: 'Internal', note: 'Penyelesaian seluruh pelatihan internal.' },
    { key: 'external', title: 'External Training Completion', center: 'External', note: 'Penyelesaian seluruh pelatihan eksternal.' },
];

const overallDonutRate = (key) => {
    const segments = displayDonutMetrics.value?.[key] || [];
    if (!segments || segments.length === 0) {
        if (key === 'overall' && props.metrics?.overallCompletionRate != null) {
            return props.metrics.overallCompletionRate;
        }
        return 0;
    }
    const total = segments.reduce((sum, segment) => sum + Number(segment.value || 0), 0);
    if (total === 0) return 0;
    const completedSeg = segments.find(s => s.label.toLowerCase().includes('completed') && !s.label.toLowerCase().includes('not'));
    const completedVal = completedSeg ? Number(completedSeg.value || 0) : Number(segments[0]?.value || 0);
    return Math.round((completedVal / total) * 1000) / 10;
};

const latestCumulativeRate = computed(() => {
    if (displayCumulativeTrend.value.length > 0) {
        return displayCumulativeTrend.value[displayCumulativeTrend.value.length - 1].percentage;
    }
    return props.metrics?.overallCompletionRate ?? 0;
});
</script>

<template>
    <Head title="Dashboard Analitik Pelatihan &amp; Kepatuhan MDKA" />

    <AdminLayout
        title="Dashboard &amp; Overview Analitik LnD"
        subtitle="Sistem Pelacakan Kepatuhan Pelatihan, Matriks Gap, dan Sertifikasi Karyawan (MDKA Group Standard)"
    >
        <div class="space-y-6">

            <!-- ============================================================== -->
            <!-- 5 SUB-TAB MODE DASHBOARD (ALA EXCEL MDKA GROUP)                 -->
            <!-- ============================================================== -->
            <div class="border-b border-slate-200">
                <nav class="flex flex-wrap gap-2 -mb-px">
                    <button
                        type="button"
                        @click="switchTab('overview')"
                        :class="[
                            currentTab === 'overview'
                                ? 'border-slate-900 text-slate-900 font-bold bg-white shadow-sm'
                                : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300',
                            'px-4 py-2.5 text-xs rounded-t-lg border-b-2 transition flex items-center gap-1.5'
                        ]"
                    >
                        <span>1. Overview &amp; Analitik Utama</span>
                    </button>

                    <button
                        type="button"
                        @click="switchTab('mandatory')"
                        :class="[
                            currentTab === 'mandatory' || currentTab === 'compliance'
                                ? 'border-slate-900 text-slate-900 font-bold bg-white shadow-sm'
                                : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300',
                            'px-4 py-2.5 text-xs rounded-t-lg border-b-2 transition flex items-center gap-1.5'
                        ]"
                    >
                        <span>2. Dashboard Mandatory (Compliance Overview)</span>
                        <span class="bg-emerald-100 text-emerald-800 text-[10px] font-extrabold px-1.5 py-0.2 rounded-full">3 Donut</span>
                    </button>

                    <button
                        type="button"
                        @click="switchTab('non_mandatory')"
                        :class="[
                            currentTab === 'non_mandatory'
                                ? 'border-slate-900 text-slate-900 font-bold bg-white shadow-sm'
                                : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300',
                            'px-4 py-2.5 text-xs rounded-t-lg border-b-2 transition flex items-center gap-1.5'
                        ]"
                    >
                        <span>3. Dashboard Non-Mandatory</span>
                        <span class="bg-amber-100 text-amber-800 text-[10px] font-extrabold px-1.5 py-0.2 rounded-full">3 Donut</span>
                    </button>

                    <button
                        type="button"
                        @click="switchTab('safety_internal')"
                        :class="[
                            currentTab === 'safety_internal'
                                ? 'border-slate-900 text-slate-900 font-bold bg-white shadow-sm'
                                : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300',
                            'px-4 py-2.5 text-xs rounded-t-lg border-b-2 transition flex items-center gap-1.5'
                        ]"
                    >
                        <span>4. Internal &amp; Safety Mandatory Gap</span>
                    </button>

                    <button
                        type="button"
                        @click="switchTab('external_mandatory')"
                        :class="[
                            currentTab === 'external_mandatory'
                                ? 'border-slate-900 text-slate-900 font-bold bg-white shadow-sm'
                                : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300',
                            'px-4 py-2.5 text-xs rounded-t-lg border-b-2 transition flex items-center gap-1.5'
                        ]"
                    >
                        <span>5. External Mandatory (Lisensi BNSP / KEMNAKER / ESDM)</span>
                    </button>
                </nav>
            </div>

            <GlobalTableFilterBar
                v-if="currentTab !== 'overview'"
                filter-url="/dashboard"
                :filters="filters"
                :filter-options="filterOptions"
                :departments="filterOptions.departments"
                :companies="filterOptions.companies"
                :sites="filterOptions.sites"
                :job-levels="filterOptions.jobLevels"
                :show-category-filter="true"
                :show-date-filter="false"
                :extra-params="dashboardFilterParams"
                search-placeholder="Cari NIK, nama karyawan, kode, atau judul training..."
            />

            <!-- ============================================================== -->
            <!-- TAB 1: OVERVIEW & ANALITIK UTAMA                               -->
            <!-- ============================================================== -->
            <div v-show="currentTab === 'overview'" class="space-y-6">
                <section class="space-y-4" aria-label="Overall training completion summary">
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                        <article
                            v-for="chart in overallDonutCharts"
                            :key="chart.key"
                            class="flex flex-col justify-between rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5"
                        >
                            <div>
                                <h2 class="border-b border-slate-100 pb-2 text-xs font-bold uppercase tracking-wider text-slate-900">
                                    {{ chart.title }}
                                </h2>
                                <div class="flex justify-center py-3">
                                    <SvgDonutChart
                                        :segments="displayDonutMetrics[chart.key] || []"
                                        :center-title="chart.center"
                                        :center-value="`${overallDonutRate(chart.key)}%`"
                                    />
                                </div>
                            </div>
                            <p class="border-t border-slate-100 pt-2.5 text-center text-[11px] text-slate-500">
                                {{ chart.note }}
                            </p>
                        </article>
                    </div>

                    <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                        <div class="mb-3 flex flex-col gap-2 border-b border-slate-100 pb-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-900">
                                    Training Completion Trend ({{ currentYear }}, Cumulative %)
                                </h2>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    Penyelesaian aktif kumulatif per bulan pada cakupan filter saat ini.
                                </p>
                            </div>
                            <span class="w-fit rounded border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[10px] font-bold uppercase text-emerald-800">
                                Overall · {{ latestCumulativeRate }}%
                            </span>
                        </div>
                        <div class="overflow-x-auto">
                            <svg
                                viewBox="0 0 850 220"
                                class="h-auto min-w-[700px] w-full overflow-visible select-none"
                                role="img"
                                :aria-label="`Tren kumulatif penyelesaian training sepanjang ${currentYear}`"
                            >
                                <defs>
                                    <linearGradient id="completionAreaGrad" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#059669" stop-opacity="0.22" />
                                        <stop offset="100%" stop-color="#059669" stop-opacity="0.0" />
                                    </linearGradient>
                                </defs>
                                <g v-for="percentage in [0, 25, 50, 75, 100]" :key="`completion-grid-${percentage}`">
                                    <line
                                        x1="48"
                                        :y1="176 - percentage * 1.25"
                                        x2="812"
                                        :y2="176 - percentage * 1.25"
                                        stroke="#f1f5f9"
                                        stroke-dasharray="3 4"
                                    />
                                    <text x="40" :y="180 - percentage * 1.25" text-anchor="end" class="fill-slate-400 text-[10px] font-mono">
                                        {{ percentage }}%
                                    </text>
                                </g>

                                <!-- Area Fill di bawah Line Chart (Visual Depth ala Remotion) -->
                                <polygon
                                    v-if="displayCumulativeTrend.length > 0"
                                    :points="`60,176 ${displayCumulativeTrend.map((item, index) => `${60 + index * 66},${176 - item.percentage * 1.25}`).join(' ')} ${60 + (displayCumulativeTrend.length - 1) * 66},176`"
                                    fill="url(#completionAreaGrad)"
                                />

                                <!-- Smooth Stroke Line -->
                                <path
                                    v-if="displayCumulativeTrend.length > 0"
                                    :d="displayCumulativeTrend.map((item, index) => `${index === 0 ? 'M' : 'L'} ${60 + index * 66} ${176 - item.percentage * 1.25}`).join(' ')"
                                    fill="none"
                                    stroke="#059669"
                                    stroke-width="3"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    class="transition-all duration-700 ease-out"
                                />

                                <!-- Interactive Data Points -->
                                <g v-for="(item, index) in displayCumulativeTrend" :key="`overall-completion-${item.month}`" class="group/point cursor-pointer">
                                    <circle
                                        :cx="60 + index * 66"
                                        :cy="176 - item.percentage * 1.25"
                                        r="4"
                                        fill="white"
                                        stroke="#059669"
                                        stroke-width="2.5"
                                        class="transition-all duration-300 ease-out group-hover/point:r-6 group-hover/point:stroke-emerald-500"
                                    >
                                        <title>{{ item.month }}: {{ item.percentage }}%</title>
                                    </circle>
                                    <text
                                        :x="60 + index * 66"
                                        :y="166 - item.percentage * 1.25"
                                        text-anchor="middle"
                                        class="fill-emerald-800 text-[9px] font-extrabold font-mono transition-transform duration-200 group-hover/point:-translate-y-1"
                                    >
                                        {{ item.percentage }}%
                                    </text>
                                    <text
                                        :x="60 + index * 66"
                                        y="202"
                                        text-anchor="middle"
                                        class="fill-slate-600 text-[10px] font-bold group-hover/point:fill-emerald-700"
                                    >
                                        {{ item.month }}
                                    </text>
                                </g>
                            </svg>
                        </div>
                    </article>
                </section>

                <!-- 1. Tren Partisipasi, Teaching Hours, & Self-Report -->
                <div class="bg-white border border-slate-200 rounded-xl p-4 sm:p-5 shadow-sm">
                    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 mb-4 border-b border-slate-100 pb-3">
                        <div>
                            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-900">
                                1. Tren Partisipasi Pendaftaran, Teaching Hours, &amp; Self-Report (Tahun {{ currentYear }})
                            </h2>
                            <p class="text-xs text-slate-500 mt-0.5">Akumulasi bulanan aktivitas operasional pelatihan korporat.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-3 text-xs font-bold">
                            <span class="flex items-center gap-1.5"><span class="w-3 h-3 bg-slate-900 rounded-xs inline-block"></span> Pendaftaran Resmi</span>
                            <span class="flex items-center gap-1.5"><span class="w-3 h-3 bg-emerald-600 rounded-xs inline-block"></span> Teaching Hours</span>
                            <span class="flex items-center gap-1.5"><span class="w-3 h-3 bg-amber-500 rounded-xs inline-block"></span> Self-Report Approved</span>
                        </div>
                    </div>

                    <div class="h-44 w-full flex items-end justify-between gap-2 pt-6 px-2 border-b border-slate-200">
                        <div v-for="(mLabel, idx) in monthsLabels" :key="mLabel" class="flex-1 flex flex-col items-center gap-1 h-full justify-end group cursor-pointer">
                            <div class="w-full flex items-end justify-center gap-1.5 h-full">
                                <div
                                    :style="{ height: `${((metrics.participationTrend?.[idx] || 0) / maxParticipation) * 100}%` }"
                                    class="w-2.5 bg-blue-600 transition-all duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] group-hover:bg-blue-500 group-hover:scale-y-105 origin-bottom relative rounded-t-sm shadow-sm"
                                    :title="`Pendaftaran: ${metrics.participationTrend?.[idx] || 0} Orang`"
                                ></div>
                                <div
                                    :style="{ height: `${((metrics.trainerHoursTrend?.[idx] || 0) / 150) * 100}%` }"
                                    class="w-2.5 bg-emerald-600 transition-all duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] group-hover:bg-emerald-500 group-hover:scale-y-105 origin-bottom relative rounded-t-sm shadow-sm"
                                    :title="`Teaching Hours: ${metrics.trainerHoursTrend?.[idx] || 0} Jam`"
                                ></div>
                                <div
                                    :style="{ height: `${((metrics.selfReportTrend?.[idx] || 0) / maxParticipation) * 100}%` }"
                                    class="w-2.5 bg-amber-500 transition-all duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] group-hover:bg-amber-400 group-hover:scale-y-105 origin-bottom relative rounded-t-sm shadow-sm"
                                    :title="`Self-Report Approved: ${metrics.selfReportTrend?.[idx] || 0}`"
                                ></div>
                            </div>
                            <span class="text-[10px] font-mono text-slate-500 mt-2 group-hover:text-slate-900 group-hover:font-bold transition-colors">{{ mLabel }}</span>
                        </div>
                    </div>
                </div>

                <!-- 2. Top 12 Skill Gap Terbesar -->
                <div class="bg-white border border-slate-200 rounded-xl p-4 sm:p-5 shadow-sm">
                    <div class="border-b border-slate-100 pb-3 mb-4">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-900">
                            2. Top 12 Skill Gap Terbesar Kompetensi (Defisit Terbanyak)
                        </h2>
                        <p class="text-xs text-slate-500 mt-0.5">Kompetensi yang paling banyak memerlukan intervensi pelatihan segera (Gap Minus &amp; Expired).</p>
                    </div>

                    <div v-if="metrics.topSkillGaps && metrics.topSkillGaps.length > 0" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                        <div
                            v-for="gap in metrics.topSkillGaps"
                            :key="gap.id"
                            class="p-3 rounded-lg border border-rose-200 bg-rose-50/50 flex flex-col justify-between hover:bg-rose-50 transition"
                        >
                            <div>
                                <span class="font-mono text-[10px] font-bold text-rose-700 bg-rose-100 px-1.5 py-0.5 rounded">
                                    {{ gap.code }}
                                </span>
                                <h3 class="text-xs font-bold text-slate-900 mt-1.5 line-clamp-2">
                                    {{ gap.name }}
                                </h3>
                            </div>
                            <div class="mt-3 flex items-baseline justify-between border-t border-rose-100 pt-2">
                                <span class="text-[10px] text-slate-500">Defisit Pekerja</span>
                                <span class="font-mono text-sm font-extrabold text-rose-600">
                                    {{ gap.total_deficits }} Orang
                                </span>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-6 text-slate-400 text-xs">
                        Tidak ada defisit kompetensi yang terdeteksi pada filter saat ini.
                    </div>
                </div>

                <!-- 3 & 4: Tren Volume Bulanan & Manpower Terkompetensi per Organisasi -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-white border border-slate-200 rounded-xl p-4 sm:p-5 shadow-sm">
                        <div class="border-b border-slate-100 pb-3 mb-4">
                            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-900">
                                3. Tren Pelatihan Time-Series Bulanan
                            </h2>
                            <p class="text-xs text-slate-500 mt-0.5">Jumlah sesi kelas dibuka vs total peserta hadir per bulan.</p>
                        </div>
                        <div class="h-44 w-full flex items-end justify-between gap-2 pt-6 px-2 border-b border-slate-200">
                            <div
                                v-for="vol in metrics.monthlyTrainingVolume"
                                :key="vol.month"
                                class="flex-1 flex flex-col items-center gap-1 h-full justify-end group"
                            >
                                <div class="w-full flex items-end justify-center gap-1 h-full">
                                    <div
                                        :style="{ height: `${(vol.courses_count / 15) * 100}%` }"
                                        class="w-2.5 bg-slate-400 group-hover:bg-slate-500 transition-all rounded-t-sm"
                                        :title="`Jumlah Kelas: ${vol.courses_count}`"
                                    ></div>
                                    <div
                                        :style="{ height: `${(vol.participants / maxVolume) * 100}%` }"
                                        class="w-2.5 bg-emerald-600 group-hover:bg-emerald-700 transition-all rounded-t-sm"
                                        :title="`Peserta: ${vol.participants}`"
                                    ></div>
                                </div>
                                <span class="text-[10px] font-mono text-slate-500 mt-2">{{ vol.month }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white border border-slate-200 rounded-xl p-4 sm:p-5 shadow-sm">
                        <div class="border-b border-slate-100 pb-3 mb-4">
                            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-900">
                                4. Rasio Manpower Terkompetensi Penuh
                            </h2>
                            <p class="text-xs text-slate-500 mt-0.5">Persentase karyawan dengan seluruh skill target tercapai &amp; sertifikat valid.</p>
                        </div>
                        <div class="space-y-3">
                            <div v-for="org in metrics.manpowerCompetencyPerOrg" :key="org.org_name" class="space-y-1">
                                <div class="flex justify-between items-center text-xs">
                                    <span class="font-bold text-slate-800">{{ org.org_name }}</span>
                                    <span class="font-mono text-slate-600 text-[11px]">
                                        {{ org.fully_competent_count }} / {{ org.total_manpower }} Orang ({{ org.competency_percentage }}%)
                                    </span>
                                </div>
                                <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                                    <div
                                        class="h-full bg-slate-900 rounded-full transition-all"
                                        :style="{ width: `${org.competency_percentage}%` }"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 5. Jadwal Pelatihan Terdekat -->
                <div class="bg-white border border-slate-200 rounded-xl p-4 sm:p-5 shadow-sm">
                    <div class="flex justify-between items-center border-b border-slate-100 pb-3 mb-4">
                        <div>
                            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-900">
                                5. Jadwal Pelatihan Terdekat (Upcoming Schedules)
                            </h2>
                            <p class="text-xs text-slate-500 mt-0.5">Agenda kelas pelatihan yang akan segera dimulai dalam waktu dekat.</p>
                        </div>
                        <Link href="/training/calendar" class="text-xs font-bold text-slate-900 hover:underline">
                            Lihat Kalender Lengkap &rarr;
                        </Link>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        <div
                            v-for="sch in metrics.upcomingSchedules"
                            :key="sch.id"
                            class="bg-slate-50 border border-slate-200 rounded-lg p-3.5 flex flex-col justify-between hover:bg-slate-100/70 transition"
                        >
                            <div>
                                <div class="flex justify-between items-start gap-2 mb-1.5">
                                    <span class="font-mono text-[10px] font-bold bg-slate-900 text-white px-1.5 py-0.5 rounded">
                                        {{ sch.code }}
                                    </span>
                                    <span
                                        class="text-[10px] font-bold px-1.5 py-0.5 rounded uppercase"
                                        :class="sch.is_mandatory ? 'bg-rose-100 text-rose-700' : 'bg-slate-200 text-slate-700'"
                                    >
                                        {{ sch.is_mandatory ? 'Mandatory' : 'Non-Mandatory' }}
                                    </span>
                                </div>
                                <h3 class="text-xs font-bold text-slate-900">{{ sch.title }}</h3>
                                <p class="text-[11px] text-slate-500 mt-1">Pemateri: {{ sch.trainer_name }}</p>
                            </div>
                            <Link
                                :href="sch.calendar_url || '/training/calendar'"
                                class="mt-3 pt-2 border-t border-slate-200/60 flex justify-between items-center text-[10px] font-mono text-slate-600 hover:text-blue-700 transition"
                            >
                                <span>{{ sch.category }}</span>
                                <span class="font-bold text-slate-900 flex items-center gap-1">
                                    <span>{{ sch.start_date }}</span>
                                    <span>&rarr;</span>
                                </span>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================================== -->
            <!-- SUB-TAB 2: DASHBOARD MANDATORY (COMPLIANCE OVERVIEW)           -->
            <!-- ============================================================== -->
            <div v-show="currentTab === 'mandatory' || currentTab === 'compliance'" class="space-y-6">
                <!-- 6 KPI CARDS MANDATORY -->
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Total Employees</div>
                        <div class="text-2xl font-black text-slate-900 mt-1">{{ kpi.totalEmployees ?? 0 }}</div>
                        <div class="text-[10px] text-slate-400 mt-0.5">Manpower Terdaftar</div>
                    </div>
                    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Mandatory Types</div>
                        <div class="text-2xl font-black text-slate-900 mt-1">{{ kpi.totalTrainingTypes ?? 0 }}</div>
                        <div class="text-[10px] text-slate-400 mt-0.5">Katalog Wajib (M)</div>
                    </div>
                    <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 shadow-sm">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-emerald-700">Completed (Done)</div>
                        <div class="text-2xl font-black text-emerald-700 mt-1">{{ kpi.totalCompleted ?? 0 }}</div>
                        <div class="text-[10px] text-emerald-600 font-semibold mt-0.5">Lulus &amp; Sertifikat Aktif</div>
                    </div>
                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 shadow-sm">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-amber-700">Not Yet Completed</div>
                        <div class="text-2xl font-black text-amber-700 mt-1">{{ kpi.totalNotYet ?? 0 }}</div>
                        <div class="text-[10px] text-amber-600 font-semibold mt-0.5">Belum Mengikuti (Gap)</div>
                    </div>
                    <div class="bg-rose-50 border border-rose-200 rounded-xl p-4 shadow-sm">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-rose-700">Expired</div>
                        <div class="text-2xl font-black text-rose-700 mt-1">{{ kpi.totalExpired ?? 0 }}</div>
                        <div class="text-[10px] text-rose-600 font-semibold mt-0.5">Masa Berlaku Habis</div>
                    </div>
                    <div class="bg-slate-900 text-white rounded-xl p-4 shadow-sm">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-300">100% Complete</div>
                        <div class="text-2xl font-black text-emerald-400 mt-1">{{ kpi.employees100Complete ?? 0 }}</div>
                        <div class="text-[10px] text-slate-300 mt-0.5">Karyawan Sempurna (M)</div>
                    </div>
                </div>

                <!-- 3 DONUT CHARTS MANDATORY BERDAMPINGAN -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Donut 1: Overall Mandatory Completion -->
                    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm flex flex-col justify-between">
                        <div>
                            <div class="text-xs font-bold uppercase tracking-wider text-slate-900 border-b border-slate-100 pb-2 mb-3">
                                (1) Overall Mandatory Completion %
                            </div>
                            <div class="flex justify-center py-2">
                                <SvgDonutChart
                                    :segments="metrics.donutData?.overall || []"
                                    center-title="Overall"
                                    :center-value="`${kpi.overallCompletionRate ?? 0}%`"
                                />
                            </div>
                        </div>
                        <div class="text-center text-[11px] text-slate-500 border-t border-slate-100 pt-2.5 mt-2">
                            Total Kepatuhan Mandatory (Internal + Eksternal)
                        </div>
                    </div>

                    <!-- Donut 2: Internal Mandatory Completion -->
                    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm flex flex-col justify-between">
                        <div>
                            <div class="text-xs font-bold uppercase tracking-wider text-slate-900 border-b border-slate-100 pb-2 mb-3">
                                (2) Internal Mandatory %
                            </div>
                            <div class="flex justify-center py-2">
                                <SvgDonutChart
                                    :segments="metrics.donutData?.internal || []"
                                    center-title="Internal"
                                    :center-value="`${kpi.internalCompletionRate ?? 0}%`"
                                />
                            </div>
                        </div>
                        <div class="text-center text-[11px] text-slate-500 border-t border-slate-100 pt-2.5 mt-2">
                            Kepatuhan Pelatihan Wajib Internal Perusahaan
                        </div>
                    </div>

                    <!-- Donut 3: External Mandatory Completion -->
                    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm flex flex-col justify-between">
                        <div>
                            <div class="text-xs font-bold uppercase tracking-wider text-slate-900 border-b border-slate-100 pb-2 mb-3">
                                (3) External Mandatory %
                            </div>
                            <div class="flex justify-center py-2">
                                <SvgDonutChart
                                    :segments="metrics.donutData?.external || []"
                                    center-title="External"
                                    :center-value="`${kpi.externalCompletionRate ?? 0}%`"
                                />
                            </div>
                        </div>
                        <div class="text-center text-[11px] text-slate-500 border-t border-slate-100 pt-2.5 mt-2">
                            Kepatuhan Sertifikasi Wajib Eksternal (BNSP/ESDM)
                        </div>
                    </div>
                </div>

                <!-- GRAFIK CUMULATIVE COMPLETION % TREND (JAN - DES) -->
                <div class="bg-white border border-slate-200 rounded-xl p-4 sm:p-5 shadow-sm">
                    <div class="flex justify-between items-center border-b border-slate-100 pb-3 mb-4">
                        <div>
                            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-900">
                                Cumulative Mandatory Completion % Trend (Jan &ndash; Des {{ currentYear }})
                            </h2>
                            <p class="text-xs text-slate-500 mt-0.5">Rasio progres kumulatif penyelesaian pelatihan Mandatory sepanjang tahun.</p>
                        </div>
                        <span class="text-xs font-mono font-bold text-emerald-600 bg-emerald-50 px-2.5 py-1 rounded-md border border-emerald-200">
                            Current: {{ kpi.overallCompletionRate ?? 0 }}%
                        </span>
                    </div>

                    <div class="h-36 w-full flex items-end justify-between gap-2 pt-4 px-2 border-b border-slate-200">
                        <div
                            v-for="item in (metrics.cumulativeTrend || [])"
                            :key="item.month"
                            class="flex-1 flex flex-col items-center gap-1 h-full justify-end group"
                        >
                            <span class="text-[9px] font-mono font-bold text-emerald-700 opacity-0 group-hover:opacity-100 transition">
                                {{ item.percentage }}%
                            </span>
                            <div class="w-full flex items-end justify-center h-full">
                                <div
                                    :style="{ height: `${Math.max(4, item.percentage)}%` }"
                                    class="w-full max-w-[28px] bg-gradient-to-t from-emerald-600 to-emerald-400 group-hover:from-emerald-700 group-hover:to-emerald-500 transition-all rounded-t-sm"
                                    :title="`${item.month}: ${item.percentage}% Selesai`"
                                ></div>
                            </div>
                            <span class="text-[10px] font-mono text-slate-500 mt-2">{{ item.month }}</span>
                        </div>
                    </div>
                </div>

                <!-- TABEL RANKING DEPARTEMEN MANDATORY -->
                <div class="bg-white border border-slate-200 rounded-xl p-4 sm:p-5 shadow-sm">
                    <div class="flex justify-between items-center border-b border-slate-100 pb-3 mb-4">
                        <div>
                            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-900">
                                Department Compliance Ranking (Mandatory)
                            </h2>
                            <p class="text-xs text-slate-500 mt-0.5">Peringkat kepatuhan mandatory diurutkan berdasarkan persentase kelulusan tertinggi.</p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold uppercase tracking-wider text-slate-600">
                                    <th class="p-2.5">Departemen</th>
                                    <th class="p-2.5 text-center">Tipe</th>
                                    <th class="p-2.5 text-center">Jumlah Karyawan</th>
                                    <th class="p-2.5 text-center">Rata-rata Belum/Emp</th>
                                    <th class="p-2.5 text-center">Wajib Ikut</th>
                                    <th class="p-2.5 text-center text-emerald-700">Sudah Selesai</th>
                                    <th class="p-2.5 text-center text-amber-700">Belum Selesai</th>
                                    <th class="p-2.5 text-center text-rose-700">Expired</th>
                                    <th class="p-2.5 text-right">% Selesai</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="dept in metrics.deptRankings" :key="dept.name" class="hover:bg-slate-50 transition">
                                    <td class="p-2.5 font-bold text-slate-900">{{ dept.name }}</td>
                                    <td class="p-2.5 text-center">
                                        <span
                                            class="text-[10px] font-bold px-1.5 py-0.5 rounded uppercase"
                                            :class="dept.type === 'internal_dept' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-800'"
                                        >
                                            {{ dept.type === 'internal_dept' ? 'Internal' : 'Kontraktor' }}
                                        </span>
                                    </td>
                                    <td class="p-2.5 text-center font-mono">{{ dept.total_employees }}</td>
                                    <td class="p-2.5 text-center font-mono font-semibold">{{ dept.avg_not_yet_per_emp }}</td>
                                    <td class="p-2.5 text-center font-mono font-bold">{{ dept.required }}</td>
                                    <td class="p-2.5 text-center font-mono text-emerald-600 font-bold">{{ dept.completed }}</td>
                                    <td class="p-2.5 text-center font-mono text-amber-600 font-bold">{{ dept.not_yet }}</td>
                                    <td class="p-2.5 text-center font-mono text-rose-600 font-bold">{{ dept.expired }}</td>
                                    <td class="p-2.5 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <div class="w-16 h-2 bg-slate-100 rounded-full overflow-hidden">
                                                <div
                                                    class="h-full rounded-full transition-all"
                                                    :class="dept.completion_pct >= 80 ? 'bg-emerald-500' : (dept.completion_pct >= 50 ? 'bg-amber-500' : 'bg-rose-500')"
                                                    :style="{ width: `${dept.completion_pct}%` }"
                                                ></div>
                                            </div>
                                            <span class="font-mono font-bold text-slate-900 w-10 text-right">{{ dept.completion_pct }}%</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ============================================================== -->
            <!-- SUB-TAB 3: DASHBOARD NON-MANDATORY                             -->
            <!-- ============================================================== -->
            <div v-show="currentTab === 'non_mandatory'" class="space-y-6">
                <!-- 6 KPI CARDS NON-MANDATORY -->
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Total Employees</div>
                        <div class="text-2xl font-black text-slate-900 mt-1">{{ nmKpi.totalEmployees ?? 0 }}</div>
                        <div class="text-[10px] text-slate-400 mt-0.5">Manpower Terdaftar</div>
                    </div>
                    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Non-Mandatory Types</div>
                        <div class="text-2xl font-black text-slate-900 mt-1">{{ nmKpi.totalTrainingTypes ?? 0 }}</div>
                        <div class="text-[10px] text-slate-400 mt-0.5">Katalog Opsional (NM)</div>
                    </div>
                    <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 shadow-sm">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-emerald-700">Completed (Done)</div>
                        <div class="text-2xl font-black text-emerald-700 mt-1">{{ nmKpi.totalCompleted ?? 0 }}</div>
                        <div class="text-[10px] text-emerald-600 font-semibold mt-0.5">Lulus &amp; Sertifikat Aktif</div>
                    </div>
                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 shadow-sm">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-amber-700">Not Yet Completed</div>
                        <div class="text-2xl font-black text-amber-700 mt-1">{{ nmKpi.totalNotYet ?? 0 }}</div>
                        <div class="text-[10px] text-amber-600 font-semibold mt-0.5">Belum Diambil (NM)</div>
                    </div>
                    <div class="bg-rose-50 border border-rose-200 rounded-xl p-4 shadow-sm">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-rose-700">Expired</div>
                        <div class="text-2xl font-black text-rose-700 mt-1">{{ nmKpi.totalExpired ?? 0 }}</div>
                        <div class="text-[10px] text-rose-600 font-semibold mt-0.5">Masa Berlaku Habis</div>
                    </div>
                    <div class="bg-slate-900 text-white rounded-xl p-4 shadow-sm">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-300">100% Complete</div>
                        <div class="text-2xl font-black text-emerald-400 mt-1">{{ nmKpi.employees100Complete ?? 0 }}</div>
                        <div class="text-[10px] text-slate-300 mt-0.5">Karyawan Sempurna (NM)</div>
                    </div>
                </div>

                <!-- 3 DONUT CHARTS NON-MANDATORY BERDAMPINGAN -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Donut 1: Overall Non-Mandatory -->
                    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm flex flex-col justify-between">
                        <div>
                            <div class="text-xs font-bold uppercase tracking-wider text-slate-900 border-b border-slate-100 pb-2 mb-3">
                                (1) Overall Non-Mandatory %
                            </div>
                            <div class="flex justify-center py-2">
                                <SvgDonutChart
                                    :segments="metrics.nonMandatoryDonutData?.overall || []"
                                    center-title="Overall NM"
                                    :center-value="`${nmKpi.overallCompletionRate ?? 0}%`"
                                />
                            </div>
                        </div>
                        <div class="text-center text-[11px] text-slate-500 border-t border-slate-100 pt-2.5 mt-2">
                            Total Kepatuhan Non-Mandatory Gabungan
                        </div>
                    </div>

                    <!-- Donut 2: Internal Non-Mandatory -->
                    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm flex flex-col justify-between">
                        <div>
                            <div class="text-xs font-bold uppercase tracking-wider text-slate-900 border-b border-slate-100 pb-2 mb-3">
                                (2) Internal Non-Mandatory %
                            </div>
                            <div class="flex justify-center py-2">
                                <SvgDonutChart
                                    :segments="metrics.nonMandatoryDonutData?.internal || []"
                                    center-title="Internal NM"
                                    :center-value="`${nmKpi.internalCompletionRate ?? 0}%`"
                                />
                            </div>
                        </div>
                        <div class="text-center text-[11px] text-slate-500 border-t border-slate-100 pt-2.5 mt-2">
                            Pelatihan Sukarela &amp; Soft Skills Internal
                        </div>
                    </div>

                    <!-- Donut 3: External Non-Mandatory -->
                    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm flex flex-col justify-between">
                        <div>
                            <div class="text-xs font-bold uppercase tracking-wider text-slate-900 border-b border-slate-100 pb-2 mb-3">
                                (3) External Non-Mandatory %
                            </div>
                            <div class="flex justify-center py-2">
                                <SvgDonutChart
                                    :segments="metrics.nonMandatoryDonutData?.external || []"
                                    center-title="External NM"
                                    :center-value="`${nmKpi.externalCompletionRate ?? 0}%`"
                                />
                            </div>
                        </div>
                        <div class="text-center text-[11px] text-slate-500 border-t border-slate-100 pt-2.5 mt-2">
                            Pelatihan Sertifikasi Eksternal Sukarela
                        </div>
                    </div>
                </div>

                <!-- GRAFIK CUMULATIVE NON-MANDATORY TREND (JAN - DES) -->
                <div class="bg-white border border-slate-200 rounded-xl p-4 sm:p-5 shadow-sm">
                    <div class="flex justify-between items-center border-b border-slate-100 pb-3 mb-4">
                        <div>
                            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-900">
                                Cumulative Non-Mandatory Trend (Jan &ndash; Des {{ currentYear }})
                            </h2>
                            <p class="text-xs text-slate-500 mt-0.5">Rasio progres kumulatif partisipasi kursus Non-Mandatory sepanjang tahun.</p>
                        </div>
                        <span class="text-xs font-mono font-bold text-amber-700 bg-amber-50 px-2.5 py-1 rounded-md border border-amber-200">
                            Current: {{ nmKpi.overallCompletionRate ?? 0 }}%
                        </span>
                    </div>

                    <div class="h-36 w-full flex items-end justify-between gap-2 pt-4 px-2 border-b border-slate-200">
                        <div
                            v-for="item in (metrics.nonMandatoryCumulativeTrend || [])"
                            :key="item.month"
                            class="flex-1 flex flex-col items-center gap-1 h-full justify-end group"
                        >
                            <span class="text-[9px] font-mono font-bold text-amber-700 opacity-0 group-hover:opacity-100 transition">
                                {{ item.percentage }}%
                            </span>
                            <div class="w-full flex items-end justify-center h-full">
                                <div
                                    :style="{ height: `${Math.max(4, item.percentage)}%` }"
                                    class="w-full max-w-[28px] bg-gradient-to-t from-amber-500 to-amber-300 group-hover:from-amber-600 group-hover:to-amber-400 transition-all rounded-t-sm"
                                    :title="`${item.month}: ${item.percentage}% Selesai`"
                                ></div>
                            </div>
                            <span class="text-[10px] font-mono text-slate-500 mt-2">{{ item.month }}</span>
                        </div>
                    </div>
                </div>

                <!-- TABEL GAP PER DEPARTEMEN (NON-MANDATORY) -->
                <div class="bg-white border border-slate-200 rounded-xl p-4 sm:p-5 shadow-sm">
                    <div class="flex justify-between items-center border-b border-slate-100 pb-3 mb-4">
                        <div>
                            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-900">
                                Department Gap &amp; Participation (Non-Mandatory)
                            </h2>
                            <p class="text-xs text-slate-500 mt-0.5">Pemetaan partisipasi kursus sukarela dan pengembangan mandiri per departemen.</p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold uppercase tracking-wider text-slate-600">
                                    <th class="p-2.5">Departemen</th>
                                    <th class="p-2.5 text-center">Tipe</th>
                                    <th class="p-2.5 text-center">Jumlah Karyawan</th>
                                    <th class="p-2.5 text-center">Rata-rata Belum/Emp</th>
                                    <th class="p-2.5 text-center">Target (NM)</th>
                                    <th class="p-2.5 text-center text-emerald-700">Sudah Selesai</th>
                                    <th class="p-2.5 text-center text-amber-700">Belum Selesai</th>
                                    <th class="p-2.5 text-center text-rose-700">Expired</th>
                                    <th class="p-2.5 text-right">% Selesai</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="dept in metrics.nonMandatoryDeptRankings" :key="dept.name" class="hover:bg-slate-50 transition">
                                    <td class="p-2.5 font-bold text-slate-900">{{ dept.name }}</td>
                                    <td class="p-2.5 text-center">
                                        <span
                                            class="text-[10px] font-bold px-1.5 py-0.5 rounded uppercase"
                                            :class="dept.type === 'internal_dept' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-800'"
                                        >
                                            {{ dept.type === 'internal_dept' ? 'Internal' : 'Kontraktor' }}
                                        </span>
                                    </td>
                                    <td class="p-2.5 text-center font-mono">{{ dept.total_employees }}</td>
                                    <td class="p-2.5 text-center font-mono font-semibold">{{ dept.avg_not_yet_per_emp }}</td>
                                    <td class="p-2.5 text-center font-mono font-bold">{{ dept.required }}</td>
                                    <td class="p-2.5 text-center font-mono text-emerald-600 font-bold">{{ dept.completed }}</td>
                                    <td class="p-2.5 text-center font-mono text-amber-600 font-bold">{{ dept.not_yet }}</td>
                                    <td class="p-2.5 text-center font-mono text-rose-600 font-bold">{{ dept.expired }}</td>
                                    <td class="p-2.5 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <div class="w-16 h-2 bg-slate-100 rounded-full overflow-hidden">
                                                <div
                                                    class="h-full rounded-full transition-all"
                                                    :class="dept.completion_pct >= 80 ? 'bg-emerald-500' : (dept.completion_pct >= 50 ? 'bg-amber-500' : 'bg-rose-500')"
                                                    :style="{ width: `${dept.completion_pct}%` }"
                                                ></div>
                                            </div>
                                            <span class="font-mono font-bold text-slate-900 w-10 text-right">{{ dept.completion_pct }}%</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ============================================================== -->
            <!-- SUB-TAB 4: INTERNAL & SAFETY MANDATORY GAP                     -->
            <!-- ============================================================== -->
            <div v-show="currentTab === 'safety_internal'" class="space-y-6">
                <div class="bg-white border border-slate-200 rounded-xl p-4 sm:p-5 shadow-sm">
                    <div class="flex justify-between items-center border-b border-slate-100 pb-3 mb-4">
                        <div>
                            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-900">
                                4. Internal &amp; Safety Mandatory Gap
                            </h2>
                            <p class="text-xs text-slate-500 mt-0.5">Rincian seluruh pelatihan Internal &amp; Safety Mandatory diurutkan dari defisit (Not Yet) terbanyak.</p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold uppercase tracking-wider text-slate-600">
                                    <th class="p-2.5 text-center w-10">No</th>
                                    <th class="p-2.5">Kode Training</th>
                                    <th class="p-2.5">Judul Training</th>
                                    <th class="p-2.5 text-center">Kategori</th>
                                    <th class="p-2.5 text-center">Wajib Ikut (M)</th>
                                    <th class="p-2.5 text-center text-emerald-700">Sudah Selesai (Done)</th>
                                    <th class="p-2.5 text-center text-amber-700 font-extrabold bg-amber-50">Belum Selesai (Not Yet)</th>
                                    <th class="p-2.5 text-center text-rose-700">Expired</th>
                                    <th class="p-2.5 text-right">% Selesai</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="(c, idx) in metrics.safetyInternalGap" :key="c.id" class="hover:bg-slate-50 transition">
                                    <td class="p-2.5 text-center font-mono text-slate-400">{{ idx + 1 }}</td>
                                    <td class="p-2.5 font-mono font-bold text-blue-700">{{ c.code }}</td>
                                    <td class="p-2.5 font-bold text-slate-900">{{ c.title }}</td>
                                    <td class="p-2.5 text-center">
                                        <span class="text-[10px] font-semibold bg-slate-100 text-slate-700 px-1.5 py-0.5 rounded">
                                            {{ c.training_category }}
                                        </span>
                                    </td>
                                    <td class="p-2.5 text-center font-mono font-bold">{{ c.required }}</td>
                                    <td class="p-2.5 text-center font-mono text-emerald-600 font-bold">{{ c.completed }}</td>
                                    <td class="p-2.5 text-center font-mono text-amber-700 font-black bg-amber-50/70">{{ c.not_yet }}</td>
                                    <td class="p-2.5 text-center font-mono text-rose-600 font-bold">{{ c.expired }}</td>
                                    <td class="p-2.5 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <div class="w-16 h-2 bg-slate-100 rounded-full overflow-hidden">
                                                <div
                                                    class="h-full rounded-full transition-all"
                                                    :class="c.completion_percentage >= 80 ? 'bg-emerald-500' : (c.completion_percentage >= 50 ? 'bg-amber-500' : 'bg-rose-500')"
                                                    :style="{ width: `${c.completion_percentage}%` }"
                                                ></div>
                                            </div>
                                            <span class="font-mono font-bold text-slate-900 w-10 text-right">{{ c.completion_percentage }}%</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ============================================================== -->
            <!-- SUB-TAB 5: EXTERNAL MANDATORY (LISENSI BNSP / KEMNAKER / ESDM) -->
            <!-- ============================================================== -->
            <div v-show="currentTab === 'external_mandatory'" class="space-y-6">
                <div class="bg-white border border-slate-200 rounded-xl p-4 sm:p-5 shadow-sm">
                    <div class="flex justify-between items-center border-b border-slate-100 pb-3 mb-4">
                        <div>
                            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-900">
                                5. External Mandatory (Lisensi BNSP / KEMNAKER / ESDM)
                            </h2>
                            <p class="text-xs text-slate-500 mt-0.5">Rekapitulasi sertifikasi eksternal berstatus Mandatory (POP, POM, POU, SIO, TKPK, AK3) urut defisit terbanyak.</p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold uppercase tracking-wider text-slate-600">
                                    <th class="p-2.5 text-center w-10">No</th>
                                    <th class="p-2.5">Kode Training</th>
                                    <th class="p-2.5">Nama Sertifikasi / Pelatihan</th>
                                    <th class="p-2.5 text-center">Kategori</th>
                                    <th class="p-2.5 text-center">Total Wajib Ikut</th>
                                    <th class="p-2.5 text-center text-emerald-700">Completed</th>
                                    <th class="p-2.5 text-center text-amber-700 font-extrabold bg-amber-50">Not Yet</th>
                                    <th class="p-2.5 text-center text-rose-700">Expired</th>
                                    <th class="p-2.5 text-right">% Completed</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="(c, idx) in metrics.externalMandatoryGap" :key="c.id" class="hover:bg-slate-50 transition">
                                    <td class="p-2.5 text-center font-mono text-slate-400">{{ idx + 1 }}</td>
                                    <td class="p-2.5 font-mono font-bold text-blue-700">{{ c.code }}</td>
                                    <td class="p-2.5 font-bold text-slate-900">{{ c.title }}</td>
                                    <td class="p-2.5 text-center">
                                        <span class="text-[10px] font-semibold bg-slate-100 text-slate-700 px-1.5 py-0.5 rounded">
                                            {{ c.training_category }}
                                        </span>
                                    </td>
                                    <td class="p-2.5 text-center font-mono font-bold">{{ c.required }}</td>
                                    <td class="p-2.5 text-center font-mono text-emerald-600 font-bold">{{ c.completed }}</td>
                                    <td class="p-2.5 text-center font-mono text-amber-700 font-black bg-amber-50/70">{{ c.not_yet }}</td>
                                    <td class="p-2.5 text-center font-mono text-rose-600 font-bold">{{ c.expired }}</td>
                                    <td class="p-2.5 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <div class="w-16 h-2 bg-slate-100 rounded-full overflow-hidden">
                                                <div
                                                    class="h-full rounded-full transition-all"
                                                    :class="c.completion_percentage >= 80 ? 'bg-emerald-500' : (c.completion_percentage >= 50 ? 'bg-amber-500' : 'bg-rose-500')"
                                                    :style="{ width: `${c.completion_percentage}%` }"
                                                ></div>
                                            </div>
                                            <span class="font-mono font-bold text-slate-900 w-10 text-right">{{ c.completion_percentage }}%</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </AdminLayout>
</template>
