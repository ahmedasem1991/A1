<?php

namespace App\Enums;

enum PaymentType: string
{
    case Standard = 'standard';
    case Refund = 'refund';
    case Advance = 'advance';

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Standard',
            self::Refund => 'Refund',
            self::Advance => 'Advance',
        };
    }
}
