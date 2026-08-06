<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentEnrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'class_session_id',
        'student_access_code_id',
        'status',
        'started_at',
        'access_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'access_expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function classSession(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class);
    }

    public function accessCode(): BelongsTo
    {
        return $this->belongsTo(StudentAccessCode::class, 'student_access_code_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && ($this->access_expires_at === null || $this->access_expires_at->isFuture());
    }
}
