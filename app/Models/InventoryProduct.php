<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryProduct extends Model
{
    use HasFactory;

    protected $table = 'inventory_product';

    protected $fillable = ['product_id', 'inventory_id', 'stock_quantity'];

     public function order(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }
}
