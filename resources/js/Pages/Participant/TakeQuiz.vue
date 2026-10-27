<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import ParticipantLayout from '@/Layouts/ParticipantLayout.vue';

const props = defineProps({
    quiz: {
        type: Object,
        required: true,
    },
    attempt: {
        type: Object,
        required: true,
    },
    questions: {
        type: Array,
        default: () => [],
    },
    initialAnswers: {
        type: Object,
        default: () => ({}),
    },
    result: {
        type: Object,
        default: null,
    },
});

// State Pengerjaan Soal
const currentQuestionIndex = ref(0);
const answers = ref({ ...props.initialAnswers });
const isConfirmModalOpen = ref(false);
const isSubmitting = ref(false);

// State Timer Countdown
const remainingSeconds = ref(props.attempt?.remaining_seconds ?? 0);
let timerInterval = null;

const currentQuestion = computed(() => {
    return props.questions[currentQuestionIndex.value] || null;
});

const totalQuestions = computed(() => props.questions.length);

const answeredCount = computed(() => {
    return Object.keys(answers.value).filter(
        (qId) => answers.value[qId] !== null && answers.value[qId] !== undefined && answers.value[qId] !== ''
    ).length;
});

const unansweredCount = computed(() => {
    return Math.max(0, totalQuestions.value - answeredCount.value);
});

const formattedTime = computed(() => {
    const mins = Math.floor(remainingSeconds.value / 60);
    const secs = remainingSeconds.value % 60;
    return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
});

const timerColorClass = computed(() => {
    if (remainingSeconds.value <= 60) return 'text-rose-600 bg-rose-50 border-rose-300 animate-pulse';
    if (remainingSeconds.value <= 300) return 'text-amber-600 bg-amber-50 border-amber-300';
    return 'text-blue-700 bg-blue-50 border-blue-200';
});

const selectOption = (questionId, optionKey) => {
    if (props.result) return;
    answers.value[questionId] = optionKey;
};

const goToQuestion = (index) => {
    if (index >= 0 && index < totalQuestions.value) {
        currentQuestionIndex.value = index;
    }
};

const nextQuestion = () => {
    if (currentQuestionIndex.value < totalQuestions.value - 1) {
        currentQuestionIndex.value++;
    }
};

const prevQuestion = () => {
    if (currentQuestionIndex.value > 0) {
        currentQuestionIndex.value--;
    }
};

// Form Inertia untuk Submit Jawaban Ujian
const submitForm = useForm({
    answers: {},
});

