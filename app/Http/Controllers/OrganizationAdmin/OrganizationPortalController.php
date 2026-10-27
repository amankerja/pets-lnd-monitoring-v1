<?php

namespace App\Http\Controllers\OrganizationAdmin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Organization;
use App\Models\Skill;
use App\Models\TrainingRequest;
use App\Models\User;
use App\Traits\HasTrainingFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationPortalController extends Controller
{
    use HasTrainingFilters;

    /**
     * Query Eloquent Presisi untuk mendeteksi sertifikat yang sudah Expired atau Akan Expired (<= 60 hari):
     * 1. Multi-Tenant Scoped sesuai `organization_id` milik `dept_admin` / `contractor_admin` (atau global untuk `lnd`).
     * 2. Anti-False-Alarm (`whereNotExists`): Hanya mengambil sertifikat terbaru per `(user_id, course_id)`.
     * 3. Anti-Double-Request Spam: Mengecek lookup map `alreadyRequestedMap` dari `training_requests` aktif.
     */
    protected function buildExpiringCertificatesList(User $actor, array $filters = []): Collection
    {
        $thresholdDate = now()->addDays(60)->toDateString();

        // 1. Ambil daftar pengajuan pembaruan yang sedang berjalan (aktif) agar tidak diajukan 2x
        $activeRequests = TrainingRequest::query()
            ->when(
                !in_array($actor->role, ['lnd', 'admin'], true),
                fn ($q) => $q->where('organization_id', $actor->organization_id)
            )
            ->where('request_type', 'renewal_refreshment')
            ->whereIn('status', ['pending_lnd', 'approved', 'scheduled'])
            ->get(['course_id', 'participant_ids']);

        // Buat lookup map: "courseId_userId" => true
        $alreadyRequestedMap = [];
        foreach ($activeRequests as $req) {
            foreach ($req->participant_ids ?? [] as $uid) {
                $alreadyRequestedMap["{$req->course_id}_{$uid}"] = true;
            }
        }

        // 2. Query Sertifikat yang Expired / H-60 (Hanya ambil sertifikat terbaru per user & course)
        $expiringQuery = Enrollment::query()
            ->with(['user.organization', 'course'])
            ->whereNotNull('certificate_expires_at')
            ->whereDate('certificate_expires_at', '<=', $thresholdDate)
            // Filter Multi-Tenant: Kunci hanya untuk organisasi milik Admin Dept / Kontraktor
            ->whereHas('user', function ($q) use ($actor) {
                $q->where('role', 'peserta');
                if (in_array($actor->role, ['dept_admin', 'contractor_admin'], true)) {
                    if ($actor->organization_id !== null) {
                        $q->where('organization_id', $actor->organization_id);
                    } else {
                        $q->where('department', $actor->department);
                    }
                }
            })
            // Subquery Anti-False-Alarm: Pastikan tidak ada enrollment di course yang sama dengan masa berlaku lebih baru
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('enrollments as newer')
                    ->whereColumn('newer.user_id', 'enrollments.user_id')
                    ->whereColumn('newer.course_id', 'enrollments.course_id')
                    ->whereColumn('newer.certificate_expires_at', '>', 'enrollments.certificate_expires_at');
            });

        if (!empty($filters)) {
            $this->applyEnrollmentFilters($expiringQuery, $filters, $actor, 'enrollments.certificate_expires_at');
        }

        return $expiringQuery
            ->orderBy('certificate_expires_at', 'asc')
            ->get()
            ->map(function (Enrollment $enrollment) use ($alreadyRequestedMap) {
                $daysLeft = (int) now()->startOfDay()->diffInDays($enrollment->certificate_expires_at->startOfDay(), false);

                // Kategori tingkat keparahan (Severity)
                $severity = match (true) {
                    $daysLeft < 0 => 'expired',    // Sudah lewat masa berlaku (Merah Pekat)
                    $daysLeft <= 30 => 'critical', // <= 30 Hari (Oranye/Merah)
                    default => 'warning',          // 31 - 60 Hari (Kuning)
                };

                $key = "{$enrollment->course_id}_{$enrollment->user_id}";

                return [
                    'enrollment_id' => $enrollment->id,
                    'user_id' => $enrollment->user_id,
                    'user_name' => $enrollment->user?->name ?? '-',
                    'user_nik' => $enrollment->user?->nik ?? '-',
                    'worker_name' => $enrollment->user?->name ?? '-',
                    'worker_nik' => $enrollment->user?->nik ?? '-',
                    'organization_name' => $enrollment->user?->organization?->name ?? ($enrollment->user?->department ?? '-'),
                    'organization_type' => $enrollment->user?->organization?->type ?? 'internal_dept',
                    'course_id' => $enrollment->course_id,
                    'course_code' => $enrollment->course?->code ?? '-',
                    'course_title' => $enrollment->course?->title ?? '-',
                    'is_mandatory' => (bool) ($enrollment->course?->is_mandatory ?? true),
                    'training_source' => $enrollment->course?->training_source ?? 'internal',
                    'training_category' => $enrollment->course?->training_category ?? 'Safety',
                    'certificate_number' => $enrollment->certificate_number
                        ?? sprintf('CERT/PANI-LND/%s/%04d', now()->format('Y'), $enrollment->id),
                    'expires_at' => $enrollment->certificate_expires_at->format('d M Y'),
                    'certificate_expires_at' => $enrollment->certificate_expires_at->format('d M Y'),
                    'days_left' => $daysLeft,
                    'days_until_expiry' => $daysLeft,
                    'severity' => $severity,
                    'is_requested' => isset($alreadyRequestedMap[$key]),
                ];
            });
    }

    /**
     * Halaman Standalone Radar Masa Berlaku Sertifikat (H-60 & Expired).
     */
    public function renewalAlerts(Request $request): Response
    {
        $actor = $request->user();
        $filters = $this->extractTrainingFilters($request);
        $expiringEnrollments = $this->buildExpiringCertificatesList($actor, $filters);

        return Inertia::render('OrganizationAdmin/RenewalAlerts', [
            'expiringList' => $expiringEnrollments,
            'summary' => [
                'expired_count' => $expiringEnrollments->where('severity', 'expired')->count(),
                'critical_count' => $expiringEnrollments->where('severity', 'critical')->count(),
                'warning_count' => $expiringEnrollments->where('severity', 'warning')->count(),
            ],
            'filters' => $filters,
            'filterOptions' => $this->buildTrainingFilterOptions($actor),
        ]);
    }

    /**
     * Portal Terpadu 3-Tab untuk `dept_admin`, `contractor_admin`, dan `lnd`.
     */
    public function index(Request $request): Response
    {
        $actor = $request->user();
        $organization = $actor->organization;
        $filters = $this->extractTrainingFilters($request);

        // 1. Daftar sertifikat yang sudah Expired atau Akan Expired dalam <= 60 hari (Eloquent Presisi)
        $expiringEnrollments = $this->buildExpiringCertificatesList($actor, $filters);
        $summary = [
            'expired_count' => $expiringEnrollments->where('severity', 'expired')->count(),
            'critical_count' => $expiringEnrollments->where('severity', 'critical')->count(),
            'warning_count' => $expiringEnrollments->where('severity', 'warning')->count(),
        ];

        // 2. Daftar Pekerja di bawah Organisasi/Departemen/Kontraktor ini (Terkunci via scopeAccessibleBy + Filter Global)
        $workersQuery = User::query()
            ->role('peserta')
            ->accessibleBy($actor)
            ->with([
                'organization:id,code,name,type',
                'jobPosition:id,position_title,entity',
                'enrollments.course:id,code,title,start_date,end_date,status,is_mandatory,training_source,training_category',
                'userSkills.skill:id,code,name',
            ]);

        $this->applyUserFilters($workersQuery, $filters, $actor);

        $workersPaginated = $workersQuery
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(function (User $worker) {
                $enrollments = $worker->enrollments->map(function (Enrollment $e) {
                    $expiresAt = $e->certificate_expires_at;
                    $daysRemaining = $expiresAt
                        ? (int) now()->startOfDay()->diffInDays($expiresAt->startOfDay(), false)
                        : null;

                    return [
                        'id' => $e->id,
                        'course_id' => $e->course_id,
                        'course_code' => $e->course?->code,
                        'course_title' => $e->course?->title,
                        'is_mandatory' => (bool) ($e->course?->is_mandatory ?? true),
                        'training_source' => $e->course?->training_source ?? 'internal',
                        'training_category' => $e->course?->training_category ?? 'Safety',
                        'status' => $e->status,
                        'progress_percentage' => (int) $e->progress_percentage,
                        'certificate_number' => $e->certificate_number
                            ?? ((int) $e->progress_percentage >= 100
                                ? sprintf('CERT/PANI-LND/%s/%04d', now()->format('Y'), $e->id)
                                : null),
                        'certificate_issued_at' => $e->certificate_issued_at?->format('d M Y'),
                        'certificate_expires_at' => $expiresAt?->format('d M Y'),
                        'days_until_expiry' => $daysRemaining,
                        'is_expiring_soon' => $daysRemaining !== null && $daysRemaining <= 60,
                    ];
                });

                $deficitSkillsCount = $worker->userSkills
                    ->filter(fn ($us) => (int) $us->current_level < (int) $us->target_level)
                    ->count();

                return [
                    'id' => $worker->id,
                    'nik' => $worker->nik,
                    'name' => $worker->name,
                    'email' => $worker->email,
                    'department' => $worker->department,
                    'position' => $worker->jobPosition?->position_title,
                    'entity' => $worker->jobPosition?->entity,
                    'organization_name' => $worker->organization?->name ?? $worker->department,
                    'organization_type' => $worker->organization?->type ?? 'internal_dept',
                    'deficit_skills_count' => $deficitSkillsCount,
                    'enrollments' => $enrollments,
                    'report_print_url' => route('admin.participants.report-print', ['user' => $worker->id]),
                ];
            });

        // 3. Riwayat Pengajuan TNA & Training Pembaruan (Refreshment) dari Organisasi ini
        $trainingRequestsQuery = TrainingRequest::query()
            ->accessibleBy($actor)
            ->with([
                'organization:id,code,name,type',
                'requester:id,name,nik',
                'skill:id,code,name',
                'course:id,code,title,is_mandatory,training_source,training_category',
            ]);

        $this->applyTrainingRequestFilters($trainingRequestsQuery, $filters, $actor, 'training_requests.created_at');

        $trainingRequestsPaginated = $trainingRequestsQuery
            ->orderByDesc('created_at')
            ->paginate(25, ['*'], 'tna_page')
            ->withQueryString()
            ->through(fn (TrainingRequest $tr) => [
                'id' => $tr->id,
                'organization_name' => $tr->organization?->name ?? '-',
                'organization_type' => $tr->organization?->type ?? 'internal_dept',
                'requester_name' => $tr->requester?->name ?? '-',
                'request_type' => $tr->request_type,
                'skill_code' => $tr->skill?->code,
                'skill_name' => $tr->skill?->name,
                'course_code' => $tr->course?->code,
                'course_title' => $tr->course?->title,
                'target_participants_count' => (int) $tr->target_participants_count,
                'participant_ids' => $tr->participant_ids ?? [],
                'reason_notes' => $tr->reason_notes,
                'status' => $tr->status,
                'created_at' => $tr->created_at?->format('d M Y H:i'),
            ]);

        // 4. Katalog Kelas Aktif & Daftar Skill
        $activeCourses = Course::query()
            ->active()
            ->with(['trainer:id,name'])
            ->orderBy('start_date')
            ->get()
            ->map(fn (Course $c) => [
                'id' => $c->id,
                'code' => $c->code,
                'title' => $c->title,
                'is_mandatory' => (bool) $c->is_mandatory,
                'training_source' => $c->training_source,
                'training_category' => $c->training_category,
                'trainer_name' => $c->trainer?->name ?? 'Trainer L&D',
                'start_date' => $c->start_date?->format('d M Y'),
                'end_date' => $c->end_date?->format('d M Y'),
            ]);

        $skills = Skill::query()
            ->select(['id', 'code', 'name', 'category'])
            ->orderBy('category')
            ->orderBy('code')
            ->get();

        return Inertia::render('OrganizationAdmin/Portal', [
            'organizationInfo' => [
                'id' => $organization?->id,
                'code' => $organization?->code ?? 'ORG-INTERNAL',
                'name' => $organization?->name ?? ($actor->department ?: 'Seluruh Organisasi (Mode Super Admin LnD)'),
                'type' => $organization?->type ?? ($actor->role === 'contractor_admin' ? 'contractor' : 'internal_dept'),
                'actor_role' => $actor->role,
            ],
            'workers' => $workersPaginated->items(),
            'workersPagination' => $workersPaginated,
            'expiringList' => $expiringEnrollments,
            'expiringCertificates' => $expiringEnrollments,
            'summary' => $summary,
            'trainingRequests' => $trainingRequestsPaginated->items(),
            'trainingRequestsPagination' => $trainingRequestsPaginated,
            'activeCourses' => $activeCourses,
            'skills' => $skills,
            'filters' => $filters,
            'filterOptions' => $this->buildTrainingFilterOptions($actor),
        ]);
    }

    /**
     * Endpoint Submit Pengajuan Training Pembaruan (Bisa Satuan atau Sekaligus/Batch per Course)
     */
    public function requestRenewal(Request $request): RedirectResponse
    {
        $actor = $request->user();

        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'participant_ids' => 'required|array|min:1',
            'participant_ids.*' => 'integer|exists:users,id',
            'reason_notes' => 'nullable|string|max:500',
        ]);

        // KEAMANAN: Pastikan semua participant_ids benar-benar milik organisasi si Admin (Anti-IDOR)
        $validUserIds = User::query()
            ->whereIn('id', $validated['participant_ids'])
            ->when(
                in_array($actor->role, ['dept_admin', 'contractor_admin'], true),
                function ($q) use ($actor) {
                    if ($actor->organization_id !== null) {
                        $q->where('organization_id', $actor->organization_id);
                    } else {
                        $q->where('department', $actor->department);
                    }
                }
            )
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->toArray();

        abort_if(
            count($validUserIds) !== count(array_unique($validated['participant_ids'])),
            403,
            'Terdapat data pekerja di luar wewenang organisasi Anda.'
        );

        $orgId = $actor->organization_id
            ?? User::whereKey($validUserIds[0])->value('organization_id')
            ?? Organization::query()->value('id');

        TrainingRequest::create([
            'organization_id' => $orgId,
            'requested_by' => $actor->id,
            'course_id' => (int) $validated['course_id'],
            'request_type' => 'renewal_refreshment',
            'target_participants_count' => count($validUserIds),
            'participant_ids' => $validUserIds,
            'reason_notes' => $validated['reason_notes'] ?? 'Pengajuan pembaruan sertifikat (Masa berlaku H-60 / Expired).',
            'status' => 'pending_lnd',
        ]);

        return back()->with('success', count($validUserIds) . ' pekerja berhasil diajukan ke Tim LnD untuk Training Pembaruan.');
    }

    /**
     * Tab 1: Pendaftaran Kolektif Pekerja ke Jadwal Training oleh Admin Departemen / Admin Kontraktor.
     */
    public function bulkEnroll(Request $request): RedirectResponse
    {
        $actor = $request->user();

        $validated = $request->validate([
            'course_id' => ['nullable', 'integer', 'exists:courses,id', 'required_without:course_ids'],
            'course_ids' => ['nullable', 'array', 'min:1', 'required_without:course_id'],
            'course_ids.*' => ['required', 'integer', 'distinct', 'exists:courses,id'],
            'participant_ids' => ['required', 'array', 'min:1'],
            'participant_ids.*' => ['required', 'integer', 'exists:users,id'],
        ]);

        $courseIds = collect($validated['course_ids'] ?? [])
            ->when(isset($validated['course_id']), fn ($ids) => $ids->push($validated['course_id']))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $activeCourseIds = Course::query()
            ->whereIn('id', $courseIds)
            ->where('status', 'active')
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        if ($activeCourseIds->count() !== $courseIds->count()) {
            throw ValidationException::withMessages([
                'course_ids' => 'Pilih hanya program training yang sedang aktif.',
            ]);
        }

        $allowedWorkerIds = User::query()
            ->role('peserta')
            ->accessibleBy($actor)
            ->whereIn('id', $validated['participant_ids'])
            ->pluck('id')
            ->all();

        if (count($allowedWorkerIds) !== count(array_unique($validated['participant_ids']))) {
            throw ValidationException::withMessages([
                'participant_ids' => 'Akses ditolak: Terdapat pekerja yang bukan berasal dari departemen/perusahaan Anda.',
            ]);
        }

        $initialStatus = in_array($actor->role, ['lnd', 'admin'], true) ? 'approved' : 'pending';

        $enrolledCount = DB::transaction(function () use ($allowedWorkerIds, $courseIds, $actor, $initialStatus) {
            $count = 0;
            foreach ($courseIds as $courseId) {
                foreach ($allowedWorkerIds as $workerId) {
                    $enrollment = Enrollment::firstOrNew(
                        [
                            'user_id' => $workerId,
                            'course_id' => $courseId,
                        ]
                    );

                    if (!$enrollment->exists || $enrollment->status === 'rejected') {
                        $enrollment->fill([
                            'registered_by' => $actor->id,
                            'status' => $initialStatus,
                            'progress_percentage' => 0,
                        ]);
                        $enrollment->save();
                        $count++;
                    }
                }
            }

            return $count;
        });

        return back()->with('success', sprintf(
            '%d pendaftaran berhasil dikirim untuk %d pekerja dan %d program training.',
            $enrolledCount,
            count($allowedWorkerIds),
            $courseIds->count()
        ));
    }

    /**
     * Tab 2: Pengajuan TNA (Training Needs Analysis) Baru atau Training Pembaruan.
     */
    public function storeTrainingRequest(Request $request): RedirectResponse
    {
        $actor = $request->user();

        $validated = $request->validate([
            'request_type' => ['required', 'in:new_tna,renewal_refreshment'],
            'skill_id' => ['nullable', 'integer', 'exists:skills,id'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'participant_ids' => ['required', 'array', 'min:1'],
            'participant_ids.*' => ['required', 'integer', 'exists:users,id'],
            'reason_notes' => ['required', 'string', 'max:1000'],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
        ]);

        $allowedWorkerIds = User::query()
            ->role('peserta')
            ->accessibleBy($actor)
            ->whereIn('id', $validated['participant_ids'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if (count($allowedWorkerIds) !== count(array_unique($validated['participant_ids']))) {
            throw ValidationException::withMessages([
                'participant_ids' => 'Akses ditolak: Anda hanya dapat mengajukan TNA untuk pekerja di dalam organisasi Anda sendiri.',
            ]);
        }

        $orgId = $actor->organization_id
            ?? ($validated['organization_id'] ?? Organization::query()->value('id'));

        if (!$orgId) {
            throw ValidationException::withMessages([
                'organization_id' => 'Organisasi pengaju belum terasosiasi pada akun Anda.',
            ]);
        }

        DB::transaction(function () use ($validated, $actor, $orgId, $allowedWorkerIds) {
            TrainingRequest::create([
                'organization_id' => $orgId,
                'requested_by' => $actor->id,
                'skill_id' => $validated['skill_id'] ?? null,
                'course_id' => $validated['course_id'] ?? null,
                'request_type' => $validated['request_type'],
                'target_participants_count' => count($allowedWorkerIds),
                'participant_ids' => $allowedWorkerIds,
                'reason_notes' => $validated['reason_notes'],
                'status' => 'pending_lnd',
            ]);
        });

        return back()->with('success', 'Pengajuan TNA / Training Pembaruan berhasil dikirim ke Tim LnD.');
    }

    /**
     * Approval / Penjadwalan Pengajuan TNA oleh Tim LnD (Super Admin).
     */
    public function updateRequestStatus(Request $request, TrainingRequest $trainingRequest): RedirectResponse
    {
        $actor = $request->user();
        abort_if(
            !in_array($actor->role, ['lnd', 'admin'], true),
            403,
            'Hanya Tim LnD yang berwenang menyetujui atau menjadwalkan pengajuan TNA.'
        );

        $validated = $request->validate([
            'status' => ['required', 'in:pending_lnd,approved,scheduled,completed,rejected'],
        ]);

        DB::transaction(function () use ($trainingRequest, $validated, $actor) {
            $trainingRequest->update([
                'status' => $validated['status'],
            ]);

            if (in_array($validated['status'], ['approved', 'scheduled'], true) && $trainingRequest->course_id && is_array($trainingRequest->participant_ids)) {
                foreach ($trainingRequest->participant_ids as $workerId) {
                    Enrollment::firstOrCreate(
                        [
                            'user_id' => (int) $workerId,
                            'course_id' => $trainingRequest->course_id,
                        ],
                        [
                            'registered_by' => $actor->id,
                            'status' => 'approved',
                            'progress_percentage' => 0,
                        ]
                    );
                }
            }
        });

        return back()->with('success', 'Status pengajuan TNA berhasil diperbarui.');
    }
}
