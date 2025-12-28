<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImageCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'card_size',
        'price',
        'instant_price',

    ];
}
