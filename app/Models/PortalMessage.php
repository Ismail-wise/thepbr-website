<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortalMessage extends Model
{
    protected $fillable = ['conversation_id', 'role', 'content', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
