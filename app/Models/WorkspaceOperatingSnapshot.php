<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceOperatingSnapshot extends Model
{
    protected $fillable = [
        'workspace_id',
        'domain_key',
        'revision',
        'status',
        'schema_version',
        'payload',
        'summary',
        'generated_by_user_id',
        'generated_at',
        'agreed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'summary' => 'array',
            'generated_at' => 'datetime',
            'agreed_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(PartnershipWorkspace::class, 'workspace_id');
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }
}
