<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Processing = 'processing';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Processing => 'Processing',
            self::Completed => 'Completed',
        };
    }
}
