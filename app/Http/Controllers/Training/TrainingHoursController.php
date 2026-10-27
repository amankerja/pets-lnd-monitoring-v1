<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\TrainerTaskAndKpi;
use App\Models\TrainerTeachingLog;
use App\Models\User;
use App\Services\TrainerKpiActualsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TrainingHoursController extends Controller
{
    /**
     * SUMMARY ALL TRAINERS - TRAINING HOURS & RIWAYAT (MINGGU, BULAN, TAHUN)
     *
     * Fitur Utama:
     * 1. Summary All Trainers: Total Target, Plan, Realisasi & Achievement %
     * 2. Tabel Rekap Seluruh Trainer: Target vs Plan vs Realisasi jam mengajar
     * 3. Filter & Riwayat Berdasarkan: Minggu, Bulan, dan Tahun
     * 4. Admin bisa menambahkan / mengubah target jam mengajar per trainer
     * 5. Rincian Realisasi & Plan Training Hours per Program/Kursus yang diampu trainer
     */
    public function index(Request $request): Response
    {
        $periodType = $request->input('period_type', 'month'); // 'week' | 'month' | 'year'
        if (!in_array($periodType, ['week', 'month', 'year'], true)) {
            $periodType = 'month';
        }

        $now = now();
        $dateParam = $request->input('date');
        $refDate = $dateParam ? Carbon::parse($dateParam) : $now->copy();

        // -------------------------------------------------------------
        // 1. Tentukan Rentang Waktu Periode (Minggu, Bulan, atau Tahun)
        // -------------------------------------------------------------
        switch ($periodType) {
            case 'week':
                $startDate = $refDate->copy()->startOfWeek();
                $endDate = $refDate->copy()->endOfWeek();
                $periodKey = $startDate->format('Y') . '-W' . str_pad((string) $startDate->weekOfYear, 2, '0', STR_PAD_LEFT);
                $periodLabel = 'Minggu Ke-' . $startDate->weekOfYear . ' (' . $startDate->translatedFormat('d M') . ' - ' . $endDate->translatedFormat('d M Y') . ')';
                $prevDate = $startDate->copy()->subWeek()->toDateString();
                $nextDate = $startDate->copy()->addWeek()->toDateString();
                break;

            case 'year':
                $startDate = $refDate->copy()->startOfYear();
                $endDate = $refDate->copy()->endOfYear();
                $periodKey = $startDate->format('Y');
                $periodLabel = 'Tahun ' . $startDate->format('Y');
                $prevDate = $startDate->copy()->subYear()->toDateString();
                $nextDate = $startDate->copy()->addYear()->toDateString();
                break;

            case 'month':
            default:
                $periodType = 'month';
                $startDate = $refDate->copy()->startOfMonth();
                $endDate = $refDate->copy()->endOfMonth();
                $periodKey = $startDate->format('Y-m');
                $periodLabel = $startDate->translatedFormat('F Y');
                $prevDate = $startDate->copy()->subMonth()->toDateString();
                $nextDate = $startDate->copy()->addMonth()->toDateString();
                break;
        }

        $startStr = $startDate->toDateString();
        $endStr = $endDate->toDateString();
        $todayStr = $now->toDateString();

        // -------------------------------------------------------------
        // 2. Filter & Query Daftar Trainer
        // -------------------------------------------------------------
        $search = trim((string) $request->input('search', ''));
        $trainerTypeFilter = $request->input('trainer_type', '');
        $departmentFilter = $request->input('department', '');

        $trainersQuery = User::query()
            ->trainers()
            ->with([
                'organization:id,name,type',
                'jobPosition:id,code,level_name,position_title',
                'rosters' => fn ($q) => $q->whereDate('roster_date', $todayStr),
                'authorizedSkills.skill:id,code,name,category',
            ])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('nik', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when(!empty($trainerTypeFilter), fn ($q) => $q->where('trainer_type', $trainerTypeFilter))
            ->when(!empty($departmentFilter), fn ($q) => $q->where('department', $departmentFilter));

        $rawTrainers = $trainersQuery->orderBy('name')->get();
        $trainerIds = $rawTrainers->pluck('id')->all();

        // List Department untuk filter dropdown
        $allDepartments = User::trainers()
            ->whereNotNull('department')
            ->where('department', '!=', '')
            ->distinct()
            ->orderBy('department')
            ->pluck('department')
            ->all();

        // -------------------------------------------------------------
        // 3. Agregasi Sesi Presensi & Jam Mengajar pada Periode Terpilih
        // -------------------------------------------------------------
        // A. Sesi Terjadwal / Plan (Semua sesi pada rentang waktu ini)
        $plannedSessionsQuery = AttendanceSession::query()
            ->with(['course:id,code,title,training_category,trainer_id'])
            ->withAttendanceCounts()
            ->whereBetween('session_date', [$startStr, $endStr])
            ->where(function ($q) use ($trainerIds) {
                $q->whereIn('trainer_id', $trainerIds)
                    ->orWhereHas('course', fn ($cq) => $cq->whereIn('trainer_id', $trainerIds));
            })
            ->get();

        // B. Log Mengajar Manual (TrainerTeachingLog) untuk sesi manual L&D
        $manualLogs = DB::table('trainer_teaching_logs')
            ->whereIn('trainer_id', $trainerIds)
            ->where('entry_source', 'manual_lnd_entry')
            ->whereBetween('log_date', [$startStr, $endStr])
            ->select('trainer_id', 'duration_hours', 'scanned_participants_count', 'course_id', 'training_title', 'log_date')
            ->get();

        // C. Target KPI / Target Training Hours per Trainer
        // Cari KPI yang berlaku pada periode ini
        $kpiRecords = TrainerTaskAndKpi::query()
            ->whereIn('trainer_id', $trainerIds)
            ->where(function ($q) use ($startStr, $endStr, $periodType) {
                $q->where('period', match ($periodType) {
                    'week' => 'weekly',
                    'year' => 'yearly',
                    default => 'monthly',
                })
                ->where(function ($sub) use ($startStr, $endStr) {
                    $sub->whereBetween('start_date', [$startStr, $endStr])
                        ->orWhereBetween('due_date', [$startStr, $endStr])
                        ->orWhere(function ($s) use ($startStr, $endStr) {
                            $s->where('start_date', '<=', $startStr)
                              ->where('due_date', '>=', $endStr);
                        });
                });
            })
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('trainer_id');

        // D. Seluruh Program / Kursus yang Diampu oleh Masing-Masing Trainer
        $trainerCourses = Course::query()
            ->whereIn('trainer_id', $trainerIds)
            ->orWhereHas('attendanceSessions', fn ($q) => $q->whereIn('trainer_id', $trainerIds))
            ->with([
                'attendanceSessions' => fn ($q) => $q->withAttendanceCounts()->orderBy('session_date'),
                'skill:id,code,name,category',
            ])
            ->withCount([
                'attendanceSessions as total_sessions_count',
                'enrollments as participants_count' => fn ($q) => $q->where('status', 'approved'),
            ])
            ->get();

        // -------------------------------------------------------------
        // 4. Transformasi Data Seluruh Trainer (Table Rows)
        // -------------------------------------------------------------
        $trainersData = $rawTrainers->map(function (User $trainer) use (
            $plannedSessionsQuery,
            $manualLogs,
            $kpiRecords,
            $trainerCourses,
            $startStr,
            $endStr
        ) {
            $tId = $trainer->id;

            // Sesi Trainer pada Periode Ini
            $trainerPeriodSessions = $plannedSessionsQuery->filter(function ($s) use ($tId) {
                return $s->trainer_id === $tId || ($s->course && $s->course->trainer_id === $tId);
            });

            // Sesi Terverifikasi (Ada minimal 1 peserta hadir)
            $verifiedPeriodSessions = $trainerPeriodSessions->filter(function ($s) {
                return (int) $s->present_count > 0;
            });

            // Manual logs periode ini
            $trainerManualLogs = $manualLogs->where('trainer_id', $tId);

            // Perhitungan Jam
            $planHours = (float) $trainerPeriodSessions->sum('duration_hours');
            $verifiedHours = (float) $verifiedPeriodSessions->sum('duration_hours');
            $manualHours = (float) $trainerManualLogs->sum('duration_hours');
            $realisasiHours = round($verifiedHours + $manualHours, 1);

            $scannedParticipants = (int) $verifiedPeriodSessions->sum('present_count')
                + (int) $trainerManualLogs->sum('scanned_participants_count');

            // Ambil Target KPI Trainer untuk periode ini
            $kpi = $kpiRecords->get($tId)?->first();
            $targetHours = $kpi ? (float) $kpi->target_teaching_hours : 0.0;
            $targetParticipants = $kpi ? (int) $kpi->target_participants_count : 0;
            $targetRating = $kpi ? (float) $kpi->target_rating : 4.5;

            // Variansi & Persentase Capaian
            $achievementVsTargetPct = $targetHours > 0
                ? min(999, round(($realisasiHours / $targetHours) * 100, 1))
                : null;

            $achievementVsPlanPct = $planHours > 0
                ? min(999, round(($realisasiHours / $planHours) * 100, 1))
                : 0.0;

            $gapHours = $targetHours > 0 ? round($realisasiHours - $targetHours, 1) : 0.0;

            // Program / Kursus yang Diampu Trainer beserta Realisasi & Plan Hours per Program
            $trainerAssignedCourses = $trainerCourses->filter(function ($c) use ($tId) {
                return $c->trainer_id === $tId || $c->attendanceSessions->contains('trainer_id', $tId);
            });

            $programsBreakdown = $trainerAssignedCourses->map(function (Course $course) use ($tId, $startStr, $endStr) {
                // Sesi program ini pada periode aktif
                $periodCourseSessions = $course->attendanceSessions->filter(function ($s) use ($tId, $startStr, $endStr) {
                    $inDate = $s->session_date && $s->session_date->format('Y-m-d') >= $startStr && $s->session_date->format('Y-m-d') <= $endStr;
                    $isTrainer = $s->trainer_id === $tId || $course->trainer_id === $tId;
                    return $inDate && $isTrainer;
                });

                // Semua sesi program ini (Overall)
                $allCourseSessions = $course->attendanceSessions->filter(function ($s) use ($tId, $course) {
                    return $s->trainer_id === $tId || $course->trainer_id === $tId;
                });

                $planHoursPeriod = (float) $periodCourseSessions->sum('duration_hours');
                $realisasiHoursPeriod = (float) $periodCourseSessions->filter(fn ($s) => (int) $s->present_count > 0)->sum('duration_hours');

                $planHoursTotal = (float) $allCourseSessions->sum('duration_hours');
                $realisasiHoursTotal = (float) $allCourseSessions->filter(fn ($s) => (int) $s->present_count > 0)->sum('duration_hours');

                $presentParticipantsPeriod = (int) $periodCourseSessions->sum('present_count');
                $verifiedSessionsCountPeriod = $periodCourseSessions->filter(fn ($s) => (int) $s->present_count > 0)->count();

                $programPct = $planHoursPeriod > 0
                    ? min(100, round(($realisasiHoursPeriod / $planHoursPeriod) * 100, 1))
                    : ($planHoursTotal > 0 ? min(100, round(($realisasiHoursTotal / $planHoursTotal) * 100, 1)) : 0);

                return [
                    'id' => $course->id,
                    'code' => $course->code,
                    'title' => $course->title,
                    'training_category' => $course->training_category ?? 'General',
                    'training_source' => $course->training_source ?? 'internal',
                    'status' => $course->status,
                    'start_date' => $course->start_date?->format('d M Y'),
                    'end_date' => $course->end_date?->format('d M Y'),
                    'plan_hours' => round($planHoursPeriod > 0 ? $planHoursPeriod : $planHoursTotal, 1),
                    'plan_hours_period' => round($planHoursPeriod, 1),
                    'plan_hours_total' => round($planHoursTotal, 1),
                    'realisasi_hours' => round($realisasiHoursPeriod > 0 ? $realisasiHoursPeriod : $realisasiHoursTotal, 1),
                    'realisasi_hours_period' => round($realisasiHoursPeriod, 1),
                    'realisasi_hours_total' => round($realisasiHoursTotal, 1),
                    'achievement_pct' => $programPct,
                    'total_sessions_period' => $periodCourseSessions->count(),
                    'verified_sessions_period' => $verifiedSessionsCountPeriod,
                    'total_sessions_all' => $allCourseSessions->count(),
                    'participants_count' => $presentParticipantsPeriod > 0 ? $presentParticipantsPeriod : (int) $course->participants_count,
                    'sessions_list' => $periodCourseSessions->isNotEmpty()
                        ? $periodCourseSessions->values()->map(fn ($s) => [
                            'id' => $s->id,
                            'title' => $s->title,
                            'session_date' => $s->session_date?->format('d M Y'),
                            'time_range' => ($s->start_time ? substr((string)$s->start_time, 0, 5) : '08:00') . ' - ' . ($s->end_time ? substr((string)$s->end_time, 0, 5) : '16:00'),
                            'duration_hours' => (float) ($s->duration_hours ?? 2.0),
                            'location' => $s->location ?? '-',
                            'present_count' => (int) $s->present_count,
                            'is_verified' => (int) $s->present_count > 0,
                        ])
                        : $allCourseSessions->take(5)->values()->map(fn ($s) => [
                            'id' => $s->id,
                            'title' => $s->title,
                            'session_date' => $s->session_date?->format('d M Y'),
                            'time_range' => ($s->start_time ? substr((string)$s->start_time, 0, 5) : '08:00') . ' - ' . ($s->end_time ? substr((string)$s->end_time, 0, 5) : '16:00'),
                            'duration_hours' => (float) ($s->duration_hours ?? 2.0),
                            'location' => $s->location ?? '-',
                            'present_count' => (int) $s->present_count,
                            'is_verified' => (int) $s->present_count > 0,
                        ]),
                ];
            })->values();

            $todayRoster = $trainer->rosters->first();
            $rosterStatus = $todayRoster ? $todayRoster->status : 'on_duty';

            return [
                'id' => $trainer->id,
                'name' => $trainer->name,
                'nik' => $trainer->nik,
                'email' => $trainer->email,
                'department' => $trainer->department ?? '-',
                'job_position' => $trainer->jobPosition?->position_title ?? 'Trainer / SME',
                'trainer_type' => $trainer->trainer_type ?? 'INTERNAL_SME',
                'roster_status' => $rosterStatus,
                'roster_label' => match ($rosterStatus) {
                    'on_duty' => 'ON DUTY',
                    'off_roster' => 'OFF ROSTER',
                    'annual_leave' => 'CUTI',
                    'sick' => 'SAKIT',
                    'site_visit' => 'SITE VISIT',
                    default => strtoupper($rosterStatus),
                },
                'target_hours' => $targetHours,
                'plan_hours' => round($planHours, 1),
                'realisasi_hours' => $realisasiHours,
                'gap_hours' => $gapHours,
                'achievement_vs_target_pct' => $achievementVsTargetPct,
                'achievement_vs_plan_pct' => $achievementVsPlanPct,
                'planned_sessions_count' => $trainerPeriodSessions->count(),
                'verified_sessions_count' => $verifiedPeriodSessions->count() + $trainerManualLogs->count(),
                'scanned_participants' => $scannedParticipants,
                'programs_count' => $programsBreakdown->count(),
                'programs' => $programsBreakdown,
                'target_kpi' => $kpi ? [
                    'id' => $kpi->id,
                    'title' => $kpi->title,
                    'period' => $kpi->period,
                    'target_teaching_hours' => (float) $kpi->target_teaching_hours,
                    'target_participants_count' => (int) $kpi->target_participants_count,
                    'target_rating' => (float) $kpi->target_rating,
                    'start_date' => $kpi->start_date?->toDateString(),
                    'due_date' => $kpi->due_date?->toDateString(),
                    'status' => $kpi->status,
                ] : null,
            ];
        });

        // -------------------------------------------------------------
        // 5. SUMMARY METRICS (ALL TRAINERS COMBINED)
        // -------------------------------------------------------------
        $totalTargetHours = round((float) $trainersData->sum('target_hours'), 1);
        $totalPlanHours = round((float) $trainersData->sum('plan_hours'), 1);
        $totalRealisasiHours = round((float) $trainersData->sum('realisasi_hours'), 1);
        $activeTrainersCount = $trainersData->filter(fn ($t) => $t['realisasi_hours'] > 0)->count();
        $totalSessionsCompleted = (int) $trainersData->sum('verified_sessions_count');
        $totalParticipantsServed = (int) $trainersData->sum('scanned_participants');

        $overallVsTargetPct = $totalTargetHours > 0
            ? min(999, round(($totalRealisasiHours / $totalTargetHours) * 100, 1))
            : ($totalPlanHours > 0 ? min(999, round(($totalRealisasiHours / $totalPlanHours) * 100, 1)) : 0.0);

        $overallVsPlanPct = $totalPlanHours > 0
            ? min(999, round(($totalRealisasiHours / $totalPlanHours) * 100, 1))
            : 0.0;

        $summary = [
            'total_trainers' => $trainersData->count(),
            'active_trainers' => $activeTrainersCount,
            'total_target_hours' => $totalTargetHours,
            'total_plan_hours' => $totalPlanHours,
            'total_realisasi_hours' => $totalRealisasiHours,
            'total_gap_hours' => round($totalRealisasiHours - $totalTargetHours, 1),
            'overall_vs_target_pct' => $overallVsTargetPct,
            'overall_vs_plan_pct' => $overallVsPlanPct,
            'total_sessions_completed' => $totalSessionsCompleted,
            'total_participants_served' => $totalParticipantsServed,
            'avg_realisasi_per_trainer' => $trainersData->count() > 0 ? round($totalRealisasiHours / $trainersData->count(), 1) : 0,
            'period_label' => $periodLabel,
            'period_type' => $periodType,
            'start_date' => $startStr,
            'end_date' => $endStr,
        ];

        // -------------------------------------------------------------
        // 6. RIWAYAT BREAKDOWN (MINGGU, BULAN, TAHUN) UNTUK GRAFIK & AUDIT
        // -------------------------------------------------------------
        $historyTrend = $this->buildHistoryTrend($periodType, $refDate, $trainerIds);

        // -------------------------------------------------------------
        // 7. RIWAYAT LOG SESI PENGAJARAN TERAKHIR (Tab Riwayat Sesi)
        // -------------------------------------------------------------
        $recentSessionLogs = AttendanceSession::query()
            ->with([
                'course:id,code,title,training_category',
                'trainer:id,name,nik,department',
            ])
            ->withAttendanceCounts()
            ->whereBetween('session_date', [$startStr, $endStr])
            ->where(function ($q) use ($trainerIds) {
                $q->whereIn('trainer_id', $trainerIds)
                    ->orWhereHas('course', fn ($cq) => $cq->whereIn('trainer_id', $trainerIds));
            })
            ->orderByDesc('session_date')
            ->orderByDesc('start_time')
            ->limit(30)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'session_date' => $s->session_date?->format('d M Y'),
                'raw_date' => $s->session_date?->toDateString(),
                'title' => $s->title,
                'course_code' => $s->course?->code ?? '-',
                'course_title' => $s->course?->title ?? '-',
                'training_category' => $s->course?->training_category ?? 'General',
                'trainer_name' => $s->trainer?->name ?? ($s->course?->trainer?->name ?? 'Instruktur'),
                'trainer_nik' => $s->trainer?->nik ?? ($s->course?->trainer?->nik ?? '-'),
                'time_range' => ($s->start_time ? substr((string)$s->start_time, 0, 5) : '08:00') . ' - ' . ($s->end_time ? substr((string)$s->end_time, 0, 5) : '16:00'),
                'duration_hours' => (float) ($s->duration_hours ?? 2.0),
                'present_count' => (int) $s->present_count,
                'quota' => (int) ($s->quota ?? 25),
                'is_verified' => (int) $s->present_count > 0,
                'location' => $s->location ?? 'Site PBJ',
            ]);

        return Inertia::render('Training/TrainingHoursSummary', [
            'summary' => $summary,
            'trainers' => $trainersData,
            'historyTrend' => $historyTrend,
            'recentSessionLogs' => $recentSessionLogs,
            'departments' => $allDepartments,
            'filters' => [
                'period_type' => $periodType,
                'date' => $refDate->toDateString(),
                'period_label' => $periodLabel,
                'prev_date' => $prevDate,
                'next_date' => $nextDate,
                'search' => $search,
                'trainer_type' => $trainerTypeFilter,
                'department' => $departmentFilter,
            ],
        ]);
    }

    /**
     * Membangun Tren Riwayat Berdasarkan Minggu, Bulan, atau Tahun
     */
    private function buildHistoryTrend(string $periodType, Carbon $refDate, array $trainerIds): array
    {
        $trendData = [];

        if ($periodType === 'year') {
            // Breakdown 12 Bulan dalam Tahun tersebut (Jan - Des)
            $year = $refDate->year;
            for ($m = 1; $m <= 12; $m++) {
                $monthObj = Carbon::createFromDate($year, $m, 1);
                $mStart = $monthObj->copy()->startOfMonth()->toDateString();
                $mEnd = $monthObj->copy()->endOfMonth()->toDateString();

                $planHours = (float) AttendanceSession::query()
                    ->whereBetween('session_date', [$mStart, $mEnd])
                    ->where(fn ($q) => $q->whereIn('trainer_id', $trainerIds)->orWhereHas('course', fn ($cq) => $cq->whereIn('trainer_id', $trainerIds)))
                    ->sum('duration_hours');

                $verifiedHours = (float) AttendanceSession::query()
                    ->whereBetween('session_date', [$mStart, $mEnd])
                    ->where(fn ($q) => $q->whereIn('trainer_id', $trainerIds)->orWhereHas('course', fn ($cq) => $cq->whereIn('trainer_id', $trainerIds)))
                    ->whereHas('attendances', fn ($q) => $q->where('status', 'present'))
                    ->sum('duration_hours');

                $manualHours = (float) DB::table('trainer_teaching_logs')
                    ->whereIn('trainer_id', $trainerIds)
                    ->where('entry_source', 'manual_lnd_entry')
                    ->whereBetween('log_date', [$mStart, $mEnd])
                    ->sum('duration_hours');

                $targetHours = (float) TrainerTaskAndKpi::query()
                    ->whereIn('trainer_id', $trainerIds)
                    ->where('period', 'monthly')
                    ->where(function ($sub) use ($mStart, $mEnd) {
                        $sub->whereBetween('start_date', [$mStart, $mEnd])
                            ->orWhereBetween('due_date', [$mStart, $mEnd]);
                    })
                    ->sum('target_teaching_hours');

                $trendData[] = [
                    'label' => $monthObj->translatedFormat('M'),
                    'full_label' => $monthObj->translatedFormat('F Y'),
                    'date_start' => $mStart,
                    'date_end' => $mEnd,
                    'plan_hours' => round($planHours, 1),
                    'realisasi_hours' => round($verifiedHours + $manualHours, 1),
                    'target_hours' => round($targetHours, 1),
                ];
            }
        } elseif ($periodType === 'month') {
            // Breakdown 4-5 Minggu dalam Bulan tersebut
            $startOfMonth = $refDate->copy()->startOfMonth();
            $endOfMonth = $refDate->copy()->endOfMonth();
            $cursor = $startOfMonth->copy();
            $weekIndex = 1;

            while ($cursor->lte($endOfMonth)) {
                $wStart = $cursor->copy()->startOfWeek();
                $wEnd = $cursor->copy()->endOfWeek();

                // Batasi agar tidak meluber keluar bulan berjalan
                $effectiveStart = $wStart->lt($startOfMonth) ? $startOfMonth->copy() : $wStart;
                $effectiveEnd = $wEnd->gt($endOfMonth) ? $endOfMonth->copy() : $wEnd;

                $eStartStr = $effectiveStart->toDateString();
                $eEndStr = $effectiveEnd->toDateString();

                $planHours = (float) AttendanceSession::query()
                    ->whereBetween('session_date', [$eStartStr, $eEndStr])
                    ->where(fn ($q) => $q->whereIn('trainer_id', $trainerIds)->orWhereHas('course', fn ($cq) => $cq->whereIn('trainer_id', $trainerIds)))
                    ->sum('duration_hours');

                $verifiedHours = (float) AttendanceSession::query()
                    ->whereBetween('session_date', [$eStartStr, $eEndStr])
                    ->where(fn ($q) => $q->whereIn('trainer_id', $trainerIds)->orWhereHas('course', fn ($cq) => $cq->whereIn('trainer_id', $trainerIds)))
                    ->whereHas('attendances', fn ($q) => $q->where('status', 'present'))
                    ->sum('duration_hours');

                $manualHours = (float) DB::table('trainer_teaching_logs')
                    ->whereIn('trainer_id', $trainerIds)
                    ->where('entry_source', 'manual_lnd_entry')
                    ->whereBetween('log_date', [$eStartStr, $eEndStr])
                    ->sum('duration_hours');

                $trendData[] = [
                    'label' => 'W' . $weekIndex,
                    'full_label' => 'Minggu ' . $weekIndex . ' (' . $effectiveStart->format('d/m') . ' - ' . $effectiveEnd->format('d/m') . ')',
                    'date_start' => $eStartStr,
                    'date_end' => $eEndStr,
                    'plan_hours' => round($planHours, 1),
                    'realisasi_hours' => round($verifiedHours + $manualHours, 1),
                    'target_hours' => 0.0,
                ];

                $cursor = $wEnd->copy()->addDay();
                $weekIndex++;
            }
        } else {
            // Breakdown 7 Hari dalam Minggu tersebut (Senin - Minggu)
            $startOfWeek = $refDate->copy()->startOfWeek();
            for ($d = 0; $d < 7; $d++) {
                $dayObj = $startOfWeek->copy()->addDays($d);
                $dayStr = $dayObj->toDateString();

                $planHours = (float) AttendanceSession::query()
                    ->whereDate('session_date', $dayStr)
                    ->where(fn ($q) => $q->whereIn('trainer_id', $trainerIds)->orWhereHas('course', fn ($cq) => $cq->whereIn('trainer_id', $trainerIds)))
                    ->sum('duration_hours');

                $verifiedHours = (float) AttendanceSession::query()
                    ->whereDate('session_date', $dayStr)
                    ->where(fn ($q) => $q->whereIn('trainer_id', $trainerIds)->orWhereHas('course', fn ($cq) => $cq->whereIn('trainer_id', $trainerIds)))
                    ->whereHas('attendances', fn ($q) => $q->where('status', 'present'))
                    ->sum('duration_hours');

                $manualHours = (float) DB::table('trainer_teaching_logs')
                    ->whereIn('trainer_id', $trainerIds)
                    ->where('entry_source', 'manual_lnd_entry')
                    ->whereDate('log_date', $dayStr)
                    ->sum('duration_hours');

                $trendData[] = [
                    'label' => $dayObj->translatedFormat('D'),
                    'full_label' => $dayObj->translatedFormat('l, d M Y'),
                    'date_start' => $dayStr,
                    'date_end' => $dayStr,
                    'plan_hours' => round($planHours, 1),
                    'realisasi_hours' => round($verifiedHours + $manualHours, 1),
                    'target_hours' => 0.0,
                ];
            }
        }

        return $trendData;
    }

    /**
     * ADMIN MENAMBAHKAN / MENGUBAH TARGET TRAINING HOURS PADA MASING-MASING TRAINER
     */
    public function storeTarget(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'trainer_id' => ['required', 'exists:users,id'],
            'period' => ['required', 'string', 'in:weekly,monthly,yearly'],
            'target_teaching_hours' => ['required', 'numeric', 'min:0.5', 'max:500'],
            'target_participants_count' => ['nullable', 'integer', 'min:0'],
            'target_rating' => ['nullable', 'numeric', 'min:1.0', 'max:5.0'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $trainer = User::findOrFail($validated['trainer_id']);

        // Default rentang tanggal jika tidak diisi
        $now = now();
        $startDate = !empty($validated['start_date'])
            ? Carbon::parse($validated['start_date'])
            : match ($validated['period']) {
                'weekly' => $now->copy()->startOfWeek(),
                'yearly' => $now->copy()->startOfYear(),
                default => $now->copy()->startOfMonth(),
            };

        $dueDate = !empty($validated['due_date'])
            ? Carbon::parse($validated['due_date'])
            : match ($validated['period']) {
                'weekly' => $startDate->copy()->endOfWeek(),
                'yearly' => $startDate->copy()->endOfYear(),
                default => $startDate->copy()->endOfMonth(),
            };

        $periodTitle = !empty($validated['title'])
            ? $validated['title']
            : 'Target Jam Mengajar ' . match ($validated['period']) {
                'weekly' => 'Mingguan (' . $startDate->format('d M') . ' - ' . $dueDate->format('d M Y') . ')',
                'yearly' => 'Tahun ' . $startDate->format('Y'),
                default => 'Bulan ' . $startDate->translatedFormat('F Y'),
            };

        // Cari atau perbarui KPI target untuk periode dan rentang waktu ini
        $existingKpi = TrainerTaskAndKpi::query()
            ->where('trainer_id', $trainer->id)
            ->where('period', $validated['period'])
            ->where(function ($q) use ($startDate, $dueDate) {
                $q->whereBetween('start_date', [$startDate->toDateString(), $dueDate->toDateString()])
                  ->orWhereBetween('due_date', [$startDate->toDateString(), $dueDate->toDateString()]);
            })
            ->first();

        if ($existingKpi) {
            $existingKpi->update([
                'title' => $periodTitle,
                'description' => $validated['description'] ?? $existingKpi->description,
                'target_teaching_hours' => (float) $validated['target_teaching_hours'],
                'target_participants_count' => (int) ($validated['target_participants_count'] ?? $existingKpi->target_participants_count),
                'target_rating' => (float) ($validated['target_rating'] ?? $existingKpi->target_rating),
                'start_date' => $startDate->toDateString(),
                'due_date' => $dueDate->toDateString(),
            ]);
        } else {
            TrainerTaskAndKpi::create([
                'trainer_id' => $trainer->id,
                'title' => $periodTitle,
                'description' => $validated['description'] ?? 'Target alokasi jam mengajar instruktur oleh Admin LnD.',
                'period' => $validated['period'],
                'start_date' => $startDate->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'target_teaching_hours' => (float) $validated['target_teaching_hours'],
                'actual_teaching_hours' => 0.00,
                'target_participants_count' => (int) ($validated['target_participants_count'] ?? 0),
                'actual_participants_count' => 0,
                'target_rating' => (float) ($validated['target_rating'] ?? 4.50),
                'actual_rating' => 0.00,
                'status' => 'in_progress',
                'created_by' => $request->user()?->id,
            ]);
        }

        // Sinkronisasi aktual KPI trainer
        app(TrainerKpiActualsService::class)->syncTrainer($trainer->id);

        return back()->with('success', "Target Training Hours sebesar {$validated['target_teaching_hours']} Jam berhasil ditetapkan untuk {$trainer->name}.");
    }

    /**
     * DETAIL PROGRAM & REALISASI VS PLAN TRAINING HOURS PER TRAINER (JSON / MODAL ENDPOINT)
     */
    public function trainerPrograms(Request $request, User $trainer): JsonResponse
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $courses = Course::query()
            ->where('trainer_id', $trainer->id)
            ->orWhereHas('attendanceSessions', fn ($q) => $q->where('trainer_id', $trainer->id))
            ->with([
                'attendanceSessions' => function ($q) use ($startDate, $endDate) {
                    if ($startDate && $endDate) {
                        $q->whereBetween('session_date', [$startDate, $endDate]);
                    }
                    $q->withAttendanceCounts()->orderBy('session_date');
                },
                'skill:id,code,name,category',
            ])
            ->withCount([
                'enrollments as participants_count' => fn ($q) => $q->where('status', 'approved'),
            ])
            ->get()
            ->map(function (Course $course) use ($trainer) {
                $sessions = $course->attendanceSessions->filter(fn ($s) => $s->trainer_id === $trainer->id || $course->trainer_id === $trainer->id);
                $planHours = (float) $sessions->sum('duration_hours');
                $verifiedSessions = $sessions->filter(fn ($s) => (int) $s->present_count > 0);
                $realisasiHours = (float) $verifiedSessions->sum('duration_hours');

                return [
                    'id' => $course->id,
                    'code' => $course->code,
                    'title' => $course->title,
                    'category' => $course->training_category ?? 'General',
                    'status' => $course->status,
                    'start_date' => $course->start_date?->format('d M Y'),
                    'end_date' => $course->end_date?->format('d M Y'),
                    'plan_hours' => round($planHours, 1),
                    'realisasi_hours' => round($realisasiHours, 1),
                    'achievement_pct' => $planHours > 0 ? min(100, round(($realisasiHours / $planHours) * 100, 1)) : 0,
                    'sessions_count' => $sessions->count(),
                    'verified_sessions_count' => $verifiedSessions->count(),
                    'participants_count' => (int) $course->participants_count,
                    'sessions' => $sessions->map(fn ($s) => [
                        'id' => $s->id,
                        'title' => $s->title,
                        'session_date' => $s->session_date?->format('d M Y'),
                        'time_range' => ($s->start_time ? substr((string)$s->start_time, 0, 5) : '08:00') . ' - ' . ($s->end_time ? substr((string)$s->end_time, 0, 5) : '16:00'),
                        'duration_hours' => (float) ($s->duration_hours ?? 2.0),
                        'location' => $s->location ?? '-',
                        'present_count' => (int) $s->present_count,
                        'is_verified' => (int) $s->present_count > 0,
                    ])->values(),
                ];
            });

        return response()->json([
            'trainer' => [
                'id' => $trainer->id,
                'name' => $trainer->name,
                'nik' => $trainer->nik,
                'department' => $trainer->department,
            ],
            'programs' => $courses,
        ]);
    }
}

