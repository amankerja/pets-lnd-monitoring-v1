<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    canManage: {
        type: Boolean,
        default: false,
    },
    course: {
        type: Object,
        default: null,
    },
});

const defaultAccommodationChecklist = () => [
    { item: 'Pesawat', status: 'pending', notes: '' },
    { item: 'Hotel', status: 'pending', notes: '' },
    { item: 'Uang Saku', status: 'pending', notes: '' },
    { item: 'Travel', status: 'pending', notes: '' },
];

const form = useForm({
    external_type: 'inhouse',
    vendor_name: '',
    pic_training: '',
    training_mode: 'offline',
    training_room: '',
    training_duration_days: 1,
    training_cost: 0,
    required_documents_schema: [],
    accommodation_checklist: defaultAccommodationChecklist(),
});
const newDocumentName = ref('');
const newAccommodationItem = ref('');
const pendingAccommodationItem = ref('');
const accommodationError = ref('');
const isExternalCourse = computed(() => props.course?.training_source === 'external');
const logistics = computed(() => props.course?.external_training_logistic);
const formErrorMessages = computed(() => Object.values(form.errors));

watch(
    () => props.course?.id,
    () => {
        const saved = logistics.value;
        form.external_type = saved?.external_type || 'inhouse';
        form.vendor_name = saved?.vendor_name || '';
        form.pic_training = saved?.pic_training || '';
        form.training_mode = saved?.training_mode || 'offline';
        form.training_room = saved?.training_room || '';
        form.training_duration_days = saved?.training_duration_days || 1;
        form.training_cost = saved?.training_cost || 0;
        form.required_documents_schema = saved?.required_documents_schema || [];
        form.accommodation_checklist = saved?.accommodation_checklist?.length
            ? saved.accommodation_checklist
            : defaultAccommodationChecklist();
    },
    { immediate: true }
);

const addDocumentRequirement = () => {
    const name = newDocumentName.value.trim();
    if (
        !name
        || form.required_documents_schema.length >= 50
        || form.required_documents_schema.some((document) => document.name.toLowerCase() === name.toLowerCase())
    ) {
        return;
    }

    form.required_documents_schema.push({ name, mandatory: true });
    newDocumentName.value = '';
};

const removeDocumentRequirement = (index) => {
    form.required_documents_schema.splice(index, 1);
};

const addAccommodationItem = () => {
    const item = newAccommodationItem.value.trim();
    if (
        !item
        || form.accommodation_checklist.length >= 20
        || form.accommodation_checklist.some((entry) => entry.item.toLowerCase() === item.toLowerCase())
    ) {
        return;
    }

    form.accommodation_checklist.push({ item, status: 'pending', notes: '' });
    newAccommodationItem.value = '';
};

const removeAccommodationItem = (index) => {
    form.accommodation_checklist.splice(index, 1);
};

const canQuickUpdateAccommodation = (accommodation) =>
    logistics.value?.external_type === 'dinas_luar'
    && form.external_type === 'dinas_luar'
    && logistics.value.accommodation_checklist?.some((entry) => entry.item === accommodation.item);

const saveLogistics = () => {
    if (!props.course?.id || form.processing) return;

    form.put(`/admin/training/courses/${props.course.id}/logistics`, {
        preserveScroll: true,
    });
};

const toggleAccommodationStatus = (accommodation) => {
    if (!props.course?.id || pendingAccommodationItem.value) return;

    const nextStatus = accommodation.status === 'done' ? 'pending' : 'done';
    pendingAccommodationItem.value = accommodation.item;
    accommodationError.value = '';
    router.patch(
        `/admin/training/courses/${props.course.id}/accommodation-status`,
        { item: accommodation.item, status: nextStatus, notes: accommodation.notes || '' },
        {
            preserveScroll: true,
            onSuccess: () => {
                accommodation.status = nextStatus;
                accommodationError.value = '';
            },
            onError: (errors) => {
                accommodationError.value = Object.values(errors)[0] || 'Status akomodasi gagal diperbarui.';
            },
            onFinish: () => {
                pendingAccommodationItem.value = '';
            },
        }
    );
};
</script>

