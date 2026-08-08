<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceToolOutput extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'chapter_tool_id',
        'source_tool_session_id',
        'revision',
        'status',
        'output_data',
        'generated_by_user_id',
        'generated_at',
        'agreed_at',
    ];

    protected function casts(): array
    {
        return [
            'output_data' => 'array',
            'generated_at' => 'datetime',
            'agreed_at' => 'datetime',
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

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'generated_by_user_id'
        );
    }

    public function isAgreed(): bool
    {
        return $this->status === 'agreed';
    }
}
