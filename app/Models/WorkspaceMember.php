<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'user_id',
        'member_role',
        'invitation_status',
        'invited_email',
        'invitation_token_hash',
        'invited_by_user_id',
        'invited_at',
        'accepted_at',
        'permissions',
    ];

    protected function casts(): array
    {
        return [
            'invited_at' => 'datetime',
            'accepted_at' => 'datetime',
            'permissions' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(PartnershipWorkspace::class, 'workspace_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public static function fingerprintInvitationToken(string $token): string
    {
        return hash_hmac('sha256', trim($token), (string) config('app.key'));
    }

    public function isAccepted(): bool
    {
        return $this->invitation_status === 'accepted';
    }

    public function isPending(): bool
    {
        return $this->invitation_status === 'pending';
    }
}
