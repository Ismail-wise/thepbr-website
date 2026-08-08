<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartnershipWorkspace extends Model
{
    use HasFactory;

    public const BUSINESS_STAGES = [
        'new' => 'Planning a New Partnership',
        'existing' => 'Managing an Existing Partnership',
    ];

    public const CURRENCIES = [
        'THB' => 'Thai Baht (THB)',
        'MMK' => 'Myanmar Kyat (MMK)',
        'USD' => 'US Dollar (USD)',
        'SGD' => 'Singapore Dollar (SGD)',
        'MYR' => 'Malaysian Ringgit (MYR)',
    ];

    protected $fillable = [
        'owner_user_id',
        'name',
        'business_name',
        'business_stage',
        'currency_code',
        'status',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'owner_user_id'
        );
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(
            WorkspaceMember::class,
            'workspace_id'
        );
    }

    public function acceptedMemberships(): HasMany
    {
        return $this->memberships()
            ->where(
                'invitation_status',
                'accepted'
            );
    }

    public function toolSessions(): HasMany
    {
        return $this->hasMany(
            ToolSession::class,
            'workspace_id'
        );
    }

    public function toolOutputs(): HasMany
    {
        return $this->hasMany(
            WorkspaceToolOutput::class,
            'workspace_id'
        );
    }

    public function isNewPartnership(): bool
    {
        return $this->business_stage === 'new';
    }

    public function isExistingPartnership(): bool
    {
        return $this->business_stage === 'existing';
    }

    public function hasBusinessContext(): bool
    {
        return in_array(
            $this->business_stage,
            array_keys(self::BUSINESS_STAGES),
            true
        ) && filled($this->currency_code);
    }
}
