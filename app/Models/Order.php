<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    public const STATUSES = ['pending' => 'Chờ xác nhận', 'confirmed' => 'Đã xác nhận', 'shipping' => 'Đang giao (mô phỏng)', 'completed' => 'Hoàn tất (mô phỏng)', 'cancelled' => 'Đã hủy'];

    public const TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['shipping', 'cancelled'],
        'shipping' => ['completed'],
        'completed' => [],
        'cancelled' => [],
    ];

    public function availableTransitions(): array
    {
        return array_intersect_key(self::STATUSES, array_flip(self::TRANSITIONS[$this->status] ?? []));
    }

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['compatibility_snapshot' => 'array', 'total' => 'integer', 'subtotal' => 'integer', 'discount' => 'integer', 'promotion_snapshot' => 'array'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
