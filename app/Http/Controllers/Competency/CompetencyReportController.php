<?php

namespace App\Http\Controllers\Competency;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Organization;
use App\Models\PositionSkillStandard;
use App\Models\User;
use App\Traits\HasTrainingFilters;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompetencyReportController extends Controller
{
    use HasTrainingFilters;

    /**
     * 1. Halaman "Individual Training View" (Merealisasikan sheet 'Individual View' Excel MDKA Group).
     * Mendukung:
     * - Mode "Per Departemen & Section": Analisis Kepatuhan Kompetensi, KPI Departemen, Daftar Nama Sudah / Belum Memenuhi (TNA Queue).
     * - Mode "Per Individu": Profil 360° kepatuhan training 1 pekerja terpilih.
     */
    public function individualView(Request $request): Response
    {
        $actor = $request->user();
        $today = now()->startOfDay();
        $viewMode = $request->query('view_mode', 'department');
        $selectedDepartment = (string) $request->query('department', 'Mining');
        $selectedSection = (string) $request->query('section', '');
        $search = trim((string) $request->query('search', ''));

        // =========================================================
        // 1. DATA MASTER DEPARTEMEN & SECTION UNTUK TNA DEPARTEMEN
        // =========================================================
        $defaultDepts = [
            'Mining',
            'Engineering',
            'Processing',
            'Occupational Health & Safety',
            'Commercial',
            'Environment',
            'Technical Services',
            'Human Resources',
        ];
        $dbDepts = User::query()
            ->role('peserta')
            ->accessibleBy($actor)
            ->whereNotNull('department')
            ->where('department', '!=', '')
            ->distinct()
            ->pluck('department')
            ->toArray();
        $departments = !empty($dbDepts) ? array_values(array_unique(array_merge($dbDepts, $defaultDepts))) : $defaultDepts;

        if ($actor->role === 'dept_admin' && !empty($actor->department)) {
            $departments = [$actor->department];
            $selectedDepartment = $actor->department;
        }

        $sectionMap = [
            'Mining' => ['Load & Haul', 'Drill & Blast', 'Mine Geology'],
            'Engineering' => ['Electrical', 'Civil & Infrastructure', 'Mechanical', 'General'],
            'Processing' => ['Crushing & Grinding', 'CIL Leaching', 'Refinery'],
            'Occupational Health & Safety' => ['Safety & Compliance', 'Emergency Response', 'Industrial Hygiene'],
            'Commercial' => ['Cost Control', 'Procurement', 'Finance & Accounting'],
            'Environment' => ['Rehabilitation & Monitoring', 'Waste Management'],
            'Technical Services' => ['Survey & Geotech', 'Mine Planning'],
            'Human Resources' => ['Recruitment & Development', 'L&D & Training'],
        ];

        $dbSections = User::query()
            ->role('peserta')
            ->accessibleBy($actor)
            ->when($selectedDepartment && $selectedDepartment !== 'all', fn ($q) => $q->where('department', $selectedDepartment))
            ->whereNotNull('section')
            ->where('section', '!=', '')
            ->distinct()
            ->pluck('section')
            ->toArray();

        $fallbackSections = $sectionMap[$selectedDepartment] ?? [];
        $sections = !empty($dbSections) ? array_values(array_unique(array_merge($dbSections, $fallbackSections))) : $fallbackSections;

        // =========================================================
        // 2. ANALISIS TNA PER DEPARTEMEN & SECTION
        // =========================================================
        $deptWorkersQuery = User::query()
            ->role('peserta')
            ->accessibleBy($actor)
            ->when($selectedDepartment && $selectedDepartment !== 'all', fn ($q) => $q->where('department', $selectedDepartment))
            ->when($selectedSection !== '', fn ($q) => $q->where('section', $selectedSection))
            ->with([
                'jobPosition:id,code,position_title,level_name',
                'jobPosition.skillStandards',
                'enrollments:id,user_id,course_id,status,completion_status,progress_percentage,certificate_number,certificate_issued_at,certificate_expires_at',
                'userSkills:id,user_id,skill_id,target_level,current_level,valid_until,last_certificate_number',
            ]);
        $deptWorkers = $deptWorkersQuery->get();

        $allCourses = Course::query()
            ->with('competency:id,code,name,category,competency_type')
            ->orderBy('code')
            ->get();

        $departmentCompetencies = [];
        $totalFulfilledSkillsCount = 0;
        $totalGapSkillsCount = 0;
        $totalGapsSum = 0;
        $sumComplianceRates = 0;

        foreach ($allCourses as $c) {
            $fulfilledWorkers = [];
            $unfulfilledWorkers = [];

            foreach ($deptWorkers as $worker) {
                $enr = $worker->enrollments->firstWhere('course_id', $c->id);
                $us = $c->skill_id ? $worker->userSkills->firstWhere('skill_id', $c->skill_id) : null;
                $expiresAt = $enr?->certificate_expires_at ? Carbon::parse($enr->certificate_expires_at) : ($us?->valid_until ? Carbon::parse($us->valid_until) : null);
                $isPassed = $enr && (
                    $enr->status === 'approved' &&
                    ($enr->completion_status === 'passed' || $enr->progress_percentage >= 100 || !empty($enr->certificate_number))
                );
                $isExpired = $expiresAt && $expiresAt->lt($today);

                $targetLevel = $us?->target_level ?? 3;
                $currentLevel = $us?->current_level ?? ($isPassed && !$isExpired ? $targetLevel : max(1, $targetLevel - 1));

                if ($isPassed && !$isExpired && $currentLevel >= $targetLevel) {
                    $fulfilledWorkers[] = [
                        'user' => [
                            'id' => $worker->id,
                            'nik' => $worker->nik,
                            'name' => $worker->name,
                            'department' => $worker->department,
                            'section' => $worker->section ?? 'Operasional',
                            'job_level' => $worker->jobPosition?->position_title ?? ($worker->job_level ?: 'Staff'),
                        ],
                        'current_level' => $currentLevel,
                        'target_level' => $targetLevel,
                        'cert_no' => $enr?->certificate_number ?? $us?->last_certificate_number ?? ('CERT-' . $worker->nik . '-2025'),
                        'valid_until' => $expiresAt ? $expiresAt->format('d/m/Y') : 'Seumur Hidup',
                        'status_badge' => 'bg-emerald-100 text-emerald-800',
                        'status_label' => 'Valid & Lulus',
                    ];
                } else {
                    $reason = 'Belum Mengikuti Pelatihan';
                    $badge = 'bg-rose-100 text-rose-800';
                    if ($isExpired) {
                        $reason = 'Sertifikat Expired';
                        $badge = 'bg-amber-100 text-amber-800';
                    } elseif ($currentLevel < $targetLevel && $isPassed) {
                        $reason = "Level Belum Cukup ($currentLevel/$targetLevel)";
                        $badge = 'bg-orange-100 text-orange-800';
                    }

                    $unfulfilledWorkers[] = [
                        'user' => [
                            'id' => $worker->id,
                            'nik' => $worker->nik,
                            'name' => $worker->name,
                            'department' => $worker->department,
                            'section' => $worker->section ?? 'Operasional',
                            'job_level' => $worker->jobPosition?->position_title ?? ($worker->job_level ?: 'Staff'),
                        ],
                        'current_level' => $currentLevel,
                        'target_level' => $targetLevel,
                        'reason' => $reason,
                        'reason_badge' => $badge,
                    ];
                }
            }

            $totalWorkersCount = count($deptWorkers);
            $fCount = count($fulfilledWorkers);
            $uCount = count($unfulfilledWorkers);
            $rate = $totalWorkersCount > 0 ? (int) round(($fCount / $totalWorkersCount) * 100) : 0;

            if ($uCount > 0) {
                $totalGapSkillsCount++;
                $totalGapsSum += $uCount;
            } else {
                $totalFulfilledSkillsCount++;
            }
            $sumComplianceRates += $rate;

            $departmentCompetencies[] = [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->title,
                'description' => $c->description ?? 'Standar kompetensi operasional terpadu.',
                'category' => $c->training_category ?? 'Umum',
                'is_mandatory' => $c->is_mandatory ?? true,
                'totalWorkers' => $totalWorkersCount,
                'fulfilledCount' => $fCount,
                'unfulfilledCount' => $uCount,
                'fulfillmentRate' => $rate,
                'fulfilledWorkers' => $fulfilledWorkers,
                'unfulfilledWorkers' => $unfulfilledWorkers,
            ];
        }

        $totalSkills = count($departmentCompetencies);
        $avgCompliance = $totalSkills > 0 ? (int) round($sumComplianceRates / max(1, $totalSkills)) : 0;

        $departmentKpi = [
            'totalWorkers' => count($deptWorkers),
            'avgCompliance' => $avgCompliance,
            'fulfilledSkills' => $totalFulfilledSkillsCount,
            'gapSkills' => $totalGapSkillsCount,
            'totalGaps' => $totalGapsSum,
        ];

        // =========================================================
        // 3. ANALISIS PER INDIVIDU (PRESERVED & INTEGRATED)
        // =========================================================
        $participantsQuery = User::query()
            ->role('peserta')
            ->accessibleBy($actor)
            ->with('organization:id,name,code')
            ->select(['id', 'nik', 'name', 'department', 'organization_id', 'job_position_id'])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('nik', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            }))
            ->orderByRaw('CASE WHEN nik = ? THEN 0 ELSE 1 END', [$search])
            ->orderBy('name');

        $participantsList = $participantsQuery
            ->limit(50)
            ->get()
            ->map(fn (User $participant) => [
                'id' => $participant->id,
                'nik' => $participant->nik,
                'name' => $participant->name,
                'department' => $participant->department,
                'organization' => ['name' => $participant->organization?->name],
            ]);
        $selectedUserId = $request->integer('user_id') ?: ($participantsList->first()?->id);

        $selectedUser = null;
        $summary = [
            'completed' => 0,
            'not_yet' => 0,
            'expired' => 0,
            'not_required' => 0,
        ];
        $trainings = [];

        if ($selectedUserId) {
            $user = User::query()
                ->role('peserta')
                ->accessibleBy($actor)
                ->with([
                    'organization:id,code,name,type',
                    'jobPosition:id,code,level_name,position_title',
                    'jobPosition.skillStandards',
                    'enrollments:id,user_id,course_id,status,completion_status,progress_percentage,certificate_number,certificate_issued_at,certificate_expires_at',
                    'userSkills:id,user_id,skill_id,target_level,current_level,valid_until,last_certificate_number',
                ])
                ->find($selectedUserId);

            if ($user) {
                if (!$participantsList->contains('id', $user->id)) {
                    $participantsList->prepend([
                        'id' => $user->id,
                        'nik' => $user->nik,
                        'name' => $user->name,
                        'department' => $user->department,
                        'organization' => ['name' => $user->organization?->name],
                    ]);
                }

                $hasPosition = !empty($user->job_position_id);
                $matchStatus = $hasPosition ? 'Matched Title+Level' : 'No Match';

                $selectedUser = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'nik' => $user->nik,
                    'email' => $user->email,
                    'department' => $user->organization?->name ?? ($user->department ?: '-'),
                    'section' => $user->section ?? 'Facility Electrical',
                    'position' => $user->jobPosition?->position_title ?? ($hasPosition ? $user->jobPosition?->full_label : 'Unassigned Position'),
                    'level' => $user->jobPosition?->level_name ?? ($user->job_level ?: 'Staff'),
                    'site' => $user->site ?? 'Site Pani Project (PETS/GSM)',
                    'entity' => $user->entity ?: ($user->organization?->type === 'contractor' ? 'Contractor' : 'MDKA'),
                    'nationality' => $user->nationality ?? 'Indonesia',
                    'match_status' => $matchStatus,
                ];

                $posStandards = $user->jobPosition?->skillStandards?->keyBy('skill_id') ?? collect();
                $userEnrollments = $user->enrollments->keyBy('course_id');
                $userSkills = $user->userSkills->keyBy('skill_id');

                $courses = Course::query()
                    ->with('competency:id,code,name,category,competency_type')
                    ->orderBy('code')
                    ->get();

                foreach ($courses as $c) {
                    $std = $c->skill_id ? $posStandards->get($c->skill_id) : null;
                    $us = $c->skill_id ? $userSkills->get($c->skill_id) : null;
                    $enr = $userEnrollments->get($c->id);

                    // Tentukan Status Jabatan (M, NM, NR)
                    $statusJabatan = $this->resolveRequirementType($c, $std);

                    // Tentukan Tanggal & Status Kelulusan
                    $issuedAt = $enr?->certificate_issued_at ? Carbon::parse($enr->certificate_issued_at) : null;
                    $expiresAt = $enr?->certificate_expires_at ? Carbon::parse($enr->certificate_expires_at) : ($us?->valid_until ? Carbon::parse($us->valid_until) : null);

                    $isPassed = $enr && (
                        $enr->status === 'approved' &&
                        ($enr->completion_status === 'passed' || $enr->progress_percentage >= 100 || !empty($enr->certificate_number))
                    );

                    $isExpired = $expiresAt && $expiresAt->lt($today);

                    if ($statusJabatan === 'NR') {
                        $completionStatus = 'N/A';
                        $summary['not_required']++;
                    } elseif ($isPassed && $isExpired) {
                        $completionStatus = 'Expired';
                        $summary['expired']++;
                    } elseif ($isPassed) {
                        $completionStatus = 'Completed';
                        $summary['completed']++;
                    } else {
                        $completionStatus = 'Not Yet Completed';
                        $summary['not_yet']++;
                    }

                    $validityMonths = $c->validity_months ?: (($c->validity_years ?: 2) * 12);

                    $trainings[] = [
                        'course_id' => $c->id,
                        'code' => $c->code,
                        'name' => $c->title,
                        'category' => $c->training_category ?? 'General',
                        'training_source' => $c->training_source ?? 'internal',
                        'status' => $c->status,
                        'status_jabatan' => $statusJabatan,
                        'validity_months' => $validityMonths,
                        'last_training_date' => $issuedAt ? $issuedAt->format('d/m/Y') : '-',
                        'expiry_date' => $expiresAt ? $expiresAt->format('d/m/Y') : ($isPassed ? 'Seumur Hidup' : '-'),
                        'completion_status' => $completionStatus,
                        'certificate_number' => $enr?->certificate_number ?? $us?->last_certificate_number,
                    ];
                }
            }
        }

        return Inertia::render('Competency/IndividualTrainingView', [
            'viewMode' => $viewMode,
            'departments' => $departments,
            'sections' => $sections,
            'selectedDepartment' => $selectedDepartment,
            'selectedSection' => $selectedSection,
            'departmentKpi' => $departmentKpi,
            'departmentCompetencies' => $departmentCompetencies,
            'participantsList' => $participantsList,
            'selectedUserId' => $selectedUserId,
            'search' => $search,
            'canScheduleTraining' => in_array($actor->role, ['lnd', 'admin'], true),
            'selectedUser' => $selectedUser,
            'summary' => $summary,
            'trainings' => $trainings,
        ]);
    }

    /**
     * 2. Halaman "Rangkuman Per Training" (Merealisasikan sheet Rangkuman Per Training, Internal-External, Summary Mandatory, & Emp Completion).
     */
    public function trainingSummary(Request $request): Response
    {
        $actor = $request->user();
        $today = now()->startOfDay();
        $filters = $this->extractTrainingFilters($request);

        $courses = Course::query()
            ->with(['competency:id,code,name,category,competency_type'])
            ->orderBy('code')
            ->get();

        $participantsQuery = User::query()
            ->role('peserta')
            ->accessibleBy($actor);

        $this->applyUserFilters($participantsQuery, $filters, $actor);

        $employeePage = (clone $participantsQuery)
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(50, ['users.id'], 'employee_page')
            ->withQueryString();
        $employeePageIds = $employeePage->getCollection()->pluck('id')->map(fn ($id) => (int) $id);
        $employeePageIdLookup = $employeePageIds->flip();
        $employeeRows = [];

        $courseStats = [];
        foreach ($courses as $course) {
            $courseStats[$course->id] = [
                'id' => $course->id,
                'code' => $course->code,
                'title' => $course->title,
                'executor' => strtoupper((string) ($course->training_source ?? 'INTERNAL')),
                'category' => $course->training_category ?? 'General',
                'is_mandatory' => (bool) $course->is_mandatory,
                'total_required' => 0,
                'completed' => 0,
                'not_yet' => 0,
                'expired' => 0,
                'completion_pct' => 0,
            ];
        }

        $organizations = Organization::query()
            ->orderBy('type')
            ->orderBy('name')
            ->get(['id', 'name', 'type'])
            ->keyBy('id');
        $divisionStats = [];

        (clone $participantsQuery)
            ->with([
                'organization:id,name,code,type',
                'jobPosition:id,code,level_name,position_title',
                'jobPosition.skillStandards',
                'enrollments:id,user_id,course_id,status,completion_status,progress_percentage,certificate_number,certificate_expires_at',
                'userSkills:id,user_id,skill_id,valid_until',
            ])
            ->chunkById(250, function ($participants) use (
                $courses,
                $organizations,
                $employeePageIdLookup,
                &$courseStats,
                &$divisionStats,
                &$employeeRows,
                $today
            ) {
                foreach ($participants as $participant) {
                    $positionStandards = $participant->jobPosition?->skillStandards?->keyBy('skill_id') ?? collect();
                    $enrollments = $participant->enrollments->keyBy('course_id');
                    $userSkills = $participant->userSkills->keyBy('skill_id');
                    $organization = $organizations->get($participant->organization_id);
                    $division = null;

                    if ($organization) {
                        $division = $divisionStats[$organization->id] ??= [
                            'org_id' => $organization->id,
                            'org_name' => $organization->name,
                            'org_type' => $organization->type,
                            'total_workers' => 0,
                            'mandatory_internal' => 0,
                            'mandatory_external' => 0,
                            'non_mandatory_internal' => 0,
                            'non_mandatory_external' => 0,
                        ];
                        $divisionStats[$organization->id]['total_workers']++;
                    }

                    $requiredMandatory = 0;
                    $completedMandatory = 0;
                    $expiredMandatory = 0;
                    $notYetMandatory = 0;

                    foreach ($courses as $course) {
                        $standard = $course->skill_id ? $positionStandards->get($course->skill_id) : null;
                        $requirementType = $this->resolveRequirementType($course, $standard);
                        if ($requirementType === 'NR') {
                            continue;
                        }

                        $isMandatory = $requirementType === 'M';
                        $isInternal = ($course->training_source ?? 'internal') === 'internal';
                        $enrollment = $enrollments->get($course->id);
                        $userSkill = $course->skill_id ? $userSkills->get($course->skill_id) : null;
                        $isPassed = $enrollment && $enrollment->status === 'approved' && (
                            $enrollment->completion_status === 'passed'
                            || $enrollment->progress_percentage >= 100
                            || !empty($enrollment->certificate_number)
                        );
                        $expiry = $enrollment?->certificate_expires_at
                            ? Carbon::parse($enrollment->certificate_expires_at)
                            : ($userSkill?->valid_until ? Carbon::parse($userSkill->valid_until) : null);
                        $isExpired = $expiry && $expiry->lt($today);

                        if ($isMandatory) {
                            $requiredMandatory++;
                            $courseStats[$course->id]['total_required']++;

                            if ($isPassed) {
                                $courseStats[$course->id]['completed']++;
                                $completedMandatory++;
                                if ($isExpired) {
                                    $courseStats[$course->id]['expired']++;
                                    $expiredMandatory++;
                                }
                            } else {
                                $courseStats[$course->id]['not_yet']++;
                                $notYetMandatory++;
                            }
                        }

                        if ($division) {
                            $divisionKey = ($isMandatory ? 'mandatory_' : 'non_mandatory_')
                                .($isInternal ? 'internal' : 'external');
                            $divisionStats[$organization->id][$divisionKey]++;
                        }
                    }

                    if ($employeePageIdLookup->has($participant->id)) {
                        $hasPosition = !empty($participant->job_position_id);
                        $employeeRows[$participant->id] = [
                            'id' => $participant->id,
                            'nik' => $participant->nik,
                            'name' => $participant->name,
                            'site' => $participant->site ?? 'Site Pani Project (PETS/GSM)',
                            'department' => $organization?->name ?? ($participant->department ?: '-'),
                            'level' => $participant->jobPosition?->level_name ?? ($participant->job_level ?: 'Staff'),
                            'match_status' => $hasPosition ? 'Matched Title+Level' : 'No Match',
                            'required_mandatory' => $requiredMandatory,
                            'completed_mandatory' => $completedMandatory,
                            'not_yet_mandatory' => $notYetMandatory,
                            'expired_mandatory' => $expiredMandatory,
                            'is_fully_completed' => $requiredMandatory > 0
                                && $notYetMandatory === 0
                                && $expiredMandatory === 0 ? 'YES' : 'NO',
                        ];
                    }
                }
            });

        $trainingTypeSummary = array_values(array_map(function (array $stats) {
            $stats['completion_pct'] = $stats['total_required'] > 0
                ? round(($stats['completed'] / $stats['total_required']) * 100, 1)
                : 0;

            return $stats;
        }, array_filter($courseStats, fn (array $stats) => $stats['total_required'] > 0)));

        $divisiSummary = array_values(array_map(function (array $stats) {
            $stats['total_mandatory'] = $stats['mandatory_internal'] + $stats['mandatory_external'];
            $stats['grand_total'] = $stats['total_mandatory']
                + $stats['non_mandatory_internal']
                + $stats['non_mandatory_external'];

            return $stats;
        }, array_filter($divisionStats, fn (array $stats) => $stats['total_workers'] > 0)));
        $employeeCompletionList = array_values(array_filter(
            array_map(fn ($id) => $employeeRows[$id] ?? null, $employeePageIds->all())
        ));

        return Inertia::render('Competency/TrainingSummaryReport', [
            'trainingTypeSummary' => $trainingTypeSummary,
            'divisiSummary' => $divisiSummary,
            'employeeCompletionList' => $employeeCompletionList,
            'employeeCompletionPagination' => [
                'current_page' => $employeePage->currentPage(),
                'last_page' => $employeePage->lastPage(),
                'per_page' => $employeePage->perPage(),
                'total' => $employeePage->total(),
            ],
            'filters' => $filters,
            'filterOptions' => $this->buildTrainingFilterOptions($actor),
            'departments' => Organization::where('type', 'internal_dept')->orderBy('name')->get(['id', 'name', 'code']),
            'companies' => Organization::where('type', 'contractor')->orderBy('name')->get(['id', 'name', 'code']),
            'courseCodes' => Course::orderBy('code')->get(['id', 'code', 'title']),
        ]);
    }

    private function resolveRequirementType(Course $course, ?PositionSkillStandard $standard): string
    {
        if ($standard) {
            return $standard->requirement_type ?: ($standard->is_mandatory ? 'M' : 'NM');
        }

        return $course->is_mandatory ? 'M' : 'NM';
    }
}
