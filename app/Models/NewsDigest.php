<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsDigest extends Model
{
    protected $fillable = ['item_count', 'entries'];

    protected function casts(): array
    {
        return ['entries' => 'array'];
    }
}
