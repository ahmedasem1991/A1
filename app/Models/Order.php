<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'customer_id',
        'subtotal',
        'discount',
        'total_price',
        'paid_amount',
        'remaining_amount',
        'status',
    ];

    protected $casts = [
        'items' => 'array', // ✅ required for saving repeater data
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'total_price' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'status' => OrderStatus::class,
    ];

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);   // FK = order_id
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }
}
