<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Promotion extends Model
{
    protected $fillable = ['name', 'required_product_ids', 'discount_amount', 'starts_at', 'ends_at', 'is_active'];

    protected function casts(): array
    {
        return ['required_product_ids' => 'array', 'discount_amount' => 'integer', 'starts_at' => 'datetime',
            'ends_at' => 'datetime', 'is_active' => 'boolean'];
    }
}
