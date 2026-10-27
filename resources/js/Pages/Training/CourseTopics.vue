<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    courses: {
        type: Object,
        required: true,
    },
    kpi: {
        type: Object,
        default: () => ({
            total_courses: 0,
            scheduled_courses: 0,
            mandatory_courses: 0,
            internal_courses: 0,
            external_courses: 0,
            total_sessions: 0,
        }),
    },
    filters: {
        type: Object,
        default: () => ({
            search: '',
            category: '',
            mandatory_type: '',
            training_source: '',
            trainer_id: null,
            schedule_status: '',
        }),
    },
    competencies: {
        type: Array,
        default: () => [],
    },
    trainers: {
        type: Array,
        default: () => [],
    },
    categories: {
        type: Array,
        default: () => [],
    },
    canManage: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success || '');
const flashError = computed(() => page.props.flash?.error || '');

// Filter Form State
const localFilters = reactive({
    search: props.filters.search || '',
    category: props.filters.category || '',
    mandatory_type: props.filters.mandatory_type || '',
    training_source: props.filters.training_source || '',
    trainer_id: props.filters.trainer_id ? String(props.filters.trainer_id) : '',
    schedule_status: props.filters.schedule_status || '',
});

const applyFilters = () => {
    router.get('/training-program/courses', {
        search: localFilters.search || undefined,
        category: localFilters.category || undefined,
        mandatory_type: localFilters.mandatory_type || undefined,
        training_source: localFilters.training_source || undefined,
        trainer_id: localFilters.trainer_id || undefined,
        schedule_status: localFilters.schedule_status || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const resetFilters = () => {
    localFilters.search = '';
    localFilters.category = '';
    localFilters.mandatory_type = '';
    localFilters.training_source = '';
    localFilters.trainer_id = '';
    localFilters.schedule_status = '';
    applyFilters();
};

const hasActiveFilters = computed(() => {
    return Boolean(
        localFilters.search ||
        localFilters.category ||
        localFilters.mandatory_type ||
        localFilters.training_source ||
        localFilters.trainer_id ||
        localFilters.schedule_status
    );
});

// View Toggle: Grid Card vs Data Table
const viewMode = ref('cards'); // 'cards' | 'table'

// ==========================================
// MODAL: BUAT / EDIT TOPIK PELATIHAN
// ==========================================
const showCourseModal = ref(false);
const isEditing = ref(false);
const editingCourseId = ref(null);

const courseForm = useForm({
    code: '',
    title: '',
    description: '',
    training_category: 'Safety',
    is_mandatory: true,
    training_source: 'internal',
    validity_months: 24,
    start_date: new Date().toISOString().slice(0, 10),
    end_date: new Date(Date.now() + 180 * 86400000).toISOString().slice(0, 10),
    skill_id: '',
    granted_skill_level: 3,
    trainer_id: '',
    // Quick Calendar Schedule
    create_schedule: false,
    session_title: '',
    session_date: '',
    start_time: '08:00',
    end_time: '16:00',
    duration_hours: 8.0,
    location: 'Ruang Training Utama',
    quota: 25,
});

const openCreateModal = () => {
    isEditing.value = false;
    editingCourseId.value = null;
    courseForm.reset();
    courseForm.clearErrors();
    courseForm.code = `MDKA-TRN-${String(Math.floor(100 + Math.random() * 900))}`;
    courseForm.training_category = 'Safety';
    courseForm.is_mandatory = true;
    courseForm.training_source = 'internal';
    courseForm.validity_months = 24;
    courseForm.granted_skill_level = 3;
    courseForm.start_date = new Date().toISOString().slice(0, 10);
    courseForm.end_date = new Date(Date.now() + 180 * 86400000).toISOString().slice(0, 10);
    courseForm.create_schedule = false;
    courseForm.duration_hours = 8.0;
    if (props.competencies.length > 0) {
        courseForm.skill_id = props.competencies[0].id;
    }
    if (props.trainers.length > 0) {
        courseForm.trainer_id = props.trainers[0].id;
    }
    showCourseModal.value = true;
};

const openEditModal = (course) => {
    isEditing.value = true;
    editingCourseId.value = course.id;
    courseForm.clearErrors();
    courseForm.code = course.code;
    courseForm.title = course.title;
    courseForm.description = course.description || '';
    courseForm.training_category = course.training_category || 'Safety';
    courseForm.is_mandatory = Boolean(course.is_mandatory);
    courseForm.training_source = course.training_source || 'internal';
    courseForm.validity_months = course.validity_months || 24;
    courseForm.start_date = course.start_date || new Date().toISOString().slice(0, 10);
    courseForm.end_date = course.end_date || new Date(Date.now() + 180 * 86400000).toISOString().slice(0, 10);
    courseForm.skill_id = course.skill_id || '';
    courseForm.granted_skill_level = course.granted_skill_level || 3;
    courseForm.trainer_id = course.trainer_id || '';
    courseForm.create_schedule = false;
    showCourseModal.value = true;
};

const submitCourseForm = () => {
    if (isEditing.value) {
        courseForm.put(`/training/courses/${editingCourseId.value}`, {
            onSuccess: () => {
                showCourseModal.value = false;
            },
        });
    } else {
        courseForm.post('/training/courses', {
            onSuccess: () => {
                showCourseModal.value = false;
            },
        });
    }
};

const deleteCourse = (course) => {
    if (confirm(`Apakah Anda yakin ingin menghapus topik pelatihan [${course.code}] ${course.title}?`)) {
        router.delete(`/training/courses/${course.id}`);
    }
};

// ==========================================
// MODAL: JADWALKAN KE KALENDER
// ==========================================
const showScheduleModal = ref(false);
const selectedCourseForSchedule = ref(null);

const scheduleForm = useForm({
    title: '',
    session_date: '',
    start_time: '08:00',
    end_time: '16:00',
    duration_hours: 8.0,
    trainer_id: '',
    location: 'Ruang Training Utama',
    quota: 25,
    plan_status: 'approved',
});

const openScheduleModal = (course) => {
    selectedCourseForSchedule.value = course;
    scheduleForm.clearErrors();
    scheduleForm.title = `Sesi Pelatihan: ${course.title}`;
    scheduleForm.session_date = new Date(Date.now() + 86400000 * 2).toISOString().slice(0, 10);
    scheduleForm.start_time = '08:00';
    scheduleForm.end_time = '16:00';
    scheduleForm.duration_hours = course.duration_hours || 8.0;
    scheduleForm.trainer_id = course.trainer_id || (props.trainers[0]?.id ?? '');
    scheduleForm.location = 'Ruang Training Utama';
    scheduleForm.quota = 25;
    scheduleForm.plan_status = 'approved';
    showScheduleModal.value = true;
};

const submitScheduleForm = () => {
    if (!selectedCourseForSchedule.value) return;
    scheduleForm.post(`/training-program/courses/${selectedCourseForSchedule.value.id}/schedule`, {
        onSuccess: () => {
            showScheduleModal.value = false;
        },
    });
};

// ==========================================
// MODAL: DETAIL 360° KOLABORASI TOPIK
// ==========================================
const showDetailModal = ref(false);
const detailCourse = ref(null);
const detailTab = ref('overview'); // 'overview' | 'competency' | 'schedule' | 'syllabus'

const openDetailModal = (course) => {
    detailCourse.value = course;
    detailTab.value = 'overview';
    showDetailModal.value = true;
};

const levelName = (lvl) => {
    switch (Number(lvl)) {
        case 1: return 'Level 1 - Awareness / Pemula';
        case 2: return 'Level 2 - Basic / Operasional';
        case 3: return 'Level 3 - Competent / Mandiri';
        case 4: return 'Level 4 - Advanced / Pengawas';
        case 5: return 'Level 5 - Expert / Ahli Utama';
        default: return `Level ${lvl}`;
    }
};

const getLevelBadgeCls = (lvl) => {
    switch (Number(lvl)) {
        case 1: return 'bg-slate-100 text-slate-700 border-slate-200';
        case 2: return 'bg-sky-50 text-sky-700 border-sky-200';
        case 3: return 'bg-emerald-50 text-emerald-800 border-emerald-200 font-bold';
        case 4: return 'bg-purple-50 text-purple-700 border-purple-200 font-bold';
        case 5: return 'bg-amber-50 text-amber-800 border-amber-300 font-extrabold';
        default: return 'bg-slate-100 text-slate-700 border-slate-200';
    }
};

const selectedSkillDetail = computed(() => {
    if (!courseForm.skill_id) return null;
    return props.competencies.find(s => s.id === Number(courseForm.skill_id)) || null;
});
</script>

<template>
    <AdminLayout>
        <Head title="Program & Topik Pelatihan (Training Topics) — L&D PANI" />

        <div class="space-y-6 max-w-7xl mx-auto pb-12">
            <!-- FLASH ALERTS -->
            <div v-if="flashSuccess" class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold flex items-center justify-between shadow-xs">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span>{{ flashSuccess }}</span>
                </div>
            </div>
            <div v-if="flashError" class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold flex items-center justify-between shadow-xs">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>{{ flashError }}</span>
                </div>
            </div>

            <!-- 1. HEADER SECTION -->
            <div class="bg-white border border-slate-200 rounded-2xl p-5 sm:p-6 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2.5">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-extrabold uppercase bg-blue-50 text-blue-700 border border-blue-200">
                            SEKSI 4: TRAINING PROGRAM
                        </span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-extrabold uppercase bg-emerald-50 text-emerald-700 border border-emerald-200">
                            COLLABORATIVE ARCHITECTURE
                        </span>
                    </div>
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight mt-1.5">
                        Topik &amp; Program Pelatihan (Training Topics)
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-500 mt-1 max-w-3xl">
                        Pusat perancangan Topik Pelatihan yang saling berkolaborasi dengan 
                        <strong class="text-slate-700">Competency List</strong>, 
                        <strong class="text-slate-700">Jam Ajar (JP)</strong>, 
                        <strong class="text-slate-700">Instruktur / Trainer</strong>, 
                        <strong class="text-slate-700">Peserta</strong>, dan 
                        <strong class="text-slate-700">Kalender Pelatihan (ATMP)</strong>.
                    </p>
                </div>
                <div class="flex items-center gap-2.5 shrink-0">
                    <Link
                        href="/training-program/schedules"
                        class="inline-flex items-center gap-2 px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold transition-all shadow-xs"
                    >
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <span>Buka Kalender ATMP</span>
                    </Link>
                    <button
                        v-if="canManage"
                        type="button"
                        @click="openCreateModal"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold transition-all shadow-sm shadow-blue-500/20"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span>Buat Topik Baru</span>
                    </button>
                </div>
            </div>

            <!-- 2. EXECUTIVE 4-PILLAR KPI SUMMARY CARDS -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                <!-- KPI 1: Total Topics -->
                <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Program Topik</span>
                        <span class="w-7 h-7 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-xs">📚</span>
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-black text-slate-900">{{ kpi.total_courses }}</span>
                        <span class="text-xs text-slate-500 font-semibold">Topik Aktif</span>
                    </div>
                    <div class="mt-2 text-[11px] text-slate-500 flex items-center gap-1.5">
                        <span class="text-emerald-600 font-bold">{{ kpi.internal_courses }} Internal</span>
                        <span>•</span>
                        <span class="text-indigo-600 font-bold">{{ kpi.external_courses }} External</span>
                    </div>
                </div>

                <!-- KPI 2: Kalender Synchronization -->
                <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Jadwal di Kalender</span>
                        <span class="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold text-xs">📅</span>
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-black text-emerald-600">{{ kpi.scheduled_courses }}</span>
                        <span class="text-xs text-slate-500 font-semibold">/ {{ kpi.total_courses }} Terjadwal</span>
                    </div>
                    <div class="mt-2 text-[11px] text-slate-500">
                        <span class="font-bold text-slate-700">{{ kpi.total_sessions }} Sesi Pertemuan</span> di Kalender
                    </div>
                </div>

                <!-- KPI 3: Competency Alignment -->
                <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Competency List</span>
                        <span class="w-7 h-7 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center font-bold text-xs">🎯</span>
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-black text-purple-600">100%</span>
                        <span class="text-xs text-slate-500 font-semibold">Tersinkronisasi</span>
                    </div>
                    <div class="mt-2 text-[11px] text-slate-500">
                        Setiap topik menganugerahkan <strong class="text-slate-700">1 Kompetensi</strong>
                    </div>
                </div>

                <!-- KPI 4: Mandatory Compliance -->
                <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Mandatory K3 &amp; SMKP</span>
                        <span class="w-7 h-7 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center font-bold text-xs">⚡</span>
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-black text-rose-600">{{ kpi.mandatory_courses }}</span>
                        <span class="text-xs text-slate-500 font-semibold">Wajib Operasional</span>
                    </div>
                    <div class="mt-2 text-[11px] text-slate-500">
                        {{ kpi.total_courses - kpi.mandatory_courses }} Program Pilihan (Non-Mandatory)
                    </div>
                </div>
            </div>

            <!-- 3. FILTER BAR -->
            <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs space-y-3">
                <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
                    <!-- Search Input -->
                    <div class="relative flex-1">
                        <svg class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0118 0z"/></svg>
                        <input
                            v-model="localFilters.search"
                            @keyup.enter="applyFilters"
                            type="text"
                            placeholder="Cari kode program, nama topik pelatihan, nama kompetensi, atau nama trainer..."
                            class="w-full pl-10 pr-4 py-2 rounded-xl border-slate-200 text-xs text-slate-800 placeholder-slate-400 focus:border-blue-500 focus:ring-blue-500"
                        />
                    </div>

                    <!-- Action Controls & View Toggler -->
                    <div class="flex items-center gap-2 shrink-0">
                        <button
                            type="button"
                            @click="applyFilters"
                            class="px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold transition-colors shadow-xs"
                        >
                            Terapkan Filter
                        </button>
                        <button
                            v-if="hasActiveFilters"
                            type="button"
                            @click="resetFilters"
                            class="px-3 py-2 rounded-xl border border-slate-200 hover:bg-slate-100 text-rose-600 text-xs font-bold transition-colors"
                        >
                            ✕ Reset
                        </button>
                        <div class="h-6 w-px bg-slate-200 mx-1"></div>
                        <div class="flex items-center border border-slate-200 rounded-xl overflow-hidden p-0.5 bg-slate-50">
                            <button
                                type="button"
                                @click="viewMode = 'cards'"
                                :class="['px-2.5 py-1 text-xs font-bold rounded-lg transition-colors', viewMode === 'cards' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-900']"
                                title="Tampilan Kartu Kolaborasi"
                            >
                                <svg class="w-3.5 h-3.5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                                Kartu
                            </button>
                            <button
                                type="button"
                                @click="viewMode = 'table'"
                                :class="['px-2.5 py-1 text-xs font-bold rounded-lg transition-colors', viewMode === 'table' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-900']"
                                title="Tampilan Tabel Master"
                            >
                                <svg class="w-3.5 h-3.5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                                Tabel
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Dropdown Select Filters -->
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-2.5 pt-2 border-t border-slate-100">
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Bidang / Kategori</label>
                        <select v-model="localFilters.category" @change="applyFilters" class="w-full rounded-lg border-slate-200 text-xs py-1.5 px-2 bg-white">
                            <option value="">Semua Bidang</option>
                            <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Jenis Program</label>
                        <select v-model="localFilters.mandatory_type" @change="applyFilters" class="w-full rounded-lg border-slate-200 text-xs py-1.5 px-2 bg-white">
                            <option value="">Semua Sifat</option>
                            <option value="mandatory">Mandatory (Wajib)</option>
                            <option value="non_mandatory">Non-Mandatory (Pilihan)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Pelaksana / Sumber</label>
                        <select v-model="localFilters.training_source" @change="applyFilters" class="w-full rounded-lg border-slate-200 text-xs py-1.5 px-2 bg-white">
                            <option value="">Internal &amp; External</option>
                            <option value="internal">Internal (L&amp;D MDKA)</option>
                            <option value="external">External (LSP / Vendor Luar)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Trainer Pengampu</label>
                        <select v-model="localFilters.trainer_id" @change="applyFilters" class="w-full rounded-lg border-slate-200 text-xs py-1.5 px-2 bg-white">
                            <option value="">Semua Instruktur</option>
                            <option v-for="t in trainers" :key="t.id" :value="String(t.id)">{{ t.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Status Kalender</label>
                        <select v-model="localFilters.schedule_status" @change="applyFilters" class="w-full rounded-lg border-slate-200 text-xs py-1.5 px-2 bg-white">
                            <option value="">Semua Status</option>
                            <option value="scheduled">Sudah Ada di Kalender</option>
                            <option value="unscheduled">Belum Dijadwalkan</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- 4. CONTENT VIEW: CARDS MODE -->
            <div v-if="viewMode === 'cards'" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                <div
                    v-for="c in courses.data"
                    :key="c.id"
                    class="bg-white border border-slate-200 rounded-2xl p-5 shadow-xs hover:shadow-md transition-all duration-200 flex flex-col justify-between group"
                >
                    <div class="space-y-4">
                        <!-- Top Badges & Program Code -->
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="font-mono text-[11px] font-extrabold bg-slate-900 text-white px-2 py-0.5 rounded-md tracking-wider">
                                    {{ c.code }}
                                </span>
                                <span :class="['px-2 py-0.5 rounded-md text-[10px] font-extrabold uppercase border', c.is_mandatory ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-slate-50 text-slate-600 border-slate-200']">
                                    {{ c.is_mandatory ? 'Mandatory' : 'Non-Mandatory' }}
                                </span>
                                <span :class="['px-2 py-0.5 rounded-md text-[10px] font-extrabold uppercase border', c.training_source === 'external' ? 'bg-indigo-50 text-indigo-700 border-indigo-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200']">
                                    {{ c.training_source }}
                                </span>
                            </div>
                            <span class="text-[10px] font-bold text-slate-400 uppercase bg-slate-100 px-2 py-0.5 rounded-full">
                                {{ c.training_category }}
                            </span>
                        </div>

                        <!-- 1. NAMA PELATIHAN -->
                        <div>
                            <h3 class="text-base font-bold text-slate-900 group-hover:text-blue-600 transition-colors leading-snug">
                                {{ c.title }}
                            </h3>
                            <p class="text-xs text-slate-500 mt-1 line-clamp-2">
                                {{ c.description || 'Program pembinaan standar kompetensi operasional dan keselamatan tambang Pani Project.' }}
                            </p>
                        </div>

                        <!-- 2. NAMA KOMPETENSI (Kolaborasi Competency List) -->
                        <div class="bg-purple-50/60 border border-purple-100 rounded-xl p-3">
                            <div class="flex items-center justify-between text-[10px] font-bold text-purple-700 uppercase tracking-wider mb-1">
                                <span>🎯 Kompetensi yang Dianugerahkan</span>
                                <span :class="['px-1.5 py-0.2 rounded border text-[9px]', getLevelBadgeCls(c.granted_skill_level)]">
                                    ⭐ Level {{ c.granted_skill_level }}
                                </span>
                            </div>
                            <p class="text-xs font-bold text-slate-900">
                                {{ c.competency ? c.competency.name : 'Kompetensi Umum Terkait' }}
                            </p>
                            <div class="flex items-center justify-between text-[10px] text-slate-500 mt-1">
                                <span class="font-mono text-purple-900 font-bold">[{{ c.competency?.code || 'KOMP-REF' }}]</span>
                                <span>Masa Berlaku: <strong class="text-slate-700">{{ c.validity_months ? c.validity_months + ' Bulan' : 'Permanen' }}</strong></span>
                            </div>
                        </div>

                        <!-- 3. JAM AJAR & SILABUS MODUL -->
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div class="bg-slate-50 border border-slate-200 rounded-xl p-2.5">
                                <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">🕒 Jam Ajar (JP)</span>
                                <span class="font-extrabold text-slate-900 text-sm mt-0.5 block">
                                    {{ c.duration_hours }} Jam Ajar
                                </span>
                                <span class="text-[10px] text-slate-500">{{ c.modules?.length || 4 }} Modul Silabus</span>
                            </div>

                            <!-- 4. PENGAJAR / TRAINER -->
                            <div class="bg-slate-50 border border-slate-200 rounded-xl p-2.5">
                                <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">👤 Instruktur / Trainer</span>
                                <span class="font-bold text-slate-900 truncate block mt-0.5" :title="c.trainer?.name || 'Tim L&D'">
                                    {{ c.trainer?.name || 'Tim Instruktur L&D' }}
                                </span>
                                <span class="text-[10px] text-slate-500 font-mono">{{ c.trainer?.nik || 'ID Trainer' }}</span>
                            </div>
                        </div>

                        <!-- 5. PESERTA & KUOTA KELAS -->
                        <div class="border-t border-slate-100 pt-3">
                            <div class="flex items-center justify-between text-xs mb-1">
                                <span class="text-slate-500 font-medium">Peserta Terdaftar</span>
                                <span class="font-bold text-slate-800">
                                    {{ c.approved_enrolled }} Peserta
                                    <span v-if="c.passed_enrolled" class="text-emerald-600 font-semibold text-[11px]">({{ c.passed_enrolled }} Lulus)</span>
                                </span>
                            </div>
                            <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden">
                                <div
                                    class="bg-blue-600 h-full rounded-full transition-all"
                                    :style="{ width: Math.min(100, Math.max(10, (c.approved_enrolled / 30) * 100)) + '%' }"
                                ></div>
                            </div>
                        </div>

                        <!-- 6. JADWAL KALENDER AKTIF -->
                        <div class="bg-slate-50 border border-slate-200 rounded-xl p-3 flex items-center justify-between">
                            <div class="min-w-0">
                                <span class="block text-[10px] font-bold uppercase text-slate-400 tracking-wider">Jadwal Kalender Terdekat</span>
                                <div v-if="c.next_session" class="mt-0.5">
                                    <span class="text-xs font-bold text-slate-900 block truncate">
                                        📅 {{ c.next_session.date }} ({{ c.next_session.start_time }}-{{ c.next_session.end_time }})
                                    </span>
                                    <span class="text-[10px] text-slate-500 truncate block">
                                        {{ c.next_session.location || 'Ruang Training Utama' }} • {{ c.next_session.plan_status === 'approved' ? 'Approved' : 'Pending' }}
                                    </span>
                                </div>
                                <div v-else class="text-[11px] font-semibold text-amber-700 mt-0.5">
                                    ⚠️ Belum ada jadwal di kalender
                                </div>
                            </div>
                            <span v-if="c.sessions_count > 0" class="px-2 py-1 rounded-lg bg-emerald-100 text-emerald-800 text-[10px] font-extrabold shrink-0 border border-emerald-200">
                                {{ c.sessions_count }} Sesi
                            </span>
                        </div>
                    </div>

                    <!-- CARD FOOTER ACTIONS -->
                    <div class="pt-4 border-t border-slate-100 mt-4 flex items-center justify-between gap-2">
                        <button
                            type="button"
                            @click="openDetailModal(c)"
                            class="px-2.5 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-100 text-slate-700 text-xs font-bold transition-colors"
                        >
                            Detail 360°
                        </button>

                        <div class="flex items-center gap-1.5">
                            <button
                                v-if="canManage"
                                type="button"
                                @click="openScheduleModal(c)"
                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition-colors shadow-xs"
                                title="Jadwalkan langsung ke kalender"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <span>+ Kalender</span>
                            </button>

                            <button
                                v-if="canManage"
                                type="button"
                                @click="openEditModal(c)"
                                class="p-1.5 rounded-lg border border-slate-200 text-slate-600 hover:text-blue-600 hover:bg-blue-50 transition-colors"
                                title="Ubah Topik"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </button>

                            <button
                                v-if="canManage"
                                type="button"
                                @click="deleteCourse(c)"
                                class="p-1.5 rounded-lg border border-slate-200 text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors"
                                title="Hapus Topik"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 5. CONTENT VIEW: TABLE MODE -->
            <div v-else class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 border-b border-slate-200 text-[10px] font-extrabold uppercase text-slate-500 tracking-wider">
                            <tr>
                                <th class="py-3 px-4">Kode &amp; Topik Pelatihan</th>
                                <th class="py-3 px-4">Kompetensi Acuan &amp; Level</th>
                                <th class="py-3 px-4">Jam Ajar (JP)</th>
                                <th class="py-3 px-4">Pengajar / Trainer</th>
                                <th class="py-3 px-4 text-center">Peserta</th>
                                <th class="py-3 px-4">Jadwal di Kalender</th>
                                <th class="py-3 px-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="c in courses.data" :key="c.id" class="hover:bg-slate-50/80 transition-colors">
                                <!-- Topik & Kode -->
                                <td class="py-3 px-4 min-w-[220px]">
                                    <div class="flex items-center gap-1.5 mb-1">
                                        <span class="font-mono text-[10px] font-bold bg-slate-900 text-white px-1.5 py-0.2 rounded">
                                            {{ c.code }}
                                        </span>
                                        <span :class="['px-1.5 py-0.2 rounded text-[9px] font-bold uppercase border', c.is_mandatory ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-slate-50 text-slate-600 border-slate-200']">
                                            {{ c.is_mandatory ? 'M' : 'NM' }}
                                        </span>
                                        <span :class="['px-1.5 py-0.2 rounded text-[9px] font-bold uppercase border', c.training_source === 'external' ? 'bg-indigo-50 text-indigo-700 border-indigo-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200']">
                                            {{ c.training_source }}
                                        </span>
                                    </div>
                                    <div class="font-bold text-slate-900">{{ c.title }}</div>
                                    <div class="text-[10px] text-slate-500">{{ c.training_category }}</div>
                                </td>

                                <!-- Kompetensi & Level -->
                                <td class="py-3 px-4 min-w-[200px]">
                                    <div class="font-semibold text-slate-900">
                                        {{ c.competency ? c.competency.name : 'Kompetensi Terkait' }}
                                    </div>
                                    <div class="flex items-center gap-1.5 mt-0.5 text-[10px]">
                                        <span class="font-mono text-purple-700 font-bold">[{{ c.competency?.code || 'KOMP' }}]</span>
                                        <span :class="['px-1.5 py-0.2 rounded border text-[9px]', getLevelBadgeCls(c.granted_skill_level)]">
                                            Level {{ c.granted_skill_level }}
                                        </span>
                                    </div>
                                </td>

                                <!-- Jam Ajar -->
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <span class="font-bold text-slate-900 text-xs block">
                                        🕒 {{ c.duration_hours }} Jam Ajar
                                    </span>
                                    <span class="text-[10px] text-slate-500 font-mono">
                                        {{ c.modules?.length || 4 }} Modul
                                    </span>
                                </td>

                                <!-- Trainer -->
                                <td class="py-3 px-4 min-w-[150px]">
                                    <div class="font-bold text-slate-900">{{ c.trainer?.name || 'Tim Instruktur L&D' }}</div>
                                    <div class="text-[10px] text-slate-500 font-mono">{{ c.trainer?.nik || '-' }}</div>
                                </td>

                                <!-- Peserta -->
                                <td class="py-3 px-4 text-center whitespace-nowrap">
                                    <span class="font-bold text-slate-900 text-xs">{{ c.approved_enrolled }}</span>
                                    <span class="text-slate-400 text-[10px]"> terdaftar</span>
                                    <div v-if="c.passed_enrolled" class="text-[10px] text-emerald-600 font-bold">
                                        {{ c.passed_enrolled }} Lulus
                                    </div>
                                </td>

                                <!-- Jadwal Kalender -->
                                <td class="py-3 px-4 min-w-[180px]">
                                    <div v-if="c.next_session" class="text-xs">
                                        <span class="font-bold text-slate-900">📅 {{ c.next_session.date }}</span>
                                        <div class="text-[10px] text-slate-500 font-mono">
                                            {{ c.next_session.start_time }}-{{ c.next_session.end_time }} ({{ c.next_session.duration_hours }} Jam)
                                        </div>
                                    </div>
                                    <span v-else class="text-[10px] font-bold text-amber-700 bg-amber-50 px-2 py-0.5 rounded border border-amber-200">
                                        Belum Terjadwal
                                    </span>
                                </td>

                                <!-- Aksi -->
                                <td class="py-3 px-4 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button
                                            type="button"
                                            @click="openDetailModal(c)"
                                            class="p-1 rounded-md border border-slate-200 text-slate-600 hover:bg-slate-100"
                                            title="Detail 360°"
                                        >
                                            👁️
                                        </button>
                                        <button
                                            v-if="canManage"
                                            type="button"
                                            @click="openScheduleModal(c)"
                                            class="px-2 py-1 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white text-[10px] font-bold transition-colors"
                                            title="Jadwalkan ke Kalender"
                                        >
                                            + Kalender
                                        </button>
                                        <button
                                            v-if="canManage"
                                            type="button"
                                            @click="openEditModal(c)"
                                            class="p-1 rounded-md border border-slate-200 text-slate-600 hover:bg-blue-50"
                                            title="Ubah"
                                        >
                                            ✏️
                                        </button>
                                        <button
                                            v-if="canManage"
                                            type="button"
                                            @click="deleteCourse(c)"
                                            class="p-1 rounded-md border border-slate-200 text-rose-500 hover:bg-rose-50"
                                            title="Hapus"
                                        >
                                            🗑️
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- PAGINATION -->
            <div v-if="courses.links?.length > 3" class="flex justify-center items-center gap-1 pt-4">
                <template v-for="(link, idx) in courses.links" :key="idx">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        v-html="link.label"
                        :class="['px-3 py-1.5 rounded-lg text-xs font-bold transition-colors', link.active ? 'bg-blue-600 text-white' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50']"
                    />
                    <span v-else v-html="link.label" class="px-3 py-1.5 text-xs text-slate-400"></span>
                </template>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- MODAL: BUAT / EDIT TOPIK PELATIHAN         -->
        <!-- ========================================== -->
        <div v-if="showCourseModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center z-50 p-4" @click.self="showCourseModal = false">
            <div class="bg-white border border-slate-300 rounded-2xl w-full max-w-2xl p-6 shadow-2xl max-h-[92vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b border-slate-200 pb-3 mb-4">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">
                            {{ isEditing ? 'Ubah Topik & Program Pelatihan' : 'Buat Topik / Program Pelatihan Baru' }}
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Menghubungkan Nama Pelatihan, Kompetensi Acuan, Jam Ajar, Pengajar &amp; Kalender.
                        </p>
                    </div>
                    <button type="button" @click="showCourseModal = false" class="text-slate-400 hover:text-slate-800 font-bold text-lg">✕</button>
                </div>

                <form @submit.prevent="submitCourseForm" class="space-y-4 text-xs">
                    <!-- Row 1: Code & Title -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Kode Topik / Program *</label>
                            <input v-model="courseForm.code" type="text" required placeholder="Contoh: MDKA-TRN-101" class="w-full rounded-xl border-slate-300 font-mono text-xs uppercase px-3 py-2 font-bold" />
                            <p v-if="courseForm.errors.code" class="text-rose-600 mt-0.5 text-[11px]">{{ courseForm.errors.code }}</p>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block font-bold text-slate-700 mb-1">Nama / Judul Pelatihan *</label>
                            <input v-model="courseForm.title" type="text" required placeholder="Contoh: Working at Height Safety Certification" class="w-full rounded-xl border-slate-300 text-xs px-3 py-2" />
                            <p v-if="courseForm.errors.title" class="text-rose-600 mt-0.5 text-[11px]">{{ courseForm.errors.title }}</p>
                        </div>
                    </div>

                    <!-- Row 2: Description -->
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Deskripsi &amp; Silabus Ringkas</label>
                        <textarea v-model="courseForm.description" rows="2" placeholder="Jelaskan tujuan pelatihan, sasaran operasional, dan materi pokok..." class="w-full rounded-xl border-slate-300 text-xs px-3 py-2"></textarea>
                    </div>

                    <!-- Row 3: Category, Source & Mandatory -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Bidang / Kategori *</label>
                            <select v-model="courseForm.training_category" required class="w-full rounded-xl border-slate-300 text-xs px-3 py-2 bg-white">
                                <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Sifat Pelatihan *</label>
                            <select v-model="courseForm.is_mandatory" required class="w-full rounded-xl border-slate-300 text-xs px-3 py-2 bg-white">
                                <option :value="true">Mandatory (Wajib K3 / SMKP)</option>
                                <option :value="false">Non-Mandatory (Pilihan / Refresh)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Pelaksana / Sumber *</label>
                            <select v-model="courseForm.training_source" required class="w-full rounded-xl border-slate-300 text-xs px-3 py-2 bg-white">
                                <option value="internal">Internal (L&amp;D Site Pani)</option>
                                <option value="external">External (LSP / KaIT ESDM / Vendor)</option>
                            </select>
                        </div>
                    </div>

                    <!-- SECTION: KOLABORASI DENGAN COMPETENCY LIST -->
                    <div class="bg-purple-50/60 border border-purple-200 rounded-xl p-4 space-y-3">
                        <div class="flex items-center gap-2">
                            <span class="w-6 h-6 rounded-lg bg-purple-600 text-white flex items-center justify-center font-bold text-xs">🎯</span>
                            <h4 class="font-bold text-slate-900 text-xs">Kolaborasi Competency List</h4>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="sm:col-span-2">
                                <label class="block font-bold text-slate-700 mb-1">Pilih Kompetensi Acuan *</label>
                                <select v-model="courseForm.skill_id" required class="w-full rounded-xl border-slate-300 text-xs px-3 py-2 bg-white font-medium">
                                    <option value="" disabled>-- Pilih Kompetensi dari Master List --</option>
                                    <option v-for="s in competencies" :key="s.id" :value="s.id">
                                        [{{ s.code }}] {{ s.name }} ({{ s.category }})
                                    </option>
                                </select>
                                <p v-if="courseForm.errors.skill_id" class="text-rose-600 mt-0.5 text-[11px]">{{ courseForm.errors.skill_id }}</p>
                            </div>
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Level yang Dianugerahkan *</label>
                                <select v-model="courseForm.granted_skill_level" required class="w-full rounded-xl border-slate-300 text-xs px-3 py-2 bg-white font-bold">
                                    <option :value="1">Level 1 - Awareness / Pemula</option>
                                    <option :value="2">Level 2 - Basic / Operasional</option>
                                    <option :value="3">Level 3 - Competent / Mandiri</option>
                                    <option :value="4">Level 4 - Advanced / Pengawas</option>
                                    <option :value="5">Level 5 - Expert / Ahli Utama</option>
                                </select>
                            </div>
                        </div>
                        <div v-if="selectedSkillDetail" class="text-[11px] text-purple-900 bg-white/80 p-2 rounded-lg border border-purple-100 flex items-center justify-between">
                            <span>Kategori: <strong>{{ selectedSkillDetail.category }}</strong> • Tipe: <strong>{{ selectedSkillDetail.competency_type }}</strong></span>
                            <span class="font-bold text-emerald-700">✓ Terhubung ke Master Competency List</span>
                        </div>
                    </div>

                    <!-- SECTION: PENGAJAR / TRAINER & JAM AJAR -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Instruktur / Trainer Utama</label>
                            <select v-model="courseForm.trainer_id" class="w-full rounded-xl border-slate-300 text-xs px-3 py-2 bg-white font-medium">
                                <option value="" disabled>-- Pilih Instruktur Pengampu --</option>
                                <option v-for="t in trainers" :key="t.id" :value="t.id">
                                    {{ t.name }} ({{ t.department || 'L&D' }})
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Masa Berlaku Sertifikat</label>
                            <select v-model="courseForm.validity_months" class="w-full rounded-xl border-slate-300 text-xs px-3 py-2 bg-white font-medium">
                                <option :value="null">Berlaku Selamanya (Permanen)</option>
                                <option :value="12">12 Bulan (1 Tahun)</option>
                                <option :value="24">24 Bulan (2 Tahun)</option>
                                <option :value="36">36 Bulan (3 Tahun)</option>
                            </select>
                        </div>
                    </div>

                    <!-- OPTION: LANGSUNG BUAT JADWAL KE KALENDER -->
                    <div v-if="!isEditing" class="border border-slate-200 rounded-xl p-3 bg-slate-50 space-y-3">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input v-model="courseForm.create_schedule" type="checkbox" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                            <span class="font-bold text-slate-800 text-xs">Langsung buat Sesi Jadwal di Kalender Pelatihan (ATMP)</span>
                        </label>

                        <div v-if="courseForm.create_schedule" class="pt-2 border-t border-slate-200 space-y-3">
                            <div class="grid grid-cols-1 sm:grid-cols-4 gap-2">
                                <div class="sm:col-span-2">
                                    <label class="block font-bold text-slate-700 mb-1">Judul Sesi</label>
                                    <input v-model="courseForm.session_title" type="text" placeholder="Contoh: Sesi Pagi - Batch 1" class="w-full rounded-lg border-slate-300 text-xs px-2.5 py-1.5" />
                                </div>
                                <div>
                                    <label class="block font-bold text-slate-700 mb-1">Tanggal</label>
                                    <input v-model="courseForm.session_date" type="date" class="w-full rounded-lg border-slate-300 text-xs px-2 py-1.5 font-mono" />
                                </div>
                                <div>
                                    <label class="block font-bold text-slate-700 mb-1">Jam Ajar (JP)</label>
                                    <input v-model="courseForm.duration_hours" type="number" step="0.5" class="w-full rounded-lg border-slate-300 text-xs px-2 py-1.5 font-mono" />
                                </div>
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <div>
                                    <label class="block font-bold text-slate-700 mb-1">Jam Mulai</label>
                                    <input v-model="courseForm.start_time" type="time" class="w-full rounded-lg border-slate-300 text-xs px-2 py-1.5 font-mono" />
                                </div>
                                <div>
                                    <label class="block font-bold text-slate-700 mb-1">Jam Selesai</label>
                                    <input v-model="courseForm.end_time" type="time" class="w-full rounded-lg border-slate-300 text-xs px-2 py-1.5 font-mono" />
                                </div>
                                <div>
                                    <label class="block font-bold text-slate-700 mb-1">Kuota Peserta</label>
                                    <input v-model="courseForm.quota" type="number" min="1" class="w-full rounded-lg border-slate-300 text-xs px-2 py-1.5 font-mono" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- MODAL FOOTER -->
                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200">
                        <button type="button" @click="showCourseModal = false" class="px-4 py-2 rounded-xl border border-slate-300 text-slate-700 font-bold hover:bg-slate-50 transition-colors">
                            Batal
                        </button>
                        <button type="submit" :disabled="courseForm.processing" class="px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold transition-colors shadow-sm">
                            {{ isEditing ? 'Simpan Perubahan Topik' : 'Buat Topik Pelatihan' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- MODAL: JADWALKAN LANGSUNG KE KALENDER      -->
        <!-- ========================================== -->
        <div v-if="showScheduleModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center z-50 p-4" @click.self="showScheduleModal = false">
            <div class="bg-white border border-slate-300 rounded-2xl w-full max-w-lg p-6 shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-200 pb-3 mb-4">
                    <div>
                        <span class="font-mono text-[10px] font-bold bg-slate-900 text-white px-1.5 py-0.2 rounded">
                            {{ selectedCourseForSchedule?.code }}
                        </span>
                        <h3 class="text-base font-bold text-slate-900 mt-1">
                            Jadwalkan ke Kalender Pelatihan
                        </h3>
                        <p class="text-xs text-slate-500">
                            {{ selectedCourseForSchedule?.title }}
                        </p>
                    </div>
                    <button type="button" @click="showScheduleModal = false" class="text-slate-400 hover:text-slate-800 font-bold text-lg">✕</button>
                </div>

                <form @submit.prevent="submitScheduleForm" class="space-y-3.5 text-xs">
                    <!-- Preview Kompetensi Terkait -->
                    <div class="bg-purple-50 border border-purple-200 rounded-xl p-3 text-purple-900">
                        <span class="text-[10px] font-bold uppercase tracking-wider block text-purple-600">Kompetensi yang Dicapai</span>
                        <div class="font-bold text-slate-900 mt-0.5">
                            {{ selectedCourseForSchedule?.competency?.name || 'Kompetensi Terkait' }}
                        </div>
                        <span class="text-[10px] font-mono text-purple-700">
                            Target Level {{ selectedCourseForSchedule?.granted_skill_level }}
                        </span>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Judul Sesi / Batch *</label>
                        <input v-model="scheduleForm.title" type="text" required class="w-full rounded-xl border-slate-300 text-xs px-3 py-2" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Tanggal Pelaksanaan *</label>
                            <input v-model="scheduleForm.session_date" type="date" required class="w-full rounded-xl border-slate-300 text-xs px-3 py-2 font-mono" />
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Jam Ajar (JP)</label>
                            <input v-model="scheduleForm.duration_hours" type="number" step="0.5" required class="w-full rounded-xl border-slate-300 text-xs px-3 py-2 font-mono" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Jam Mulai</label>
                            <input v-model="scheduleForm.start_time" type="time" required class="w-full rounded-xl border-slate-300 text-xs px-3 py-2 font-mono" />
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Jam Selesai</label>
                            <input v-model="scheduleForm.end_time" type="time" required class="w-full rounded-xl border-slate-300 text-xs px-3 py-2 font-mono" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Instruktur / Trainer</label>
                            <select v-model="scheduleForm.trainer_id" class="w-full rounded-xl border-slate-300 text-xs px-3 py-2 bg-white">
                                <option v-for="t in trainers" :key="t.id" :value="t.id">{{ t.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Kuota Peserta</label>
                            <input v-model="scheduleForm.quota" type="number" min="1" class="w-full rounded-xl border-slate-300 text-xs px-3 py-2 font-mono" />
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Lokasi / Ruang Pelatihan</label>
                        <input v-model="scheduleForm.location" type="text" placeholder="Ruang Training Utama" class="w-full rounded-xl border-slate-300 text-xs px-3 py-2" />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200">
                        <button type="button" @click="showScheduleModal = false" class="px-4 py-2 rounded-xl border border-slate-300 text-slate-700 font-bold hover:bg-slate-50 transition-colors">
                            Batal
                        </button>
                        <button type="submit" :disabled="scheduleForm.processing" class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold transition-colors shadow-sm">
                            Simpan ke Kalender
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- MODAL: DETAIL KOLABORASI 360° TOPIK        -->
        <!-- ========================================== -->
        <div v-if="showDetailModal && detailCourse" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center z-50 p-4" @click.self="showDetailModal = false">
            <div class="bg-white border border-slate-300 rounded-2xl w-full max-w-2xl p-6 shadow-2xl max-h-[92vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b border-slate-200 pb-3 mb-4">
                    <div>
                        <div class="flex items-center gap-1.5">
                            <span class="font-mono text-xs font-bold bg-slate-900 text-white px-2 py-0.5 rounded">
                                {{ detailCourse.code }}
                            </span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-blue-50 text-blue-700 border border-blue-200">
                                {{ detailCourse.training_category }}
                            </span>
                        </div>
                        <h3 class="text-base font-bold text-slate-900 mt-1">
                            {{ detailCourse.title }}
                        </h3>
                    </div>
                    <button type="button" @click="showDetailModal = false" class="text-slate-400 hover:text-slate-800 font-bold text-lg">✕</button>
                </div>

                <!-- Tabs -->
                <div class="flex gap-2 border-b border-slate-200 pb-2 mb-4 text-xs font-bold">
                    <button
                        type="button"
                        @click="detailTab = 'overview'"
                        :class="['px-3 py-1.5 rounded-lg transition-colors', detailTab === 'overview' ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100']"
                    >
                        Overview &amp; Jam Ajar
                    </button>
                    <button
                        type="button"
                        @click="detailTab = 'competency'"
                        :class="['px-3 py-1.5 rounded-lg transition-colors', detailTab === 'competency' ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100']"
                    >
                        Kompetensi Terkait
                    </button>
                    <button
                        type="button"
                        @click="detailTab = 'schedule'"
                        :class="['px-3 py-1.5 rounded-lg transition-colors', detailTab === 'schedule' ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100']"
                    >
                        Sesi Kalender ({{ detailCourse.sessions?.length || 0 }})
                    </button>
                </div>

                <!-- Tab 1: Overview -->
                <div v-if="detailTab === 'overview'" class="space-y-3 text-xs">
                    <p class="text-slate-600 leading-relaxed">{{ detailCourse.description || 'Tidak ada deskripsi rinci.' }}</p>
                    <div class="grid grid-cols-2 gap-3 pt-2">
                        <div class="bg-slate-50 p-3 rounded-xl border border-slate-200">
                            <span class="block text-[10px] font-bold text-slate-400 uppercase">Durasi Jam Ajar</span>
                            <span class="font-bold text-slate-900 text-sm mt-0.5 block">{{ detailCourse.duration_hours }} Jam Ajar (JP)</span>
                        </div>
                        <div class="bg-slate-50 p-3 rounded-xl border border-slate-200">
                            <span class="block text-[10px] font-bold text-slate-400 uppercase">Instruktur Pengampu</span>
                            <span class="font-bold text-slate-900 text-sm mt-0.5 block">{{ detailCourse.trainer?.name || '-' }}</span>
                        </div>
                    </div>
                    <div class="border-t border-slate-100 pt-3">
                        <h4 class="font-bold text-slate-800 text-xs mb-2">Silabus Modul Pertemuan</h4>
                        <div class="space-y-1.5">
                            <div v-for="m in detailCourse.modules" :key="m.id" class="p-2.5 rounded-lg bg-slate-50 border border-slate-200 flex items-center justify-between">
                                <span class="font-bold text-slate-700">Pertemuan {{ m.order_index }}</span>
                                <span class="text-slate-900 font-medium">{{ m.title }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab 2: Competency -->
                <div v-else-if="detailTab === 'competency'" class="space-y-3 text-xs">
                    <div class="bg-purple-50 border border-purple-200 p-4 rounded-xl space-y-2">
                        <span class="text-[10px] font-bold uppercase text-purple-700">Kompetensi yang Dianugerahkan Saat Lulus</span>
                        <h4 class="text-sm font-bold text-slate-900">{{ detailCourse.competency?.name || 'Kompetensi Terkait' }}</h4>
                        <div class="flex items-center gap-2 pt-1">
                            <span class="font-mono text-purple-800 font-bold bg-white px-2 py-0.5 rounded border border-purple-200">
                                [{{ detailCourse.competency?.code }}]
                            </span>
                            <span :class="['px-2 py-0.5 rounded border text-[10px]', getLevelBadgeCls(detailCourse.granted_skill_level)]">
                                {{ levelName(detailCourse.granted_skill_level) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Tab 3: Schedule -->
                <div v-else-if="detailTab === 'schedule'" class="space-y-3 text-xs">
                    <div v-if="detailCourse.sessions?.length" class="space-y-2">
                        <div v-for="s in detailCourse.sessions" :key="s.id" class="p-3 rounded-xl border border-slate-200 bg-slate-50 flex items-center justify-between">
                            <div>
                                <span class="font-bold text-slate-900 block">{{ s.title }}</span>
                                <span class="text-[11px] text-slate-500 font-mono">📅 {{ s.date }} • 🕒 {{ s.start_time }}-{{ s.end_time }} ({{ s.duration_hours }} Jam)</span>
                                <span class="text-[11px] text-slate-500 block">📍 {{ s.location }} • Kuota {{ s.quota || '∞' }} Peserta</span>
                            </div>
                            <span :class="['px-2 py-0.5 rounded text-[10px] font-bold uppercase border', s.plan_status === 'approved' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-amber-50 text-amber-800 border-amber-300']">
                                {{ s.plan_status }}
                            </span>
                        </div>
                    </div>
                    <div v-else class="text-center py-6 text-slate-400">
                        Belum ada jadwal sesi yang terdaftar di kalender untuk topik pelatihan ini.
                    </div>
                </div>

                <div class="flex justify-end pt-4 border-t border-slate-200 mt-4">
                    <button type="button" @click="showDetailModal = false" class="px-4 py-2 rounded-xl bg-slate-900 text-white font-bold text-xs hover:bg-slate-800 transition-colors">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

