<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;
    protected $fillable = [
        'order_id',
        'studio_image_id',
        'is_instant',
        'include_soft_copy',
        'price',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function studioImage()
    {
        return $this->belongsTo(\App\Models\StudioImage::class);
    }
}
