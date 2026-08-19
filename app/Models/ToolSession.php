<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ToolSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'chapter_tool_id',
        'workspace_id',
        'business_stage',
        'scenario_name',
        'status',
        'input_data',
        'result_data',
        'started_at',
        'last_saved_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'input_data' => 'array',
            'result_data' => 'array',
            'started_at' => 'datetime',
            'last_saved_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tool(): BelongsTo
    {
        return $this->belongsTo(
            ChapterTool::class,
            'chapter_tool_id'
        );
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(
            PartnershipWorkspace::class,
            'workspace_id'
        );
    }

    public function workspaceOutputs(): HasMany
    {
        return $this->hasMany(
            WorkspaceToolOutput::class,
            'source_tool_session_id'
        );
    }

    public function toolActions(): HasMany
    {
        return $this->hasMany(
            WorkspaceToolAction::class,
            'source_tool_session_id'
        );
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
