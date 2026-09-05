<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class WorkspaceV2Action extends Model
{
    protected $fillable = [
        'workspace_id',
        'action_domain_key',
        'action_component_key',
        'producer_domain_key',
        'producer_component_key',
        'producer_canonical_revision_id',
        'producer_operating_record_id',
        'created_by_user_id',
        'title',
        'description',
        'owner_name',
        'priority',
        'status',
        'due_date',
        'completed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $action): void {
            $allowed = [
                'status',
                'completed_at',
                'updated_at',
            ];

            $unexpected = array_diff(
                array_keys($action->getDirty()),
                $allowed
            );

            if ($unexpected !== []) {
                throw new LogicException(
                    'V2 action identity and provenance are immutable; '
                    .'only lifecycle status may change.'
                );
            }
        });

        static::deleting(function (): void {
            throw new LogicException(
                'V2 actions cannot be deleted; transition lifecycle state instead.'
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

    public function producerCanonicalRevision(): BelongsTo
    {
        return $this->belongsTo(
            WorkspaceV2CanonicalRevision::class,
            'producer_canonical_revision_id'
        );
    }

    public function producerOperatingRecord(): BelongsTo
    {
        return $this->belongsTo(
            WorkspaceV2OperatingRecord::class,
            'producer_operating_record_id'
        );
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by_user_id'
        );
    }

    public function canonicalSources(): HasMany
    {
        return $this->hasMany(
            WorkspaceV2ActionCanonicalSource::class,
            'action_id'
        );
    }

    public function isTerminal(): bool
    {
        return in_array(
            $this->status,
            ['completed', 'superseded'],
            true
        );
    }
}
