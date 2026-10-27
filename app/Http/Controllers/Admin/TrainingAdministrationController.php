<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\ModuleProgress;
use App\Models\Organization;
use App\Models\Skill;
use App\Models\User;
use App\Models\UserSkill;
use App\Traits\HasTrainingFilters;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TrainingAdministrationController extends Controller
{
    use HasTrainingFilters;

    /**
     * Halaman Buku Induk Sertifikat (Certificate Ledger) & Administrasi Kelas Terpadu.
     * Dilengkapi Server-Side Filtering (<GlobalTableFilterBar />), Isolasi Multi-Tenant RBAC,
     * dan Perhitungan KPI Kepatuhan yang Kebal "Superseded Trap" (`is_superseded = false`).
     */
    public function index(Request $request): Response
    {
        $actor = $request->user();
        $validated = $request->validate([
            'session_id' => ['nullable', 'integer', 'exists:attendance_sessions,id'],
        ]);
        $selectedSession = !empty($validated['session_id'])
            ? AttendanceSession::query()->with('course:id,code,title')->findOrFail($validated['session_id'])
            : null;
        $today = now()->startOfDay();
        $warningThreshold = now()->addDays(60)->endOfDay();
        $filters = $this->extractTrainingFilters($request);

        // 1. Base Query dengan Eager Loading & Isolasi Multi-Tenant RBAC
        $baseQuery = Enrollment::query()
            ->accessibleBy($actor)
            ->when($selectedSession, fn ($query) => $query->where('attendance_session_id', $selectedSession->id))
            ->with([
                'user:id,name,nik,email,department,organization_id,job_position_id',
                'user.organization:id,code,name,type',
                'user.jobPosition:id,code,level_name,position_title',
                'course:id,code,title,is_mandatory,training_source,training_category,validity_months,skill_id,granted_skill_level',
                'course.skill:id,code,name,category',
            ]);

        // 2. Terapkan 10 Atribut Filter dari <GlobalTableFilterBar />
        $this->applyEnrollmentFilters($baseQuery, $filters, $actor, 'enrollments.created_at');

        // 3. Hitung 4 KPI Cards di atas tabel (Single Aggregated Query, Kebal Superseded Trap)
        $todayDate = $today->toDateString();
        $warningDate = $warningThreshold->toDateString();

        $kpiRaw = (clone $baseQuery)
            ->selectRaw("
                COUNT(*) as total_records,
                COUNT(CASE WHEN is_superseded = 0 AND completion_status = 'passed' AND (certificate_expires_at IS NULL OR certificate_expires_at > ?) THEN 1 END) as valid_active,
                COUNT(CASE WHEN is_superseded = 0 AND completion_status = 'passed' AND certificate_expires_at >= ? AND certificate_expires_at <= ? THEN 1 END) as warning_h60,
                COUNT(CASE WHEN is_superseded = 0 AND (completion_status = 'failed' OR (completion_status = 'passed' AND certificate_expires_at IS NOT NULL AND certificate_expires_at < ?)) THEN 1 END) as non_compliant
            ", [$warningDate, $todayDate, $warningDate, $todayDate])
            ->first();

        $kpi = [
            'total_records' => (int) ($kpiRaw?->total_records ?? 0),
            'valid_active' => (int) ($kpiRaw?->valid_active ?? 0),
            'warning_h60' => (int) ($kpiRaw?->warning_h60 ?? 0),
            'non_compliant' => (int) ($kpiRaw?->non_compliant ?? 0),
        ];

        // 4. Paginate Buku Induk (25 baris per halaman) & injeksikan status visual
        $ledger = $baseQuery
            ->orderByRaw('CASE WHEN is_superseded = 0 THEN 0 ELSE 1 END')
            ->orderByDesc('enrollments.created_at')
            ->paginate(25)
            ->withQueryString()
            ->through(function (Enrollment $row) use ($today, $warningThreshold) {
                $expiresAt = $row->certificate_expires_at ? Carbon::parse($row->certificate_expires_at) : null;
                $daysRemaining = $expiresAt ? (int) $today->diffInDays($expiresAt, false) : null;

                if ($row->is_superseded) {
                    $validityBadge = 'SUPERSEDED';
                } elseif ($row->completion_status !== 'passed') {
                    $validityBadge = 'IN_PROGRESS';
                } elseif (!$expiresAt) {
                    $validityBadge = 'PERMANENT';
                } elseif ($expiresAt->lt($today)) {
                    $validityBadge = 'EXPIRED';
                } elseif ($expiresAt->lte($warningThreshold)) {
                    $validityBadge = 'WARNING_H60';
                } else {
                    $validityBadge = 'VALID';
                }

                $fileUrl = null;
                if (!empty($row->certificate_file_path)) {
                    $path = $row->certificate_file_path;
                    $fileUrl = (str_starts_with($path, 'http') || str_starts_with($path, '/'))
                        ? $path
                        : "/storage/{$path}";
                }

                return [
                    'id' => $row->id,
                    'user_id' => $row->user_id,
                    'course_id' => $row->course_id,
                    'user' => [
                        'id' => $row->user?->id,
                        'name' => $row->user?->name ?? '-',
                        'nik' => $row->user?->nik ?? '-',
                        'org_name' => $row->user?->organization?->name ?? ($row->user?->department ?? '-'),
                        'org_type' => $row->user?->organization?->type ?? 'internal_dept',
                        'position' => $row->user?->jobPosition?->position_title ?? '-',
                    ],
                    'course' => [
                        'id' => $row->course?->id,
                        'code' => $row->course?->code ?? '-',
                        'title' => $row->course?->title ?? '-',
                        'is_mandatory' => (bool) ($row->course?->is_mandatory ?? true),
                        'training_source' => $row->course?->training_source ?? 'internal',
                        'training_category' => $row->course?->training_category ?? 'Safety',
                        'validity_months' => $row->course?->validity_months,
                        'skill_name' => $row->course?->skill?->name ?? null,
                        'granted_level' => $row->course?->granted_skill_level ?? null,
                    ],
                    'enrollment_type' => $row->enrollment_type ?? 'regular',
                    'completion_status' => $row->completion_status ?? 'registered',
                    'progress_percentage' => (int) $row->progress_percentage,
                    'certificate_number' => $row->certificate_number,
                    'certificate_file_path' => $row->certificate_file_path,
                    'certificate_file_url' => $fileUrl,
                    'issued_at' => $row->certificate_issued_at ? Carbon::parse($row->certificate_issued_at)->format('d M Y') : '-',
                    'expires_at' => $expiresAt
                        ? $expiresAt->format('d M Y')
                        : ($row->completion_status === 'passed' ? 'Selamanya' : '-'),
                    'days_remaining' => $daysRemaining,
                    'validity_badge' => $validityBadge,
                    'is_superseded' => (bool) $row->is_superseded,
                    'print_report_url' => route('admin.participants.report-print', ['user' => $row->user_id]),
                    'verify_public_url' => $row->certificate_number
                        ? route('public.certificate.verify', ['certificate_number' => $row->certificate_number])
                        : null,
                ];
            });

        // 5. Data Master untuk Modal Pendaftaran Batch & Pencatatan Sertifikat Eksternal
        $courses = Course::query()
            ->select([
                'id',
                'code',
                'title',
                'is_mandatory',
                'training_source',
                'training_category',
                'validity_months',
                'skill_id',
                'granted_skill_level',
            ])
            ->with(['skill:id,code,name'])
            ->when(
                in_array($actor->role, ['lnd', 'admin'], true),
                fn ($query) => $query->with('externalTrainingLogistic')
            )
            ->orderBy('code')
            ->get();

        $workers = User::query()
            ->role('peserta')
            ->accessibleBy($actor)
            ->select(['id', 'name', 'nik', 'department', 'organization_id', 'job_position_id'])
            ->with([
                'organization:id,code,name,type',
                'jobPosition:id,position_title,entity',
                'enrollments' => fn ($q) => $q
                    ->where('is_superseded', false)
                    ->select([
                        'id',
                        'user_id',
                        'course_id',
                        'completion_status',
                        'certificate_number',
                        'certificate_expires_at',
                    ]),
            ])
            ->orderBy('name')
            ->get()
            ->map(function (User $w) {
                $courseCertMap = [];
                foreach ($w->enrollments as $enr) {
                    $hasCert = $enr->completion_status === 'passed' || !empty($enr->certificate_number);
                    if (!$hasCert) {
                        continue;
                    }
                    $exp = $enr->certificate_expires_at;
                    $days = $exp ? (int) now()->startOfDay()->diffInDays($exp->startOfDay(), false) : null;
                    $state = match (true) {
                        $exp === null => 'active_permanent',
                        $days > 60 => 'active_valid',
                        $days >= 0 => 'expiring_h60',
                        default => 'expired',
                    };
                    $courseCertMap[$enr->course_id] = [
                        'certificate_number' => $enr->certificate_number,
                        'expires_at' => $exp?->format('d M Y') ?? 'Selamanya',
                        'days_left' => $days,
                        'state' => $state,
                    ];
                }

                return [
                    'id' => $w->id,
                    'name' => $w->name,
                    'nik' => $w->nik,
                    'department' => $w->department,
                    'position' => $w->jobPosition?->position_title,
                    'entity' => $w->jobPosition?->entity,
                    'organization_name' => $w->organization?->name ?? ($w->department ?? '-'),
                    'organization_type' => $w->organization?->type ?? 'internal_dept',
                    'course_cert_map' => $courseCertMap,
                ];
            });

        $filterOptions = $this->buildTrainingFilterOptions($actor);

        return Inertia::render('Training/AdministrationAndCertificates', [
            'ledger' => $ledger,
            'kpi' => $kpi,
            'filters' => $filters,
            'departments' => Organization::where('type', 'internal_dept')->orderBy('name')->get(['id', 'name', 'code']),
            'companies' => Organization::where('type', 'contractor')->orderBy('name')->get(['id', 'name', 'code']),
            'courseCodes' => Course::orderBy('code')->get(['id', 'code', 'title']),
            'filterOptions' => $filterOptions,
            'courses' => $courses,
            'workers' => $workers,
            'selectedSession' => $selectedSession ? [
                'id' => $selectedSession->id,
                'title' => $selectedSession->title,
                'course_id' => $selectedSession->course_id,
                'course' => $selectedSession->course,
            ] : null,
            'skills' => Skill::orderBy('category')->orderBy('code')->get(['id', 'code', 'name', 'category']),
        ]);
    }

    /**
     * TUGAS 2: Engine Otomatisasi Kelulusan Massal, Penerbitan Sertifikat Anti-Race-Condition,
     * Penandaan Sertifikat Lama (`is_superseded = true`), & Auto-Upgrade Skill Matrix (`user_skills`).
     */
    public function batchGraduateAndCertify(Request $request): RedirectResponse
    {
        $actor = $request->user();
        abort_if(
            !in_array($actor->role, ['lnd', 'admin', 'trainer'], true),
            403,
            'Hanya Tim LnD atau Trainer yang berhak menerbitkan kelulusan & sertifikat.'
        );

        $validated = $request->validate([
            'enrollment_ids' => ['required', 'array', 'min:1'],
            'enrollment_ids.*' => ['integer', 'exists:enrollments,id'],
            'completed_at' => ['required', 'date'],
            'external_cert_number' => ['nullable', 'string', 'max:120'],
            'certificate_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $completedDate = Carbon::parse($validated['completed_at']);
        $uploadedFilePath = null;

        if ($request->hasFile('certificate_file')) {
            $uploadedFilePath = $request->file('certificate_file')->store('certificates/external', 'public');
        }

        $graduatedCount = 0;

        DB::transaction(function () use ($validated, $completedDate, $uploadedFilePath, $actor, &$graduatedCount) {
            // Lock rows yang akan diproses untuk mencegah race condition
            $enrollments = Enrollment::with('course')
                ->whereIn('id', $validated['enrollment_ids'])
                ->lockForUpdate()
                ->get();

            foreach ($enrollments as $enrollment) {
                $course = $enrollment->course;
                if (!$course) {
                    continue;
                }

                // 1. Hitung Tanggal Kadaluarsa Otomatis dari Master Course (`validity_months`)
                $expiresAt = $course->validity_months !== null
                    ? $completedDate->copy()->addMonths((int) $course->validity_months)->toDateString()
                    : null;

                // 2. Generate Nomor Sertifikat Unik Anti-Tabrakan (menggunakan Row Lock)
                $certNumber = !empty($validated['external_cert_number']) && count($validated['enrollment_ids']) === 1
                    ? trim($validated['external_cert_number'])
                    : $this->generateUniqueCertificateNumber($course->code, $completedDate);

                // 3. Tandai sertifikat LAMA milik pekerja ini di course yang sama sebagai SUPERSEDED
                //    agar sertifikat lama yang sudah mati tidak lagi dihitung sebagai EXPIRED di KPI!
                Enrollment::where('user_id', $enrollment->user_id)
                    ->where('course_id', $course->id)
                    ->where('id', '!=', $enrollment->id)
                    ->where('completion_status', 'passed')
                    ->update(['is_superseded' => true]);

                // 4. Tandai seluruh modul kelas selesai (jika ada)
                $moduleIds = DB::table('course_modules')
                    ->where('course_id', $course->id)
                    ->pluck('id');

                foreach ($moduleIds as $modId) {
                    ModuleProgress::updateOrCreate(
                        [
                            'enrollment_id' => $enrollment->id,
                            'course_module_id' => $modId,
                        ],
                        [
                            'is_completed' => true,
                            'completed_at' => now(),
                        ]
                    );
                }

                // 5. Update Record Enrollment Saat Ini
                $enrollment->update([
                    'status' => 'approved',
                    'completion_status' => 'passed',
                    'progress_percentage' => 100,
                    'completed_at' => $completedDate->toDateString(),
                    'certificate_number' => $certNumber,
                    'certificate_file_path' => $uploadedFilePath ?? $enrollment->certificate_file_path,
                    'certificate_issued_at' => $completedDate->toDateString(),
                    'certificate_expires_at' => $expiresAt,
                    'is_superseded' => false,
                ]);

                // 6. AUTO-UPGRADE SKILL MATRIX (`user_skills`) & SINKRONISASI MASA BERLAKU (`valid_until`)
                if ($course->skill_id) {
                    $existingSkill = UserSkill::where('user_id', $enrollment->user_id)
                        ->where('skill_id', $course->skill_id)
                        ->first();

                    $grantedLevel = max(1, min(5, (int) ($course->granted_skill_level ?: 3)));
                    $newLevel = $existingSkill
                        ? max((int) $existingSkill->current_level, $grantedLevel)
                        : $grantedLevel;

                    UserSkill::updateOrCreate(
                        [
                            'user_id' => $enrollment->user_id,
                            'skill_id' => $course->skill_id,
                        ],
                        [
                            'current_level' => $newLevel,
                            'target_level' => $existingSkill ? $existingSkill->target_level : max(3, $grantedLevel),
                            'valid_until' => $expiresAt,
                            'last_certificate_number' => $certNumber,
                            'updated_by' => $actor->id,
                        ]
                    );
                }

                $enrollment->syncCompletedRenewalRequests();
                $graduatedCount++;
            }
        });

        return back()->with(
            'success',
            "Berhasil meluluskan & menerbitkan {$graduatedCount} sertifikat resmi, memperbarui status sertifikat lama (Superseded), serta meng-upgrade Skill Matrix otomatis."
        );
    }

    /**
     * Alias method untuk kompatibilitas pemanggilan route batch-graduate.
     */
    public function batchGraduate(Request $request): RedirectResponse
    {
        return $this->batchGraduateAndCertify($request);
    }

    /**
     * Generator Nomor Sertifikat dengan Pessimistic Locking (`lockForUpdate()`)
     * Format: `CERT/{KODE_COURSE}/{YYYY}/{MM}/{0001}`
     */
    private function generateUniqueCertificateNumber(string $courseCode, Carbon $date): string
    {
        $cleanCode = strtoupper(trim($courseCode ?: 'CRS-GEN'));
        $prefix = sprintf('CERT/%s/%s/%s/', $cleanCode, $date->format('Y'), $date->format('m'));

        // Kunci baris terakhir dengan prefix yang sama agar transaksi konkuren mengantre dengan aman
        $lastCert = Enrollment::where('certificate_number', 'like', "{$prefix}%")
            ->lockForUpdate()
            ->orderByDesc('certificate_number')
            ->value('certificate_number');

        if ($lastCert) {
            $lastSequence = (int) substr($lastCert, -4);
            $nextSequence = $lastSequence + 1;
        } else {
            $nextSequence = 1;
        }

        $candidate = $prefix . str_pad((string) $nextSequence, 4, '0', STR_PAD_LEFT);

        while (Enrollment::where('certificate_number', $candidate)->lockForUpdate()->exists()) {
            $nextSequence++;
            $candidate = $prefix . str_pad((string) $nextSequence, 4, '0', STR_PAD_LEFT);
        }

        return $candidate;
    }

    /**
     * Pendaftaran Peserta Batch (Individu, Multi-Select, atau Upload CSV)
     * Dilengkapi Deteksi Otomatis Sertifikat Aktif (> H-60) untuk mencegah duplikasi pendaftaran!
     */
    public function storeEnrollment(Request $request): RedirectResponse
    {
        $actor = $request->user();

        $validated = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'attendance_session_id' => ['nullable', 'integer', 'exists:attendance_sessions,id'],
            'participant_ids' => ['nullable', 'array'],
            'participant_ids.*' => ['integer', 'exists:users,id'],
            'csv_file' => ['nullable', 'file', 'mimes:csv,txt', 'max:5120'],
            'force_enroll' => ['nullable', 'boolean'],
        ]);

        $course = Course::findOrFail((int) $validated['course_id']);
        $session = !empty($validated['attendance_session_id'])
            ? AttendanceSession::query()->findOrFail($validated['attendance_session_id'])
            : null;

        if ($session && (int) $session->course_id !== (int) $course->id) {
            throw ValidationException::withMessages([
                'attendance_session_id' => 'Sesi yang dipilih tidak sesuai dengan program training.',
            ]);
        }

        $targetUserIds = collect($validated['participant_ids'] ?? [])->map(fn ($id) => (int) $id)->all();

        if ($request->hasFile('csv_file')) {
            $handle = fopen($request->file('csv_file')->getRealPath(), 'r');
            $csvNiks = [];
            if ($handle !== false) {
                $rowNum = 0;
                while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                    $rowNum++;
                    if ($rowNum === 1 && isset($row[0])) {
                        $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $row[0]);
                    }
                    $nikCol = strtoupper(trim((string) ($row[0] ?? '')));
                    if ($nikCol !== '' && !in_array($nikCol, ['NIK', 'NO_INDUK', 'EMPLOYEE_ID'], true)) {
                        $csvNiks[] = $nikCol;
                    }
                }
                fclose($handle);
            }

            if (!empty($csvNiks)) {
                $csvUserIds = User::query()
                    ->accessibleBy($actor)
                    ->whereIn('nik', $csvNiks)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
                $targetUserIds = array_values(array_unique(array_merge($targetUserIds, $csvUserIds)));
            }
        }

        if (empty($targetUserIds)) {
            throw ValidationException::withMessages([
                'participant_ids' => 'Pilih minimal 1 pekerja atau unggah file CSV NIK yang valid.',
            ]);
        }

        $allowedWorkers = User::query()
            ->accessibleBy($actor)
            ->whereIn('id', $targetUserIds)
            ->get(['id', 'name', 'nik']);

        abort_if(
            $allowedWorkers->count() !== count(array_unique($targetUserIds)),
            403,
            'Akses ditolak: Terdapat pekerja di luar cakupan departemen/perusahaan Anda.'
        );

        $forceEnroll = $request->boolean('force_enroll');
        $h60ThresholdDate = now()->addDays(60)->toDateString();

        // Ambil enrollment aktif (tidak superseded) milik pekerja pada course ini
        $existingActiveEnrollments = Enrollment::query()
            ->where('course_id', $course->id)
            ->where('is_superseded', false)
            ->whereIn('user_id', $allowedWorkers->pluck('id'))
            ->orderByDesc('id')
            ->get()
            ->groupBy('user_id')
            ->map(fn ($enrollments) => $session
                ? ($enrollments->firstWhere('attendance_session_id', $session->id) ?? $enrollments->last())
                : $enrollments->last());

        $skippedActiveCertWorkers = [];
        $enrolledCount = 0;
        $refreshmentCount = 0;
        $initialStatus = in_array($actor->role, ['lnd', 'admin'], true) ? 'approved' : 'pending';

        DB::transaction(function () use (
            $allowedWorkers,
            $existingActiveEnrollments,
            $course,
            $session,
            $actor,
            $initialStatus,
            $h60ThresholdDate,
            $forceEnroll,
            &$skippedActiveCertWorkers,
            &$enrolledCount,
            &$refreshmentCount
        ) {
            if ($session) {
                $lockedSession = AttendanceSession::query()
                    ->whereKey($session->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $alreadyRegistered = Enrollment::query()
                    ->where('attendance_session_id', $lockedSession->id)
                    ->whereIn('status', ['pending', 'approved'])
                    ->where('is_superseded', false)
                    ->distinct()
                    ->count('user_id');
                $alreadyRegisteredUserIds = Enrollment::query()
                    ->where('attendance_session_id', $lockedSession->id)
                    ->whereIn('status', ['pending', 'approved'])
                    ->where('is_superseded', false)
                    ->whereIn('user_id', $allowedWorkers->pluck('id'))
                    ->distinct()
                    ->pluck('user_id');
                $newRegistrationCount = $allowedWorkers->pluck('id')->diff($alreadyRegisteredUserIds)->count();

                if ($lockedSession->quota !== null && $alreadyRegistered + $newRegistrationCount > $lockedSession->quota) {
                    throw ValidationException::withMessages([
                        'attendance_session_id' => 'Kuota sesi tidak mencukupi untuk jumlah peserta yang dipilih.',
                    ]);
                }
            }

            foreach ($allowedWorkers as $worker) {
                $existing = $existingActiveEnrollments->get($worker->id);

                if ($existing) {
                    $hasPassedOrCert = $existing->completion_status === 'passed' || !empty($existing->certificate_number);
                    $expiresAtStr = $existing->certificate_expires_at?->toDateString();

                    // Sertifikat masih aktif panjang (> H-60 atau Permanent)
                    $isStillLongActive = $hasPassedOrCert && ($expiresAtStr === null || $expiresAtStr > $h60ThresholdDate);

                    if ($isStillLongActive && !$forceEnroll) {
                        $expLabel = $existing->certificate_expires_at
                            ? $existing->certificate_expires_at->format('d M Y')
                            : 'Selamanya (Permanent)';
                        $skippedActiveCertWorkers[] = "{$worker->name} ({$worker->nik} - Aktif s/d {$expLabel})";
                        continue;
                    }

                    if ($hasPassedOrCert) {
                        // Jika sudah punya sertifikat yang masuk H-60 atau sudah Expired, buat baris pendaftaran baru bertipe 'refreshment'
                        // sehingga riwayat sertifikat lama tetap utuh di Buku Induk dan akan otomatis ditandai SUPERSEDED saat lulus nanti!
                        Enrollment::create([
                            'user_id' => $worker->id,
                            'course_id' => $course->id,
                            'attendance_session_id' => $session?->id,
                            'registered_by' => $actor->id,
                            'enrollment_type' => 'refreshment',
                            'status' => $initialStatus,
                            'completion_status' => 'registered',
                            'progress_percentage' => 0,
                            'is_superseded' => false,
                        ]);
                        $refreshmentCount++;
                        $enrolledCount++;
                    } else {
                        if (!$session || (int) $existing->attendance_session_id === (int) $session->id) {
                            $existing->update([
                                'registered_by' => $actor->id,
                                'status' => $initialStatus,
                            ]);
                        } else {
                            Enrollment::create([
                                'user_id' => $worker->id,
                                'course_id' => $course->id,
                                'attendance_session_id' => $session->id,
                                'registered_by' => $actor->id,
                                'enrollment_type' => 'regular',
                                'status' => $initialStatus,
                                'completion_status' => 'registered',
                                'progress_percentage' => 0,
                                'is_superseded' => false,
                            ]);
                        }
                        $enrolledCount++;
                    }
                } else {
                    Enrollment::create([
                        'user_id' => $worker->id,
                        'course_id' => $course->id,
                        'attendance_session_id' => $session?->id,
                        'registered_by' => $actor->id,
                        'enrollment_type' => 'regular',
                        'status' => $initialStatus,
                        'completion_status' => 'registered',
                        'progress_percentage' => 0,
                        'is_superseded' => false,
                    ]);
                    $enrolledCount++;
                }
            }
        });

        if ($enrolledCount === 0 && !empty($skippedActiveCertWorkers)) {
            return back()->with(
                'error',
                'Pendaftaran dibatalkan (Anti-Duplikasi): Pekerja berikut masih memiliki sertifikat aktif (> H-60) di kelas [' .
                $course->code . ']: ' . implode(', ', $skippedActiveCertWorkers)
            );
        }

        $msg = "Berhasil mendaftarkan {$enrolledCount} pekerja ke kelas [{$course->code}] {$course->title}";
        if ($refreshmentCount > 0) {
            $msg .= " ({$refreshmentCount} otomatis tercatat sebagai pendaftaran Refreshment H-60/Expired)";
        }
        $msg .= '.';

        if (!empty($skippedActiveCertWorkers)) {
            $msg .= ' Peringatan: ' . count($skippedActiveCertWorkers) . ' pekerja dilewati karena sertifikatnya masih aktif (> H-60): ' . implode(', ', $skippedActiveCertWorkers) . '.';
        }

        return back()->with('success', $msg);
    }

    /**
     * Pencatatan Sertifikat Training Eksternal & Historis (Individu / Batch + Upload File PDF)
     * Sekaligus menandai sertifikat lama sebagai `is_superseded = true` & menyinkronkan `valid_until` ke `user_skills`.
     */
    public function storeExternalCertificate(Request $request): RedirectResponse
    {
        $actor = $request->user();
        abort_if(
            !in_array($actor->role, ['lnd', 'admin', 'dept_admin', 'contractor_admin'], true),
            403,
            'Anda tidak memiliki izin untuk mencatat sertifikat eksternal/historis.'
        );

        $validated = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'enrollment_type' => ['required', 'in:regular,refreshment,historical_migration'],
            'participant_ids' => ['required', 'array', 'min:1'],
            'participant_ids.*' => ['required', 'integer', 'exists:users,id'],
            'certificate_number' => ['nullable', 'string', 'max:120'],
            'certificate_issued_at' => ['required', 'date'],
            'certificate_expires_at' => ['nullable', 'date', 'after_or_equal:certificate_issued_at'],
            'certificate_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $course = Course::findOrFail((int) $validated['course_id']);

        $allowedWorkers = User::query()
            ->accessibleBy($actor)
            ->whereIn('id', $validated['participant_ids'])
            ->get(['id', 'name', 'nik']);

        abort_if(
            $allowedWorkers->count() !== count(array_unique($validated['participant_ids'])),
            403,
            'Akses ditolak: Terdapat pekerja di luar cakupan organisasi Anda.'
        );

        $storedFilePath = null;
        if ($request->hasFile('certificate_file')) {
            $storedFilePath = $request->file('certificate_file')->store('certificates/external', 'public');
        }

        $issuedDate = Carbon::parse($validated['certificate_issued_at']);
        $issuedDateStr = $issuedDate->toDateString();
        $customExpiresStr = !empty($validated['certificate_expires_at'])
            ? Carbon::parse($validated['certificate_expires_at'])->toDateString()
            : ($course->validity_months !== null
                ? $issuedDate->copy()->addMonths((int) $course->validity_months)->toDateString()
                : null);

        $recordedCount = 0;

        DB::transaction(function () use (
            $allowedWorkers,
            $course,
            $actor,
            $validated,
            $issuedDate,
            $issuedDateStr,
            $customExpiresStr,
            $storedFilePath,
            &$recordedCount
        ) {
            $isMultiWorker = $allowedWorkers->count() > 1;
            $baseCustomNumber = trim((string) ($validated['certificate_number'] ?? ''));

            foreach ($allowedWorkers as $idx => $worker) {
                $certNumber = $baseCustomNumber !== ''
                    ? ($isMultiWorker ? sprintf('%s-%03d', rtrim($baseCustomNumber, '-/'), $idx + 1) : $baseCustomNumber)
                    : $this->generateUniqueCertificateNumber($course->code, $issuedDate);

                // Tandai sertifikat lama milik pekerja ini di course yang sama sebagai SUPERSEDED
                Enrollment::where('user_id', $worker->id)
                    ->where('course_id', $course->id)
                    ->where('completion_status', 'passed')
                    ->update(['is_superseded' => true]);

                // Buat record sertifikat baru
                Enrollment::create([
                    'user_id' => $worker->id,
                    'course_id' => $course->id,
                    'registered_by' => $actor->id,
                    'enrollment_type' => $validated['enrollment_type'],
                    'status' => 'approved',
                    'completion_status' => 'passed',
                    'progress_percentage' => 100,
                    'completed_at' => $issuedDateStr,
                    'certificate_number' => $certNumber,
                    'certificate_file_path' => $storedFilePath,
                    'certificate_issued_at' => $issuedDateStr,
                    'certificate_expires_at' => $customExpiresStr,
                    'is_superseded' => false,
                ]);

                // Sinkronisasi langsung ke Skill Matrix (`user_skills`) beserta `valid_until` & `last_certificate_number`
                if ($course->skill_id) {
                    $existingSkill = UserSkill::where('user_id', $worker->id)
                        ->where('skill_id', $course->skill_id)
                        ->first();

                    $grantedLevel = max(1, min(5, (int) ($course->granted_skill_level ?: 3)));
                    $newLevel = $existingSkill
                        ? max((int) $existingSkill->current_level, $grantedLevel)
                        : $grantedLevel;

                    UserSkill::updateOrCreate(
                        [
                            'user_id' => $worker->id,
                            'skill_id' => $course->skill_id,
                        ],
                        [
                            'current_level' => $newLevel,
                            'target_level' => $existingSkill ? $existingSkill->target_level : max(3, $grantedLevel),
                            'valid_until' => $customExpiresStr,
                            'last_certificate_number' => $certNumber,
                            'updated_by' => $actor->id,
                        ]
                    );
                }

                $recordedCount++;
            }
        });

        return back()->with(
            'success',
            "Berhasil mencatat {$recordedCount} sertifikat ({$course->code}) beserta sinkronisasi masa berlaku dan Skill Matrix."
        );
    }

    /**
     * Export CSV Buku Induk Sertifikat (Certificate Ledger) sesuai filter aktif (<GlobalTableFilterBar />).
     */
    public function exportLedgerCsv(Request $request): StreamedResponse
    {
        $actor = $request->user();
        $validated = $request->validate([
            'session_id' => ['nullable', 'integer', 'exists:attendance_sessions,id'],
        ]);
        $filters = $this->extractTrainingFilters($request);
        $sessionId = $validated['session_id'] ?? null;
        $fileName = 'LND_Certificate_Ledger_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($actor, $filters, $sessionId) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'NIK',
                'Nama Pekerja',
                'Departemen / Perusahaan',
                'Tipe Organisasi',
                'Kode Training',
                'Judul Training',
                'Sifat (Mandatory/Non)',
                'Sumber (Internal/External)',
                'Jenis / Bidang Training',
                'Tipe Pendaftaran',
                'Status Penyelesaian',
                'No. Sertifikat Resmi',
                'Tanggal Terbit',
                'Tanggal Kadaluarsa',
                'Sisa Hari Berlaku',
                'Status Kepatuhan / Masa Berlaku',
            ]);

            $query = Enrollment::query()
                ->accessibleBy($actor)
                ->when($sessionId, fn ($query) => $query->where('attendance_session_id', $sessionId))
                ->with([
                    'user:id,name,nik,department,organization_id',
                    'user.organization:id,code,name,type',
                    'course:id,code,title,is_mandatory,training_source,training_category,validity_months',
                ]);

            $this->applyEnrollmentFilters($query, $filters, $actor, 'enrollments.created_at');

            $query->orderBy('id')->chunk(200, function ($enrollments) use ($handle) {
                foreach ($enrollments as $enr) {
                    $exp = $enr->certificate_expires_at;
                    $days = $exp ? (int) now()->startOfDay()->diffInDays($exp->startOfDay(), false) : null;
                    $isPassed = $enr->completion_status === 'passed' || !empty($enr->certificate_number);

                    $validityStatus = match (true) {
                        (bool) $enr->is_superseded => 'SUPERSEDED (TELAH DIPERBARUI)',
                        !$isPassed => 'BELUM LULUS / IN PROGRESS',
                        $exp === null => 'PERMANENT / SELAMANYA',
                        $days < 0 => 'EXPIRED / GUGUR',
                        $days <= 60 => 'WARNING H-60 (SEGERA HABIS)',
                        default => 'VALID & AKTIF',
                    };

                    fputcsv($handle, [
                        $enr->user?->nik ?? '-',
                        $enr->user?->name ?? '-',
                        $enr->user?->organization?->name ?? ($enr->user?->department ?? '-'),
                        ($enr->user?->organization?->type ?? 'internal_dept') === 'contractor' ? 'KONTRAKTOR' : 'INTERNAL',
                        $enr->course?->code ?? '-',
                        $enr->course?->title ?? '-',
                        ($enr->course?->is_mandatory ?? true) ? 'MANDATORY' : 'NON-MANDATORY',
                        strtoupper($enr->course?->training_source ?? 'internal'),
                        $enr->course?->training_category ?? 'Safety',
                        strtoupper($enr->enrollment_type ?? 'regular'),
                        strtoupper($enr->completion_status ?? 'registered'),
                        $enr->certificate_number ?? '-',
                        $enr->certificate_issued_at?->format('Y-m-d') ?? '-',
                        $exp ? $exp->format('Y-m-d') : ($isPassed ? 'PERMANENT' : '-'),
                        $days !== null ? (string) $days : '-',
                        $validityStatus,
                    ]);
                }

                if (function_exists('flush')) {
                    flush();
                }
            });

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }
}
