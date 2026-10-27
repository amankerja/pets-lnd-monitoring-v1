<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Skill;
use App\Models\User;
use App\Traits\HasTrainingFilters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    use HasTrainingFilters;

    /**
     * 1. Streamed CSV Export untuk Skill Matrix & Gap Analysis (Zero-Library, Zero Memory Spike).
     * Menyisipkan UTF-8 BOM (\xEF\xBB\xBF) di baris pertama agar Microsoft Excel Windows
     * langsung mengenali encoding UTF-8 dan pemisah kolom dengan benar.
     */
    public function exportSkillMatrix(Request $request): StreamedResponse
    {
        $actor = $request->user();
        $filters = $this->extractTrainingFilters($request);
        $legacyDepartment = $request->string('department')->trim()->toString();
        $courseId = $filters['course_id'] ?? null;

        $skills = Skill::query()
            ->select(['id', 'code', 'name', 'category'])
            ->orderBy('category')
            ->orderBy('code')
            ->get();

        $fileName = 'LND_Skill_Matrix_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($actor, $skills, $filters, $legacyDepartment, $courseId) {
            $handle = fopen('php://output', 'w');

            // WAJIB: Sisipkan UTF-8 BOM agar Excel Windows mengenali karakter UTF-8 & kolom secara otomatis
            fwrite($handle, "\xEF\xBB\xBF");

            // Bangun Header Kolom Dinamis (M/NM/NR, Target, Current, Gap, Masa Berlaku)
            $headers = ['NIK', 'Nama Peserta', 'Departemen / Perusahaan'];
            foreach ($skills as $skill) {
                $headers[] = "[{$skill->code}] Status";
                $headers[] = "[{$skill->code}] Target";
                $headers[] = "[{$skill->code}] Current";
                $headers[] = "[{$skill->code}] Gap";
                $headers[] = "[{$skill->code}] Masa Berlaku";
            }
            $headers[] = 'Rata-rata Gap';
            $headers[] = 'Status Kelulusan';

            fputcsv($handle, $headers);

            // Query dengan Eager Loading, Multi-Tenant Scope accessibleBy, Global Multi-Attribute Filter & Chunking 200 baris per batch
            $participantsQuery = User::query()
                ->role('peserta')
                ->accessibleBy($actor)
                ->select(['id', 'nik', 'name', 'department', 'organization_id', 'job_position_id'])
                ->with([
                    'organization:id,code,name,type',
                    'jobPosition:id,code,level_name,position_title',
                    'jobPosition.skillStandards',
                    'userSkills:id,user_id,skill_id,target_level,current_level,valid_until',
                    'enrollments:id,user_id,course_id,status,progress_percentage',
                ])
                ->when($legacyDepartment !== '', fn ($q) => $q->where('department', $legacyDepartment));

            $this->applyUserFilters($participantsQuery, $filters, $actor);

            $today = now()->startOfDay();

            $participantsQuery
                ->orderBy('id')
                ->chunk(200, function ($participants) use ($handle, $skills, $courseId, $today) {
                    $skillCount = max(1, $skills->count());

                    foreach ($participants as $user) {
                        $skillMap = $user->userSkills->keyBy('skill_id');
                        $posStandards = $user->jobPosition?->skillStandards?->keyBy('skill_id') ?? collect();
                        $hasPosition = !empty($user->job_position_id);

                        $row = [
                            $user->nik,
                            $user->name,
                            $user->organization?->name ?? ($user->department ?? '-'),
                        ];

                        $deficitCount = 0;
                        $totalGap = 0;

                        foreach ($skills as $skill) {
                            $std = $posStandards->get($skill->id);
                            $us = $skillMap->get($skill->id);

                            $validUntil = $us?->valid_until ? \Illuminate\Support\Carbon::parse($us->valid_until) : null;
                            $isExpired = $validUntil && $validUntil->lt($today);

                            // Evaluasi Standar Jabatan (M, NM, NR / N/A)
                            if (!$hasPosition || !$std) {
                                $stdType = 'NR';
                                $target = $us ? (int) $us->target_level : 0;
                                $current = $us ? (int) $us->current_level : 0;
                                $gap = 0; // Not Required tidak menghasilkan defisit
                            } else {
                                $stdType = $std->is_mandatory ? 'M' : 'NM';
                                $target = (int) $std->required_target_level;
                                $current = $us ? (int) $us->current_level : 0;
                                $gap = $current - $target;
                                $totalGap += $gap;

                                if ($gap < 0 || $isExpired) {
                                    $deficitCount++;
                                }
                            }

                            $validityLabel = '-';
                            if ($validUntil) {
                                $validityLabel = $isExpired
                                    ? 'EXPIRED (' . $validUntil->format('d/m/Y') . ')'
                                    : $validUntil->format('d/m/Y');
                            }

                            $row[] = $stdType;
                            $row[] = $stdType === 'NR' ? '-' : (string) $target;
                            $row[] = (string) $current;
                            $row[] = $stdType === 'NR' ? '-' : ($gap > 0 ? "+{$gap}" : (string) $gap);
                            $row[] = $validityLabel;
                        }

                        $avgGap = round($totalGap / $skillCount, 2);
                        $row[] = (string) $avgGap;

                        // Evaluasi Status Kelulusan (Kompetensi + Progres Modul)
                        $approvedEnrollments = $user->enrollments->where('status', 'approved');
                        if ($courseId !== null) {
                            $approvedEnrollments = $approvedEnrollments->where('course_id', $courseId);
                        }

                        $avgProgress = $approvedEnrollments->count() > 0
                            ? round($approvedEnrollments->avg('progress_percentage'), 0)
                            : 0;

                        if ($deficitCount === 0 && $avgProgress >= 100) {
                            $graduationStatus = 'LULUS & KOMPETEN';
                        } elseif ($deficitCount === 0) {
                            $graduationStatus = "KOMPETEN (MODUL {$avgProgress}%)";
                        } else {
                            $graduationStatus = "PERLU TRAINING ULANG (GAP MINUS) - {$deficitCount} SKILL DEFISIT";
                        }

                        $row[] = $graduationStatus;

                        fputcsv($handle, $row);
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

    /**
     * 2. Streamed CSV Export untuk Rekapitulasi Kehadiran per Sesi di dalam suatu Course.
     */
    public function exportAttendance(Request $request, Course $course): StreamedResponse
    {
        $actor = $request->user();
        $filters = $this->extractTrainingFilters($request);

        $sessions = AttendanceSession::query()
            ->where('course_id', $course->id)
            ->orderBy('session_date', 'asc')
            ->orderBy('id', 'asc')
            ->get(['id', 'title', 'session_date']);

        $fileName = 'LND_Rekap_Absensi_' . $course->code . '_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($course, $sessions, $actor, $filters) {
            $handle = fopen('php://output', 'w');

            // WAJIB: UTF-8 BOM untuk kompatibilitas langsung dengan Microsoft Excel Windows
            fwrite($handle, "\xEF\xBB\xBF");

            // Bangun Header Kolom Dinamis per Sesi Pertemuan
            $headers = [
                'NIK',
                'Nama Peserta',
                'Departemen / Perusahaan',
                'Status Pendaftaran',
                'Progres Modul (%)',
            ];

            foreach ($sessions as $idx => $session) {
                $dateStr = $session->session_date?->format('d/m/Y') ?? '-';
                $headers[] = 'Sesi ' . ($idx + 1) . " ({$dateStr}) - {$session->title}";
            }

            $headers[] = 'Total Hadir';
            $headers[] = 'Total Sesi';
            $headers[] = 'Persentase Kehadiran (%)';

            fputcsv($handle, $headers);

            $sessionIds = $sessions->pluck('id')->all();

            // Stream data peserta per 200 baris dengan dukungan Global Multi-Attribute Filter
            $enrollmentsQuery = Enrollment::query()
                ->where('course_id', $course->id)
                ->with([
                    'user:id,nik,name,department,organization_id',
                    'user.organization:id,code,name,type',
                    'user.attendances' => fn ($q) => $q->whereIn('attendance_session_id', $sessionIds)
                        ->select(['id', 'attendance_session_id', 'user_id', 'status', 'scanned_at']),
                ]);

            $filtersWithoutCourse = $filters;
            unset($filtersWithoutCourse['course_id'], $filtersWithoutCourse['course_code']);
            $this->applyEnrollmentFilters($enrollmentsQuery, $filtersWithoutCourse, $actor, 'enrollments.created_at');

            $enrollmentsQuery
                ->orderBy('id')
                ->chunk(200, function ($enrollments) use ($handle, $sessions) {
                    $totalSessions = $sessions->count();

                    foreach ($enrollments as $enrollment) {
                        $user = $enrollment->user;
                        if (!$user) {
                            continue;
                        }

                        $attMap = $user->attendances->keyBy('attendance_session_id');

                        $row = [
                            $user->nik,
                            $user->name,
                            $user->organization?->name ?? ($user->department ?? '-'),
                            strtoupper($enrollment->status),
                            (int) $enrollment->progress_percentage . '%',
                        ];

                        $presentCount = 0;

                        foreach ($sessions as $session) {
                            $att = $attMap->get($session->id);
                            if (!$att) {
                                $row[] = 'ALPHA / BELUM ABSEN';
                            } else {
                                if ($att->status === 'present') {
                                    $presentCount++;
                                    $timeStr = $att->scanned_at ? ' (' . $att->scanned_at->format('H:i') . ')' : '';
                                    $row[] = 'HADIR' . $timeStr;
                                } elseif ($att->status === 'permit') {
                                    $row[] = 'IZIN';
                                } else {
                                    $row[] = 'ALPHA';
                                                                }
                            }
                        }

                        $attendancePct = $totalSessions > 0
                            ? round(($presentCount / $totalSessions) * 100, 1)
                            : 0;

                        $row[] = $presentCount;
                        $row[] = $totalSessions;
                        $row[] = "{$attendancePct}%";

                        fputcsv($handle, $row);
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

    /**
     * 3. Halaman Cetak Rapor Kompetensi & Sertifikat Peserta (Print-Ready A4 Standalone View).
     * Dilengkapi proteksi anti-IDOR agar Admin Departemen / Admin Kontraktor / Peserta
     * tidak dapat mengintip sertifikat milik organisasi atau individu lain.
     */
    public function printParticipantReport(Request $request, User $user): Response
    {
        $actor = $request->user();

        abort_if(
            in_array($actor->role, ['dept_admin', 'contractor_admin'], true) &&
            $user->organization_id !== $actor->organization_id,
            403,
            'Akses ditolak: Pekerja ini bukan dari departemen/perusahaan Anda.'
        );

        abort_if(
            $actor->role === 'peserta' && $user->id !== $actor->id,
            403,
            'Akses ditolak: Anda hanya dapat mengunduh sertifikat milik sendiri.'
        );

        // 1. Ambil seluruh skill beserta evaluasi milik peserta
        $allSkills = Skill::query()
            ->orderBy('category')
            ->orderBy('code')
            ->get();

        $userSkillsMap = $user->userSkills()
            ->with('updater:id,name')
            ->get()
            ->keyBy('skill_id');

        $skills = $allSkills->map(function (Skill $skill) use ($userSkillsMap) {
            $us = $userSkillsMap->get($skill->id);
            $current = $us ? (int) $us->current_level : 1;
            $target = $us ? (int) $us->target_level : 3;

            return [
                'skill_id' => $skill->id,
                'code' => $skill->code,
                'name' => $skill->name,
                'category' => $skill->category,
                'current_level' => $current,
                'target_level' => $target,
                'gap' => $current - $target,
                'evaluator_name' => $us?->updater?->name ?? 'Tim Assessor L&D',
                'updated_at' => $us?->updated_at?->format('d M Y') ?? now()->format('d M Y'),
            ];
        });

        // 2. Ambil riwayat kelas pelatihan & modul yang diikuti peserta
        $enrollments = Enrollment::query()
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->with([
                'course:id,code,title,trainer_id,start_date,end_date',
                'course.trainer:id,name',
            ])
            ->withCount([
                'moduleProgress as completed_modules_count' => fn ($q) => $q->where('is_completed', true),
            ])
            ->get()
            ->map(fn (Enrollment $e) => [
                'id' => $e->id,
                'course_code' => $e->course?->code,
                'course_title' => $e->course?->title,
                'trainer_name' => $e->course?->trainer?->name ?? 'Trainer L&D',
                'period' => ($e->course?->start_date?->format('d M Y') ?? '-') . ' - ' . ($e->course?->end_date?->format('d M Y') ?? '-'),
                'progress_percentage' => (int) $e->progress_percentage,
                'completed_modules_count' => (int) $e->completed_modules_count,
            ]);

        // 3. Hitung persentase kehadiran peserta di seluruh sesi kelas yang diikuti
        $enrolledCourseIds = Enrollment::query()
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->pluck('course_id');

        $totalSessionsCount = AttendanceSession::query()
            ->whereIn('course_id', $enrolledCourseIds)
            ->count();

        $presentCount = DB::table('attendances')
            ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendances.attendance_session_id')
            ->whereIn('attendance_sessions.course_id', $enrolledCourseIds)
            ->where('attendances.user_id', $user->id)
            ->where('attendances.status', 'present')
            ->count();

        $attendancePercentage = $totalSessionsCount > 0
            ? round(($presentCount / $totalSessionsCount) * 100, 1)
            : 100.0;

        $competentCount = $skills->filter(fn ($s) => $s['current_level'] >= $s['target_level'])->count();
        $avgCurrent = round($skills->avg('current_level') ?? 0, 2);
        $avgTarget = round($skills->avg('target_level') ?? 0, 2);
        $avgModuleProgress = round($enrollments->avg('progress_percentage') ?? 0, 1);

        // 4. Nomor Sertifikat & Signature Verifikasi Keaslian Dokumen (Untuk QR Code di Footer A4)
        $certNumber = sprintf('CERT-PANI-%s-%04d', now()->format('Y'), $user->id);
        $verificationHash = strtoupper(substr(hash_hmac('sha256', "{$user->nik}:{$certNumber}", config('app.key', 'LND-PANI-SECRET')), 0, 12));
        $verificationUrl = url("/verify-certificate/{$certNumber}");

        $primaryTrainer = $enrollments->first()['trainer_name'] ?? 'Ir. Hendra Wijaya, M.T.';

        return Inertia::render('Admin/ParticipantReportPrint', [
            'participant' => [
                'id' => $user->id,
                'name' => $user->name,
                'nik' => $user->nik,
                'email' => $user->email,
                'department' => $user->department ?? 'Mining Operations',
            ],
            'skills' => $skills,
            'enrollments' => $enrollments,
            'summary' => [
                'total_skills' => $skills->count(),
                'competent_count' => $competentCount,
                'gap_count' => $skills->count() - $competentCount,
                'avg_current' => $avgCurrent,
                'avg_target' => $avgTarget,
                'attendance_percentage' => $attendancePercentage,
                'present_sessions' => $presentCount,
                'total_sessions' => $totalSessionsCount,
                'avg_module_progress' => $avgModuleProgress,
                'is_graduated' => $competentCount === $skills->count() && $attendancePercentage >= 75,
            ],
            'documentMeta' => [
                'certificate_number' => $certNumber,
                'issued_date' => now()->translatedFormat('d F Y'),
                'verification_hash' => $verificationHash,
                'verification_payload' => $verificationUrl,
                'verification_url' => $verificationUrl,
                'trainer_name' => $primaryTrainer,
                'manager_name' => 'Budi Santoso, S.T., M.M.',
            ],
        ]);
    }
}

