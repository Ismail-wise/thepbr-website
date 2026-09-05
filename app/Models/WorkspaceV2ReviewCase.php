<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class WorkspaceV2ReviewCase extends Model
{
    protected $fillable = [
        'workspace_id',
        'target_canonical_revision_id',
        'target_domain_key',
        'target_component_key',
        'target_revision',
        'producer_domain_key',
        'producer_component_key',
        'trigger_type',
        'trigger_context',
        'status',
        'resolution',
        'resolution_note',
        'opened_by_user_id',
        'resolved_by_user_id',
        'opened_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'target_revision' => 'integer',
            'trigger_context' => 'array',
            'opened_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (): void {
            throw new LogicException(
                'V2 review cases cannot be deleted; resolve them instead.'
            );
        });
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(
            PartnershipWorkspace::class,
            'workspace_id'
        );
    }

    public function targetCanonicalRevision(): BelongsTo
    {
        return $this->belongsTo(
            WorkspaceV2CanonicalRevision::class,
            'target_canonical_revision_id'
        );
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'opened_by_user_id'
        );
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'resolved_by_user_id'
        );
    }

    public function auditEvents(): HasMany
    {
        return $this->hasMany(
            WorkspaceV2ReviewAuditEvent::class,
            'review_case_id'
        );
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }
}
