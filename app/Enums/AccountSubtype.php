<?php

namespace App\Enums;

enum AccountSubtype: string
{
    case Cash = 'cash';
    case Bank = 'bank';
    case AccountsReceivable = 'accounts_receivable';
    case AccountsPayable = 'accounts_payable';
    case SalesRevenue = 'sales_revenue';
    case OperatingExpense = 'operating_expense';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Bank => 'Bank',
            self::AccountsReceivable => 'Accounts Receivable',
            self::AccountsPayable => 'Accounts Payable',
            self::SalesRevenue => 'Sales Revenue',
            self::OperatingExpense => 'Operating Expense',
            self::Other => 'Other',
        };
    }
}
