<?php

namespace App\Http\Controllers\Participant;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Skill;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ParticipantPortalController extends Controller
{
    public function dashboard(Request $request): Response
    {
        $user = $request->user();

        // Daftar kelas yang diikuti peserta beserta detail progres modulnya (Anti N+1)
        $myEnrollments = Enrollment::query()
            ->where('user_id', $user->id)
            ->with([
                'course:id,code,title,description,trainer_id,start_date,end_date,status,training_source',
                'course.trainer:id,name',
                'course.modules:id,course_id,title,order_index',
                'course.externalTrainingLogistic:course_id,required_documents_schema',
                'moduleProgress:id,enrollment_id,course_module_id,is_completed,completed_at',
            ])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Enrollment $enrollment) {
                $completedMap = [];
                foreach ($enrollment->moduleProgress as $mp) {
                    if ($mp->is_completed) {
                        $completedMap[$mp->course_module_id] = $mp->completed_at?->format('d M Y');
                    }
                }

                $modules = ($enrollment->course?->modules ?? collect())->map(fn ($mod) => [
                    'id' => $mod->id,
                    'title' => $mod->title,
                    'order_index' => $mod->order_index,
                    'is_completed' => isset($completedMap[$mod->id]),
                    'completed_at' => $completedMap[$mod->id] ?? null,
                ]);

                $certExpiresAt = $enrollment->certificate_expires_at;
                $daysUntilExpiry = $certExpiresAt ? now()->startOfDay()->diffInDays($certExpiresAt->startOfDay(), false) : null;

                return [
                    'id' => $enrollment->id,
                    'status' => $enrollment->status,
                    'progress_percentage' => (int) $enrollment->progress_percentage,
                    'submitted_document_names' => array_keys($enrollment->submitted_documents ?? []),
                    'completed_count' => count($completedMap),
                    'total_modules' => $modules->count(),
                    'certificate_number' => $enrollment->certificate_number
                        ?? ((int) $enrollment->progress_percentage >= 100
                            ? sprintf('CERT/PANI-LND/%s/%04d', now()->format('Y'), $enrollment->id)
                            : null),
                    'certificate_issued_at' => $enrollment->certificate_issued_at?->format('d M Y'),
                    'certificate_expires_at' => $certExpiresAt?->format('d M Y'),
                    'is_expiring_soon' => $daysUntilExpiry !== null && $daysUntilExpiry <= 60,
                    'days_until_expiry' => $daysUntilExpiry,
                    'course' => [
                        'id' => $enrollment->course?->id,
                        'code' => $enrollment->course?->code,
                        'title' => $enrollment->course?->title,
                        'description' => $enrollment->course?->description,
                        'trainer_name' => $enrollment->course?->trainer?->name,
                        'start_date' => $enrollment->course?->start_date?->format('d M Y'),
                        'end_date' => $enrollment->course?->end_date?->format('d M Y'),
                        'status' => $enrollment->course?->status,
                        'training_source' => $enrollment->course?->training_source ?? 'internal',
                        'required_documents_schema' => $enrollment->course?->externalTrainingLogistic?->required_documents_schema ?? [],
                    ],
                    'modules' => $modules,
                ];
            });

        $externalDocumentCourses = $myEnrollments
            ->filter(fn (array $enrollment) => $enrollment['status'] === 'approved'
                && $enrollment['course']['training_source'] === 'external'
                && $enrollment['course']['required_documents_schema'] !== [])
            ->map(fn (array $enrollment) => [
                'enrollment_id' => $enrollment['id'],
                'course_code' => $enrollment['course']['code'],
                'course_title' => $enrollment['course']['title'],
                'required_documents_schema' => $enrollment['course']['required_documents_schema'],
                'submitted_document_names' => $enrollment['submitted_document_names'],
            ])
            ->values();

        // Jadwal Mendatang (Sesi kelas yang akan datang / aktif milik kelas peserta)
        $approvedCourseIds = $myEnrollments
            ->where('status', 'approved')
            ->pluck('course.id')
            ->filter()
            ->all();

        $upcomingSchedules = AttendanceSession::query()
            ->whereIn('course_id', $approvedCourseIds)
            ->whereDate('session_date', '>=', now()->toDateString())
            ->with(['course:id,code,title', 'trainer:id,name'])
            ->orderBy('session_date')
            ->orderBy('id')
            ->limit(10)
            ->get()
            ->map(fn (AttendanceSession $s) => [
                'id' => $s->id,
                'title' => $s->title,
                'course_code' => $s->course?->code,
                'course_title' => $s->course?->title,
                'trainer_name' => $s->trainer?->name ?? 'Trainer L&D',
                'session_date' => $s->session_date?->format('d M Y'),
                'duration_hours' => (float) ($s->duration_hours ?? 2.0),
                'is_active' => (bool) $s->is_active,
            ]);

        // Riwayat Pelatihan & Sertifikat (Kelas yang sudah selesai / lulus 100%)
        $trainingHistory = $myEnrollments
            ->filter(fn (array $e) => $e['progress_percentage'] >= 100 || ($e['course']['status'] ?? '') === 'completed')
            ->values();

        // Katalog kelas aktif yang belum diambil peserta
        $enrolledCourseIds = $myEnrollments->pluck('course.id')->filter()->all();
        $availableCourses = Course::query()
            ->active()
            ->whereNotIn('id', $enrolledCourseIds)
            ->with(['trainer:id,name'])
            ->withCount('modules')
            ->get()
            ->map(fn (Course $c) => [
                'id' => $c->id,
                'code' => $c->code,
                'title' => $c->title,
                'trainer_name' => $c->trainer?->name,
                'modules_count' => $c->modules_count,
                'start_date' => $c->start_date?->format('d M Y'),
            ]);

        // Riwayat presensi terbaru milik peserta
        $recentAttendances = Attendance::query()
            ->where('user_id', $user->id)
            ->with([
                'session:id,course_id,title,session_date',
                'session.course:id,code,title',
            ])
            ->orderByDesc('scanned_at')
            ->limit(5)
            ->get()
            ->map(fn (Attendance $att) => [
                'id' => $att->id,
                'status' => $att->status,
                'scanned_at' => $att->scanned_at?->format('d M Y H:i'),
                'session_title' => $att->session?->title,
                'course_code' => $att->session?->course?->code,
            ]);

        // Akumulasi Total Jam Pelatihan Peserta (dari sesi yang dihadiri peserta `status = 'present'`)
        $myTrainingHours = (float) DB::table('attendances as a')
            ->join('attendance_sessions as s', 's.id', '=', 'a.attendance_session_id')
            ->where('a.user_id', $user->id)
            ->where('a.status', 'present')
            ->sum('s.duration_hours');

        return Inertia::render('Participant/Dashboard', [
            'enrollments' => $myEnrollments,
            'upcomingSchedules' => $upcomingSchedules,
            'trainingHistory' => $trainingHistory,
            'availableCourses' => $availableCourses,
            'recentAttendances' => $recentAttendances,
            'externalDocumentCourses' => $externalDocumentCourses,
            'totalTrainingHours' => round($myTrainingHours, 1),
            'certificatePrintUrl' => route('participant.certificate.print', ['user' => $user->id]),
        ]);
    }

    public function enrollCourse(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
        ]);

        DB::transaction(function () use ($request, $validated) {
            Enrollment::firstOrCreate(
                [
                    'user_id' => $request->user()->id,
                    'course_id' => $validated['course_id'],
                ],
                [
                    'status' => 'pending',
                    'progress_percentage' => 0,
                ]
            );
        });

        return back()->with('success', 'Pendaftaran kelas berhasil diajukan dan menunggu persetujuan Admin/Trainer.');
    }

    public function showScanner(Request $request): Response
    {
        $user = $request->user();

        $externalDocumentCourses = Enrollment::query()
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereHas('course.externalTrainingLogistic', fn ($query) => $query->whereNotNull('required_documents_schema'))
            ->with([
                'course:id,code,title',
                'course.externalTrainingLogistic:course_id,required_documents_schema',
            ])
            ->get()
            ->map(fn (Enrollment $enrollment) => [
                'enrollment_id' => $enrollment->id,
                'course_id' => $enrollment->course_id,
                'course_code' => $enrollment->course?->code,
                'course_title' => $enrollment->course?->title,
                'required_documents_schema' => $enrollment->course?->externalTrainingLogistic?->required_documents_schema ?? [],
                'submitted_document_names' => array_keys($enrollment->submitted_documents ?? []),
            ])
            ->filter(fn (array $enrollment) => $enrollment['required_documents_schema'] !== [])
            ->values();

        // Sesi aktif hari ini dari kelas yang diikuti peserta
        $activeSessions = AttendanceSession::query()
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->whereHas('course.enrollments', function ($q) use ($user) {
                $q->where('user_id', $user->id)->where('status', 'approved');
            })
            ->with(['course:id,code,title,trainer_id', 'trainer:id,name', 'course.trainer:id,name'])
            ->withExists([
                'attendances as already_scanned' => fn ($query) => $query->where('user_id', $user->id),
                'evaluations as already_evaluated' => fn ($query) => $query->where('participant_id', $user->id),
            ])
            ->orderBy('expires_at')
            ->get()
            ->map(function (AttendanceSession $s) use ($user) {
                $trainerId = $s->trainer_id ?? $s->course?->trainer_id;
                $trainerName = $s->trainer?->name ?? $s->course?->trainer?->name ?? 'Instruktur LnD';

                return [
                    'id' => $s->id,
                    'title' => $s->title,
                    'course_id' => $s->course_id,
                    'course_code' => $s->course?->code,
                    'course_title' => $s->course?->title,
                    'trainer_id' => $trainerId,
                    'trainer_name' => $trainerName,
                    'expires_at' => $s->expires_at?->format('H:i'),
                    'already_scanned' => (bool) $s->already_scanned,
                    'already_evaluated' => $trainerId ? (bool) $s->already_evaluated : false,
                ];
            });

        return Inertia::render('Participant/ScanAttendance', [
            'activeSessions' => $activeSessions,
            'externalDocumentCourses' => $externalDocumentCourses,
        ]);
    }

    public function submitAttendanceScan(Request $request): RedirectResponse
    {
        return app(\App\Http\Controllers\AttendanceController::class)->scan($request);
    }

    public function profileMatrix(Request $request): Response
    {
        $user = $request->user();

        $allSkills = Skill::query()
            ->orderBy('category')
            ->orderBy('code')
            ->get();

        $userSkillsMap = $user->userSkills()
            ->with('updater:id,name')
            ->get()
            ->keyBy('skill_id');

        $radarSkills = $allSkills->map(function (Skill $skill) use ($userSkillsMap) {
            $us = $userSkillsMap->get($skill->id);
            $current = $us ? (int) $us->current_level : 1;
            $target = $us ? (int) $us->target_level : 3;

            return [
                'skill_id' => $skill->id,
                'code' => $skill->code,
                'name' => $skill->name,
                'category' => $skill->category,
                'current_level' => $current,
                'target_level' => $target,
                'gap' => $current - $target,
                'updated_by_name' => $us?->updater?->name ?? 'System Default',
                'updated_at' => $us?->updated_at?->format('d M Y') ?? '-',
            ];
        });

        $competentCount = $radarSkills->filter(fn ($s) => $s['current_level'] >= $s['target_level'])->count();
        $avgCurrent = round($radarSkills->avg('current_level') ?? 0, 1);
        $avgTarget = round($radarSkills->avg('target_level') ?? 0, 1);

        return Inertia::render('Participant/ProfileMatrix', [
            'skills' => $radarSkills,
            'summary' => [
                'total_skills' => $radarSkills->count(),
                'competent_count' => $competentCount,
                'gap_count' => $radarSkills->count() - $competentCount,
                'avg_current' => $avgCurrent,
                'avg_target' => $avgTarget,
            ],
        ]);
    }
}
