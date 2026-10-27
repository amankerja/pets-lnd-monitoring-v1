<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\ModuleProgress;
use App\Models\User;
use App\Traits\HasTrainingFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EnrollmentProgressController extends Controller
{
    use HasTrainingFilters;

    public function index(Request $request): Response
    {
        $actor = $request->user();
        $validated = $request->validate([
            'session_id' => ['nullable', 'integer', 'exists:attendance_sessions,id'],
        ]);
        $session = !empty($validated['session_id'])
            ? AttendanceSession::query()->with('course:id,code,title')->findOrFail($validated['session_id'])
            : null;

        abort_if(
            $session && $actor->role === 'trainer' && $session->trainer_id !== $actor->id,
            404
        );

        $filters = [
            ...$this->extractTrainingFilters($request),
            'session_id' => $session?->id,
        ];

        $coursesQuery = Course::query()
            ->select([
                'id',
                'code',
                'title',
                'status',
                'is_mandatory',
                'training_source',
                'training_category',
            ])
            ->withCount('enrollments')
            ->with(['modules:id,course_id,title,order_index'])
            ->when($session, fn ($query) => $query->whereKey($session->course_id))
            ->orderBy('code');

        $courses = $coursesQuery->get();

        // Sinkronisasi course_code <-> course_id agar kolom checklist modul tetap terhubung
        if (!empty($filters['course_code']) && empty($filters['course_id'])) {
            $matchedCourse = $courses->firstWhere('code', $filters['course_code']);
            if ($matchedCourse) {
                $filters['course_id'] = $matchedCourse->id;
            }
        } elseif (!empty($filters['course_id']) && empty($filters['course_code'])) {
            $matchedCourse = $courses->firstWhere('id', (int) $filters['course_id']);
            if ($matchedCourse) {
                $filters['course_code'] = $matchedCourse->code;
            }
        }

        if (empty($filters['course_id']) && empty($filters['course_code']) && $courses->isNotEmpty()) {
            $defaultCourse = $courses->first(fn ($course) => $course->enrollments_count > 0) ?? $courses->first();
            $filters['course_id'] = $defaultCourse->id;
            $filters['course_code'] = $defaultCourse->code;
        }

        $enrollmentsQuery = Enrollment::query()
            ->accessibleBy($actor)
            ->when($session, fn ($query) => $query->where('attendance_session_id', $session->id))
            ->with([
                'user:id,name,nik,department,email,organization_id,job_position_id',
                'user.organization:id,code,name,type',
                'user.jobPosition:id,position_title,entity',
                'course:id,code,title,is_mandatory,training_source,training_category',
                'course.modules:id,course_id,title,order_index',
                'registrar:id,name,role',
                'reviewer:id,name,role',
                'attendanceSession:id,course_id,title,session_date,start_time,end_time,location,quota,trainer_id',
                'attendanceSession.trainer:id,name',
                'moduleProgress:id,enrollment_id,course_module_id,is_completed,completed_at',
            ]);

        $this->applyEnrollmentFilters($enrollmentsQuery, $filters, $actor, 'enrollments.created_at');

        // KPI Counts
        $baseKpiQuery = Enrollment::query()->accessibleBy($actor);
        $kpi = [
            'total' => (clone $baseKpiQuery)->count(),
            'pending' => (clone $baseKpiQuery)->where('status', 'pending')->count(),
            'approved' => (clone $baseKpiQuery)->where('status', 'approved')->count(),
            'rejected' => (clone $baseKpiQuery)->where('status', 'rejected')->count(),
            'linked_to_session' => (clone $baseKpiQuery)->whereNotNull('attendance_session_id')->count(),
        ];

        // Active Running Training Sessions for Calendar Connection
        $activeRunningSessions = AttendanceSession::query()
            ->with(['course:id,code,title', 'trainer:id,name'])
            ->withCount(['enrollments as enrolled_count' => fn ($q) => $q->whereIn('status', ['pending', 'approved'])])
            ->where('session_date', '>=', now()->toDateString())
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->get()
            ->map(fn (AttendanceSession $s) => [
                'id' => $s->id,
                'course_id' => $s->course_id,
                'course_code' => $s->course?->code ?? '-',
                'course_title' => $s->course?->title ?? '-',
                'title' => $s->title,
                'session_date' => $s->session_date?->format('d M Y') ?? '-',
                'raw_session_date' => $s->session_date?->format('Y-m-d'),
                'start_time' => $s->start_time ?? '-',
                'end_time' => $s->end_time ?? '-',
                'location' => $s->location ?? '-',
                'quota' => $s->quota,
                'enrolled_count' => (int) $s->enrolled_count,
                'remaining_quota' => $s->quota !== null ? max(0, $s->quota - $s->enrolled_count) : 999,
                'trainer_name' => $s->trainer?->name ?? 'TBA',
            ]);

        // Scoped workers for bulk registration form
        $scopedWorkers = User::query()
            ->role('peserta')
            ->accessibleBy($actor)
            ->with(['organization:id,name,type', 'jobPosition:id,position_title,entity'])
            ->select(['id', 'name', 'nik', 'department', 'organization_id', 'job_position_id'])
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'nik' => $u->nik,
                'department' => $u->department ?? '-',
                'position' => $u->jobPosition?->position_title ?? '-',
                'entity' => $u->jobPosition?->entity ?? ($u->organization?->name ?? '-'),
                'organization_type' => $u->organization?->type ?? 'internal_dept',
            ]);

        $enrollments = $enrollmentsQuery
            ->orderByRaw("CASE WHEN status = 'pending' THEN 1 WHEN status = 'approved' THEN 2 ELSE 3 END")
            ->orderByDesc('updated_at')
            ->paginate(25)
            ->withQueryString()
            ->through(function (Enrollment $enrollment) {
                $completedMap = [];
                foreach ($enrollment->moduleProgress as $mp) {
                    $completedMap[$mp->course_module_id] = (bool) $mp->is_completed;
                }

                return [
                    'id' => $enrollment->id,
                    'user_id' => $enrollment->user_id,
                    'course_id' => $enrollment->course_id,
                    'attendance_session_id' => $enrollment->attendance_session_id,
                    'status' => $enrollment->status,
                    'rejection_reason' => $enrollment->rejection_reason,
                    'reviewed_by' => $enrollment->reviewer?->name,
                    'reviewed_at' => $enrollment->reviewed_at?->format('d M Y H:i'),
                    'progress_percentage' => (int) $enrollment->progress_percentage,
                    'certificate_number' => $enrollment->certificate_number,
                    'created_at' => $enrollment->created_at?->format('d M Y'),
                    'user' => [
                        'id' => $enrollment->user?->id,
                        'name' => $enrollment->user?->name,
                        'nik' => $enrollment->user?->nik,
                        'department' => $enrollment->user?->department,
                        'organization_name' => $enrollment->user?->organization?->name,
                        'position' => $enrollment->user?->jobPosition?->position_title,
                        'entity' => $enrollment->user?->jobPosition?->entity,
                        'organization_type' => $enrollment->user?->organization?->type ?? 'internal_dept',
                    ],
                    'registrar' => [
                        'name' => $enrollment->registrar?->name ?? '-',
                        'role' => $enrollment->registrar?->role ?? '-',
                    ],
                    'attendance_session' => $enrollment->attendanceSession ? [
                        'id' => $enrollment->attendanceSession->id,
                        'title' => $enrollment->attendanceSession->title,
                        'session_date' => $enrollment->attendanceSession->session_date?->format('d M Y'),
                        'start_time' => $enrollment->attendanceSession->start_time,
                        'end_time' => $enrollment->attendanceSession->end_time,
                        'location' => $enrollment->attendanceSession->location,
                        'trainer_name' => $enrollment->attendanceSession->trainer?->name ?? 'TBA',
                    ] : null,
                    'course' => [
                        'id' => $enrollment->course?->id,
                        'code' => $enrollment->course?->code,
                        'title' => $enrollment->course?->title,
                        'is_mandatory' => (bool) ($enrollment->course?->is_mandatory ?? true),
                        'training_source' => $enrollment->course?->training_source ?? 'internal',
                        'training_category' => $enrollment->course?->training_category ?? 'Safety',
                        'modules' => $enrollment->course?->modules ?? [],
                    ],
                    'submitted_document_names' => array_keys($enrollment->submitted_documents ?? []),
                    'completed_modules' => $completedMap,
                ];
            });

        return Inertia::render('Admin/EnrollmentAndProgress', [
            'courses' => $courses,
            'enrollments' => $enrollments,
            'filters' => $filters,
            'kpi' => $kpi,
            'activeRunningSessions' => $activeRunningSessions,
            'scopedWorkers' => $scopedWorkers,
            'selectedSession' => $session ? [
                'id' => $session->id,
                'title' => $session->title,
                'session_date' => $session->session_date?->format('Y-m-d'),
                'course' => $session->course,
            ] : null,
            'filterOptions' => $this->buildTrainingFilterOptions($actor),
        ]);
    }

    public function updateStatus(Request $request, Enrollment $enrollment): RedirectResponse
    {
        abort_if(
            !in_array($request->user()->role, ['lnd', 'admin'], true),
            403,
            'Hanya Tim LnD yang dapat menyetujui atau menolak pendaftaran training.'
        );

        $validated = $request->validate([
            'status' => ['required', 'in:pending,approved,rejected'],
            'rejection_reason' => ['nullable', 'required_if:status,rejected', 'string', 'max:500'],
            'attendance_session_id' => ['nullable', 'integer', 'exists:attendance_sessions,id'],
        ]);

        $actor = $request->user();

        DB::transaction(function () use ($enrollment, $validated, $actor) {
            $session = null;
            if (!empty($validated['attendance_session_id'])) {
                $session = AttendanceSession::query()
                    ->whereKey($validated['attendance_session_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if ((int) $session->course_id !== (int) $enrollment->course_id) {
                    throw ValidationException::withMessages([
                        'attendance_session_id' => 'Sesi yang dipilih tidak sesuai dengan program pelatihan pendaftaran ini.',
                    ]);
                }

                $alreadyEnrolledCount = Enrollment::query()
                    ->where('attendance_session_id', $session->id)
                    ->whereIn('status', ['pending', 'approved'])
                    ->where('id', '!=', $enrollment->id)
                    ->count();

                if ($session->quota !== null && $alreadyEnrolledCount >= $session->quota) {
                    throw ValidationException::withMessages([
                        'attendance_session_id' => "Kuota sesi berjalan ({$session->quota}) telah penuh.",
                    ]);
                }
            }

            $updateData = [
                'status' => $validated['status'],
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ];

            if ($validated['status'] === 'rejected') {
                $updateData['rejection_reason'] = $validated['rejection_reason'] ?? 'Tidak memenuhi syarat administrasi.';
                $updateData['attendance_session_id'] = null;
            } elseif ($validated['status'] === 'approved') {
                $updateData['rejection_reason'] = null;
                if ($session) {
                    $updateData['attendance_session_id'] = $session->id;
                }
            }

            $enrollment->update($updateData);

            // Jika disetujui, pastikan record module_progress terinisialisasi untuk setiap modul kelas
            if ($validated['status'] === 'approved') {
                $moduleIds = DB::table('course_modules')
                    ->where('course_id', $enrollment->course_id)
                    ->pluck('id');

                foreach ($moduleIds as $moduleId) {
                    ModuleProgress::firstOrCreate(
                        [
                            'enrollment_id' => $enrollment->id,
                            'course_module_id' => $moduleId,
                        ],
                        [
                            'is_completed' => false,
                            'completed_at' => null,
                        ]
                    );
                }

                $enrollment->recalculateProgress();
            }
        });

        $msg = $validated['status'] === 'approved'
            ? 'Pendaftaran peserta berhasil disetujui' . (!empty($validated['attendance_session_id']) ? ' dan dihubungkan ke sesi program berjalan.' : '.')
            : 'Pendaftaran peserta berhasil ditolak dengan alasan tercatat.';

        return back()->with('success', $msg);
    }

    public function linkSession(Request $request, Enrollment $enrollment): RedirectResponse
    {
        abort_if(
            !in_array($request->user()->role, ['lnd', 'admin'], true),
            403,
            'Hanya Tim LnD yang berwenang menghubungkan peserta ke sesi program berjalan.'
        );

        $validated = $request->validate([
            'attendance_session_id' => ['required', 'integer', 'exists:attendance_sessions,id'],
        ]);

        $actor = $request->user();

        DB::transaction(function () use ($enrollment, $validated, $actor) {
            $session = AttendanceSession::query()
                ->whereKey($validated['attendance_session_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $session->course_id !== (int) $enrollment->course_id) {
                throw ValidationException::withMessages([
                    'attendance_session_id' => 'Sesi yang dipilih tidak cocok dengan materi training peserta.',
                ]);
            }

            $alreadyCount = Enrollment::query()
                ->where('attendance_session_id', $session->id)
                ->whereIn('status', ['pending', 'approved'])
                ->where('id', '!=', $enrollment->id)
                ->count();

            if ($session->quota !== null && $alreadyCount >= $session->quota) {
                throw ValidationException::withMessages([
                    'attendance_session_id' => "Kuota sesi ({$session->quota}) telah penuh.",
                ]);
            }

            $enrollment->update([
                'attendance_session_id' => $session->id,
                'status' => 'approved',
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ]);

            // Inisialisasi module progress jika belum ada
            $moduleIds = DB::table('course_modules')
                ->where('course_id', $enrollment->course_id)
                ->pluck('id');

            foreach ($moduleIds as $moduleId) {
                ModuleProgress::firstOrCreate(
                    [
                        'enrollment_id' => $enrollment->id,
                        'course_module_id' => $moduleId,
                    ],
                    [
                        'is_completed' => false,
                        'completed_at' => null,
                    ]
                );
            }
            $enrollment->recalculateProgress();
        });

        return back()->with('success', 'Peserta berhasil dihubungkan ke sesi program berjalan.');
    }

    public function bulkUpdateStatus(Request $request): RedirectResponse
    {
        abort_if(
            !in_array($request->user()->role, ['lnd', 'admin'], true),
            403,
            'Hanya Tim LnD yang dapat melakukan persetujuan/penolakan massal.'
        );

        $validated = $request->validate([
            'enrollment_ids' => ['required', 'array', 'min:1'],
            'enrollment_ids.*' => ['required', 'integer', 'exists:enrollments,id'],
            'action' => ['required', 'in:approve,reject,link_session'],
            'rejection_reason' => ['nullable', 'required_if:action,reject', 'string', 'max:500'],
            'attendance_session_id' => ['nullable', 'required_if:action,link_session', 'integer', 'exists:attendance_sessions,id'],
        ]);

        $actor = $request->user();
        $action = $validated['action'];
        $session = !empty($validated['attendance_session_id'])
            ? AttendanceSession::query()->findOrFail($validated['attendance_session_id'])
            : null;

        $count = DB::transaction(function () use ($validated, $action, $session, $actor) {
            $enrollments = Enrollment::query()
                ->whereIn('id', $validated['enrollment_ids'])
                ->get();

            $processed = 0;
            foreach ($enrollments as $enr) {
                if ($action === 'reject') {
                    $enr->update([
                        'status' => 'rejected',
                        'rejection_reason' => $validated['rejection_reason'],
                        'attendance_session_id' => null,
                        'reviewed_by' => $actor->id,
                        'reviewed_at' => now(),
                    ]);
                    $processed++;
                } elseif ($action === 'approve') {
                    $enr->update([
                        'status' => 'approved',
                        'rejection_reason' => null,
                        'reviewed_by' => $actor->id,
                        'reviewed_at' => now(),
                    ]);
                    // Modul inisialisasi
                    $moduleIds = DB::table('course_modules')->where('course_id', $enr->course_id)->pluck('id');
                    foreach ($moduleIds as $mId) {
                        ModuleProgress::firstOrCreate(['enrollment_id' => $enr->id, 'course_module_id' => $mId], ['is_completed' => false]);
                    }
                    $enr->recalculateProgress();
                    $processed++;
                } elseif ($action === 'link_session' && $session) {
                    if ((int) $session->course_id === (int) $enr->course_id) {
                        $enr->update([
                            'attendance_session_id' => $session->id,
                            'status' => 'approved',
                            'rejection_reason' => null,
                            'reviewed_by' => $actor->id,
                            'reviewed_at' => now(),
                        ]);
                        $moduleIds = DB::table('course_modules')->where('course_id', $enr->course_id)->pluck('id');
                        foreach ($moduleIds as $mId) {
                            ModuleProgress::firstOrCreate(['enrollment_id' => $enr->id, 'course_module_id' => $mId], ['is_completed' => false]);
                        }
                        $enr->recalculateProgress();
                        $processed++;
                    }
                }
            }
            return $processed;
        });

        return back()->with('success', "Aksi massal berhasil diproses untuk {$count} pendaftaran.");
    }

    public function bulkEnroll(Request $request): RedirectResponse
    {
        $actor = $request->user();

        $validated = $request->validate([
            'course_id' => ['nullable', 'integer', 'exists:courses,id', 'required_without:course_ids'],
            'course_ids' => ['nullable', 'array', 'min:1', 'required_without:course_id'],
            'course_ids.*' => ['required', 'integer', 'distinct', 'exists:courses,id'],
            'participant_ids' => ['required', 'array', 'min:1'],
            'participant_ids.*' => ['required', 'integer', 'exists:users,id'],
            'attendance_session_id' => ['nullable', 'integer', 'exists:attendance_sessions,id'],
        ]);

        $courseIds = collect($validated['course_ids'] ?? [])
            ->when(isset($validated['course_id']), fn ($ids) => $ids->push($validated['course_id']))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $allowedWorkerIds = User::query()
            ->role('peserta')
            ->accessibleBy($actor)
            ->whereIn('id', $validated['participant_ids'])
            ->pluck('id')
            ->all();

        if (count($allowedWorkerIds) !== count(array_unique($validated['participant_ids']))) {
            throw ValidationException::withMessages([
                'participant_ids' => 'Akses ditolak: Terdapat pekerja yang berada di luar cakupan organisasi/departemen Anda.',
            ]);
        }

        $session = !empty($validated['attendance_session_id'])
            ? AttendanceSession::query()->findOrFail($validated['attendance_session_id'])
            : null;

        $initialStatus = in_array($actor->role, ['lnd', 'admin'], true) ? 'approved' : 'pending';

        $enrolledCount = DB::transaction(function () use ($allowedWorkerIds, $courseIds, $session, $actor, $initialStatus) {
            $count = 0;
            foreach ($courseIds as $courseId) {
                // Sesi hanya di-bind jika cocok dengan course_id
                $targetSessionId = ($session && (int) $session->course_id === (int) $courseId) ? $session->id : null;

                foreach ($allowedWorkerIds as $workerId) {
                    $enrollment = Enrollment::firstOrNew([
                        'user_id' => $workerId,
                        'course_id' => $courseId,
                    ]);

                    if (!$enrollment->exists || $enrollment->status === 'rejected') {
                        $enrollment->fill([
                            'registered_by' => $actor->id,
                            'attendance_session_id' => $targetSessionId,
                            'status' => $initialStatus,
                            'rejection_reason' => null,
                            'progress_percentage' => 0,
                        ]);
                        $enrollment->save();

                        if ($initialStatus === 'approved') {
                            $moduleIds = DB::table('course_modules')->where('course_id', $courseId)->pluck('id');
                            foreach ($moduleIds as $mId) {
                                ModuleProgress::firstOrCreate(['enrollment_id' => $enrollment->id, 'course_module_id' => $mId], ['is_completed' => false]);
                            }
                        }
                        $count++;
                    }
                }
            }

            return $count;
        });

        $msg = sprintf(
            '%d pendaftaran berhasil diajukan (%d pekerja & %d program training)%s.',
            $enrolledCount,
            count($allowedWorkerIds),
            $courseIds->count(),
            $initialStatus === 'pending' ? ' dan menunggu review Tim L&D' : ' dan langsung disetujui'
        );

        return back()->with('success', $msg);
    }

    public function toggleModule(Request $request, Enrollment $enrollment): RedirectResponse
    {
        $validated = $request->validate([
            'course_module_id' => ['required', 'integer', 'exists:course_modules,id'],
            'is_completed' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($enrollment, $validated) {
            // Kunci baris enrollment untuk mencegah race condition saat kalkulasi persentase
            $lockedEnrollment = Enrollment::query()
                ->whereKey($enrollment->id)
                ->lockForUpdate()
                ->firstOrFail();

            ModuleProgress::updateOrCreate(
                [
                    'enrollment_id' => $lockedEnrollment->id,
                    'course_module_id' => $validated['course_module_id'],
                ],
                [
                    'is_completed' => $validated['is_completed'],
                    'completed_at' => $validated['is_completed'] ? now() : null,
                ]
            );

            // Hitung ulang progress_percentage otomatis
            $lockedEnrollment->recalculateProgress();
        });

        return back()->with('success', 'Progres penyelesaian modul peserta berhasil diperbarui.');
    }
}
