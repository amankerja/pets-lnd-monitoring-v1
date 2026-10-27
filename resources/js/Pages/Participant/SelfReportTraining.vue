<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import ParticipantLayout from '@/Layouts/ParticipantLayout.vue';

defineProps({
    submissions: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    course_name: '',
    organizer: '',
    certificate_number: '',
    issued_at: '',
    expires_at: '',
    certificate_file: null,
});

const handleFileChange = (e) => {
    form.certificate_file = e.target.files[0] || null;
};

const submitSelfReport = () => {
    form.post('/participant/self-reports', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset();
        },
    });
};
</script>

<template>
    <Head title="Lapor Sertifikat Mandiri (Self-Report)" />

    <ParticipantLayout title="Self-Report Sertifikat Pelatihan">
        <!-- 1. Form Input Pelatihan Mandiri + Upload Sertifikat (PDF/JPG) -->
        <div class="bg-white border border-slate-200 rounded-md p-4 mb-4">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-900 mb-1">
                Lapor Pelatihan &amp; Upload Sertifikat Mandiri
            </h2>
            <p class="text-[11px] text-slate-500 mb-3">
                Unggah sertifikat pelatihan eksternal/mandiri Anda (PDF/JPG). Setelah diverifikasi Tim LnD, sertifikat akan masuk ke portofolio resmi Anda.
            </p>

            <form @submit.prevent="submitSelfReport" class="space-y-3 text-xs">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Nama Pelatihan / Sertifikasi</label>
                    <input
                        v-model="form.course_name"
                        type="text"
                        required
                        placeholder="Contoh: Sertifikasi Ahli K3 Umum BNSP"
                        class="w-full rounded border-slate-300 text-xs py-2 px-3"
                    />
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Lembaga Penyelenggara / Penerbit</label>
                    <input
                        v-model="form.organizer"
                        type="text"
                        required
                        placeholder="Contoh: Kemnaker RI / BNSP"
                        class="w-full rounded border-slate-300 text-xs py-2 px-3"
                    />
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Nomor Sertifikat</label>
                    <input
                        v-model="form.certificate_number"
                        type="text"
                        required
                        placeholder="Contoh: SER-BNSP-2026-9912"
                        class="w-full rounded border-slate-300 text-xs py-2 px-3 font-mono uppercase"
                    />
                </div>

                <div class="grid grid-cols-2 gap-2.5">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Tanggal Terbit</label>
                        <input
                            v-model="form.issued_at"
                            type="date"
                            required
                            class="w-full rounded border-slate-300 text-xs py-2 px-2.5"
                        />
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Masa Berlaku s/d</label>
                        <input
                            v-model="form.expires_at"
                            type="date"
                            class="w-full rounded border-slate-300 text-xs py-2 px-2.5"
                        />
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Upload File Sertifikat (PDF / JPG / PNG, Maks 5MB)</label>
                    <input
                        type="file"
                        accept=".pdf,.jpg,.jpeg,.png"
                        @change="handleFileChange"
                        class="w-full text-xs text-slate-600 file:mr-2.5 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-xs file:font-bold file:bg-blue-600 file:text-white"
                    />
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full py-2.5 px-4 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs sm:text-sm font-bold disabled:opacity-50 shadow-sm"
                >
                    {{ form.processing ? 'Mengunggah Sertifikat...' : 'Kirim untuk Verifikasi Tim LnD' }}
                </button>
            </form>
        </div>

        <!-- 2. Riwayat Status Verifikasi Self-Report -->
        <div class="bg-white border border-slate-200 rounded-md p-4">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-900 mb-2.5">
                Status Pengajuan Sertifikat Mandiri ({{ submissions.length }})
            </h3>

            <div v-if="submissions.length" class="space-y-2.5">
                <div
                    v-for="item in submissions"
                    :key="item.id"
                    class="p-3 rounded border border-slate-200 bg-slate-50 text-xs"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="font-bold text-slate-900">{{ item.course_name }}</div>
                            <div class="text-[11px] text-slate-500">{{ item.organizer }} &bull; {{ item.certificate_number }}</div>
                            <div class="text-[10px] text-slate-400 mt-0.5">
                                Terbit: {{ item.issued_at }} &bull; Berlaku s/d: {{ item.expires_at }}
                            </div>
                        </div>
                        <span
                            :class="[
                                'px-2 py-0.5 rounded text-[10px] font-bold uppercase shrink-0',
                                item.status === 'approved'
                                    ? 'bg-emerald-100 text-emerald-800'
                                    : item.status === 'rejected'
                                      ? 'bg-rose-100 text-rose-800'
                                      : 'bg-amber-100 text-amber-800',
                            ]"
                        >
                            {{ item.status }}
                        </span>
                    </div>

                    <div v-if="item.review_notes" class="mt-2 pt-2 border-t border-slate-200 text-[11px] text-slate-600">
                        <strong>Catatan Reviewer ({{ item.reviewer_name || 'Tim LnD' }}):</strong> {{ item.review_notes }}
                    </div>
                </div>
            </div>
            <p v-else class="text-xs text-slate-400 text-center py-4">
                Belum ada sertifikat mandiri yang dilaporkan.
            </p>
        </div>
    </ParticipantLayout>
</template>

