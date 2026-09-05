<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class WorkspaceV2CanonicalWorkSource extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'workspace_id',
        'work_artifact_id',
        'source_generation',
        'canonical_revision_id',
        'source_domain_key',
        'source_component_key',
        'source_revision',
        'pinned_at',
    ];

    protected function casts(): array
    {
        return [
            'source_generation' => 'integer',
            'canonical_revision_id' => 'integer',
            'source_revision' => 'integer',
            'pinned_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException(
                'V2 canonical work source evidence is append-only '
                .'and immutable.'
            );
        });

        static::deleting(function (): void {
            throw new LogicException(
                'V2 canonical work source evidence is append-only '
                .'and cannot be deleted.'
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

    public function workArtifact(): BelongsTo
    {
        return $this->belongsTo(
            WorkspaceV2CanonicalWorkArtifact::class,
            'work_artifact_id'
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
