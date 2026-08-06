<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartnershipWorkspace extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_user_id',
        'name',
        'business_name',
        'status',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class, 'workspace_id');
    }

    public function acceptedMemberships(): HasMany
    {
        return $this->memberships()->where('invitation_status', 'accepted');
    }
}
