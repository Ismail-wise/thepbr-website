<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceBusinessPlanRevision extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['content' => 'array', 'revision' => 'integer', 'based_on_revision' => 'integer', 'created_at' => 'datetime'];
    }

    public function businessPlan(): BelongsTo
    {
        return $this->belongsTo(WorkspaceBusinessPlan::class);
    }
}
