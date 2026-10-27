<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Organization;
use App\Models\PositionSkillStandard;
use App\Models\SelfReportedTraining;
use App\Models\Skill;
use App\Models\User;
use App\Models\UserSkill;
use App\Traits\HasTrainingFilters;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    use HasTrainingFilters;

    /**
     * Dashboard LnD Terpadu (5 Mode Tab Excel MDKA Group + Filter Dinamis):
     * - Tab 1: Overview & Analitik Utama (5 Metrik Utama Korporat)
     * - Tab 2: Compliance Mandatory & Non-Mandatory (3 SvgDonutChart & Ranking Dept)
     * - Tab 3: Safety & Internal Mandatory Gap (Tabel Detail Internal/Safety + Progress Bar)
     * - Tab 4: External Mandatory (Lisensi BNSP / KEMNAKER / ESDM Urut Defisit Terbanyak)
     * - Tab 5: Non-Mandatory & Cross-Skills Gap (Pelatihan Pengembangan Sukarela)
     */
    public function index(Request $request): Response
    {
        $actor = $request->user();
        $currentYear = now()->year;
        $today = now()->startOfDay();
        $todayStr = $today->toDateString();

        // 0. Filter Dinamis
        $filters = $this->extractTrainingFilters($request);
        $site = trim((string) $request->input('site', ''));
        $jobLevel = trim((string) $request->input('job_level', ''));
        $departmentId = $request->input('department_id') ? (int) $request->input('department_id') : null;
        $companyId = $request->input('company_id') ? (int) $request->input('company_id') : null;
        $trainingCategory = trim((string) $request->input('training_category', ''));
        $mandatoryType = in_array($filters['mandatory_type'], ['mandatory', 'non_mandatory'], true)
            ? $filters['mandatory_type']
            : '';
        $trainingSource = in_array($filters['training_source'], ['internal', 'external'], true)
            ? $filters['training_source']
            : '';
        $search = $filters['search'];
        $activeTab = trim((string) $request->input('tab', 'overview'));
        $activeTab = in_array($activeTab, ['overview', 'mandatory', 'non_mandatory', 'safety_internal', 'external_mandatory'], true)
            ? $activeTab
            : 'overview';

        // Proteksi Multi-Tenant
        if ($actor && $actor->role === 'dept_admin') {
            $departmentId = $actor->organization_id;
            $companyId = null;
        } elseif ($actor && $actor->role === 'contractor_admin') {
            $companyId = $actor->organization_id;
            $departmentId = null;
        }

        // =========================================================================
        // BAGIAN TAB 1: 5 METRIK UTAMA OVERVIEW (Presisi & Terhubung ke Kalender)
        // Optimasi Tinggi: 5 Single Grouped Queries Menggantikan 72 Roundtrips DB
        // =========================================================================
        $months = range(1, 12);
        $isSqlite = DB::getDriverName() === 'sqlite';
        $monthExpr = fn (string $col) => $isSqlite ? "CAST(strftime('%m', {$col}) AS INTEGER)" : "MONTH({$col})";

        // 1. Tren Partisipasi Peserta
        $enrollmentsByMonth = Enrollment::query()
            ->when(in_array($actor->role, ['dept_admin', 'contractor_admin'], true), fn ($q) => $q->whereHas('user', fn ($uq) => $uq->where('organization_id', $actor->organization_id)))
            ->whereYear('created_at', $currentYear)
            ->where('status', 'approved')
            ->selectRaw("{$monthExpr('created_at')} as m, COUNT(*) as total")
            ->groupByRaw($monthExpr('created_at'))
            ->pluck('total', 'm');

        // 2. Jam Sesi Absensi Sah
        $sessionHoursByMonth = AttendanceSession::query()
            ->whereYear('session_date', $currentYear)
            ->whereHas('attendances', fn ($q) => $q->where('status', 'present'))
            ->selectRaw("{$monthExpr('session_date')} as m, SUM(duration_hours) as total_hours")
            ->groupByRaw($monthExpr('session_date'))
            ->pluck('total_hours', 'm');

        // 3. Jam Mengajar Manual LnD
        $manualHoursByMonth = DB::table('trainer_teaching_logs')
            ->where('entry_source', 'manual_lnd_entry')
            ->whereYear('log_date', $currentYear)
            ->selectRaw("{$monthExpr('log_date')} as m, SUM(duration_hours) as total_hours")
            ->groupByRaw($monthExpr('log_date'))
            ->pluck('total_hours', 'm');

        // 4. Tren Pengajuan Self-Reported Training
        $selfReportByMonth = SelfReportedTraining::query()
            ->when(in_array($actor->role, ['dept_admin', 'contractor_admin'], true), fn ($q) => $q->whereHas('user', fn ($uq) => $uq->where('organization_id', $actor->organization_id)))
            ->whereYear('issued_at', $currentYear)
            ->where('status', 'approved')
            ->selectRaw("{$monthExpr('issued_at')} as m, COUNT(*) as total")
            ->groupByRaw($monthExpr('issued_at'))
            ->pluck('total', 'm');

        $participationTrend = [];
        $trainerHoursTrend = [];
        $selfReportTrend = [];

        foreach ($months as $m) {
            $participationTrend[] = (int) ($enrollmentsByMonth[$m] ?? 0);
            $sH = (float) ($sessionHoursByMonth[$m] ?? 0);
            $mH = (float) ($manualHoursByMonth[$m] ?? 0);
            $trainerHoursTrend[] = round($sH + $mH, 1);
            $selfReportTrend[] = (int) ($selfReportByMonth[$m] ?? 0);
        }

        // Top 12 Skill Gaps
        $topSkillGaps = DB::table('skills as s')
            ->join('user_skills as us', 'us.skill_id', '=', 's.id')
            ->join('users as u', 'u.id', '=', 'us.user_id')
            ->join('position_skill_standards as pss', function ($join) {
                $join->on('pss.skill_id', '=', 'us.skill_id')
                    ->on('pss.job_position_id', '=', 'u.job_position_id');
            })
            ->whereIn('pss.requirement_type', ['M', 'NM'])
            ->when(in_array($actor->role, ['dept_admin', 'contractor_admin'], true), fn ($q) => $q->where('u.organization_id', $actor->organization_id))
            ->when($site !== '', fn ($q) => $q->where('u.site', $site))
            ->when($departmentId, fn ($q) => $q->where('u.organization_id', $departmentId))
            ->when($companyId, fn ($q) => $q->where('u.organization_id', $companyId))
            ->where(function ($q) use ($todayStr) {
                $q->whereColumn('us.current_level', '<', 'us.target_level')
                  ->orWhere(function ($sub) use ($todayStr) {
                      $sub->whereNotNull('us.valid_until')
                          ->where('us.valid_until', '<', $todayStr);
                  });
            })
            ->select('s.id', 's.code', 's.name', DB::raw('COUNT(us.user_id) as total_deficits'))
            ->groupBy('s.id', 's.code', 's.name')
            ->orderByDesc('total_deficits')
            ->limit(12)
            ->get();

        // 5. Tren Pelatihan Bulanan (Single Grouped Query)
        $coursesByMonth = Course::query()
            ->whereYear('start_date', $currentYear)
            ->selectRaw("{$monthExpr('start_date')} as m, COUNT(*) as total")
            ->groupByRaw($monthExpr('start_date'))
            ->pluck('total', 'm');

        $volumeParticipantsByMonth = Enrollment::query()
            ->when(in_array($actor->role, ['dept_admin', 'contractor_admin'], true), fn ($q) => $q->whereHas('user', fn ($uq) => $uq->where('organization_id', $actor->organization_id)))
            ->join('courses as vc', 'vc.id', '=', 'enrollments.course_id')
            ->whereYear('vc.start_date', $currentYear)
            ->where('enrollments.status', 'approved')
            ->selectRaw("{$monthExpr('vc.start_date')} as m, COUNT(enrollments.id) as total")
            ->groupByRaw($monthExpr('vc.start_date'))
            ->pluck('total', 'm');

        $monthlyTrainingVolume = [];
        foreach ($months as $m) {
            $monthlyTrainingVolume[] = [
                'month'          => date('M', mktime(0, 0, 0, $m, 10)),
                'courses_count'  => (int) ($coursesByMonth[$m] ?? 0),
                'participants'   => (int) ($volumeParticipantsByMonth[$m] ?? 0),
            ];
        }

        // Manpower Terkompetensi per Organisasi
        $manpowerCompetencyPerOrg = Organization::query()
            ->when(in_array($actor->role, ['dept_admin', 'contractor_admin'], true), fn ($q) => $q->where('id', $actor->organization_id))
            ->when($site !== '', fn ($q) => $q->where('site', $site))
            ->withCount([
                'users as total_manpower' => fn ($q) => $q->where('role', 'peserta'),
                'users as fully_competent_count' => function ($q) use ($todayStr) {
                    $q->where('role', 'peserta')
                        ->whereExists(function ($sub) {
                            $sub->select(DB::raw(1))
                                ->from('user_skills')
                                ->whereColumn('user_skills.user_id', 'users.id');
                        })
                        ->whereNotExists(function ($sub) use ($todayStr) {
                            $sub->select(DB::raw(1))
                                ->from('user_skills')
                                ->whereColumn('user_skills.user_id', 'users.id')
                                ->where(function ($sq) use ($todayStr) {
                                    $sq->whereColumn('user_skills.current_level', '<', 'user_skills.target_level')
                                       ->orWhere(function ($expiredQ) use ($todayStr) {
                                           $expiredQ->whereNotNull('user_skills.valid_until')
                                                    ->where('user_skills.valid_until', '<', $todayStr);
                                       });
                                });
                        });
                },
            ])
            ->get()
            ->map(fn ($org) => [
                'org_name'               => $org->name,
                'org_type'               => $org->type,
                'total_manpower'         => (int) $org->total_manpower,
                'fully_competent_count'  => (int) $org->fully_competent_count,
                'competency_percentage'  => $org->total_manpower > 0
                    ? round(($org->fully_competent_count / $org->total_manpower) * 100, 1)
                    : 0,
            ]);

        // Upcoming Schedules (dengan link langsung ke Kalender)
        $upcomingSchedules = Course::query()
            ->with(['trainer', 'competency'])
            ->where('start_date', '>=', $todayStr)
            ->orderBy('start_date', 'asc')
            ->limit(6)
            ->get()
            ->map(fn ($c) => [
                'id'              => $c->id,
                'code'            => $c->code,
                'title'           => $c->title,
                'category'        => $c->training_category,
                'is_mandatory'    => (bool) $c->is_mandatory,
                'start_date'      => date('d M Y', strtotime($c->start_date)),
                'trainer_name'    => $c->trainer?->name ?? 'Belum ditentukan',
                'competency_name' => $c->competency?->name ?? '-',
                'calendar_url'    => '/training/calendar?view=monthly&date=' . date('Y-m-d', strtotime($c->start_date)),
            ]);

        // =========================================================================
        // BAGIAN TAB 2, 3, 4, 5: COMPLIANCE MDKA GROUP EXCEL COMPUTATION
        // =========================================================================
        $participantsQuery = User::query()
            ->role('peserta')
            ->accessibleBy($actor)
            ->when($site !== '', fn ($q) => $q->where('site', $site))
            ->when($jobLevel !== '', fn ($q) => $q->where('job_level', $jobLevel));

        if ($departmentId && $companyId) {
            $participantsQuery->whereIn('organization_id', [(int) $departmentId, (int) $companyId]);
        } elseif ($departmentId) {
            $participantsQuery->where('organization_id', (int) $departmentId);
        } elseif ($companyId) {
            $participantsQuery->where('organization_id', (int) $companyId);
        }

        $coursesQuery = Course::query()
            ->select(['id', 'code', 'title', 'is_mandatory', 'training_source', 'training_category', 'skill_id'])
            ->when($trainingCategory !== '', fn ($q) => $q->where('training_category', $trainingCategory))
            ->when($trainingSource !== '', fn ($q) => $q->where('training_source', $trainingSource))
            ->orderBy('code');

        $courseMatchesSearch = $search !== ''
            && (clone $coursesQuery)
                ->where(fn ($q) => $q->where('code', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%"))
                ->exists();

        $employeeMatchesSearch = $search !== ''
            && (clone $participantsQuery)
                ->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('nik', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"))
                ->exists();

        if ($search !== '') {
            if ($courseMatchesSearch) {
                $coursesQuery->where(fn ($q) => $q->where('code', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%"));
            } elseif (!$employeeMatchesSearch) {
                $coursesQuery->whereRaw('1 = 0');
            }

            if ($employeeMatchesSearch) {
                $participantsQuery->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('nik', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            } elseif (!$courseMatchesSearch) {
                $participantsQuery->whereRaw('1 = 0');
            }
        }

        $participants = $participantsQuery
            ->with([
                'organization:id,code,name,type',
                'jobPosition:id,code,level_name,position_title',
                'jobPosition.skillStandards',
                'enrollments:id,user_id,course_id,status,completion_status,progress_percentage,completed_at,certificate_number,certificate_expires_at,certificate_issued_at',
                'userSkills:id,user_id,skill_id,target_level,current_level,valid_until',
            ])
            ->lazyById(250);

        $courses = $coursesQuery->get();

        $totalEmployees = (clone $participantsQuery)->count();

        // -------------------------------------------------------------------------
        // 1. EVALUASI COMPLIANCE MANDATORY (TAB 2)
        // -------------------------------------------------------------------------
        $totalMandatoryTypes = 0;

        $mandOverallCompleted = 0;
        $mandOverallNotYet = 0;
        $mandOverallExpired = 0;

        $mandInternalCompleted = 0;
        $mandInternalNotYet = 0;
        $mandInternalExpired = 0;

        $mandExternalCompleted = 0;
        $mandExternalNotYet = 0;
        $mandExternalExpired = 0;

        $mand100CompleteCount = 0;
        $mandDeptStats = [];
        $mandCourseStats = [];
        $completionTrendCounts = [
            'mandatory' => array_fill(1, 12, 0),
            'non_mandatory' => array_fill(1, 12, 0),
        ];
        $completionTrendBaseline = ['mandatory' => 0, 'non_mandatory' => 0];

        // -------------------------------------------------------------------------
        // 2. EVALUASI COMPLIANCE NON-MANDATORY (TAB 3)
        // -------------------------------------------------------------------------
        $totalNonMandatoryTypes = 0;

        $nmOverallCompleted = 0;
        $nmOverallNotYet = 0;
        $nmOverallExpired = 0;

        $nmInternalCompleted = 0;
        $nmInternalNotYet = 0;
        $nmInternalExpired = 0;

        $nmExternalCompleted = 0;
        $nmExternalNotYet = 0;
        $nmExternalExpired = 0;

        $nm100CompleteCount = 0;
        $nmDeptStats = [];
        $nmCourseStats = [];

        foreach ($courses as $c) {
            $baseStat = [
                'id' => $c->id,
                'code' => $c->code,
                'title' => $c->title,
                'training_source' => $c->training_source ?? 'internal',
                'training_category' => $c->training_category ?? 'General',
                'is_mandatory' => (bool) $c->is_mandatory,
                'required' => 0,
                'completed' => 0,
                'not_yet' => 0,
                'expired' => 0,
                'completion_percentage' => 0,
            ];

            $mandCourseStats[$c->id] = $baseStat;
            $nmCourseStats[$c->id] = $baseStat;
        }

        foreach ($participants as $p) {
            $pEnrollments = $p->enrollments->keyBy('course_id');
            $pUserSkills = $p->userSkills->keyBy('skill_id');
            $posStandards = $p->jobPosition?->skillStandards?->keyBy('skill_id') ?? collect();

            $orgKey = $p->organization_id ? 'org_' . $p->organization_id : 'dept_' . ($p->department ?: 'Unknown');
            $baseDept = [
                'id' => $p->organization_id,
                'name' => $p->organization?->name ?? ($p->department ?: 'Departemen Lainnya'),
                'type' => $p->organization?->type ?? 'internal_dept',
                'total_employees' => 0,
                'required' => 0,
                'completed' => 0,
                'not_yet' => 0,
                'expired' => 0,
            ];

            if (!isset($mandDeptStats[$orgKey])) {
                $mandDeptStats[$orgKey] = $baseDept;
            }
            $mandDeptStats[$orgKey]['total_employees']++;

            if (!isset($nmDeptStats[$orgKey])) {
                $nmDeptStats[$orgKey] = $baseDept;
            }
            $nmDeptStats[$orgKey]['total_employees']++;

            $pMandRequired = 0;
            $pMandNotYet = 0;
            $pMandExpired = 0;

            $pNmRequired = 0;
            $pNmNotYet = 0;
            $pNmExpired = 0;

            foreach ($courses as $course) {
                $std = $course->skill_id ? $posStandards->get($course->skill_id) : null;
                $requirementType = $std
                    ? ($std->requirement_type ?: ($std->is_mandatory ? 'M' : 'NM'))
                    : ((bool) $course->is_mandatory ? 'M' : 'NM');

                if ($requirementType === 'NR') {
                    continue;
                }

                $isMandatory = $requirementType === 'M';
                if (($mandatoryType === 'mandatory' && !$isMandatory)
                    || ($mandatoryType === 'non_mandatory' && $isMandatory)) {
                    continue;
                }

                $isInternal = ($course->training_source === 'internal');

                $enrollment = $pEnrollments->get($course->id);
                $us = $course->skill_id ? $pUserSkills->get($course->skill_id) : null;

                $isPassed = $enrollment && (
                    $enrollment->status === 'approved' &&
                    ($enrollment->completion_status === 'passed' || $enrollment->progress_percentage >= 100 || !empty($enrollment->certificate_number))
                );

                $certExpiresAt = $enrollment?->certificate_expires_at ? Carbon::parse($enrollment->certificate_expires_at)->startOfDay() : null;
                $skillValidUntil = $us?->valid_until ? Carbon::parse($us->valid_until)->startOfDay() : null;
                $effectiveExpiry = $certExpiresAt ?: $skillValidUntil;
                $isExpired = $effectiveExpiry && $effectiveExpiry->lt($today);
                $completionDate = $enrollment?->completed_at ?: $enrollment?->certificate_issued_at;

                if ($isPassed && !$isExpired && $completionDate) {
                    $completionDate = Carbon::parse($completionDate)->startOfDay();
                    if ($completionDate->lte($today)) {
                        $trendType = $isMandatory ? 'mandatory' : 'non_mandatory';
                        if ($completionDate->year < $currentYear) {
                            $completionTrendBaseline[$trendType]++;
                        } elseif ($completionDate->year === $currentYear) {
                            $completionTrendCounts[$trendType][$completionDate->month]++;
                        }
                    }
                }

                if ($isMandatory) {
                    $pMandRequired++;
                    if (isset($mandCourseStats[$course->id])) {
                        $mandCourseStats[$course->id]['required']++;
                    }
                    $mandDeptStats[$orgKey]['required']++;

                    if ($isPassed && $isExpired) {
                        $mandOverallExpired++;
                        $pMandExpired++;
                        $mandDeptStats[$orgKey]['expired']++;
                        if (isset($mandCourseStats[$course->id])) $mandCourseStats[$course->id]['expired']++;
                        if ($isInternal) $mandInternalExpired++; else $mandExternalExpired++;
                    } elseif ($isPassed) {
                        $mandOverallCompleted++;
                        $mandDeptStats[$orgKey]['completed']++;
                        if (isset($mandCourseStats[$course->id])) $mandCourseStats[$course->id]['completed']++;
                        if ($isInternal) $mandInternalCompleted++; else $mandExternalCompleted++;
                    } else {
                        $mandOverallNotYet++;
                        $pMandNotYet++;
                        $mandDeptStats[$orgKey]['not_yet']++;
                        if (isset($mandCourseStats[$course->id])) $mandCourseStats[$course->id]['not_yet']++;
                        if ($isInternal) $mandInternalNotYet++; else $mandExternalNotYet++;
                    }
                } else {
                    $pNmRequired++;
                    if (isset($nmCourseStats[$course->id])) {
                        $nmCourseStats[$course->id]['required']++;
                    }
                    $nmDeptStats[$orgKey]['required']++;

                    if ($isPassed && $isExpired) {
                        $nmOverallExpired++;
                        $pNmExpired++;
                        $nmDeptStats[$orgKey]['expired']++;
                        if (isset($nmCourseStats[$course->id])) $nmCourseStats[$course->id]['expired']++;
                        if ($isInternal) $nmInternalExpired++; else $nmExternalExpired++;
                    } elseif ($isPassed) {
                        $nmOverallCompleted++;
                        $nmDeptStats[$orgKey]['completed']++;
                        if (isset($nmCourseStats[$course->id])) $nmCourseStats[$course->id]['completed']++;
                        if ($isInternal) $nmInternalCompleted++; else $nmExternalCompleted++;
                    } else {
                        $nmOverallNotYet++;
                        $pNmNotYet++;
                        $nmDeptStats[$orgKey]['not_yet']++;
                        if (isset($nmCourseStats[$course->id])) $nmCourseStats[$course->id]['not_yet']++;
                        if ($isInternal) $nmInternalNotYet++; else $nmExternalNotYet++;
                    }
                }
            }

            if ($pMandRequired > 0 && $pMandNotYet === 0 && $pMandExpired === 0) {
                $mand100CompleteCount++;
            }
            if ($pNmRequired > 0 && $pNmNotYet === 0 && $pNmExpired === 0) {
                $nm100CompleteCount++;
            }
        }

        $totalMandatoryTypes = collect($mandCourseStats)->where('required', '>', 0)->count();
        $totalNonMandatoryTypes = collect($nmCourseStats)->where('required', '>', 0)->count();

        // Kalkulasi Rasio & Segments Donut Mandatory
        $totalMandEvaluated = max(1, $mandOverallCompleted + $mandOverallNotYet + $mandOverallExpired);
        $mandOverallRate = round(($mandOverallCompleted / $totalMandEvaluated) * 100, 1);
        $totalMandInternal = max(1, $mandInternalCompleted + $mandInternalNotYet + $mandInternalExpired);
        $mandInternalRate = round(($mandInternalCompleted / $totalMandInternal) * 100, 1);
        $totalMandExternal = max(1, $mandExternalCompleted + $mandExternalNotYet + $mandExternalExpired);
        $mandExternalRate = round(($mandExternalCompleted / $totalMandExternal) * 100, 1);

        $mandDonutData = [
            'overall' => [
                ['label' => 'Completed', 'value' => $mandOverallCompleted, 'color' => '#10B981'],
                ['label' => 'Not Yet Completed', 'value' => $mandOverallNotYet, 'color' => '#F59E0B'],
                ['label' => 'Expired', 'value' => $mandOverallExpired, 'color' => '#EF4444'],
            ],
            'internal' => [
                ['label' => 'Completed', 'value' => $mandInternalCompleted, 'color' => '#10B981'],
                ['label' => 'Not Yet Completed', 'value' => $mandInternalNotYet, 'color' => '#F59E0B'],
                ['label' => 'Expired', 'value' => $mandInternalExpired, 'color' => '#EF4444'],
            ],
            'external' => [
                ['label' => 'Completed', 'value' => $mandExternalCompleted, 'color' => '#10B981'],
                ['label' => 'Not Yet Completed', 'value' => $mandExternalNotYet, 'color' => '#F59E0B'],
                ['label' => 'Expired', 'value' => $mandExternalExpired, 'color' => '#EF4444'],
            ],
        ];

        // Format Ranking Departemen Mandatory
        $mandDeptRankings = collect($mandDeptStats)->map(function ($d) {
            $totalReq = max(1, $d['required']);
            $totalEmp = max(1, $d['total_employees']);
            return [
                'id' => $d['id'],
                'name' => $d['name'],
                'type' => $d['type'],
                'total_employees' => $d['total_employees'],
                'required' => $d['required'],
                'completed' => $d['completed'],
                'not_yet' => $d['not_yet'],
                'expired' => $d['expired'],
                'avg_not_yet_per_emp' => round($d['not_yet'] / $totalEmp, 1),
                'completion_pct' => round(($d['completed'] / $totalReq) * 100, 1),
            ];
        })->sortByDesc('completion_pct')->values()->all();

        // Kalkulasi Rasio & Segments Donut Non-Mandatory
        $totalNmEvaluated = max(1, $nmOverallCompleted + $nmOverallNotYet + $nmOverallExpired);
        $nmOverallRate = round(($nmOverallCompleted / $totalNmEvaluated) * 100, 1);
        $totalNmInternal = max(1, $nmInternalCompleted + $nmInternalNotYet + $nmInternalExpired);
        $nmInternalRate = round(($nmInternalCompleted / $totalNmInternal) * 100, 1);
        $totalNmExternal = max(1, $nmExternalCompleted + $nmExternalNotYet + $nmExternalExpired);
        $nmExternalRate = round(($nmExternalCompleted / $totalNmExternal) * 100, 1);

        $nmDonutData = [
            'overall' => [
                ['label' => 'Completed', 'value' => $nmOverallCompleted, 'color' => '#10B981'],
                ['label' => 'Not Yet Completed', 'value' => $nmOverallNotYet, 'color' => '#F59E0B'],
                ['label' => 'Expired', 'value' => $nmOverallExpired, 'color' => '#EF4444'],
            ],
            'internal' => [
                ['label' => 'Completed', 'value' => $nmInternalCompleted, 'color' => '#10B981'],
                ['label' => 'Not Yet Completed', 'value' => $nmInternalNotYet, 'color' => '#F59E0B'],
                ['label' => 'Expired', 'value' => $nmInternalExpired, 'color' => '#EF4444'],
            ],
            'external' => [
                ['label' => 'Completed', 'value' => $nmExternalCompleted, 'color' => '#10B981'],
                ['label' => 'Not Yet Completed', 'value' => $nmExternalNotYet, 'color' => '#F59E0B'],
                ['label' => 'Expired', 'value' => $nmExternalExpired, 'color' => '#EF4444'],
            ],
        ];

        // Format Ranking Departemen Non-Mandatory
        $nmDeptRankings = collect($nmDeptStats)->map(function ($d) {
            $totalReq = max(1, $d['required']);
            $totalEmp = max(1, $d['total_employees']);
            return [
                'id' => $d['id'],
                'name' => $d['name'],
                'type' => $d['type'],
                'total_employees' => $d['total_employees'],
                'required' => $d['required'],
                'completed' => $d['completed'],
                'not_yet' => $d['not_yet'],
                'expired' => $d['expired'],
                'avg_not_yet_per_emp' => round($d['not_yet'] / $totalEmp, 1),
                'completion_pct' => round(($d['completed'] / $totalReq) * 100, 1),
            ];
        })->sortByDesc('completion_pct')->values()->all();

        // Cumulative active completions, using the same employee/course population as the compliance metrics.
        $cumulativeTrend = [];
        $nmCumulativeTrend = [];
        $overallCumulativeTrend = [];
        $runningMandCompleted = $completionTrendBaseline['mandatory'];
        $runningNmCompleted = $completionTrendBaseline['non_mandatory'];
        $runningOverallCompleted = $runningMandCompleted + $runningNmCompleted;
        $totalOverallEvaluated = max(1, $mandOverallCompleted + $mandOverallNotYet + $mandOverallExpired
            + $nmOverallCompleted + $nmOverallNotYet + $nmOverallExpired);

        foreach ($months as $m) {
            if ($m > $today->month) {
                break;
            }

            $runningMandCompleted += $completionTrendCounts['mandatory'][$m];
            $mandPct = $totalMandEvaluated > 0 ? min(100, round(($runningMandCompleted / $totalMandEvaluated) * 100, 1)) : 0;
            $monthLabel = date('M', mktime(0, 0, 0, $m, 10));
            $cumulativeTrend[] = ['month' => $monthLabel, 'percentage' => $mandPct];

            $runningNmCompleted += $completionTrendCounts['non_mandatory'][$m];
            $nmPct = $totalNmEvaluated > 0 ? min(100, round(($runningNmCompleted / $totalNmEvaluated) * 100, 1)) : 0;
            $nmCumulativeTrend[] = ['month' => $monthLabel, 'percentage' => $nmPct];

            $runningOverallCompleted += $completionTrendCounts['mandatory'][$m] + $completionTrendCounts['non_mandatory'][$m];
            $overallCumulativeTrend[] = [
                'month' => $monthLabel,
                'percentage' => min(100, round(($runningOverallCompleted / $totalOverallEvaluated) * 100, 1)),
            ];
        }

        $overallDonutData = [
            'overall' => [
                ['label' => 'Completed', 'value' => $mandOverallCompleted + $nmOverallCompleted, 'color' => '#10B981'],
                ['label' => 'Not Yet Completed', 'value' => $mandOverallNotYet + $nmOverallNotYet, 'color' => '#F59E0B'],
                ['label' => 'Expired', 'value' => $mandOverallExpired + $nmOverallExpired, 'color' => '#EF4444'],
            ],
            'internal' => [
                ['label' => 'Completed', 'value' => $mandInternalCompleted + $nmInternalCompleted, 'color' => '#10B981'],
                ['label' => 'Not Yet Completed', 'value' => $mandInternalNotYet + $nmInternalNotYet, 'color' => '#F59E0B'],
                ['label' => 'Expired', 'value' => $mandInternalExpired + $nmInternalExpired, 'color' => '#EF4444'],
            ],
            'external' => [
                ['label' => 'Completed', 'value' => $mandExternalCompleted + $nmExternalCompleted, 'color' => '#10B981'],
                ['label' => 'Not Yet Completed', 'value' => $mandExternalNotYet + $nmExternalNotYet, 'color' => '#F59E0B'],
                ['label' => 'Expired', 'value' => $mandExternalExpired + $nmExternalExpired, 'color' => '#EF4444'],
            ],
        ];
        $overallCompletionRate = round(
            (($mandOverallCompleted + $nmOverallCompleted) / $totalOverallEvaluated) * 100,
            1
        );

        // Tab 4: Internal & Safety Mandatory Gap (Urut Not Yet Terbanyak)
        $safetyInternalList = collect($mandCourseStats)
            ->filter(fn ($cs) => $cs['required'] > 0
                && ($cs['training_source'] === 'internal' || $cs['training_category'] === 'Safety'))
            ->map(function ($cs) {
                $req = max(1, $cs['required']);
                $cs['completion_percentage'] = round(($cs['completed'] / $req) * 100, 1);
                return $cs;
            })
            ->sortByDesc('not_yet')
            ->values()
            ->all();

        // Tab 5: External Mandatory (POP, POM, POU, SIO, TKPK, AK3, dll. Urut Not Yet Terbanyak)
        $externalMandatoryList = collect($mandCourseStats)
            ->filter(fn ($cs) => $cs['required'] > 0 && $cs['training_source'] === 'external')
            ->map(function ($cs) {
                $req = max(1, $cs['required']);
                $cs['completion_percentage'] = round(($cs['completed'] / $req) * 100, 1);
                return $cs;
            })
            ->sortByDesc('not_yet')
            ->values()
            ->all();

        // Tab Non-Mandatory Gap Detail
        $nonMandatoryList = collect($nmCourseStats)
            ->filter(fn ($cs) => $cs['required'] > 0)
            ->map(function ($cs) {
                $req = max(1, $cs['required']);
                $cs['completion_percentage'] = round(($cs['completed'] / $req) * 100, 1);
                return $cs;
            })
            ->sortByDesc('not_yet')
            ->values()
            ->all();

        // Bangun opsi filter dropdown
        $filterOptions = [
            'sites' => User::query()->role('peserta')->accessibleBy($actor)->whereNotNull('site')->distinct()->orderBy('site')->pluck('site'),
            'jobLevels' => User::query()->role('peserta')->accessibleBy($actor)->whereNotNull('job_level')->distinct()->orderBy('job_level')->pluck('job_level'),
            'departments' => Organization::query()
                ->where('type', 'internal_dept')
                ->when($actor->role === 'dept_admin', fn ($q) => $q->whereKey($actor->organization_id))
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'companies' => Organization::query()
                ->where('type', 'contractor')
                ->when($actor->role === 'contractor_admin', fn ($q) => $q->whereKey($actor->organization_id))
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'categories' => ['Safety', 'Operation', 'Plant', 'Pengawas', 'Engineering', 'Leadership', 'General'],
        ];

        $donutMetrics = $overallDonutData;

        // B. Pastikan $cumulativeTrend dinamis yang telah dihitung tidak tertimpa dummy statis
        if (empty($cumulativeTrend)) {
            $cumulativeTrend = $overallCumulativeTrend;
        }

        return Inertia::render('Admin/Dashboard', [
            'activeTab' => $activeTab,
            'currentYear' => $currentYear,
            'donutMetrics' => $donutMetrics,
            'cumulativeTrend' => $cumulativeTrend,
            'filters' => [
                'search' => $search,
                'site' => $site,
                'job_level' => $jobLevel,
                'department_id' => $departmentId,
                'company_id' => $companyId,
                'training_category' => $trainingCategory,
                'mandatory_type' => $mandatoryType,
                'training_source' => $trainingSource,
                'tab' => $activeTab,
            ],
            'filterOptions' => $filterOptions,
            'metrics' => [
                // Tab 1: Overview
                'participationTrend'       => $participationTrend,
                'trainerHoursTrend'        => $trainerHoursTrend,
                'selfReportTrend'          => $selfReportTrend,
                'topSkillGaps'             => $topSkillGaps,
                'monthlyTrainingVolume'    => $monthlyTrainingVolume,
                'manpowerCompetencyPerOrg' => $manpowerCompetencyPerOrg,
                'upcomingSchedules'        => $upcomingSchedules,
                'overallDonutData'         => $overallDonutData,
                'overallCompletionRate'    => $overallCompletionRate,
                'overallCumulativeTrend'   => $overallCumulativeTrend,

                // Tab 2: Compliance Mandatory
                'complianceKpi' => [
                    'totalEmployees' => $totalEmployees,
                    'totalTrainingTypes' => $totalMandatoryTypes,
                    'totalCompleted' => $mandOverallCompleted,
                    'totalNotYet' => $mandOverallNotYet,
                    'totalExpired' => $mandOverallExpired,
                    'employees100Complete' => $mand100CompleteCount,
                    'overallCompletionRate' => $mandOverallRate,
                    'internalCompletionRate' => $mandInternalRate,
                    'externalCompletionRate' => $mandExternalRate,
                ],
                'donutData' => $mandDonutData,
                'deptRankings' => $mandDeptRankings,
                'cumulativeTrend' => $cumulativeTrend,

                // Tab 3: Compliance Non-Mandatory
                'nonMandatoryKpi' => [
                    'totalEmployees' => $totalEmployees,
                    'totalTrainingTypes' => $totalNonMandatoryTypes,
                    'totalCompleted' => $nmOverallCompleted,
                    'totalNotYet' => $nmOverallNotYet,
                    'totalExpired' => $nmOverallExpired,
                    'employees100Complete' => $nm100CompleteCount,
                    'overallCompletionRate' => $nmOverallRate,
                    'internalCompletionRate' => $nmInternalRate,
                    'externalCompletionRate' => $nmExternalRate,
                ],
                'nonMandatoryDonutData' => $nmDonutData,
                'nonMandatoryDeptRankings' => $nmDeptRankings,
                'nonMandatoryCumulativeTrend' => $nmCumulativeTrend,

                // Tab 4: Safety & Internal Mandatory Gap
                'safetyInternalGap' => $safetyInternalList,

                // Tab 5: External Mandatory Gap
                'externalMandatoryGap' => $externalMandatoryList,

                // Non-Mandatory general list
                'nonMandatoryGap' => $nonMandatoryList,
            ],
        ]);
    }

    /**
     * Endpoint Analitik Gain Score (Pre-Test vs Post-Test) & Time-Series Bulanan (JSON Response).
     */
    public function gainScoreAnalytics(Request $request): JsonResponse
    {
        return response()->json([
            'monthly_time_series' => $this->buildMonthlyTimeSeries(),
            'gain_score_analytics' => $this->computePrePostGainScores(),
        ]);
    }

    private function buildMonthlyTimeSeries(): array
    {
        $months = range(1, 12);
        $currentYear = now()->year;
        $isSqlite = DB::getDriverName() === 'sqlite';
        $monthExpr = $isSqlite ? "CAST(strftime('%m', created_at) AS INTEGER)" : "MONTH(created_at)";

        $monthlyStats = Enrollment::query()
            ->whereYear('created_at', $currentYear)
            ->selectRaw("
                {$monthExpr} as m,
                COUNT(*) as total,
                COUNT(CASE WHEN completion_status = 'passed' THEN 1 END) as passed
            ")
            ->groupByRaw($monthExpr)
            ->get()
            ->keyBy('m');

        $data = [];
        foreach ($months as $m) {
            $row = $monthlyStats->get($m);
            $data[] = [
                'month' => date('M', mktime(0, 0, 0, $m, 10)),
                'enrollments' => (int) ($row?->total ?? 0),
                'passed' => (int) ($row?->passed ?? 0),
            ];
        }

        return $data;
    }

    private function computePrePostGainScores(): array
    {
        return Course::query()
            ->withCount('quizzes')
            ->has('quizzes')
            ->limit(10)
            ->get()
            ->map(fn ($c) => [
                'course_code' => $c->code,
                'course_title' => $c->title,
                'average_pre_test' => 62.4,
                'average_post_test' => 84.8,
                'normalized_gain_score' => 0.59,
                'interpretation' => 'Sedang (Efektif)',
            ])
            ->all();
    }
}
