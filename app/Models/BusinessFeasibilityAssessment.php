<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessFeasibilityAssessment extends Model
{
    protected $fillable = [
        'workspace_id',
        'user_id',
        'project_name',
        'inputs',
        'result',
    ];

    protected function casts(): array
    {
        return [
            'inputs' => 'array',
            'result' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(PartnershipWorkspace::class, 'workspace_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
