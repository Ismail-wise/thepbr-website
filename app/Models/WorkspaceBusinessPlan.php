<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkspaceBusinessPlan extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['draft' => 'array', 'draft_base_revision' => 'integer', 'lock_version' => 'integer'];
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(WorkspaceBusinessPlanRevision::class, 'business_plan_id');
    }
}
