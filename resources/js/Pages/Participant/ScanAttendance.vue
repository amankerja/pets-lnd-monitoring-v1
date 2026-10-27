<script setup>
import { computed, onUnmounted, ref, watch } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import ParticipantLayout from '@/Layouts/ParticipantLayout.vue';
import ExternalTrainingDocumentUpload from '@/Components/ExternalTrainingDocumentUpload.vue';

const props = defineProps({
    activeSessions: {
        type: Array,
        default: () => [],
    },
    externalDocumentCourses: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const isCameraActive = ref(false);
const cameraError = ref('');
const manualPinInputRef = ref(null);
let html5QrCodeInstance = null;

const focusManualInput = () => {
    stopCameraScanner();
    if (manualPinInputRef.value) {
        manualPinInputRef.value.focus();
    }
};

const scanForm = useForm({
    payload: '',
    qr_token: '',
    device_info: typeof navigator !== 'undefined' ? navigator.userAgent : 'Mobile Browser',
});

// Modal / Card Form Penilaian Trainer Sesi Ini (TUGAS 3 - Poin 2)
const showEvaluationModal = ref(false);
const selectedSessionForEvaluation = ref(null);

const evalForm = useForm({
    trainer_id: null,
    attendance_session_id: null,
    course_id: null,
    rating: 5,
    material_mastery_rating: 5,
    delivery_method_rating: 5,
    punctuality_rating: 5,
    comment: '',
});

const openEvaluation = (sessionData) => {
    selectedSessionForEvaluation.value = sessionData;
    evalForm.trainer_id = sessionData.trainer_id;
    evalForm.attendance_session_id = sessionData.id;
    evalForm.course_id = sessionData.course_id;
    evalForm.rating = 5;
    evalForm.material_mastery_rating = 5;
    evalForm.delivery_method_rating = 5;
    evalForm.punctuality_rating = 5;
    evalForm.comment = '';
    showEvaluationModal.value = true;
};

// Pantau flash scanned_session dari AttendanceController::scan
watch(
    () => page.props.flash?.scanned_session,
    (newScanned) => {
        if (newScanned && newScanned.trainer_id) {
            openEvaluation(newScanned);
        }
    },
    { immediate: true }
);

const submitAttendance = () => {
    const cleanVal = (scanForm.payload || scanForm.qr_token).trim();
    if (!cleanVal || scanForm.processing) return;

    scanForm.payload = cleanVal;
    scanForm.qr_token = cleanVal;

    scanForm.post('/participant/attendance/scan', {
        preserveScroll: true,
        onSuccess: () => {
            scanForm.reset('payload', 'qr_token');
            stopCameraScanner();
        },
    });
};

const submitEvaluation = () => {
    if (evalForm.processing || !evalForm.trainer_id) return;

    evalForm.post('/participant/trainer-evaluations', {
        preserveScroll: true,
        onSuccess: () => {
            showEvaluationModal.value = false;
            evalForm.reset();
        },
    });
};

const startCameraScanner = async () => {
    cameraError.value = '';

    try {
        const { Html5Qrcode } = await import('html5-qrcode');
        isCameraActive.value = true;

        setTimeout(async () => {
            try {
                html5QrCodeInstance = new Html5Qrcode('qr-reader-viewport');
                await html5QrCodeInstance.start(
                    { facingMode: 'environment' },
                    {
                        fps: 10,
                        qrbox: { width: 220, height: 220 },
                    },
                    (decodedText) => {
                        scanForm.payload = decodedText.trim();
                        scanForm.qr_token = decodedText.trim();
                        stopCameraScanner();
                        submitAttendance();
                    },
                    () => {}
                );
            } catch (err) {
                isCameraActive.value = false;
                cameraError.value =
                    'Kamera tidak dapat diakses (pastikan izin kamera diberikan atau gunakan kode PIN 6-digit manual di bawah).';
            }
        }, 100);
    } catch (e) {
        cameraError.value = 'Modul kamera belum tersedia. Silakan ketik kode PIN 6-digit di bawah.';
    }
};

const stopCameraScanner = async () => {
    if (html5QrCodeInstance) {
        try {
            await html5QrCodeInstance.stop();
            await html5QrCodeInstance.clear();
        } catch (_) {}
        html5QrCodeInstance = null;
    }
    isCameraActive.value = false;
};

onUnmounted(() => {
    stopCameraScanner();
});
</script>

<template>
    <Head title="Scan Rotating QR Presensi" />

    <ParticipantLayout title="Presensi Kehadiran Kelas">
        <!-- 1. Area Kamera Scanner HP -->
        <div class="bg-white border border-slate-200 rounded-md p-4 mb-4">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-900">Scanner Rotating QR</h2>
                    <p class="text-[11px] text-slate-500">Arahkan kamera HP ke QR proyektor (toleransi lag 15-30 detik)</p>
                </div>
                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200">
                    HMAC-SHA256
                </span>
            </div>

            <div
                v-if="isCameraActive"
                class="relative rounded overflow-hidden bg-slate-950 border border-slate-800 mb-3"
            >
                <div id="qr-reader-viewport" class="w-full min-h-[260px]"></div>
                <button
                    type="button"
                    @click="stopCameraScanner"
                    class="w-full py-2.5 bg-rose-600 text-white text-xs font-bold hover:bg-rose-700"
                >
                    Matikan Kamera
                </button>
            </div>

            <div
                v-else
                class="py-8 px-4 rounded border border-dashed border-slate-300 bg-slate-50 flex flex-col items-center text-center"
            >
                <svg class="w-10 h-10 text-blue-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.8"
                        d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"
                    />
                </svg>
                <p class="text-xs text-slate-600 font-medium mb-3">
                    Klik tombol di bawah untuk memindai QR Code dinamis di layar proyektor.
                </p>
                <button
                    type="button"
                    @click="startCameraScanner"
                    class="w-full py-2.5 px-4 rounded bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold transition-colors"
                >
                    Buka Kamera &amp; Scan QR
                </button>
                <button
                    type="button"
                    @click="focusManualInput"
                    class="mt-2 w-full py-2 px-4 rounded bg-slate-200 hover:bg-slate-300 text-slate-800 text-xs font-bold transition-colors"
                >
                    Ketik Kode Manual 6-Digit
                </button>
            </div>

            <div v-if="cameraError" class="mt-2.5 p-2.5 rounded bg-amber-50 border border-amber-200 text-amber-800 text-[11px]">
                {{ cameraError }}
            </div>
        </div>

        <ExternalTrainingDocumentUpload :courses="externalDocumentCourses" />

        <!-- 2. Input Kode PIN 6-Digit Manual / Payload (Fallback) -->
        <div class="bg-white border border-slate-200 rounded-md p-4 mb-4">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-900 mb-1">
                Input Kode PIN 6-Digit / Payload Manual
            </h3>
            <p class="text-[11px] text-slate-500 mb-3">
                Jika kamera HP bermasalah, ketik 6 digit angka PIN yang tampil di layar proyektor saat ini.
            </p>

            <form @submit.prevent="submitAttendance" class="space-y-3">
                <div>
                    <input
                        ref="manualPinInputRef"
                        v-model="scanForm.qr_token"
                        type="text"
                        required
                        placeholder="Ketik 6-digit PIN atau Payload QR..."
                        class="w-full rounded border-slate-300 font-mono text-sm tracking-widest uppercase py-2.5 px-3 focus:border-blue-600 focus:ring-blue-600"
                    />
                    <p v-if="scanForm.errors.qr_token" class="mt-1.5 text-xs font-semibold text-rose-600">
                        {{ scanForm.errors.qr_token }}
                    </p>
                </div>

                <button
                    type="submit"
                    :disabled="scanForm.processing || !scanForm.qr_token.trim()"
                    class="w-full py-2.5 px-4 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs sm:text-sm font-bold disabled:opacity-50 shadow-sm"
                >
                    {{ scanForm.processing ? 'Memverifikasi Signature HMAC...' : 'Verifikasi & Catat Kehadiran' }}
                </button>
            </form>
        </div>

        <!-- 3. Sesi Presensi Aktif Hari Ini & Tombol Nilai Trainer -->
        <div class="bg-white border border-slate-200 rounded-md p-4">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-900 mb-2.5">
                Sesi Kelas Anda yang Sedang Dibuka
            </h3>

            <div v-if="activeSessions.length" class="space-y-2.5">
                <div
                    v-for="s in activeSessions"
                    :key="s.id"
                    class="p-3 rounded border border-slate-200 bg-slate-50 flex flex-col gap-2"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <span class="text-[10px] font-bold text-blue-700 bg-blue-50 px-1.5 py-0.5 rounded border border-blue-200">
                                {{ s.course_code }}
                            </span>
                            <div class="text-xs font-bold text-slate-900 mt-1">{{ s.title }}</div>
                            <div class="text-[11px] text-slate-600 font-medium">Trainer: {{ s.trainer_name }}</div>
                            <div class="text-[11px] text-slate-500">Batas Waktu: {{ s.expires_at }} WIB</div>
                        </div>
                        <div>
                            <span
                                v-if="s.already_scanned"
                                class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 text-[10px] font-bold uppercase inline-block"
                            >
                                Sudah Hadir
                            </span>
                            <span
                                v-else
                                class="px-2 py-0.5 rounded bg-amber-100 text-amber-800 text-[10px] font-bold uppercase inline-block"
                            >
                                Belum Scan
                            </span>
                        </div>
                    </div>

                    <!-- Tombol Nilai Trainer jika sudah scan dan belum evaluasi -->
                    <div v-if="s.already_scanned" class="pt-2 border-t border-slate-200 flex items-center justify-between">
                        <span v-if="s.already_evaluated" class="text-[11px] text-emerald-600 font-bold flex items-center gap-1">
                            ✓ Anda telah menilai Trainer sesi ini
                        </span>
                        <button
                            v-else
                            type="button"
                            @click="openEvaluation(s)"
                            class="w-full py-1.5 px-3 rounded bg-amber-500 hover:bg-amber-600 text-white text-[11px] font-bold flex items-center justify-center gap-1.5 shadow-sm transition-colors"
                        >
                            <span>⭐</span> Nilai Trainer Sesi Ini (Rating 1-5 & Feedback)
                        </button>
                    </div>
                </div>
            </div>
            <p v-else class="text-xs text-slate-400">
                Tidak ada sesi presensi aktif untuk kelas Anda saat ini.
            </p>
        </div>

        <!-- MODAL FORM: NILAI TRAINER SESI INI (TUGAS 3 - Poin 2) -->
        <div
            v-if="showEvaluationModal"
            class="fixed inset-0 z-50 bg-slate-900/70 backdrop-blur-sm flex items-end sm:items-center justify-center p-3 sm:p-4"
        >
            <div class="bg-white rounded-t-xl sm:rounded-xl shadow-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto p-5">
                <div class="flex items-center justify-between pb-3 border-b border-slate-200 mb-4">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">⭐</span>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">Nilai Trainer Sesi Ini</h3>
                            <p class="text-[11px] text-slate-500">Evaluasi performa Trainer Anda untuk peningkatan mutu LnD</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="showEvaluationModal = false"
                        class="text-slate-400 hover:text-slate-600 p-1 rounded text-lg leading-none"
                    >
                        ✕
                    </button>
                </div>

                <div v-if="selectedSessionForEvaluation" class="p-3 bg-blue-50 border border-blue-200 rounded mb-4">
                    <div class="text-[10px] font-bold text-blue-700 uppercase tracking-wider">Instruktur / Trainer</div>
                    <div class="text-xs font-bold text-slate-900">{{ selectedSessionForEvaluation.trainer_name }}</div>
                    <div class="text-[11px] text-slate-600 mt-0.5">{{ selectedSessionForEvaluation.title }}</div>
                </div>

                <form @submit.prevent="submitEvaluation" class="space-y-4">
                    <!-- Rating 1: Overall Star Rating -->
                    <div>
                        <label class="block text-xs font-bold text-slate-800 mb-1">
                            Kepuasan Keseluruhan (Overall Rating):
                        </label>
                        <div class="flex items-center gap-2">
                            <button
                                v-for="star in 5"
                                :key="'overall-' + star"
                                type="button"
                                @click="evalForm.rating = star"
                                class="text-2xl transition-transform hover:scale-125 focus:outline-none"
                                :class="star <= evalForm.rating ? 'text-amber-400' : 'text-slate-300'"
                            >
                                ★
                            </button>
                            <span class="text-xs font-bold text-slate-700 ml-2">({{ evalForm.rating }}/5)</span>
                        </div>
                    </div>

                    <!-- Rating 2: Penguasaan Materi -->
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="text-xs font-bold text-slate-700">Penguasaan Materi:</label>
                            <span class="text-xs font-bold text-blue-700">{{ evalForm.material_mastery_rating }} / 5</span>
                        </div>
                        <input
                            type="range"
                            min="1"
                            max="5"
                            v-model.number="evalForm.material_mastery_rating"
                            class="w-full accent-blue-600 cursor-pointer"
                        />
                    </div>

                    <!-- Rating 3: Cara Penyampaian -->
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="text-xs font-bold text-slate-700">Cara Penyampaian / Komunikasi:</label>
                            <span class="text-xs font-bold text-blue-700">{{ evalForm.delivery_method_rating }} / 5</span>
                        </div>
                        <input
                            type="range"
                            min="1"
                            max="5"
                            v-model.number="evalForm.delivery_method_rating"
                            class="w-full accent-blue-600 cursor-pointer"
                        />
                    </div>

                    <!-- Rating 4: Ketepatan Waktu -->
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="text-xs font-bold text-slate-700">Ketepatan Waktu (Punctuality):</label>
                            <span class="text-xs font-bold text-blue-700">{{ evalForm.punctuality_rating }} / 5</span>
                        </div>
                        <input
                            type="range"
                            min="1"
                            max="5"
                            v-model.number="evalForm.punctuality_rating"
                            class="w-full accent-blue-600 cursor-pointer"
                        />
                    </div>

                    <!-- Komentar / Saran -->
                    <div>
                        <label class="block text-xs font-bold text-slate-800 mb-1">
                            Komentar &amp; Saran Konstruktif:
                        </label>
                        <textarea
                            v-model="evalForm.comment"
                            rows="3"
                            placeholder="Tuliskan pengalaman Anda mengikuti materi, kejelasan penjelasan Trainer, dll..."
                            class="w-full rounded border-slate-300 text-xs focus:border-blue-600 focus:ring-blue-600 p-2.5"
                        ></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200">
                        <button
                            type="button"
                            @click="showEvaluationModal = false"
                            class="px-3.5 py-2 text-xs font-bold text-slate-600 hover:text-slate-800 rounded bg-slate-100 hover:bg-slate-200"
                        >
                            Nanti Saja
                        </button>
                        <button
                            type="submit"
                            :disabled="evalForm.processing"
                            class="px-4 py-2 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded shadow-sm disabled:opacity-50"
                        >
                            {{ evalForm.processing ? 'Mengirim Penilaian...' : 'Kirim Penilaian' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </ParticipantLayout>
</template>
