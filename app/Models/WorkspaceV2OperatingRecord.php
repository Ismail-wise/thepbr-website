<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class WorkspaceV2OperatingRecord extends Model
{
    protected $fillable = [
        'workspace_id',
        'domain_key',
        'component_key',
        'recorded_by_user_id',
        'state',
        'title',
        'record_date',
        'effective_at',
        'payload',
        'metadata',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'record_date' => 'date',
            'effective_at' => 'datetime',
            'payload' => 'array',
            'metadata' => 'array',
            'recorded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException(
                'V2 operating records are append-only and immutable.'
            );
        });

        static::deleting(function (): void {
            throw new LogicException(
                'V2 operating records are append-only and cannot be deleted.'
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

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'recorded_by_user_id'
        );
    }

    public function canonicalSources(): HasMany
    {
        return $this->hasMany(
            WorkspaceV2OperatingRecordCanonicalSource::class,
            'operating_record_id'
        );
    }

    public function producedActions(): HasMany
    {
        return $this->hasMany(
            WorkspaceV2Action::class,
            'producer_operating_record_id'
        );
    }
}
