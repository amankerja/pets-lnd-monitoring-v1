<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import GlobalTableFilterBar from '@/Components/GlobalTableFilterBar.vue';

const props = defineProps({
    skills: {
        type: Array,
        default: () => [],
    },
    participants: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
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
    courses: {
        type: Array,
        default: () => [],
    },
    filterOptions: {
        type: Object,
        default: () => ({
            departments: [],
            companies: [],
            courses: [],
            categories: [],
        }),
    },
});

// Helper ambil data cell Peserta x Skill (mendukung p.matrix maupun p.skills_map)
const getCellData = (participant, skillId) => {
    const map = participant.matrix || participant.skills_map || {};
    return (
        map[skillId] || {
            skill_id: skillId,
            current: 0,
            target: 0,
            current_level: 0,
            target_level: 0,
            gap: 0,
            status: 'not_required',
            standard_type: 'NR',
            is_mandatory: false,
            valid_until: null,
            days_left: null,
            cert_no: null,
        }
    );
};

// State Modal Interaktif Update Nilai Skill
const activeModal = ref(false);
const selectedParticipant = ref(null);
const selectedSkill = ref(null);
const selectedCell = ref(null);

const form = useForm({
    user_id: null,
    skill_id: null,
    current_level: 1,
    target_level: 3,
});

const openCellEditor = (participant, skill) => {
    const cell = getCellData(participant, skill.id);
    selectedParticipant.value = participant;
    selectedSkill.value = skill;
    selectedCell.value = cell;

    form.user_id = participant.id;
    form.skill_id = skill.id;
    form.current_level = cell.current ?? cell.current_level ?? 1;
    form.target_level = cell.target ?? cell.target_level ?? 3;
    activeModal.value = true;
};

const closeCellEditor = () => {
    activeModal.value = false;
    selectedParticipant.value = null;
    selectedSkill.value = null;
    selectedCell.value = null;
    form.clearErrors();
};

const submitSkillUpdate = () => {
    form.patch('/admin/skill-matrix/cell', {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            closeCellEditor();
        },
    });
};
</script>

