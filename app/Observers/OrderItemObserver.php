<?php

namespace App\Observers;

use App\Models\OrderItem;

class OrderItemObserver
{
    public function created(OrderItem $orderItem): void
    {
        if ($orderItem->category === 'product') {
            $orderItem->deductStock($orderItem->quantity);
        }
    }

    public function updated(OrderItem $orderItem): void
    {
        if ($orderItem->category !== 'product' || ! $orderItem->isDirty('quantity')) {
            return;
        }

        $delta = $orderItem->quantity - $orderItem->getOriginal('quantity');

        if ($delta > 0) {
            $orderItem->deductStock($delta);
        } elseif ($delta < 0) {
            $orderItem->restoreStock(abs($delta));
        }
    }

    public function deleted(OrderItem $orderItem): void
    {
        if ($orderItem->category === 'product') {
            $orderItem->restoreStock($orderItem->quantity);
        }
    }
}
