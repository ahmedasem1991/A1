<?php

namespace App\Console\Commands;

use App\Enums\AccountSubtype;
use App\Enums\AccountType;
use App\Enums\DebitCredit;
use App\Enums\PaymentDirection;
use App\Enums\PaymentType;
use App\Models\Account;
use App\Models\DailyFinancialSummary;
use App\Models\JournalEntryLine;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RefreshDailyFinancialSummaryCommand extends Command
{
    protected $signature = 'finance:refresh-daily-summary {date? : The date to summarize, defaults to yesterday}';

    protected $description = 'Refresh the daily_financial_summaries row for a given date (or a range via --from/--to)';

    public function handle(): int
    {
        $date = $this->argument('date')
            ? Carbon::parse($this->argument('date'))
            : now()->subDay();

        $this->refreshForDate($date->startOfDay());

        $this->info("Refreshed daily financial summary for {$date->toDateString()}.");

        return self::SUCCESS;
    }

    private function refreshForDate(Carbon $date): void
    {
        $dateString = $date->toDateString();

        $lines = JournalEntryLine::query()
            ->whereDate('entry_date', $dateString)
            ->with('account')
            ->get();

        $totalSales = $lines
            ->where('type', DebitCredit::Credit)
            ->filter(fn (JournalEntryLine $line) => $line->account->account_subtype === AccountSubtype::SalesRevenue)
            ->sum('amount');

        $totalIncome = $lines
            ->where('type', DebitCredit::Credit)
            ->filter(fn (JournalEntryLine $line) => $line->account->account_type === AccountType::Revenue)
            ->sum('amount');

        $totalExpenses = $lines
            ->where('type', DebitCredit::Debit)
            ->filter(fn (JournalEntryLine $line) => $line->account->account_type === AccountType::Expense)
            ->sum('amount');

        $paymentsReceived = Payment::query()
            ->whereDate('payment_date', $dateString)
            ->where('direction', PaymentDirection::Inbound)
            ->where('type', '!=', PaymentType::Refund)
            ->sum('amount');

        $paymentsMade = Payment::query()
            ->whereDate('payment_date', $dateString)
            ->where('direction', PaymentDirection::Outbound)
            ->where('type', '!=', PaymentType::Refund)
            ->sum('amount');

        $refundsIssued = Payment::query()
            ->whereDate('payment_date', $dateString)
            ->where('type', PaymentType::Refund)
            ->sum('amount');

        $outstandingReceivables = $this->balanceAsOfDate(AccountSubtype::AccountsReceivable, $date);
        $outstandingPayables = $this->balanceAsOfDate(AccountSubtype::AccountsPayable, $date);
        $cashBalance = Account::query()
            ->where('is_bank_account', true)
            ->get()
            ->sum(fn (Account $account) => $this->accountBalanceAsOfDate($account, $date));

        DailyFinancialSummary::query()->updateOrCreate(
            ['summary_date' => $dateString],
            [
                'total_sales' => $totalSales,
                'total_income' => $totalIncome,
                'total_expenses' => $totalExpenses,
                'net_profit' => $totalIncome - $totalExpenses,
                'payments_received' => $paymentsReceived,
                'payments_made' => $paymentsMade,
                'refunds_issued' => $refundsIssued,
                'outstanding_receivables_eod' => $outstandingReceivables,
                'outstanding_payables_eod' => $outstandingPayables,
                'cash_balance_eod' => $cashBalance,
            ]
        );
    }

    private function balanceAsOfDate(AccountSubtype $subtype, Carbon $date): float
    {
        return Account::query()
            ->where('account_subtype', $subtype)
            ->get()
            ->sum(fn (Account $account) => $this->accountBalanceAsOfDate($account, $date));
    }

    private function accountBalanceAsOfDate(Account $account, Carbon $date): float
    {
        $normalBalance = $account->account_type->normalBalance();

        $debits = (float) JournalEntryLine::query()
            ->where('account_id', $account->id)
            ->where('type', DebitCredit::Debit)
            ->whereDate('entry_date', '<=', $date->toDateString())
            ->sum('amount');

        $credits = (float) JournalEntryLine::query()
            ->where('account_id', $account->id)
            ->where('type', DebitCredit::Credit)
            ->whereDate('entry_date', '<=', $date->toDateString())
            ->sum('amount');

        return $normalBalance === DebitCredit::Debit ? $debits - $credits : $credits - $debits;
    }
}
