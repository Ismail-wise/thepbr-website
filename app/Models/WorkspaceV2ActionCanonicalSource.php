<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class WorkspaceV2ActionCanonicalSource extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'workspace_id',
        'action_id',
        'canonical_revision_id',
        'source_domain_key',
        'source_component_key',
        'source_revision',
        'pinned_at',
    ];

    protected function casts(): array
    {
        return [
            'source_revision' => 'integer',
            'pinned_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException(
                'V2 action source evidence is immutable.'
            );
        });

        static::deleting(function (): void {
            throw new LogicException(
                'V2 action source evidence cannot be deleted.'
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

    public function action(): BelongsTo
    {
        return $this->belongsTo(
            WorkspaceV2Action::class,
            'action_id'
        );
    }

    public function canonicalRevision(): BelongsTo
    {
        return $this->belongsTo(
            WorkspaceV2CanonicalRevision::class,
            'canonical_revision_id'
        );
    }
}
