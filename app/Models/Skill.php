<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Skill extends Model
{
    use HasFactory;

    public const TYPE_EXTERNAL_MANDATORY = 'EXTERNAL_MANDATORY';
    public const TYPE_INTERNAL_MANDATORY = 'INTERNAL_MANDATORY';
    public const TYPE_NON_MANDATORY = 'NON_MANDATORY';

    public const COMPETENCY_TYPES = [
        self::TYPE_EXTERNAL_MANDATORY,
        self::TYPE_INTERNAL_MANDATORY,
        self::TYPE_NON_MANDATORY,
    ];

    public const CATEGORIES = [
        'Safety',
        'Operation',
        'Plant',
        'Pengawas',
        'Engineering',
        'Leadership',
        'General',
    ];

    protected $fillable = [
        'code',
        'name',
        'category',
        'competency_type',
        'description',
    ];

    protected static function booted(): void
    {
        static::saving(function (Skill $skill) {
            if (!empty($skill->code)) {
                $skill->code = strtoupper(trim($skill->code));
            }
            if (empty($skill->competency_type)) {
                $skill->competency_type = self::TYPE_INTERNAL_MANDATORY;
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relations (Terhubung ke Courses, UserSkills, & PositionSkillStandards)
    |--------------------------------------------------------------------------
    */

    /**
     * Daftar Program Pelatihan (Courses) yang meng-upgrade kompetensi ini.
     */
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'skill_id');
    }

    public function trainerAuthorizedSkills(): HasMany
    {
        return $this->hasMany(TrainerAuthorizedSkill::class, 'skill_id');
    }

    /**
     * Daftar pencapaian kompetensi pekerja pada Skill Matrix (`user_skills`).
     */
    public function userSkills(): HasMany
    {
        return $this->hasMany(UserSkill::class, 'skill_id');
    }

    /**
     * Daftar standar target kompetensi berdasarkan Level x Position (`position_skill_standards`).
     */
    public function positionStandards(): HasMany
    {
        return $this->hasMany(PositionSkillStandard::class, 'skill_id');
    }

    /**
     * Relasi Many-to-Many ke `job_positions` melalui `position_skill_standards`.
     */
    public function jobPositions(): BelongsToMany
    {
        return $this->belongsToMany(JobPosition::class, 'position_skill_standards', 'skill_id', 'job_position_id')
            ->withPivot(['id', 'required_target_level', 'is_mandatory'])
            ->withTimestamps();
    }

    /**
     * Relasi Many-to-Many ke `users` (pekerja) melalui `user_skills`.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_skills', 'skill_id', 'user_id')
            ->withPivot(['id', 'target_level', 'current_level', 'valid_until', 'last_certificate_number', 'updated_by'])
            ->withTimestamps();
    }

    /**
     * Lisensi mengajar Trainer (`trainer_competencies`) pada kompetensi ini.
     */
    public function trainerCompetencies(): HasMany
    {
        return $this->hasMany(TrainerCompetency::class, 'skill_id');
    }

    /**
     * Pengajuan TNA / Refreshment (`training_requests`) terkait kompetensi ini.
     */
    public function trainingRequests(): HasMany
    {
        return $this->hasMany(TrainingRequest::class, 'skill_id');
    }
}
