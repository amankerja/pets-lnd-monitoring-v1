<script setup>
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    expiringList: { type: Array, required: true },
    summary: { type: Object, required: true },
});

const selectedFilter = ref('all'); // all, expired, critical, warning
const selectedItems = ref([]); // Menyimpan objek { course_id, user_id }

const renewalEndpoint = () => {
    return typeof route === 'function' ? route('org.renewal.store') : '/organization/renewal-requests';
};

const filteredList = computed(() => {
    if (selectedFilter.value === 'all') return props.expiringList;
    return props.expiringList.filter((item) => item.severity === selectedFilter.value);
});

// Kelompokkan item yang dicentang berdasarkan course_id untuk batch submit
const submitSingleRenewal = (item) => {
    const form = useForm({
        course_id: item.course_id,
        participant_ids: [item.user_id],
        reason_notes: `Pembaruan sertifikat ${item.certificate_number} (Sisa ${item.days_left} hari)`,
    });
    form.post(renewalEndpoint(), { preserveScroll: true });
};

const submitBatchByCourse = (courseId) => {
    const userIds = selectedItems.value
        .filter((i) => i.course_id === courseId)
        .map((i) => i.user_id);

    if (!userIds.length) return;

    const form = useForm({
        course_id: courseId,
        participant_ids: userIds,
        reason_notes: `Pengajuan kolektif pembaruan sertifikat untuk ${userIds.length} personel.`,
    });

    form.post(renewalEndpoint(), {
        preserveScroll: true,
        onSuccess: () => {
            selectedItems.value = selectedItems.value.filter((i) => i.course_id !== courseId);
        },
    });
};

// Hitung grup course yang sedang dicentang oleh Admin
const selectedCourseGroups = computed(() => {
    const groups = {};
    selectedItems.value.forEach((item) => {
        if (!groups[item.course_id]) {
            groups[item.course_id] = {
                course_id: item.course_id,
                course_title: item.course_title,
                count: 0,
            };
        }
        groups[item.course_id].count++;
    });
    return Object.values(groups);
});
</script>

