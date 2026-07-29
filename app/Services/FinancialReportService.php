<?php

namespace App\Services;

use App\Enums\AccountSubtype;
use App\Enums\AccountType;
use App\Enums\DebitCredit;
use App\Enums\PaymentDirection;
use App\Enums\PaymentType;
use App\Models\Account;
use App\Models\Customer;
use App\Models\DailyFinancialSummary;
use App\Models\JournalEntryLine;
use App\Models\Payment;
use App\Models\Supplier;
use Carbon\Carbon;

class FinancialReportService
{
    /**
     * Summarize financial activity over [from, to]. Historical days are read
     * from the daily_financial_summaries cache, which only has day-level
     * granularity, so any part of the range before today is always
     * calendar-day inclusive regardless of the time-of-day passed in.
     *
     * If the entire requested range falls within today, figures are computed
     * live with full time-of-day precision instead of the day-level cache,
     * since today is the only part of the range where per-transaction
     * timestamps are actually meaningful to filter by.
     *
     * @return array{
     *     total_sales: float,
     *     total_income: float,
     *     total_expenses: float,
     *     net_profit: float,
     *     payments_received: float,
     *     payments_made: float,
     *     refunds_issued: float,
     *     outstanding_receivables: float,
     *     outstanding_payables: float,
     *     cash_balance: float,
     * }
     */
    public function summarizeRange(Carbon $from, Carbon $to): array
    {
        $today = now()->startOfDay();
        $todayEnd = $today->copy()->endOfDay();

        if ($from->gte($today) && $from->lte($todayEnd) && $to->gte($today) && $to->lte($todayEnd)) {
            $totals = $this->liveWindowSummary($from, $to);

            return [
                ...$totals,
                'net_profit' => $totals['total_income'] - $totals['total_expenses'],
                'outstanding_receivables' => (float) Customer::query()->sum('current_balance_cache'),
                'outstanding_payables' => (float) Supplier::query()->sum('current_balance_cache'),
                'cash_balance' => (float) Account::query()->where('is_bank_account', true)->sum('current_balance_cache'),
            ];
        }

        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();

        $historicalTo = $to->lt($today) ? $to : $today->copy()->subDay();

        $totals = [
            'total_sales' => 0.0,
            'total_income' => 0.0,
            'total_expenses' => 0.0,
            'payments_received' => 0.0,
            'payments_made' => 0.0,
            'refunds_issued' => 0.0,
        ];

        if ($from->lte($historicalTo)) {
            $summaries = DailyFinancialSummary::query()
                ->whereBetween('summary_date', [$from->toDateString(), $historicalTo->toDateString()])
                ->get();

            foreach (['total_sales', 'total_income', 'total_expenses', 'payments_received', 'payments_made', 'refunds_issued'] as $field) {
                $totals[$field] += (float) $summaries->sum($field);
            }
        }

        if ($to->gte($today) && $from->lte($today)) {
            $liveToday = $this->liveWindowSummary($today, $todayEnd);

            foreach (array_keys($totals) as $field) {
                $totals[$field] += $liveToday[$field];
            }
        }

        return [
            ...$totals,
            'net_profit' => $totals['total_income'] - $totals['total_expenses'],
            'outstanding_receivables' => (float) Customer::query()->sum('current_balance_cache'),
            'outstanding_payables' => (float) Supplier::query()->sum('current_balance_cache'),
            'cash_balance' => (float) Account::query()->where('is_bank_account', true)->sum('current_balance_cache'),
        ];
    }

    /**
     * @return array{total_sales: float, total_income: float, total_expenses: float, payments_received: float, payments_made: float, refunds_issued: float}
     */
    private function liveWindowSummary(Carbon $from, Carbon $to): array
    {
        $lines = JournalEntryLine::query()
            ->whereBetween('entry_date', [$from, $to])
            ->with('account')
            ->get();

        $totalSales = (float) $lines
            ->where('type', DebitCredit::Credit)
            ->filter(fn (JournalEntryLine $line) => $line->account->account_subtype === AccountSubtype::SalesRevenue)
            ->sum('amount');

        $totalIncome = (float) $lines
            ->where('type', DebitCredit::Credit)
            ->filter(fn (JournalEntryLine $line) => $line->account->account_type === AccountType::Revenue)
            ->sum('amount');

        $totalExpenses = (float) $lines
            ->where('type', DebitCredit::Debit)
            ->filter(fn (JournalEntryLine $line) => $line->account->account_type === AccountType::Expense)
            ->sum('amount');

        $paymentsReceived = (float) Payment::query()
            ->whereBetween('payment_date', [$from, $to])
            ->where('direction', PaymentDirection::Inbound)
            ->where('type', '!=', PaymentType::Refund)
            ->sum('amount');

        $paymentsMade = (float) Payment::query()
            ->whereBetween('payment_date', [$from, $to])
            ->where('direction', PaymentDirection::Outbound)
            ->where('type', '!=', PaymentType::Refund)
            ->sum('amount');

        $refundsIssued = (float) Payment::query()
            ->whereBetween('payment_date', [$from, $to])
            ->where('type', PaymentType::Refund)
            ->sum('amount');

        return [
            'total_sales' => $totalSales,
            'total_income' => $totalIncome,
            'total_expenses' => $totalExpenses,
            'payments_received' => $paymentsReceived,
            'payments_made' => $paymentsMade,
            'refunds_issued' => $refundsIssued,
        ];
    }
}
