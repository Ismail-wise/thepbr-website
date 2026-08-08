<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseChapter extends Model
{
    use HasFactory;

    protected $fillable = [
        'chapter_number',
        'slug',
        'phase',
        'title_en',
        'title_mm',
        'description',
        'topics',
        'version',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'topics' => 'array',
            'is_published' => 'boolean',
        ];
    }

    public function tools(): HasMany
    {
        return $this->hasMany(
            ChapterTool::class
        )->orderBy('sort_order');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(
            ChapterProgress::class
        );
    }
}