<template>
    <div class="bg-white border border-slate-200">
        <!-- Top Alert Banner & Filter Summary -->
        <div class="p-4 border-b border-slate-200 flex flex-wrap items-center justify-between gap-4 bg-slate-50">
            <div>
                <h2 class="text-sm font-bold uppercase tracking-wider text-slate-900">
                    Radar Masa Berlaku Sertifikat (H-60 &amp; Expired)
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    Deteksi otomatis pekerja internal &amp; kontraktor yang wajib mengikuti training pembaruan (Refreshment).
                </p>
            </div>

            <!-- Filter Pills -->
            <div class="flex flex-wrap items-center gap-2 text-xs font-bold">
                <button
                    type="button"
                    @click="selectedFilter = 'all'"
                    :class="selectedFilter === 'all' ? 'bg-blue-600 text-white border border-blue-600' : 'bg-white text-slate-700 border border-slate-300'"
                    class="px-3 py-1.5 rounded-lg"
                >
                    Semua ({{ expiringList.length }})
                </button>
                <button
                    type="button"
                    @click="selectedFilter = 'expired'"
                    :class="selectedFilter === 'expired' ? 'bg-rose-700 text-white border border-rose-700' : 'bg-rose-50 text-rose-800 border border-rose-300'"
                    class="px-3 py-1.5 rounded-lg"
                >
                    Expired ({{ summary.expired_count }})
                </button>
                <button
                    type="button"
                    @click="selectedFilter = 'critical'"
                    :class="selectedFilter === 'critical' ? 'bg-amber-600 text-white border border-amber-600' : 'bg-amber-50 text-amber-800 border border-amber-300'"
                    class="px-3 py-1.5 rounded-lg"
                >
                    Kritis &le; 30 Hari ({{ summary.critical_count }})
                </button>
                <button
                    type="button"
                    @click="selectedFilter = 'warning'"
                    :class="selectedFilter === 'warning' ? 'bg-amber-500 text-white border border-amber-500' : 'bg-amber-50/70 text-amber-800 border border-amber-200'"
                    class="px-3 py-1.5 rounded-lg"
                >
                    31-60 Hari ({{ summary.warning_count }})
                </button>
            </div>
        </div>

        <!-- Bar Aksi Kolektif (Muncul saat ada baris yang dicentang) -->
        <div v-if="selectedCourseGroups.length" class="bg-blue-50 border-b border-blue-200 text-blue-900 px-4 py-2.5 flex flex-wrap items-center justify-between gap-2 text-xs">
            <span class="font-semibold">Pengajuan Kolektif Terpilih:</span>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="grp in selectedCourseGroups"
                    :key="grp.course_id"
                    type="button"
                    @click="submitBatchByCourse(grp.course_id)"
                    class="bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg px-3 py-1.5 font-bold"
                >
                    Ajukan {{ grp.count }} Pekerja untuk [{{ grp.course_title }}] &rarr;
                </button>
            </div>
        </div>

        <!-- Tabel Data Bersih -->
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left border-collapse">
                <thead>
                    <tr class="bg-slate-100 text-slate-700 border-b border-slate-200 uppercase">
                        <th class="p-3 w-8 text-center">#</th>
                        <th class="p-3">NIK &amp; Nama Pekerja</th>
                        <th class="p-3">Unit / Kontraktor</th>
                        <th class="p-3">Pelatihan &amp; No. Sertifikat</th>
                        <th class="p-3 text-center">Tgl Kadaluarsa</th>
                        <th class="p-3 text-center">Status Sisa Waktu</th>
                        <th class="p-3 text-right">Tindakan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <tr v-for="item in filteredList" :key="item.enrollment_id" class="hover:bg-slate-50">
                        <td class="p-3 text-center">
                            <input
                                v-if="!item.is_requested"
                                type="checkbox"
                                :value="item"
                                v-model="selectedItems"
                                class="border-slate-300 text-slate-900 focus:ring-0"
                            />
                        </td>
                        <td class="p-3">
                            <p class="font-bold text-slate-900">{{ item.user_name }}</p>
                            <p class="font-mono text-[11px] text-slate-500">{{ item.user_nik }}</p>
                        </td>
                        <td class="p-3">
                            <span
                                :class="item.organization_type === 'contractor' ? 'bg-purple-50 text-purple-800 border-purple-200' : 'bg-slate-100 text-slate-700 border-slate-300'"
                                class="px-2 py-0.5 border text-[10px] font-bold uppercase"
                            >
                                {{ item.organization_type === 'contractor' ? 'KONTRAKTOR' : 'INTERNAL' }}
                            </span>
                            <p class="font-medium text-slate-800 mt-1">{{ item.organization_name }}</p>
                        </td>
                        <td class="p-3">
                            <p class="font-bold text-slate-900">[{{ item.course_code }}] {{ item.course_title }}</p>
                            <p class="font-mono text-[11px] text-slate-500">No: {{ item.certificate_number || '-' }}</p>
                        </td>
                        <td class="p-3 text-center font-mono font-bold text-slate-800">
                            {{ item.expires_at }}
                        </td>
                        <td class="p-3 text-center">
                            <span
                                v-if="item.severity === 'expired'"
                                class="bg-rose-100 text-rose-800 border border-rose-300 px-2 py-1 font-bold inline-block"
                            >
                                EXPIRED ({{ Math.abs(item.days_left) }} Hari Lalu)
                            </span>
                            <span
                                v-else-if="item.severity === 'critical'"
                                class="bg-amber-100 text-amber-900 border border-amber-300 px-2 py-1 font-bold inline-block"
                            >
                                H-{{ item.days_left }} Hari Lagi
                            </span>
                            <span
                                v-else
                                class="bg-yellow-50 text-yellow-800 border border-yellow-300 px-2 py-1 font-bold inline-block"
                            >
                                H-{{ item.days_left }} Hari Lagi
                            </span>
                        </td>
                        <td class="p-3 text-right">
                            <span
                                v-if="item.is_requested"
                                class="text-[11px] font-mono font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-1 inline-block"
                            >
                                &#10003; MENUNGGU JADWAL LnD
                            </span>
                            <button
                                v-else
                                type="button"
                                @click="submitSingleRenewal(item)"
                                class="bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-bold px-3.5 py-1.5 text-xs"
                            >
                                Ajukan Pembaruan
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!filteredList.length">
                        <td colspan="7" class="p-8 text-center text-slate-400">
                            Tidak ada sertifikat pekerja yang mendekati masa kadaluarsa (H-60).
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

