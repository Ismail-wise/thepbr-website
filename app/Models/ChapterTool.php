<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChapterTool extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_chapter_id',
        'tool_key',
        'slug',
        'title_en',
        'title_mm',
        'tool_type',
        'description',
        'sort_order',
        'version',
        'supports_new_business',
        'supports_existing_business',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'supports_new_business' => 'boolean',
            'supports_existing_business' => 'boolean',
            'is_published' => 'boolean',
        ];
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(
            CourseChapter::class,
            'course_chapter_id'
        );
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(
            ToolSession::class
        );
    }

    public function workspaceOutputs(): HasMany
    {
        return $this->hasMany(
            WorkspaceToolOutput::class
        );
    }
}
