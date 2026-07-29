<?php

namespace App\Exceptions;

use App\Models\Product;
use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public function __construct(?Product $product, int $requestedQuantity)
    {
        $available = $product?->total_stock ?? 0;

        parent::__construct(
            "Insufficient stock for product '{$product?->name}': requested {$requestedQuantity}, only {$available} available."
        );
    }
}
