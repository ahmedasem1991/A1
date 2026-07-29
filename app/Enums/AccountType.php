<?php

namespace App\Enums;

enum AccountType: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Revenue = 'revenue';
    case Expense = 'expense';

    public function label(): string
    {
        return match ($this) {
            self::Asset => 'Asset',
            self::Liability => 'Liability',
            self::Equity => 'Equity',
            self::Revenue => 'Revenue',
            self::Expense => 'Expense',
        };
    }

    /**
     * The side of the ledger that increases this account type's balance.
     */
    public function normalBalance(): DebitCredit
    {
        return match ($this) {
            self::Asset, self::Expense => DebitCredit::Debit,
            self::Liability, self::Equity, self::Revenue => DebitCredit::Credit,
        };
    }
}
