<script setup>
import { computed, onBeforeUnmount, reactive, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    filters: {
        type: Object,
        default: () => ({}),
    },
    filterOptions: {
        type: Object,
        default: () => ({
            departments: [],
            companies: [],
            courses: [],
            categories: ['Safety', 'Operation', 'Plant', 'Pengawas', 'Engineering', 'Leadership', 'General'],
        }),
    },
    departments: {
        type: Array,
        default: () => [],
    },
    companies: {
        type: Array,
        default: () => [],
    },
    courseCodes: {
        type: Array,
        default: () => [],
    },
    routeUrl: {
        type: String,
        default: '',
    },
    filterUrl: {
        type: String,
        default: '',
    },
    showTrainingFilters: {
        type: Boolean,
        default: true,
    },
    sites: {
        type: Array,
        default: () => [
            'PT Gorontalo Sejahtera Mining',
            'PT Elabram Systems',
            'Site Wetar (BKP)',
            'Site Tujuh Bukit (BSI)',
            'Site Pani Project (PETS/GSM)',
            'Jakarta Head Office',
        ],
    },
    jobLevels: {
        type: Array,
        default: () => [
            'Non-Staff',
            'Staff',
            'Supervisor',
            'Superintendent',
            'Manager',
        ],
    },
    showExtendedFilters: {
        type: Boolean,
        default: true,
    },
    showDateFilter: {
        type: Boolean,
        default: true,
    },
    showCategoryFilter: {
        type: Boolean,
        default: false,
    },
    showStatusFilter: {
        type: Boolean,
        default: false,
    },
    statusOptions: {
        type: Array,
        default: () => [
            { value: 'pending', label: 'Pending (Menunggu Approval)' },
            { value: 'approved', label: 'Approved (Disetujui / Aktif)' },
            { value: 'rejected', label: 'Rejected (Ditolak)' },
        ],
    },
    exportUrl: {
        type: String,
        default: '',
    },
    exportLabel: {
        type: String,
        default: 'Export CSV (Excel)',
    },
    extraParams: {
        type: Object,
        default: () => ({}),
    },
    searchPlaceholder: {
        type: String,
        default: 'Cari NIK, Nama Pekerja, Kode/Judul Training, atau No. Sertifikat...',
    },
});

const emit = defineEmits(['filter-change', 'reset']);

const page = usePage();
const userRole = computed(() => {
    const raw = page.props.auth?.user?.role || 'lnd';
    return raw === 'admin' ? 'lnd' : raw;
});

// Dropdown Departemen Internal & Perusahaan Kontraktor hanya aktif untuk lnd & trainer
const canFilterOrganizations = computed(() => {
    return ['lnd', 'trainer'].includes(userRole.value);
});

// Default date_mode diutamakan 'month' (Bulan) jika ada month atau default
const initialDateMode = computed(() => {
    if (props.filters?.month) return 'month';
    if (props.filters?.date_from || props.filters?.date_to) return props.filters?.date_mode || 'range';
    return props.filters?.date_mode || 'month';
});

const state = reactive({
    search: props.filters?.search || '',
    course_code: props.filters?.course_code || '',
    course_id: props.filters?.course_id || '',
    date_mode: initialDateMode.value, // 'month' | 'single' | 'range'
    date_from: props.filters?.date_from || '',
    date_to: props.filters?.date_to || '',
    month: props.filters?.month || '',
    department_id: props.filters?.department_id || '',
    company_id: props.filters?.company_id || '',
    site: props.filters?.site || '',
    job_level: props.filters?.job_level || '',
    mandatory_type: props.filters?.mandatory_type || '',
    training_source: props.filters?.training_source || '',
    training_category: props.filters?.training_category || '',
    status: props.filters?.status || '',
});

watch(
    () => props.filters,
    (newVal) => {
        if (!newVal) return;
        state.search = newVal.search ?? '';
        state.course_code = newVal.course_code ?? '';
        state.course_id = newVal.course_id ?? '';
        state.date_mode = newVal.date_mode || (newVal.month ? 'month' : (newVal.date_from ? 'single' : 'month'));
        state.date_from = newVal.date_from ?? '';
        state.date_to = newVal.date_to ?? '';
        state.month = newVal.month ?? '';
        state.department_id = newVal.department_id ?? '';
        state.company_id = newVal.company_id ?? '';
        state.site = newVal.site ?? '';
        state.job_level = newVal.job_level ?? '';
        state.mandatory_type = newVal.mandatory_type ?? '';
        state.training_source = newVal.training_source ?? '';
        state.training_category = newVal.training_category ?? '';
        state.status = newVal.status ?? '';
    },
    { deep: true }
);

