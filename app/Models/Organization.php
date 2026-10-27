<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'type',
        'site',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function trainingRequests(): HasMany
    {
        return $this->hasMany(TrainingRequest::class);
    }

    public function isContractor(): bool
    {
        return $this->type === 'contractor';
    }
}

