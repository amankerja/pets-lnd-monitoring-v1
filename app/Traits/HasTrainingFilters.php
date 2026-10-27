<?php

namespace App\Traits;

use App\Models\Course;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait HasTrainingFilters
{
    /**
     * Daftar parameter URL standar untuk Global Search & Multi-Attribute Filter Bar.
     */
    public static function trainingFilterKeys(): array
    {
        return [
            'search',
            'department_id',
            'company_id',
            'date_mode',
            'date_from',
            'date_to',
            'month',
            'mandatory_type',
            'training_source',
            'training_category',
            'course_code',
            'course_id',
            'status',
            'completion_status',
            'enrollment_type',
            'date_column',
            'group_type',
            'organization_id',
            'site',
            'job_level',
        ];
    }

    /**
     * Ekstrak dan normalisasi seluruh parameter filter dari Request URL secara konsisten.
     */
    public function extractTrainingFilters(Request $request): array
    {
        $raw = $request->only(self::trainingFilterKeys());
        $actor = $request->user();

        $departmentId = isset($raw['department_id']) && $raw['department_id'] !== '' ? (int) $raw['department_id'] : null;
        $companyId = isset($raw['company_id']) && $raw['company_id'] !== '' ? (int) $raw['company_id'] : null;

        // Proteksi RBAC Multi-Tenant:
        // Jika yang login adalah dept_admin -> kunci otomatis ke departemennya & abaikan company_id
        if ($actor && $actor->role === 'dept_admin') {
            $departmentId = $actor->organization_id;
            $companyId = null;
        }

        // Jika yang login adalah contractor_admin -> kunci otomatis ke perusahaan kontraktornya & abaikan department_id
        if ($actor && $actor->role === 'contractor_admin') {
            $companyId = $actor->organization_id;
            $departmentId = null;
        }

        $dateMode = in_array($raw['date_mode'] ?? 'single', ['single', 'range', 'month'], true)
            ? (string) $raw['date_mode']
            : 'single';

        return [
            'search' => trim((string) ($raw['search'] ?? '')),
            'department_id' => $departmentId,
            'company_id' => $companyId,
            'site' => trim((string) ($raw['site'] ?? '')),
            'job_level' => trim((string) ($raw['job_level'] ?? '')),
            'date_mode' => $dateMode,
            'date_from' => trim((string) ($raw['date_from'] ?? '')),
            'date_to' => trim((string) ($raw['date_to'] ?? '')),
            'month' => trim((string) ($raw['month'] ?? '')),
            'mandatory_type' => trim((string) ($raw['mandatory_type'] ?? '')),
            'training_source' => trim((string) ($raw['training_source'] ?? '')),
            'training_category' => trim((string) ($raw['training_category'] ?? '')),
            'course_code' => trim((string) ($raw['course_code'] ?? '')),
            'course_id' => isset($raw['course_id']) && $raw['course_id'] !== '' ? (int) $raw['course_id'] : null,
            'status' => trim((string) ($raw['status'] ?? '')),
            'completion_status' => trim((string) ($raw['completion_status'] ?? '')),
            'enrollment_type' => trim((string) ($raw['enrollment_type'] ?? '')),
            'date_column' => trim((string) ($raw['date_column'] ?? '')),
            'group_type' => trim((string) ($raw['group_type'] ?? '')),
            'organization_id' => isset($raw['organization_id']) && $raw['organization_id'] !== '' ? (int) $raw['organization_id'] : null,
        ];
    }

    /**
     * Eloquent Local Scope: `Model::query()->applyTrainingFilters($filters, $actor, $config)`
     * Dapat dipanggil langsung dari model `Enrollment`, `User`, `SelfReportedTraining`, maupun `TrainingRequest`.
     */
    public function scopeApplyTrainingFilters(
        Builder $query,
        array|Request $filtersInput,
        ?User $actor = null,
        array $config = []
    ): Builder {
        $filters = $filtersInput instanceof Request
            ? $this->extractTrainingFilters($filtersInput)
            : $filtersInput;

        $actor = $actor ?? ($filtersInput instanceof Request ? $filtersInput->user() : auth()->user());
        $table = $query->getModel()->getTable();
        $dateColumn = $config['date_column'] ?? "{$table}.created_at";

        return match ($table) {
            'enrollments' => $this->applyFiltersToEnrollmentBuilder($query, $filters, $actor, $dateColumn),
            'users' => $this->applyFiltersToUserBuilder($query, $filters, $actor, $dateColumn),
            'self_reported_trainings' => $this->applyFiltersToSelfReportBuilder($query, $filters, $actor, $dateColumn),
            'training_requests' => $this->applyFiltersToTrainingRequestBuilder($query, $filters, $actor, $dateColumn),
            default => $this->applyDateCriteria($query, $dateColumn, $filters),
        };
    }

    /**
     * Helper Controller untuk tabel `enrollments` (Pendaftaran, Administrasi Kelas, & Sertifikat).
     */
    public function applyEnrollmentFilters(
        Builder $query,
        array|Request $filtersInput,
        ?User $actor = null,
        string $dateColumn = 'enrollments.created_at'
    ): Builder {
        $filters = $filtersInput instanceof Request ? $this->extractTrainingFilters($filtersInput) : $filtersInput;
        $actor = $actor ?? ($filtersInput instanceof Request ? $filtersInput->user() : auth()->user());

        return $this->applyFiltersToEnrollmentBuilder($query, $filters, $actor, $dateColumn);
    }

    /**
     * Helper Controller untuk tabel `users` (Skill Matrix, Manpower CRUD, & Batch Enrollment).
     */
    public function applyUserFilters(
        Builder $query,
        array|Request $filtersInput,
        ?User $actor = null,
        string $dateColumn = 'users.created_at'
    ): Builder {
        $filters = $filtersInput instanceof Request ? $this->extractTrainingFilters($filtersInput) : $filtersInput;
        $actor = $actor ?? ($filtersInput instanceof Request ? $filtersInput->user() : auth()->user());

        return $this->applyFiltersToUserBuilder($query, $filters, $actor, $dateColumn);
    }

    /**
     * Helper Controller untuk tabel `self_reported_trainings` (Verifikasi Sertifikat Mandiri).
     */
    public function applySelfReportFilters(
        Builder $query,
        array|Request $filtersInput,
        ?User $actor = null,
        string $dateColumn = 'self_reported_trainings.issued_at'
    ): Builder {
        $filters = $filtersInput instanceof Request ? $this->extractTrainingFilters($filtersInput) : $filtersInput;
        $actor = $actor ?? ($filtersInput instanceof Request ? $filtersInput->user() : auth()->user());

        return $this->applyFiltersToSelfReportBuilder($query, $filters, $actor, $dateColumn);
    }

    /**
     * Helper Controller untuk tabel `training_requests` (TNA & Alert Pembaruan).
     */
    public function applyTrainingRequestFilters(
        Builder $query,
        array|Request $filtersInput,
        ?User $actor = null,
        string $dateColumn = 'training_requests.created_at'
    ): Builder {
        $filters = $filtersInput instanceof Request ? $this->extractTrainingFilters($filtersInput) : $filtersInput;
        $actor = $actor ?? ($filtersInput instanceof Request ? $filtersInput->user() : auth()->user());

        return $this->applyFiltersToTrainingRequestBuilder($query, $filters, $actor, $dateColumn);
    }

    /**
     * Implementasi filter lengkap pada query `Enrollment`.
     */
    protected function applyFiltersToEnrollmentBuilder(
        Builder $query,
        array $filters,
        ?User $actor,
        string $dateColumn
    ): Builder {
        $search = $filters['search'] ?? '';

        // 1. Global Search: Nama Pekerja, NIK, Judul Training, Kode Training, atau No. Sertifikat
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                $q->where('certificate_number', 'like', "%{$search}%")
                    ->orWhereHas('user', function (Builder $uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('nik', 'like', "%{$search}%")
                            ->orWhere('department', 'like', "%{$search}%");
                    })
                    ->orWhereHas('course', function (Builder $cq) use ($search) {
                        $cq->where('title', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
            });
        }

        // 2 & 3. Filter Organisasi (Departemen Internal & Perusahaan Kontraktor) dengan RBAC
        $this->applyOrganizationCriteriaToRelation($query, 'user', $filters, $actor);

        // 3B. Filter Site / Lokasi Operasional & Job Grade (Level Jabatan)
        if (!empty($filters['site'])) {
            $query->whereHas('user', fn (Builder $uq) => $uq->where('site', $filters['site']));
        }

        if (!empty($filters['job_level'])) {
            $query->whereHas('user', fn (Builder $uq) => $uq->where('job_level', $filters['job_level']));
        }

        // 4. Filter Tanggal (Individual Date / Range Date / Bulan)
        $resolvedDateCol = match ($filters['date_column'] ?? '') {
            'issued_at' => 'enrollments.certificate_issued_at',
            'expires_at' => 'enrollments.certificate_expires_at',
            'completed_at' => 'enrollments.completed_at',
            default => $dateColumn,
        };
        $this->applyDateCriteria($query, $resolvedDateCol, $filters);

        // 5, 6, 7, 8. Filter Atribut Klasifikasi Training (`courses`)
        $this->applyCourseClassificationCriteria($query, 'course', $filters);

        if (!empty($filters['course_id'])) {
            $query->where('course_id', (int) $filters['course_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['completion_status'])) {
            $query->where('completion_status', $filters['completion_status']);
        }

        if (!empty($filters['enrollment_type'])) {
            $query->where('enrollment_type', $filters['enrollment_type']);
        }

        return $query;
    }

    /**
     * Implementasi filter lengkap pada query `User` (Peserta / Manpower / Skill Matrix).
     */
    protected function applyFiltersToUserBuilder(
        Builder $query,
        array $filters,
        ?User $actor,
        string $dateColumn
    ): Builder {
        $search = $filters['search'] ?? '';

        // 1. Global Search: Nama Pekerja, NIK, Email, Departemen, atau Kode/Judul/Sertifikat Training yang diikuti
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('nik', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('department', 'like', "%{$search}%")
                    ->orWhereHas('enrollments', function (Builder $eq) use ($search) {
                        $eq->where('certificate_number', 'like', "%{$search}%")
                            ->orWhereHas('course', function (Builder $cq) use ($search) {
                                $cq->where('code', 'like', "%{$search}%")
                                    ->orWhere('title', 'like', "%{$search}%");
                            });
                    });
            });
        }

        // 2 & 3. Filter Departemen Internal & Perusahaan Kontraktor dengan RBAC
        $this->applyOrganizationCriteriaDirect($query, $filters, $actor);

        // 3B. Filter Site / Lokasi Operasional & Job Grade (Level Jabatan)
        if (!empty($filters['site'])) {
            $query->where('site', $filters['site']);
        }

        if (!empty($filters['job_level'])) {
            $query->where('job_level', $filters['job_level']);
        }

        // Grup Organisasi (internal_dept vs contractor) & organization_id eksplisit
        if (!empty($filters['group_type'])) {
            $groupType = $filters['group_type'];
            $query->whereHas('organization', fn (Builder $oq) => $oq->where('type', $groupType));
        }

        if (!empty($filters['organization_id']) && empty($filters['department_id']) && empty($filters['company_id'])) {
            $query->where('organization_id', (int) $filters['organization_id']);
        }

        // 4, 5, 6, 7, 8. Jika ada filter atribut Training atau Tanggal Enrollment, filter via relasi `enrollments`
        $hasCourseOrEnrollmentFilter = !empty($filters['mandatory_type'])
            || !empty($filters['training_source'])
            || !empty($filters['training_category'])
            || !empty($filters['course_code'])
            || !empty($filters['course_id'])
            || !empty($filters['date_from'])
            || !empty($filters['date_to'])
            || !empty($filters['month']);

        if ($hasCourseOrEnrollmentFilter) {
            $query->whereHas('enrollments', function (Builder $eq) use ($filters) {
                if (!empty($filters['course_id'])) {
                    $eq->where('course_id', (int) $filters['course_id']);
                }

                $this->applyDateCriteria($eq, 'enrollments.created_at', $filters);
                $this->applyCourseClassificationCriteria($eq, 'course', $filters);
            });
        }

        return $query;
    }

    /**
     * Implementasi filter lengkap pada query `SelfReportedTraining`.
     */
    protected function applyFiltersToSelfReportBuilder(
        Builder $query,
        array $filters,
        ?User $actor,
        string $dateColumn
    ): Builder {
        $search = $filters['search'] ?? '';

        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                $q->where('course_name', 'like', "%{$search}%")
                    ->orWhere('organizer', 'like', "%{$search}%")
                    ->orWhere('certificate_number', 'like', "%{$search}%")
                    ->orWhereHas('user', function (Builder $uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('nik', 'like', "%{$search}%");
                    });
            });
        }

        $this->applyOrganizationCriteriaToRelation($query, 'user', $filters, $actor);
        $this->applyDateCriteria($query, $dateColumn, $filters);

        if (!empty($filters['site'])) {
            $query->whereHas('user', fn (Builder $uq) => $uq->where('site', $filters['site']));
        }

        if (!empty($filters['job_level'])) {
            $query->whereHas('user', fn (Builder $uq) => $uq->where('job_level', $filters['job_level']));
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query;
    }

    /**
     * Implementasi filter lengkap pada query `TrainingRequest` (TNA).
     */
    protected function applyFiltersToTrainingRequestBuilder(
        Builder $query,
        array $filters,
        ?User $actor,
        string $dateColumn
    ): Builder {
        $search = $filters['search'] ?? '';

        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                $q->where('reason_notes', 'like', "%{$search}%")
                    ->orWhereHas('course', function (Builder $cq) use ($search) {
                        $cq->where('code', 'like', "%{$search}%")
                            ->orWhere('title', 'like', "%{$search}%");
                    })
                    ->orWhereHas('skill', function (Builder $sq) use ($search) {
                        $sq->where('code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($actor && in_array($actor->role, ['dept_admin', 'contractor_admin'], true)) {
            $query->where('organization_id', $actor->organization_id);
        } else {
            $deptId = $filters['department_id'] ?? null;
            $compId = $filters['company_id'] ?? null;
            if ($deptId && $compId) {
                $query->whereIn('organization_id', [(int) $deptId, (int) $compId]);
            } elseif ($deptId) {
                $query->where('organization_id', (int) $deptId);
            } elseif ($compId) {
                $query->where('organization_id', (int) $compId);
            }
        }

        $this->applyDateCriteria($query, $dateColumn, $filters);
        $this->applyCourseClassificationCriteria($query, 'course', $filters);

        if (!empty($filters['site'])) {
            $query->whereHas('requester', fn (Builder $uq) => $uq->where('site', $filters['site']));
        }

        if (!empty($filters['job_level'])) {
            $query->whereHas('requester', fn (Builder $uq) => $uq->where('job_level', $filters['job_level']));
        }

        return $query;
    }

    /**
     * Terapkan filter `department_id` (internal_dept) & `company_id` (contractor) langsung pada tabel `users`.
     */
    protected function applyOrganizationCriteriaDirect(Builder $query, array $filters, ?User $actor): void
    {
        if ($actor && $actor->role === 'dept_admin') {
            if ($actor->organization_id !== null) {
                $query->where('organization_id', $actor->organization_id);
            } else {
                $query->where('department', $actor->department);
            }
            return;
        }

        if ($actor && $actor->role === 'contractor_admin') {
            if ($actor->organization_id !== null) {
                $query->where('organization_id', $actor->organization_id);
            } else {
                $query->where('department', $actor->department);
            }
            return;
        }

        $deptId = $filters['department_id'] ?? null;
        $compId = $filters['company_id'] ?? null;

        if ($deptId && $compId) {
            $query->whereIn('organization_id', [(int) $deptId, (int) $compId]);
        } elseif ($deptId) {
            $query->where('organization_id', (int) $deptId)
                ->whereHas('organization', fn (Builder $oq) => $oq->where('type', 'internal_dept'));
        } elseif ($compId) {
            $query->where('organization_id', (int) $compId)
                ->whereHas('organization', fn (Builder $oq) => $oq->where('type', 'contractor'));
        }
    }

    /**
     * Terapkan filter `department_id` & `company_id` melalui relasi `user`.
     */
    protected function applyOrganizationCriteriaToRelation(
        Builder $query,
        string $userRelation,
        array $filters,
        ?User $actor
    ): void {
        if ($actor && in_array($actor->role, ['dept_admin', 'contractor_admin'], true)) {
            $query->whereHas($userRelation, function (Builder $uq) use ($actor) {
                if ($actor->organization_id !== null) {
                    $uq->where('organization_id', $actor->organization_id);
                } else {
                    $uq->where('department', $actor->department);
                }
            });
            return;
        }

        $deptId = $filters['department_id'] ?? null;
        $compId = $filters['company_id'] ?? null;

        if ($deptId && $compId) {
            $query->whereHas($userRelation, fn (Builder $uq) => $uq->whereIn('organization_id', [(int) $deptId, (int) $compId]));
        } elseif ($deptId) {
            $query->whereHas($userRelation, function (Builder $uq) use ($deptId) {
                $uq->where('organization_id', (int) $deptId)
                    ->whereHas('organization', fn (Builder $oq) => $oq->where('type', 'internal_dept'));
            });
        } elseif ($compId) {
            $query->whereHas($userRelation, function (Builder $uq) use ($compId) {
                $uq->where('organization_id', (int) $compId)
                    ->whereHas('organization', fn (Builder $oq) => $oq->where('type', 'contractor'));
            });
        }
    }

    /**
     * Terapkan filter Tanggal:
     * - `date_mode === 'single'` (atau hanya `date_from` yang diisi): `whereDate($dateColumn, $dateFrom)`
     * - `date_mode === 'range'` (keduanya diisi): `whereBetween($dateColumn, [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])`
     * - `date_mode === 'month'` (atau parameter `month` format YYYY-MM diisi): filter tahun & bulan
     */
    protected function applyDateCriteria(Builder $query, string $dateColumn, array $filters): Builder
    {
        $dateMode = $filters['date_mode'] ?? 'single';
        $dateFrom = $filters['date_from'] ?? '';
        $dateTo = $filters['date_to'] ?? '';
        $month = $filters['month'] ?? '';

        if (($dateMode === 'month' || $month !== '') && preg_match('/^(\d{4})-(\d{2})$/', $month, $m)) {
            $query->whereYear($dateColumn, (int) $m[1])
                ->whereMonth($dateColumn, (int) $m[2]);
            return $query;
        }

        if ($dateMode === 'range' && $dateFrom !== '' && $dateTo !== '') {
            $query->whereBetween($dateColumn, [
                $dateFrom . ' 00:00:00',
                $dateTo . ' 23:59:59',
            ]);
        } elseif ($dateFrom !== '') {
            $query->whereDate($dateColumn, $dateFrom);
        } elseif ($dateTo !== '') {
            $query->whereDate($dateColumn, $dateTo);
        }

        return $query;
    }

    /**
     * Terapkan filter Atribut Klasifikasi Training (`mandatory_type`, `training_source`, `training_category`, `course_code`).
     */
    protected function applyCourseClassificationCriteria(
        Builder $query,
        string $courseRelation,
        array $filters
    ): void {
        $mandatoryType = $filters['mandatory_type'] ?? '';
        $trainingSource = $filters['training_source'] ?? '';
        $trainingCategory = $filters['training_category'] ?? '';
        $courseCode = $filters['course_code'] ?? '';

        if ($mandatoryType === '' && $trainingSource === '' && $trainingCategory === '' && $courseCode === '') {
            return;
        }

        $query->whereHas($courseRelation, function (Builder $cq) use (
            $mandatoryType,
            $trainingSource,
            $trainingCategory,
            $courseCode
        ) {
            if ($mandatoryType === 'mandatory') {
                $cq->where('is_mandatory', true);
            } elseif ($mandatoryType === 'non_mandatory') {
                $cq->where('is_mandatory', false);
            }

            if (in_array($trainingSource, ['internal', 'external'], true)) {
                $cq->where('training_source', $trainingSource);
            }

            if ($trainingCategory !== '') {
                $cq->where('training_category', $trainingCategory);
            }

            if ($courseCode !== '') {
                $cq->where('code', $courseCode);
            }
        });
    }

    /**
     * Bangun opsi dropdown standar (`filterOptions`) untuk dikirim ke `<GlobalTableFilterBar />`.
     */
    public function buildTrainingFilterOptions(?User $actor = null): array
    {
        $canFilterAllOrgs = !$actor || in_array($actor->role, ['lnd', 'admin', 'trainer'], true);

        $departments = Organization::query()
            ->where('type', 'internal_dept')
            ->when(!$canFilterAllOrgs, fn (Builder $q) => $q->whereKey($actor->organization_id))
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'type']);

        $companies = Organization::query()
            ->where('type', 'contractor')
            ->when(!$canFilterAllOrgs, fn (Builder $q) => $q->whereKey($actor->organization_id))
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'type']);

        $courses = Course::query()
            ->select([
                'id',
                'code',
                'title',
                'is_mandatory',
                'training_source',
                'training_category',
            ])
            ->orderBy('code')
            ->get();

        $categories = [
            'Safety',
            'Operation',
            'Plant',
            'Pengawas',
            'Engineering',
            'Leadership',
            'General',
        ];

        return [
            'departments' => $departments,
            'companies' => $companies,
            'courses' => $courses,
            'categories' => $categories,
        ];
    }
}

