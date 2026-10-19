<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkspaceKnowledgeChunk extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['embedding' => 'array', 'revision' => 'integer'];
    }
}
