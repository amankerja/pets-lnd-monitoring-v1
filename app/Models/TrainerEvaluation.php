<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainerEvaluation extends Model
{
    use HasFactory;

    protected $fillable = [
        'trainer_id',
        'attendance_session_id',
        'course_id',
        'participant_id',
        'rating',
        'material_mastery_rating',
        'delivery_method_rating',
        'punctuality_rating',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'material_mastery_rating' => 'integer',
            'delivery_method_rating' => 'integer',
            'punctuality_rating' => 'integer',
        ];
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'attendance_session_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'participant_id');
    }
}

