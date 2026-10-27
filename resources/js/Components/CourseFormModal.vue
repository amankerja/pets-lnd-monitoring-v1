<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    course: {
        type: Object,
        default: null,
    },
    competencies: {
        type: Array,
        default: () => [],
    },
    trainers: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['close', 'saved']);

const isEditMode = computed(() => Boolean(props.course && props.course.id));

const form = useForm({
    code: props.course?.code || '',
    title: props.course?.title || '',
    description: props.course?.description || '',
    training_category: props.course?.training_category || 'Safety',
    training_source: props.course?.training_source || 'internal',
    is_mandatory: props.course?.is_mandatory ?? true,
    validity_months: props.course?.validity_months ?? 24,
    skill_id: props.course?.skill_id || '',
    granted_skill_level: props.course?.granted_skill_level ?? 3,
    trainer_id: props.course?.trainer_id || '',
    start_date: props.course?.start_date || new Date().toISOString().slice(0, 10),
    end_date: props.course?.end_date || new Date(Date.now() + 14 * 86400000).toISOString().slice(0, 10),
});

const onCodeInput = (e) => {
    form.code = (e.target.value || '').toUpperCase();
};

const submitCourse = () => {
    form.code = (form.code || '').trim().toUpperCase();

    if (isEditMode.value) {
        form.put(`/training/courses/${props.course.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                emit('saved');
                emit('close');
            },
        });
    } else {
        form.post('/training/courses', {
            preserveScroll: true,
            onSuccess: () => {
                emit('saved');
                emit('close');
            },
        });
    }
};
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 bg-slate-900/50 flex items-center justify-center p-4"
        @click.self="emit('close')"
    >
        <div class="bg-white border border-slate-200 rounded-xl w-full max-w-2xl shadow-xl overflow-hidden">
            <!-- Header Modal -->
            <div class="px-5 py-3.5 bg-blue-50 border-b border-blue-100 flex items-center justify-between">
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-wider text-blue-600">
                        {{ isEditMode ? 'Edit Program Training' : 'Buat Program Training Baru' }}
                    </div>
                    <h3 class="text-sm font-bold text-slate-900">
                        {{ isEditMode ? `Perbarui [${form.code}] ${form.title}` : 'Form Kurikulum Pelatihan & Relasi Kompetensi' }}
                    </h3>
                </div>
                <button
                    type="button"
                    @click="emit('close')"
                    class="text-slate-400 hover:text-slate-700 font-bold px-2"
                >
                    &#10005;
                </button>
            </div>

            <!-- Form Isi -->
            <form @submit.prevent="submitCourse" class="p-5 space-y-3.5 max-h-[82vh] overflow-y-auto text-xs">
                <!-- Baris 1: Kode Training (Auto-Uppercase) & Judul Pelatihan -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">
                            Kode Training <span class="text-rose-600">*</span>
                        </label>
                        <input
                            :value="form.code"
                            @input="onCodeInput"
                            type="text"
                            required
                            placeholder="Contoh: CRS-2026-01"
                            class="w-full rounded-lg border-slate-300 font-mono uppercase text-xs py-2 px-3 focus:border-blue-600 focus:ring-blue-600"
                        />
                        <p v-if="form.errors.code" class="text-[11px] text-rose-600 mt-1">
                            {{ form.errors.code }}
                        </p>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block font-bold text-slate-700 mb-1">
                            Judul Program Pelatihan <span class="text-rose-600">*</span>
                        </label>
                        <input
                            v-model="form.title"
                            type="text"
                            required
                            placeholder="Contoh: Sertifikasi Keselamatan Kerja Tambang & HIRA (POP)"
                            class="w-full rounded-lg border-slate-300 text-xs py-2 px-3 focus:border-blue-600 focus:ring-blue-600"
                        />
                        <p v-if="form.errors.title" class="text-[11px] text-rose-600 mt-1">
                            {{ form.errors.title }}
                        </p>
                    </div>
                </div>

                <!-- Baris 2: Hubungkan ke Master Kompetensi (Competency List) & Granted Level -->
                <div class="p-3.5 bg-blue-50/70 border border-blue-200 rounded-xl space-y-3">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-blue-600 inline-block"></span>
                        <span class="font-bold text-blue-900 text-xs uppercase tracking-wider">
                            Alur Relasi Kompetensi (Auto-Update Matrix)
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="sm:col-span-2">
                            <label class="block font-bold text-slate-700 mb-1">
                                Hubungkan ke Master Kompetensi (Competency List) <span class="text-rose-600">*</span>
                            </label>
                            <select
                                v-model="form.skill_id"
                                required
                                class="w-full rounded-lg border-slate-300 text-xs py-2 px-3 focus:border-blue-600 focus:ring-blue-600 font-semibold text-slate-800"
                            >
                                <option value="">-- Pilih Kompetensi Acuan Pusat --</option>
                                <option
                                    v-for="comp in competencies"
                                    :key="comp.id"
                                    :value="comp.id"
                                >
                                    [{{ comp.code }}] {{ comp.name }} ({{ comp.competency_type || comp.category }})
                                </option>
                            </select>
                            <p class="text-[10px] text-slate-500 mt-1">
                                Peserta yang lulus training ini otomatis mendapat update level pada kompetensi di atas.
                            </p>
                            <p v-if="form.errors.skill_id" class="text-[11px] text-rose-600 mt-0.5">
                                {{ form.errors.skill_id }}
                            </p>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1">
                                Level yang Dianugerahkan <span class="text-rose-600">*</span>
                            </label>
                            <select
                                v-model="form.granted_skill_level"
                                required
                                class="w-full rounded-lg border-slate-300 text-xs py-2 px-3 focus:border-blue-600 focus:ring-blue-600 font-bold text-blue-700"
                            >
                                <option :value="1">Level 1 (Novice)</option>
                                <option :value="2">Level 2 (Basic)</option>
                                <option :value="3">Level 3 (Competent Standard)</option>
                                <option :value="4">Level 4 (Advanced)</option>
                                <option :value="5">Level 5 (Master / Specialist)</option>
                            </select>
                            <p class="text-[10px] text-slate-500 mt-1">
                                Nilai akhir di Matrix: <code>max(current, granted)</code>
                            </p>
                            <p v-if="form.errors.granted_skill_level" class="text-[11px] text-rose-600 mt-0.5">
                                {{ form.errors.granted_skill_level }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Baris 3: Klasifikasi Training (Kategori, Sifat, Sumber, & Masa Berlaku) -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Bidang / Kategori</label>
                        <select
                            v-model="form.training_category"
                            class="w-full rounded-lg border-slate-300 text-xs py-2 px-2.5"
                        >
                            <option value="Safety">Safety</option>
                            <option value="Operation">Operation</option>
                            <option value="Plant">Plant</option>
                            <option value="Pengawas">Pengawas</option>
                            <option value="Engineering">Engineering</option>
                            <option value="Leadership">Leadership</option>
                            <option value="General">General</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Penyelenggara</label>
                        <select
                            v-model="form.training_source"
                            class="w-full rounded-lg border-slate-300 text-xs py-2 px-2.5 font-medium"
                        >
                            <option value="internal">Internal (PBJ)</option>
                            <option value="external">External (Lembaga Luar)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Sifat Pelatihan</label>
                        <select
                            v-model="form.is_mandatory"
                            class="w-full rounded-lg border-slate-300 text-xs py-2 px-2.5 font-medium"
                        >
                            <option :value="true">Mandatory (Wajib)</option>
                            <option :value="false">Non-Mandatory (Pilihan)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Masa Berlaku (Bulan)</label>
                        <select
                            v-model="form.validity_months"
                            class="w-full rounded-lg border-slate-300 text-xs py-2 px-2.5 font-medium"
                        >
                            <option :value="12">12 Bulan (1 Tahun)</option>
                            <option :value="24">24 Bulan (2 Tahun)</option>
                            <option :value="36">36 Bulan (3 Tahun)</option>
                            <option :value="null">Permanent (Selamanya)</option>
                        </select>
                    </div>
                </div>

                <!-- Baris 4: Tanggal Pelaksanaan & Trainer -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Tanggal Mulai</label>
                        <input
                            v-model="form.start_date"
                            type="date"
                            required
                            class="w-full rounded-lg border-slate-300 text-xs py-2 px-3"
                        />
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Tanggal Selesai</label>
                        <input
                            v-model="form.end_date"
                            type="date"
                            required
                            class="w-full rounded-lg border-slate-300 text-xs py-2 px-3"
                        />
                    </div>
                </div>

                <!-- Baris 5: Deskripsi Training -->
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Deskripsi / Silabus Pelatihan</label>
                    <textarea
                        v-model="form.description"
                        rows="2"
                        placeholder="Rincian tujuan pelatihan, materi pokok, dan standar kelulusan..."
                        class="w-full rounded-lg border-slate-300 text-xs py-2 px-3"
                    ></textarea>
                </div>

                <!-- Footer Tombol Aksi -->
                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200">
                    <button
                        type="button"
                        @click="emit('close')"
                        class="px-3.5 py-2 rounded-lg border border-slate-300 font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-bold shadow-sm disabled:opacity-50"
                    >
                        {{ form.processing ? 'Menyimpan...' : isEditMode ? 'Simpan Perubahan' : 'Buat Program Training' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

