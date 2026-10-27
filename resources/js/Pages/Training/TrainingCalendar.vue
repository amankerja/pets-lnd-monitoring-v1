<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    view: { type: String, default: 'monthly' }, // 'atmp' | 'monthly' | 'weekly'
    anchorDate: { type: String, required: true }, // YYYY-MM-DD
    todayDate: { type: String, required: true },
    rangeStart: { type: String, required: true },
    rangeEnd: { type: String, required: true },
    events: { type: Array, default: () => [] },
    liveSessions: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({ total: 0, approved: 0, pending: 0, live: 0 }) },
    filters: { type: Object, default: () => ({}) },
    categories: { type: Array, default: () => [] },
    abilities: {
        type: Object,
        default: () => ({ canManage: false, canOpenProjector: false, isParticipant: false }),
    },
    courses: { type: Array, default: () => [] },
    trainers: { type: Array, default: () => [] },
});

/* ------------------------------------------------------------------ */
/* Helper tanggal (lokal, tanpa konversi UTC agar tidak bergeser sehari) */
/* ------------------------------------------------------------------ */
const pad = (n) => String(n).padStart(2, '0');
const toIso = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const parseIso = (s) => {
    const [y, m, d] = s.split('-').map(Number);
    return new Date(y, m - 1, d);
};
const addDays = (d, n) => new Date(d.getFullYear(), d.getMonth(), d.getDate() + n);

const MONTHS_LONG = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const WEEKDAYS_SHORT = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
const WEEKDAYS_LONG = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

const anchor = computed(() => parseIso(props.anchorDate));

const fmtLong = (iso) => {
    const d = parseIso(iso);
    const wd = WEEKDAYS_LONG[(d.getDay() + 6) % 7];
    return `${wd}, ${d.getDate()} ${MONTHS_LONG[d.getMonth()]} ${d.getFullYear()}`;
};

const periodLabel = computed(() => {
    if (props.view === 'atmp') return `Tahun ${anchor.value.getFullYear()}`;
    if (props.view === 'weekly') {
        const a = parseIso(props.rangeStart);
        const b = parseIso(props.rangeEnd);
        return `${a.getDate()} ${MONTHS_LONG[a.getMonth()].slice(0, 3)} – ${b.getDate()} ${MONTHS_LONG[b.getMonth()].slice(0, 3)} ${b.getFullYear()}`;
    }
    return `${MONTHS_LONG[anchor.value.getMonth()]} ${anchor.value.getFullYear()}`;
});

/* ------------------------------------------------------------------ */
/* Navigasi server-side (Inertia GET, preserveState)                   */
/* ------------------------------------------------------------------ */
const localFilters = reactive({
    training_category: props.filters?.training_category || '',
    mandatory_type: props.filters?.mandatory_type || '',
    training_source: props.filters?.training_source || '',
    plan_status: props.filters?.plan_status || '',
});

const hasFilters = computed(() => Object.values(localFilters).some((v) => v !== ''));

const navigate = (overrides = {}) => {
    const query = { view: props.view, date: props.anchorDate, ...localFilters, ...overrides };
    const clean = Object.fromEntries(Object.entries(query).filter(([, v]) => v !== '' && v != null));
    router.get('/training/calendar', clean, { preserveState: true, preserveScroll: true, replace: true });
};

const setView = (v) => navigate({ view: v });

const shiftPeriod = (dir) => {
    const a = anchor.value;
    if (props.view === 'atmp') {
        navigate({ date: toIso(new Date(a.getFullYear() + dir, 0, 1)) });
    } else if (props.view === 'weekly') {
        navigate({ date: toIso(addDays(a, 7 * dir)) });
    } else {
        navigate({ date: toIso(new Date(a.getFullYear(), a.getMonth() + dir, 1)) });
    }
};

const goToday = () => navigate({ date: props.todayDate });

const resetFilters = () => {
    Object.keys(localFilters).forEach((k) => (localFilters[k] = ''));
    navigate({});
};

/* ------------------------------------------------------------------ */
/* Pengelompokan event                                                  */
/* ------------------------------------------------------------------ */
const eventsByDate = computed(() => {
    const map = {};
    props.events.forEach((e) => {
        (map[e.date] ||= []).push(e);
    });
    return map;
});

const monthCells = computed(() => {
    const start = parseIso(props.rangeStart);
    const end = parseIso(props.rangeEnd);
    const cells = [];
    for (let d = start; d <= end; d = addDays(d, 1)) {
        const iso = toIso(d);
        cells.push({
            iso,
            day: d.getDate(),
            inMonth: d.getMonth() === anchor.value.getMonth(),
            isToday: iso === props.todayDate,
            events: eventsByDate.value[iso] || [],
        });
    }
    return cells;
});