const siteList = computed(() => {
    return props.sites?.length ? props.sites : [];
});

const jobLevelList = computed(() => {
    return props.jobLevels?.length ? props.jobLevels : [];
});

const departmentList = computed(() => {
    return props.departments?.length ? props.departments : props.filterOptions?.departments || [];
});

const companyList = computed(() => {
    return props.companies?.length ? props.companies : props.filterOptions?.companies || [];
});

const courseCodeList = computed(() => {
    return props.courseCodes?.length ? props.courseCodes : props.filterOptions?.courses || [];
});

const categoryList = computed(() => {
    return props.filterOptions?.categories?.length
        ? props.filterOptions.categories
        : ['Safety', 'Operation', 'Plant', 'Pengawas', 'Engineering', 'Leadership', 'General'];
});

const buildCleanPayload = () => {
    const payload = {
        ...props.extraParams,
        search: state.search || undefined,
        department_id: canFilterOrganizations.value && state.department_id ? state.department_id : undefined,
        company_id: canFilterOrganizations.value && state.company_id ? state.company_id : undefined,
        site: state.site || undefined,
        job_level: state.job_level || undefined,
        date_mode: state.month || state.date_from || state.date_to ? state.date_mode : undefined,
        month: state.date_mode === 'month' && state.month ? state.month : undefined,
        date_from: state.date_mode !== 'month' && state.date_from ? state.date_from : undefined,
        date_to: state.date_mode === 'range' && state.date_to ? state.date_to : undefined,
        mandatory_type: state.mandatory_type || undefined,
        training_source: state.training_source || undefined,
        course_code: state.course_code || undefined,
        course_id: state.course_id || undefined,
        training_category: state.training_category || undefined,
        status: state.status || undefined,
    };

    return Object.fromEntries(
        Object.entries(payload).filter(([, v]) => v !== undefined && v !== null && v !== '')
    );
};

const hasActiveFilters = computed(() => {
    return Boolean(
        state.search ||
        state.department_id ||
        state.company_id ||
        state.site ||
        state.job_level ||
        state.month ||
        state.date_from ||
        state.date_to ||
        state.mandatory_type ||
        state.training_source ||
        state.course_code ||
        state.training_category ||
        state.status
    );
});

const activeFiltersCount = computed(() => {
    let count = 0;
    if (state.search) count++;
    if (state.department_id) count++;
    if (state.company_id) count++;
    if (state.site) count++;
    if (state.job_level) count++;
    if (state.month || state.date_from || state.date_to) count++;
    if (state.mandatory_type) count++;
    if (state.training_source) count++;
    if (state.course_code) count++;
    if (state.training_category) count++;
    if (state.status) count++;
    return count;
});

const activeQueryString = computed(() => {
    const clean = buildCleanPayload();
    const params = new URLSearchParams();
    Object.entries(clean).forEach(([k, v]) => {
        params.append(k, String(v));
    });
    const str = params.toString();
    return str ? `?${str}` : '';
});

const fullExportUrl = computed(() => {
    if (!props.exportUrl) return '';
    const base = props.exportUrl.split('?')[0];
    return `${base}${activeQueryString.value}`;
});

let debounceTimer = null;

const resolvedTargetUrl = computed(() => {
    return props.routeUrl || props.filterUrl || (typeof window !== 'undefined' ? window.location.pathname : '/');
});

const triggerServerFilter = () => {
    const payload = buildCleanPayload();
    emit('filter-change', { ...state, payload });

    router.get(resolvedTargetUrl.value, payload, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const onSearchInput = () => {
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        triggerServerFilter();
    }, 350);
};

const setDateMode = (mode) => {
    state.date_mode = mode;
    if (mode === 'single') {
        state.month = '';
        state.date_to = '';
    } else if (mode === 'range') {
        state.month = '';
    } else if (mode === 'month') {
        state.date_from = '';
        state.date_to = '';
    }
    triggerServerFilter();
};

const onMonthChange = () => {
    state.date_mode = 'month';
    state.date_from = '';
    state.date_to = '';
    triggerServerFilter();
};

const onDepartmentChange = () => {
    if (state.department_id) {
        state.company_id = '';
    }
    triggerServerFilter();
};

const onCompanyChange = () => {
    if (state.company_id) {
        state.department_id = '';
    }
    triggerServerFilter();
};

