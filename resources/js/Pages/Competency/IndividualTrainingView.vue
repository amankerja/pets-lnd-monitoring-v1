<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    viewMode: {
        type: String,
        default: 'department',
    },
    departments: {
        type: Array,
        default: () => [],
    },
    sections: {
        type: Array,
        default: () => [],
    },
    selectedDepartment: {
        type: String,
        default: 'Mining',
    },
    selectedSection: {
        type: String,
        default: '',
    },
    departmentKpi: {
        type: Object,
        default: () => ({
            totalWorkers: 0,
            avgCompliance: 0,
            fulfilledSkills: 0,
            gapSkills: 0,
            totalGaps: 0,
        }),
    },
    departmentCompetencies: {
        type: Array,
        default: () => [],
    },
    participantsList: {
        type: Array,
        default: () => [],
    },
    selectedUserId: {
        type: [Number, String],
        default: null,
    },
    search: {
        type: String,
        default: '',
    },
    canScheduleTraining: {
        type: Boolean,
        default: false,
    },
    selectedUser: {
        type: Object,
        default: null,
    },
    summary: {
        type: Object,
        default: () => ({
            completed: 0,
            not_yet: 0,
            expired: 0,
            not_required: 0,
        }),
    },
    trainings: {
        type: Array,
        default: () => [],
    },
});

// Mode State
const activeMode = ref(props.viewMode || 'department');

// Department & Section TNA State
const currentDepartment = ref(props.selectedDepartment || 'Mining');
const currentSection = ref(props.selectedSection || '');
const statusFilter = ref('');
const competencySearch = ref('');
const expandedSkillId = ref(null);

const toggleExpandSkill = (id) => {
    expandedSkillId.value = expandedSkillId.value === id ? null : id;
};