const weekDays = computed(() => {
    const start = parseIso(props.rangeStart);
    return Array.from({ length: 7 }, (_, i) => {
        const d = addDays(start, i);
        const iso = toIso(d);
        return {
            iso,
            label: `${WEEKDAYS_LONG[i]}, ${d.getDate()} ${MONTHS_LONG[d.getMonth()].slice(0, 3)}`,
            isToday: iso === props.todayDate,
            events: eventsByDate.value[iso] || [],
        };
    });
});

const atmpMonths = computed(() => {
    const year = anchor.value.getFullYear();
    return MONTHS_LONG.map((label, i) => {
        const prefix = `${year}-${pad(i + 1)}`;
        const list = props.events.filter((e) => e.date.startsWith(prefix));
        return {
            idx: i,
            label,
            firstDay: `${prefix}-01`,
            events: list,
            pending: list.filter((e) => e.plan_status === 'pending').length,
        };
    });
});

/* ------------------------------------------------------------------ */
/* Tampilan status                                                      */
/* ------------------------------------------------------------------ */
const chipClass = (e) => {
    if (e.status === 'live') return 'bg-emerald-600 text-white border-emerald-700';
    if (e.status === 'done') return 'bg-slate-100 text-slate-500 border-slate-200';
    if (e.plan_status === 'pending') return 'bg-amber-50 text-amber-900 border-amber-300 border-dashed';
    return 'bg-slate-800 text-white border-slate-900';
};

const statusBadge = (e) => {
    if (e.status === 'live') return { label: 'LIVE', cls: 'bg-emerald-50 text-emerald-800 border-emerald-200' };
    if (e.status === 'today') return { label: 'HARI INI', cls: 'bg-sky-50 text-sky-800 border-sky-200' };
    if (e.status === 'done') return { label: 'SELESAI', cls: 'bg-slate-100 text-slate-600 border-slate-200' };
    return { label: 'TERJADWAL', cls: 'bg-slate-50 text-slate-700 border-slate-200' };
};

const planBadge = (e) =>
    e.plan_status === 'pending'
        ? { label: 'PENDING', cls: 'bg-amber-50 text-amber-800 border-amber-300' }
        : { label: 'APPROVED', cls: 'bg-emerald-50 text-emerald-800 border-emerald-200' };

const timeRange = (e) => (e.start_time && e.end_time ? `${e.start_time} – ${e.end_time}` : 'Jam belum ditentukan');

const quotaText = (e) =>
    e.quota ? `${e.enrolled_count} / ${e.quota} peserta` : `${e.enrolled_count} peserta terdaftar (tanpa batas kuota)`;

const quotaPct = (e) => (e.quota ? Math.min(100, Math.round((e.enrolled_count / e.quota) * 100)) : 0);

/* ------------------------------------------------------------------ */
/* Modal tambah jadwal (klik tanggal kosong)                           */
/* ------------------------------------------------------------------ */
const showAdd = ref(false);
const addForm = useForm({
    course_id: '',
    title: '',
    session_date: '',
    start_time: '',
    end_time: '',
    trainer_id: '',
    location: '',
    quota: '',
    plan_status: 'approved',
});

const trainerAvailability = ref({ loading: false, result: null });
let availabilityTimer = null;

const checkTrainerAvailability = () => {
    if (availabilityTimer) clearTimeout(availabilityTimer);
    if (!addForm.trainer_id || !addForm.session_date) {
        trainerAvailability.value = { loading: false, result: null };
        return;
    }
    trainerAvailability.value.loading = true;
    availabilityTimer = setTimeout(async () => {
        try {
            const params = new URLSearchParams({
                trainer_id: addForm.trainer_id,
                session_date: addForm.session_date,
                start_time: addForm.start_time || '',
                end_time: addForm.end_time || '',
            });
            const res = await fetch(`/admin/program-schedules/check-availability?${params.toString()}`, {
                headers: { Accept: 'application/json' },
            });
            if (res.ok) {
                trainerAvailability.value.result = await res.json();
            }
        } catch {
            // Silently ignore network hiccup
        } finally {
            trainerAvailability.value.loading = false;
        }
    }, 250);
};

watch(
    [
        () => addForm.trainer_id,
        () => addForm.session_date,
        () => addForm.start_time,
        () => addForm.end_time,
    ],
    () => {
        if (showAdd.value) {
            checkTrainerAvailability();
        }
    }
);

watch(
    () => addForm.course_id,
    (id) => {
        const c = props.courses.find((x) => x.id === Number(id));
        if (c && !addForm.trainer_id && c.trainer_id) addForm.trainer_id = c.trainer_id;
    }
);

const selectedCourseInAdd = computed(() => {
    if (!addForm.course_id) return null;
    return props.courses.find((c) => c.id === Number(addForm.course_id)) || null;
});

