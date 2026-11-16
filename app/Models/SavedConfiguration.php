<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavedConfiguration extends Model
{
    protected $fillable = ['name', 'parts', 'owned_types'];

    protected function casts(): array
    {
        return ['parts' => 'array', 'owned_types' => 'array'];
    }
}
