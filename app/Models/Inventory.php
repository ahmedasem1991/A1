<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inventory extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'inventory_product')
            ->withPivot('stock_quantity')
            ->withTimestamps();
    }

    public function inventoryProduct(): HasMany
    {
        return $this->hasMany(InventoryProduct::class);
    }
}
