<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceToolAction extends Model
{
    public const ACTIVE_STATUSES = [
        'open',
        'in_progress',
        'blocked',
    ];

    protected $fillable = [
        'workspace_id',
        'chapter_tool_id',
        'source_tool_session_id',
        'workspace_tool_output_id',
        'created_by_user_id',
        'title',
        'description',
        'owner_name',
        'priority',
        'status',
        'due_date',
        'completed_at',
        'operating_context',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'operating_context' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(
            PartnershipWorkspace::class,
            'workspace_id'
        );
    }

    public function tool(): BelongsTo
    {
        return $this->belongsTo(
            ChapterTool::class,
            'chapter_tool_id'
        );
    }

    public function sourceSession(): BelongsTo
    {
        return $this->belongsTo(
            ToolSession::class,
            'source_tool_session_id'
        );
    }

    public function workspaceOutput(): BelongsTo
    {
        return $this->belongsTo(
            WorkspaceToolOutput::class,
            'workspace_tool_output_id'
        );
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by_user_id'
        );
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query
            ->active()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today());
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isOverdue(): bool
    {
        return ! $this->isCompleted()
            && $this->due_date !== null
            && $this->due_date->isBefore(today());
    }
}
