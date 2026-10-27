<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use App\Models\QuizQuestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ParticipantQuizController extends Controller
{
    /**
     * Memulai atau melanjutkan pengerjaan Quiz (Pre-Test / Post-Test).
     * PENTING: Daftar soal dikirim ke frontend TANPA field `correct_option`
     * guna mencegah kebocoran kunci jawaban via Network Tab / DevTools.
     */
    public function start(Request $request, Quiz $quiz): Response|RedirectResponse
    {
        $user = $request->user();

        // 1. Validasi Peserta Terdaftar di Course Terkait
        $enrollment = Enrollment::query()
            ->where('user_id', $user->id)
            ->where('course_id', $quiz->course_id)
            ->where('status', 'approved')
            ->first();

        if (!$enrollment) {
            return redirect()->route('mobile.home')->with('error', 'Anda belum terdaftar atau belum disetujui pada pelatihan ini.');
        }

        // 2. Validasi Prasyarat Post-Test (Minimal kehadiran 1 sesi atau progress modul tercatat)
        if ($quiz->quiz_type === 'post_test') {
            $hasAttendance = Attendance::query()
                ->where('user_id', $user->id)
                ->whereHas('attendanceSession', fn ($q) => $q->where('course_id', $quiz->course_id))
                ->where('status', 'present')
                ->exists();

            if (!$hasAttendance && $enrollment->progress_percentage < 50) {
                return redirect()->route('training-today.index', ['tab' => 'pre_post_test'])
                    ->with('error', 'Prasyarat belum terpenuhi: Anda wajib hadir minimal di 1 sesi atau menyelesaikan 50% materi sebelum mengikuti Post-Test.');
            }
        }

        // 3. Cek Attempt yang Sedang Berjalan atau Sudah Selesai
        $latestAttempt = QuizAttempt::query()
            ->where('quiz_id', $quiz->id)
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        $durationSeconds = (int) ($quiz->duration_minutes * 60);

        // Jika attempt sebelumnya sudah disubmit, langsung arahkan atau tampilkan hasil
        if ($latestAttempt && $latestAttempt->submitted_at !== null) {
            return $this->renderResult($latestAttempt, 'Anda telah menyelesaikan ujian ini.');
        }

        // Jika ada attempt aktif dan masih dalam batas waktu durasi (+ grace period 60 detik)
        if ($latestAttempt && $latestAttempt->submitted_at === null) {
            $elapsed = now()->diffInSeconds($latestAttempt->started_at);
            if ($elapsed > ($durationSeconds + 60)) {
                // Waktu habis: otomatis submit dengan jawaban yang ada
                $this->finalizeAttempt($latestAttempt, []);
                return $this->renderResult($latestAttempt, 'Batas waktu ujian telah berakhir.');
            }
            $attempt = $latestAttempt;
        } else {
            // Buat Attempt baru
            $attempt = QuizAttempt::create([
                'quiz_id' => $quiz->id,
                'user_id' => $user->id,
                'score' => 0,
                'is_passed' => false,
                'started_at' => now(),
                'submitted_at' => null,
            ]);
        }

        $elapsedSeconds = now()->diffInSeconds($attempt->started_at);
        $remainingSeconds = max(0, $durationSeconds - $elapsedSeconds);

        // 4. Muat Soal TANPA menyertakan `correct_option` ke Vue JSON Payload (Anti-Cheat)
        $questions = $quiz->questions()
            ->orderBy('order_index')
            ->get(['id', 'quiz_id', 'question_text', 'options', 'points', 'order_index'])
            ->map(function (QuizQuestion $q) {
                return [
                    'id' => $q->id,
                    'question_text' => $q->question_text,
                    'options' => $q->options,
                    'points' => $q->points,
                    'order_index' => $q->order_index,
                ];
            });

        // Muat jawaban yang tersimpan sebelumnya jika sedang melanjutkan attempt
        $existingAnswers = QuizAttemptAnswer::query()
            ->where('quiz_attempt_id', $attempt->id)
            ->pluck('selected_option', 'quiz_question_id')
            ->toArray();

        return Inertia::render('Participant/TakeQuiz', [
            'quiz' => [
                'id' => $quiz->id,
                'title' => $quiz->title,
                'quiz_type' => $quiz->quiz_type,
                'passing_score' => $quiz->passing_score,
                'duration_minutes' => $quiz->duration_minutes,
                'course' => [
                    'id' => $quiz->course->id,
                    'code' => $quiz->course->code,
                    'title' => $quiz->course->title,
                ],
            ],
            'attempt' => [
                'id' => $attempt->id,
                'started_at' => $attempt->started_at->toIso8601String(),
                'remaining_seconds' => $remainingSeconds,
                'is_submitted' => false,
            ],
            'questions' => $questions,
            'initialAnswers' => $existingAnswers,
            'result' => null,
        ]);
    }

    /**
     * Submit Lembar Jawaban Ujian oleh Peserta.
     * Dilakukan di dalam DB::transaction() dengan validasi batas waktu.
     */
    public function submit(Request $request, QuizAttempt $attempt): Response|RedirectResponse
    {
        $user = $request->user();

        // Validasi Kepemilikan Attempt
        abort_if($attempt->user_id !== $user->id, 403, 'Anda tidak berhak mengakses attempt ini.');

        // Cegah Double Submit
        if ($attempt->submitted_at !== null) {
            return $this->renderResult($attempt, 'Ujian ini sudah pernah disubmit sebelumnya.');
        }

        $validated = $request->validate([
            'answers' => ['nullable', 'array'],
            'answers.*' => ['nullable', 'string', 'max:10'],
        ]);

        $answers = $validated['answers'] ?? [];

        // Validasi Toleransi Batas Waktu (+60 detik network latency)
        $durationSeconds = (int) ($attempt->quiz->duration_minutes * 60);
        $elapsed = now()->diffInSeconds($attempt->started_at);
        $isOverdue = $elapsed > ($durationSeconds + 60);

        $this->finalizeAttempt($attempt, $answers, $isOverdue);

        return $this->renderResult($attempt, 'Jawaban ujian berhasil dikumpulkan dan dinilai secara otomatis.');
    }

    /**
     * Tampilkan Hasil Evaluasi Ujian & Analisis Gain Score jika Post-Test.
     */
    public function result(Request $request, QuizAttempt $attempt): Response
    {
        $user = $request->user();
        abort_if($attempt->user_id !== $user->id && !in_array($user->role, ['lnd', 'trainer', 'admin'], true), 403);

        return $this->renderResult($attempt);
    }

    /**
     * Hitung Skor Otomatis di Backend & Simpan Hasil Evaluasi ke DB.
     */
    protected function finalizeAttempt(QuizAttempt $attempt, array $userAnswers, bool $isOverdue = false): void
    {
        $quiz = $attempt->quiz()->with('questions')->first();
        $questions = $quiz->questions;

        $totalPossiblePoints = 0;
        $earnedPoints = 0;
        $correctCount = 0;
        $wrongCount = 0;

        DB::transaction(function () use ($attempt, $questions, $userAnswers, &$totalPossiblePoints, &$earnedPoints, &$correctCount, &$wrongCount) {
            foreach ($questions as $question) {
                $totalPossiblePoints += $question->points;
                $selected = $userAnswers[$question->id] ?? null;
                $isCorrect = ($selected !== null && strtoupper(trim($selected)) === strtoupper(trim($question->correct_option)));

                if ($isCorrect) {
                    $earnedPoints += $question->points;
                    $correctCount++;
                } else {
                    $wrongCount++;
                }

                QuizAttemptAnswer::updateOrCreate(
                    [
                        'quiz_attempt_id' => $attempt->id,
                        'quiz_question_id' => $question->id,
                    ],
                    [
                        'selected_option' => $selected ? strtoupper(trim($selected)) : null,
                        'is_correct' => $isCorrect,
                    ]
                );
            }

            $finalScore = $totalPossiblePoints > 0
                ? (int) round(($earnedPoints / $totalPossiblePoints) * 100)
                : 0;

            $isPassed = $finalScore >= $quiz->passing_score;

            $attempt->update([
                'score' => $finalScore,
                'is_passed' => $isPassed,
                'submitted_at' => now(),
            ]);

            // Jika Post-Test & Lulus, perbarui progress enrollment menjadi 100%
            if ($quiz->quiz_type === 'post_test' && $isPassed) {
                Enrollment::query()
                    ->where('user_id', $attempt->user_id)
                    ->where('course_id', $quiz->course_id)
                    ->update([
                        'progress_percentage' => 100,
                    ]);
            }
        });
    }

    /**
     * Render Tampilan Hasil Ujian (Inertia View) beserta Gain Score jika Post-Test.
     */
    protected function renderResult(QuizAttempt $attempt, ?string $flashMessage = null): Response
    {
        $attempt->load(['quiz.course', 'answers.question']);
        $quiz = $attempt->quiz;
        $user = $attempt->user;

        $totalQuestions = $quiz->questions()->count();
        $correctAnswers = $attempt->answers()->where('is_correct', true)->count();
        $wrongAnswers = $totalQuestions - $correctAnswers;

        // Analisis Gain Score jika ini adalah Post-Test
        $gainScoreAnalysis = null;
        if ($quiz->quiz_type === 'post_test') {
            $preTestAttempt = QuizAttempt::query()
                ->where('user_id', $attempt->user_id)
                ->whereHas('quiz', function ($q) use ($quiz) {
                    $q->where('course_id', $quiz->course_id)
                        ->where('quiz_type', 'pre_test');
                })
                ->whereNotNull('submitted_at')
                ->latest('submitted_at')
                ->first();

            if ($preTestAttempt) {
                $preScore = $preTestAttempt->score;
                $postScore = $attempt->score;
                $delta = $postScore - $preScore;

                // Normalised Gain (Hake's Gain Score formula)
                $maxPossibleGain = 100 - $preScore;
                $normalisedGain = $maxPossibleGain > 0 ? round(($delta / $maxPossibleGain), 2) : 0;

                $gainScoreAnalysis = [
                    'pre_test_score' => $preScore,
                    'post_test_score' => $postScore,
                    'delta_score' => $delta,
                    'normalised_gain' => $normalisedGain,
                    'improvement_category' => match (true) {
                        $normalisedGain >= 0.7 => 'Peningkatan Tinggi (Tinggi)',
                        $normalisedGain >= 0.3 => 'Peningkatan Sedang (Sedang)',
                        $delta > 0 => 'Peningkatan Rendah',
                        $delta === 0 => 'Tetap (Sama)',
                        default => 'Penurunan Nilai',
                    },
                ];
            }
        }

        return Inertia::render('Participant/TakeQuiz', [
            'quiz' => [
                'id' => $quiz->id,
                'title' => $quiz->title,
                'quiz_type' => $quiz->quiz_type,
                'passing_score' => $quiz->passing_score,
                'duration_minutes' => $quiz->duration_minutes,
                'course' => [
                    'id' => $quiz->course->id,
                    'code' => $quiz->course->code,
                    'title' => $quiz->course->title,
                ],
            ],
            'attempt' => [
                'id' => $attempt->id,
                'started_at' => $attempt->started_at?->toIso8601String(),
                'remaining_seconds' => 0,
                'is_submitted' => true,
            ],
            'questions' => [],
            'initialAnswers' => [],
            'result' => [
                'attempt_id' => $attempt->id,
                'score' => $attempt->score,
                'passing_score' => $quiz->passing_score,
                'is_passed' => (bool) $attempt->is_passed,
                'submitted_at' => $attempt->submitted_at?->format('d M Y H:i'),
                'total_questions' => $totalQuestions,
                'correct_answers' => $correctAnswers,
                'wrong_answers' => $wrongAnswers,
                'gain_score' => $gainScoreAnalysis,
                'flash_message' => $flashMessage,
            ],
        ]);
    }
}

