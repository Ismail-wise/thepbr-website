<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerDynamicsReport extends Model
{
    protected $fillable = [
        'workspace_id',
        'report_version',
        'status',
        'participants',
        'alignment_summary',
        'shared_strengths',
        'complementary_areas',
        'important_differences',
        'shared_blind_spots',
        'role_suggestions',
        'decision_recommendations',
        'discussion_priorities',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'participants' => 'array',
            'alignment_summary' => 'array',
            'shared_strengths' => 'array',
            'complementary_areas' => 'array',
            'important_differences' => 'array',
            'shared_blind_spots' => 'array',
            'role_suggestions' => 'array',
            'decision_recommendations' => 'array',
            'discussion_priorities' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(PartnershipWorkspace::class, 'workspace_id');
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }
}
