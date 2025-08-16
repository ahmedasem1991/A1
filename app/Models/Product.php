<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;
    protected $fillable = ['name','sku','description','price','base_price','is_active'];

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }
   public function inventories(): BelongsToMany
    {
        return $this->belongsToMany(Inventory::class , 'inventory_product')
            ->withPivot('stock_quantity') // Make sure to include stock_quantity in the pivot
            ->withTimestamps();
    }

     public function inventoryProduct(): HasMany
    {
        return $this->hasMany(InventoryProduct::class);
    }
    public function getTotalStockAttribute()
    {
        return $this->inventories->sum('pivot.stock_quantity');
    }
    public function category()
{
    return $this->belongsTo(Category::class, 'category_id');
}
}
