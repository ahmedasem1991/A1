<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'subtotal',
        'discount',
        'total_price',
        'paid_amount',
        'remaining_amount',
    ];
    protected $casts = [
            'items' => 'array', // ✅ required for saving repeater data
            'subtotal' => 'float',
            'discount' => 'float',
            'total_price' => 'float',
            'paid_amount' => 'float',
            'remaining_amount' => 'float',
        ];
    public function orderItems(): HasMany
{
    return $this->hasMany(OrderItem::class);   // FK = order_id
}
    
//   protected static function booted()
//     {
//         static::created(function ($order) {
//             // Log to verify if this event is being triggered
//             Log::info('Order created from Model event:', ['order' => $order->toArray()]);
//             // You can also use dd($order) here
//         });
//     }
// // Optional: Auto-calculate total price before saving
// public function calculateTotals(): void
// {
//     $subtotal = $this->items()->sum('price');
//     $total = max(0, $subtotal - $this->discount);
//     $remaining = max(0, $total - $this->paid_amount);

//     $this->subtotal = $subtotal;
//     $this->total_price = $total;
//     $this->remaining_amount = $remaining;
//     $this->save();
// }

  public function calculateTotals(): void
    {
        $subtotal = 0;

        foreach ($this->items ?? [] as $item) {
            $subtotal += floatval($item['price'] ?? 0);
        }

        $total = max(0, $subtotal - ($this->discount ?? 0));
        $remaining = max(0, $total - ($this->paid_amount ?? 0));

        $this->subtotal = $subtotal;
        $this->total_price = $total;
        $this->remaining_amount = $remaining;

        $this->save();
    }

  
}
