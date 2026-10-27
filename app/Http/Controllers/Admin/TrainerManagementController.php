<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Skill;
use App\Models\TrainerAuthorizedSkill;
use App\Models\TrainerEvaluation;
use App\Models\TrainerRoster;
use App\Models\TrainerTaskAndKpi;
use App\Models\TrainerTeachingLog;
use App\Models\User;
use App\Services\TrainerKpiActualsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TrainerManagementController extends Controller
{
    /**
     * TUGAS 2 - Poin 1: Index Trainer Management (Pusat Kendali Admin LnD).
     * Menampilkan daftar seluruh Trainer beserta ringkasan KPI, filter,
     * dan modal Angkat Manpower Jadi Trainer.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));
        $trainerType = $request->input('trainer_type');
        $rosterStatus = $request->input('roster_status');
        $department = $request->input('department');

        // Filter Bulan (Format: Y-m, contoh: 2026-10). Default ke bulan berjalan.
        $selectedMonth = trim((string) $request->input('month', now()->format('Y-m')));
        try {
            $monthDate = Carbon::createFromFormat('Y-m', $selectedMonth) ?: now();
        } catch (\Throwable $e) {
            $monthDate = now();
            $selectedMonth = $monthDate->format('Y-m');
        }
        $startOfMonth = $monthDate->copy()->startOfMonth()->toDateString();
        $endOfMonth = $monthDate->copy()->endOfMonth()->toDateString();
        $today = now()->toDateString();

        // Query seluruh pengguna yang terdaftar sebagai Trainer
        $trainersQuery = User::query()
            ->trainers()
            ->with([
                'organization:id,name,type',
                'jobPosition:id,code,level_name,position_title',
                'authorizedSkills.skill:id,code,name,category',
                'rosters' => fn ($q) => $q->whereDate('roster_date', $today),
            ])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('nik', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when(!empty($trainerType), fn ($q) => $q->where('trainer_type', $trainerType))
            ->when(!empty($department), fn ($q) => $q->where('department', $department));

        $rawTrainers = $trainersQuery->orderBy('name')->get();
        $trainerIds = $rawTrainers->pluck('id')->all();

        // Hitung total jam mengajar pada bulan yang dipilih ($selectedMonth)
        $monthHoursMap = DB::table('trainer_teaching_logs')
            ->whereIn('trainer_id', $trainerIds)
            ->whereBetween('log_date', [$startOfMonth, $endOfMonth])
            ->groupBy('trainer_id')
            ->select('trainer_id', DB::raw('SUM(duration_hours) as total_hours'))
            ->pluck('total_hours', 'trainer_id')
            ->all();

        // Total peserta yang diajar pada bulan tersebut
        $participantsCountMap = DB::table('trainer_teaching_logs')
            ->whereIn('trainer_id', $trainerIds)
            ->whereBetween('log_date', [$startOfMonth, $endOfMonth])
            ->groupBy('trainer_id')
            ->select('trainer_id', DB::raw('SUM(scanned_participants_count) as total_participants'))
            ->pluck('total_participants', 'trainer_id')
            ->all();

        // Rata-rata rating penilaian peserta
        $ratingsMap = DB::table('trainer_evaluations')
            ->whereIn('trainer_id', $trainerIds)
            ->groupBy('trainer_id')
            ->select('trainer_id', DB::raw('ROUND(AVG(rating), 2) as avg_rating'), DB::raw('COUNT(id) as total_evals'))
            ->get()
            ->keyBy('trainer_id');

        $trainers = $rawTrainers->map(function (User $t) use ($monthHoursMap, $participantsCountMap, $ratingsMap) {
            $todayRoster = $t->rosters->first();
            $currentStatus = $todayRoster ? $todayRoster->status : 'on_duty';
            $ratingInfo = $ratingsMap->get($t->id);

            return [
                'id' => $t->id,
                'name' => $t->name,
                'nik' => $t->nik,
                'email' => $t->email,
                'department' => $t->department ?? '-',
                'job_position' => $t->jobPosition?->full_label ?? 'Trainer / SME',
                'trainer_type' => $t->trainer_type ?? 'INTERNAL_SME',
                'roster_pattern' => $t->roster_pattern ?? '5-2 (Senin - Jumat)',
                'is_trainer' => (bool) $t->is_trainer,
                'today_roster' => [
                    'status' => $currentStatus,
                    'label' => match ($currentStatus) {
                        'on_duty' => 'ON DUTY',
                        'off_roster' => 'OFF ROSTER',
                        'annual_leave' => 'CUTI TAHUNAN',
                        'sick' => 'SAKIT (SKD)',
                        'site_visit' => 'SITE VISIT',
                        default => strtoupper($currentStatus),
                    },
                    'shift' => $todayRoster?->shift ?? 'day',
                    'notes' => $todayRoster?->notes,
                ],
                'authorized_skills_count' => $t->authorizedSkills->count(),
                'authorized_skills_preview' => $t->authorizedSkills->take(3)->map(fn ($as) => [
                    'id' => $as->id,
                    'code' => $as->skill?->code,
                    'name' => $as->skill?->name,
                ]),
                'month_teaching_hours' => (float) ($monthHoursMap[$t->id] ?? 0),
                'total_scanned_participants' => (int) ($participantsCountMap[$t->id] ?? 0),
                'average_rating' => $ratingInfo ? (float) $ratingInfo->avg_rating : 5.0,
                'evaluations_count' => $ratingInfo ? (int) $ratingInfo->total_evals : 0,
            ];
        });

        // Filter roster_status jika dipilih
        if (!empty($rosterStatus)) {
            $trainers = $trainers->filter(fn ($t) => $t['today_roster']['status'] === $rosterStatus)->values();
        }

        $summary = [
            'total_trainers' => $rawTrainers->count(),
            'on_duty_today' => $rawTrainers->filter(fn ($t) => ($t->rosters->first()?->status ?? 'on_duty') === 'on_duty')->count(),
            'off_today' => $rawTrainers->filter(fn ($t) => in_array($t->rosters->first()?->status ?? 'on_duty', ['off_roster', 'annual_leave', 'sick'], true))->count(),
            'total_hours_this_month' => round(array_sum($monthHoursMap), 1),
            'total_participants_served' => array_sum($participantsCountMap),
            'selected_month_label' => $monthDate->translatedFormat('F Y'),
            'overall_avg_rating' => $ratingsMap->count() > 0
                ? round($ratingsMap->avg('avg_rating'), 2)
                : 5.0,
        ];

        // -----------------------------------------------------------------
        // JADWAL TRAINER MINGGU INI & BULAN INI (KALENDER, LIST & DETAIL)
        // -----------------------------------------------------------------
        $now = now();
        $weekStart = $now->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $now->copy()->endOfWeek(Carbon::SUNDAY);
        $monthStart = $monthDate->copy()->startOfMonth();
        $monthEnd = $monthDate->copy()->endOfMonth();

        $queryStart = min($weekStart->toDateString(), $monthStart->toDateString());
        $queryEnd = max($weekEnd->toDateString(), $monthEnd->toDateString());

        // 1. Ambil Sesi Pelatihan Terjadwal (AttendanceSession)
        $sessionsQuery = AttendanceSession::query()
            ->with([
                'trainer:id,name,nik,department,trainer_type,email',
                'course:id,code,title,training_category,training_source,trainer_id',
                'course.trainer:id,name,nik,department,trainer_type,email',
            ])
            ->withAttendanceCounts()
            ->whereBetween('session_date', [$queryStart, $queryEnd])
            ->orderBy('session_date')
            ->orderBy('start_time');

        // 2. Ambil Roster Tugas & Penugasan Khusus Trainer (TrainerRoster)
        $rostersQuery = TrainerRoster::query()
            ->with([
                'trainer:id,name,nik,department,trainer_type',
                'course:id,code,title,training_category',
            ])
            ->whereBetween('roster_date', [$queryStart, $queryEnd])
            ->orderBy('roster_date');

        $sessionsCollection = $sessionsQuery->get();
        $rostersCollection = $rostersQuery->get();

        $formattedSessions = $sessionsCollection->map(function ($s) {
            $trainer = $s->trainer ?? $s->course?->trainer;
            $date = $s->session_date ? Carbon::parse($s->session_date) : null;
            $startTime = $s->start_time ? substr((string)$s->start_time, 0, 5) : '08:00';
            $endTime = $s->end_time ? substr((string)$s->end_time, 0, 5) : '16:00';

            return [
                'id' => (string) $s->id,
                'source' => 'session',
                'title' => $s->title ?: ($s->course?->title ?? 'Sesi Pelatihan'),
                'course_id' => $s->course_id,
                'course_code' => $s->course?->code ?? 'CRS',
                'course_title' => $s->course?->title ?? 'Materi Pelatihan',
                'training_category' => $s->course?->training_category ?? 'General',
                'training_source' => $s->course?->training_source ?? 'internal',
                'trainer_id' => $trainer?->id,
                'trainer_name' => $trainer?->name ?? 'Instruktur Belum Ditugaskan',
                'trainer_nik' => $trainer?->nik ?? '-',
                'trainer_dept' => $trainer?->department ?? '-',
                'trainer_type' => $trainer?->trainer_type ?? 'INTERNAL_SME',
                'date' => $date?->toDateString(),
                'date_formatted' => $date?->translatedFormat('d M Y') ?? '-',
                'day_name' => $date?->translatedFormat('l') ?? '-',
                'start_time' => $startTime,
                'end_time' => $endTime,
                'time_range' => "{$startTime} - {$endTime} WIB",
                'duration_hours' => (float) ($s->duration_hours ?? 2.0),
                'location' => $s->location ?? 'Site PBJ Training Room',
                'quota' => (int) ($s->quota ?? 25),
                'present_count' => (int) ($s->present_count ?? 0),
                'permit_count' => (int) ($s->permit_count ?? 0),
                'absent_count' => (int) ($s->absent_count ?? 0),
                'plan_status' => $s->plan_status ?? 'approved',
                'is_active' => (bool) $s->is_active,
                'activity_type' => 'TRAINING_CLASS',
                'notes' => 'Sesi pelatihan terdaftar di kalender.',
            ];
        });

        // Kumpulkan sesi yang sudah ada agar tidak diduplikasi oleh roster harian biasa
        $sessionTrainerDates = $formattedSessions->map(fn ($s) => ($s['trainer_id'] ?? 0) . '_' . $s['date'])->all();

        $formattedRosters = $rostersCollection
            ->filter(fn ($r) => !in_array(($r->trainer_id . '_' . $r->roster_date?->toDateString()), $sessionTrainerDates, true))
            ->map(function ($r) {
                $date = $r->roster_date ? Carbon::parse($r->roster_date) : null;
                return [
                    'id' => 'roster_' . $r->id,
                    'source' => 'roster',
                    'title' => $r->activity_label . ($r->course ? ' - ' . $r->course->title : ''),
                    'course_id' => $r->course_id,
                    'course_code' => $r->course?->code ?? '-',
                    'course_title' => $r->course?->title ?? '-',
                    'training_category' => $r->course?->training_category ?? 'General',
                    'training_source' => 'internal',
                    'trainer_id' => $r->trainer_id,
                    'trainer_name' => $r->trainer?->name ?? 'Trainer',
                    'trainer_nik' => $r->trainer?->nik ?? '-',
                    'trainer_dept' => $r->trainer?->department ?? '-',
                    'trainer_type' => $r->trainer?->trainer_type ?? 'INTERNAL_SME',
                    'date' => $date?->toDateString(),
                    'date_formatted' => $date?->translatedFormat('d M Y') ?? '-',
                    'day_name' => $date?->translatedFormat('l') ?? '-',
                    'start_time' => '07:00',
                    'end_time' => '15:00',
                    'time_range' => ($r->shift === 'night' ? '19:00 - 05:00' : '07:00 - 15:00') . ' WIB',
                    'duration_hours' => 8.0,
                    'location' => $r->location ?? 'Site PBJ Operations',
                    'quota' => null,
                    'present_count' => null,
                    'permit_count' => 0,
                    'absent_count' => 0,
                    'plan_status' => $r->status,
                    'is_active' => $r->status === 'on_duty',
                    'activity_type' => $r->activity_type ?? 'ON_DUTY',
                    'notes' => $r->notes ?? ('Jadwal Roster: ' . $r->status_label),
                ];
            });

        $allSchedules = $formattedSessions->concat($formattedRosters)->sortBy([
            ['date', 'asc'],
            ['start_time', 'asc'],
        ])->values();

        $weekStartStr = $weekStart->toDateString();
        $weekEndStr = $weekEnd->toDateString();
        $monthStartStr = $monthStart->toDateString();
        $monthEndStr = $monthEnd->toDateString();

        $weekSchedules = $allSchedules->filter(fn ($s) => $s['date'] >= $weekStartStr && $s['date'] <= $weekEndStr)->values();
        $monthSchedules = $allSchedules->filter(fn ($s) => $s['date'] >= $monthStartStr && $s['date'] <= $monthEndStr)->values();

        // 7 Hari untuk Kalender Mingguan
        $weekDays = [];
        $tempDay = $weekStart->copy();
        for ($i = 0; $i < 7; $i++) {
            $dayDateStr = $tempDay->toDateString();
            $weekDays[] = [
                'date' => $dayDateStr,
                'day_name' => $tempDay->translatedFormat('D'),
                'day_full' => $tempDay->translatedFormat('l'),
                'day_number' => $tempDay->format('d'),
                'month_name' => $tempDay->translatedFormat('M'),
                'is_today' => $dayDateStr === $now->toDateString(),
            ];
            $tempDay->addDay();
        }

        $scheduleStats = [
            'week' => [
                'total_sessions' => $weekSchedules->count(),
                'active_trainers' => $weekSchedules->pluck('trainer_id')->filter()->unique()->count(),
                'total_hours' => round($weekSchedules->sum('duration_hours'), 1),
                'range_label' => $weekStart->translatedFormat('d M') . ' - ' . $weekEnd->translatedFormat('d M Y'),
                'week_number' => $weekStart->weekOfYear,
            ],
            'month' => [
                'total_sessions' => $monthSchedules->count(),
                'active_trainers' => $monthSchedules->pluck('trainer_id')->filter()->unique()->count(),
                'total_hours' => round($monthSchedules->sum('duration_hours'), 1),
                'range_label' => $monthStart->translatedFormat('F Y'),
                'month_name' => $monthStart->translatedFormat('F'),
                'year' => $monthStart->format('Y'),
            ],
        ];

        return Inertia::render('Admin/Trainers/Index', [
            'trainers' => $trainers,
            'summary' => $summary,
            'trainerSchedules' => [
                'week' => $weekSchedules,
                'month' => $monthSchedules,
                'weekDays' => $weekDays,
                'stats' => $scheduleStats,
            ],
            'filters' => [
                'search' => $search,
                'trainer_type' => $trainerType,
                'roster_status' => $rosterStatus,
                'department' => $department,
                'month' => $selectedMonth,
            ],
            'eligibleManpower' => User::query()
                ->where('is_trainer', false)
                ->where('role', '!=', 'trainer')
                ->select('id', 'name', 'nik', 'department')
                ->orderBy('name')
                ->limit(200)
                ->get(),
            'masterSkills' => Skill::query()
                ->select('id', 'code', 'name', 'category')
                ->orderBy('code')
                ->get(),
            'departments' => User::query()
                ->whereNotNull('department')
                ->distinct()
                ->pluck('department')
                ->sort()
                ->values(),
        ]);
    }

    public function masterRosterCalendar(Request $request): Response
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'department' => ['nullable', 'string', 'max:100'],
            'trainer_type' => ['nullable', 'in:INTERNAL_SME,FULL_TIME_LND,EXTERNAL_VENDOR'],
            'roster_status' => ['nullable', 'in:on_duty,off_roster,annual_leave,sick,site_visit'],
            'activity_type' => ['nullable', 'in:TRAINING_CLASS,FIELD_OBSERVATION,LND_ADMIN,STANDBY,OFF'],
        ]);

        $monthDate = isset($validated['month'])
            ? Carbon::createFromFormat('Y-m', $validated['month'])
            : now()->startOfMonth();
        $start = $monthDate->copy()->startOfMonth();
        $end = $monthDate->copy()->endOfMonth();

        $trainerQuery = User::query()
            ->trainers()
            ->when($validated['department'] ?? null, fn ($query, $department) => $query->where('department', $department))
            ->when($validated['trainer_type'] ?? null, fn ($query, $type) => $query->where('trainer_type', $type))
            ->orderBy('name');
        $trainers = $trainerQuery->get(['id', 'name', 'department', 'trainer_type']);
        $trainerIds = $trainers->modelKeys();

        $rosters = TrainerRoster::query()
            ->with(['trainer:id,name,department,trainer_type', 'course:id,code,title'])
            ->whereIn('trainer_id', $trainerIds)
            ->whereBetween('roster_date', [$start->toDateString(), $end->toDateString()])
            ->get();

        $sessions = AttendanceSession::query()
            ->with(['trainer:id,name,department,trainer_type', 'course:id,code,title,trainer_id', 'course.trainer:id,name,department,trainer_type'])
            ->whereBetween('session_date', [$start->toDateString(), $end->toDateString()])
            ->where(function ($query) use ($trainerIds) {
                $query->whereIn('trainer_id', $trainerIds)
                    ->orWhereHas('course', fn ($courseQuery) => $courseQuery->whereIn('trainer_id', $trainerIds));
            })
            ->orderBy('start_time')
            ->get();

        $calendarItemsByDate = [];
        foreach ($rosters as $roster) {
            $date = $roster->roster_date->format('Y-m-d');
            $calendarItemsByDate[$date][$roster->trainer_id] = [
                'trainer_id' => $roster->trainer_id,
                'trainer_name' => $roster->trainer?->name ?? 'Trainer',
                'department' => $roster->trainer?->department,
                'trainer_type' => $roster->trainer?->trainer_type,
                'status' => $roster->status,
                'activity_type' => $roster->activity_type ?? 'TRAINING_CLASS',
                'course' => $roster->course ? [
                    'id' => $roster->course->id,
                    'code' => $roster->course->code,
                    'title' => $roster->course->title,
                ] : null,
                'shift' => $roster->shift,
                'location' => $roster->location,
                'notes' => $roster->notes,
                'sessions' => [],
                'has_conflict' => false,
            ];
        }

        foreach ($sessions as $session) {
            $trainer = $session->trainer ?? $session->course?->trainer;
            if (!$trainer ||
                (!empty($validated['department']) && $trainer->department !== $validated['department']) ||
                (!empty($validated['trainer_type']) && $trainer->trainer_type !== $validated['trainer_type'])) {
                continue;
            }

            $date = $session->session_date->format('Y-m-d');
            $item = $calendarItemsByDate[$date][$trainer->id] ?? [
                'trainer_id' => $trainer->id,
                'trainer_name' => $trainer->name,
                'department' => $trainer->department,
                'trainer_type' => $trainer->trainer_type,
                'status' => 'on_duty',
                'activity_type' => 'TRAINING_CLASS',
                'course' => null,
                'shift' => 'day',
                'location' => $session->location,
                'notes' => null,
                'sessions' => [],
                'has_conflict' => false,
            ];
            $item['sessions'][] = [
                'id' => $session->id,
                'title' => $session->title,
                'course' => $session->course ? [
                    'code' => $session->course->code,
                    'title' => $session->course->title,
                ] : null,
                'location' => $session->location,
                'start_time' => $session->start_time,
                'end_time' => $session->end_time,
            ];
            $item['has_conflict'] = in_array($item['status'], ['off_roster', 'annual_leave', 'sick'], true);
            $calendarItemsByDate[$date][$trainer->id] = $item;
        }

        $weeks = [];
        $calendarDates = [];
        $week = array_fill(0, (int) $start->dayOfWeek, null);
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $date = $day->toDateString();
            $items = collect($calendarItemsByDate[$date] ?? [])
                ->when($validated['roster_status'] ?? null, fn ($collection, $status) => $collection->filter(
                    fn ($item) => $item['status'] === $status
                ))
                ->when($validated['activity_type'] ?? null, fn ($collection, $activityType) => $collection->filter(
                    fn ($item) => $item['activity_type'] === $activityType
                ))
                ->sortBy('trainer_name')
                ->values()
                ->all();

            $calendarDay = [
                'date' => $date,
                'day_number' => $day->day,
                'is_today' => $day->isToday(),
            ];
            $calendarDates[] = $calendarDay;
            $week[] = $calendarDay + ['items' => $items];

            if (count($week) === 7) {
                $weeks[] = $week;
                $week = [];
            }
        }
        if ($week !== []) {
            $weeks[] = array_pad($week, 7, null);
        }

        $trainerRows = $trainers->map(fn (User $trainer) => [
            'id' => $trainer->id,
            'name' => $trainer->name,
            'department' => $trainer->department,
            'cells' => collect($calendarDates)->map(function ($calendarDay) use ($trainer, $calendarItemsByDate, $validated) {
                $item = $calendarItemsByDate[$calendarDay['date']][$trainer->id] ?? null;
                if (($validated['roster_status'] ?? null) && $item && $item['status'] !== $validated['roster_status']) {
                    $item = null;
                }
                if (($validated['activity_type'] ?? null) && $item && $item['activity_type'] !== $validated['activity_type']) {
                    $item = null;
                }

                return [
                    'date' => $calendarDay['date'],
                    'day_number' => $calendarDay['day_number'],
                    'is_today' => $calendarDay['is_today'],
                    'item' => $item,
                ];
            })->all(),
        ])->values();

        $activeCourses = Course::query()
            ->select('id', 'code', 'title')
            ->orderBy('code')
            ->get();

        return Inertia::render('Admin/Trainers/MasterRoster', [
            'month' => $monthDate->format('Y-m'),
            'weeks' => $weeks,
            'calendarDates' => $calendarDates,
            'filters' => [
                'department' => $validated['department'] ?? '',
                'trainer_type' => $validated['trainer_type'] ?? '',
                'roster_status' => $validated['roster_status'] ?? '',
                'activity_type' => $validated['activity_type'] ?? '',
            ],
            'departments' => User::query()->trainers()->whereNotNull('department')->where('department', '!=', '')
                ->distinct()->orderBy('department')->pluck('department'),
            'trainers' => $trainers,
            'trainerRows' => $trainerRows,
            'courses' => $activeCourses,
        ]);
    }

    /**
     * TUGAS 2 - Poin 2: Show Trainer 360° Profile (5 Tab Terintegrasi).
     */
    public function show(Request $request, User $trainer): Response
    {
        $currentMonth = $request->input('month', now()->format('Y-m'));
        $monthDate = Carbon::createFromFormat('Y-m', $currentMonth) ?: now();
        $startOfMonth = $monthDate->copy()->startOfMonth()->toDateString();
        $endOfMonth = $monthDate->copy()->endOfMonth()->toDateString();

        // 1. Profil Dasar Trainer
        $trainer->load(['organization:id,name,type', 'jobPosition:id,code,level_name,position_title']);

        // 2. TAB 1: Kompetensi Ajar (Certified Skills)
        $authorizedSkills = TrainerAuthorizedSkill::query()
            ->where('trainer_id', $trainer->id)
            ->with(['skill:id,code,name,category,competency_type', 'creator:id,name'])
            ->orderByDesc('valid_until')
            ->get()
            ->map(fn ($as) => [
                'id' => $as->id,
                'skill_id' => $as->skill_id,
                'code' => $as->skill?->code,
                'name' => $as->skill?->name,
                'category' => $as->skill?->category,
                'competency_type' => $as->skill?->competency_type,
                'license_type' => $as->license_type ?? 'ToT BNSP',
                'license_number' => $as->license_number ?? '-',
                'valid_until' => $as->valid_until?->format('Y-m-d'),
                'valid_until_formatted' => $as->valid_until?->format('d M Y') ?? 'Seumur Hidup',
                'status' => $as->status,
                'notes' => $as->notes,
            ]);

        // 3. TAB 2: Kalender Roster (Masuk / Off / Cuti)
        // Ambil data roster sepanjang bulan yang dipilih
        $rosters = TrainerRoster::query()
            ->where('trainer_id', $trainer->id)
            ->whereBetween('roster_date', [$startOfMonth, $endOfMonth])
            ->with('course:id,code,title')
            ->get()
            ->keyBy(fn ($r) => $r->roster_date->format('Y-m-d'));

        // Deteksi Bentrok: Cek apakah ada jadwal mengajar pada tanggal di mana Trainer sedang Off / Cuti
        $scheduledSessionsThisMonth = AttendanceSession::query()
            ->where(function ($q) use ($trainer) {
                $q->where('trainer_id', $trainer->id)
                    ->orWhereHas('course', fn ($cq) => $cq->where('trainer_id', $trainer->id));
            })
            ->whereBetween('session_date', [$startOfMonth, $endOfMonth])
            ->with(['course:id,code,title'])
            ->get();

        $scheduleConflicts = [];
        foreach ($scheduledSessionsThisMonth as $session) {
            $sessDate = $session->session_date?->format('Y-m-d');
            if ($sessDate && isset($rosters[$sessDate])) {
                $rosterStatus = $rosters[$sessDate]->status;
                if (in_array($rosterStatus, ['off_roster', 'annual_leave', 'sick'], true)) {
                    $scheduleConflicts[] = [
                        'date' => $sessDate,
                        'session_id' => $session->id,
                        'session_title' => $session->title,
                        'course_code' => $session->course?->code,
                        'roster_status' => $rosterStatus,
                        'warning' => "Bentrok: Jadwal kelas '{$session->title}' berjalan pada hari " . strtoupper($rosterStatus) . " Trainer!",
                    ];
                }
            }
        }

        // Bentuk struktur hari dalam bulan untuk kalender interaktif
        $calendarDays = [];
        $daysInMonth = $monthDate->daysInMonth;
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dayObj = $monthDate->copy()->day($d);
            $dateStr = $dayObj->format('Y-m-d');
            $rosterItem = $rosters->get($dateStr);
            $sessionsOnDay = $scheduledSessionsThisMonth->filter(fn ($s) => $s->session_date?->format('Y-m-d') === $dateStr);

            $calendarDays[] = [
                'date' => $dateStr,
                'day_number' => $d,
                'day_name' => $dayObj->translatedFormat('D'),
                'is_weekend' => $dayObj->isWeekend(),
                'status' => $rosterItem?->status ?? (
                    $dayObj->isWeekend() && $sessionsOnDay->isEmpty() ? 'off_roster' : 'on_duty'
                ),
                'activity_type' => $rosterItem?->activity_type ?? ($sessionsOnDay->isNotEmpty() ? 'TRAINING_CLASS' : ($dayObj->isWeekend() ? 'OFF' : 'STANDBY')),
                'course_id' => $rosterItem?->course_id,
                'course_title' => $rosterItem?->course?->title,
                'location' => $rosterItem?->location,
                'shift' => $rosterItem?->shift ?? 'day',
                'notes' => $rosterItem?->notes,
                'sessions_count' => $sessionsOnDay->count(),
                'sessions' => $sessionsOnDay->values()->map(fn ($s) => [
                    'id' => $s->id,
                    'title' => $s->title,
                    'course_code' => $s->course?->code,
                ]),
                'has_conflict' => collect($scheduleConflicts)->contains('date', $dateStr),
            ];
        }

        // 4. TAB 3: Riwayat Mengajar, Jam (Teaching Hours), & Daftar Peserta Scan QR
        $teachingLogs = TrainerTeachingLog::query()
            ->where('trainer_id', $trainer->id)
            ->with(['course:id,code,title', 'attendanceSession:id,title,session_date,duration_hours'])
            ->orderByDesc('log_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'attendance_session_id' => $log->attendance_session_id,
                'course_id' => $log->course_id,
                'course_code' => $log->course?->code ?? 'MANUAL',
                'training_title' => $log->training_title,
                'log_date' => $log->log_date?->format('Y-m-d'),
                'log_date_formatted' => $log->log_date?->format('d M Y'),
                'entry_source' => $log->entry_source,
                'entry_source_label' => $log->entry_source === 'auto_qr_session' ? 'Auto QR Session' : 'Input Manual LnD',
                'duration_hours' => (float) $log->duration_hours,
                'scanned_participants_count' => (int) $log->scanned_participants_count,
                'location' => $log->location ?? '-',
                'notes' => $log->notes,
            ]);

        // 5. TAB 4: Penugasan & KPI (Harian, Mingguan, Bulanan, Tahunan)
        $kpiTasks = TrainerTaskAndKpi::query()
            ->where('trainer_id', $trainer->id)
            ->orderBy('due_date')
            ->orderByDesc('id')
            ->get();
        $kpiActuals = app(TrainerKpiActualsService::class)->actualsFor($kpiTasks);
        $kpiTasks = $kpiTasks->map(function ($kpi) use ($kpiActuals) {
            $actuals = $kpiActuals->get($kpi->id);
            $kpi->actual_teaching_hours = $actuals['actual_teaching_hours'];
            $kpi->actual_participants_count = $actuals['actual_participants_count'];
            $kpi->actual_rating = $actuals['actual_rating'];

            return [
                'id' => $kpi->id,
                'title' => $kpi->title,
                'description' => $kpi->description,
                'period' => $kpi->period,
                'period_label' => match ($kpi->period) {
                    'daily' => 'Harian (Daily)',
                    'weekly' => 'Mingguan (Weekly)',
                    'monthly' => 'Bulanan (Monthly)',
                    'yearly' => 'Tahunan (Yearly)',
                    default => ucfirst($kpi->period),
                },
                'start_date' => $kpi->start_date?->format('Y-m-d'),
                'due_date' => $kpi->due_date?->format('Y-m-d'),
                'due_date_formatted' => $kpi->due_date?->format('d M Y') ?? 'Fleksibel',
                'target_teaching_hours' => (float) $kpi->target_teaching_hours,
                'actual_teaching_hours' => (float) $actuals['actual_teaching_hours'],
                'target_participants_count' => (int) $kpi->target_participants_count,
                'actual_participants_count' => (int) $actuals['actual_participants_count'],
                'target_rating' => (float) $kpi->target_rating,
                'actual_rating' => $actuals['evaluation_count'] > 0 ? $actuals['actual_rating'] : null,
                'progress_percentage' => $kpi->progress_percentage,
                'status' => $kpi->status,
            ];
        });

        // 6. TAB 5: Penilaian & Feedback Peserta
        $evaluations = TrainerEvaluation::query()
            ->where('trainer_id', $trainer->id)
            ->with(['participant:id,name,nik,department', 'course:id,code,title', 'session:id,title,session_date'])
            ->orderByDesc('created_at')
            ->get();

        $avgMaterialMastery = $evaluations->isNotEmpty() ? round($evaluations->avg('material_mastery_rating'), 2) : null;
        $avgDeliveryMethod = $evaluations->isNotEmpty() ? round($evaluations->avg('delivery_method_rating'), 2) : null;
        $avgPunctuality = $evaluations->isNotEmpty() ? round($evaluations->avg('punctuality_rating'), 2) : null;
        $overallAvgRating = $evaluations->isNotEmpty() ? round($evaluations->avg('rating'), 2) : null;

        $feedbackList = $evaluations->map(fn ($ev) => [
            'id' => $ev->id,
            'participant_name' => $ev->participant?->name ?? 'Peserta Anonim',
            'participant_nik' => $ev->participant?->nik ?? '-',
            'participant_dept' => $ev->participant?->department ?? '-',
            'course_title' => $ev->course?->title ?? $ev->session?->title ?? 'Pelatihan Umum',
            'training_date' => $ev->session?->session_date?->format('d M Y') ?? $ev->created_at->format('d M Y'),
            'rating' => (int) $ev->rating,
            'material_mastery_rating' => (int) $ev->material_mastery_rating,
            'delivery_method_rating' => (int) $ev->delivery_method_rating,
            'punctuality_rating' => (int) $ev->punctuality_rating,
            'comment' => $ev->comment ?: '(Tidak ada komentar tertulis)',
            'created_at' => $ev->created_at->format('d M Y H:i'),
        ]);

        // Master skills untuk dropdown tambah sertifikasi kompetensi
        $availableSkills = Skill::query()
            ->whereNotIn('id', $authorizedSkills->pluck('skill_id')->all())
            ->select('id', 'code', 'name', 'category')
            ->orderBy('category')
            ->orderBy('code')
            ->get();

        $activeCourses = Course::query()
            ->select('id', 'code', 'title')
            ->orderBy('code')
            ->get();

        return Inertia::render('Admin/Trainers/Show', [
            'trainer' => [
                'id' => $trainer->id,
                'name' => $trainer->name,
                'nik' => $trainer->nik,
                'email' => $trainer->email,
                'department' => $trainer->department ?? '-',
                'job_position' => $trainer->jobPosition?->full_label ?? 'Trainer / SME',
                'trainer_type' => $trainer->trainer_type ?? 'INTERNAL_SME',
                'roster_pattern' => $trainer->roster_pattern ?? '5-2 (Senin - Jumat)',
                'is_trainer' => (bool) $trainer->is_trainer,
                'today_roster' => $trainer->today_roster?->status_label ?? 'ON DUTY',
            ],
            'currentMonth' => $currentMonth,
            'calendarDays' => $calendarDays,
            'scheduleConflicts' => $scheduleConflicts,
            'authorizedSkills' => $authorizedSkills,
            'availableSkills' => $availableSkills,
            'teachingLogs' => $teachingLogs,
            'activeCourses' => $activeCourses,
            'kpiTasks' => $kpiTasks,
            'evaluationSummary' => [
                'overall_avg' => $overallAvgRating,
                'avg_material_mastery' => $avgMaterialMastery,
                'avg_delivery_method' => $avgDeliveryMethod,
                'avg_punctuality' => $avgPunctuality,
                'total_evaluations' => $evaluations->count(),
            ],
            'feedbackList' => $feedbackList,
        ]);
    }

    /**
     * TUGAS 2 - Poin 1: Angkat Manpower Jadi Trainer
     */
    public function promoteManpower(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'trainer_type' => ['required', 'string', 'in:INTERNAL_SME,FULL_TIME_LND,EXTERNAL_VENDOR'],
            'roster_pattern' => ['required', 'string', 'max:50'],
            'skill_ids' => ['nullable', 'array'],
            'skill_ids.*' => ['integer', 'exists:skills,id'],
            'license_type' => ['nullable', 'string', 'max:100'],
            'license_number' => ['nullable', 'string', 'max:100'],
            'valid_until' => ['nullable', 'date'],
        ]);

        DB::transaction(function () use ($validated, $request) {
            $user = User::findOrFail($validated['user_id']);
            $user->update([
                'is_trainer' => true,
                'role' => $user->role === 'peserta' ? 'trainer' : $user->role,
                'trainer_type' => $validated['trainer_type'],
                'roster_pattern' => $validated['roster_pattern'],
            ]);

            // Assign kompetensi jika ada
            if (!empty($validated['skill_ids'])) {
                foreach ($validated['skill_ids'] as $skillId) {
                    TrainerAuthorizedSkill::updateOrCreate(
                        [
                            'trainer_id' => $user->id,
                            'skill_id' => $skillId,
                        ],
                        [
                            'license_type' => $validated['license_type'] ?? 'ToT Internal/BNSP',
                            'license_number' => $validated['license_number'] ?? null,
                            'valid_until' => $validated['valid_until'] ?? null,
                            'created_by' => $request->user()?->id,
                        ]
                    );
                }
            }
        });

        return back()->with('success', 'Manpower berhasil diangkat menjadi Trainer dan lisensi kompetensi telah ditetapkan.');
    }

    /**
     * Update profil metadata Trainer (trainer_type & roster_pattern).
     */
    public function updateProfile(Request $request, User $trainer): RedirectResponse
    {
        $validated = $request->validate([
            'trainer_type' => ['required', 'string', 'in:INTERNAL_SME,FULL_TIME_LND,EXTERNAL_VENDOR'],
            'roster_pattern' => ['required', 'string', 'max:50'],
        ]);

        $trainer->update($validated);

        return back()->with('success', 'Informasi profil Trainer berhasil diperbarui.');
    }

    /**
     * TAB 1: Tambah sertifikasi kompetensi ajar Trainer
     */
    public function storeAuthorizedSkill(Request $request, User $trainer): RedirectResponse
    {
        $validated = $request->validate([
            'skill_id' => ['required', 'integer', 'exists:skills,id'],
            'license_type' => ['nullable', 'string', 'max:100'],
            'license_number' => ['nullable', 'string', 'max:100'],
            'valid_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        TrainerAuthorizedSkill::updateOrCreate(
            [
                'trainer_id' => $trainer->id,
                'skill_id' => $validated['skill_id'],
            ],
            [
                'license_type' => $validated['license_type'] ?? 'ToT BNSP',
                'license_number' => $validated['license_number'],
                'valid_until' => $validated['valid_until'],
                'notes' => $validated['notes'],
                'created_by' => $request->user()?->id,
            ]
        );

        return back()->with('success', 'Kompetensi ajar Trainer berhasil ditambahkan ke daftar bersertifikasi.');
    }

    /**
     * TAB 1: Hapus kompetensi ajar Trainer
     */
    public function destroyAuthorizedSkill(Request $request, User $trainer, TrainerAuthorizedSkill $skill): RedirectResponse
    {
        if ($skill->trainer_id !== $trainer->id) {
            abort(403);
        }

        $skill->delete();

        return back()->with('success', 'Kompetensi ajar berhasil dihapus dari profil Trainer.');
    }

    /**
     * TAB 2: Atur Roster Single Date
     */
    public function setRoster(Request $request, User $trainer): RedirectResponse
    {
        $validated = $request->validate([
            'roster_date' => ['required', 'date'],
            'status' => ['required', 'string', 'in:on_duty,off_roster,annual_leave,sick,site_visit'],
            'activity_type' => ['required', 'in:TRAINING_CLASS,FIELD_OBSERVATION,LND_ADMIN,STANDBY,OFF'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'location' => ['nullable', 'string', 'max:150'],
            'shift' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:300'],
        ]);

        if (in_array($validated['status'], ['off_roster', 'annual_leave', 'sick'], true)) {
            $this->ensureNoTeachingConflict($trainer, [$validated['roster_date']]);
        }
        if (in_array($validated['status'], ['off_roster', 'annual_leave', 'sick'], true)) {
            $validated['activity_type'] = 'OFF';
            $validated['course_id'] = null;
        }

        TrainerRoster::updateOrCreate(
            [
                'trainer_id' => $trainer->id,
                'roster_date' => $validated['roster_date'],
            ],
            [
                'status' => $validated['status'],
                'activity_type' => $validated['activity_type'],
                'course_id' => $validated['course_id'] ?? null,
                'location' => $validated['location'] ?? null,
                'shift' => $validated['shift'] ?? 'day',
                'notes' => $validated['notes'],
                'created_by' => $request->user()?->id,
            ]
        );

        return back()->with('success', "Status roster tanggal {$validated['roster_date']} berhasil diperbarui.");
    }

    /**
     * TAB 2: Bulk Set Date Range Roster
     */
    public function bulkSetRoster(Request $request, User $trainer): RedirectResponse
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['required', 'string', 'in:on_duty,off_roster,annual_leave,sick,site_visit'],
            'activity_type' => ['required', 'in:TRAINING_CLASS,FIELD_OBSERVATION,LND_ADMIN,STANDBY,OFF'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'location' => ['nullable', 'string', 'max:150'],
            'shift' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:300'],
        ]);

        $start = Carbon::parse($validated['start_date']);
        $end = Carbon::parse($validated['end_date']);
        $dates = [];
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dates[] = $date->toDateString();
        }
        if (in_array($validated['status'], ['off_roster', 'annual_leave', 'sick'], true)) {
            $this->ensureNoTeachingConflict($trainer, $dates);
        }
        if (in_array($validated['status'], ['off_roster', 'annual_leave', 'sick'], true)) {
            $validated['activity_type'] = 'OFF';
            $validated['course_id'] = null;
        }

        DB::transaction(function () use ($trainer, $start, $end, $validated, $request) {
            $current = $start->copy();
            while ($current->lte($end)) {
                TrainerRoster::updateOrCreate(
                    [
                        'trainer_id' => $trainer->id,
                        'roster_date' => $current->toDateString(),
                    ],
                    [
                        'status' => $validated['status'],
                        'activity_type' => $validated['activity_type'],
                        'course_id' => $validated['course_id'] ?? null,
                        'location' => $validated['location'] ?? null,
                        'shift' => $validated['shift'] ?? 'day',
                        'notes' => $validated['notes'],
                        'created_by' => $request->user()?->id,
                    ]
                );
                $current->addDay();
            }
        });

        return back()->with('success', "Roster periode {$validated['start_date']} s/d {$validated['end_date']} berhasil disetel.");
    }

    private function ensureNoTeachingConflict(User $trainer, array $dates): void
    {
        $conflicts = AttendanceSession::query()
            ->with('course:id,code,title')
            ->whereIn('session_date', $dates)
            ->where(function ($query) use ($trainer) {
                $query->where('trainer_id', $trainer->id)
                    ->orWhereHas('course', fn ($courseQuery) => $courseQuery->where('trainer_id', $trainer->id));
            })
            ->get(['id', 'title', 'session_date', 'course_id'])
            ->map(fn (AttendanceSession $session) => "{$session->session_date->format('Y-m-d')}: {$session->title}")
            ->all();

        if ($conflicts !== []) {
            throw ValidationException::withMessages([
                'status' => 'Roster tidak dapat disetel sebagai Off/Cuti/Sakit karena terdapat jadwal mengajar: '.implode('; ', $conflicts),
            ]);
        }
    }

    /**
     * TAB 3: Input Jam Mengajar Manual bagi Admin LnD
     */
    public function storeManualTeachingLog(Request $request, User $trainer): RedirectResponse
    {
        $validated = $request->validate([
            'training_title' => ['required', 'string', 'max:200'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'log_date' => ['required', 'date'],
            'duration_hours' => ['required', 'numeric', 'min:0.5', 'max:24'],
            'scanned_participants_count' => ['required', 'integer', 'min:0'],
            'location' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        TrainerTeachingLog::create([
            'trainer_id' => $trainer->id,
            'course_id' => $validated['course_id'] ?? null,
            'training_title' => $validated['training_title'],
            'log_date' => $validated['log_date'],
            'entry_source' => 'manual_lnd_entry',
            'duration_hours' => round((float) $validated['duration_hours'], 2),
            'scanned_participants_count' => (int) $validated['scanned_participants_count'],
            'location' => $validated['location'],
            'notes' => $validated['notes'],
            'created_by' => $request->user()?->id,
        ]);

        app(TrainerKpiActualsService::class)->syncTrainer($trainer->id);

        return back()->with('success', 'Jam mengajar manual berhasil dicatat ke rekam jejak Trainer.');
    }

    /**
     * TAB 3: Ambil Daftar Lengkap Peserta yang Scan QR untuk Modal/Drawer
     */
    public function getScannedParticipants(AttendanceSession $session): JsonResponse
    {
        $participants = Attendance::query()
            ->where('attendance_session_id', $session->id)
            ->where('status', 'present')
            ->with(['user:id,name,nik,department,organization_id', 'user.organization:id,name'])
            ->orderBy('scanned_at')
            ->get()
            ->map(fn ($att) => [
                'id' => $att->id,
                'name' => $att->user?->name ?? 'Peserta Tidak Diketahui',
                'nik' => $att->user?->nik ?? '-',
                'department' => $att->user?->department ?? '-',
                'company' => $att->user?->organization?->name ?? 'Internal PBJ',
                'scanned_at' => $att->scanned_at ? $att->scanned_at->format('d M Y - H:i:s') : '-',
                'device_info' => $att->device_info,
            ]);

        return response()->json([
            'session' => [
                'id' => $session->id,
                'title' => $session->title,
                'session_date' => $session->session_date?->format('d M Y'),
                'duration_hours' => $session->duration_hours,
                'total_scanned' => $participants->count(),
            ],
            'participants' => $participants,
        ]);
    }

    /**
     * TAB 4: Tambah Target KPI / Penugasan Baru
     */
    public function storeKpiTask(Request $request, User $trainer): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'period' => ['required', 'string', 'in:daily,weekly,monthly,yearly'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'target_teaching_hours' => ['nullable', 'numeric', 'min:0'],
            'target_participants_count' => ['nullable', 'integer', 'min:0'],
            'target_rating' => ['nullable', 'numeric', 'min:1', 'max:5'],
        ]);

        $kpi = TrainerTaskAndKpi::create([
            'trainer_id' => $trainer->id,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'period' => $validated['period'],
            'start_date' => $validated['start_date'] ?? now()->toDateString(),
            'due_date' => $validated['due_date'],
            'target_teaching_hours' => (float) ($validated['target_teaching_hours'] ?? 0),
            'target_participants_count' => (int) ($validated['target_participants_count'] ?? 0),
            'target_rating' => (float) ($validated['target_rating'] ?? 4.5),
            'status' => 'in_progress',
            'created_by' => $request->user()?->id,
        ]);

        app(TrainerKpiActualsService::class)->syncTrainer($trainer->id);

        return back()->with('success', 'Target KPI / Penugasan Trainer berhasil ditetapkan.');
    }

    /**
     * TAB 4: Update status KPI
     */
    public function updateKpiTask(Request $request, User $trainer, TrainerTaskAndKpi $kpi): RedirectResponse
    {
        if ($kpi->trainer_id !== $trainer->id) {
            abort(403);
        }

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:pending,in_progress,completed,cancelled'],
        ]);

        $kpi->update($validated);

        return back()->with('success', 'Status target KPI berhasil diperbarui.');
    }

    /**
     * TAB 4: Hapus Target KPI
     */
    public function destroyKpiTask(Request $request, User $trainer, TrainerTaskAndKpi $kpi): RedirectResponse
    {
        if ($kpi->trainer_id !== $trainer->id) {
            abort(403);
        }

        $kpi->delete();

        return back()->with('success', 'Target KPI berhasil dihapus.');
    }

    /**
     * TUGAS 3 - Poin 2: Simpan Penilaian & Evaluasi Trainer dari Peserta
     */
    public function storeEvaluation(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'trainer_id' => ['required', 'integer', 'exists:users,id'],
            'attendance_session_id' => ['required', 'integer', 'exists:attendance_sessions,id'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'material_mastery_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'delivery_method_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'punctuality_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $participantId = $request->user()->id;

        TrainerEvaluation::updateOrCreate(
            [
                'attendance_session_id' => $validated['attendance_session_id'],
                'participant_id' => $participantId,
            ],
            [
                'trainer_id' => $validated['trainer_id'],
                'course_id' => $validated['course_id'] ?? null,
                'rating' => $validated['rating'],
                'material_mastery_rating' => $validated['material_mastery_rating'] ?? $validated['rating'],
                'delivery_method_rating' => $validated['delivery_method_rating'] ?? $validated['rating'],
                'punctuality_rating' => $validated['punctuality_rating'] ?? $validated['rating'],
                'comment' => $validated['comment'] ?? null,
            ]
        );

        app(TrainerKpiActualsService::class)->syncTrainer($validated['trainer_id']);

        return back()->with('success', 'Terima kasih! Evaluasi Anda terhadap Trainer berhasil disimpan.');
    }

    /**
     * Portal & KPI Saya (Khusus Role Trainer melihat dirinya sendiri)
     */
    public function myProfile(Request $request): Response
    {
        $user = $request->user();
        return $this->show($request, $user);
    }

}
