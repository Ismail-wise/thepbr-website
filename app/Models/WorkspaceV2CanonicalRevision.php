<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceV2CanonicalRevision extends Model
{
    protected $fillable = [
        'workspace_id',
        'domain_key',
        'component_key',
        'component_type',
        'revision',
        'state',
        'payload',
        'approval_decision',
        'published_by_user_id',
        'published_at',
        'superseded_at',
    ];

    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'payload' => 'array',
            'approval_decision' => 'array',
            'published_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(
            PartnershipWorkspace::class,
            'workspace_id'
        );
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'published_by_user_id'
        );
    }

    public function isCurrent(): bool
    {
        return $this->state === 'approved_current';
    }

    public function isSuperseded(): bool
    {
        return $this->state === 'superseded';
    }
}