const resetAllFilters = () => {
    if (debounceTimer) clearTimeout(debounceTimer);
    state.search = '';
    state.department_id = '';
    state.company_id = '';
    state.site = '';
    state.job_level = '';
    state.date_mode = 'month';
    state.month = '';
    state.date_from = '';
    state.date_to = '';
    state.mandatory_type = '';
    state.training_source = '';
    state.course_code = '';
    state.course_id = '';
    state.training_category = '';
    state.status = '';

    emit('reset');
    router.get(resolvedTargetUrl.value, { ...props.extraParams }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

onBeforeUnmount(() => {
    if (debounceTimer) clearTimeout(debounceTimer);
});
</script>

<template>
    <div class="bg-white border border-slate-200 rounded-xl p-3 sm:p-4 mb-4 shadow-sm space-y-3 print:hidden transition-all">
        <!-- ================================================================ -->
        <!-- BARIS 1: PENCARIAN CEPAT GLOBAL, ACTIVE FILTER BADGE, RESET & CSV -->
        <!-- ================================================================ -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
            <!-- 1. Pencarian Cepat Input -->
            <div class="relative flex-1 max-w-xl">
                <input
                    v-model="state.search"
                    @input="onSearchInput"
                    type="search"
                    :placeholder="searchPlaceholder"
                    class="w-full rounded-lg border-slate-300 text-xs py-1.5 pl-8 pr-3 text-slate-800 placeholder-slate-400 focus:border-slate-500 focus:ring-1 focus:ring-slate-400 transition"
                />
                <svg
                    class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M21 21l-4.35-4.35m1.85-5.15a7 7 0 11-14 0 7 7 0 0114 0z"
                    />
                </svg>
            </div>

            <!-- 2. Tombol Reset Filter & Export CSV -->
            <div class="flex items-center gap-2 shrink-0">
                <button
                    v-if="hasActiveFilters"
                    type="button"
                    @click="resetAllFilters"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-rose-50 text-slate-600 hover:text-rose-700 border border-slate-200 text-xs font-semibold transition"
                    title="Bersihkan semua parameter filter"
                >
                    <span class="text-rose-500 font-bold">✕</span>
                    <span>Reset Filter</span>
                    <span class="px-1.5 py-0.2 bg-slate-200 text-slate-700 text-[10px] rounded-full font-bold">
                        {{ activeFiltersCount }}
                    </span>
                </button>

                <slot name="export" :export-url="fullExportUrl" :query-string="activeQueryString">
                    <a
                        v-if="exportUrl"
                        :href="fullExportUrl"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium shadow-sm transition"
                    >
                        <span>⬇ {{ exportLabel }}</span>
                    </a>
                </slot>
            </div>
        </div>

        <!-- ================================================================ -->
        <!-- BARIS 2: 6 ATRIBUT FILTER UTAMA (SESUAI SPESIFIKASI PROJEK)       -->
        <!-- 1. DEPARTEMENT                                                   -->
        <!-- 2. PERUSAHAAN                                                    -->
        <!-- 3. BULAN (DENGAN MINI-SWITCH MODE: BULAN / HARI / RENTANG)       -->
        <!-- 4. JENIS TRAINING (MANDATORY DAN NON MANDATORY)                  -->
        <!-- 5. TRAINING INTERNAL & EXTERNAL                                  -->
        <!-- 6. KODE TRAINING                                                 -->
        <!-- ================================================================ -->
        <div class="pt-2.5 border-t border-slate-100 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-2.5">
            <!-- 1. DEPARTEMENT -->
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1 truncate" title="Departemen (Internal)">
                    DEPARTEMENT
                </label>
                <select
                    v-model="state.department_id"
                    @change="onDepartmentChange"
                    :disabled="!canFilterOrganizations"
                    class="w-full rounded-lg border-slate-300 text-xs py-1.5 px-2 text-slate-800 bg-white focus:border-slate-500 focus:ring-1 focus:ring-slate-400 disabled:bg-slate-100 disabled:cursor-not-allowed transition"
                >
                    <option value="">Semua Departemen</option>
                    <option
                        v-for="dept in departmentList"
                        :key="dept.id || dept"
                        :value="dept.id || dept"
                    >
                        {{ dept.code ? `[${dept.code}] ${dept.name}` : (dept.name || dept) }}
                    </option>
                </select>
            </div>

            <!-- 2. PERUSAHAAN -->
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1 truncate" title="Perusahaan (Kontraktor)">
                    PERUSAHAAN
                </label>
                <select
                    v-model="state.company_id"
                    @change="onCompanyChange"
                    :disabled="!canFilterOrganizations"
                    class="w-full rounded-lg border-slate-300 text-xs py-1.5 px-2 text-slate-800 bg-white focus:border-slate-500 focus:ring-1 focus:ring-slate-400 disabled:bg-slate-100 disabled:cursor-not-allowed transition"
                >
                    <option value="">Semua Perusahaan</option>
                    <option
                        v-for="comp in companyList"
                        :key="comp.id || comp"
                        :value="comp.id || comp"
                    >
                        {{ comp.code ? `[${comp.code}] ${comp.name}` : (comp.name || comp) }}
                    </option>
                </select>
            </div>

            <!-- 3. BULAN -->
            <div v-if="showDateFilter">
                <div class="flex items-center justify-between mb-1">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 truncate" title="Bulan Pelatihan">
                        BULAN
                    </label>
                    <div class="inline-flex rounded bg-slate-100 p-0.5 border border-slate-200 text-[9px] font-semibold">
                        <button
                            type="button"
                            @click="setDateMode('month')"
                            :class="[
                                'px-1 py-0.2 rounded transition',
                                state.date_mode === 'month' ? 'bg-slate-700 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900'
                            ]"
                            title="Filter Berdasarkan Bulan (YYYY-MM)"
                        >
                            Bulan
                        </button>
                        <button
                            type="button"
                            @click="setDateMode('single')"
                            :class="[
                                'px-1 py-0.2 rounded transition',
                                state.date_mode === 'single' ? 'bg-slate-700 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900'
                            ]"
                            title="Filter Berdasarkan 1 Hari Spesifik"
                        >
                            Hari
                        </button>
                        <button
                            type="button"
                            @click="setDateMode('range')"
                            :class="[
                                'px-1 py-0.2 rounded transition',
                                state.date_mode === 'range' ? 'bg-slate-700 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900'
                            ]"
                            title="Filter Berdasarkan Rentang Tanggal"
                        >
                            Rentang
                        </button>
                    </div>
                </div>

                <!-- Mode Bulan (Default) -->
                <div v-if="state.date_mode === 'month'">
                    <input
                        v-model="state.month"
                        @change="onMonthChange"
                        type="month"
                        class="w-full rounded-lg border-slate-300 text-xs py-1.5 px-2 text-slate-800 bg-white focus:border-slate-500 focus:ring-1 focus:ring-slate-400 transition"
                    />
                </div>

                <!-- Mode 1 Hari Spesifik -->
                <div v-else-if="state.date_mode === 'single'">
                    <input
                        v-model="state.date_from"
                        @change="triggerServerFilter"
                        type="date"
                        class="w-full rounded-lg border-slate-300 text-xs py-1.5 px-2 text-slate-800 bg-white focus:border-slate-500 focus:ring-1 focus:ring-slate-400 transition"
                    />
                </div>

                <!-- Mode Rentang Tanggal -->
                <div v-else class="grid grid-cols-2 gap-1">
                    <input
                        v-model="state.date_from"
                        @change="triggerServerFilter"
                        type="date"
                        title="Dari Tanggal"
                        class="w-full rounded-lg border-slate-300 text-xs py-1.5 px-1 text-slate-800 bg-white focus:border-slate-500 focus:ring-1 focus:ring-slate-400"
                    />
                    <input
                        v-model="state.date_to"
                        @change="triggerServerFilter"
                        type="date"
                        title="Sampai Tanggal"
                        class="w-full rounded-lg border-slate-300 text-xs py-1.5 px-1 text-slate-800 bg-white focus:border-slate-500 focus:ring-1 focus:ring-slate-400"
                    />
                </div>
            </div>

            <!-- 4. JENIS TRAINING (MANDATORY DAN NON MANDATORY) -->
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1 truncate" title="Jenis Training (Mandatory vs Non-Mandatory)">
                    JENIS TRAINING (MANDATORY / NON)
                </label>
                <select
                    v-model="state.mandatory_type"
                    @change="triggerServerFilter"
                    class="w-full rounded-lg border-slate-300 text-xs py-1.5 px-2 text-slate-800 bg-white focus:border-slate-500 focus:ring-1 focus:ring-slate-400 transition"
                >
                    <option value="">Semua Jenis (Mandatory &amp; Non)</option>
                    <option value="mandatory">Mandatory (Wajib)</option>
                    <option value="non_mandatory">Non-Mandatory (Pilihan)</option>
                </select>
            </div>

            <!-- 5. TRAINING INTERNAL & EXTERNAL -->
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1 truncate" title="Penyelenggara: Training Internal & External">
                    TRAINING INTERNAL &amp; EXTERNAL
                </label>
                <select
                    v-model="state.training_source"
                    @change="triggerServerFilter"
                    class="w-full rounded-lg border-slate-300 text-xs py-1.5 px-2 text-slate-800 bg-white focus:border-slate-500 focus:ring-1 focus:ring-slate-400 transition"
                >
                    <option value="">Semua (Internal &amp; External)</option>
                    <option value="internal">Internal</option>
                    <option value="external">External</option>
                </select>
            </div>

            <!-- 6. KODE TRAINING -->
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1 truncate" title="Kode Training Terdaftar">
                    KODE TRAINING
                </label>
                <select
                    v-model="state.course_code"
                    @change="triggerServerFilter"
                    class="w-full rounded-lg border-slate-300 text-xs py-1.5 px-2 text-slate-800 bg-white focus:border-slate-500 focus:ring-1 focus:ring-slate-400 font-medium transition"
                >
                    <option value="">Semua Kode Training</option>
                    <option
                        v-for="course in courseCodeList"
                        :key="course.code || course.id"
                        :value="course.code"
                    >
                        [{{ course.code }}] {{ course.title }}
                    </option>
                </select>
            </div>
        </div>

        <!-- ================================================================ -->
        <!-- BARIS 3: ATRIBUT EKSTENSI (SITE OPERASIONAL, GRADE JABATAN, DLL)   -->
        <!-- ================================================================ -->
        <div
            v-if="showExtendedFilters || showStatusFilter || showCategoryFilter"
            class="pt-2 border-t border-slate-100 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-2.5 items-end"
        >
            <!-- 1. Filter Site / Lokasi Operasional Tambang -->
            <div v-if="showExtendedFilters">
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1 truncate" title="Site / Lokasi Tambang Operasional">
                    SITE / LOKASI TAMBANG
                </label>
                <select
                    v-model="state.site"
                    @change="triggerServerFilter"
                    class="w-full rounded-lg border-slate-300 text-xs py-1.5 px-2 text-slate-800 bg-white focus:border-slate-500 focus:ring-1 focus:ring-slate-400 transition"
                >
                    <option value="">Semua Site / Entitas</option>
                    <option v-for="s in siteList" :key="s" :value="s">
                        {{ s }}
                    </option>
                </select>
            </div>

            <!-- 2. Filter Level Jabatan (Job Grade) -->
            <div v-if="showExtendedFilters">
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1 truncate" title="Level Jabatan (Job Grade MDKA Group)">
                    LEVEL JABATAN (GRADE)
                </label>
                <select
                    v-model="state.job_level"
                    @change="triggerServerFilter"
                    class="w-full rounded-lg border-slate-300 text-xs py-1.5 px-2 text-slate-800 bg-white focus:border-slate-500 focus:ring-1 focus:ring-slate-400 transition"
                >
                    <option value="">Semua Level Jabatan</option>
                    <option v-for="lvl in jobLevelList" :key="lvl" :value="lvl">
                        {{ lvl }}
                    </option>
                </select>
            </div>

            <!-- 3. Status Verifikasi / Pendaftaran (jika aktif) -->
            <div v-if="showStatusFilter">
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1 truncate">
                    STATUS PENDAFTARAN
                </label>
                <select
                    v-model="state.status"
                    @change="triggerServerFilter"
                    class="w-full rounded-lg border-slate-300 text-xs py-1.5 px-2 text-slate-800 bg-white focus:border-slate-500 focus:ring-1 focus:ring-slate-400"
                >
                    <option value="">Semua Status</option>
                    <option v-for="st in statusOptions" :key="st.value" :value="st.value">
                        {{ st.label }}
                    </option>
                </select>
            </div>

            <!-- 4. Bidang Training (jika aktif) -->
            <div v-if="showCategoryFilter">
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1 truncate">
                    BIDANG TRAINING
                </label>
                <select
                    v-model="state.training_category"
                    @change="triggerServerFilter"
                    class="w-full rounded-lg border-slate-300 text-xs py-1.5 px-2 text-slate-800 bg-white focus:border-slate-500 focus:ring-1 focus:ring-slate-400"
                >
                    <option value="">Semua Bidang</option>
                    <option v-for="cat in categoryList" :key="cat" :value="cat">
                        {{ cat }}
                    </option>
                </select>
            </div>
        </div>
    </div>
</template>
