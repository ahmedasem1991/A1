<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case InstaPay = 'instapay';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::InstaPay => 'InstaPay',
        };
    }

    /**
     * The account subtype money for this payment method always moves
     * through: Cash payments go through the Cash Drawer, InstaPay payments
     * go through the Main Bank account.
     */
    public function accountSubtype(): AccountSubtype
    {
        return match ($this) {
            self::Cash => AccountSubtype::Cash,
            self::InstaPay => AccountSubtype::Bank,
        };
    }
}
