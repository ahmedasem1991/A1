<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;
    protected $fillable = ['name','sku','description','price','base_price','is_active'];

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }
}
