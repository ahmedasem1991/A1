<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
   
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

// Optional: Auto-calculate total price before saving
public function calculateTotals(): void
{
    $subtotal = $this->items()->sum('price');
    $total = max(0, $subtotal - $this->discount);
    $remaining = max(0, $total - $this->paid_amount);

    $this->subtotal = $subtotal;
    $this->total_price = $total;
    $this->remaining_amount = $remaining;
    $this->save();
}
}
