<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainerRoster extends Model
{
    use HasFactory;

    protected $fillable = [
        'trainer_id',
        'course_id',
        'roster_date',
        'status',
        'activity_type',
        'shift',
        'location',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'roster_date' => 'date',
        ];
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Label representatif untuk status roster.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'on_duty' => 'ON DUTY',
            'off_roster' => 'OFF ROSTER',
            'annual_leave' => 'CUTI TAHUNAN',
            'sick' => 'SAKIT (SKD)',
            'site_visit' => 'SITE VISIT',
            default => strtoupper((string) $this->status),
        };
    }

    /**
     * Label representatif untuk tipe aktivitas harian.
     */
    public function getActivityLabelAttribute(): string
    {
        return match ($this->activity_type) {
            'TRAINING_CLASS' => 'Mengajar Kelas',
            'FIELD_OBSERVATION' => 'Observasi Lapangan',
            'LND_ADMIN' => 'Tugas LnD (Admin)',
            'STANDBY' => 'Standby',
            'OFF' => 'Off / Libur',
            default => (string) ($this->activity_type ?? 'Mengajar Kelas'),
        };
    }
}
