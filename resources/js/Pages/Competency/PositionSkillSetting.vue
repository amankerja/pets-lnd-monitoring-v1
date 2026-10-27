<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    positions: {
        type: Array,
        default: () => [],
    },
    skills: {
        type: Array,
        default: () => [],
    },
    selectedPositionId: {
        type: Number,
        default: null,
    },
    availableWorkers: {
        type: Array,
        default: () => [],
    },
    canEdit: {
        type: Boolean,
        default: true,
    },
    activeTabParam: {
        type: String,
        default: 'standards',
    },
});

const activeTab = ref(props.activeTabParam === 'positions' ? 'positions' : 'standards');
const currentPosId = ref(props.selectedPositionId || props.positions[0]?.id || null);

const activePosition = computed(() => {
    return props.positions.find((p) => Number(p.id) === Number(currentPosId.value)) || props.positions[0] || null;
});

// Bangun form matriks skill untuk posisi yang sedang dipilih
const buildStandardsArray = (pos) => {
    const map = pos?.standards_map || {};
    return props.skills.map((sk) => {
        const existing = map[sk.id];
        return {
            skill_id: sk.id,
            code: sk.code,
            name: sk.name,
            category: sk.category,
            enabled: Boolean(existing),
            required_target_level: existing ? Number(existing.required_target_level) : 3,
            is_mandatory: existing ? Boolean(existing.is_mandatory) : true,
        };
    });
};

const standardsForm = useForm({
    standards: buildStandardsArray(activePosition.value),
    auto_sync_workers: true,
});

watch(activePosition, (newPos) => {
    standardsForm.standards = buildStandardsArray(newPos);
});

const savePositionStandards = () => {
    if (!activePosition.value || !props.canEdit) return;
    standardsForm.post(`/competency/position-standards/${activePosition.value.id}/save`, {
        preserveScroll: true,
    });
};

const syncToAllWorkersInPosition = () => {
    if (!activePosition.value || !props.canEdit) return;
    router.post(
        `/competency/position-standards/${activePosition.value.id}/sync-workers`,
        {},
        { preserveScroll: true }
    );
};

// Form Assign Pekerja ke Jabatan Ini
const assignWorkerForm = useForm({
    user_id: props.availableWorkers[0]?.id || '',
});

const submitAssignWorker = () => {
    if (!activePosition.value || !assignWorkerForm.user_id) return;
    assignWorkerForm.post(`/competency/position-standards/${activePosition.value.id}/assign-worker`, {
        preserveScroll: true,
    });
};

// Form Master Data Level x Position (Khusus LnD)
const positionForm = useForm({
    code: '',
    level_name: 'Supervisor',
    position_title: '',
    organization_type: 'both',
});

const submitNewPosition = () => {
    if (!props.canEdit) return;
    positionForm.post('/competency/position-standards/positions', {
        preserveScroll: true,
        onSuccess: () => {
            positionForm.reset('code', 'position_title');
            activeTab.value = 'standards';
        },
    });
};
</script>

