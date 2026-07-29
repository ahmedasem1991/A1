<?php

namespace App\Filament\Widgets;

use App\Enums\AccountType;
use App\Enums\DebitCredit;
use App\Enums\InvoiceStatus;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\JournalEntryLine;
use App\Models\Supplier;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CashPositionOverview extends BaseWidget
{
    use HasWidgetShield;

    protected function getStats(): array
    {
        $cashBalance = Account::query()->where('is_bank_account', true)->sum('current_balance_cache');
        $outstandingReceivables = Customer::query()->sum('current_balance_cache');
        $outstandingPayables = Supplier::query()->sum('current_balance_cache');

        [$todayIncome, $todayExpense] = $this->todayIncomeAndExpense();
        [$overdueCount, $overdueTotal] = $this->overdueInvoices();

        return [
            Stat::make('Cash Balance', 'EGP '.number_format($cashBalance, 2)),
            Stat::make('Outstanding Receivables', 'EGP '.number_format($outstandingReceivables, 2)),
            Stat::make('Outstanding Payables', 'EGP '.number_format($outstandingPayables, 2)),
            Stat::make("Today's Income", 'EGP '.number_format($todayIncome, 2))->color('success'),
            Stat::make("Today's Expenses", 'EGP '.number_format($todayExpense, 2))->color('danger'),
            Stat::make('Overdue Invoices', (string) $overdueCount)
                ->description('EGP '.number_format($overdueTotal, 2).' outstanding')
                ->color($overdueCount > 0 ? 'danger' : 'success'),
        ];
    }

    /**
     * @return array{0: int, 1: float}
     */
    private function overdueInvoices(): array
    {
        $overdueInvoices = Invoice::query()->where('status', InvoiceStatus::Overdue)->get(['balance_cache']);

        return [$overdueInvoices->count(), (float) $overdueInvoices->sum('balance_cache')];
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function todayIncomeAndExpense(): array
    {
        $lines = JournalEntryLine::query()
            ->whereDate('entry_date', today())
            ->with('account')
            ->get();

        $income = $lines
            ->where('type', DebitCredit::Credit)
            ->filter(fn ($line) => $line->account->account_type === AccountType::Revenue)
            ->sum('amount');

        $expense = $lines
            ->where('type', DebitCredit::Debit)
            ->filter(fn ($line) => $line->account->account_type === AccountType::Expense)
            ->sum('amount');

        return [(float) $income, (float) $expense];
    }
}
