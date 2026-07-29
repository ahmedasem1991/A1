<?php

namespace App\Enums;

enum JournalEntryType: string
{
    case Sale = 'sale';
    case PaymentReceived = 'payment_received';
    case PaymentMade = 'payment_made';
    case Expense = 'expense';
    case Transfer = 'transfer';
    case Adjustment = 'adjustment';
    case Reversal = 'reversal';
    case OpeningBalance = 'opening_balance';

    public function label(): string
    {
        return match ($this) {
            self::Sale => 'Sale',
            self::PaymentReceived => 'Payment Received',
            self::PaymentMade => 'Payment Made',
            self::Expense => 'Expense',
            self::Transfer => 'Transfer',
            self::Adjustment => 'Adjustment',
            self::Reversal => 'Reversal',
            self::OpeningBalance => 'Opening Balance',
        };
    }
}
