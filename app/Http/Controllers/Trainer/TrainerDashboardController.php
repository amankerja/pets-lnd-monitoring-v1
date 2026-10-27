<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\TrainerCompetency;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TrainerDashboardController extends Controller
{
    /**
     * BAGIAN 3 - Poin 2: Pencatatan & Akumulasi Otomatis Teaching Hours Trainer.
     * Mengakumulasi total jam mengajar (`duration_hours`) HANYA dari sesi absensi yang
     * sudah terlaksana dan memiliki peserta hadir (`status = 'present'`), dilengkapi
     * rekapitulasi bulanan (`GROUP BY YEAR, MONTH`).
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Jika Super Admin LnD membuka halaman ini, izinkan filter per trainer_id
        $targetTrainerId = in_array($user->role, ['lnd', 'admin'], true) && $request->filled('trainer_id')
            ? $request->integer('trainer_id')
            : $user->id;

        // 1. Akumulasi Total Jam Mengajar Sah (Query Agregasi Langsung di Database)
        // Hanya menghitung sesi yang sudah terlaksana (session_date <= today) DAN memiliki peserta hadir (status = 'present')
        $verifiedTeachingHours = (float) AttendanceSession::query()
            ->where(function ($q) use ($targetTrainerId) {
                $q->where('trainer_id', $targetTrainerId)
                    ->orWhereHas('course', fn ($cq) => $cq->where('trainer_id', $targetTrainerId));
            })
            ->whereDate('session_date', '<=', now()->toDateString())
            ->whereHas('attendances', fn ($q) => $q->where('status', 'present'))
            ->sum('duration_hours');

        // 2. Total Jam Mengajar Terjadwal (Termasuk sesi mendatang / belum ada peserta hadir)
        $scheduledTeachingHours = (float) AttendanceSession::query()
            ->where(function ($q) use ($targetTrainerId) {
                $q->where('trainer_id', $targetTrainerId)
                    ->orWhereHas('course', fn ($cq) => $cq->where('trainer_id', $targetTrainerId));
            })
            ->sum('duration_hours');

        // 3. Rekapitulasi Time-Series Bulanan Teaching Hours Trainer (GROUP BY YEAR, MONTH)
        $isSqlite = DB::getDriverName() === 'sqlite';
        $yearExpr = $isSqlite ? "strftime('%Y', s.session_date)" : "YEAR(s.session_date)";
        $monthExpr = $isSqlite ? "CAST(strftime('%m', s.session_date) AS INTEGER)" : "MONTH(s.session_date)";
        $labelExpr = $isSqlite ? "strftime('%m/%Y', s.session_date)" : "DATE_FORMAT(s.session_date, '%b %Y')";

        $monthlyTeachingHours = DB::table('attendance_sessions as s')
            ->join('courses as c', 'c.id', '=', 's.course_id')
            ->where(function ($q) use ($targetTrainerId) {
                $q->where('s.trainer_id', '=', $targetTrainerId)
                    ->orWhere('c.trainer_id', '=', $targetTrainerId);
            })
            ->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('attendances as a')
                    ->whereColumn('a.attendance_session_id', 's.id')
                    ->where('a.status', 'present');
            })
            ->selectRaw("
                {$yearExpr} as year,
                {$monthExpr} as month,
                {$labelExpr} as period_label,
                COUNT(s.id) as verified_sessions_count,
                ROUND(SUM(s.duration_hours), 1) as total_hours
            ")
            ->groupByRaw("{$yearExpr}, {$monthExpr}, {$labelExpr}")
            ->orderByRaw("{$yearExpr} ASC, {$monthExpr} ASC")
            ->limit(12)
            ->get();

        // 4. Daftar Sesi Mengajar beserta rincian jumlah kehadiran peserta
        $sessions = AttendanceSession::query()
            ->with(['course:id,code,title,trainer_id'])
            ->withAttendanceCounts()
            ->where(function ($q) use ($targetTrainerId) {
                $q->where('trainer_id', $targetTrainerId)
                    ->orWhereHas('course', fn ($cq) => $cq->where('trainer_id', $targetTrainerId));
            })
            ->orderByDesc('session_date')
            ->orderByDesc('id')
            ->get();

        // 5. Kelas yang ditugaskan kepada Trainer
        $assignedCourses = Course::query()
            ->where('trainer_id', $targetTrainerId)
            ->withCount([
                'enrollments as approved_participants_count' => fn ($q) => $q->where('status', 'approved'),
                'modules as modules_count',
            ])
            ->orderByDesc('start_date')
            ->get();

        // 6. Portofolio Lisensi & Kompetensi Mengajar Trainer
        $competencies = TrainerCompetency::query()
            ->where('trainer_id', $targetTrainerId)
            ->with(['skill:id,code,name,category'])
            ->orderBy('valid_until')
            ->get();

        $trainersList = in_array($user->role, ['lnd', 'admin'], true)
            ? User::query()->where('role', 'trainer')->select(['id', 'name', 'nik', 'department'])->get()
            : [];

        // Data Chart untuk SvgBarChart di halaman Trainer
        $hoursByCourseChart = $sessions
            ->filter(fn ($s) => ((int) $s->present_count + (int) $s->permit_count + (int) $s->absent_count) > 0)
            ->take(8)
            ->map(function ($s) {
                $tot = (int) $s->present_count + (int) $s->permit_count + (int) $s->absent_count;
                return [
                    'id' => $s->id,
                    'session_title' => $s->title,
                    'short_date' => $s->session_date?->format('d/m') ?? '-',
                    'course_code' => $s->course?->code ?? 'SESI',
                    'present_count' => (int) $s->present_count,
                    'permit_count' => (int) $s->permit_count,
                    'absent_count' => (int) $s->absent_count,
                    'present_rate' => $tot > 0 ? round(($s->present_count / $tot) * 100, 1) : 0,
                ];
            })
            ->values();

        $completedSessionsCount = $sessions->filter(fn ($s) => (int) $s->present_count > 0)->count();

        return Inertia::render('Trainer/MyScheduleAndHours', [
            'summary' => [
                'teaching_hours' => round($verifiedTeachingHours, 1),
                'planned_hours' => round($scheduledTeachingHours, 1),
                'verified_teaching_hours' => round($verifiedTeachingHours, 1),
                'scheduled_teaching_hours' => round($scheduledTeachingHours, 1),
                'completed_sessions_count' => $completedSessionsCount,
                'verified_sessions_count' => $completedSessionsCount,
                'total_sessions_count' => $sessions->count(),
                'assigned_courses_count' => $assignedCourses->count(),
                'active_competencies_count' => $competencies->count(),
                'competencies_count' => $competencies->count(),
            ],
            'hoursByCourseChart' => $hoursByCourseChart,
            'monthlyTeachingHours' => $monthlyTeachingHours,
            'sessions' => $sessions->map(fn (AttendanceSession $s) => [
                'id' => $s->id,
                'course_id' => $s->course_id,
                'course_code' => $s->course?->code,
                'course_title' => $s->course?->title,
                'title' => $s->title,
                'session_date' => $s->session_date?->format('d M Y'),
                'duration_hours' => (float) ($s->duration_hours ?: 2.0),
                'present_count' => (int) $s->present_count,
                'permit_count' => (int) $s->permit_count,
                'absent_count' => (int) $s->absent_count,
                'is_counted_in_ledger' => (int) $s->present_count > 0,
                'is_active' => (bool) $s->is_active,
            ]),
            'assignedCourses' => $assignedCourses,
            'competencies' => $competencies,
            'trainersList' => $trainersList,
            'selectedTrainerId' => $targetTrainerId,
        ]);
    }
}

