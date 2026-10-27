<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class JobPosition extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'level_name',
        'position_title',
        'organization_type',
        'entity',
        'department',
        'section',
        'nationality',
    ];

    public function skillStandards(): HasMany
    {
        return $this->hasMany(PositionSkillStandard::class, 'job_position_id');
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'position_skill_standards')
            ->withPivot(['id', 'required_target_level', 'requirement_type', 'is_mandatory'])
            ->withTimestamps();
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'job_position_id');
    }

    public function getFullLabelAttribute(): string
    {
        return "[{$this->code}] {$this->level_name} - {$this->position_title}";
    }

    /**
     * Sinkronkan standar `required_target_level` dari jabatan ini ke seluruh pekerja (`users`)
     * yang menduduki jabatan ini (`job_position_id = $this->id`).
     */
    public function syncStandardsToAssignedUsers(?int $actorId = null): int
    {
        $standards = $this->skillStandards()
            ->whereIn('requirement_type', ['M', 'NM'])
            ->get(['skill_id', 'required_target_level', 'is_mandatory']);
        if ($standards->isEmpty()) {
            return 0;
        }

        $workers = $this->users()->get(['id', 'job_position_id']);
        $syncedWorkersCount = 0;

        DB::transaction(function () use ($workers, $standards, $actorId, &$syncedWorkersCount) {
            foreach ($workers as $worker) {
                foreach ($standards as $std) {
                    $existing = UserSkill::query()
                        ->where('user_id', $worker->id)
                        ->where('skill_id', $std->skill_id)
                        ->first();

                    if ($existing) {
                        $existing->update([
                            'target_level' => (int) $std->required_target_level,
                            'updated_by' => $actorId ?? $existing->updated_by,
                        ]);
                    } else {
                        UserSkill::create([
                            'user_id' => $worker->id,
                            'skill_id' => (int) $std->skill_id,
                            'target_level' => (int) $std->required_target_level,
                            'current_level' => 1,
                            'updated_by' => $actorId,
                        ]);
                    }
                }
                $syncedWorkersCount++;
            }
        });

        return $syncedWorkersCount;
    }
}
