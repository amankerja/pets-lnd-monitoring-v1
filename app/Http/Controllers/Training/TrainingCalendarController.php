<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Interactive Training Calendar & Live Control Center.
 *
 * Satu halaman, tiga tampilan (server-side range query):
 *  - atmp    : Annual Training Master Plan (12 bulan)
 *  - monthly : grid kalender bulanan (Senin-Minggu)
 *  - weekly  : agenda mingguan dengan jam operasional
 *
 * Data bersumber dari `attendance_sessions` sehingga satu jadwal otomatis menjadi
 * sesi presensi Rotating QR yang dapat dibuka dari Proyektor.
 */
class TrainingCalendarController extends Controller
{
    private const VIEWS = ['atmp', 'monthly', 'weekly'];

    private const CATEGORIES = ['Safety', 'Operation', 'Plant', 'Pengawas', 'Engineering', 'Leadership', 'General'];

    public function index(Request $request): Response
    {
        $user = $request->user();
        $role = $user->role === 'admin' ? 'lnd' : $user->role;

        $canManage = $role === 'lnd';
        $canOpenProjector = in_array($role, ['lnd', 'trainer'], true);
        $isParticipant = $role === 'peserta';

        $view = in_array($request->query('view'), self::VIEWS, true) ? (string) $request->query('view') : 'monthly';
        $anchor = $this->parseDate($request->query('date')) ?? now()->startOfDay();

        [$from, $to] = match ($view) {
            'atmp' => [$anchor->copy()->startOfYear(), $anchor->copy()->endOfYear()],
            'weekly' => [
                $anchor->copy()->startOfWeek(Carbon::MONDAY),
                $anchor->copy()->endOfWeek(Carbon::SUNDAY),
            ],
            default => [
                $anchor->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY),
                $anchor->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY),
            ],
        };

        $filters = [
            'training_category' => trim((string) $request->query('training_category', '')),
            'mandatory_type' => trim((string) $request->query('mandatory_type', '')),
            'training_source' => trim((string) $request->query('training_source', '')),
            'plan_status' => trim((string) $request->query('plan_status', '')),
        ];

        $sessions = AttendanceSession::query()
            ->with([
                'course:id,code,title,training_category,is_mandatory,training_source,skill_id,granted_skill_level,trainer_id',
                'course.skill:id,code,name,category,competency_type',
                'course.trainer:id,name,nik,department',
                'trainer:id,name,nik,department',
            ])
            ->withAttendanceCounts()
            ->whereBetween('session_date', [$from->toDateString(), $to->toDateString()])
            ->when($filters['training_category'] !== '', fn ($q) => $q->whereHas(
                'course', fn ($c) => $c->where('training_category', $filters['training_category'])
            ))
            ->when($filters['mandatory_type'] === 'mandatory', fn ($q) => $q->whereHas('course', fn ($c) => $c->where('is_mandatory', true)))
            ->when($filters['mandatory_type'] === 'non_mandatory', fn ($q) => $q->whereHas('course', fn ($c) => $c->where('is_mandatory', false)))
            ->when(in_array($filters['training_source'], ['internal', 'external'], true), fn ($q) => $q->whereHas(
                'course', fn ($c) => $c->where('training_source', $filters['training_source'])
            ))
            ->when(in_array($filters['plan_status'], ['pending', 'approved'], true), fn ($q) => $q->where('plan_status', $filters['plan_status']))
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->orderBy('id')
            ->get();

        // Sesi yang sedang LIVE tidak ikut terfilter / terbatas range: radar harus selalu akurat.
        $liveRaw = AttendanceSession::query()
            ->with([
                'course:id,code,title,training_category,is_mandatory,training_source,skill_id,granted_skill_level,trainer_id',
                'course.skill:id,code,name,category,competency_type',
                'course.trainer:id,name,nik,department',
                'trainer:id,name,nik,department',
            ])
            ->withAttendanceCounts()
            ->whereDate('session_date', now()->toDateString())
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->orderBy('id')
            ->get();

        $courseIds = $sessions->pluck('course_id')->merge($liveRaw->pluck('course_id'))->unique()->values();

        $enrolledByCourse = Enrollment::query()
            ->whereIn('course_id', $courseIds)
            ->where('status', 'approved')
            ->select('course_id', DB::raw('COUNT(*) as total'))
            ->groupBy('course_id')
            ->pluck('total', 'course_id');

        $myEnrollments = $isParticipant
            ? Enrollment::query()->where('user_id', $user->id)->whereIn('course_id', $courseIds)->pluck('status', 'course_id')
            : collect();

        $map = fn (AttendanceSession $s): array => $this->mapEvent(
            $s, $enrolledByCourse, $myEnrollments, $canOpenProjector
        );

        $events = $sessions->map($map)->values();
        $live = $liveRaw->map($map)->values();

        return Inertia::render('Training/TrainingCalendar', [
            'view' => $view,
            'anchorDate' => $anchor->toDateString(),
            'todayDate' => now()->toDateString(),
            'rangeStart' => $from->toDateString(),
            'rangeEnd' => $to->toDateString(),
            'events' => $events,
            'liveSessions' => $live,
            'summary' => [
                'total' => $events->count(),
                'approved' => $events->where('plan_status', 'approved')->count(),
                'pending' => $events->where('plan_status', 'pending')->count(),
                'live' => $live->count(),
            ],
            'filters' => $filters,
            'categories' => self::CATEGORIES,
            'abilities' => [
                'canManage' => $canManage,
                'canOpenProjector' => $canOpenProjector,
                'isParticipant' => $isParticipant,
            ],
            'courses' => $canManage
                ? Course::query()->active()->with(['skill:id,code,name,category', 'trainer:id,name,nik'])->orderBy('code')->get(['id', 'code', 'title', 'trainer_id', 'skill_id', 'granted_skill_level'])
                : [],
            'trainers' => $canManage
                ? User::query()->trainers()->orderBy('name')->get(['id', 'name', 'nik'])
                : [],
        ]);
    }

    /**
     * Tambah jadwal dari kalender (klik tanggal kosong). Hanya LnD / Admin.
     * Sesi dibuat NON-AKTIF: Trainer membukanya dari Proyektor QR saat kelas dimulai.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'title' => ['required', 'string', 'max:150'],
            'session_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'required_with:start_time'],
            'trainer_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(function ($q) {
                    $q->whereIn('role', ['trainer', 'lnd'])
                      ->orWhere('is_trainer', true);
                }),
            ],
            'location' => ['nullable', 'string', 'max:120'],
            'quota' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'duration_hours' => ['nullable', 'numeric', 'min:0.5', 'max:16'],
            'plan_status' => ['nullable', Rule::in(['pending', 'approved'])],
        ]);

        $course = Course::query()->findOrFail($data['course_id']);
        $trainerId = $data['trainer_id'] ?? $course->trainer_id;

        // Verifikasi ketersediaan Trainer (Bentrok jam & Status Cuti/Off) via TrainerScheduleSyncService
        if ($trainerId) {
            $syncService = app(\App\Services\TrainerScheduleSyncService::class);
            $check = $syncService->checkAvailability(
                $trainerId,
                $data['session_date'],
                $data['start_time'] ?? null,
                $data['end_time'] ?? null
            );

            if (!$check['available']) {
                throw ValidationException::withMessages([
                    'trainer_id' => "Konflik Jadwal Instruktur: " . $check['message'],
                ]);
            }
        }

        $start = $data['start_time'] ?? null;
        $end = $data['end_time'] ?? null;

        if ($start && $end && $end <= $start) {
            throw ValidationException::withMessages([
                'end_time' => 'Jam selesai harus lebih besar dari jam mulai.',
            ]);
        }

        $durationHours = $start && $end
            ? round(Carbon::createFromFormat('H:i', $start)->diffInMinutes(Carbon::createFromFormat('H:i', $end)) / 60, 1)
            : round((float) ($data['duration_hours'] ?? 2.0), 1);

        $expiresAt = $end
            ? Carbon::createFromFormat('Y-m-d H:i', "{$data['session_date']} {$end}")
            : Carbon::createFromFormat('Y-m-d', $data['session_date'])->endOfDay();

        $session = DB::transaction(function () use ($course, $trainerId, $data, $start, $end, $durationHours, $expiresAt) {
            $created = AttendanceSession::create([
                'course_id' => $course->id,
                'trainer_id' => $trainerId,
                'title' => $data['title'],
                'session_date' => $data['session_date'],
                'start_time' => $start,
                'end_time' => $end,
                'location' => $data['location'] ?? null,
                'quota' => $data['quota'] ?? null,
                'plan_status' => $data['plan_status'] ?? 'approved',
                'duration_hours' => $durationHours,
                'qr_token' => Str::random(32),
                'expires_at' => $expiresAt,
                'is_active' => false,
            ]);

            app(\App\Services\TrainerScheduleSyncService::class)->syncSessionToRoster($created);

            return $created;
        });

        return back()->with('success', "Jadwal [{$course->code}] {$data['title']} berhasil ditambahkan ke kalender.");
    }

    /**
     * Setujui / kembalikan ke Pending usulan jadwal ATMP. Hanya LnD / Admin.
     */
    public function updatePlanStatus(Request $request, AttendanceSession $session): RedirectResponse
    {
        $data = $request->validate([
            'plan_status' => ['required', Rule::in(['pending', 'approved'])],
        ]);

        DB::transaction(function () use ($session, $data) {
            $session->update(['plan_status' => $data['plan_status']]);
            app(\App\Services\TrainerScheduleSyncService::class)->syncSessionToRoster($session->fresh());
        });

        return back()->with('success', 'Status rencana jadwal diubah menjadi '.strtoupper($data['plan_status']).'.');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function parseDate(mixed $value): ?Carbon
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * done | live | today (hari ini, belum dibuka) | scheduled
     */
    private function resolveStatus(AttendanceSession $s): string
    {
        $today = now()->toDateString();
        $date = $s->session_date?->toDateString();

        if ($date === null) {
            return 'scheduled';
        }
        if ($date < $today) {
            return 'done';
        }
        if ($date > $today) {
            return 'scheduled';
        }

        if ($s->is_active && $s->expires_at && $s->expires_at->isFuture()) {
            return 'live';
        }

        $end = $s->end_time ? substr((string) $s->end_time, 0, 5) : null;
        if ($end !== null && $end < now()->format('H:i')) {
            return 'done';
        }

        return 'today';
    }

    private function mapEvent(
        AttendanceSession $s,
        Collection $enrolledByCourse,
        Collection $myEnrollments,
        bool $canOpenProjector
    ): array {
        $skill = $s->course?->skill ?? $s->course?->competency;
        $trainer = $s->trainer ?? $s->course?->trainer;

        return [
            'id' => $s->id,
            'course_id' => $s->course_id,
            'course_code' => $s->course?->code ?? '-',
            'course_title' => $s->course?->title ?? '-',
            'training_category' => $s->course?->training_category,
            'is_mandatory' => (bool) ($s->course?->is_mandatory ?? false),
            'training_source' => $s->course?->training_source,
            'title' => $s->title,
            // 1. Nama Kompetensi & Level
            'competency_id' => $skill?->id,
            'competency_code' => $skill?->code ?? '-',
            'competency_name' => $skill?->name ?? 'Kompetensi Umum',
            'competency_category' => $skill?->category ?? 'General',
            'granted_skill_level' => (int) ($s->course?->granted_skill_level ?? 3),
            // 2. Jam Ajar (Waktu & Durasi)
            'date' => $s->session_date?->toDateString(),
            'start_time' => $s->start_time ? substr((string) $s->start_time, 0, 5) : null,
            'end_time' => $s->end_time ? substr((string) $s->end_time, 0, 5) : null,
            'duration_hours' => (float) ($s->duration_hours ?: 4.0),
            // 3. Pengajar / Trainer
            'trainer_id' => $trainer?->id,
            'trainer_name' => $trainer?->name ?? 'Tim Instruktur L&D',
            'trainer_nik' => $trainer?->nik ?? '-',
            'trainer_department' => $trainer?->department ?? 'L&D Dept',
            // 4. Peserta & Lokasi
            'location' => $s->location,
            'quota' => $s->quota,
            'enrolled_count' => (int) ($enrolledByCourse[$s->course_id] ?? 0),
            'present_count' => (int) $s->present_count,
            'plan_status' => $s->plan_status ?? 'approved',
            'status' => $this->resolveStatus($s),
            'my_enrollment' => $myEnrollments[$s->course_id] ?? null,
            // Hanya LnD & Trainer yang boleh membuka Proyektor QR (token tidak pernah dikirim ke klien).
            'projector_url' => $canOpenProjector
                ? route('admin.attendance.index', ['session_id' => $s->id])
                : null,
        ];
    }
}
