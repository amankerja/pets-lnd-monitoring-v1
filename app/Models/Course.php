<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'title',
        'description',
        'trainer_id',
        'start_date',
        'end_date',
        'status',
        'is_mandatory',
        'training_source',
        'training_category',
        'validity_years',
        'validity_months',
        'skill_id',
        'granted_skill_level',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_mandatory' => 'boolean',
            'validity_years' => 'integer',
            'validity_months' => 'integer',
            'skill_id' => 'integer',
            'granted_skill_level' => 'integer',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    /**
     * Setiap Course merujuk ke satu Kompetensi utama di Competency List.
     */
    public function competency(): BelongsTo
    {
        return $this->belongsTo(Skill::class, 'skill_id');
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class, 'skill_id');
    }

    public function modules(): HasMany
    {
        return $this->hasMany(CourseModule::class)->orderBy('order_index');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function externalTrainingLogistic(): HasOne
    {
        return $this->hasOne(ExternalTrainingLogistic::class);
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'enrollments')
            ->withPivot([
                'id',
                'status',
                'enrollment_type',
                'completion_status',
                'progress_percentage',
                'completed_at',
                'certificate_number',
                'certificate_file_path',
                'certificate_issued_at',
                'certificate_expires_at',
            ])
            ->withTimestamps();
    }

    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class)->orderByDesc('session_date');
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers & Scopes (Anti N+1)
    |--------------------------------------------------------------------------
    */

    /**
     * Hitung tanggal kadaluarsa sertifikat berdasarkan `validity_months`.
     * Jika `validity_months === null`, sertifikat berlaku selamanya (`null` / Permanent).
     */
    public function calculateExpiryDate(string|\DateTimeInterface|null $issuedAt = null): ?string
    {
        if ($this->validity_months === null) {
            return null;
        }

        $months = (int) $this->validity_months;
        if ($months <= 0) {
            return null;
        }

        $baseDate = $issuedAt ? Carbon::parse($issuedAt) : now();

        return $baseDate->copy()->addMonths($months)->toDateString();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeWithDashboardStats(Builder $query): Builder
    {
        return $query->with([
            'trainer:id,name,nik,department',
            'skill:id,code,name,category',
        ])
            ->withCount([
                'modules',
                'enrollments as total_enrolled_count',
                'enrollments as approved_enrolled_count' => fn (Builder $q) => $q->where('status', 'approved'),
                'attendanceSessions',
            ])
            ->withAvg(
                ['enrollments as avg_progress' => fn (Builder $q) => $q->where('status', 'approved')],
                'progress_percentage'
            );
    }
}
