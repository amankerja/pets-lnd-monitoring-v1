<?php

namespace App\Models;

use App\Traits\HasTrainingFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasTrainingFilters;

    protected $fillable = [
        'name',
        'nik',
        'email',
        'password',
        'role',
        'is_trainer',
        'trainer_type',
        'roster_pattern',
        'organization_id',
        'job_position_id',
        'department',
        'site',
        'job_level',
        'section',
        'entity',
        'nationality',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_trainer' => 'boolean',
        ];
    }

    /**
     * TUGAS 1 - Poin 3: Auto-Sync `target_level` di tabel `user_skills` setiap kali
     * pekerja (`peserta`) di-assign atau diubah `job_position_id`-nya.
     */
    protected static function booted(): void
    {
        static::saved(function (User $user) {
            if ($user->job_position_id !== null && ($user->wasRecentlyCreated || $user->wasChanged('job_position_id'))) {
                $actorId = auth()->check() ? auth()->id() : null;
                $user->syncPositionSkillStandards($actorId);
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function jobPosition(): BelongsTo
    {
        return $this->belongsTo(JobPosition::class, 'job_position_id');
    }

    public function taughtCourses(): HasMany
    {
        return $this->hasMany(Course::class, 'trainer_id');
    }

    public function attendanceSessionsTaught(): HasMany
    {
        return $this->hasMany(AttendanceSession::class, 'trainer_id');
    }

    public function trainerCompetencies(): HasMany
    {
        return $this->hasMany(TrainerCompetency::class, 'trainer_id');
    }

    public function authorizedSkills(): HasMany
    {
        return $this->hasMany(TrainerAuthorizedSkill::class, 'trainer_id');
    }

    public function rosters(): HasMany
    {
        return $this->hasMany(TrainerRoster::class, 'trainer_id');
    }

    public function teachingLogs(): HasMany
    {
        return $this->hasMany(TrainerTeachingLog::class, 'trainer_id');
    }

    public function evaluationsReceived(): HasMany
    {
        return $this->hasMany(TrainerEvaluation::class, 'trainer_id');
    }

    public function evaluationsGiven(): HasMany
    {
        return $this->hasMany(TrainerEvaluation::class, 'participant_id');
    }

    public function tasksAndKpis(): HasMany
    {
        return $this->hasMany(TrainerTaskAndKpi::class, 'trainer_id');
    }

    public function trainingRequests(): HasMany
    {
        return $this->hasMany(TrainingRequest::class, 'requested_by');
    }

    public function selfReportedTrainings(): HasMany
    {
        return $this->hasMany(SelfReportedTraining::class);
    }

    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function enrolledCourses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'enrollments')
            ->withPivot(['id', 'status', 'progress_percentage', 'registered_by', 'certificate_number', 'certificate_issued_at', 'certificate_expires_at'])
            ->withTimestamps();
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function userSkills(): HasMany
    {
        return $this->hasMany(UserSkill::class);
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'user_skills')
            ->withPivot(['id', 'target_level', 'current_level', 'valid_until', 'last_certificate_number', 'updated_by'])
            ->withTimestamps();
    }

    /*
    |--------------------------------------------------------------------------
    | Level x Position Competency Standard Sync Helper
    |--------------------------------------------------------------------------
    */

    /**
     * Menyinkronkan `target_level` pada tabel `user_skills` mengikuti `position_skill_standards`
     * dari `job_position_id` milik pekerja tanpa menghapus `current_level` aktual yang sudah ada.
     */
    public function syncPositionSkillStandards(?int $actorId = null): int
    {
        if (!$this->job_position_id) {
            return 0;
        }

        $standards = PositionSkillStandard::query()
            ->where('job_position_id', $this->job_position_id)
            ->whereIn('requirement_type', ['M', 'NM'])
            ->get(['skill_id', 'required_target_level']);

        if ($standards->isEmpty()) {
            return 0;
        }

        $syncedSkills = 0;

        DB::transaction(function () use ($standards, $actorId, &$syncedSkills) {
            foreach ($standards as $standard) {
                $existing = UserSkill::query()
                    ->where('user_id', $this->id)
                    ->where('skill_id', $standard->skill_id)
                    ->first();

                if ($existing) {
                    $existing->update([
                        'target_level' => (int) $standard->required_target_level,
                        'updated_by' => $actorId ?? $existing->updated_by,
                    ]);
                } else {
                    UserSkill::create([
                        'user_id' => $this->id,
                        'skill_id' => (int) $standard->skill_id,
                        'target_level' => (int) $standard->required_target_level,
                        'current_level' => 1,
                        'updated_by' => $actorId,
                    ]);
                }
                $syncedSkills++;
            }
        });

        return $syncedSkills;
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes & Multi-Tenant Data Isolation (Anti N+1 & Anti Multi-Tenancy Leak)
    |--------------------------------------------------------------------------
    */

    public function scopeAccessibleBy(Builder $query, User $actor): Builder
    {
        if (in_array($actor->role, ['lnd', 'admin'], true)) {
            return $query;
        }

        if (in_array($actor->role, ['dept_admin', 'contractor_admin'], true)) {
            if ($actor->organization_id !== null) {
                return $query->where('organization_id', $actor->organization_id);
            }

            return $query->where('department', $actor->department);
        }

        if ($actor->role === 'trainer') {
            return $query->whereHas('enrollments.course', function (Builder $cq) use ($actor) {
                $cq->where('trainer_id', $actor->id)
                    ->orWhereHas('attendanceSessions', fn (Builder $sq) => $sq->where('trainer_id', $actor->id));
            });
        }

        return $query->where('id', $actor->id);
    }

    public function scopeRole(Builder $query, string|array $roles): Builder
    {
        return is_array($roles)
            ? $query->whereIn('role', $roles)
            : $query->where('role', $roles);
    }

    public function scopeDepartment(Builder $query, ?string $department): Builder
    {
        return $department ? $query->where('department', $department) : $query;
    }

    public function scopeWithSkillMatrix(Builder $query): Builder
    {
        return $query->with([
            'userSkills' => fn ($q) => $q->select('id', 'user_id', 'skill_id', 'target_level', 'current_level', 'valid_until', 'last_certificate_number', 'updated_by'),
            'userSkills.skill:id,code,name,category',
        ]);
    }

    public function scopeWithTrainingSummary(Builder $query): Builder
    {
        return $query->withCount([
            'enrollments as active_enrollments_count' => fn (Builder $q) => $q->where('status', 'approved'),
            'attendances as present_attendances_count' => fn (Builder $q) => $q->where('status', 'present'),
        ]);
    }

    public function hasRole(string|array $roles): bool
    {
        return is_array($roles)
            ? in_array($this->role, $roles, true)
            : $this->role === $roles;
    }

    public function scopeTrainers(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('is_trainer', true)
              ->orWhere('role', 'trainer');
        });
    }

    public function getTodayRosterAttribute(): ?TrainerRoster
    {
        if ($this->relationLoaded('rosters')) {
            $todayStr = now()->toDateString();
            return $this->rosters->first(fn ($r) => (
                $r->roster_date instanceof \Carbon\CarbonInterface
                    ? $r->roster_date->toDateString()
                    : (string) $r->roster_date
            ) === $todayStr);
        }

        return $this->rosters()->whereDate('roster_date', now()->toDateString())->first();
    }
}
