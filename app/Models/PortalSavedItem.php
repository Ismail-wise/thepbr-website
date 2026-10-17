<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortalSavedItem extends Model
{
    protected $fillable = ['user_id', 'kind', 'title', 'input', 'output'];

    protected function casts(): array
    {
        return ['input' => 'array', 'output' => 'array'];
    }
}
