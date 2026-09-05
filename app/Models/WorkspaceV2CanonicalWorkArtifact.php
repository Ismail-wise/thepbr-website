<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class WorkspaceV2CanonicalWorkArtifact extends Model
{
    protected $fillable = [
        'workspace_id',
        'target_domain_key',
        'target_component_key',
        'producer_domain_key',
        'producer_component_key',
        'state',
        'payload',
        'source_generation',
        'created_by_user_id',
        'submitted_by_user_id',
        'submitted_at',
        'amendment_review_case_id',
        'amendment_target_canonical_revision_id',
        'published_canonical_revision_id',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'source_generation' => 'integer',
            'submitted_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException(
                'V2 canonical work artifacts may only be mutated '
                .'through the canonical work service.'
            );
        });

        static::deleting(function (): void {
            throw new LogicException(
                'V2 canonical work artifacts cannot be deleted.'
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by_user_id'
        );
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'submitted_by_user_id'
        );
    }

    public function amendmentReviewCase(): BelongsTo
    {
        return $this->belongsTo(
            WorkspaceV2ReviewCase::class,
            'amendment_review_case_id'
        );
    }

    public function amendmentTargetCanonicalRevision(): BelongsTo
    {
        return $this->belongsTo(
            WorkspaceV2CanonicalRevision::class,
            'amendment_target_canonical_revision_id'
        );
    }

    public function publishedCanonicalRevision(): BelongsTo
    {
        return $this->belongsTo(
            WorkspaceV2CanonicalRevision::class,
            'published_canonical_revision_id'
        );
    }

    public function canonicalSources(): HasMany
    {
        return $this->hasMany(
            WorkspaceV2CanonicalWorkSource::class,
            'work_artifact_id'
        );
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null
            || $this->published_canonical_revision_id !== null;
    }
}
