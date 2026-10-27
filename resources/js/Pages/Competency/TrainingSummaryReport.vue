<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import GlobalTableFilterBar from '@/Components/GlobalTableFilterBar.vue';

const props = defineProps({
    trainingTypeSummary: {
        type: Array,
        default: () => [],
    },
    divisiSummary: {
        type: Array,
        default: () => [],
    },
    employeeCompletionList: {
        type: Array,
        default: () => [],
    },
    employeeCompletionPagination: {
        type: Object,
        default: () => ({ current_page: 1, last_page: 1, per_page: 50, total: 0 }),
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    filterOptions: {
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
});

const activeTab = ref('training_type');

const internalTrainings = computed(() => {
    return (props.trainingTypeSummary || []).filter(
        (t) => String(t.executor || '').toUpperCase() === 'INTERNAL'
    );
});

const externalTrainings = computed(() => {
    return (props.trainingTypeSummary || []).filter(
        (t) => String(t.executor || '').toUpperCase() !== 'INTERNAL'
    );
});

const goToEmployeePage = (page) => {
    if (page < 1 || page > props.employeeCompletionPagination.last_page) return;

    router.get('/competency/training-summary', {
        ...props.filters,
        employee_page: page,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};
</script>

<template>
    <Head title="Rangkuman Pelatihan &amp; Kepatuhan (MDKA Group Standard)" />

    <AdminLayout
        title="Rangkuman Pelatihan &amp; Kepatuhan Divisi"
        subtitle="Laporan Rekapitulasi Pelatihan Internal-Eksternal, Status Mandatory per Divisi, dan Tracker Penyelesaian Karyawan"
    >
        <div class="space-y-5">
            <!-- GLOBAL FILTER BAR DARI EXCEL MDKA -->
            <GlobalTableFilterBar
                :filters="filters"
                :filter-options="filterOptions"
                :departments="departments"
                :companies="companies"
                :course-codes="courseCodes"
                filter-url="/competency/training-summary"
                search-placeholder="Cari NIK, Nama Pekerja, atau Kode/Judul Training..."
            />

            <!-- 3 TABS NAV DARI EXCEL MDKA -->
            <div class="border-b border-slate-200">
                <nav class="flex flex-wrap gap-2 -mb-px">
                    <button
                        type="button"
                        @click="activeTab = 'training_type'"
                        :class="[
                            activeTab === 'training_type'
                                ? 'border-slate-900 text-slate-900 font-bold bg-white shadow-sm'
                                : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300',
                            'px-4 py-2.5 text-xs rounded-t-lg border-b-2 transition flex items-center gap-1.5'
                        ]"
                    >
                        <span>Tab A. Rangkuman Per Jenis Training (Internal vs External)</span>
                    </button>

                    <button
                        type="button"
                        @click="activeTab = 'divisi'"
                        :class="[
                            activeTab === 'divisi'
                                ? 'border-slate-900 text-slate-900 font-bold bg-white shadow-sm'
                                : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300',
                            'px-4 py-2.5 text-xs rounded-t-lg border-b-2 transition flex items-center gap-1.5'
                        ]"
                    >
                        <span>Tab B. Summary Per Divisi &amp; Per Karyawan (Mandatory vs Non-Mandatory)</span>
                    </button>

                    <button
                        type="button"
                        @click="activeTab = 'emp_completion'"
                        :class="[
                            activeTab === 'emp_completion'
                                ? 'border-slate-900 text-slate-900 font-bold bg-white shadow-sm'
                                : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300',
                            'px-4 py-2.5 text-xs rounded-t-lg border-b-2 transition flex items-center gap-1.5'
                        ]"
                    >
                        <span>Tab C. Employee Completion Tracker</span>
                        <span class="bg-emerald-100 text-emerald-800 text-[10px] font-black px-1.5 py-0.2 rounded-full">100% Tracker</span>
                    </button>
                </nav>
            </div>

            <!-- TAB A: RANGKUMAN PER JENIS TRAINING (Internal vs External) -->
            <div v-show="activeTab === 'training_type'" class="space-y-6">
                <!-- SEKSI 1: TRAINING INTERNAL -->
                <div class="bg-white border border-slate-200 rounded-xl p-4 sm:p-5 shadow-sm">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-blue-600"></span>
                                <h2 class="text-xs font-black uppercase tracking-wider text-slate-900">
                                    TRAINING INTERNAL
                                </h2>
                                <span class="bg-blue-100 text-blue-800 text-[10px] font-bold px-2 py-0.5 rounded-full">
                                    {{ internalTrainings.length }} Modul
                                </span>
                            </div>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Pelatihan diselenggarakan oleh L&amp;D Internal dan Trainer tersertifikasi internal site MDKA.
                            </p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold uppercase tracking-wider text-slate-600">
                                    <th class="p-2.5">Kode Training</th>
                                    <th class="p-2.5">Judul Training</th>
                                    <th class="p-2.5 text-center">Kategori</th>
                                    <th class="p-2.5 text-center">Total Wajib Ikut (M)</th>
                                    <th class="p-2.5 text-center text-emerald-700">Sudah Selesai (Done)</th>
                                    <th class="p-2.5 text-center text-amber-700">Belum Selesai (Not Yet)</th>
                                    <th class="p-2.5 text-center text-rose-700">Expired</th>
                                    <th class="p-2.5 text-right">% Selesai</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-if="internalTrainings.length === 0">
                                    <td colspan="8" class="p-4 text-center text-slate-400">
                                        Tidak ada data pelatihan internal untuk filter yang dipilih.
                                    </td>
                                </tr>
                                <tr v-for="t in internalTrainings" :key="t.id" class="hover:bg-slate-50 transition">
                                    <td class="p-2.5 font-mono font-bold text-blue-700">{{ t.code }}</td>
                                    <td class="p-2.5 font-bold text-slate-900">{{ t.title }}</td>
                                    <td class="p-2.5 text-center">
                                        <span class="text-[10px] font-semibold bg-slate-100 text-slate-700 px-1.5 py-0.5 rounded">
                                            {{ t.category }}
                                        </span>
                                    </td>
                                    <td class="p-2.5 text-center font-mono font-bold">{{ t.total_required }}</td>
                                    <td class="p-2.5 text-center font-mono text-emerald-600 font-bold">{{ t.completed }}</td>
                                    <td class="p-2.5 text-center font-mono text-amber-600 font-bold">{{ t.not_yet }}</td>
                                    <td class="p-2.5 text-center font-mono text-rose-600 font-bold" title="Bagian dari sudah selesai yang masa berlakunya habis">
                                        {{ t.expired }}
                                    </td>
                                    <td class="p-2.5 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <div class="w-16 h-2 bg-slate-100 rounded-full overflow-hidden">
                                                <div
                                                    class="h-full rounded-full transition-all"
                                                    :class="t.completion_pct >= 80 ? 'bg-emerald-500' : (t.completion_pct >= 50 ? 'bg-amber-500' : 'bg-rose-500')"
                                                    :style="{ width: `${t.completion_pct}%` }"
                                                ></div>
                                            </div>
                                            <span class="font-mono font-bold text-slate-900 w-10 text-right">{{ t.completion_pct }}%</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- SEKSI 2: TRAINING EXTERNAL -->
                <div class="bg-white border border-slate-200 rounded-xl p-4 sm:p-5 shadow-sm">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-purple-600"></span>
                                <h2 class="text-xs font-black uppercase tracking-wider text-slate-900">
                                    TRAINING EXTERNAL (BNSP / KEMNAKER / ESDM)
                                </h2>
                                <span class="bg-purple-100 text-purple-800 text-[10px] font-bold px-2 py-0.5 rounded-full">
                                    {{ externalTrainings.length }} Modul
                                </span>
                            </div>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Pelatihan lisensi wajib, sertifikasi kompetensi kerja (POP, POM, POU, SIO, K3) oleh lembaga eksternal.
                            </p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold uppercase tracking-wider text-slate-600">
                                    <th class="p-2.5">Kode Training</th>
                                    <th class="p-2.5">Judul Training</th>
                                    <th class="p-2.5 text-center">Kategori</th>
                                    <th class="p-2.5 text-center">Total Wajib Ikut (M)</th>
                                    <th class="p-2.5 text-center text-emerald-700">Sudah Selesai (Done)</th>
                                    <th class="p-2.5 text-center text-amber-700">Belum Selesai (Not Yet)</th>
                                    <th class="p-2.5 text-center text-rose-700">Expired</th>
                                    <th class="p-2.5 text-right">% Selesai</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-if="externalTrainings.length === 0">
                                    <td colspan="8" class="p-4 text-center text-slate-400">
                                        Tidak ada data pelatihan eksternal untuk filter yang dipilih.
                                    </td>
                                </tr>
                                <tr v-for="t in externalTrainings" :key="t.id" class="hover:bg-slate-50 transition">
                                    <td class="p-2.5 font-mono font-bold text-purple-700">{{ t.code }}</td>
                                    <td class="p-2.5 font-bold text-slate-900">{{ t.title }}</td>
                                    <td class="p-2.5 text-center">
                                        <span class="text-[10px] font-semibold bg-slate-100 text-slate-700 px-1.5 py-0.5 rounded">
                                            {{ t.category }}
                                        </span>
                                    </td>
                                    <td class="p-2.5 text-center font-mono font-bold">{{ t.total_required }}</td>
                                    <td class="p-2.5 text-center font-mono text-emerald-600 font-bold">{{ t.completed }}</td>
                                    <td class="p-2.5 text-center font-mono text-amber-600 font-bold">{{ t.not_yet }}</td>
                                    <td class="p-2.5 text-center font-mono text-rose-600 font-bold" title="Bagian dari sudah selesai yang masa berlakunya habis">
                                        {{ t.expired }}
                                    </td>
                                    <td class="p-2.5 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <div class="w-16 h-2 bg-slate-100 rounded-full overflow-hidden">
                                                <div
                                                    class="h-full rounded-full transition-all"
                                                    :class="t.completion_pct >= 80 ? 'bg-emerald-500' : (t.completion_pct >= 50 ? 'bg-amber-500' : 'bg-rose-500')"
                                                    :style="{ width: `${t.completion_pct}%` }"
                                                ></div>
                                            </div>
                                            <span class="font-mono font-bold text-slate-900 w-10 text-right">{{ t.completion_pct }}%</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB B: SUMMARY PER DIVISI (Mandatory vs Non-Mandatory) -->
            <div v-show="activeTab === 'divisi'" class="space-y-4">
                <div class="bg-white border border-slate-200 rounded-xl p-4 sm:p-5 shadow-sm">
                    <div class="border-b border-slate-100 pb-3 mb-4">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-900">
                            Summary Per Divisi &amp; Per Karyawan (Mandatory vs Non-Mandatory)
                        </h2>
                        <p class="text-xs text-slate-500 mt-0.5">Pemetaan kuantitas slot training: Mandatory Internal vs External dan Non-Mandatory.</p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold uppercase tracking-wider text-slate-600">
                                    <th class="p-2.5">Divisi / Kontraktor</th>
                                    <th class="p-2.5 text-center">Tipe</th>
                                    <th class="p-2.5 text-center">Jumlah Karyawan</th>
                                    <th class="p-2.5 text-center text-blue-700 bg-blue-50/50">Mandatory Internal</th>
                                    <th class="p-2.5 text-center text-purple-700 bg-purple-50/50">Mandatory External</th>
                                    <th class="p-2.5 text-center font-extrabold text-slate-900 bg-slate-100">Total Mandatory</th>
                                    <th class="p-2.5 text-center text-slate-600">Non-Mandatory Internal</th>
                                    <th class="p-2.5 text-center text-slate-600">Non-Mandatory External</th>
                                    <th class="p-2.5 text-right font-black">Grand Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-if="divisiSummary.length === 0">
                                    <td colspan="9" class="p-4 text-center text-slate-400">
                                        Tidak ada data divisi untuk filter yang dipilih.
                                    </td>
                                </tr>
                                <tr v-for="d in divisiSummary" :key="d.org_id" class="hover:bg-slate-50 transition">
                                    <td class="p-2.5 font-bold text-slate-900">{{ d.org_name }}</td>
                                    <td class="p-2.5 text-center">
                                        <span
                                            class="text-[10px] font-bold px-1.5 py-0.5 rounded uppercase"
                                            :class="d.org_type === 'internal_dept' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-800'"
                                        >
                                            {{ d.org_type === 'internal_dept' ? 'Internal' : 'Kontraktor' }}
                                        </span>
                                    </td>
                                    <td class="p-2.5 text-center font-mono font-bold">{{ d.total_workers }}</td>
                                    <td class="p-2.5 text-center font-mono font-bold text-blue-700 bg-blue-50/30">{{ d.mandatory_internal }}</td>
                                    <td class="p-2.5 text-center font-mono font-bold text-purple-700 bg-purple-50/30">{{ d.mandatory_external }}</td>
                                    <td class="p-2.5 text-center font-mono font-black text-slate-900 bg-slate-100/60">{{ d.total_mandatory }}</td>
                                    <td class="p-2.5 text-center font-mono text-slate-600">{{ d.non_mandatory_internal }}</td>
                                    <td class="p-2.5 text-center font-mono text-slate-600">{{ d.non_mandatory_external }}</td>
                                    <td class="p-2.5 text-right font-mono font-black text-slate-900">{{ d.grand_total }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB C: EMPLOYEE COMPLETION TRACKER (Emp Completion) -->
            <div v-show="activeTab === 'emp_completion'" class="space-y-4">
                <div class="bg-white border border-slate-200 rounded-xl p-4 sm:p-5 shadow-sm">
                    <div class="border-b border-slate-100 pb-3 mb-4">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-900">
                            Employee Completion Tracker (Sheet Emp Completion)
                        </h2>
                        <p class="text-xs text-slate-500 mt-0.5">Status kepatuhan 100% per individu pekerja terhadap seluruh standar training Mandatory.</p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold uppercase tracking-wider text-slate-600">
                                    <th class="p-2.5">NIK</th>
                                    <th class="p-2.5">Employee Name</th>
                                    <th class="p-2.5">Site</th>
                                    <th class="p-2.5">Department</th>
                                    <th class="p-2.5">Level</th>
                                    <th class="p-2.5 text-center">Match Status</th>
                                    <th class="p-2.5 text-center">Required (M)</th>
                                    <th class="p-2.5 text-center text-emerald-700">Completed (M)</th>
                                    <th class="p-2.5 text-center text-amber-700">Not Yet</th>
                                    <th class="p-2.5 text-center text-rose-700">Expired</th>
                                    <th class="p-2.5 text-center font-extrabold">Fully Completed</th>
                                    <th class="p-2.5 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-if="employeeCompletionList.length === 0">
                                    <td colspan="12" class="p-4 text-center text-slate-400">
                                        Tidak ada data pekerja untuk filter yang dipilih.
                                    </td>
                                </tr>
                                <tr v-for="emp in employeeCompletionList" :key="emp.id" class="hover:bg-slate-50 transition">
                                    <td class="p-2.5 font-mono font-bold text-slate-700">{{ emp.nik }}</td>
                                    <td class="p-2.5 font-bold text-slate-900">{{ emp.name }}</td>
                                    <td class="p-2.5 text-slate-500">{{ emp.site }}</td>
                                    <td class="p-2.5 text-slate-700">{{ emp.department }}</td>
                                    <td class="p-2.5 text-slate-600 font-medium">{{ emp.level }}</td>
                                    <td class="p-2.5 text-center">
                                        <span
                                            class="text-[10px] font-bold px-1.5 py-0.5 rounded"
                                            :class="emp.match_status.includes('Matched') ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                                        >
                                            {{ emp.match_status }}
                                        </span>
                                    </td>
                                    <td class="p-2.5 text-center font-mono font-bold">{{ emp.required_mandatory }}</td>
                                    <td class="p-2.5 text-center font-mono text-emerald-600 font-bold">{{ emp.completed_mandatory }}</td>
                                    <td class="p-2.5 text-center font-mono text-amber-600 font-bold">{{ emp.not_yet_mandatory }}</td>
                                    <td class="p-2.5 text-center font-mono text-rose-600 font-bold">{{ emp.expired_mandatory }}</td>
                                    <td class="p-2.5 text-center">
                                        <span
                                            class="text-[10px] font-black px-2 py-0.5 rounded uppercase"
                                            :class="emp.is_fully_completed === 'YES' ? 'bg-emerald-600 text-white' : 'bg-rose-100 text-rose-800 font-bold'"
                                        >
                                            {{ emp.is_fully_completed }}
                                        </span>
                                    </td>
                                    <td class="p-2.5 text-right">
                                        <Link
                                            :href="`/competency/individual-view?user_id=${emp.id}`"
                                            class="text-xs font-bold text-slate-900 hover:underline"
                                        >
                                            Detail &rarr;
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4 flex flex-col gap-3 border-t border-slate-200 pt-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-slate-600" aria-live="polite">
                            Halaman {{ employeeCompletionPagination.current_page }} dari
                            {{ employeeCompletionPagination.last_page }} ·
                            {{ employeeCompletionPagination.total }} karyawan
                        </p>
                        <div class="flex gap-2">
                            <button
                                type="button"
                                :disabled="employeeCompletionPagination.current_page <= 1"
                                class="min-h-11 rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 focus:outline-none focus:ring-2 focus:ring-blue-600"
                                @click="goToEmployeePage(employeeCompletionPagination.current_page - 1)"
                            >
                                Sebelumnya
                            </button>
                            <button
                                type="button"
                                :disabled="employeeCompletionPagination.current_page >= employeeCompletionPagination.last_page"
                                class="min-h-11 rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 focus:outline-none focus:ring-2 focus:ring-blue-600"
                                @click="goToEmployeePage(employeeCompletionPagination.current_page + 1)"
                            >
                                Berikutnya
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </AdminLayout>
</template>