const openAdd = (iso) => {
    if (!props.abilities.canManage) return;
    addForm.reset();
    addForm.clearErrors();
    trainerAvailability.value = { loading: false, result: null };
    addForm.session_date = iso;
    showAdd.value = true;
};

const submitAdd = () => {
    addForm.post('/training/calendar/sessions', {
        preserveScroll: true,
        onSuccess: () => {
            showAdd.value = false;
            addForm.reset();
        },
    });
};

/* ------------------------------------------------------------------ */
/* Drawer detail event                                                  */
/* ------------------------------------------------------------------ */
const selectedId = ref(null);
const selectedEvent = computed(() => {
    if (selectedId.value === null) return null;
    return [...props.events, ...props.liveSessions].find((e) => e.id === selectedId.value) || null;
});

const approvePlan = (e) => {
    router.patch(`/training/calendar/sessions/${e.id}/plan-status`, { plan_status: 'approved' }, { preserveScroll: true });
};

const enrollMe = (e) => {
    router.post('/participant/courses/enroll', { course_id: e.course_id }, { preserveScroll: true });
};
</script>

<template>
    <AdminLayout
        title="Kalender & Master Plan Pelatihan"
        subtitle="ATMP tahunan, plan bulanan & mingguan, serta radar sesi pelatihan yang sedang berlangsung"
    >
        <div class="space-y-4">
            <!-- 1. LIVE TRAINING CENTER -->
            <div v-if="liveSessions.length" class="bg-slate-900 text-white rounded-xl p-4 border border-slate-700">
                <div class="flex items-center gap-3 mb-3">
                    <span class="relative flex h-3 w-3">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                    </span>
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-emerald-400">Live Training Center</p>
                        <p class="text-xs text-slate-300">{{ liveSessions.length }} sesi kelas sedang aktif hari ini.</p>
                    </div>
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-2">
                    <div
                        v-for="live in liveSessions"
                        :key="live.id"
                        class="bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 flex items-center justify-between gap-3 text-xs"
                    >
                        <button type="button" class="text-left min-w-0" @click="selectedId = live.id">
                            <p class="font-bold text-white truncate">[{{ live.course_code }}] {{ live.course_title }}</p>
                            <p class="text-[10px] text-slate-400 truncate">
                                {{ live.title }} • {{ live.trainer_name }} • {{ live.present_count }} hadir / {{ live.enrolled_count }} terdaftar
                            </p>
                        </button>
                        <a
                            v-if="live.projector_url"
                            :href="live.projector_url"
                            class="shrink-0 bg-emerald-600 hover:bg-emerald-500 text-white font-bold px-2.5 py-1 rounded text-[11px]"
                        >
                            Buka Proyektor QR &rarr;
                        </a>
                        <button
                            v-else
                            type="button"
                            class="shrink-0 border border-slate-600 hover:bg-slate-700 text-slate-200 font-bold px-2.5 py-1 rounded text-[11px]"
                            @click="selectedId = live.id"
                        >
                            Detail
                        </button>
                    </div>
                </div>
            </div>

            <!-- 2. KONTROL: SWITCHER, NAVIGASI, FILTER -->
            <div class="bg-white border border-slate-200 rounded-xl p-3 sm:p-4 shadow-sm space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="inline-flex rounded-lg bg-slate-100 p-0.5 border border-slate-200 text-[11px] font-bold uppercase">
                        <button
                            v-for="v in [
                                { key: 'atmp', label: 'ATMP (1 Tahun)' },
                                { key: 'monthly', label: 'Plan Bulanan' },
                                { key: 'weekly', label: 'Plan Mingguan' },
                            ]"
                            :key="v.key"
                            type="button"
                            @click="setView(v.key)"
                            :class="[
                                'px-3 py-1.5 rounded-md transition',
                                view === v.key ? 'bg-slate-800 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900',
                            ]"
                        >
                            {{ v.label }}
                        </button>
                    </div>

                    <div class="flex items-center gap-2">
                        <Link
                            v-if="abilities.canManage"
                            href="/admin/program-schedules"
                            class="px-2.5 py-1 text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 border border-slate-200 rounded-lg transition-all flex items-center gap-1.5 shadow-sm mr-1"
                        >
                            <svg class="w-3.5 h-3.5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                            </svg>
                            <span>Manajer Sesi</span>
                        </Link>

                        <button type="button" @click="shiftPeriod(-1)" class="border border-slate-300 hover:bg-slate-100 rounded-lg px-2.5 py-1 text-xs font-bold">
                            &larr;
                        </button>
                        <span class="font-mono font-bold text-sm text-slate-900 min-w-[150px] text-center">{{ periodLabel }}</span>
                        <button type="button" @click="shiftPeriod(1)" class="border border-slate-300 hover:bg-slate-100 rounded-lg px-2.5 py-1 text-xs font-bold">
                            &rarr;
                        </button>
                        <button type="button" @click="goToday" class="border border-slate-300 hover:bg-slate-100 rounded-lg px-2.5 py-1 text-xs font-bold">
                            Hari Ini
                        </button>
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-100 flex flex-wrap items-end gap-2.5">
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">Bidang</label>
                        <select v-model="localFilters.training_category" @change="navigate()" class="rounded-lg border-slate-300 text-xs py-1.5 px-2 bg-white">
                            <option value="">Semua Bidang</option>
                            <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">Jenis Training</label>
                        <select v-model="localFilters.mandatory_type" @change="navigate()" class="rounded-lg border-slate-300 text-xs py-1.5 px-2 bg-white">
                            <option value="">Mandatory &amp; Non</option>
                            <option value="mandatory">Mandatory</option>
                            <option value="non_mandatory">Non-Mandatory</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">Internal / External</label>
                        <select v-model="localFilters.training_source" @change="navigate()" class="rounded-lg border-slate-300 text-xs py-1.5 px-2 bg-white">
                            <option value="">Semua</option>
                            <option value="internal">Internal</option>
                            <option value="external">External</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">Status Rencana</label>
                        <select v-model="localFilters.plan_status" @change="navigate()" class="rounded-lg border-slate-300 text-xs py-1.5 px-2 bg-white">
                            <option value="">Approved &amp; Pending</option>
                            <option value="approved">Approved</option>
                            <option value="pending">Pending</option>
                        </select>
                    </div>
                    <button v-if="hasFilters" type="button" @click="resetFilters" class="text-xs font-semibold text-rose-700 hover:underline pb-2">
                        &#10005; Reset
                    </button>

                    <div class="ml-auto flex flex-wrap items-center gap-1.5 text-[10px] font-bold uppercase pb-1.5">
                        <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 border border-slate-200">{{ summary.total }} Jadwal</span>
                        <span class="px-2 py-0.5 rounded bg-emerald-50 text-emerald-800 border border-emerald-200">{{ summary.approved }} Approved</span>
                        <span class="px-2 py-0.5 rounded bg-amber-50 text-amber-800 border border-amber-300">{{ summary.pending }} Pending</span>
                    </div>
                </div>
            </div>

            <!-- 3. AREA TAMPILAN -->
            <div class="bg-white border border-slate-200 rounded-xl p-3 sm:p-4 shadow-sm min-h-[480px]">
                <!-- A. ATMP -->
                <div v-if="view === 'atmp'" class="space-y-3">
                    <div class="flex justify-between items-center border-b border-slate-100 pb-2">
                        <h3 class="text-xs font-bold uppercase text-slate-700">Annual Training Master Plan (ATMP) {{ anchor.getFullYear() }}</h3>
                        <span class="text-[11px] text-slate-500">Garis putus-putus = usulan Pending</span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                        <div v-for="m in atmpMonths" :key="m.idx" class="border border-slate-200 rounded-lg bg-slate-50/60 p-3 flex flex-col min-h-[150px]">
                            <div class="flex items-center justify-between border-b border-slate-200 pb-1 mb-2">
                                <button type="button" class="text-xs font-bold uppercase text-slate-900 hover:underline" @click="navigate({ view: 'monthly', date: m.firstDay })">
                                    {{ m.label }}
                                </button>
                                <span class="text-[10px] font-mono text-slate-500">
                                    {{ m.events.length }} jadwal<template v-if="m.pending"> • {{ m.pending }} pending</template>
                                </span>
                            </div>
                            <div class="space-y-1.5 flex-1">
                                <button
                                    v-for="e in m.events.slice(0, 5)"
                                    :key="e.id"
                                    type="button"
                                    @click="selectedId = e.id"
                                    :class="['w-full text-left border rounded-lg px-2 py-1.5 text-[11px] mb-1 leading-snug', chipClass(e)]"
                                >
                                    <div class="flex items-center justify-between">
                                        <span class="font-mono font-bold">{{ e.date.slice(8) }}</span>
                                        <span class="text-[9px] font-bold opacity-80">🕒 {{ e.duration_hours }} JP</span>
                                    </div>
                                    <div class="font-bold truncate mt-0.5">[{{ e.course_code }}] {{ e.course_title }}</div>
                                    <div class="text-[10px] text-purple-900 truncate font-semibold">🎯 {{ e.competency_name }}</div>
                                    <div class="text-[10px] text-slate-500 truncate">👤 {{ e.trainer_name }}</div>
                                </button>
                                <button
                                    v-if="m.events.length > 5"
                                    type="button"
                                    class="text-[10px] font-bold text-slate-600 hover:underline"
                                    @click="navigate({ view: 'monthly', date: m.firstDay })"
                                >
                                    +{{ m.events.length - 5 }} jadwal lagi
                                </button>
                                <p v-if="!m.events.length" class="text-[11px] text-slate-400">Belum ada rencana.</p>
                            </div>
                            <button v-if="abilities.canManage" type="button" class="text-[10px] font-bold text-slate-600 hover:text-slate-900 text-right mt-2" @click="openAdd(m.firstDay)">
                                + Tambah Jadwal &rarr;
                            </button>
                        </div>
                    </div>
                </div>

                <!-- B. PLAN BULANAN -->
                <div v-else-if="view === 'monthly'">
                    <div class="grid grid-cols-7 gap-px bg-slate-200 border border-slate-200 rounded-t-lg overflow-hidden text-center text-[11px] font-bold uppercase text-slate-600">
                        <div v-for="w in WEEKDAYS_SHORT" :key="w" class="bg-slate-100 py-2">{{ w }}</div>
                    </div>
                    <div class="grid grid-cols-7 gap-px bg-slate-200 border-x border-b border-slate-200 rounded-b-lg overflow-hidden">
                        <div
                            v-for="cell in monthCells"
                            :key="cell.iso"
                            @click="openAdd(cell.iso)"
                            :class="[
                                'group p-1.5 min-h-[105px] flex flex-col gap-1',
                                cell.inMonth ? 'bg-white' : 'bg-slate-50',
                                abilities.canManage ? 'cursor-pointer hover:bg-slate-50' : '',
                            ]"
                        >
                            <div class="flex items-center justify-between">
                                <span
                                    :class="[
                                        'text-[11px] font-mono font-bold w-5 h-5 inline-flex items-center justify-center rounded-full',
                                        cell.isToday ? 'bg-slate-900 text-white' : cell.inMonth ? 'text-slate-600' : 'text-slate-300',
                                    ]"
                                >
                                    {{ cell.day }}
                                </span>
                                <span v-if="abilities.canManage" class="text-[10px] text-slate-300 group-hover:text-slate-600 font-bold">+</span>
                            </div>
                            <button
                                v-for="e in cell.events.slice(0, 3)"
                                :key="e.id"
                                type="button"
                                @click.stop="selectedId = e.id"
                                :class="['w-full text-left border rounded-lg px-1.5 py-1 text-[10px] mb-0.5 leading-tight', chipClass(e)]"
                                :title="`[${e.course_code}] ${e.course_title}\n🎯 Kompetensi: ${e.competency_name} (Level ${e.granted_skill_level})\n🕒 Jam Ajar: ${e.duration_hours} Jam (${e.start_time || '08:00'}-${e.end_time || '16:00'})\n👤 Trainer: ${e.trainer_name}`"
                            >
                                <div class="flex items-center justify-between gap-1">
                                    <span v-if="e.start_time" class="font-mono opacity-80 text-[9px]">{{ e.start_time }}</span>
                                    <b class="truncate text-[10px]">{{ e.course_code }}</b>
                                    <span class="text-[9px] font-bold shrink-0 opacity-75">{{ e.duration_hours }}h</span>
                                </div>
                                <div class="truncate font-semibold text-[10px] mt-0.5">{{ e.course_title }}</div>
                                <div class="truncate text-[9px] text-purple-900 font-bold">🎯 {{ e.competency_name }}</div>
                                <div class="truncate text-[9px] text-slate-600">👤 {{ e.trainer_name }}</div>
                            </button>
                            <button
                                v-if="cell.events.length > 3"
                                type="button"
                                @click.stop="navigate({ view: 'weekly', date: cell.iso })"
                                class="text-left text-[10px] font-bold text-slate-600 hover:underline"
                            >
                                +{{ cell.events.length - 3 }} lagi
                            </button>
                        </div>
                    </div>
                </div>

                <!-- C. PLAN MINGGUAN -->
                <div v-else class="divide-y divide-slate-100">
                    <div v-for="d in weekDays" :key="d.iso" class="py-3 flex flex-col sm:flex-row sm:items-start gap-2">
                        <div class="sm:w-36 shrink-0">
                            <p :class="['text-xs font-bold', d.isToday ? 'text-sky-700' : 'text-slate-800']">{{ d.label }}</p>
                            <p v-if="d.isToday" class="text-[10px] font-bold uppercase text-sky-600">Hari ini</p>
                            <button v-if="abilities.canManage" type="button" class="mt-1 text-[10px] font-bold text-slate-500 hover:text-slate-900" @click="openAdd(d.iso)">
                                + Tambah
                            </button>
                        </div>
                        <div class="flex-1 space-y-2">
                            <button
                                v-for="e in d.events"
                                :key="e.id"
                                type="button"
                                @click="selectedId = e.id"
                                class="w-full text-left bg-slate-50 hover:bg-slate-100 border border-slate-200 rounded-xl p-3 text-xs flex flex-wrap items-center justify-between gap-3 shadow-xs"
                            >
                                <div class="min-w-0 space-y-1">
                                    <!-- 1. NAMA PELATIHAN -->
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="font-mono text-[10px] font-bold bg-slate-900 text-white px-2 py-0.5 rounded">
                                            {{ e.course_code }}
                                        </span>
                                        <p class="font-bold text-slate-900 text-sm">{{ e.course_title }}</p>
                                        <span class="text-xs text-slate-500">({{ e.title }})</span>
                                    </div>
                                    <!-- 2. NAMA KOMPETENSI, 3. JAM AJAR, 4. TRAINER -->
                                    <div class="flex items-center gap-2 flex-wrap text-[11px]">
                                        <span class="px-2 py-0.5 rounded-md bg-purple-50 text-purple-700 font-bold border border-purple-200">
                                            🎯 {{ e.competency_name }} (⭐ Level {{ e.granted_skill_level }})
                                        </span>
                                        <span class="px-2 py-0.5 rounded-md bg-blue-50 text-blue-700 font-bold border border-blue-200">
                                            🕒 {{ timeRange(e) }} • {{ e.duration_hours }} Jam Ajar
                                        </span>
                                        <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-800 font-semibold border border-slate-200">
                                            👤 Trainer: {{ e.trainer_name }}
                                        </span>
                                        <span v-if="e.location" class="text-slate-500">📍 {{ e.location }}</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <span :class="['px-2 py-0.5 border rounded text-[10px] font-bold', planBadge(e).cls]">{{ planBadge(e).label }}</span>
                                    <span :class="['px-2 py-0.5 border rounded text-[10px] font-bold', statusBadge(e).cls]">{{ statusBadge(e).label }}</span>
                                </div>
                            </button>
                            <p v-if="!d.events.length" class="text-[11px] text-slate-400 py-1">Tidak ada agenda.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL TAMBAH JADWAL -->
        <div v-if="showAdd" class="fixed inset-0 bg-slate-900/50 flex items-center justify-center z-50 p-4" @click.self="showAdd = false">
            <form @submit.prevent="submitAdd" class="bg-white border border-slate-300 rounded-xl w-full max-w-md p-5 space-y-3 shadow-xl max-h-[92vh] overflow-y-auto">
                <div class="flex justify-between items-center border-b border-slate-200 pb-3">
                    <h3 class="text-xs font-bold uppercase text-slate-900">Tambah Jadwal Pelatihan</h3>
                    <button type="button" @click="showAdd = false" class="text-slate-400 hover:text-slate-900 font-bold">&#10005;</button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Program Training (Course)</label>
                        <select v-model="addForm.course_id" required class="w-full rounded-lg border-slate-300 text-xs px-3 py-2">
                            <option value="" disabled>-- Pilih Course --</option>
                            <option v-for="c in courses" :key="c.id" :value="c.id">[{{ c.code }}] {{ c.title }}</option>
                        </select>
                        <p v-if="addForm.errors.course_id" class="text-rose-600 mt-1">{{ addForm.errors.course_id }}</p>

                        <!-- Preview Kolaborasi: Kompetensi Terkait, Jam Ajar, Trainer Default -->
                        <div v-if="selectedCourseInAdd" class="mt-2 bg-purple-50/70 border border-purple-200 rounded-xl p-3 text-xs space-y-1">
                            <span class="text-[10px] font-bold uppercase text-purple-700 tracking-wider">🎯 Kompetensi yang Dianugerahkan</span>
                            <div class="font-bold text-slate-900">{{ selectedCourseInAdd.skill?.name || 'Kompetensi Acuan' }}</div>
                            <div class="flex items-center justify-between text-[11px] text-slate-600 pt-1 border-t border-purple-100 mt-1">
                                <span>Target: <strong class="text-purple-900">⭐ Level {{ selectedCourseInAdd.granted_skill_level || 3 }}</strong></span>
                                <span>Trainer Default: <strong class="text-slate-800">{{ selectedCourseInAdd.trainer?.name || 'Tim L&D' }}</strong></span>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Judul Sesi / Batch</label>
                        <input v-model="addForm.title" type="text" required placeholder="Contoh: Sesi Pagi - Batch 3" class="w-full rounded-lg border-slate-300 text-xs px-3 py-2" />
                        <p v-if="addForm.errors.title" class="text-rose-600 mt-1">{{ addForm.errors.title }}</p>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Tanggal</label>
                            <input v-model="addForm.session_date" type="date" required class="w-full rounded-lg border-slate-300 font-mono text-xs px-2 py-2" />
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Mulai</label>
                            <input v-model="addForm.start_time" type="time" class="w-full rounded-lg border-slate-300 font-mono text-xs px-2 py-2" />
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Selesai</label>
                            <input v-model="addForm.end_time" type="time" class="w-full rounded-lg border-slate-300 font-mono text-xs px-2 py-2" />
                        </div>
                    </div>
                    <p v-if="addForm.errors.session_date || addForm.errors.start_time || addForm.errors.end_time" class="text-rose-600">
                        {{ addForm.errors.session_date || addForm.errors.start_time || addForm.errors.end_time }}
                    </p>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Instruktur / Trainer</label>
                        <select v-model="addForm.trainer_id" class="w-full rounded-lg border-slate-300 text-xs px-3 py-2">
                            <option value="">(Ikut trainer default course)</option>
                            <option v-for="t in trainers" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                        <p v-if="addForm.errors.trainer_id" class="text-rose-600 mt-1 font-semibold">{{ addForm.errors.trainer_id }}</p>

                        <!-- Real-time Availability Feedback -->
                        <div v-if="trainerAvailability.loading" class="mt-1.5 flex items-center gap-1.5 text-[11px] text-slate-500 font-medium">
                            <svg class="animate-spin w-3.5 h-3.5 text-blue-600 shrink-0" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                            <span>Memeriksa ketersediaan jadwal instruktur...</span>
                        </div>
                        <div v-else-if="trainerAvailability.result && addForm.trainer_id" class="mt-1.5">
                            <div
                                v-if="trainerAvailability.result.available"
                                class="px-2.5 py-1.5 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-[11px] font-semibold flex items-center gap-1.5"
                            >
                                <svg class="w-3.5 h-3.5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>Instruktur Tersedia &amp; Siap Mengajar</span>
                            </div>
                            <div
                                v-else
                                class="px-2.5 py-1.5 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 text-[11px] font-semibold flex items-start gap-1.5"
                            >
                                <svg class="w-3.5 h-3.5 text-rose-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <span>{{ trainerAvailability.result.message }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <div class="col-span-2">
                            <label class="block font-bold text-slate-700 mb-1">Lokasi / Ruang</label>
                            <input v-model="addForm.location" type="text" placeholder="Ruang Training Utama" class="w-full rounded-lg border-slate-300 text-xs px-3 py-2" />
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Kuota</label>
                            <input v-model="addForm.quota" type="number" min="1" placeholder="∞" class="w-full rounded-lg border-slate-300 text-xs px-2 py-2" />
                        </div>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Status Rencana</label>
                        <select v-model="addForm.plan_status" class="w-full rounded-lg border-slate-300 text-xs px-3 py-2">
                            <option value="approved">Approved (resmi dijadwalkan)</option>
                            <option value="pending">Pending (usulan ATMP)</option>
                        </select>
                    </div>
                    <p class="text-[11px] text-slate-500">
                        Sesi dibuat dalam keadaan tertutup. Trainer membuka presensi Rotating QR dari Layar Proyektor saat kelas dimulai.
                    </p>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-slate-200">
                    <button type="button" @click="showAdd = false" class="border border-slate-300 rounded-lg px-3 py-1.5 text-xs font-bold text-slate-700">Batal</button>
                    <button type="submit" :disabled="addForm.processing" class="bg-slate-900 hover:bg-slate-700 disabled:opacity-50 text-white rounded-lg px-4 py-1.5 text-xs font-bold">
                        {{ addForm.processing ? 'Menyimpan...' : 'Simpan Jadwal' }}
                    </button>
                </div>
            </form>
        </div>

        <!-- DRAWER DETAIL EVENT -->
        <div v-if="selectedEvent" class="fixed inset-0 z-50 flex justify-end bg-slate-900/40" @click.self="selectedId = null">
            <aside class="w-full max-w-sm bg-white h-full shadow-2xl border-l border-slate-200 p-5 overflow-y-auto space-y-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <span class="font-mono text-[10px] font-bold bg-slate-900 text-white px-1.5 py-0.5 rounded">{{ selectedEvent.course_code }}</span>
                        <h3 class="mt-1.5 text-sm font-bold text-slate-900">{{ selectedEvent.course_title }}</h3>
                        <p class="text-xs text-slate-500">{{ selectedEvent.title }}</p>
                    </div>
                    <button type="button" @click="selectedId = null" class="text-slate-400 hover:text-slate-900 font-bold">&#10005;</button>
                </div>

                <div class="flex flex-wrap gap-1.5">
                    <span :class="['px-1.5 py-0.5 border rounded text-[10px] font-bold', statusBadge(selectedEvent).cls]">{{ statusBadge(selectedEvent).label }}</span>
                    <span :class="['px-1.5 py-0.5 border rounded text-[10px] font-bold', planBadge(selectedEvent).cls]">{{ planBadge(selectedEvent).label }}</span>
                    <span :class="['px-1.5 py-0.5 border rounded text-[10px] font-bold uppercase', selectedEvent.is_mandatory ? 'bg-rose-50 text-rose-800 border-rose-200' : 'bg-slate-100 text-slate-700 border-slate-200']">
                        {{ selectedEvent.is_mandatory ? 'Mandatory' : 'Non-Mandatory' }}
                    </span>
                    <span v-if="selectedEvent.training_source" class="px-1.5 py-0.5 border rounded text-[10px] font-bold uppercase bg-slate-100 text-slate-700 border-slate-200">
                        {{ selectedEvent.training_source }}
                    </span>
                </div>

                <!-- 4 PILAR: KOMPETENSI ACUAN (Competency List Collaboration) -->
                <div class="bg-purple-50/70 border border-purple-200 rounded-xl p-3 space-y-1">
                    <span class="text-[10px] font-bold uppercase text-purple-700 tracking-wider">🎯 Kompetensi yang Dianugerahkan</span>
                    <h4 class="text-xs font-bold text-slate-900">{{ selectedEvent.competency_name || 'Kompetensi Terkait' }}</h4>
                    <div class="flex items-center justify-between text-[10px] pt-1 text-slate-500 border-t border-purple-100 mt-1">
                        <span class="font-mono text-purple-800 font-bold">[{{ selectedEvent.competency_code || 'KOMP' }}]</span>
                        <span class="px-1.5 py-0.5 rounded bg-purple-100 text-purple-800 font-bold">⭐ Level {{ selectedEvent.granted_skill_level || 3 }}</span>
                    </div>
                </div>

                <dl class="text-xs space-y-2.5 border-t border-slate-100 pt-3">
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Tanggal Sesi</dt><dd class="font-semibold text-slate-900 text-right">{{ fmtLong(selectedEvent.date) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Jam Operasional</dt><dd class="font-mono font-semibold text-slate-900">{{ timeRange(selectedEvent) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Jam Ajar (JP)</dt><dd class="font-bold text-blue-700">{{ selectedEvent.duration_hours }} Jam Ajar</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Lokasi / Ruang</dt><dd class="font-semibold text-slate-900 text-right">{{ selectedEvent.location || '-' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Pengajar / Trainer</dt><dd class="font-semibold text-slate-900 text-right">{{ selectedEvent.trainer_name }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Hadir (sesi ini)</dt><dd class="font-semibold text-slate-900">{{ selectedEvent.present_count }}</dd></div>
                </dl>

                <div class="border-t border-slate-100 pt-3">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">Status Kuota</p>
                    <p class="text-xs font-semibold text-slate-900">{{ quotaText(selectedEvent) }}</p>
                    <div v-if="selectedEvent.quota" class="mt-1.5 w-full bg-slate-100 h-2 rounded overflow-hidden">
                        <div :class="['h-full', quotaPct(selectedEvent) >= 100 ? 'bg-rose-500' : 'bg-emerald-500']" :style="{ width: quotaPct(selectedEvent) + '%' }"></div>
                    </div>
                </div>

                <div class="border-t border-slate-100 pt-3 space-y-2">
                    <a
                        v-if="selectedEvent.projector_url && selectedEvent.status !== 'done'"
                        :href="selectedEvent.projector_url"
                        class="block text-center bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg px-3 py-2 text-xs font-bold"
                    >
                        Buka Proyektor QR Absen &rarr;
                    </a>

                    <button
                        v-if="abilities.canManage && selectedEvent.plan_status === 'pending'"
                        type="button"
                        @click="approvePlan(selectedEvent)"
                        class="w-full bg-slate-900 hover:bg-slate-700 text-white rounded-lg px-3 py-2 text-xs font-bold"
                    >
                        Setujui Jadwal (Approved)
                    </button>

                    <template v-if="abilities.isParticipant">
                        <p
                            v-if="selectedEvent.my_enrollment"
                            class="text-center text-xs font-bold bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 uppercase text-slate-700"
                        >
                            Pendaftaran Anda: {{ selectedEvent.my_enrollment }}
                        </p>
                        <button
                            v-else-if="selectedEvent.status !== 'done' && selectedEvent.plan_status === 'approved'"
                            type="button"
                            @click="enrollMe(selectedEvent)"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white rounded-lg px-3 py-2 text-xs font-bold"
                        >
                            Daftar Kelas Ini
                        </button>
                        <p v-else-if="selectedEvent.plan_status === 'pending'" class="text-[11px] text-slate-500 text-center">
                            Jadwal masih berstatus usulan (Pending) dan belum dibuka untuk pendaftaran.
                        </p>
                    </template>
                </div>
            </aside>
        </div>
    </AdminLayout>
</template>
