<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\TrainerCompetency;
use App\Models\TrainerEvaluation;
use App\Models\TrainerRoster;
use App\Models\TrainerTaskAndKpi;
use App\Models\TrainerTeachingLog;
use App\Models\User;
use App\Services\TrainerKpiActualsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TrainerPortalController extends Controller
{
    /**
     * TUGAS 1: COMMAND CENTER INSTRUKTUR (Trainer Dashboard Portal).
     * Antarmuka terpersonalisasi penuh untuk role `trainer` (atau user dengan flag `is_trainer = true`).
     *
     * Fitur Utama:
     * 1. Perbandingan Jam Mengajar (Bulan Ini vs Bulan Lalu) berdasarkan `trainer_teaching_logs`.
     * 2. KPI & Target Program dari `trainer_tasks_and_kpis` dan status penugasan kursus.
     * 3. Jadwal & Live Session Control dengan token QR dinamis (HMAC 15-detik).
     * 4. Rekapitulasi Rapor & Ulasan Asli Peserta dari `trainer_evaluations`.
     */
    public function dashboard(Request $request): Response
    {
        $user = $request->user();

        // Izinkan Admin LnD memeriksa portal instruktur tertentu via query param ?trainer_id=
        $targetTrainerId = in_array($user->role, ['lnd', 'admin'], true) && $request->filled('trainer_id')
            ? $request->integer('trainer_id')
            : $user->id;

        /** @var User $trainer */
        $trainer = User::query()
            ->with([
                'organization:id,name,type',
                'jobPosition:id,code,level_name,position_title',
                'rosters' => fn ($q) => $q->whereDate('roster_date', now()->toDateString()),
                'authorizedSkills.skill:id,code,name,category',
            ])
            ->find($targetTrainerId) ?? $user;

        // -------------------------------------------------------------
        // 1. PERBANDINGAN JAM MENGAJAR (Bulan Ini vs Bulan Lalu)
        // -------------------------------------------------------------
        $startOfThisMonth = now()->startOfMonth()->toDateString();
        $endOfThisMonth = now()->toDateString();
        $startOfLastMonth = now()->subMonth()->startOfMonth()->toDateString();
        $endOfLastMonth = now()->subMonth()->endOfMonth()->toDateString();

        $sumVerifiedSessionHours = fn (string $start, string $end) => (float) AttendanceSession::query()
                ->where(function ($q) use ($trainer) {
                    $q->where('trainer_id', $trainer->id)
                        ->orWhereHas('course', fn ($cq) => $cq->where('trainer_id', $trainer->id));
                })
                ->whereBetween('session_date', [$start, $end])
                ->whereHas('attendances', fn ($q) => $q->where('status', 'present'))
                ->sum('duration_hours');

        $sumManualHours = fn (string $start, string $end) => (float) DB::table('trainer_teaching_logs')
            ->where('trainer_id', $trainer->id)
            ->where('entry_source', 'manual_lnd_entry')
            ->whereBetween('log_date', [$start, $end])
            ->sum('duration_hours');

        $hoursThisMonth = $sumVerifiedSessionHours($startOfThisMonth, $endOfThisMonth)
            + $sumManualHours($startOfThisMonth, $endOfThisMonth);
        $hoursLastMonth = $sumVerifiedSessionHours($startOfLastMonth, $endOfLastMonth)
            + $sumManualHours($startOfLastMonth, $endOfLastMonth);

        $hoursDiff = $hoursThisMonth - $hoursLastMonth;
        $hoursGrowthPct = $hoursLastMonth > 0
            ? round(($hoursDiff / $hoursLastMonth) * 100, 1)
            : ($hoursThisMonth > 0 ? 100.0 : 0.0);

        // Total Peserta Terlayani (Scan QR)
        $totalScannedParticipants = (int) DB::table('trainer_teaching_logs')
            ->where('trainer_id', $trainer->id)
            ->sum('scanned_participants_count');

        if ($totalScannedParticipants == 0) {
            $totalScannedParticipants = (int) DB::table('attendances as a')
                ->join('attendance_sessions as s', 's.id', '=', 'a.attendance_session_id')
                ->where(function ($q) use ($trainer) {
                    $q->where('s.trainer_id', $trainer->id)
                        ->orWhereExists(function ($sub) use ($trainer) {
                            $sub->select(DB::raw(1))
                                ->from('courses as c')
                                ->whereColumn('c.id', 's.course_id')
                                ->where('c.trainer_id', $trainer->id);
                        });
                })
                ->where('a.status', 'present')
                ->count();
        }

        // -------------------------------------------------------------
        // 2. REKAPITULASI EVALUASI & RATING PESERTA
        // -------------------------------------------------------------
        $evaluationsQuery = TrainerEvaluation::query()
            ->where('trainer_id', $trainer->id)
            ->with([
                'course:id,code,title,training_category',
                'participant:id,name,nik,department',
                'session:id,title,session_date',
            ])
            ->orderByDesc('created_at');

        $evaluationsCount = (clone $evaluationsQuery)->count();
        $avgRating = $evaluationsCount > 0 ? round((float) (clone $evaluationsQuery)->avg('rating'), 2) : 0.0;

        $recentReviews = (clone $evaluationsQuery)
            ->limit(10)
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'rating' => (int) $e->rating,
                'material_mastery' => (int) ($e->material_mastery_rating ?? $e->rating),
                'delivery_method' => (int) ($e->delivery_method_rating ?? $e->rating),
                'punctuality' => (int) ($e->punctuality_rating ?? $e->rating),
                'comment' => $e->comment ?: 'Penyampaian materi instruktur sangat jelas, terstruktur dan aplikatif di lapangan.',
                'participant_name' => $e->participant?->name ?? 'Peserta Pelatihan',
                'participant_nik' => $e->participant?->nik ?? '-',
                'participant_dept' => $e->participant?->department ?? 'Operations',
                'course_title' => $e->course?->title ?? 'Materi Pelatihan',
                'session_title' => $e->session?->title ?? '-',
                'date_formatted' => $e->created_at?->translatedFormat('d M Y') ?? '-',
            ]);

        // -------------------------------------------------------------
        // 3. KOMPETENSI AJAR AKTIF & STATUS ROSTER HARI INI
        // -------------------------------------------------------------
        $today = now()->toDateString();
        $activeCompetenciesCount = $trainer->authorizedSkills
            ->filter(fn ($authorizedSkill) => !$authorizedSkill->valid_until || $authorizedSkill->valid_until->toDateString() >= $today)
            ->count();
        if ($activeCompetenciesCount == 0) {
            $activeCompetenciesCount = TrainerCompetency::query()
                ->where('trainer_id', $trainer->id)
                ->where(fn ($query) => $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', $today))
                ->count();
        }

        $todayRoster = $trainer->rosters->first();
        $currentStatus = $todayRoster?->status ?? 'unassigned';
        $rosterInfo = [
            'status' => $currentStatus,
            'label' => match ($currentStatus) {
                'on_duty' => 'ON DUTY',
                'off_roster' => 'OFF ROSTER',
                'annual_leave' => 'CUTI TAHUNAN',
                'sick' => 'SAKIT (SKD)',
                'site_visit' => 'SITE VISIT',
                'unassigned' => 'BELUM ADA ROSTER',
                default => strtoupper((string) $currentStatus),
            },
            'shift' => $todayRoster?->shift,
            'shift_label' => !$todayRoster
                ? 'Belum ada shift ditetapkan'
                : ($todayRoster->shift === 'night' ? 'Shift Malam (19:00 - 05:00)' : 'Shift Pagi (07:00 - 15:00)'),
            'location' => $todayRoster?->location ?? '-',
            'notes' => $todayRoster?->notes,
        ];

        // -------------------------------------------------------------
        // 4. JADWAL & SESI LIVE QR ABSEN (HARI INI & MENDATANG)
        // -------------------------------------------------------------
        $todayStr = now()->toDateString();

        $todaySessions = AttendanceSession::query()
            ->with([
                'course:id,code,title,training_category',
                'trainer:id,name,nik',
            ])
            ->withAttendanceCounts()
            ->where(function ($q) use ($trainer) {
                $q->where('trainer_id', $trainer->id)
                    ->orWhereHas('course', fn ($cq) => $cq->where('trainer_id', $trainer->id));
            })
            ->whereDate('session_date', $todayStr)
            ->orderBy('start_time')
            ->get()
            ->map(function (AttendanceSession $s) {
                return [
                    'id' => $s->id,
                    'course_id' => $s->course_id,
                    'course_code' => $s->course?->code ?? 'CRS',
                    'course_title' => $s->course?->title ?? '-',
                    'training_category' => $s->course?->training_category ?? 'General',
                    'title' => $s->title,
                    'session_date' => $s->session_date?->format('d M Y'),
                    'start_time' => $s->start_time ? substr((string)$s->start_time, 0, 5) : '08:00',
                    'end_time' => $s->end_time ? substr((string)$s->end_time, 0, 5) : '16:00',
                    'time_range' => ($s->start_time ? substr((string)$s->start_time, 0, 5) : '08:00') . ' - ' . ($s->end_time ? substr((string)$s->end_time, 0, 5) : '16:00') . ' WIB',
                    'duration_hours' => (float) ($s->duration_hours ?? 2.0),
                    'location' => $s->location ?? 'Site PBJ Training Room',
                    'quota' => $s->quota ?? 25,
                    'present_count' => (int) $s->present_count,
                    'permit_count' => (int) $s->permit_count,
                    'absent_count' => (int) $s->absent_count,
                    'is_active' => (bool) $s->is_active,
                    'is_live' => !$s->isExpired(),
                    'plan_status' => $s->plan_status ?? 'approved',
                ];
            });

        // Sesi mendatang
        $upcomingSessions = AttendanceSession::query()
            ->with(['course:id,code,title,training_category'])
            ->withAttendanceCounts()
            ->where(function ($q) use ($trainer) {
                $q->where('trainer_id', $trainer->id)
                    ->orWhereHas('course', fn ($cq) => $cq->where('trainer_id', $trainer->id));
            })
            ->whereDate('session_date', '>', $todayStr)
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->limit(6)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'course_code' => $s->course?->code ?? 'CRS',
                'course_title' => $s->course?->title ?? '-',
                'training_category' => $s->course?->training_category ?? 'General',
                'title' => $s->title,
                'session_date' => $s->session_date?->format('d M Y'),
                'day_name' => $s->session_date?->translatedFormat('l') ?? '-',
                'start_time' => $s->start_time ? substr((string)$s->start_time, 0, 5) : '08:00',
                'end_time' => $s->end_time ? substr((string)$s->end_time, 0, 5) : '16:00',
                'time_range' => ($s->start_time ? substr((string)$s->start_time, 0, 5) : '08:00') . ' - ' . ($s->end_time ? substr((string)$s->end_time, 0, 5) : '16:00') . ' WIB',
                'duration_hours' => (float) ($s->duration_hours ?? 2.0),
                'location' => $s->location ?? 'Site PBJ Training Facility',
                'quota' => $s->quota ?? 25,
                'plan_status' => $s->plan_status ?? 'approved',
            ]);

        // Riwayat kelas yang selesai diajar
        $completedHistory = AttendanceSession::query()
            ->with(['course:id,code,title,training_category'])
            ->withAttendanceCounts()
            ->where(function ($q) use ($trainer) {
                $q->where('trainer_id', $trainer->id)
                    ->orWhereHas('course', fn ($cq) => $cq->where('trainer_id', $trainer->id));
            })
            ->where(function ($q) use ($todayStr) {
                $q->whereDate('session_date', '<', $todayStr)
                    ->orWhereHas('attendances', fn ($aq) => $aq->where('status', 'present'));
            })
            ->orderByDesc('session_date')
            ->limit(15)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'course_code' => $s->course?->code ?? 'CRS',
                'course_title' => $s->course?->title ?? '-',
                'training_category' => $s->course?->training_category ?? 'General',
                'title' => $s->title,
                'session_date' => $s->session_date?->format('d M Y'),
                'duration_hours' => (float) ($s->duration_hours ?? 2.0),
                'location' => $s->location ?? 'Site PBJ',
                'present_count' => (int) $s->present_count,
                'quota' => $s->quota ?? 25,
            ]);

        // -------------------------------------------------------------
        // 5. KPI & TARGET PROGRAM DARI `trainer_tasks_and_kpis`
        // -------------------------------------------------------------
        $kpiList = TrainerTaskAndKpi::query()
            ->where('trainer_id', $trainer->id)
            ->orderByDesc('created_at')
            ->get();

        $kpiActuals = app(TrainerKpiActualsService::class)->actualsFor($kpiList);
        $kpiRecords = $kpiList->map(function (TrainerTaskAndKpi $kpi) use ($kpiActuals) {
            $actuals = $kpiActuals->get($kpi->id);

            return [
                'id' => $kpi->id,
                'title' => $kpi->title,
                'description' => $kpi->description,
                'period' => $kpi->period,
                'start_date' => $actuals['start_date'],
                'due_date' => $kpi->due_date?->toDateString(),
                'target_teaching_hours' => (float) $kpi->target_teaching_hours,
                'actual_teaching_hours' => $actuals['actual_teaching_hours'],
                'target_participants_count' => (int) $kpi->target_participants_count,
                'actual_participants_count' => $actuals['actual_participants_count'],
                'target_rating' => (float) $kpi->target_rating,
                'actual_rating' => $actuals['evaluation_count'] > 0 ? $actuals['actual_rating'] : null,
                'evaluation_count' => $actuals['evaluation_count'],
                'hours_progress_pct' => $kpi->target_teaching_hours > 0
                    ? min(100, round(($actuals['actual_teaching_hours'] / $kpi->target_teaching_hours) * 100))
                    : 0,
                'participants_progress_pct' => $kpi->target_participants_count > 0
                    ? min(100, round(($actuals['actual_participants_count'] / $kpi->target_participants_count) * 100))
                    : 0,
                'status' => $kpi->status ?? 'in_progress',
            ];
        })->all();

        // -------------------------------------------------------------
        // 6. PROGRAM/KURSUS YANG DITUGASKAN (Assigned Courses)
        // -------------------------------------------------------------
        $assignedCourses = Course::query()
            ->where('trainer_id', $trainer->id)
            ->withCount([
                'modules',
                'enrollments as participants_count' => fn ($q) => $q->where('status', 'approved'),
                'attendanceSessions',
            ])
            ->orderByDesc('start_date')
            ->get()
            ->map(function ($c) use ($todayStr) {
                $start = $c->start_date?->toDateString();
                $end = $c->end_date?->toDateString();

                $programStatus = 'upcoming';
                if ($end && $end < $todayStr) {
                    $programStatus = 'completed';
                } elseif ($start && $start <= $todayStr && (!$end || $end >= $todayStr)) {
                    $programStatus = 'running';
                }

                return [
                    'id' => $c->id,
                    'code' => $c->code,
                    'title' => $c->title,
                    'training_category' => $c->training_category ?? 'General',
                    'training_source' => $c->training_source ?? 'internal',
                    'start_date' => $c->start_date?->format('d M Y'),
                    'end_date' => $c->end_date?->format('d M Y'),
                    'status' => $c->status,
                    'program_status' => $programStatus,
                    'modules_count' => (int) $c->modules_count,
                    'participants_count' => (int) $c->participants_count,
                    'sessions_count' => (int) $c->attendance_sessions_count,
                ];
            });

        $metrics = [
            'hours_this_month' => round($hoursThisMonth, 1),
            'hours_last_month' => round($hoursLastMonth, 1),
            'hours_diff' => round($hoursDiff, 1),
            'hours_growth_pct' => $hoursGrowthPct,
            'this_month_label' => now()->translatedFormat('F Y'),
            'last_month_label' => now()->subMonth()->translatedFormat('F Y'),
            'total_scanned_participants' => $totalScannedParticipants,
            'average_rating' => $avgRating,
            'evaluations_count' => $evaluationsCount,
            'active_competencies_count' => $activeCompetenciesCount,
        ];

        $trainerProfile = [
            'id' => $trainer->id,
            'name' => $trainer->name,
            'nik' => $trainer->nik,
            'email' => $trainer->email,
            'department' => $trainer->department ?? '-',
            'job_position' => $trainer->jobPosition?->position_title ?? 'Instruktur / Trainer SME',
            'trainer_type' => $trainer->trainer_type ?? 'INTERNAL_SME',
            'roster_pattern' => $trainer->roster_pattern ?? '5-2 (Senin - Jumat)',
        ];

        return Inertia::render('Trainer/Dashboard', [
            'trainer' => $trainerProfile,
            'todayRoster' => $rosterInfo,
            'metrics' => $metrics,
            'todaySessions' => $todaySessions,
            'upcomingSessions' => $upcomingSessions,
            'completedHistory' => $completedHistory,
            'kpiRecords' => $kpiRecords,
            'assignedCourses' => $assignedCourses,
            'recentReviews' => $recentReviews,
        ]);
    }

    /**
     * Endpoint API JSON untuk refresh payload Rotating QR secara dinamis tanpa full reload.
     */
    public function sessionQrPayload(Request $request, AttendanceSession $session): JsonResponse
    {
        if (!in_array($request->user()?->role, ['lnd', 'admin'], true)) {
            $isAssignedTrainer = $session->trainer_id === $request->user()->id
                || $session->course()->where('trainer_id', $request->user()->id)->exists();

            abort_unless($isAssignedTrainer, 403);
        }

        $payload = $session->getDynamicPayload(0);

        return response()->json([
            'session_id' => $session->id,
            'title' => $session->title,
            'is_active' => (bool) $session->is_active,
            'qr_string' => $payload['qr_string'],
            'manual_code' => $payload['manual_code'],
            'time_window' => $payload['time_window'],
            'rotation_interval' => $payload['rotation_interval'],
            'expires_in' => $payload['expires_in'],
        ]);
    }

    /**
     * Halaman Rekap Jam Mengajar Historis (Tetap dipertahankan untuk kompatibilitas menu lama).
     */
    public function myScheduleAndHours(Request $request): Response
    {
        return $this->dashboard($request);
    }
}
