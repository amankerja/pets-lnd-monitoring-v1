<?php

namespace App\Models;

use App\Traits\HasTrainingFilters;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Enrollment extends Model
{
    use HasFactory, HasTrainingFilters;

    protected $fillable = [
        'user_id',
        'course_id',
        'attendance_session_id',
        'registered_by',
        'enrollment_type',
        'status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
        'completion_status',
        'progress_percentage',
        'completed_at',
        'certificate_number',
        'certificate_file_path',
        'certificate_issued_at',
        'certificate_expires_at',
        'is_superseded',
        'submitted_documents',
    ];

    protected function casts(): array
    {
        return [
            'progress_percentage' => 'integer',
            'completed_at' => 'date',
            'certificate_issued_at' => 'date',
            'certificate_expires_at' => 'date',
            'reviewed_at' => 'datetime',
            'is_superseded' => 'boolean',
            'submitted_documents' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function attendanceSession(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class);
    }

    public function registrar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function moduleProgress(): HasMany
    {
        return $this->hasMany(ModuleProgress::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes & Business Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Multi-Tenant Data Isolation Scope untuk Enrollment:
     * Membatasi pendaftaran & sertifikat agar `dept_admin` dan `contractor_admin`
     * hanya bisa melihat pekerja di dalam `organization_id` mereka sendiri.
     */
    public function scopeAccessibleBy(Builder $query, User $actor): Builder
    {
        if (in_array($actor->role, ['lnd', 'admin'], true)) {
            return $query;
        }

        if (in_array($actor->role, ['dept_admin', 'contractor_admin'], true)) {
            return $query->whereHas('user', function (Builder $uq) use ($actor) {
                if ($actor->organization_id !== null) {
                    $uq->where('organization_id', $actor->organization_id);
                } else {
                    $uq->where('department', $actor->department);
                }
            });
        }

        if ($actor->role === 'trainer') {
            return $query->whereHas('course', function (Builder $cq) use ($actor) {
                $cq->where('trainer_id', $actor->id)
                    ->orWhereHas('attendanceSessions', fn (Builder $sq) => $sq->where('trainer_id', $actor->id));
            });
        }

        return $query->where('user_id', $actor->id);
    }

    /**
     * Deteksi sertifikat pekerja yang akan kedaluwarsa dalam <= $days hari (atau sudah expired),
     * mengabaikan sertifikat lama yang sudah diperbarui (`is_superseded = true`).
     */
    public function scopeExpiringWithinDays(Builder $query, int $days = 60): Builder
    {
        return $query
            ->where('is_superseded', false)
            ->whereNotNull('certificate_expires_at')
            ->whereDate('certificate_expires_at', '<=', now()->addDays($days));
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function scopeWithFullDetails(Builder $query): Builder
    {
        return $query->with([
            'user:id,name,nik,department,organization_id',
            'course:id,code,title,trainer_id,start_date,end_date,status,is_mandatory,training_source,training_category,validity_months,skill_id,granted_skill_level',
            'course.trainer:id,name',
            'moduleProgress:id,enrollment_id,course_module_id,is_completed,completed_at',
        ]);
    }

    /**
     * Generator Nomor Sertifikat Unik Anti-Tabrakan (menggunakan Row Lock `lockForUpdate()` di dalam DB::transaction):
     * Format: `CERT/{KODE_COURSE}/{YYYY}/{MM}/{0001}`
     */
    public static function generateCorporateCertificateNumber(
        Course|string $courseOrCode,
        ?string $issuedDate = null,
        ?int $fallbackId = null
    ): string {
        $dt = $issuedDate ? Carbon::parse($issuedDate) : now();
        $year = $dt->format('Y');
        $month = $dt->format('m');
        $rawCode = $courseOrCode instanceof Course ? $courseOrCode->code : $courseOrCode;
        $courseCode = strtoupper(trim($rawCode ?: 'CRS-GEN'));
        $prefix = "CERT/{$courseCode}/{$year}/{$month}/";

        // Kunci baris terakhir dengan prefix yang sama untuk mencegah Race Condition saat Batch Issue
        $lastCert = self::query()
            ->where('certificate_number', 'like', "{$prefix}%")
            ->lockForUpdate()
            ->orderByDesc('certificate_number')
            ->value('certificate_number');

        if ($lastCert) {
            $lastSequence = (int) substr($lastCert, -4);
            $nextSequence = $lastSequence + 1;
        } else {
            $nextSequence = max(1, (int) ($fallbackId ? 1 : 1));
        }

        $candidate = sprintf('%s%04d', $prefix, $nextSequence);

        while (self::query()->where('certificate_number', $candidate)->lockForUpdate()->exists()) {
            $nextSequence++;
            $candidate = sprintf('%s%04d', $prefix, $nextSequence);
        }

        return $candidate;
    }

    /**
     * Nyatakan lulus peserta, tandai sertifikat lama (`is_superseded = true`),
     * terbitkan nomor sertifikat baru, hitung masa berlaku berdasarkan `validity_months`,
     * dan sinkronkan `current_level`, `valid_until`, serta `last_certificate_number` ke tabel `user_skills`.
     */
    public function graduateAndIssueCertificate(
        ?int $actorId = null,
        ?string $completedAtDate = null,
        ?string $customCertNumber = null,
        ?string $customExpiresAt = null,
        ?string $certificateFilePath = null
    ): self {
        $course = $this->relationLoaded('course') && $this->course
            ? $this->course
            : $this->course()->first();

        $issuedDateStr = $completedAtDate
            ? Carbon::parse($completedAtDate)->toDateString()
            : ($this->certificate_issued_at?->toDateString() ?? now()->toDateString());

        $certNumber = $customCertNumber
            ?: ($this->certificate_number ?: ($course
                ? self::generateCorporateCertificateNumber($course, $issuedDateStr, $this->id)
                : sprintf('CERT/PANI-LND/%s/%s/%04d', now()->format('Y'), now()->format('m'), $this->id)));

        if ($customExpiresAt !== null && $customExpiresAt !== '') {
            $expiresAtStr = Carbon::parse($customExpiresAt)->toDateString();
        } elseif ($course) {
            $expiresAtStr = $course->calculateExpiryDate($issuedDateStr);
        } else {
            $expiresAtStr = Carbon::parse($issuedDateStr)->addMonths(24)->toDateString();
        }

        // 1. Tandai sertifikat lama milik pekerja ini di course yang sama sebagai SUPERSEDED
        //    agar sertifikat lama yang sudah expired tidak lagi mengotori KPI Kepatuhan!
        self::query()
            ->where('user_id', $this->user_id)
            ->where('course_id', $this->course_id)
            ->where('id', '!=', $this->id)
            ->where('completion_status', 'passed')
            ->update(['is_superseded' => true]);

        // 2. Update record pendaftaran aktif saat ini menjadi PASSED & bersertifikat aktif
        $updates = [
            'status' => 'approved',
            'completion_status' => 'passed',
            'progress_percentage' => 100,
            'completed_at' => $issuedDateStr,
            'certificate_number' => $certNumber,
            'certificate_issued_at' => $issuedDateStr,
            'certificate_expires_at' => $expiresAtStr,
            'is_superseded' => false,
        ];

        if ($certificateFilePath !== null) {
            $updates['certificate_file_path'] = $certificateFilePath;
        }

        $this->update($updates);

        // 3. Sinkronisasi otomatis ke Skill Matrix (`user_skills`):
        //    Update `current_level` (pakai max agar tidak menurunkan level yang sudah lebih tinggi),
        //    salin `valid_until = $expiresAtStr`, dan catat `last_certificate_number = $certNumber`.
        if ($course && $course->skill_id) {
            $grantedLevel = max(1, min(5, (int) ($course->granted_skill_level ?: 3)));
            $existingUserSkill = UserSkill::query()
                ->where('user_id', $this->user_id)
                ->where('skill_id', $course->skill_id)
                ->first();

            $newCurrentLevel = $existingUserSkill
                ? max((int) $existingUserSkill->current_level, $grantedLevel)
                : $grantedLevel;

            if ($existingUserSkill) {
                $existingUserSkill->update([
                    'current_level' => $newCurrentLevel,
                    'valid_until' => $expiresAtStr,
                    'last_certificate_number' => $certNumber,
                    'updated_by' => $actorId ?? $existingUserSkill->updated_by,
                ]);
            } else {
                UserSkill::create([
                    'user_id' => $this->user_id,
                    'skill_id' => $course->skill_id,
                    'target_level' => max(3, $grantedLevel),
                    'current_level' => $newCurrentLevel,
                    'valid_until' => $expiresAtStr,
                    'last_certificate_number' => $certNumber,
                    'updated_by' => $actorId,
                ]);
            }
        }

        $this->syncCompletedRenewalRequests();

        return $this;
    }

    public function recalculateProgress(): void
    {
        $totalModules = CourseModule::where('course_id', $this->course_id)->count();

        if ($totalModules === 0) {
            $this->update(['progress_percentage' => 0]);
            return;
        }

        $completedModules = $this->moduleProgress()
            ->where('is_completed', true)
            ->count();

        $percentage = min(100, (int) round(($completedModules / $totalModules) * 100));

        if ($percentage === 100) {
            $this->graduateAndIssueCertificate(auth()->id());
            return;
        }

        $this->update([
            'progress_percentage' => $percentage,
            'completion_status' => $percentage > 0 ? 'ongoing' : 'registered',
        ]);
    }

    /**
     * Tandai pengajuan Training Pembaruan (training_requests) sebagai `completed`
     * apabila pekerja di dalamnya telah lulus dan mendapatkan sertifikat baru.
     */
    public function syncCompletedRenewalRequests(): void
    {
        $activeRequests = TrainingRequest::query()
            ->where('course_id', $this->course_id)
            ->whereIn('status', ['pending_lnd', 'approved', 'scheduled'])
            ->get();

        foreach ($activeRequests as $req) {
            $participantIds = array_map('intval', $req->participant_ids ?? []);
            if (in_array((int) $this->user_id, $participantIds, true)) {
                $req->update(['status' => 'completed']);
            }
        }
    }
}
