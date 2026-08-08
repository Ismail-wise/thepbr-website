<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerDynamicsAssessment extends Model
{
    protected $fillable = [
        'user_id',
        'assessment_version',
        'status',
        'answers',
        'dimension_scores',
        'profile_scores',
        'primary_profile',
        'primary_score',
        'secondary_profile',
        'secondary_score',
        'is_blended',
        'result_confidence',
        'consistency_data',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'dimension_scores' => 'array',
            'profile_scores' => 'array',
            'primary_score' => 'decimal:2',
            'secondary_score' => 'decimal:2',
            'is_blended' => 'boolean',
            'consistency_data' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
