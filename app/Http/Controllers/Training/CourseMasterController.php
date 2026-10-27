<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Skill;
use App\Models\User;
use App\Services\TrainerScheduleSyncService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CourseMasterController extends Controller
{
    public function __construct(
        protected TrainerScheduleSyncService $syncService
    ) {}

    /**
     * TUGAS UTAMA: Program & Topik Pelatihan (Training Topics / Courses Master).
     * Fokus pada pendefinisian Topik / Program yang akan dibuat,
     * serta berkolaborasi langsung dengan:
     * 1. Competency List (Skill acuan & level target)
     * 2. Jam Ajar & Modul (Total durasi pembelajaran / JP)
     * 3. Pengajar / Trainers (Instruktur pengampu & jadwal roster)
     * 4. Peserta (Kapasitas kuota, pendaftaran & tingkat kelulusan)
     * 5. Jadwal & Kalender (Sinkronisasi ke kalender ATMP, Bulanan, Mingguan)
     */
    public function index(Request $request): Response
    {
        $actor = $request->user();
        $search = trim((string) $request->input('search', ''));
        $category = trim((string) $request->input('category', ''));
        $mandatoryType = trim((string) $request->input('mandatory_type', ''));
        $trainingSource = trim((string) $request->input('training_source', ''));
        $trainerId = $request->filled('trainer_id') ? (int) $request->input('trainer_id') : null;
        $scheduleStatus = trim((string) $request->input('schedule_status', '')); // 'scheduled' | 'unscheduled'

        $baseQuery = Course::query()
            ->when($search !== '', function (Builder $q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('code', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%")
                        ->orWhere('training_category', 'like', "%{$search}%")
                        ->orWhereHas('skill', function ($sq) use ($search) {
                            $sq->where('code', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('trainer', function ($tq) use ($search) {
                            $tq->where('name', 'like', "%{$search}%")
                                ->orWhere('nik', 'like', "%{$search}%");
                        });
                });
            })
            ->when($category !== '', fn ($q) => $q->where('training_category', $category))
            ->when($mandatoryType === 'mandatory', fn ($q) => $q->where('is_mandatory', true))
            ->when($mandatoryType === 'non_mandatory', fn ($q) => $q->where('is_mandatory', false))
            ->when(in_array($trainingSource, ['internal', 'external'], true), fn ($q) => $q->where('training_source', $trainingSource))
            ->when($trainerId, fn ($q) => $q->where('trainer_id', $trainerId))
            ->when($scheduleStatus === 'scheduled', fn ($q) => $q->has('attendanceSessions'))
            ->when($scheduleStatus === 'unscheduled', fn ($q) => $q->doesntHave('attendanceSessions'));

        // KPI Ringkasan Eksekutif Topik Pelatihan
        $totalCourses = Course::count();
        $scheduledCount = Course::has('attendanceSessions')->count();
        $mandatoryCount = Course::where('is_mandatory', true)->count();
        $internalCount = Course::where('training_source', 'internal')->count();
        $externalCount = Course::where('training_source', 'external')->count();
        $totalSessionsCount = AttendanceSession::count();

        $courses = (clone $baseQuery)
            ->with([
                'skill:id,code,name,category,competency_type,description',
                'trainer:id,name,nik,department,trainer_type',
                'attendanceSessions' => function ($q) {
                    $q->orderBy('session_date')
                        ->orderBy('start_time')
                        ->select([
                            'id', 'course_id', 'trainer_id', 'title', 'session_date',
                            'start_time', 'end_time', 'duration_hours', 'location',
                            'quota', 'plan_status', 'is_active',
                        ]);
                },
                'modules:id,course_id,title,order_index',
            ])
            ->withCount([
                'enrollments as total_enrolled_count',
                'enrollments as approved_enrolled_count' => fn ($q) => $q->where('status', 'approved'),
                'enrollments as passed_enrolled_count' => fn ($q) => $q->where('completion_status', 'passed'),
                'attendanceSessions as sessions_count',
            ])
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(function (Course $c) {
                $skill = $c->skill;
                $trainer = $c->trainer;
                $nextSession = $c->attendanceSessions->first(fn ($s) => $s->session_date && $s->session_date->gte(now()->startOfDay()));
                $calculatedHours = (float) $c->attendanceSessions->sum('duration_hours');
                if ($calculatedHours <= 0) {
                    // Default fallback jika belum ada sesi: 8 Jam Ajar (2 sesi x 4 Jam atau 4 modul x 2 Jam)
                    $calculatedHours = 8.0;
                }

                return [
                    'id' => $c->id,
                    'code' => $c->code,
                    'title' => $c->title,
                    'description' => $c->description,
                    'training_category' => $c->training_category,
                    'is_mandatory' => (bool) $c->is_mandatory,
                    'training_source' => $c->training_source,
                    'status' => $c->status,
                    'validity_months' => $c->validity_months,
                    'start_date' => $c->start_date?->format('Y-m-d'),
                    'end_date' => $c->end_date?->format('Y-m-d'),
                    // Jam Ajar & Silabus Modul
                    'duration_hours' => $calculatedHours,
                    'modules' => $c->modules->map(fn ($m) => [
                        'id' => $m->id,
                        'order_index' => $m->order_index,
                        'title' => $m->title,
                    ])->values(),
                    // Relasi 1: Kolaborasi Competency List
                    'skill_id' => $c->skill_id,
                    'competency' => $skill ? [
                        'id' => $skill->id,
                        'code' => $skill->code,
                        'name' => $skill->name,
                        'category' => $skill->category,
                        'competency_type' => $skill->competency_type,
                    ] : null,
                    'granted_skill_level' => (int) ($c->granted_skill_level ?? 3),
                    // Relasi 2: Kolaborasi Trainers
                    'trainer_id' => $c->trainer_id,
                    'trainer' => $trainer ? [
                        'id' => $trainer->id,
                        'name' => $trainer->name,
                        'nik' => $trainer->nik,
                        'department' => $trainer->department,
                        'trainer_type' => $trainer->trainer_type,
                    ] : null,
                    // Relasi 3: Kolaborasi Peserta
                    'total_enrolled' => (int) $c->total_enrolled_count,
                    'approved_enrolled' => (int) $c->approved_enrolled_count,
                    'passed_enrolled' => (int) $c->passed_enrolled_count,
                    // Relasi 4: Kolaborasi Jadwal & Kalender
                    'sessions_count' => (int) $c->sessions_count,
                    'next_session' => $nextSession ? [
                        'id' => $nextSession->id,
                        'title' => $nextSession->title,
                        'date' => $nextSession->session_date?->format('Y-m-d'),
                        'start_time' => $nextSession->start_time ? substr((string) $nextSession->start_time, 0, 5) : null,
                        'end_time' => $nextSession->end_time ? substr((string) $nextSession->end_time, 0, 5) : null,
                        'duration_hours' => (float) ($nextSession->duration_hours ?: 4.0),
                        'location' => $nextSession->location,
                        'quota' => $nextSession->quota,
                        'plan_status' => $nextSession->plan_status,
                    ] : null,
                    'sessions' => $c->attendanceSessions->map(fn ($s) => [
                        'id' => $s->id,
                        'title' => $s->title,
                        'date' => $s->session_date?->format('Y-m-d'),
                        'start_time' => $s->start_time ? substr((string) $s->start_time, 0, 5) : null,
                        'end_time' => $s->end_time ? substr((string) $s->end_time, 0, 5) : null,
                        'duration_hours' => (float) ($s->duration_hours ?: 4.0),
                        'location' => $s->location,
                        'quota' => $s->quota,
                        'plan_status' => $s->plan_status,
                        'is_active' => (bool) $s->is_active,
                    ])->values(),
                ];
            });

        // Dropdown data master kompetensi & trainer
        $competencies = Skill::query()
            ->orderBy('category')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'category', 'competency_type']);

        $trainers = User::query()
            ->where(fn (Builder $query) => $query
                ->whereIn('role', ['trainer', 'lnd', 'admin'])
                ->orWhere('is_trainer', true))
            ->orderBy('name')
            ->get(['id', 'name', 'nik', 'department', 'trainer_type']);

        return Inertia::render('Training/CourseTopics', [
            'courses' => $courses,
            'kpi' => [
                'total_courses' => $totalCourses,
                'scheduled_courses' => $scheduledCount,
                'mandatory_courses' => $mandatoryCount,
                'internal_courses' => $internalCount,
                'external_courses' => $externalCount,
                'total_sessions' => $totalSessionsCount,
            ],
            'filters' => [
                'search' => $search,
                'category' => $category,
                'mandatory_type' => $mandatoryType,
                'training_source' => $trainingSource,
                'trainer_id' => $trainerId,
                'schedule_status' => $scheduleStatus,
            ],
            'competencies' => $competencies,
            'trainers' => $trainers,
            'categories' => [
                'Safety',
                'Operation',
                'Plant',
                'Pengawas',
                'Engineering',
                'Environment',
                'Leadership',
                'General',
            ],
            'canManage' => in_array($actor->role, ['lnd', 'admin'], true),
        ]);
    }

    /**
     * Simpan Program / Topik Training Baru dengan Relasi Wajib ke Master Competency.
     * Arsitektur: "One Course grants One Competency at a Specific Level".
     */
    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();
        abort_if(
            !in_array($actor->role, ['lnd', 'admin'], true),
            403,
            'Hanya Tim LnD yang berwenang membuat program pelatihan baru.'
        );

        $request->merge([
            'code' => strtoupper(trim((string) $request->input('code', ''))),
        ]);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('courses', 'code')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'trainer_id' => ['nullable', 'integer', 'exists:users,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_mandatory' => ['required', 'boolean'],
            'training_source' => ['required', Rule::in(['internal', 'external'])],
            'training_category' => ['required', 'string', 'max:60'],
            'validity_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'skill_id' => ['required', 'integer', 'exists:skills,id'],
            'granted_skill_level' => ['required', 'integer', 'min:1', 'max:5'],
            // Opsi Penjadwalan Langsung ke Kalender
            'create_schedule' => ['nullable', 'boolean'],
            'session_title' => ['nullable', 'string', 'max:150'],
            'session_date' => ['nullable', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'location' => ['nullable', 'string', 'max:120'],
            'quota' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'duration_hours' => ['nullable', 'numeric', 'min:0.5', 'max:24'],
        ], [
            'code.unique' => 'Kode program training tersebut sudah digunakan.',
            'skill_id.required' => 'Wajib memilih satu kompetensi acuan dari Competency List.',
            'skill_id.exists' => 'Kompetensi acuan yang dipilih tidak valid.',
            'granted_skill_level.required' => 'Wajib menentukan level kompetensi yang dianugerahkan (Skala 1 - 5).',
        ]);

        if (empty($validated['trainer_id'])) {
            $fallbackTrainer = User::where('role', 'trainer')->first();
            $validated['trainer_id'] = $fallbackTrainer?->id ?? $actor->id;
        }

        $validated['status'] = 'active';

        $course = DB::transaction(function () use ($validated) {
            $course = Course::create($validated);
            $this->ensureFourMeetingModules($course);

            // Jika admin memilih opsi langsung membuat jadwal di kalender
            if (!empty($validated['create_schedule']) && !empty($validated['session_date'])) {
                $sessionDate = $validated['session_date'];
                $startTime = $validated['start_time'] ?? '08:00';
                $endTime = $validated['end_time'] ?? '16:00';
                $duration = !empty($validated['duration_hours']) ? (float) $validated['duration_hours'] : 8.0;

                $session = AttendanceSession::create([
                    'course_id' => $course->id,
                    'trainer_id' => $course->trainer_id,
                    'title' => $validated['session_title'] ?: "Sesi 1: {$course->title}",
                    'session_date' => $sessionDate,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'duration_hours' => $duration,
                    'location' => $validated['location'] ?: 'Ruang Training Utama',
                    'quota' => $validated['quota'] ?: 25,
                    'plan_status' => 'approved',
                    'qr_token' => Str::random(32),
                    'expires_at' => Carbon::parse("{$sessionDate} {$endTime}"),
                    'is_active' => false,
                ]);

                // Sinkronkan ke Roster Trainer
                $this->syncService->syncSessionToRoster($session);
            }

            return $course;
        });

        $skill = Skill::find($validated['skill_id']);

        return back()->with(
            'success',
            "Topik / Program Training [{$course->code}] {$course->title} berhasil dibuat & terhubung ke kompetensi [{$skill?->code}] {$skill?->name} Level {$course->granted_skill_level}."
        );
    }

    /**
     * Jadwalkan Sesi Langsung ke Kalender untuk Course tertentu.
     */
    public function scheduleSession(Request $request, Course $course): RedirectResponse
    {
        $actor = $request->user();
        abort_if(
            !in_array($actor->role, ['lnd', 'admin'], true),
            403,
            'Hanya Tim LnD yang berwenang menambahkan jadwal pelatihan.'
        );

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'session_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'duration_hours' => ['nullable', 'numeric', 'min:0.5', 'max:24'],
            'trainer_id' => ['nullable', 'integer', 'exists:users,id'],
            'location' => ['nullable', 'string', 'max:120'],
            'quota' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'plan_status' => ['required', Rule::in(['approved', 'pending'])],
        ]);

        $trainerId = $validated['trainer_id'] ?? $course->trainer_id;

        // Cek bentrok jam trainer
        if ($trainerId) {
            $check = $this->syncService->checkAvailability(
                $trainerId,
                $validated['session_date'],
                $validated['start_time'],
                $validated['end_time']
            );

            if (!$check['available']) {
                throw ValidationException::withMessages([
                    'trainer_id' => 'Konflik Jadwal Instruktur: ' . $check['message'],
                ]);
            }
        }

        $start = Carbon::parse("{$validated['session_date']} {$validated['start_time']}");
        $end = Carbon::parse("{$validated['session_date']} {$validated['end_time']}");
        $duration = !empty($validated['duration_hours'])
            ? (float) $validated['duration_hours']
            : round(abs($start->diffInMinutes($end)) / 60, 1);

        $session = DB::transaction(function () use ($course, $validated, $trainerId, $duration, $end) {
            $created = AttendanceSession::create([
                'course_id' => $course->id,
                'trainer_id' => $trainerId,
                'title' => $validated['title'],
                'session_date' => $validated['session_date'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'duration_hours' => $duration,
                'location' => $validated['location'] ?: 'Ruang Training Utama',
                'quota' => $validated['quota'] ?: 25,
                'plan_status' => $validated['plan_status'],
                'qr_token' => Str::random(32),
                'expires_at' => $end,
                'is_active' => false,
            ]);

            $this->syncService->syncSessionToRoster($created);

            return $created;
        });

        return back()->with(
            'success',
            "Jadwal pelatihan [{$course->code}] {$session->title} ({$duration} Jam Ajar) berhasil ditambahkan ke kalender pelatihan."
        );
    }

    /**
     * Update Program Training & Relasi Master Kompetensi.
     */
    public function update(Request $request, Course $course): RedirectResponse
    {
        $actor = $request->user();
        abort_if(
            !in_array($actor->role, ['lnd', 'admin'], true),
            403,
            'Hanya Tim LnD yang berwenang mengubah program pelatihan.'
        );

        $request->merge([
            'code' => strtoupper(trim((string) $request->input('code', ''))),
        ]);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('courses', 'code')->ignore($course->id)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'trainer_id' => ['nullable', 'integer', 'exists:users,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_mandatory' => ['required', 'boolean'],
            'training_source' => ['required', Rule::in(['internal', 'external'])],
            'training_category' => ['required', 'string', 'max:60'],
            'validity_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'skill_id' => ['required', 'integer', 'exists:skills,id'],
            'granted_skill_level' => ['required', 'integer', 'min:1', 'max:5'],
        ], [
            'code.unique' => 'Kode program training tersebut sudah digunakan.',
            'skill_id.required' => 'Wajib memilih satu kompetensi acuan dari Competency List.',
            'granted_skill_level.required' => 'Wajib menentukan level kompetensi yang dianugerahkan (Skala 1 - 5).',
        ]);

        $course->update($validated);
        $this->ensureFourMeetingModules($course);
        $skill = Skill::find($validated['skill_id']);

        return back()->with(
            'success',
            "Program Training [{$course->code}] {$course->title} berhasil diperbarui (Terhubung ke: [{$skill?->code}] {$skill?->name} Level {$course->granted_skill_level})."
        );
    }

    /**
     * Hapus Program Training dengan Pengecekan Integritas Data.
     */
    public function destroy(Request $request, Course $course): RedirectResponse
    {
        $actor = $request->user();
        abort_if(
            !in_array($actor->role, ['lnd', 'admin'], true),
            403,
            'Hanya Tim LnD yang berwenang menghapus program pelatihan.'
        );

        $enrollmentCount = $course->enrollments()->count();
        if ($enrollmentCount > 0) {
            return back()->with(
                'error',
                "Program Training [{$course->code}] tidak dapat dihapus karena telah memiliki {$enrollmentCount} data pendaftaran / sertifikat peserta."
            );
        }

        $code = $course->code;
        $title = $course->title;
        $course->delete();

        return back()->with(
            'success',
            "Program Training [{$code}] {$title} berhasil dihapus."
        );
    }

    private function ensureFourMeetingModules(Course $course): void
    {
        for ($moduleOrder = 1; $moduleOrder <= 4; $moduleOrder++) {
            $course->modules()->firstOrCreate(
                ['order_index' => $moduleOrder],
                ['title' => "Pertemuan {$moduleOrder}"]
            );
        }
    }
}
