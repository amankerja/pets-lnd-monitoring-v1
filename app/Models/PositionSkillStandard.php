<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PositionSkillStandard extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_position_id',
        'skill_id',
        'required_target_level',
        'requirement_type',
        'is_mandatory',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $model) {
            if ($model->requirement_type) {
                $model->is_mandatory = ($model->requirement_type === 'M');
            } elseif ($model->is_mandatory !== null) {
                $model->requirement_type = $model->is_mandatory ? 'M' : 'NM';
            }
        });
    }

    protected function casts(): array
    {
        return [
            'required_target_level' => 'integer',
            'is_mandatory' => 'boolean',
        ];
    }

    public function jobPosition(): BelongsTo
    {
        return $this->belongsTo(JobPosition::class, 'job_position_id');
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class, 'skill_id');
    }
}

