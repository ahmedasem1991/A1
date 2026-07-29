<?php

namespace Tests\Feature\Finance\Concerns;

use App\Enums\AccountSubtype;
use App\Enums\AccountType;
use App\Models\Account;

trait SeedsChartOfAccounts
{
    protected Account $cashAccount;

    protected Account $bankAccount;

    protected Account $receivableAccount;

    protected Account $payableAccount;

    protected Account $revenueAccount;

    protected Account $expenseAccount;

    protected function seedChartOfAccounts(): void
    {
        $this->cashAccount = Account::create([
            'name' => 'Cash Drawer',
            'account_type' => AccountType::Asset,
            'account_subtype' => AccountSubtype::Cash,
            'is_bank_account' => true,
            'is_system' => true,
        ]);

        $this->bankAccount = Account::create([
            'name' => 'Main Bank',
            'account_type' => AccountType::Asset,
            'account_subtype' => AccountSubtype::Bank,
            'is_bank_account' => true,
            'is_system' => true,
        ]);

        $this->receivableAccount = Account::create([
            'name' => 'Accounts Receivable',
            'account_type' => AccountType::Asset,
            'account_subtype' => AccountSubtype::AccountsReceivable,
            'is_system' => true,
        ]);

        $this->payableAccount = Account::create([
            'name' => 'Accounts Payable',
            'account_type' => AccountType::Liability,
            'account_subtype' => AccountSubtype::AccountsPayable,
            'is_system' => true,
        ]);

        $this->revenueAccount = Account::create([
            'name' => 'Sales Revenue',
            'account_type' => AccountType::Revenue,
            'account_subtype' => AccountSubtype::SalesRevenue,
            'is_system' => true,
        ]);

        $this->expenseAccount = Account::create([
            'name' => 'Office Supplies',
            'account_type' => AccountType::Expense,
            'account_subtype' => AccountSubtype::OperatingExpense,
        ]);
    }
}
