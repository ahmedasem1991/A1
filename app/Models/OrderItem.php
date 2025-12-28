<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class OrderItem extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $guarded = [];

    public static array $workflow = ['creation', 'processing', 'revision', 'printing', 'completed'];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('original_image')->singleFile();
        $this->addMediaCollection('enhanced_image')->singleFile();
    }

    // app/Models/OrderItem.php
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function studioImage()
    {
        return $this->belongsTo(\App\Models\StudioImage::class);
    }

    public function product()
    {
        return $this->belongsTo(\App\Models\Product::class);
    }

    public function imageCard()
    {
        return $this->belongsTo(\App\Models\ImageCard::class);
    }

    public function advanceStatus(): void
    {
        $currentIndex = array_search($this->status, self::$workflow);

        if ($this->category === 'product' && $this->status === 'creation') {
            $currentIndex += 4;
        } else {
            $currentIndex++;
        }

        $this->status = self::$workflow[$currentIndex] ?? $this->status;
        $this->save();
    }
}
