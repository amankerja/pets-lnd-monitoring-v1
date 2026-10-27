<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\TrainerTeachingLog;
use App\Services\TrainerKpiActualsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    /**
     * Halaman Layar Proyektor Trainer/Admin dengan Stateless Rotating QR Code (15 Detik).
     * Menggunakan Closure Evaluators agar Partial Reload (`only: [...]`) sangat ringan tanpa query yang tidak perlu.
     */
    public function projector(Request $request): Response
    {
        $selectedSessionId = $request->integer('session_id') ?: null;

        $sessionQuery = AttendanceSession::query();
        $this->scopeSessionsToTrainer($sessionQuery, $request);

        $sessions = (clone $sessionQuery)
            ->with(['course:id,code,title'])
            ->orderByDesc('is_active')
            ->orderByDesc('session_date')
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        /** @var AttendanceSession|null $activeSession */
        $activeSession = $selectedSessionId
            ? (clone $sessionQuery)->with('course:id,code,title')->find($selectedSessionId)
            : ($sessions->firstWhere('is_active', true) ?? $sessions->first());
        abort_if($selectedSessionId && !$activeSession, 403);

        if ($activeSession && !$sessions->contains(fn (AttendanceSession $session) => $session->is($activeSession))) {
            $sessions->prepend($activeSession);
        }

        $coursesQuery = Course::query()
            ->active()
            ->select(['id', 'code', 'title']);
        if (!in_array($request->user()?->role, ['lnd', 'admin'], true)) {
            $coursesQuery->where('trainer_id', $request->user()->id);
        }
        $courses = $coursesQuery->orderBy('code')->get();

        return Inertia::render('Admin/AttendanceProjector', [
            'courses' => fn () => $courses,
            'sessions' => fn () => $sessions->map(fn (AttendanceSession $s) => [
                'id' => $s->id,
                'course_id' => $s->course_id,
                'course_code' => $s->course?->code,
                'course_title' => $s->course?->title,
                'title' => $s->title,
                'session_date' => $s->session_date?->format('Y-m-d'),
                'expires_at' => $s->expires_at?->toIso8601String(),
                'is_active' => (bool) $s->is_active,
            ]),
            'activeSession' => fn () => $activeSession ? [
                'id' => $activeSession->id,
                'course_id' => $activeSession->course_id,
                'course_code' => $activeSession->course?->code,
                'course_title' => $activeSession->course?->title,
                'title' => $activeSession->title,
                'session_date' => $activeSession->session_date?->format('Y-m-d'),
                'expires_at' => $activeSession->expires_at?->toIso8601String(),
                'is_active' => (bool) $activeSession->is_active,
            ] : null,

            // Prop Stateless Rotating QR (Dievaluasi ulang setiap 15 detik via Partial Reload tanpa UPDATE DB)
            'dynamicQrPayload' => function () use ($activeSession) {
                if (!$activeSession || $activeSession->isExpired()) {
                    return [
                        'qr_string' => 'INACTIVE:000000000000',
                        'payload' => 'INACTIVE:000000000000',
                        'manual_code' => '------',
                        'time_window' => AttendanceSession::getCurrentTimeWindow(),
                        'rotation_interval' => AttendanceSession::ROTATION_INTERVAL_SECONDS,
                        'generated_at' => now()->toIso8601String(),
                    ];
                }

                return $activeSession->getDynamicPayload(0);
            },

            // Prop Daftar Hadir Live (Dievaluasi ulang setiap 3 detik via Partial Reload)
            'presentParticipants' => function () use ($activeSession) {
                if (!$activeSession) {
                    return [];
                }

                return Attendance::query()
                    ->where('attendance_session_id', $activeSession->id)
                    ->with(['user:id,name,nik,department'])
                    ->orderByDesc('scanned_at')
                    ->orderByDesc('id')
                    ->get()
                    ->map(fn (Attendance $att) => [
                        'id' => $att->id,
                        'user_id' => $att->user_id,
                        'name' => $att->user?->name,
                        'nik' => $att->user?->nik,
                        'department' => $att->user?->department,
                        'status' => $att->status,
                        'scanned_at' => $att->scanned_at?->format('H:i:s'),
                        'device_info' => $att->device_info,
                    ]);
            },

            // Prop Statistik Kehadiran Live
            'stats' => function () use ($activeSession) {
                if (!$activeSession) {
                    return [
                        'enrolled_approved' => 0,
                        'present_count' => 0,
                        'permit_count' => 0,
                        'absent_count' => 0,
                        'remaining_count' => 0,
                        'attendance_percentage' => 0,
                    ];
                }

                $enrolledApproved = Enrollment::query()
                    ->where('course_id', $activeSession->course_id)
                    ->where('status', 'approved')
                    ->count();

                $counts = DB::table('attendances')
                    ->where('attendance_session_id', $activeSession->id)
                    ->selectRaw("
                        COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
                        COUNT(CASE WHEN status = 'permit' THEN 1 END) as permit_count,
                        COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_count
                    ")
                    ->first();

                $present = (int) ($counts->present_count ?? 0);
                $permit = (int) ($counts->permit_count ?? 0);
                $absent = (int) ($counts->absent_count ?? 0);
                $remaining = max(0, $enrolledApproved - ($present + $permit + $absent));
                $pct = $enrolledApproved > 0 ? round(($present / $enrolledApproved) * 100, 1) : 0;

                return [
                    'enrolled_approved' => $enrolledApproved,
                    'present_count' => $present,
                    'permit_count' => $permit,
                    'absent_count' => $absent,
                    'remaining_count' => $remaining,
                    'attendance_percentage' => $pct,
                ];
            },
        ]);
    }

    /**
     * Endpoint POST Scan QR / Input Kode Manual 6-Digit dari Browser HP Peserta.
     * Memvalidasi HMAC-SHA256 pada window T atau T-1, serta tahan terhadap Race Condition (double-tap)
     * menggunakan DB::transaction dan firstOrCreate.
     */
    public function scan(Request $request): RedirectResponse
    {
        // Mendukung input `payload` maupun `qr_token`
        $rawInput = trim((string) ($request->input('payload') ?? $request->input('qr_token', '')));

        if ($rawInput === '') {
            throw ValidationException::withMessages([
                'payload' => 'Payload QR atau kode presensi wajib diisi.',
                'qr_token' => 'Payload QR atau kode presensi wajib diisi.',
            ]);
        }

        $user = $request->user();
        $deviceInfo = substr((string) ($request->input('device_info') ?: $request->userAgent()), 0, 250);

        [$wasRecentlyCreated, $session] = DB::transaction(function () use ($rawInput, $user, $deviceInfo, $request) {
            $session = null;

            // 1. Jika format adalah "{session_id}:{hmac_signature}", ekstrak session_id langsung
            if (str_contains($rawInput, ':')) {
                [$sessionIdPart] = explode(':', $rawInput, 2);
                if (ctype_digit($sessionIdPart)) {
                    $session = AttendanceSession::query()
                        ->whereKey((int) $sessionIdPart)
                        ->first();
                }
            }

            // 2. Jika peserta mengetik kode manual 6-digit tanpa prefix session_id,
            // cari dari sesi aktif milik kelas yang diikuti peserta (atau dari parameter session_id)
            if (!$session) {
                $candidateSessions = AttendanceSession::query()
                    ->where('is_active', true)
                    ->where('expires_at', '>', now())
                    ->when($request->filled('session_id'), fn ($q) => $q->whereKey($request->integer('session_id')))
                    ->whereHas('course.enrollments', function ($q) use ($user) {
                        $q->where('user_id', $user->id)->where('status', 'approved');
                    })
                    ->get();

                foreach ($candidateSessions as $candidate) {
                    if ($candidate->verifyDynamicPayload($rawInput)) {
                        $session = $candidate;
                        break;
                    }
                }
            }

            if (!$session) {
                $msg = 'Sesi presensi tidak ditemukan atau kode QR/PIN sudah kedaluwarsa (lebih dari 30 detik).';
                throw ValidationException::withMessages([
                    'payload' => $msg,
                    'qr_token' => $msg,
                ]);
            }

            if ($session->isExpired()) {
                $msg = 'Sesi presensi ini sudah ditutup atau waktu sesi kelas telah berakhir.';
                throw ValidationException::withMessages([
                    'payload' => $msg,
                    'qr_token' => $msg,
                ]);
            }

            // 3. Validasi Stateless HMAC Signature pada window saat ini ($currentWindow) atau ($currentWindow - 1)
            if (!$session->verifyDynamicPayload($rawInput)) {
                $msg = 'Kode QR tidak valid atau sudah berganti. Silakan scan ulang QR terbaru di layar proyektor.';
                throw ValidationException::withMessages([
                    'payload' => $msg,
                    'qr_token' => $msg,
                ]);
            }

            // 4. Pastikan peserta terdaftar & berstatus `approved` pada kelas tersebut
            $isApprovedParticipant = Enrollment::query()
                ->where('user_id', $user->id)
                ->where('course_id', $session->course_id)
                ->where('status', 'approved')
                ->exists();

            if (!$isApprovedParticipant) {
                $msg = 'Anda belum terdaftar sebagai peserta aktif (Approved) pada kelas ini.';
                throw ValidationException::withMessages([
                    'payload' => $msg,
                    'qr_token' => $msg,
                ]);
            }

            // 5. Gunakan firstOrCreate di dalam transaksi untuk mencegah Duplicate Key Error saat tombol tertekan 2 kali
            $attendance = Attendance::firstOrCreate(
                [
                    'attendance_session_id' => $session->id,
                    'user_id' => $user->id,
                ],
                [
                    'status' => 'present',
                    'scanned_at' => now(),
                    'device_info' => $deviceInfo,
                ]
            );

            return [$attendance->wasRecentlyCreated, $session];
        });

        // TUGAS 3 - Poin 1: Sinkronisasi otomatis ke profil Trainer (trainer_teaching_logs & KPI)
        $trainerId = $session->trainer_id ?? $session->course?->trainer_id;
        $trainer = $trainerId ? \App\Models\User::find($trainerId) : null;
        $trainerName = $trainer?->name ?? 'Instruktur Kelas';

        if ($trainerId) {
            $scannedCount = Attendance::where('attendance_session_id', $session->id)
                ->where('status', 'present')
                ->count();

            TrainerTeachingLog::updateOrCreate(
                [
                    'attendance_session_id' => $session->id,
                ],
                [
                    'trainer_id' => $trainerId,
                    'course_id' => $session->course_id,
                    'training_title' => $session->title,
                    'log_date' => $session->session_date ?? now()->toDateString(),
                    'entry_source' => 'auto_qr_session',
                    'duration_hours' => (float) ($session->duration_hours ?? 1.0),
                    'scanned_participants_count' => $scannedCount,
                    'location' => $session->location ?? 'Site PBJ Training Room',
                ]
            );

            app(TrainerKpiActualsService::class)->syncTrainer($trainerId);
        }

        $sessionInfo = [
            'id' => $session->id,
            'title' => $session->title,
            'course_id' => $session->course_id,
            'trainer_id' => $trainerId,
            'trainer_name' => $trainerName,
        ];

        if (!$wasRecentlyCreated) {
            return back()->with([
                'success' => 'Kehadiran Anda sudah tercatat sebelumnya pada sesi ini (Idempotent Safe).',
                'scanned_session' => $sessionInfo,
            ]);
        }

        return back()->with([
            'success' => 'Presensi berhasil diverifikasi! Nama Anda telah tampil di layar proyektor Trainer.',
            'scanned_session' => $sessionInfo,
        ]);
    }

    /**
     * Buat sesi presensi baru (qr_token berperan sebagai secret_key sesi, duration_hours mencatat jam mengajar Trainer).
     */
    public function store(Request $request): RedirectResponse
    {
        $courseRule = Rule::exists('courses', 'id');
        if (!in_array($request->user()?->role, ['lnd', 'admin'], true)) {
            $courseRule->where('trainer_id', $request->user()->id);
        }

        $validated = $request->validate([
            'course_id' => ['required', 'integer', $courseRule],
            'title' => ['required', 'string', 'max:150'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'duration_hours' => ['nullable', 'numeric', 'min:0.5', 'max:16'],
        ]);

        $session = DB::transaction(function () use ($request, $validated) {
            return AttendanceSession::create([
                'course_id' => $validated['course_id'],
                'trainer_id' => $request->user()?->id,
                'title' => $validated['title'],
                'session_date' => now()->toDateString(),
                'duration_hours' => isset($validated['duration_hours'])
                    ? round((float) $validated['duration_hours'], 1)
                    : round(((int) $validated['duration_minutes']) / 60, 1),
                'qr_token' => Str::random(32), // Secret key sesi untuk HMAC-SHA256
                'expires_at' => now()->addMinutes((int) $validated['duration_minutes']),
                'is_active' => true,
            ]);
        });

        return redirect()
            ->route('admin.attendance.index', ['session_id' => $session->id])
            ->with('success', 'Sesi presensi Rotating QR baru berhasil dibuka.');
    }

    /**
     * Rotasi paksa secret_key sesi (`qr_token`) dan perpanjang masa aktif sesi.
     */
    public function regenerateSecret(Request $request, AttendanceSession $session): RedirectResponse
    {
        $this->authorizeTrainerSession($request, $session);
        $minutes = $request->integer('duration_minutes', 30);

        DB::transaction(function () use ($session, $minutes) {
            $session->update([
                'qr_token' => Str::random(32),
                'expires_at' => now()->addMinutes(max(5, min(240, $minutes))),
                'is_active' => true,
            ]);
        });

        return back()->with('success', 'Secret key sesi diperbarui dan masa aktif sesi diperpanjang.');
    }

    /**
     * Buka / Tutup sesi presensi.
     */
    public function toggleStatus(Request $request, AttendanceSession $session): RedirectResponse
    {
        $this->authorizeTrainerSession($request, $session);

        DB::transaction(function () use ($session) {
            $session->update([
                'is_active' => !$session->is_active,
            ]);
        });

        return back()->with('success', 'Status sesi presensi berhasil diubah.');
    }

    private function scopeSessionsToTrainer(Builder $query, Request $request): void
    {
        if (in_array($request->user()?->role, ['lnd', 'admin'], true)) {
            return;
        }

        $trainerId = $request->user()->id;
        $query->where(fn (Builder $sessionQuery) => $sessionQuery
            ->where('trainer_id', $trainerId)
            ->orWhereHas('course', fn (Builder $courseQuery) => $courseQuery->where('trainer_id', $trainerId)));
    }

    private function authorizeTrainerSession(Request $request, AttendanceSession $session): void
    {
        if (in_array($request->user()?->role, ['lnd', 'admin'], true)) {
            return;
        }

        $isAssignedTrainer = $session->trainer_id === $request->user()->id
            || $session->course()->where('trainer_id', $request->user()->id)->exists();

        abort_unless($isAssignedTrainer, 403);
    }
}
