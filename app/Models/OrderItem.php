<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;
 protected $guarded = [];

 public static array $workflow = ['creation', 'processing', 'revision', 'printing', 'delivery','completed'];
  // app/Models/OrderItem.php
public function order(): BelongsTo
{
    return $this->belongsTo(Order::class);
}

    public function studioImage()
    {
        return $this->belongsTo(\App\Models\StudioImage::class);
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
