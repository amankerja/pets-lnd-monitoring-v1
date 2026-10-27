<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainerTeachingLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'trainer_id',
        'attendance_session_id',
        'course_id',
        'training_title',
        'log_date',
        'entry_source',
        'duration_hours',
        'scanned_participants_count',
        'location',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'log_date' => 'date',
            'duration_hours' => 'float',
            'scanned_participants_count' => 'integer',
        ];
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    public function attendanceSession(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'attendance_session_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

