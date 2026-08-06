<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class StudentAccessCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_session_id',
        'code_hash',
        'code_encrypted',
        'code_last4',
        'status',
        'expires_at',
        'used_by_user_id',
        'used_at',
        'created_by_user_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function classSession(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class);
    }

    public function usedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public static function normalize(string $code): string
    {
        return strtoupper((string) preg_replace('/[^A-Z0-9]/i', '', trim($code)));
    }

    public static function fingerprint(string $code): string
    {
        return hash_hmac('sha256', self::normalize($code), (string) config('app.key'));
    }

    public static function encryptCode(string $code): string
    {
        return Crypt::encryptString(strtoupper(trim($code)));
    }

    protected function plainCode(): Attribute
    {
        return Attribute::get(
            fn (): string => Crypt::decryptString($this->code_encrypted),
        );
    }

    public function isUsable(): bool
    {
        return $this->status === 'available'
            && $this->used_by_user_id === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
