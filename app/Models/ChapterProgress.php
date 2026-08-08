<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChapterProgress extends Model
{
    use HasFactory;

    protected $table = 'chapter_progress';

    protected $fillable = [
        'user_id',
        'course_chapter_id',
        'student_enrollment_id',
        'status',
        'progress_percent',
        'started_at',
        'completed_at',
        'last_activity_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(
            CourseChapter::class,
            'course_chapter_id'
        );
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(
            StudentEnrollment::class,
            'student_enrollment_id'
        );
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