<template>
    <Head title="Skill Matrix & Kepatuhan Masa Berlaku" />

    <AdminLayout
        title="Skill Matrix & Pemantauan Masa Berlaku Kompetensi"
        subtitle="Setiap sel membaca masa berlaku sertifikat (valid_until) secara real-time untuk mendeteksi H-60 & Sertifikat Expired"
    >
        <div class="space-y-4">
            <!-- Header & Legenda 4 Warna Status Kompetensi -->
            <div class="flex flex-wrap items-center justify-between gap-3 bg-white border border-slate-200 rounded-xl p-3.5 shadow-sm">
                <div>
                    <h1 class="text-base font-bold text-slate-800">
                        Skill Matrix &amp; Pemantauan Masa Berlaku Kompetensi
                    </h1>
                    <p class="text-xs text-slate-500">
                        Klik pada sel pertemuan (Pekerja x Skill) untuk memperbarui nilai evaluasi kompetensi
                    </p>
                </div>

                <!-- Legenda 4 Status Visual Sel -->
                <div class="flex flex-wrap items-center gap-2 text-[11px] font-semibold">
                    <span class="px-2.5 py-1 rounded-md bg-emerald-100 text-emerald-800 border border-emerald-200">
                        Hijau: Kompeten &amp; Aktif
                    </span>
                    <span class="px-2.5 py-1 rounded-md bg-amber-100 text-amber-800 border border-amber-300">
                        Kuning: Warning H-60 (Wajib Refreshment)
                    </span>
                    <span class="px-2.5 py-1 rounded-md bg-rose-100 text-rose-900 border border-rose-300">
                        Merah Tua: Sertifikat EXPIRED (Gugur)
                    </span>
                    <span class="px-2.5 py-1 rounded-md bg-slate-100 text-slate-600 border border-slate-200">
                        Abu/Merah Muda: Skill Gap (Current &lt; Target)
                    </span>
                </div>
            </div>

            <!-- Global Filter Bar -->
            <GlobalTableFilterBar
                route-url="/admin/skill-matrix"
                :filters="filters"
                :filter-options="filterOptions"
                :departments="departments"
                :companies="companies"
                :course-codes="courseCodes"
                export-url="/admin/exports/skill-matrix"
                export-label="Export Matrix CSV"
            />

            <!-- Tabel Heatmap Matrix -->
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                                <th class="p-3 sticky left-0 bg-slate-50 z-10 border-r border-slate-200 min-w-[230px]">
                                    Pekerja &amp; Jabatan
                                </th>
                                <th
                                    v-for="skill in skills"
                                    :key="skill.id"
                                    class="p-2.5 text-center border-r border-slate-100 min-w-[125px]"
                                >
                                    <div class="text-[10px] font-mono text-blue-600">{{ skill.code }}</div>
                                    <div class="text-slate-700 font-bold leading-tight mt-0.5">{{ skill.name }}</div>
                                    <div class="text-[9px] text-slate-400 font-normal mt-0.5">{{ skill.category }}</div>
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100 text-xs">
                            <tr v-for="p in participants.data" :key="p.id" class="hover:bg-slate-50/60 group">
                                <!-- Info Pekerja (Sticky Kiri) -->
                                <td class="p-3 sticky left-0 bg-white group-hover:bg-slate-50 z-10 border-r border-slate-200">
                                    <div class="font-semibold text-slate-800 truncate" :title="p.name">{{ p.name }}</div>
                                    <div class="text-[11px] text-slate-400 font-mono">
                                        {{ p.nik }} &bull; {{ p.position }}
                                    </div>
                                    <div class="text-[10px] text-slate-500 mt-0.5">{{ p.org_name || p.department }}</div>
                                    <div class="mt-1">
                                        <span
                                            class="inline-block text-[9px] font-bold px-1.5 py-0.2 rounded"
                                            :class="p.match_status === 'Matched Position' ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-rose-100 text-rose-800 border border-rose-200'"
                                        >
                                            {{ p.match_status || (p.has_position ? 'Matched Position' : 'No Match') }}
                                        </span>
                                    </div>
                                </td>

                                <!-- Sel Skill Matrix Dinamis (Berubah Warna Sesuai Standar Jabatan M/NM/NR & Masa Berlaku) -->
                                <td
                                    v-for="skill in skills"
                                    :key="skill.id"
                                    class="p-2 text-center border-r border-slate-100 align-middle"
                                >
                                    <button
                                        type="button"
                                        @click="openCellEditor(p, skill)"
                                        :disabled="getCellData(p, skill.id).status === 'not_required'"
                                        class="w-full rounded-lg p-1.5 border transition text-center flex flex-col items-center justify-center relative disabled:cursor-not-allowed"
                                        :class="{
                                            'bg-slate-100 border-slate-200 text-slate-400 hover:bg-slate-200/70':
                                                getCellData(p, skill.id).status === 'not_required',
                                            'bg-emerald-50 border-emerald-200 text-emerald-900 hover:bg-emerald-100':
                                                getCellData(p, skill.id).status === 'valid',
                                            'bg-amber-50 border-amber-300 text-amber-900 animate-pulse hover:bg-amber-100':
                                                getCellData(p, skill.id).status === 'warning_h60',
                                            'bg-rose-100 border-rose-400 text-rose-900 hover:bg-rose-200':
                                                getCellData(p, skill.id).status === 'expired',
                                            'bg-rose-50/40 border-slate-200 text-slate-500 hover:bg-slate-100':
                                                getCellData(p, skill.id).status === 'gap',
                                        }"
                                        :title="
                                            getCellData(p, skill.id).status === 'not_required'
                                                ? `${skill.name}: Tidak dipersyaratkan di standar jabatan ${p.position}`
                                                : (getCellData(p, skill.id).cert_no
                                                    ? `No Sertifikat: ${getCellData(p, skill.id).cert_no}`
                                                    : `Klik untuk ubah level ${p.name} - ${skill.name}`)
                                        "
                                    >
                                        <!-- Badge Standar Jabatan [M] / [NM] / [NR] -->
                                        <div class="w-full flex justify-between items-center mb-0.5">
                                            <span
                                                class="text-[8px] font-black px-1 rounded uppercase tracking-wider"
                                                :class="{
                                                    'bg-emerald-600 text-white': getCellData(p, skill.id).standard_type === 'M',
                                                    'bg-amber-400 text-amber-950 font-bold': getCellData(p, skill.id).standard_type === 'NM',
                                                    'bg-slate-300 text-slate-600': getCellData(p, skill.id).standard_type === 'NR',
                                                }"
                                            >
                                                [{{ getCellData(p, skill.id).standard_type || 'NR' }}]
                                            </span>
                                            <span
                                                v-if="getCellData(p, skill.id).status !== 'not_required'"
                                                class="font-mono text-[9px] text-slate-400"
                                            >
                                                Tgt: {{ getCellData(p, skill.id).target }}
                                            </span>
                                        </div>

                                        <!-- Nilai Current / Target -->
                                        <div
                                            class="font-bold text-xs"
                                            :class="{
                                                'line-through text-rose-700': getCellData(p, skill.id).status === 'expired',
                                                'text-slate-400': getCellData(p, skill.id).status === 'not_required',
                                            }"
                                        >
                                            <template v-if="getCellData(p, skill.id).status === 'not_required'">
                                                {{ getCellData(p, skill.id).current }} / &ndash;
                                            </template>
                                            <template v-else>
                                                {{ getCellData(p, skill.id).current }} / {{ getCellData(p, skill.id).target }}
                                            </template>
                                        </div>

                                        <!-- Keterangan Status Tanggal / Standar di Bawah Angka -->
                                        <div
                                            v-if="getCellData(p, skill.id).status === 'not_required'"
                                            class="text-[9px] font-bold text-slate-400 mt-0.5 uppercase"
                                        >
                                            N/A (NR)
                                        </div>
                                        <div
                                            v-else-if="getCellData(p, skill.id).status === 'expired'"
                                            class="text-[9px] font-extrabold text-rose-800 uppercase mt-0.5"
                                        >
                                            EXPIRED ({{ getCellData(p, skill.id).valid_until }})
                                        </div>
                                        <div
                                            v-else-if="getCellData(p, skill.id).status === 'warning_h60'"
                                            class="text-[9px] font-bold text-amber-800 mt-0.5"
                                        >
                                            H-{{ getCellData(p, skill.id).days_left }} ({{ getCellData(p, skill.id).valid_until }})
                                        </div>
                                        <div
                                            v-else-if="getCellData(p, skill.id).status === 'valid' && getCellData(p, skill.id).valid_until"
                                            class="text-[9px] text-emerald-700 mt-0.5"
                                        >
                                            s/d {{ getCellData(p, skill.id).valid_until }}
                                        </div>
                                        <div
                                            v-else-if="getCellData(p, skill.id).status === 'valid'"
                                            class="text-[9px] text-emerald-700 font-semibold mt-0.5"
                                        >
                                            Kompeten (Aktif)
                                        </div>
                                        <div v-else class="text-[9px] text-rose-600 font-medium mt-0.5">
                                            Gap: {{ getCellData(p, skill.id).current - getCellData(p, skill.id).target }}
                                        </div>
                                    </button>
                                </td>
                            </tr>

                            <tr v-if="!participants.data?.length">
                                <td :colspan="skills.length + 1" class="py-8 text-center text-slate-400">
                                    Tidak ada data pekerja yang sesuai dengan filter.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer (25 Baris per Halaman) -->
                <div
                    v-if="participants.links?.length > 3"
                    class="px-4 py-3 bg-slate-50 border-t border-slate-200 flex flex-wrap items-center justify-between gap-2 text-xs"
                >
                    <div class="text-slate-500">
                        Menampilkan <span class="font-semibold text-slate-800">{{ participants.from }}</span> -
                        <span class="font-semibold text-slate-800">{{ participants.to }}</span> dari
                        <span class="font-semibold text-slate-800">{{ participants.total }}</span> pekerja
                    </div>

                    <div class="flex items-center gap-1">
                        <template v-for="(link, idx) in participants.links" :key="idx">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                preserve-scroll
                                preserve-state
                                v-html="link.label"
                                :class="[
                                    'px-2.5 py-1 rounded border text-xs font-medium',
                                    link.active
                                        ? 'bg-blue-600 text-white border-blue-600'
                                        : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100',
                                ]"
                            />
                            <span
                                v-else
                                v-html="link.label"
                                class="px-2.5 py-1 rounded border border-slate-200 text-slate-300 text-xs"
                            />
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal / Popover Interaktif Update Level Kompetensi -->
        <div
            v-if="activeModal && selectedParticipant && selectedSkill"
            class="fixed inset-0 z-50 bg-slate-900/50 flex items-center justify-center p-4"
            @click.self="closeCellEditor"
        >
            <div class="bg-white border border-slate-200 rounded-xl w-full max-w-md shadow-xl overflow-hidden">
                <div class="px-5 py-3.5 bg-blue-50/80 border-b border-blue-100 text-slate-800 flex items-center justify-between">
                    <div>
                        <div class="text-[10px] uppercase tracking-wider text-blue-600 font-bold">
                            Update Penilaian Kompetensi
                        </div>
                        <h3 class="text-sm font-bold text-slate-900 truncate">{{ selectedParticipant.name }}</h3>
                    </div>
                    <button
                        type="button"
                        @click="closeCellEditor"
                        class="text-slate-400 hover:text-slate-700 text-sm font-bold px-2 py-1"
                    >
                        &#10005;
                    </button>
                </div>

                <form @submit.prevent="submitSkillUpdate" class="p-5 space-y-4">
                    <div class="p-3 rounded bg-slate-50 border border-slate-200 text-xs space-y-1">
                        <div class="text-slate-500">Kompetensi / Skill:</div>
                        <div class="font-bold text-slate-900">
                            {{ selectedSkill.code }} &mdash; {{ selectedSkill.name }}
                        </div>
                        <div class="text-[11px] text-slate-500">
                            Organisasi: {{ selectedParticipant.org_name || selectedParticipant.department }}
                        </div>
                        <div v-if="selectedCell?.cert_no" class="text-[11px] font-mono text-blue-700 pt-1">
                            Sertifikat Terakhir: {{ selectedCell.cert_no }}
                            <span v-if="selectedCell.valid_until">(s/d {{ selectedCell.valid_until }})</span>
                        </div>
                    </div>

                    <!-- Pilih Current Level (1-5) -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2">
                            Current Level (Aktual: {{ form.current_level }})
                        </label>
                        <div class="grid grid-cols-5 gap-2">
                            <button
                                v-for="lvl in [1, 2, 3, 4, 5]"
                                :key="`cur-${lvl}`"
                                type="button"
                                @click="form.current_level = lvl"
                                :class="[
                                    'py-2.5 rounded border text-xs font-extrabold transition-colors',
                                    form.current_level === lvl
                                        ? 'bg-blue-600 text-white border-blue-600'
                                        : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50',
                                ]"
                            >
                                Lv. {{ lvl }}
                            </button>
                        </div>
                    </div>

                    <!-- Pilih Target Level (1-5) -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2">
                            Target Level Standar (Target: {{ form.target_level }})
                        </label>
                        <div class="grid grid-cols-5 gap-2">
                            <button
                                v-for="lvl in [1, 2, 3, 4, 5]"
                                :key="`tar-${lvl}`"
                                type="button"
                                @click="form.target_level = lvl"
                                :class="[
                                    'py-2 rounded border text-xs font-semibold transition-colors',
                                    form.target_level === lvl
                                        ? 'bg-slate-800 text-white border-slate-800'
                                        : 'bg-white text-slate-600 border-slate-300 hover:bg-slate-50',
                                ]"
                            >
                                Target {{ lvl }}
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-200">
                        <button
                            type="button"
                            @click="closeCellEditor"
                            class="px-3.5 py-2 rounded border border-slate-300 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-4 py-2 rounded bg-blue-600 text-white text-xs font-semibold hover:bg-blue-700 disabled:opacity-50"
                        >
                            {{ form.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
