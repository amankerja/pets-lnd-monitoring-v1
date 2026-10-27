<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class HistoricalMigrationController extends Controller
{
    /**
     * 3. Input Riwayat Training Masa Lalu (Historical Migration):
     * Mendukung 2 Mode Fleksibel:
     * - Mode A (`single_worker_multi_cert`): 1 Orang dipilih -> menginput banyak sertifikat sekaligus.
     * - Mode B (`single_course_multi_worker`): 1 Training dipilih -> dicentang massal banyak pekerja sekaligus.
     */
    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();
        $mode = $request->string('migration_mode', 'single_course_multi_worker')->toString();

        if ($mode === 'single_worker_multi_cert') {
            return $this->storeSingleWorkerMultiCertificates($request, $actor);
        }

        return $this->storeSingleCourseMultiWorkers($request, $actor);
    }

    /**
     * Mode A: 1 Pekerja menginput banyak sertifikat masa lalu sekaligus.
     */
    protected function storeSingleWorkerMultiCertificates(Request $request, User $actor): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'certificates' => ['required', 'array', 'min:1'],
            'certificates.*.course_id' => ['required', 'integer', 'exists:courses,id'],
            'certificates.*.certificate_number' => ['nullable', 'string', 'max:80'],
            'certificates.*.certificate_issued_at' => ['required', 'date'],
            'certificates.*.certificate_expires_at' => ['nullable', 'date'],
        ]);

        $worker = User::query()->accessibleBy($actor)->whereKey($validated['user_id'])->first();
        abort_if(!$worker, 403, 'Akses ditolak: Pekerja ini berada di luar organisasi Anda.');

        $coursesMap = Course::query()
            ->whereIn('id', collect($validated['certificates'])->pluck('course_id'))
            ->get(['id', 'code', 'validity_years'])
            ->keyBy('id');

        $savedCount = DB::transaction(function () use ($validated, $worker, $actor, $coursesMap) {
            $count = 0;
            foreach ($validated['certificates'] as $idx => $cert) {
                $course = $coursesMap->get((int) $cert['course_id']);
                $issuedAt = Carbon::parse($cert['certificate_issued_at']);
                $validityYears = (int) ($course?->validity_years ?: 2);
                $expiresAt = !empty($cert['certificate_expires_at'])
                    ? Carbon::parse($cert['certificate_expires_at'])
                    : (clone $issuedAt)->addYears($validityYears);

                $certNo = !empty($cert['certificate_number'])
                    ? strtoupper(trim($cert['certificate_number']))
                    : sprintf('HIST/%s/%s/%04d-%d', $course?->code ?? 'LND', $issuedAt->format('Y'), $worker->id, $idx + 1);

                $enrollment = Enrollment::updateOrCreate(
                    [
                        'user_id' => $worker->id,
                        'course_id' => (int) $cert['course_id'],
                    ],
                    [
                        'registered_by' => $actor->id,
                        'status' => 'approved',
                        'progress_percentage' => 100,
                        'certificate_number' => $certNo,
                        'certificate_issued_at' => $issuedAt->toDateString(),
                        'certificate_expires_at' => $expiresAt->toDateString(),
                    ]
                );

                $enrollment->graduateAndIssueCertificate(
                    $actor->id,
                    $issuedAt->toDateString(),
                    $certNo,
                    $expiresAt?->toDateString()
                );
                $count++;
            }

            return $count;
        });

        return back()->with('success', "Berhasil memigrasikan {$savedCount} sertifikat riwayat masa lalu untuk {$worker->name}.");
    }

    /**
     * Mode B: 1 Training dipilih lalu dicentang massal banyak pekerja sekaligus untuk masuk ke riwayat resmi.
     */
    protected function storeSingleCourseMultiWorkers(Request $request, User $actor): RedirectResponse
    {
        $validated = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'participant_ids' => ['required', 'array', 'min:1'],
            'participant_ids.*' => ['required', 'integer', 'exists:users,id'],
            'certificate_issued_at' => ['required', 'date'],
            'certificate_expires_at' => ['nullable', 'date'],
            'certificate_prefix' => ['nullable', 'string', 'max:40'],
        ]);

        $allowedWorkerIds = User::query()
            ->accessibleBy($actor)
            ->whereIn('id', $validated['participant_ids'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        abort_if(
            count($allowedWorkerIds) !== count(array_unique($validated['participant_ids'])),
            403,
            'Akses ditolak: Terdapat pekerja di luar organisasi Anda.'
        );

        $course = Course::findOrFail((int) $validated['course_id']);
        $issuedAt = Carbon::parse($validated['certificate_issued_at']);
        $validityYears = (int) ($course->validity_years ?: 2);
        $expiresAt = !empty($validated['certificate_expires_at'])
            ? Carbon::parse($validated['certificate_expires_at'])
            : (clone $issuedAt)->addYears($validityYears);

        $prefix = !empty($validated['certificate_prefix'])
            ? strtoupper(trim($validated['certificate_prefix']))
            : "HIST/{$course->code}/{$issuedAt->format('Y')}";

        DB::transaction(function () use ($allowedWorkerIds, $course, $actor, $issuedAt, $expiresAt, $prefix) {
            foreach ($allowedWorkerIds as $workerId) {
                $certNo = sprintf('%s/U%04d', $prefix, $workerId);

                $enrollment = Enrollment::updateOrCreate(
                    [
                        'user_id' => $workerId,
                        'course_id' => $course->id,
                    ],
                    [
                        'registered_by' => $actor->id,
                        'status' => 'approved',
                        'progress_percentage' => 100,
                        'certificate_number' => $certNo,
                        'certificate_issued_at' => $issuedAt->toDateString(),
                        'certificate_expires_at' => $expiresAt->toDateString(),
                    ]
                );

                $enrollment->graduateAndIssueCertificate(
                    $actor->id,
                    $issuedAt->toDateString(),
                    $certNo,
                    $expiresAt?->toDateString()
                );
            }
        });

        return back()->with('success', count($allowedWorkerIds) . " pekerja berhasil dimigrasikan ke riwayat resmi pelatihan [{$course->code}].");
    }
}

