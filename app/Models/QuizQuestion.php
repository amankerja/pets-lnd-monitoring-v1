<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CATATAN: Modul Quiz Interaktif dalam pengembangan (Pre-Test & Post-Test).
 */
class QuizQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_id',
        'question_text',
        'options',
        'correct_option',
        'points',
        'order_index',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'points' => 'integer',
            'order_index' => 'integer',
        ];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }
}

