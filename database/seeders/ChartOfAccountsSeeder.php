<?php

namespace Database\Seeders;

use App\Enums\AccountSubtype;
use App\Enums\AccountType;
use App\Models\Account;
use Illuminate\Database\Seeder;

class ChartOfAccountsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Account::create([
            'name' => 'Cash Drawer',
            'account_type' => AccountType::Asset,
            'account_subtype' => AccountSubtype::Cash,
            'is_bank_account' => true,
            'is_system' => true,
        ]);

        Account::create([
            'name' => 'Main Bank',
            'account_type' => AccountType::Asset,
            'account_subtype' => AccountSubtype::Bank,
            'is_bank_account' => true,
            'is_system' => true,
        ]);

        Account::create([
            'name' => 'Accounts Receivable',
            'account_type' => AccountType::Asset,
            'account_subtype' => AccountSubtype::AccountsReceivable,
            'is_system' => true,
        ]);

        Account::create([
            'name' => 'Accounts Payable',
            'account_type' => AccountType::Liability,
            'account_subtype' => AccountSubtype::AccountsPayable,
            'is_system' => true,
        ]);

        Account::create([
            'name' => 'Sales Revenue',
            'account_type' => AccountType::Revenue,
            'account_subtype' => AccountSubtype::SalesRevenue,
            'is_system' => true,
        ]);

        $expenseAccounts = [
            'Office Supplies',
            'Equipment Purchase',
            'Rent',
            'Utilities',
            'Marketing',
            'Salaries',
            'Maintenance',
            'Software Subscription',
            'Miscellaneous Expense',
        ];

        foreach ($expenseAccounts as $name) {
            Account::create([
                'name' => $name,
                'account_type' => AccountType::Expense,
                'account_subtype' => AccountSubtype::OperatingExpense,
            ]);
        }
    }
}
