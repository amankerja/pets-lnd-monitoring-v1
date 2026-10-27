<?php

namespace App\Models;

use App\Traits\HasTrainingFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingRequest extends Model
{
    use HasFactory, HasTrainingFilters;

    protected $fillable = [
        'organization_id',
        'requested_by',
        'skill_id',
        'course_id',
        'request_type',
        'target_participants_count',
        'participant_ids',
        'reason_notes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'target_participants_count' => 'integer',
            'participant_ids' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Multi-Tenant Data Isolation Scope untuk pengajuan TNA & Refreshment.
     */
    public function scopeAccessibleBy(Builder $query, User $actor): Builder
    {
        if (in_array($actor->role, ['lnd', 'admin'], true)) {
            return $query;
        }

        if (in_array($actor->role, ['dept_admin', 'contractor_admin'], true)) {
            return $query->where('organization_id', $actor->organization_id);
        }

        return $query->where('requested_by', $actor->id);
    }
}