const onDepartmentChange = () => {
    currentSection.value = '';
    router.get('/competency/individual-view', {
        view_mode: 'department',
        department: currentDepartment.value,
        section: '',
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const onSectionChange = () => {
    router.get('/competency/individual-view', {
        view_mode: 'department',
        department: currentDepartment.value,
        section: currentSection.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const filteredDepartmentCompetencies = computed(() => {
    let list = props.departmentCompetencies || [];
    if (statusFilter.value === 'has_gap') {
        list = list.filter(c => c.unfulfilledCount > 0);
    } else if (statusFilter.value === 'all_completed') {
        list = list.filter(c => c.unfulfilledCount === 0);
    } else if (statusFilter.value === 'mandatory') {
        list = list.filter(c => c.is_mandatory);
    }
    if (competencySearch.value.trim()) {
        const q = competencySearch.value.toLowerCase().trim();
        list = list.filter(c => 
            (c.code && c.code.toLowerCase().includes(q)) ||
            (c.name && c.name.toLowerCase().includes(q))
        );
    }
    return list;
});

const enrollWorker = (comp, item) => {
    router.post('/training/administration/enroll', {
        course_id: comp.id,
        user_ids: [item.user.id],
    }, {
        preserveScroll: true,
        onSuccess: () => alert(`Berhasil mendaftarkan ${item.user.name} (${item.user.nik}) ke program pelatihan: [${comp.code}] ${comp.name}`),
    });
};

const enrollAllUnfulfilled = (comp) => {
    if (!comp.unfulfilledWorkers || comp.unfulfilledWorkers.length === 0) return;
    const userIds = comp.unfulfilledWorkers.map(w => w.user.id);
    if (!confirm(`Daftarkan seluruh ${userIds.length} karyawan yang belum memenuhi ke pelatihan [${comp.code}] ${comp.name}?`)) return;
    router.post('/training/administration/enroll', {
        course_id: comp.id,
        user_ids: userIds,
    }, {
        preserveScroll: true,
        onSuccess: () => alert(`Berhasil mendaftarkan ${userIds.length} karyawan antrean TNA ke pelatihan [${comp.code}] ${comp.name}`),
    });
};

// Mode Individu State
const currentUserId = ref(props.selectedUserId);
const searchQuery = ref(props.search);

const onUserChange = () => {
    if (!currentUserId.value) return;
    router.get('/competency/individual-view', {
        view_mode: 'individual',
        user_id: currentUserId.value,
        search: searchQuery.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const searchByNik = () => {
    router.get('/competency/individual-view', {
        view_mode: 'individual',
        search: searchQuery.value.trim() || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};
</script>

<template>
    <Head title="TNA & Checkup Kompetensi — MDKA Group" />

    <AdminLayout
        title="TNA & Checkup Kompetensi"
        subtitle="Analisis Pemenuhan Standar Kompetensi, Identifikasi Kesenjangan (Gap) Pelatihan & Rekap Nama Sudah / Belum Lulus per Departemen & Section"
    >
        <div class="space-y-6">

            <!-- HEADER NAVIGATION & MODE SWITCHER -->
            <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-base font-black text-slate-900">
                        {{ activeMode === 'department' ? 'TNA & Checkup: Per Departemen & Section' : 'TNA & Checkup: Per Individu (Karyawan)' }}
                    </h1>
                    <p class="text-xs text-slate-500 mt-0.5">
                        {{ activeMode === 'department' ? 'Tinjau status kepatuhan standar kompetensi per departemen dan section beserta daftar nama yang sudah atau belum lulus.' : 'Pelacakan kepatuhan matriks kompetensi 360° dan riwayat sertifikasi untuk 1 orang karyawan.' }}
                    </p>
                </div>

                <!-- DUAL MODE SWITCHER -->
                <div class="inline-flex p-1 bg-slate-100 rounded-xl border border-slate-200 shrink-0">
                    <button
                        type="button"
                        @click="activeMode = 'department'"
                        :class="['px-4 py-2 rounded-lg text-xs font-bold transition-all flex items-center gap-2', activeMode === 'department' ? 'bg-white text-blue-700 shadow-sm ring-1 ring-slate-200' : 'text-slate-600 hover:text-slate-900']"
                    >
                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                        <span>🏢 Per Departemen &amp; Section</span>
                    </button>
                    <button
                        type="button"
                        @click="activeMode = 'individual'"
                        :class="['px-4 py-2 rounded-lg text-xs font-bold transition-all flex items-center gap-2', activeMode === 'individual' ? 'bg-white text-blue-700 shadow-sm ring-1 ring-slate-200' : 'text-slate-600 hover:text-slate-900']"
                    >
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        <span>👤 Per Individu (Karyawan)</span>
                    </button>
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- MODE 1: TNA & CHECKUP PER DEPARTEMEN & SECTION            -->
            <!-- ========================================================= -->
            <div v-if="activeMode === 'department'" class="space-y-4">
                <!-- FILTER BAR: DEPARTEMEN & SECTION -->
                <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        <!-- Dropdown Departemen -->
                        <div>
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">
                                1. Pilih Departemen:
                            </label>
                            <select
                                v-model="currentDepartment"
                                @change="onDepartmentChange"
                                class="w-full rounded-lg border-slate-300 text-xs py-2 px-3 bg-white font-bold text-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                            >
                                <option value="all">Semua Departemen</option>
                                <option v-for="dept in departments" :key="dept" :value="dept">{{ dept }}</option>
                            </select>
                        </div>

                        <!-- Dropdown Section -->
                        <div>
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">
                                2. Pilih Section / Bagian:
                            </label>
                            <select
                                v-model="currentSection"
                                @change="onSectionChange"
                                class="w-full rounded-lg border-slate-300 text-xs py-2 px-3 bg-white font-semibold text-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                            >
                                <option value="">{{ currentDepartment ? 'Semua Section di ' + currentDepartment : 'Semua Section' }}</option>
                                <option v-for="sec in sections" :key="sec" :value="sec">{{ sec }}</option>
                            </select>
                        </div>

                        <!-- Filter Status Kepatuhan -->
                        <div>
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">
                                3. Status Kepatuhan:
                            </label>
                            <select
                                v-model="statusFilter"
                                class="w-full rounded-lg border-slate-300 text-xs py-2 px-3 bg-white text-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                            >
                                <option value="">Semua Status</option>
                                <option value="has_gap">🔴 Memiliki Karyawan Belum Memenuhi (TNA Action)</option>
                                <option value="all_completed">🟢 100% Karyawan Memenuhi (Compliant)</option>
                                <option value="mandatory">Wajib (Mandatory MDKA) Saja</option>
                            </select>
                        </div>

                        <!-- Cari Nama/Kode Kompetensi -->
                        <div>
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">
                                4. Cari Nama / Kode:
                            </label>
                            <input
                                v-model="competencySearch"
                                type="search"
                                placeholder="Cari kode atau nama kompetensi..."
                                class="w-full rounded-lg border-slate-300 text-xs py-2 px-3 text-slate-800 placeholder-slate-400 focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                            />
                        </div>
                    </div>
                </div>

                <!-- 5 KPI SUMMARY CARDS -->
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                    <div class="bg-white border border-slate-200 rounded-xl p-3.5 shadow-sm">
                        <span class="text-[10px] font-black uppercase tracking-wider text-slate-500">Karyawan Terdata</span>
                        <div class="text-2xl font-black text-slate-900 mt-1">{{ departmentKpi.totalWorkers }} Orang</div>
                        <div class="text-[11px] text-slate-500 mt-0.5 truncate">{{ currentDepartment || 'Semua Dept' }} {{ currentSection ? '(' + currentSection + ')' : '' }}</div>
                    </div>
                    <div class="bg-blue-50/70 border border-blue-200 rounded-xl p-3.5 shadow-sm">
                        <span class="text-[10px] font-black uppercase tracking-wider text-blue-800">Tingkat Kepatuhan (Avg)</span>
                        <div class="text-2xl font-black text-blue-900 mt-1">{{ departmentKpi.avgCompliance }}%</div>
                        <div class="text-[11px] text-blue-700 mt-0.5">Pemenuhan standar level</div>
                    </div>
                    <div class="bg-emerald-50/70 border border-emerald-200 rounded-xl p-3.5 shadow-sm">
                        <span class="text-[10px] font-black uppercase tracking-wider text-emerald-800">100% Terpenuhi</span>
                        <div class="text-2xl font-black text-emerald-900 mt-1">{{ departmentKpi.fulfilledSkills }} Kompetensi</div>
                        <div class="text-[11px] text-emerald-700 mt-0.5">Semua karyawan lulus</div>
                    </div>
                    <div class="bg-amber-50/70 border border-amber-200 rounded-xl p-3.5 shadow-sm">
                        <span class="text-[10px] font-black uppercase tracking-wider text-amber-800">Perlu Training (TNA)</span>
                        <div class="text-2xl font-black text-amber-900 mt-1">{{ departmentKpi.gapSkills }} Kompetensi</div>
                        <div class="text-[11px] text-amber-700 mt-0.5">Ada karyawan belum memenuhi</div>
                    </div>
                    <div class="col-span-2 sm:col-span-1 bg-rose-50/70 border border-rose-200 rounded-xl p-3.5 shadow-sm">
                        <span class="text-[10px] font-black uppercase tracking-wider text-rose-800">Total Gap Peserta</span>
                        <div class="text-2xl font-black text-rose-900 mt-1">{{ departmentKpi.totalGaps }} Peserta</div>
                        <div class="text-[11px] text-rose-700 mt-0.5">Kebutuhan pendaftaran</div>
                    </div>
                </div>

                <!-- TABEL STATUS KOMPETENSI PER DEPARTEMEN & SECTION -->
                <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                    <div class="p-3.5 bg-slate-50 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <div>
                            <h3 class="font-bold text-xs text-slate-800">
                                Status Kepatuhan Kompetensi: {{ currentDepartment || 'Semua Departemen' }} {{ currentSection ? '— Section: ' + currentSection : '' }}
                            </h3>
                            <p class="text-[11px] text-slate-500">Klik "Rincian Nama" pada baris kompetensi untuk membuka daftar karyawan yang SUDAH atau BELUM memenuhi.</p>
                        </div>
                        <div class="text-xs text-slate-500 font-semibold">
                            Menampilkan {{ filteredDepartmentCompetencies.length }} Kompetensi
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse text-xs">
                            <thead>
                                <tr class="bg-slate-100/70 text-slate-700 border-b border-slate-200 uppercase text-[10px] tracking-wider font-bold">
                                    <th class="py-3 px-4 text-left min-w-[280px]">Kompetensi &amp; Kode</th>
                                    <th class="py-3 px-3 text-left min-w-[140px]">Kategori &amp; Sifat</th>
                                    <th class="py-3 px-3 text-left min-w-[180px]">Tingkat Kepatuhan</th>
                                    <th class="py-3 px-3 text-center min-w-[130px]">Sudah Memenuhi</th>
                                    <th class="py-3 px-3 text-center min-w-[130px]">Belum Memenuhi</th>
                                    <th class="py-3 px-4 text-right min-w-[200px]">Aksi TNA &amp; Detail</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <template v-for="comp in filteredDepartmentCompetencies" :key="'dept-comp-' + comp.id">
                                    <tr :class="['transition-colors', expandedSkillId === comp.id ? 'bg-blue-50/50' : 'hover:bg-slate-50']">
                                        <td class="py-3 px-4">
                                            <div class="font-bold text-slate-900 text-sm">
                                                <span class="text-blue-700 font-mono">[{{ comp.code }}]</span> {{ comp.name }}
                                            </div>
                                            <div class="text-[10px] text-slate-500 mt-0.5 line-clamp-1">{{ comp.description }}</div>
                                        </td>
                                        <td class="py-3 px-3">
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <span :class="['px-1.5 py-0.5 rounded text-[10px] font-bold uppercase', comp.is_mandatory ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-700']">
                                                    {{ comp.is_mandatory ? 'Mandatory' : 'Non-Mandatory' }}
                                                </span>
                                                <span class="text-[10px] text-slate-500">{{ comp.category }}</span>
                                            </div>
                                        </td>
                                        <td class="py-3 px-3">
                                            <div class="flex items-center justify-between text-[11px] font-bold">
                                                <span class="text-slate-700">{{ comp.fulfilledCount }} / {{ comp.totalWorkers }} Karyawan</span>
                                                <span :class="comp.fulfillmentRate >= 100 ? 'text-emerald-700' : comp.fulfillmentRate >= 60 ? 'text-blue-700' : 'text-rose-700'">
                                                    {{ comp.fulfillmentRate }}%
                                                </span>
                                            </div>
                                            <div class="w-full bg-slate-100 h-2 rounded mt-1 overflow-hidden">
                                                <div
                                                    :class="['h-full transition-all duration-300', comp.fulfillmentRate >= 100 ? 'bg-emerald-500' : comp.fulfillmentRate >= 60 ? 'bg-blue-600' : 'bg-rose-500']"
                                                    :style="{ width: comp.fulfillmentRate + '%' }"
                                                ></div>
                                            </div>
                                        </td>
                                        <td class="py-3 px-3 text-center">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                                ✓ {{ comp.fulfilledCount }} Orang
                                            </span>
                                        </td>
                                        <td class="py-3 px-3 text-center">
                                            <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold', comp.unfulfilledCount > 0 ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-500']">
                                                {{ comp.unfulfilledCount > 0 ? '✗ ' + comp.unfulfilledCount + ' Orang' : '0 (Lengkap)' }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-right">
                                            <div class="flex items-center justify-end gap-1.5">
                                                <button
                                                    type="button"
                                                    @click="toggleExpandSkill(comp.id)"
                                                    :class="['px-2.5 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1', expandedSkillId === comp.id ? 'bg-blue-700 text-white shadow-xs' : 'bg-blue-50 hover:bg-blue-100 text-blue-800 border border-blue-200']"
                                                >
                                                    <span>Rincian Nama ({{ comp.totalWorkers }})</span>
                                                    <span class="text-[9px]">{{ expandedSkillId === comp.id ? '▲' : '▼' }}</span>
                                                </button>
                                                <button
                                                    v-if="comp.unfulfilledCount > 0"
                                                    type="button"
                                                    @click="enrollAllUnfulfilled(comp)"
                                                    class="px-2.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-xs transition-colors"
                                                    title="Daftarkan seluruh karyawan yang belum memenuhi ke sesi training"
                                                >
                                                    + Daftarkan TNA
                                                </button>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- ACCORDION DRILLDOWN: DAFTAR NAMA SUDAH VS BELUM MEMENUHI -->
                                    <tr v-if="expandedSkillId === comp.id" :key="'expand-' + comp.id" class="bg-blue-50/30">
                                        <td colspan="6" class="p-4 border-t border-b border-blue-200">
                                            <div class="bg-white rounded-xl border border-blue-200 p-4 shadow-sm space-y-4">
                                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
                                                    <div>
                                                        <h4 class="font-bold text-sm text-slate-900">
                                                            Rincian Karyawan: [{{ comp.code }}] {{ comp.name }}
                                                        </h4>
                                                        <p class="text-[11px] text-slate-500 mt-0.5">
                                                            Departemen: {{ currentDepartment || 'Semua' }} &bull; Section: {{ currentSection || 'Semua' }} &bull; Total: {{ comp.totalWorkers }} Karyawan ({{ comp.fulfilledCount }} Sudah Memenuhi, {{ comp.unfulfilledCount }} Belum Memenuhi)
                                                        </p>
                                                    </div>
                                                    <div class="flex items-center gap-2">
                                                        <button
                                                            v-if="comp.unfulfilledCount > 0"
                                                            type="button"
                                                            @click="enrollAllUnfulfilled(comp)"
                                                            class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-xs transition"
                                                        >
                                                            + Daftarkan Semua ({{ comp.unfulfilledCount }} Karyawan) yang Belum
                                                        </button>
                                                        <button
                                                            type="button"
                                                            @click="expandedSkillId = null"
                                                            class="px-2.5 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-100 text-slate-600 text-xs font-semibold"
                                                        >
                                                            Tutup ▲
                                                        </button>
                                                    </div>
                                                </div>

                                                <!-- 2 KOLOM SPLIT: NAMA SUDAH VS NAMA BELUM -->
                                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                                    <!-- KOLOM 1: SUDAH MEMENUHI -->
                                                    <div class="border border-emerald-200 rounded-xl overflow-hidden bg-emerald-50/20">
                                                        <div class="px-3.5 py-2.5 bg-emerald-100/70 border-b border-emerald-200 flex items-center justify-between">
                                                            <div class="flex items-center gap-2">
                                                                <span class="w-2.5 h-2.5 rounded-full bg-emerald-600"></span>
                                                                <span class="font-bold text-xs text-emerald-950 uppercase tracking-wide">
                                                                    Karyawan Sudah Memenuhi ({{ comp.fulfilledCount }})
                                                                </span>
                                                            </div>
                                                            <span class="text-[10px] font-bold text-emerald-800 bg-white/70 px-2 py-0.5 rounded">
                                                                LULUS &amp; VALID
                                                            </span>
                                                        </div>

                                                        <div class="p-2 divide-y divide-emerald-100 max-h-72 overflow-y-auto">
                                                            <div
                                                                v-for="item in comp.fulfilledWorkers"
                                                                :key="'f-' + item.user.id"
                                                                class="p-2.5 hover:bg-white rounded-lg transition-colors flex items-start justify-between gap-3 text-xs"
                                                            >
                                                                <div class="min-w-0 flex-1">
                                                                    <div class="font-bold text-slate-900 flex items-center gap-2">
                                                                        <span>{{ item.user.name }}</span>
                                                                        <span class="text-slate-400 font-mono text-[10px]">[{{ item.user.nik }}]</span>
                                                                    </div>
                                                                    <div class="text-[10px] text-slate-600 mt-0.5 flex flex-wrap gap-x-2">
                                                                        <span>Dept: {{ item.user.department }}</span>
                                                                        <span v-if="item.user.section">&bull; Sec: {{ item.user.section }}</span>
                                                                        <span v-if="item.user.job_level">&bull; Posisi: {{ item.user.job_level }}</span>
                                                                    </div>
                                                                    <div class="text-[10px] text-slate-500 mt-1 flex flex-wrap items-center gap-2">
                                                                        <span class="font-mono text-emerald-700 font-semibold">{{ item.cert_no }}</span>
                                                                        <span>&bull; Exp: {{ item.valid_until }}</span>
                                                                    </div>
                                                                </div>
                                                                <div class="text-right shrink-0">
                                                                    <span :class="['px-2 py-0.5 rounded text-[9px] font-bold', item.status_badge]">
                                                                        {{ item.status_label }}
                                                                    </span>
                                                                    <div class="text-[9px] text-slate-500 mt-1">
                                                                        Level: {{ item.current_level }}/{{ item.target_level }}
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div v-if="!comp.fulfilledWorkers.length" class="p-6 text-center text-slate-400 text-xs italic">
                                                                Belum ada karyawan di departemen/section ini yang memenuhi kompetensi ini.
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- KOLOM 2: BELUM MEMENUHI -->
                                                    <div class="border border-rose-200 rounded-xl overflow-hidden bg-rose-50/20">
                                                        <div class="px-3.5 py-2.5 bg-rose-100/70 border-b border-rose-200 flex items-center justify-between">
                                                            <div class="flex items-center gap-2">
                                                                <span class="w-2.5 h-2.5 rounded-full bg-rose-600"></span>
                                                                <span class="font-bold text-xs text-rose-950 uppercase tracking-wide">
                                                                    Karyawan Belum Memenuhi ({{ comp.unfulfilledCount }})
                                                                </span>
                                                            </div>
                                                            <span class="text-[10px] font-bold text-rose-800 bg-white/70 px-2 py-0.5 rounded">
                                                                ANTREAN TNA
                                                            </span>
                                                        </div>

                                                        <div class="p-2 divide-y divide-rose-100 max-h-72 overflow-y-auto">
                                                            <div
                                                                v-for="item in comp.unfulfilledWorkers"
                                                                :key="'u-' + item.user.id"
                                                                class="p-2.5 hover:bg-white rounded-lg transition-colors flex items-start justify-between gap-3 text-xs"
                                                            >
                                                                <div class="min-w-0 flex-1">
                                                                    <div class="font-bold text-slate-900 flex items-center gap-2">
                                                                        <span>{{ item.user.name }}</span>
                                                                        <span class="text-slate-400 font-mono text-[10px]">[{{ item.user.nik }}]</span>
                                                                    </div>
                                                                    <div class="text-[10px] text-slate-600 mt-0.5 flex flex-wrap gap-x-2">
                                                                        <span>Dept: {{ item.user.department }}</span>
                                                                        <span v-if="item.user.section">&bull; Sec: {{ item.user.section }}</span>
                                                                        <span v-if="item.user.job_level">&bull; Posisi: {{ item.user.job_level }}</span>
                                                                    </div>
                                                                    <div class="text-[10px] mt-1 flex items-center gap-2">
                                                                        <span :class="['px-1.5 py-0.5 rounded text-[9px] font-bold', item.reason_badge]">
                                                                            {{ item.reason }}
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                                <div class="text-right shrink-0">
                                                                    <button
                                                                        type="button"
                                                                        @click="enrollWorker(comp, item)"
                                                                        class="px-2.5 py-1 rounded bg-blue-600 hover:bg-blue-700 text-white text-[10px] font-bold shadow-xs transition"
                                                                    >
                                                                        + Daftarkan Training
                                                                    </button>
                                                                </div>
                                                            </div>

                                                            <div v-if="!comp.unfulfilledWorkers.length" class="p-6 text-center text-emerald-700 text-xs font-semibold">
                                                                ✓ Luar biasa! Seluruh karyawan di departemen/section ini telah memenuhi standar kompetensi ini.
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </template>

                                <tr v-if="!filteredDepartmentCompetencies.length">
                                    <td colspan="6" class="py-12 text-center text-slate-400 text-xs">
                                        Tidak ada kompetensi yang sesuai dengan kriteria filter pencarian di departemen/section ini.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- MODE 2: TNA & CHECKUP PER INDIVIDU (PRESERVED)             -->
            <!-- ========================================================= -->
            <div v-else-if="activeMode === 'individual'" class="space-y-6">
                <!-- SELECT EMPLOYEE DROPDOWN BAR -->
                <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="flex-1 max-w-3xl flex flex-col sm:flex-row gap-2">
                        <form class="w-full sm:w-1/2" @submit.prevent="searchByNik">
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">
                                Cari Karyawan berdasarkan NIK / Nama
                            </label>
                            <div class="flex gap-2">
                                <input
                                    v-model="searchQuery"
                                    type="search"
                                    placeholder="Masukkan NIK atau nama karyawan"
                                    class="min-w-0 flex-1 text-sm rounded-lg border-slate-200 bg-white focus:ring-2 focus:ring-blue-600 focus:border-blue-600 py-2 px-3"
                                />
                                <button
                                    type="submit"
                                    class="min-h-10 px-4 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2"
                                >
                                    Cari
                                </button>
                            </div>
                        </form>
                        <div class="w-full sm:w-1/2">
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-700 mb-1">
                                Pilih Karyawan ({{ participantsList.length }} hasil)
                            </label>
                            <select
                                v-model="currentUserId"
                                @change="onUserChange"
                                class="w-full min-h-10 text-sm rounded-lg border-slate-200 bg-white focus:ring-2 focus:ring-blue-600 focus:border-blue-600 py-2 px-3"
                            >
                                <option value="" disabled>-- Pilih hasil pencarian --</option>
                                <option
                                    v-for="p in participantsList"
                                    :key="p.id"
                                    :value="p.id"
                                >
                                    [{{ p.nik }}] {{ p.name }} - {{ p.organization?.name || p.department }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center gap-2" v-if="selectedUser">
                        <Link
                            :href="`/admin/participants/${selectedUser.id}/report-print`"
                            class="px-3.5 py-2 rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold transition flex items-center gap-1.5 shadow-sm"
                        >
                            <svg class="w-4 h-4 text-slate-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                            </svg>
                            <span>Cetak Rapor A4</span>
                        </Link>
                    </div>
                </div>

                <div v-if="selectedUser" class="space-y-6">
                    <!-- EMPLOYEE PROFILE CARD -->
                    <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
                        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 border-b border-slate-100 pb-4 mb-4">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-full bg-slate-900 text-white font-black text-lg flex items-center justify-center">
                                    {{ selectedUser.name.charAt(0) }}
                                </div>
                                <div>
                                    <h1 class="text-base font-black text-slate-900">{{ selectedUser.name }}</h1>
                                    <p class="text-xs font-mono text-slate-500 mt-0.5">
                                        NIK: <span class="font-bold text-slate-800">{{ selectedUser.nik }}</span> &bull; {{ selectedUser.email }}
                                    </p>
                                </div>
                            </div>

                            <div>
                                <span
                                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold"
                                    :class="selectedUser.match_status === 'Matched Title+Level' ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-rose-100 text-rose-800 border border-rose-200'"
                                >
                                    <span class="w-2 h-2 rounded-full" :class="selectedUser.match_status === 'Matched Title+Level' ? 'bg-emerald-600' : 'bg-rose-600'"></span>
                                    {{ selectedUser.match_status }}
                                </span>
                            </div>
                        </div>

                        <!-- 8 Parameter Identitas Excel -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3 text-xs">
                            <div class="bg-slate-50 p-2.5 rounded-lg border border-slate-100">
                                <span class="block text-[10px] uppercase font-bold text-slate-400">Department</span>
                                <span class="font-bold text-slate-800 mt-0.5 block truncate">{{ selectedUser.department }}</span>
                            </div>
                            <div class="bg-slate-50 p-2.5 rounded-lg border border-slate-100">
                                <span class="block text-[10px] uppercase font-bold text-slate-400">Section</span>
                                <span class="font-bold text-slate-800 mt-0.5 block truncate">{{ selectedUser.section }}</span>
                            </div>
                            <div class="bg-slate-50 p-2.5 rounded-lg border border-slate-100">
                                <span class="block text-[10px] uppercase font-bold text-slate-400">Position Title</span>
                                <span class="font-bold text-slate-800 mt-0.5 block truncate">{{ selectedUser.position }}</span>
                            </div>
                            <div class="bg-slate-50 p-2.5 rounded-lg border border-slate-100">
                                <span class="block text-[10px] uppercase font-bold text-slate-400">Level</span>
                                <span class="font-bold text-slate-800 mt-0.5 block truncate">{{ selectedUser.level }}</span>
                            </div>
                            <div class="bg-slate-50 p-2.5 rounded-lg border border-slate-100">
                                <span class="block text-[10px] uppercase font-bold text-slate-400">Site</span>
                                <span class="font-bold text-slate-800 mt-0.5 block truncate">{{ selectedUser.site }}</span>
                            </div>
                            <div class="bg-slate-50 p-2.5 rounded-lg border border-slate-100">
                                <span class="block text-[10px] uppercase font-bold text-slate-400">Entity</span>
                                <span class="font-bold text-slate-800 mt-0.5 block truncate">{{ selectedUser.entity }}</span>
                            </div>
                            <div class="bg-slate-50 p-2.5 rounded-lg border border-slate-100">
                                <span class="block text-[10px] uppercase font-bold text-slate-400">Nationality</span>
                                <span class="font-bold text-slate-800 mt-0.5 block truncate">{{ selectedUser.nationality || 'Indonesia' }}</span>
                            </div>
                            <div class="bg-slate-50 p-2.5 rounded-lg border border-slate-100">
                                <span class="block text-[10px] uppercase font-bold text-slate-400">Match Status</span>
                                <span class="font-bold mt-0.5 block truncate" :class="selectedUser.match_status === 'Matched Title+Level' ? 'text-emerald-700' : 'text-rose-700'">
                                    {{ selectedUser.match_status }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- 4 KOTAK RINGKASAN: Completed, Not Yet, Expired, Not Required -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 shadow-sm">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-emerald-800">Completed (Done)</div>
                            <div class="text-3xl font-black text-emerald-700 mt-1">{{ summary.completed }}</div>
                            <div class="text-[10px] text-emerald-600 mt-0.5">Kompetensi Telah Lulus</div>
                        </div>

                        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 shadow-sm">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-amber-800">Not Yet Completed</div>
                            <div class="text-3xl font-black text-amber-700 mt-1">{{ summary.not_yet }}</div>
                            <div class="text-[10px] text-amber-600 mt-0.5">Wajib Diikuti (Gap)</div>
                        </div>

                        <div class="bg-rose-50 border border-rose-200 rounded-xl p-4 shadow-sm">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-rose-800">Expired</div>
                            <div class="text-3xl font-black text-rose-700 mt-1">{{ summary.expired }}</div>
                            <div class="text-[10px] text-rose-600 mt-0.5">Masa Berlaku Telah Lewat</div>
                        </div>

                        <div class="bg-slate-100 border border-slate-200 rounded-xl p-4 shadow-sm">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-700">Not Required (NR / N/A)</div>
                            <div class="text-3xl font-black text-slate-800 mt-1">{{ summary.not_required }}</div>
                            <div class="text-[10px] text-slate-500 mt-0.5">Di Luar Standar Jabatan</div>
                        </div>
                    </div>

                    <!-- TABEL MASTER TRAINING / KOMPETENSI INDIVIDUAL VIEW -->
                    <div class="bg-white border border-slate-200 rounded-xl p-4 sm:p-5 shadow-sm">
                        <div class="flex justify-between items-center border-b border-slate-100 pb-3 mb-4">
                            <div>
                                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-900">
                                    Matriks Pelatihan &amp; Status Kepatuhan Individu
                                </h2>
                                <p class="text-xs text-slate-500 mt-0.5">Daftar seluruh pelatihan dan validasi kompetensi sesuai standar jabatan karyawan.</p>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold uppercase tracking-wider text-slate-600">
                                        <th class="p-2.5">Training Code</th>
                                        <th class="p-2.5">Training Name</th>
                                        <th class="p-2.5 text-center">Executor</th>
                                        <th class="p-2.5 text-center">Category</th>
                                        <th class="p-2.5 text-center">Requirement Status</th>
                                        <th class="p-2.5 text-center">Validity (Months)</th>
                                        <th class="p-2.5 text-center">Last Training Date</th>
                                        <th class="p-2.5 text-center">Expiry Date</th>
                                        <th class="p-2.5 text-center">Status</th>
                                        <th class="p-2.5 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="t in trainings" :key="t.code" class="hover:bg-slate-50 transition">
                                        <td class="p-2.5 font-mono font-bold text-blue-700">{{ t.code }}</td>
                                        <td class="p-2.5 font-bold text-slate-900">
                                            {{ t.name }}
                                            <div v-if="t.certificate_number" class="text-[10px] font-mono text-slate-400 font-normal">
                                                No: {{ t.certificate_number }}
                                            </div>
                                        </td>
                                        <td class="p-2.5 text-center">
                                            <span
                                                class="text-[10px] font-bold px-1.5 py-0.5 rounded uppercase"
                                                :class="t.training_source === 'internal' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'"
                                            >
                                                {{ t.training_source === 'internal' ? 'Internal' : 'External' }}
                                            </span>
                                        </td>
                                        <td class="p-2.5 text-center">
                                            <span class="text-[10px] font-semibold bg-slate-100 text-slate-700 px-1.5 py-0.5 rounded">
                                                {{ t.category }}
                                            </span>
                                        </td>
                                        <td class="p-2.5 text-center">
                                            <span
                                                class="inline-block text-[10px] font-black px-2 py-0.5 rounded uppercase"
                                                :class="{
                                                    'bg-emerald-600 text-white': t.status_jabatan === 'M',
                                                    'bg-amber-400 text-amber-950 font-bold': t.status_jabatan === 'NM',
                                                    'bg-slate-700 text-white': t.status_jabatan === 'NR',
                                                }"
                                            >
                                                {{ t.status_jabatan }}
                                            </span>
                                        </td>
                                        <td class="p-2.5 text-center font-mono text-slate-600">
                                            {{ t.validity_months }} bln
                                        </td>
                                        <td class="p-2.5 text-center font-mono text-slate-700">
                                            {{ t.last_training_date }}
                                        </td>
                                        <td class="p-2.5 text-center font-mono font-bold" :class="t.completion_status === 'Expired' ? 'text-rose-600 line-through' : 'text-slate-800'">
                                            {{ t.expiry_date }}
                                        </td>
                                        <td class="p-2.5 text-center">
                                            <span
                                                class="text-[10px] font-bold px-2 py-0.5 rounded uppercase"
                                                :class="{
                                                    'bg-emerald-100 text-emerald-800': t.completion_status === 'Completed' || t.completion_status === 'Done',
                                                    'bg-amber-100 text-amber-800': t.completion_status === 'Not Yet Completed',
                                                    'bg-rose-100 text-rose-800': t.completion_status === 'Expired',
                                                    'bg-slate-100 text-slate-500': t.completion_status === 'N/A',
                                                }"
                                            >
                                                {{ t.completion_status }}
                                            </span>
                                        </td>
                                        <td class="p-2.5 text-right">
                                            <div class="flex items-center justify-end gap-1.5">
                                                <Link
                                                    v-if="canScheduleTraining && t.status_jabatan !== 'NR' && (t.completion_status === 'Not Yet Completed' || t.completion_status === 'Expired')"
                                                    :href="`/training/administration?course_id=${t.course_id}&participant_id=${selectedUser.id}`"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded bg-blue-600 hover:bg-blue-700 text-white text-[10px] font-bold transition"
                                                    title="Daftarkan / Jadwalkan Pelatihan"
                                                >
                                                    <span>Schedule Training</span> &rarr;
                                                </Link>
                                                <Link
                                                    :href="`/admin/participants/${selectedUser.id}/report-print`"
                                                    class="inline-flex items-center gap-1 px-2 py-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 text-[10px] font-bold transition"
                                                    title="Cetak Rapor Karyawan"
                                                >
                                                    <span>Rapor</span>
                                                </Link>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div v-else class="bg-white border border-slate-200 rounded-xl p-8 text-center text-slate-500 text-xs shadow-sm">
                    Silakan pilih karyawan terlebih dahulu untuk melihat Individual Training View.
                </div>
            </div>

        </div>
    </AdminLayout>
</template>
