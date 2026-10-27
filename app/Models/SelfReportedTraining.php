<?php

namespace App\Models;

use App\Traits\HasTrainingFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SelfReportedTraining extends Model
{
    use HasFactory, HasTrainingFilters;

    protected $fillable = [
        'user_id',
        'course_name',
        'organizer',
        'certificate_number',
        'issued_at',
        'expires_at',
        'document_path',
        'status',
        'reviewed_by',
        'review_notes',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'expires_at' => 'date',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /*
    |--------------------------------------------------------------------------
    | RBAC Multi-Tenant Isolation Scope (scopeAccessibleBy)
    |--------------------------------------------------------------------------
    */

    /**
     * Isolasi data otomatis berdasarkan Role & Organization:
     * - `lnd` / `admin`: Akses global ke seluruh pelaporan sertifikat mandiri
     * - `dept_admin` / `contractor_admin`: Hanya pelaporan dari pekerja di dalam `organization_id` miliknya
     * - `peserta`: Hanya pelaporan miliknya sendiri (`user_id = $actor->id`)
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

        return $query->where('user_id', $actor->id);
    }
}

