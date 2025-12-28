<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudioImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'image_size',
        'image_count',
        'price',
        'instant_price',
        'soft_copy_price',
        'name_price',
    ];
}
