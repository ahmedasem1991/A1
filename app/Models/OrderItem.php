<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;
 protected $guarded = [];


  // app/Models/OrderItem.php
public function order(): BelongsTo
{
    return $this->belongsTo(Order::class);
}

    public function studioImage()
    {
        return $this->belongsTo(\App\Models\StudioImage::class);
    }
}
