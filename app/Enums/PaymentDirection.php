<?php

namespace App\Enums;

enum PaymentDirection: string
{
    case Inbound = 'inbound';
    case Outbound = 'outbound';

    public function label(): string
    {
        return match ($this) {
            self::Inbound => 'Inbound',
            self::Outbound => 'Outbound',
        };
    }
}
