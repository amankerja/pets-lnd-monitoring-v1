<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalTrainingLogistic extends Model
{
    protected $fillable = [
        'course_id',
        'external_type',
        'vendor_name',
        'pic_training',
        'training_mode',
        'training_room',
        'training_duration_days',
        'training_cost',
        'required_documents_schema',
        'accommodation_checklist',
    ];

    protected function casts(): array
    {
        return [
            'training_duration_days' => 'integer',
            'training_cost' => 'decimal:2',
            'required_documents_schema' => 'array',
            'accommodation_checklist' => 'array',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
