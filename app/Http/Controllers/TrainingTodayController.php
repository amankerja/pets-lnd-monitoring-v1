<?php

namespace App\Http\Controllers;

use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Quiz;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TrainingTodayController extends Controller
{
    /**
     * TUGAS 3 - Poin 2: Halaman "TRAINING TODAY (Operasional Hari Ini)".
     * Menampilkan semua sesi pelatihan yang berlangsung HARI INI (`session_date = today()`),
     * dilengkapi tombol cepat 1-klik untuk:
     * 1. Buka Proyektor QR Absen / Scanner Kamera HP
     * 2. Generate / Cetak Daftar QR & Barcode Sesi (Native SVG)
     * 3. Daftar paket Pre-Test / Post-Test untuk dikerjakan peserta.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $today = now()->toDateString();

        // 1. Sesi Pelatihan yang berlangsung HARI INI (`session_date = today()`)
        $todaySessions = AttendanceSession::query()
            ->with(['course:id,code,title,trainer_id', 'trainer:id,name'])
            ->withAttendanceCounts()
            ->whereDate('session_date', $today)
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->get()
            ->map(function (AttendanceSession $s) {
                $payload = $s->getDynamicPayload(0);

                return [
                    'id' => $s->id,
                    'course_id' => $s->course_id,
                    'course_code' => $s->course?->code ?? 'CRS-LND',
                    'course_title' => $s->course?->title ?? '-',
                    'trainer_name' => $s->trainer?->name ?? 'Tim Instruktur L&D',
                    'title' => $s->title,
                    'session_date' => $s->session_date?->format('d M Y'),
                    'duration_hours' => (float) ($s->duration_hours ?: 2.0),
                    'is_active' => (bool) $s->is_active,
                    'present_count' => (int) $s->present_count,
                    'permit_count' => (int) $s->permit_count,
                    'absent_count' => (int) $s->absent_count,
                    'qr_string' => $payload['qr_string'],
                    'manual_code' => $payload['manual_code'],
                    'time_window' => $payload['time_window'],
                ];
            });

        // 2. Kalender / Jadwal Sesi Pelatihan Terdekat & Terakhir (Untuk Tab Kalender Pelatihan)
        $upcomingSessions = AttendanceSession::query()
            ->with(['course:id,code,title', 'trainer:id,name'])
            ->withAttendanceCounts()
            ->orderByDesc('session_date')
            ->limit(15)
            ->get()
            ->map(function (AttendanceSession $s) {
                $payload = $s->getDynamicPayload(0);

                return [
                    'id' => $s->id,
                    'course_code' => $s->course?->code ?? 'CRS-LND',
                    'course_title' => $s->course?->title ?? '-',
                    'trainer_name' => $s->trainer?->name ?? 'Tim Instruktur L&D',
                    'title' => $s->title,
                    'session_date' => $s->session_date?->format('d M Y'),
                    'is_today' => $s->session_date?->toDateString() === now()->toDateString(),
                    'duration_hours' => (float) ($s->duration_hours ?: 2.0),
                    'is_active' => (bool) $s->is_active,
                    'present_count' => (int) $s->present_count,
                    'qr_string' => $payload['qr_string'],
                    'manual_code' => $payload['manual_code'],
                ];
            });

        $courses = Course::query()
            ->select(['id', 'code', 'title'])
            ->orderBy('code')
            ->get();

        $quizzes = Quiz::query()
            ->with(['course:id,code,title'])
            ->withCount('questions')
            ->orderBy('id')
            ->get()
            ->map(fn (Quiz $q) => [
                'id' => $q->id,
                'course_id' => $q->course_id,
                'course_code' => $q->course?->code ?? '-',
                'course_title' => $q->course?->title ?? '-',
                'title' => $q->title,
                'quiz_type' => $q->quiz_type,
                'passing_score' => $q->passing_score,
                'duration_minutes' => $q->duration_minutes,
                'questions_count' => (int) $q->questions_count,
            ]);

        return Inertia::render('TrainingToday/Index', [
            'todayDateFormatted' => now()->translatedFormat('l, d F Y'),
            'todaySessions' => $todaySessions,
            'upcomingSessions' => $upcomingSessions,
            'courses' => $courses,
            'quizzes' => $quizzes,
            'activeTabParam' => $request->string('tab', 'today')->toString(),
        ]);
    }
}
