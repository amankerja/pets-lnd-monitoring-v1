<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainerTaskAndKpi extends Model
{
    use HasFactory;

    protected $table = 'trainer_tasks_and_kpis';

    protected $fillable = [
        'trainer_id',
        'title',
        'task_type',
        'priority',
        'description',
        'trainer_notes',
        'period',
        'start_date',
        'due_date',
        'target_teaching_hours',
        'actual_teaching_hours',
        'target_participants_count',
        'actual_participants_count',
        'target_rating',
        'actual_rating',
        'status',
        'completed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'target_teaching_hours' => 'float',
            'actual_teaching_hours' => 'float',
            'target_participants_count' => 'integer',
            'actual_participants_count' => 'integer',
            'target_rating' => 'float',
            'actual_rating' => 'float',
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

    /**
     * Hitung rata-rata progress persentase pencapaian KPI
     */
    public function getProgressPercentageAttribute(): int
    {
        $metrics = [];

        if ($this->target_teaching_hours > 0) {
            $metrics[] = min(100, round(($this->actual_teaching_hours / $this->target_teaching_hours) * 100));
        }

        if ($this->target_participants_count > 0) {
            $metrics[] = min(100, round(($this->actual_participants_count / $this->target_participants_count) * 100));
        }

        if ($this->target_rating > 0) {
            $metrics[] = min(100, round(($this->actual_rating / $this->target_rating) * 100));
        }

        if (empty($metrics)) {
            return $this->status === 'completed' ? 100 : 0;
        }

        return (int) round(array_sum($metrics) / count($metrics));
    }
}

