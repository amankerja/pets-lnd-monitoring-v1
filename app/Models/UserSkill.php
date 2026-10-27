<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSkill extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'skill_id',
        'target_level',
        'current_level',
        'valid_until',
        'last_certificate_number',
        'updated_by',
    ];

    protected $appends = [
        'gap',
    ];

    protected function casts(): array
    {
        return [
            'target_level' => 'integer',
            'current_level' => 'integer',
            'valid_until' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getGapAttribute(): int
    {
        return $this->current_level - $this->target_level;
    }

    public function scopeWithGapDeficit(Builder $query): Builder
    {
        return $query->whereColumn('current_level', '<', 'target_level');
    }

    public function scopeForMatrix(Builder $query): Builder
    {
        return $query->with([
            'user:id,name,nik,department',
            'skill:id,code,name,category',
            'updater:id,name',
        ]);
    }
}

