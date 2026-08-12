<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceOperatingRecord extends Model
{
    protected $fillable = [
        'workspace_id',
        'chapter_tool_id',
        'user_id',
        'record_type',
        'status',
        'title',
        'record_date',
        'effective_at',
        'data',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'record_date' => 'date',
            'effective_at' => 'datetime',
            'data' => 'array',
            'metadata' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(PartnershipWorkspace::class, 'workspace_id');
    }

    public function tool(): BelongsTo
    {
        return $this->belongsTo(ChapterTool::class, 'chapter_tool_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