const submitExam = (forced = false) => {
    if (isSubmitting.value) return;
    isSubmitting.value = true;
    isConfirmModalOpen.value = false;

    if (timerInterval) {
        clearInterval(timerInterval);
    }

    submitForm.answers = answers.value;
    submitForm.post(`/participant/quiz-attempts/${props.attempt.id}/submit`, {
        preserveScroll: true,
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};

onMounted(() => {
    if (!props.result && remainingSeconds.value > 0) {
        timerInterval = setInterval(() => {
            if (remainingSeconds.value > 0) {
                remainingSeconds.value--;
            } else {
                clearInterval(timerInterval);
                // Waktu Habis -> Auto-Submit Form
                submitExam(true);
            }
        }, 1000);
    }
});

onBeforeUnmount(() => {
    if (timerInterval) clearInterval(timerInterval);
});
</script>

<template>
    <ParticipantLayout :title="quiz.title">
        <Head :title="`${quiz.title} - ${quiz.course.title}`" />

        <!-- =================================================================== -->
        <!-- VIEW A: TAMPILAN HASIL INSTAN SETELAH SUBMIT UJIAN                  -->
        <!-- =================================================================== -->
        <div v-if="result" class="space-y-4">
            <!-- Flash Message Banner -->
            <div
                v-if="result.flash_message"
                class="p-3 rounded bg-blue-50 border border-blue-200 text-blue-900 text-xs font-semibold"
            >
                {{ result.flash_message }}
            </div>

            <!-- Score Summary Card -->
            <div class="bg-white rounded-lg border border-slate-200 shadow-sm p-6 text-center">
                <span
                    :class="[
                        'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider',
                        result.is_passed
                            ? 'bg-emerald-100 text-emerald-800 border border-emerald-300'
                            : 'bg-rose-100 text-rose-800 border border-rose-300',
                    ]"
                >
                    <span v-if="result.is_passed">✓ LULUS (COMPETENT)</span>
                    <span v-else>✕ PERLU REMEDIAL</span>
                </span>

                <div class="mt-4">
                    <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">
                        Skor Akhir Peserta
                    </div>
                    <div
                        :class="result.is_passed ? 'text-emerald-600' : 'text-rose-600'"
                        class="text-6xl font-black tracking-tight mt-1"
                    >
                        {{ result.score }}
                    </div>
                    <div class="text-xs text-slate-500 mt-1">
                        Passing Grade Minimal: <strong class="text-slate-800">{{ result.passing_score }}</strong> / 100
                    </div>
                </div>

                <!-- Detail Statistik Jawaban -->
                <div class="mt-6 grid grid-cols-3 gap-2 border-t border-slate-100 pt-4 text-xs">
                    <div class="p-2 rounded bg-slate-50">
                        <div class="text-[10px] text-slate-500 font-bold uppercase">Total Soal</div>
                        <div class="text-base font-extrabold text-slate-800">{{ result.total_questions }}</div>
                    </div>
                    <div class="p-2 rounded bg-emerald-50 text-emerald-800">
                        <div class="text-[10px] font-bold uppercase">Benar</div>
                        <div class="text-base font-extrabold">{{ result.correct_answers }}</div>
                    </div>
                    <div class="p-2 rounded bg-rose-50 text-rose-800">
                        <div class="text-[10px] font-bold uppercase">Salah</div>
                        <div class="text-base font-extrabold">{{ result.wrong_answers }}</div>
                    </div>
                </div>
            </div>

            <!-- CARD ANALISIS GAIN SCORE (JIKA POST-TEST) -->
            <div
                v-if="result.gain_score"
                class="bg-gradient-to-br from-blue-900 to-indigo-900 text-white rounded-lg p-5 shadow-md"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-[10px] font-extrabold uppercase tracking-wider text-blue-300">
                            Efektivitas Pembelajaran (Learning Gain)
                        </div>
                        <h3 class="text-sm font-bold text-white mt-0.5">
                            Perbandingan Pre-Test vs Post-Test
                        </h3>
                    </div>
                    <span class="px-2 py-0.5 rounded bg-blue-800/80 text-blue-200 text-[10px] font-bold border border-blue-600">
                        Hake's Gain Score
                    </span>
                </div>

                <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                    <div class="bg-white/10 rounded p-2.5">
                        <div class="text-[10px] text-blue-200">Pre-Test</div>
                        <div class="text-xl font-black text-white mt-0.5">{{ result.gain_score.pre_test_score }}</div>
                    </div>
                    <div class="bg-white/10 rounded p-2.5">
                        <div class="text-[10px] text-blue-200">Post-Test</div>
                        <div class="text-xl font-black text-white mt-0.5">{{ result.gain_score.post_test_score }}</div>
                    </div>
                    <div class="bg-white/20 rounded p-2.5 border border-white/30">
                        <div class="text-[10px] text-emerald-300 font-bold">Delta (&Delta;)</div>
                        <div class="text-xl font-black text-emerald-400 mt-0.5">
                            {{ result.gain_score.delta_score > 0 ? `+${result.gain_score.delta_score}` : result.gain_score.delta_score }}
                        </div>
                    </div>
                </div>

                <div class="mt-3 bg-black/20 rounded p-2.5 text-xs flex items-center justify-between">
                    <span class="text-blue-200 text-[11px]">Kategori Capaian:</span>
                    <span class="font-extrabold text-white">{{ result.gain_score.improvement_category }}</span>
                </div>
            </div>

            <!-- Tombol Aksi Selesai -->
            <div class="space-y-2 pt-2">
                <Link
                    href="/training-today"
                    class="w-full flex items-center justify-center gap-2 py-2.5 px-4 rounded bg-slate-900 text-white font-bold text-xs hover:bg-slate-800 shadow"
                >
                    &larr; Kembali ke Jadwal Pelatihan Hari Ini
                </Link>
                <Link
                    href="/participant/profile-matrix"
                    class="w-full flex items-center justify-center gap-2 py-2.5 px-4 rounded bg-white text-slate-700 font-bold text-xs border border-slate-300 hover:bg-slate-50"
                >
                    Lihat Matriks &amp; Profil Radar Skill
                </Link>
            </div>
        </div>

        <!-- =================================================================== -->
        <!-- VIEW B: LEMBAR PENGERJAAN SOAL UJIAN REAL-TIME                     -->
        <!-- =================================================================== -->
        <div v-else class="space-y-3">
            <!-- Sticky Timer & Header Ujian -->
            <div class="sticky top-14 z-10 bg-white rounded-lg border border-slate-200 p-3 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <span
                            :class="quiz.quiz_type === 'pre_test' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'"
                            class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider"
                        >
                            {{ quiz.quiz_type === 'pre_test' ? 'PRE-TEST AWAL' : 'POST-TEST AKHIR' }}
                        </span>
                        <div class="text-xs font-bold text-slate-800 mt-0.5 truncate max-w-[200px]">
                            {{ quiz.course.title }}
                        </div>
                    </div>

                    <!-- Countdown Timer -->
                    <div :class="['px-3 py-1 rounded border font-mono font-black text-sm flex items-center gap-1.5', timerColorClass]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>{{ formattedTime }}</span>
                    </div>
                </div>

                <!-- Indikator Progress Pengerjaan -->
                <div class="mt-2.5 flex items-center justify-between text-[11px] text-slate-500">
                    <span>Terjawab: <strong class="text-blue-700">{{ answeredCount }}</strong> / {{ totalQuestions }}</span>
                    <span v-if="unansweredCount > 0" class="text-amber-600 font-semibold">{{ unansweredCount }} Soal Belum</span>
                    <span v-else class="text-emerald-700 font-semibold">Semua Terjawab ✓</span>
                </div>
            </div>

            <!-- Interaktif Nomor Soal (Grid Navigator) -->
            <div class="bg-white rounded-lg border border-slate-200 p-2.5 shadow-sm">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1.5">
                    Nomor Soal:
                </div>
                <div class="flex flex-wrap gap-1.5">
                    <button
                        v-for="(q, idx) in questions"
                        :key="q.id"
                        type="button"
                        @click="goToQuestion(idx)"
                        :class="[
                            'w-7 h-7 rounded text-xs font-bold transition-all flex items-center justify-center',
                            idx === currentQuestionIndex
                                ? 'ring-2 ring-blue-600 ring-offset-1 font-black'
                                : '',
                            answers[q.id]
                                ? 'bg-blue-600 text-white'
                                : 'bg-slate-100 text-slate-700 hover:bg-slate-200',
                        ]"
                    >
                        {{ idx + 1 }}
                    </button>
                </div>
            </div>

            <!-- Kartu Soal Aktif -->
            <div v-if="currentQuestion" class="bg-white rounded-lg border border-slate-200 p-4 shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                    <span class="text-xs font-black text-blue-700 uppercase tracking-wider">
                        Soal No. {{ currentQuestionIndex + 1 }} dari {{ totalQuestions }}
                    </span>
                    <span class="text-[10px] font-bold text-slate-400">
                        Bobot: {{ currentQuestion.points }} Poin
                    </span>
                </div>

                <!-- Teks Soal -->
                <p class="text-xs font-semibold text-slate-800 leading-relaxed">
                    {{ currentQuestion.question_text }}
                </p>

                <!-- Pilihan Jawaban (A, B, C, D) -->
                <div class="space-y-2 pt-1">
                    <button
                        v-for="(optText, optKey) in currentQuestion.options"
                        :key="optKey"
                        type="button"
                        @click="selectOption(currentQuestion.id, optKey)"
                        :class="[
                            'w-full text-left p-3 rounded-lg border transition-all flex items-start gap-3',
                            answers[currentQuestion.id] === optKey
                                ? 'bg-blue-50 border-blue-600 text-blue-900 font-semibold shadow-sm'
                                : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-50',
                        ]"
                    >
                        <span
                            :class="[
                                'w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-black shrink-0 mt-0.5',
                                answers[currentQuestion.id] === optKey
                                    ? 'bg-blue-600 text-white'
                                    : 'bg-slate-100 text-slate-600 border border-slate-300',
                            ]"
                        >
                            {{ optKey }}
                        </span>
                        <span class="text-xs leading-snug flex-1">{{ optText }}</span>
                    </button>
                </div>
            </div>

            <!-- Navigasi Bawah (Sebelumnya, Selanjutnya, & Kumpulkan) -->
            <div class="flex items-center justify-between gap-2 pt-1">
                <button
                    type="button"
                    @click="prevQuestion"
                    :disabled="currentQuestionIndex === 0"
                    class="py-2 px-3 rounded bg-white border border-slate-300 text-slate-700 text-xs font-bold disabled:opacity-40 hover:bg-slate-50"
                >
                    &larr; Prev
                </button>

                <button
                    v-if="currentQuestionIndex < totalQuestions - 1"
                    type="button"
                    @click="nextQuestion"
                    class="py-2 px-4 rounded bg-slate-900 text-white text-xs font-bold hover:bg-slate-800"
                >
                    Next &rarr;
                </button>

                <button
                    v-else
                    type="button"
                    @click="isConfirmModalOpen = true"
                    class="py-2 px-4 rounded bg-emerald-600 text-white text-xs font-extrabold hover:bg-emerald-700 shadow"
                >
                    Kumpulkan Ujian
                </button>
            </div>

            <!-- Tombol Darurat Kumpulkan Sewaktu-waktu -->
            <div class="pt-2 text-center">
                <button
                    type="button"
                    @click="isConfirmModalOpen = true"
                    class="text-[11px] font-bold text-slate-500 hover:text-rose-600 underline"
                >
                    Selesai lebih awal? Kumpulkan Lembar Jawaban Sekarang
                </button>
            </div>
        </div>

        <!-- MODAL KONFIRMASI PENGUMPULAN UJIAN -->
        <div
            v-if="isConfirmModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4"
        >
            <div class="bg-white rounded-lg max-w-sm w-full p-5 shadow-2xl border border-slate-200 text-center">
                <div class="w-12 h-12 rounded-full bg-blue-100 text-blue-600 mx-auto flex items-center justify-center mb-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>

                <h3 class="text-sm font-extrabold text-slate-900">Konfirmasi Kumpulkan Jawaban</h3>
                <p class="text-xs text-slate-600 mt-1.5 leading-relaxed">
                    Anda telah menjawab <strong class="text-blue-700">{{ answeredCount }}</strong> dari {{ totalQuestions }} soal.
                    <span v-if="unansweredCount > 0" class="block text-rose-600 font-bold mt-1">
                        Peringatan: Terdapat {{ unansweredCount }} soal yang belum Anda jawab!
                    </span>
                </p>

                <div class="mt-5 grid grid-cols-2 gap-2">
                    <button
                        type="button"
                        @click="isConfirmModalOpen = false"
                        class="py-2 px-3 rounded bg-slate-100 text-slate-700 text-xs font-bold hover:bg-slate-200"
                    >
                        Batal &amp; Cek Lagi
                    </button>
                    <button
                        type="button"
                        @click="submitExam(false)"
                        :disabled="isSubmitting"
                        class="py-2 px-3 rounded bg-blue-600 text-white text-xs font-bold hover:bg-blue-700 disabled:opacity-50"
                    >
                        {{ isSubmitting ? 'Mengirim...' : 'Ya, Kumpulkan' }}
                    </button>
                </div>
            </div>
        </div>
    </ParticipantLayout>
</template>

