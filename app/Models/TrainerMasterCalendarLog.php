<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainerMasterCalendarLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'calendar_date',
        'trainer_id',
        'activity_type',
        'course_id',
        'location',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'calendar_date' => 'date',
        ];
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

