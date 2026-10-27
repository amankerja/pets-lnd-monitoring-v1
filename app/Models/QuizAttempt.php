<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * CATATAN: Modul Quiz Interaktif dalam pengembangan (Pre-Test & Post-Test).
 */
class QuizAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_id',
        'user_id',
        'score',
        'is_passed',
        'started_at',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'is_passed' => 'boolean',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuizAttemptAnswer::class);
    }

    public function scopeAccessibleBy(Builder $query, User $actor): Builder
    {
        if (in_array($actor->role, ['lnd', 'admin'], true)) {
            return $query;
        }

        if (in_array($actor->role, ['dept_admin', 'contractor_admin'], true)) {
            return $query->whereHas('user', fn (Builder $uq) => $uq->where('organization_id', $actor->organization_id));
        }

        if ($actor->role === 'trainer') {
            return $query->whereHas('quiz.course', fn (Builder $cq) => $cq->where('trainer_id', $actor->id));
        }

        return $query->where('user_id', $actor->id);
    }
}