<template>
    <Head title="Setting Competency on Level x Position" />

    <AdminLayout
        title="Setting Competency on Level x Position"
        subtitle="Tetapkan standar Target Level (1-5) per Jabatan & sinkronkan otomatis ke matriks kompetensi seluruh pekerja"
    >
        <!-- Banner Status Akses Role -->
        <div
            :class="[
                'rounded-md border p-3.5 mb-5 flex flex-wrap items-center justify-between gap-3 text-xs',
                canEdit
                    ? 'bg-blue-50 border-blue-200 text-blue-950'
                    : 'bg-amber-50 border-amber-200 text-amber-950',
            ]"
        >
            <div class="flex items-center gap-2">
                <span
                    :class="[
                        'px-2 py-0.5 rounded font-extrabold uppercase text-[10px] text-white',
                        canEdit ? 'bg-blue-600' : 'bg-amber-600',
                    ]"
                >
                    {{ canEdit ? 'MODE FULL CRUD (SUPER ADMIN L&D)' : 'MODE READ-ONLY (ADMIN ORGANISASI)' }}
                </span>
                <span class="font-medium">
                    {{
                        canEdit
                            ? 'Perubahan standar kompetensi jabatan dapat langsung di-sync ke tabel user_skills seluruh pekerja terkait.'
                            : 'Anda dapat melihat standar kompetensi jabatan dan menempatkan pekerja departemen/kontraktor Anda.'
                    }}
                </span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    @click="activeTab = 'standards'"
                    :class="[
                        'px-3.5 py-1.5 rounded-lg font-bold border',
                        activeTab === 'standards'
                            ? 'bg-blue-600 text-white border-blue-600'
                            : 'bg-white text-slate-700 border-slate-300',
                    ]"
                >
                    Matriks Standar Kompetensi Jabatan
                </button>
                <button
                    v-if="canEdit"
                    type="button"
                    @click="activeTab = 'positions'"
                    :class="[
                        'px-3.5 py-1.5 rounded-lg font-bold border',
                        activeTab === 'positions'
                            ? 'bg-blue-600 text-white border-blue-600'
                            : 'bg-white text-slate-700 border-slate-300',
                    ]"
                >
                    + Master Data Level x Position ({{ positions.length }})
                </button>
            </div>
        </div>

        <!-- ================================================================ -->
        <!-- TAB 1: SETTING COMPETENCY ON LEVEL x POSITION                    -->
        <!-- ================================================================ -->
        <div v-if="activeTab === 'standards'" class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Kolom Kiri: Daftar Level x Position -->
            <div class="lg:col-span-4 space-y-4">
                <div class="bg-white border border-slate-200 rounded-md p-4">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">
                        Pilih Jabatan (Level x Position)
                    </h2>

                    <div class="space-y-2 max-h-[420px] overflow-y-auto pr-1">
                        <button
                            v-for="pos in positions"
                            :key="pos.id"
                            type="button"
                            @click="currentPosId = pos.id"
                            :class="[
                                'w-full text-left p-3.5 rounded-xl border transition-all text-xs',
                                Number(currentPosId) === Number(pos.id)
                                    ? 'bg-blue-600 text-white border-blue-600 shadow-sm'
                                    : 'bg-slate-50 hover:bg-blue-50/60 text-slate-800 border-slate-200',
                            ]"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <span
                                    :class="[
                                        'font-mono font-extrabold text-[11px]',
                                        Number(currentPosId) === Number(pos.id) ? 'text-blue-100' : 'text-blue-700',
                                    ]"
                                >
                                    {{ pos.code }}
                                </span>
                                <span
                                    :class="[
                                        'px-1.5 py-0.5 rounded text-[9px] font-bold uppercase',
                                        Number(currentPosId) === Number(pos.id)
                                            ? 'bg-blue-700 text-blue-50'
                                            : 'bg-slate-200 text-slate-700',
                                    ]"
                                >
                                    {{ pos.organization_type }}
                                </span>
                            </div>
                            <div class="font-bold mt-1">{{ pos.level_name }} &mdash; {{ pos.position_title }}</div>
                            <div
                                :class="[
                                    'text-[11px] mt-1 flex justify-between',
                                    Number(currentPosId) === Number(pos.id) ? 'text-blue-100' : 'text-slate-500',
                                ]"
                            >
                                <span>{{ pos.standards_count }} Skill Wajib</span>
                                <span>{{ pos.workers_count }} Pekerja</span>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Assign Pekerja ke Jabatan Ini (Auto-Sync Target Level) -->
                <div v-if="activePosition" class="bg-white border border-slate-200 rounded-md p-4">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
                        Assign Pekerja ke Posisi [{{ activePosition.code }}]
                    </h3>
                    <p class="text-[11px] text-slate-500 mb-3">
                        Saat pekerja ditempatkan di posisi ini, sistem otomatis menyinkronkan <code class="font-mono">target_level</code> di tabel <code class="font-mono">user_skills</code>.
                    </p>
                    <form @submit.prevent="submitAssignWorker" class="space-y-2.5 text-xs">
                        <select v-model="assignWorkerForm.user_id" required class="w-full rounded border-slate-300 text-xs py-2 px-2.5">
                            <option v-for="w in availableWorkers" :key="w.id" :value="w.id">
                                {{ w.nik }} - {{ w.name }} ({{ w.department }})
                            </option>
                        </select>
                        <button
                            type="submit"
                            :disabled="assignWorkerForm.processing || !assignWorkerForm.user_id"
                            class="w-full py-2 px-3 rounded bg-blue-600 hover:bg-blue-700 text-white font-bold"
                        >
                            Tempatkan Pekerja &amp; Auto-Sync Target Skill
                        </button>
                    </form>
                </div>
            </div>

            <!-- Kolom Kanan: Matriks Setting Standar Kompetensi (1-5) & Tombol Sync -->
            <div v-if="activePosition" class="lg:col-span-8 bg-white border border-slate-200 rounded-md p-5">
                <div class="flex flex-wrap items-start justify-between gap-4 pb-4 mb-4 border-b border-slate-200">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 rounded bg-blue-600 text-white font-mono text-xs font-bold">
                                {{ activePosition.code }}
                            </span>
                            <h2 class="text-base font-extrabold text-slate-900">
                                {{ activePosition.level_name }} &mdash; {{ activePosition.position_title }}
                            </h2>
                        </div>
                        <p class="text-xs text-slate-500 mt-1">
                            Centang kompetensi yang diwajibkan pada posisi ini dan tentukan nilai <strong>Required Target Level (1 s/d 5)</strong>.
                        </p>
                    </div>

                    <button
                        v-if="canEdit"
                        type="button"
                        @click="syncToAllWorkersInPosition"
                        class="px-4 py-2 rounded bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold flex items-center gap-2 shrink-0"
                    >
                        <span>&#x21bb;</span>
                        <span>Sync ke Semua Pekerja di Posisi Ini ({{ activePosition.workers_count }} Orang)</span>
                    </button>
                </div>

                <form @submit.prevent="savePositionStandards">
                    <div class="overflow-x-auto border border-slate-200 rounded">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold uppercase text-slate-600">
                                    <th class="py-2.5 px-3 w-10 text-center">Aktif</th>
                                    <th class="py-2.5 px-3">Kode &amp; Nama Kompetensi (Skill)</th>
                                    <th class="py-2.5 px-3">Kategori</th>
                                    <th class="py-2.5 px-3 text-center">Sifat</th>
                                    <th class="py-2.5 px-3 text-center">Required Target Level (1-5)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="row in standardsForm.standards"
                                    :key="row.skill_id"
                                    :class="row.enabled ? 'bg-blue-50/20' : 'opacity-60 hover:opacity-100'"
                                >
                                    <td class="py-2.5 px-3 text-center">
                                        <input
                                            v-model="row.enabled"
                                            type="checkbox"
                                            :disabled="!canEdit"
                                            class="rounded border-slate-300 text-blue-600 w-4 h-4"
                                        />
                                    </td>
                                    <td class="py-2.5 px-3">
                                        <div class="font-bold text-slate-900">{{ row.name }}</div>
                                        <div class="font-mono text-[11px] text-blue-700 font-semibold">{{ row.code }}</div>
                                    </td>
                                    <td class="py-2.5 px-3 text-slate-600">{{ row.category }}</td>
                                    <td class="py-2.5 px-3 text-center">
                                        <select
                                            v-model="row.is_mandatory"
                                            :disabled="!canEdit || !row.enabled"
                                            class="rounded border-slate-300 text-[11px] py-1 px-2 font-semibold"
                                        >
                                            <option :value="true">Wajib (Mandatory)</option>
                                            <option :value="false">Pendukung (Optional)</option>
                                        </select>
                                    </td>
                                    <td class="py-2.5 px-3 text-center">
                                        <div class="inline-flex items-center gap-1">
                                            <button
                                                v-for="lvl in [1, 2, 3, 4, 5]"
                                                :key="lvl"
                                                type="button"
                                                :disabled="!canEdit || !row.enabled"
                                                @click="row.required_target_level = lvl"
                                                :class="[
                                                    'w-7 h-7 rounded font-mono text-xs font-extrabold border transition-colors',
                                                    row.enabled && row.required_target_level === lvl
                                                        ? 'bg-blue-600 text-white border-blue-600'
                                                        : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-100',
                                                ]"
                                            >
                                                {{ lvl }}
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="canEdit" class="mt-4 flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-slate-200">
                        <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-700 cursor-pointer">
                            <input
                                v-model="standardsForm.auto_sync_workers"
                                type="checkbox"
                                class="rounded border-slate-300 text-blue-600"
                            />
                            <span>Otomatis sinkronkan Target Level ke semua pekerja di posisi ini saat disimpan</span>
                        </label>

                        <button
                            type="submit"
                            :disabled="standardsForm.processing"
                            class="px-5 py-2.5 rounded bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold"
                        >
                            Simpan Standar Kompetensi [{{ activePosition.code }}]
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ================================================================ -->
        <!-- TAB 2: MASTER DATA LEVEL x POSITION (KHUSUS LnD)                 -->
        <!-- ================================================================ -->
        <div v-else class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <div class="lg:col-span-5 bg-white border border-slate-200 rounded-md p-5">
                <h2 class="text-sm font-bold text-slate-900 mb-1">Tambah / Update Master Level x Position</h2>
                <p class="text-xs text-slate-500 mb-4">
                    Buat kombinasi jenjang jabatan (<code class="font-mono">level_name</code>) dan spesialisasi posisi (<code class="font-mono">position_title</code>).
                </p>

                <form @submit.prevent="submitNewPosition" class="space-y-3.5 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Kode Jabatan Unik (Code)</label>
                        <input
                            v-model="positionForm.code"
                            type="text"
                            required
                            placeholder="Contoh: SUP-ENG, OPR-MIN, MGR-HSE"
                            class="w-full rounded border-slate-300 text-xs py-2 px-3 font-mono uppercase"
                        />
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Level Jabatan (Level Name)</label>
                        <select v-model="positionForm.level_name" class="w-full rounded border-slate-300 text-xs py-2 px-3">
                            <option value="Operator">Operator</option>
                            <option value="Technician">Technician</option>
                            <option value="Foreman">Foreman</option>
                            <option value="Supervisor">Supervisor</option>
                            <option value="Superintendent">Superintendent</option>
                            <option value="Manager">Manager</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Nama Posisi / Fungsi (Position Title)</label>
                        <input
                            v-model="positionForm.position_title"
                            type="text"
                            required
                            placeholder="Contoh: Mechanical Engineering / Mining Production / HSE"
                            class="w-full rounded border-slate-300 text-xs py-2 px-3"
                        />
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Peruntukan Organisasi</label>
                        <select v-model="positionForm.organization_type" class="w-full rounded border-slate-300 text-xs py-2 px-3">
                            <option value="both">Internal &amp; Kontraktor (Both)</option>
                            <option value="internal">Khusus Departemen Internal</option>
                            <option value="contractor">Khusus Perusahaan Kontraktor</option>
                        </select>
                    </div>

                    <button
                        type="submit"
                        :disabled="positionForm.processing"
                        class="w-full py-2.5 px-4 rounded bg-blue-600 hover:bg-blue-700 text-white font-bold"
                    >
                        + Simpan Master Level x Position
                    </button>
                </form>
            </div>

            <div class="lg:col-span-7 bg-white border border-slate-200 rounded-md p-5">
                <h2 class="text-sm font-bold text-slate-900 mb-3">Daftar Master Level x Position ({{ positions.length }})</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold uppercase text-slate-600">
                                <th class="py-2.5 px-3">Kode</th>
                                <th class="py-2.5 px-3">Level Name</th>
                                <th class="py-2.5 px-3">Position Title</th>
                                <th class="py-2.5 px-3 text-center">Tipe</th>
                                <th class="py-2.5 px-3 text-right">Standar &amp; Pekerja</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="pos in positions" :key="pos.id" class="hover:bg-slate-50">
                                <td class="py-2.5 px-3 font-mono font-bold text-blue-700">{{ pos.code }}</td>
                                <td class="py-2.5 px-3 font-semibold">{{ pos.level_name }}</td>
                                <td class="py-2.5 px-3 font-bold text-slate-900">{{ pos.position_title }}</td>
                                <td class="py-2.5 px-3 text-center uppercase text-[10px] font-bold">{{ pos.organization_type }}</td>
                                <td class="py-2.5 px-3 text-right">
                                    <button
                                        type="button"
                                        @click="currentPosId = pos.id; activeTab = 'standards'"
                                        class="px-2.5 py-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-[11px]"
                                    >
                                        Atur {{ pos.standards_count }} Skill ({{ pos.workers_count }} Org) &rarr;
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

