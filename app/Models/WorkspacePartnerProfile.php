<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspacePartnerProfile extends Model
{
    protected $fillable = [
        'workspace_id',
        'user_id',
        'partner_key',
        'display_name',
        'status',
        'profile_data',
    ];

    protected function casts(): array
    {
        return [
            'profile_data' => 'array',
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
}
