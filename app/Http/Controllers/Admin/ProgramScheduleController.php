<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\User;
use App\Services\TrainerScheduleSyncService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProgramScheduleController extends Controller
{
    public function __construct(
        protected TrainerScheduleSyncService $syncService
    ) {}

    /**
     * TUGAS 1 - Poin 1: Index Program Schedule & Session Manager
     * Menampilkan daftar sesi/jadwal terencana dengan paginasi, filter server-side,
     * dan ringkasan metrik KPI eksekutif.
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'plan_status' => ['nullable', Rule::in(['approved', 'pending'])],
            'month' => ['nullable', 'date_format:Y-m'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'training_category' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $todayStr = now()->toDateString();
        $nowStr = now()->toDateTimeString();

        $summaryRaw = $this->filteredQuery($filters, false)
            ->selectRaw("
                COUNT(*) as total,
                COUNT(CASE WHEN plan_status = 'approved' THEN 1 END) as approved,
                COUNT(CASE WHEN plan_status = 'pending' THEN 1 END) as pending,
                COUNT(CASE WHEN session_date = ? AND is_active = 1 AND expires_at > ? THEN 1 END) as live
            ", [$todayStr, $nowStr])
            ->first();

        $summary = [
            'total' => (int) ($summaryRaw?->total ?? 0),
            'approved' => (int) ($summaryRaw?->approved ?? 0),
            'pending' => (int) ($summaryRaw?->pending ?? 0),
            'live' => (int) ($summaryRaw?->live ?? 0),
        ];

        $sessions = $this->filteredQuery($filters, true)
            ->with([
                'course:id,code,title,training_category',
                'trainer:id,name,nik,department',
            ])
            ->withCount('attendances')
            ->orderByDesc('session_date')
            ->orderBy('start_time')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $sessionIds = $sessions->getCollection()->pluck('id');
        $registeredCounts = DB::table('enrollments')
            ->whereIn('attendance_session_id', $sessionIds)
            ->whereIn('status', ['pending', 'approved'])
            ->where('is_superseded', false)
            ->select('attendance_session_id', DB::raw('COUNT(DISTINCT user_id) as total'))
            ->groupBy('attendance_session_id')
            ->pluck('total', 'attendance_session_id');

        $sessions->getCollection()->transform(function (AttendanceSession $session) use ($registeredCounts) {
            $session->setAttribute('registered_count', (int) ($registeredCounts[$session->id] ?? 0));
            $session->setAttribute(
                'is_live',
                $session->session_date?->isToday() &&
                $session->is_active &&
                $session->expires_at?->isFuture()
            );

            return $session;
        });

        return Inertia::render('Admin/ProgramScheduleManager', [
            'sessions' => $sessions,
            'summary' => $summary,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'plan_status' => $filters['plan_status'] ?? '',
                'month' => $filters['month'] ?? '',
                'date_from' => $filters['date_from'] ?? '',
                'date_to' => $filters['date_to'] ?? '',
                'training_category' => $filters['training_category'] ?? '',
            ],
            'courses' => Course::query()
                ->orderBy('code')
                ->get(['id', 'code', 'title', 'training_category']),
            'trainers' => User::query()
                ->where(fn (Builder $query) => $query
                    ->whereIn('role', ['trainer', 'lnd', 'admin'])
                    ->orWhere('is_trainer', true))
                ->orderBy('name')
                ->get(['id', 'name', 'nik', 'department', 'trainer_type']),
            'categories' => Course::query()
                ->whereNotNull('training_category')
                ->where('training_category', '!=', '')
                ->distinct()
                ->orderBy('training_category')
                ->pluck('training_category'),
        ]);
    }

    /**
     * API Real-Time: Periksa ketersediaan Trainer (Bentrok jam & Status Cuti/Off)
     */
    public function checkTrainerAvailability(Request $request): JsonResponse
    {
        $trainerId = $request->filled('trainer_id') ? (int) $request->input('trainer_id') : null;
        $sessionDate = $request->input('session_date');
        $startTime = $request->input('start_time');
        $endTime = $request->input('end_time');
        $ignoreSessionId = $request->filled('session_id') ? (int) $request->input('session_id') : null;

        $result = $this->syncService->checkAvailability(
            $trainerId,
            $sessionDate,
            $startTime,
            $endTime,
            $ignoreSessionId
        );

        return response()->json($result);
    }

    /**
     * TUGAS 1 - Poin 2: Store Sesi / Jadwal Pelatihan Baru
     * Otomatis melakukan verifikasi bentrok dan sinkronisasi ke Roster Trainer jika disetujui.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedSchedule($request);
        $this->ensureTrainerAvailable($data);

        $course = Course::query()->findOrFail($data['course_id']);
        $start = Carbon::parse("{$data['session_date']} {$data['start_time']}");
        $end = Carbon::parse("{$data['session_date']} {$data['end_time']}");

        $session = DB::transaction(function () use ($data, $start, $end) {
            $created = AttendanceSession::query()->create([
                ...$data,
                'duration_hours' => round(abs($start->diffInMinutes($end)) / 60, 1),
                'qr_token' => Str::random(32),
                'expires_at' => $end,
                'is_active' => false,
            ]);

            // Sinkronisasi otomatis ke Roster Trainer
            $this->syncService->syncSessionToRoster($created);

            return $created;
        });

        return back()->with('success', "Jadwal [{$course->code}] {$data['title']} berhasil ditambahkan ke kalender pelatihan.");
    }

    /**
     * TUGAS 1 - Poin 2: Update Sesi / Jadwal Pelatihan
     */
    public function update(Request $request, AttendanceSession $session): RedirectResponse
    {
        if ($session->is_active && $session->expires_at?->isFuture()) {
            throw ValidationException::withMessages([
                'session' => 'Sesi sedang berlangsung (LIVE) dan tidak dapat diubah. Tutup sesi QR terlebih dahulu.',
            ]);
        }

        $data = $this->validatedSchedule($request);
        $this->ensureTrainerAvailable($data, $session);

        $start = Carbon::parse("{$data['session_date']} {$data['start_time']}");
        $end = Carbon::parse("{$data['session_date']} {$data['end_time']}");

        DB::transaction(function () use ($session, $data, $start, $end) {
            $session->update([
                ...$data,
                'duration_hours' => round(abs($start->diffInMinutes($end)) / 60, 1),
                'expires_at' => $end,
            ]);

            // Sinkronisasi otomatis ke Roster Trainer
            $this->syncService->syncSessionToRoster($session->fresh());
        });

        return back()->with('success', 'Jadwal sesi berhasil diperbarui.');
    }

    /**
     * TUGAS 1 - Poin 3: Fast Toggle Plan Status (Approved / Pending)
     */
    public function updateStatus(Request $request, AttendanceSession $session): RedirectResponse
    {
        $data = $request->validate([
            'plan_status' => ['required', Rule::in(['approved', 'pending'])],
        ]);

        if ($data['plan_status'] === 'approved') {
            // Cek ketersediaan trainer sebelum menyetujui jadwal
            $check = $this->syncService->checkAvailability(
                $session->trainer_id,
                $session->session_date ? Carbon::parse($session->session_date)->toDateString() : null,
                $session->start_time,
                $session->end_time,
                $session->id
            );

            if (!$check['available']) {
                throw ValidationException::withMessages([
                    'plan_status' => "Gagal menyetujui jadwal: {$check['message']}",
                ]);
            }
        }

        DB::transaction(function () use ($session, $data) {
            $session->update(['plan_status' => $data['plan_status']]);
            $this->syncService->syncSessionToRoster($session->fresh());
        });

        return back()->with('success', 'Status rencana jadwal diubah menjadi ' . strtoupper($data['plan_status']) . '.');
    }

    /**
     * TUGAS 1 - Poin 4: Safe Delete Sesi Jadwal
     */
    public function destroy(AttendanceSession $session): RedirectResponse
    {
        if ($session->is_active || $session->attendances()->exists()) {
            throw ValidationException::withMessages([
                'session' => 'Jadwal tidak dapat dihapus karena sesi QR sedang aktif atau sudah memiliki data presensi peserta.',
            ]);
        }

        DB::transaction(function () use ($session) {
            $this->syncService->cleanupSessionRoster($session);
            $session->delete();
        });

        return back()->with('success', 'Jadwal sesi berhasil dihapus.');
    }

    /**
     * Export Daftar Jadwal Pelatihan ke Format CSV (Excel Compatible with UTF-8 BOM)
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'plan_status' => ['nullable', Rule::in(['approved', 'pending'])],
            'month' => ['nullable', 'date_format:Y-m'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'training_category' => ['nullable', 'string', 'max:100'],
        ]);

        $sessions = $this->filteredQuery($filters, true)
            ->with([
                'course:id,code,title,training_category',
                'trainer:id,name,nik,department',
            ])
            ->orderByDesc('session_date')
            ->orderBy('start_time')
            ->get();

        $filename = 'jadwal-program-lnd-' . now()->format('Ymd-His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($sessions) {
            $handle = fopen('php://output', 'w');
            // Menulis UTF-8 BOM agar Excel Windows membuka aksen & simbol dengan benar
            fputs($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'ID Sesi',
                'Kode Training',
                'Nama Training',
                'Kategori',
                'Judul Sesi / Batch',
                'Tanggal Pelaksanaan',
                'Jam Mulai',
                'Jam Selesai',
                'Durasi (Jam)',
                'Lokasi',
                'Kuota Peserta',
                'Instruktur (Trainer)',
                'NIK Trainer',
                'Departemen Trainer',
                'Status Rencana',
                'Status Keaktifan',
            ]);

            foreach ($sessions as $session) {
                fputcsv($handle, [
                    $session->id,
                    $session->course?->code ?? '-',
                    $session->course?->title ?? '-',
                    $session->course?->training_category ?? '-',
                    $session->title,
                    $session->session_date ? Carbon::parse($session->session_date)->format('d/m/Y') : '-',
                    $session->start_time ? substr((string) $session->start_time, 0, 5) : '-',
                    $session->end_time ? substr((string) $session->end_time, 0, 5) : '-',
                    $session->duration_hours ?? 0,
                    $session->location ?? '-',
                    $session->quota ?? '-',
                    $session->trainer?->name ?? '-',
                    $session->trainer?->nik ?? '-',
                    $session->trainer?->department ?? '-',
                    strtoupper((string) $session->plan_status),
                    $session->is_active ? 'AKTIF (LIVE)' : 'TERJADWAL',
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    private function validatedSchedule(Request $request): array
    {
        return $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'title' => ['required', 'string', 'max:150'],
            'session_date' => ['required', 'date'],
            'start_time' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'end_time' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/', 'after:start_time'],
            'trainer_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where(fn ($roleQuery) => $roleQuery
                        ->whereIn('role', ['trainer', 'lnd', 'admin'])
                        ->orWhere('is_trainer', true))),
            ],
            'location' => ['required', 'string', 'max:120'],
            'quota' => ['required', 'integer', 'min:1', 'max:1000'],
            'plan_status' => ['required', Rule::in(['approved', 'pending'])],
        ]);
    }

    private function ensureTrainerAvailable(array $data, ?AttendanceSession $ignoreSession = null): void
    {
        $result = $this->syncService->checkAvailability(
            (int) $data['trainer_id'],
            $data['session_date'],
            $data['start_time'],
            $data['end_time'],
            $ignoreSession?->id
        );

        if (!$result['available']) {
            throw ValidationException::withMessages([
                'trainer_id' => $result['message'],
            ]);
        }
    }

    private function filteredQuery(array $filters, bool $includePlanStatus): Builder
    {
        return AttendanceSession::query()
            ->when(($filters['search'] ?? '') !== '', function (Builder $query) use ($filters) {
                $search = trim($filters['search']);
                $query->where(function (Builder $searchQuery) use ($search) {
                    $searchQuery->where('title', 'like', "%{$search}%")
                        ->orWhereHas('course', fn (Builder $courseQuery) => $courseQuery->where('code', 'like', "%{$search}%")
                            ->orWhere('title', 'like', "%{$search}%"));
                });
            })
            ->when($includePlanStatus && !empty($filters['plan_status']), fn (Builder $query) => $query->where('plan_status', $filters['plan_status']))
            ->when(!empty($filters['training_category']), fn (Builder $query) => $query->whereHas(
                'course',
                fn (Builder $courseQuery) => $courseQuery->where('training_category', $filters['training_category'])
            ))
            ->when(!empty($filters['month']), function (Builder $query) use ($filters) {
                $month = Carbon::createFromFormat('!Y-m', $filters['month']);
                $query->whereBetween('session_date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()]);
            })
            ->when(!empty($filters['date_from']), fn (Builder $query) => $query->whereDate('session_date', '>=', $filters['date_from']))
            ->when(!empty($filters['date_to']), fn (Builder $query) => $query->whereDate('session_date', '<=', $filters['date_to']));
    }
}