<template>
    <section
        v-if="canManage && isExternalCourse"
        class="mb-5 overflow-hidden rounded-xl border border-indigo-200 bg-white shadow-sm"
        aria-labelledby="external-logistics-heading"
    >
        <div class="flex flex-col gap-1 border-b border-indigo-100 bg-indigo-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 id="external-logistics-heading" class="text-sm font-bold text-indigo-950">
                    Logistik Training Eksternal
                </h2>
                <p class="text-xs text-indigo-800">
                    {{ course.code }} · {{ course.title }}
                </p>
            </div>
            <span class="w-fit rounded-full border border-indigo-200 bg-white px-2.5 py-1 text-xs font-semibold text-indigo-800">
                {{ logistics ? 'Logistik tersimpan' : 'Belum dikonfigurasi' }}
            </span>
        </div>

        <form class="space-y-5 p-4" @submit.prevent="saveLogistics">
            <div v-if="formErrorMessages.length" role="alert" class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800">
                <p class="font-semibold">Periksa kembali data logistik:</p>
                <ul class="mt-1 list-inside list-disc">
                    <li v-for="(message, index) in formErrorMessages" :key="index">{{ message }}</li>
                </ul>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label for="external-type" class="mb-1 block text-sm font-semibold text-slate-700">Tipe eksternal</label>
                    <select id="external-type" v-model="form.external_type" class="w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="inhouse">In-house</option>
                        <option value="dinas_luar">Dinas luar</option>
                    </select>
                    <p v-if="form.errors.external_type" class="mt-1 text-xs text-rose-700">{{ form.errors.external_type }}</p>
                </div>

                <div>
                    <label for="vendor-name" class="mb-1 block text-sm font-semibold text-slate-700">Vendor training</label>
                    <input id="vendor-name" v-model="form.vendor_name" maxlength="150" required class="w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    <p v-if="form.errors.vendor_name" class="mt-1 text-xs text-rose-700">{{ form.errors.vendor_name }}</p>
                </div>

                <div>
                    <label for="pic-training" class="mb-1 block text-sm font-semibold text-slate-700">PIC training</label>
                    <input id="pic-training" v-model="form.pic_training" maxlength="100" required class="w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    <p v-if="form.errors.pic_training" class="mt-1 text-xs text-rose-700">{{ form.errors.pic_training }}</p>
                </div>

                <div>
                    <label for="training-cost" class="mb-1 block text-sm font-semibold text-slate-700">Biaya training (Rp)</label>
                    <input id="training-cost" v-model="form.training_cost" type="number" min="0" step="0.01" required class="w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    <p v-if="form.errors.training_cost" class="mt-1 text-xs text-rose-700">{{ form.errors.training_cost }}</p>
                </div>

                <div>
                    <label for="training-duration" class="mb-1 block text-sm font-semibold text-slate-700">Durasi (hari)</label>
                    <input id="training-duration" v-model="form.training_duration_days" type="number" min="1" max="365" required class="w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    <p v-if="form.errors.training_duration_days" class="mt-1 text-xs text-rose-700">{{ form.errors.training_duration_days }}</p>
                </div>

                <div>
                    <label for="training-mode" class="mb-1 block text-sm font-semibold text-slate-700">Mode training</label>
                    <select id="training-mode" v-model="form.training_mode" class="w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="offline">Offline</option>
                        <option value="online">Online</option>
                    </select>
                </div>

                <div v-if="form.external_type === 'inhouse' && form.training_mode === 'offline'" class="sm:col-span-2 lg:col-span-3">
                    <label for="training-room" class="mb-1 block text-sm font-semibold text-slate-700">Lokasi / ruang training</label>
                    <input id="training-room" v-model="form.training_room" maxlength="100" class="w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    <p v-if="form.errors.training_room" class="mt-1 text-xs text-rose-700">{{ form.errors.training_room }}</p>
                </div>
            </div>

            <p v-if="form.errors.accommodation_checklist" class="text-sm text-rose-700">
                {{ form.errors.accommodation_checklist }}
            </p>

            <fieldset class="rounded-lg border border-slate-200 p-4">
                <legend class="px-1 text-sm font-bold text-slate-800">Berkas persyaratan peserta</legend>
                <p class="mb-3 text-xs text-slate-500">Tentukan dokumen yang harus diunggah peserta; unggahan disimpan secara privat.</p>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <label for="new-document-name" class="sr-only">Nama dokumen baru</label>
                    <input
                        id="new-document-name"
                        v-model="newDocumentName"
                        type="text"
                        maxlength="100"
                        placeholder="Contoh: KTP atau Sertifikat POP"
                        class="min-h-11 flex-1 rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                        @keydown.enter.prevent="addDocumentRequirement"
                    />
                    <button type="button" :disabled="form.required_documents_schema.length >= 50" class="min-h-11 rounded-lg border border-indigo-300 px-4 text-sm font-semibold text-indigo-800 hover:bg-indigo-50 disabled:cursor-not-allowed disabled:opacity-50 focus:outline-none focus:ring-2 focus:ring-indigo-500" @click="addDocumentRequirement">
                        Tambah berkas
                    </button>
                </div>
                <p v-if="form.required_documents_schema.length >= 50" class="mt-2 text-xs text-amber-800">
                    Maksimal 50 persyaratan berkas per program.
                </p>
                <ul v-if="form.required_documents_schema.length" class="mt-3 divide-y divide-slate-100">
                    <li v-for="(document, index) in form.required_documents_schema" :key="`${document.name}-${index}`" class="flex flex-col gap-2 py-2 sm:flex-row sm:items-center">
                        <span class="flex-1 text-sm font-medium text-slate-800">{{ document.name }}</span>
                        <label class="inline-flex min-h-11 items-center gap-2 text-sm text-slate-700">
                            <input v-model="document.mandatory" type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                            Wajib diunggah
                        </label>
                        <button type="button" class="min-h-11 rounded px-3 text-sm font-semibold text-rose-700 hover:bg-rose-50 focus:outline-none focus:ring-2 focus:ring-rose-500" :aria-label="`Hapus persyaratan ${document.name}`" @click="removeDocumentRequirement(index)">
                            Hapus
                        </button>
                    </li>
                </ul>
                <p v-else class="mt-3 text-sm text-slate-500">Belum ada dokumen yang diminta.</p>
                <p v-if="form.errors.required_documents_schema" class="mt-2 text-xs text-rose-700">{{ form.errors.required_documents_schema }}</p>
            </fieldset>

            <fieldset v-if="form.external_type === 'dinas_luar'" class="rounded-lg border border-slate-200 p-4">
                <legend class="px-1 text-sm font-bold text-slate-800">Kesiapan akomodasi dinas luar</legend>
                <p class="mb-3 text-xs text-slate-500">Ubah status secara cepat atau catat nomor pemesanan dan informasi perjalanan.</p>
                <p v-if="accommodationError" class="mb-3 text-sm text-rose-700" role="alert">
                    {{ accommodationError }}
                </p>
                <div class="mb-3 flex flex-col gap-2 sm:flex-row">
                    <label for="new-accommodation-item" class="sr-only">Nama akomodasi tambahan</label>
                    <input
                        id="new-accommodation-item"
                        v-model="newAccommodationItem"
                        maxlength="100"
                        placeholder="Tambah item, misalnya transport lokal"
                        class="min-h-11 flex-1 rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                        @keydown.enter.prevent="addAccommodationItem"
                    />
                    <button
                        type="button"
                        :disabled="form.accommodation_checklist.length >= 20"
                        class="min-h-11 rounded-lg border border-indigo-300 px-4 text-sm font-semibold text-indigo-800 hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        @click="addAccommodationItem"
                    >
                        Tambah akomodasi
                    </button>
                </div>
                <p v-if="form.accommodation_checklist.length >= 20" class="mb-3 text-xs text-amber-800">
                    Maksimal 20 item akomodasi per program.
                </p>
                <div class="space-y-3">
                    <div v-for="(accommodation, index) in form.accommodation_checklist" :key="accommodation.item" class="grid grid-cols-1 items-end gap-2 rounded-lg bg-slate-50 p-3 sm:grid-cols-[minmax(110px,0.7fr)_minmax(180px,1.5fr)_auto_auto]">
                        <div>
                            <span class="block text-sm font-semibold text-slate-800">{{ accommodation.item }}</span>
                            <span :class="accommodation.status === 'done' ? 'text-emerald-700' : 'text-amber-700'" class="text-xs font-semibold">
                                {{ accommodation.status === 'done' ? 'Selesai' : 'Belum' }}
                            </span>
                        </div>
                        <div>
                            <label :for="`accommodation-notes-${index}`" class="mb-1 block text-xs font-semibold text-slate-600">Catatan / kode booking</label>
                            <input :id="`accommodation-notes-${index}`" v-model="accommodation.notes" maxlength="500" class="min-h-11 w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                        <button
                            type="button"
                            :disabled="Boolean(pendingAccommodationItem) || !canQuickUpdateAccommodation(accommodation)"
                            :class="accommodation.status === 'done' ? 'border-amber-300 text-amber-800 hover:bg-amber-50' : 'border-emerald-300 text-emerald-800 hover:bg-emerald-50'"
                            class="min-h-11 rounded-lg border px-4 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-50 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            @click="toggleAccommodationStatus(accommodation)"
                        >
                            {{ pendingAccommodationItem === accommodation.item ? 'Menyimpan...' : !canQuickUpdateAccommodation(accommodation) ? 'Simpan logistik dulu' : accommodation.status === 'done' ? 'Belum' : 'Done' }}
                        </button>
                        <button
                            type="button"
                            class="min-h-11 rounded-lg border border-rose-200 px-3 text-sm font-semibold text-rose-700 hover:bg-rose-50 focus:outline-none focus:ring-2 focus:ring-rose-500"
                            :aria-label="`Hapus item ${accommodation.item}`"
                            @click="removeAccommodationItem(index)"
                        >
                            Hapus
                        </button>
                    </div>
                </div>
            </fieldset>

            <div class="flex flex-col-reverse gap-2 border-t border-slate-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                <p v-if="form.errors.accommodation_checklist" class="text-xs text-rose-700">{{ form.errors.accommodation_checklist }}</p>
                <button type="submit" :disabled="form.processing" class="min-h-11 rounded-lg bg-indigo-700 px-5 text-sm font-bold text-white hover:bg-indigo-800 disabled:opacity-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    {{ form.processing ? 'Menyimpan...' : 'Simpan logistik eksternal' }}
                </button>
            </div>
        </form>
    </section>
</template>
