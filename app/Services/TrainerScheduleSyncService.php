<?php

namespace App\Services;

use App\Models\AttendanceSession;
use App\Models\TrainerRoster;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class TrainerScheduleSyncService
{
    /**
     * Memeriksa ketersediaan Trainer pada tanggal dan rentang jam tertentu.
     * Mengembalikan status ketersediaan dan detail konflik jika ada.
     *
     * @return array{
     *     available: bool,
     *     conflict_type: 'none'|'roster_off'|'hour_overlap'|'different_hours'|'unselected',
     *     status: string,
     *     message: string,
     *     details: array
     * }
     */
    public function checkAvailability(
        ?int $trainerId,
        ?string $sessionDate,
        ?string $startTime = null,
        ?string $endTime = null,
        ?int $ignoreSessionId = null
    ): array {
        if (!$trainerId || !$sessionDate) {
            return [
                'available' => true,
                'conflict_type' => 'unselected',
                'status' => 'MENUNGGU PILIHAN',
                'message' => 'Pilih instruktur dan tanggal untuk melihat ketersediaan jadwal.',
                'details' => [],
            ];
        }

        try {
            $parsedDate = Carbon::parse($sessionDate)->toDateString();
        } catch (\Throwable) {
            return [
                'available' => false,
                'conflict_type' => 'unselected',
                'status' => 'TANGGAL TIDAK VALID',
                'message' => 'Format tanggal tidak dikenali.',
                'details' => [],
            ];
        }

        // 1. Cek Roster Harian Trainer (Off Roster / Cuti / Sakit)
        $roster = TrainerRoster::query()
            ->where('trainer_id', $trainerId)
            ->whereDate('roster_date', $parsedDate)
            ->first();

        if ($roster) {
            $isRosterOff = in_array($roster->status, ['off_roster', 'annual_leave', 'sick'], true)
                || $roster->activity_type === 'OFF';

            if ($isRosterOff) {
                $statusLabel = match ($roster->status) {
                    'off_roster' => 'OFF ROSTER',
                    'annual_leave' => 'CUTI TAHUNAN',
                    'sick' => 'SAKIT (SKD)',
                    default => strtoupper($roster->status ?? 'OFF'),
                };

                return [
                    'available' => false,
                    'conflict_type' => 'roster_off',
                    'status' => $statusLabel,
                    'message' => "Trainer berstatus {$statusLabel} pada tanggal tersebut. Penugasan sesi kelas tidak diizinkan.",
                    'details' => [
                        'roster_id' => $roster->id,
                        'roster_status' => $roster->status,
                        'activity_type' => $roster->activity_type,
                        'notes' => $roster->notes,
                    ],
                ];
            }
        }

        // 2. Cek Bentrok Jam Mengajar dengan Sesi Lain pada Tanggal yang Sama
        $existingSessions = AttendanceSession::query()
            ->with(['course:id,code,title'])
            ->whereDate('session_date', $parsedDate)
            ->where(function (Builder $query) use ($trainerId) {
                $query->where('trainer_id', $trainerId)
                    ->orWhereHas('course', fn (Builder $courseQuery) => $courseQuery->where('trainer_id', $trainerId));
            })
            ->when($ignoreSessionId, fn (Builder $query) => $query->where('id', '!=', $ignoreSessionId))
            ->get();

        if ($existingSessions->isEmpty()) {
            return [
                'available' => true,
                'conflict_type' => 'none',
                'status' => 'TERSEDIA PENUH',
                'message' => 'Trainer berstatus ON DUTY dan belum memiliki agenda mengajar pada tanggal ini.',
                'details' => [],
            ];
        }

        // Jika jam mulai & jam selesai disediakan, lakukan pengecekan overlap
        if (!empty($startTime) && !empty($endTime)) {
            $overlap = $existingSessions->first(function (AttendanceSession $s) use ($startTime, $endTime) {
                if (empty($s->start_time) || empty($s->end_time)) {
                    return false;
                }
                return ($s->start_time < $endTime) && ($s->end_time > $startTime);
            });

            if ($overlap) {
                $startFmt = substr((string) $overlap->start_time, 0, 5);
                $endFmt = substr((string) $overlap->end_time, 0, 5);
                $courseCode = $overlap->course?->code ? "[{$overlap->course->code}] " : '';

                return [
                    'available' => false,
                    'conflict_type' => 'hour_overlap',
                    'status' => 'BENTROK WAKTU',
                    'message' => "Bentrok jam dengan sesi lain: {$courseCode}{$overlap->title} ({$startFmt} - {$endFmt}).",
                    'details' => [
                        'conflicting_session_id' => $overlap->id,
                        'conflicting_title' => $overlap->title,
                        'conflicting_start' => $startFmt,
                        'conflicting_end' => $endFmt,
                    ],
                ];
            }

            // Ada sesi lain di tanggal sama tapi jamnya aman (tidak tumpang tindih)
            $otherList = $existingSessions->map(function ($s) {
                $start = substr((string) $s->start_time, 0, 5);
                $end = substr((string) $s->end_time, 0, 5);
                return "{$s->title} ({$start}-{$end})";
            })->join(', ');

            return [
                'available' => true,
                'conflict_type' => 'different_hours',
                'status' => 'TERSEDIA (ADA AGENDA LAIN)',
                'message' => "Trainer memiliki {$existingSessions->count()} agenda lain di jam berbeda: {$otherList}.",
                'details' => [
                    'session_count' => $existingSessions->count(),
                ],
            ];
        }

        return [
            'available' => true,
            'conflict_type' => 'different_hours',
            'status' => 'TERSEDIA BERSYARAT',
            'message' => "Trainer memiliki {$existingSessions->count()} sesi lain pada tanggal ini. Masukkan jam mulai & selesai untuk memverifikasi bentrok.",
            'details' => [
                'session_count' => $existingSessions->count(),
            ],
        ];
    }

    /**
     * Otomatis melakukan sinkronisasi sesi yang disetujui (approved) ke tabel TrainerRoster.
     * Membuat Trainer otomatis tercatat 'on_duty' dengan aktivitas 'TRAINING_CLASS' pada Master Roster.
     */
    public function syncSessionToRoster(AttendanceSession $session): void
    {
        $trainerId = $session->trainer_id ?? $session->course?->trainer_id;
        if (!$trainerId || !$session->session_date) {
            return;
        }

        $sessionDate = Carbon::parse($session->session_date)->toDateString();

        if ($session->plan_status === 'approved') {
            $timeSlot = (!empty($session->start_time) && !empty($session->end_time))
                ? ' (' . substr((string) $session->start_time, 0, 5) . ' - ' . substr((string) $session->end_time, 0, 5) . ')'
                : '';

            $notes = "Penugasan Kelas: {$session->title}{$timeSlot}";

            TrainerRoster::updateOrCreate(
                [
                    'trainer_id' => $trainerId,
                    'roster_date' => $sessionDate,
                ],
                [
                    'course_id' => $session->course_id,
                    'status' => 'on_duty',
                    'activity_type' => 'TRAINING_CLASS',
                    'shift' => 'day',
                    'location' => $session->location ?? 'Site PBJ Training Room',
                    'notes' => $notes,
                    'created_by' => auth()->id() ?? $session->created_by ?? null,
                ]
            );
        } else {
            // Jika plan_status bukan approved (misal: pending), periksa apakah masih ada sesi approved lain pada hari itu
            $hasOtherApproved = AttendanceSession::query()
                ->where('trainer_id', $trainerId)
                ->whereDate('session_date', $sessionDate)
                ->where('id', '!=', $session->id)
                ->where('plan_status', 'approved')
                ->exists();

            if (!$hasOtherApproved) {
                // Jika tidak ada sesi approved lain, perbarui catatan roster agar tidak terkunci ke kelas ini
                $roster = TrainerRoster::query()
                    ->where('trainer_id', $trainerId)
                    ->whereDate('roster_date', $sessionDate)
                    ->where('activity_type', 'TRAINING_CLASS')
                    ->first();

                if ($roster) {
                    $roster->update([
                        'activity_type' => 'STANDBY',
                        'course_id' => null,
                        'notes' => "Sesi kelas dialihkan ke status PENDING.",
                    ]);
                }
            }
        }
    }

    /**
     * Membersihkan sinkronisasi Roster jika jadwal sesi dihapus.
     */
    public function cleanupSessionRoster(AttendanceSession $session): void
    {
        $trainerId = $session->trainer_id ?? $session->course?->trainer_id;
        if (!$trainerId || !$session->session_date) {
            return;
        }

        $sessionDate = Carbon::parse($session->session_date)->toDateString();

        $hasOtherApproved = AttendanceSession::query()
            ->where('trainer_id', $trainerId)
            ->whereDate('session_date', $sessionDate)
            ->where('id', '!=', $session->id)
            ->where('plan_status', 'approved')
            ->exists();

        if (!$hasOtherApproved) {
            $roster = TrainerRoster::query()
                ->where('trainer_id', $trainerId)
                ->whereDate('roster_date', $sessionDate)
                ->where('course_id', $session->course_id)
                ->first();

            if ($roster) {
                $roster->update([
                    'activity_type' => 'STANDBY',
                    'course_id' => null,
                    'notes' => "Jadwal sesi {$session->title} telah dihapus dari sistem.",
                ]);
            }
        }
    }
}

