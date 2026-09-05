<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class WorkspaceV2ReviewAuditEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'workspace_id',
        'review_case_id',
        'event_type',
        'actor_user_id',
        'metadata',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException(
                'V2 review audit events are append-only and immutable.'
            );
        });

        static::deleting(function (): void {
            throw new LogicException(
                'V2 review audit events are append-only and immutable.'
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

    public function reviewCase(): BelongsTo
    {
        return $this->belongsTo(
            WorkspaceV2ReviewCase::class,
            'review_case_id'
        );
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'actor_user_id'
        );
    }
}
