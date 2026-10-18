<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkspaceKnowledgeSource extends Model
{
    public const BUSINESS_STATUSES = ['draft' => 'Draft · စဉ်းစားဆဲ', 'current' => 'Current · လက်ရှိအသုံးပြုဆဲ', 'historical' => 'Historical · ယခင်မှတ်တမ်း'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['extracted_partial' => 'boolean', 'ready_at' => 'datetime', 'revision' => 'integer'];
    }
}
