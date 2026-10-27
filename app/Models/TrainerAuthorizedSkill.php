<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainerAuthorizedSkill extends Model
{
    use HasFactory;

    protected $fillable = [
        'trainer_id',
        'skill_id',
        'license_type',
        'license_number',
        'valid_until',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'valid_until' => 'date',
        ];
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if certification/license is still valid or expiring soon.
     */
    public function getStatusAttribute(): string
    {
        if (!$this->valid_until) {
            return 'no_expiry';
        }

        if ($this->valid_until->isPast()) {
            return 'expired';
        }

        if ($this->valid_until->diffInDays(now()) <= 60) {
            return 'expiring_soon';
        }

        return 'active';
    }
}

