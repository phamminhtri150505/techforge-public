<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['specifications_snapshot' => 'array', 'unit_price' => 'integer', 'quantity' => 'integer'];
    }
}
