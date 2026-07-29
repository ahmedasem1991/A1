<?php

namespace App\Services;

use App\Enums\DebitCredit;
use App\Models\Account;
use App\Models\Customer;
use App\Models\JournalEntryLine;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LedgerReportService
{
    /**
     * @return Collection<int, array{date: \Carbon\Carbon, description: string, debit: float, credit: float, balance: float}>
     */
    public function customerLedger(Customer $customer, ?Carbon $from = null, ?Carbon $to = null): Collection
    {
        return $this->ledgerFor(
            JournalEntryLine::query()->where('customer_id', $customer->id),
            $from,
            $to,
        );
    }

    /**
     * @return Collection<int, array{date: \Carbon\Carbon, description: string, debit: float, credit: float, balance: float}>
     */
    public function supplierLedger(Supplier $supplier, ?Carbon $from = null, ?Carbon $to = null): Collection
    {
        return $this->ledgerFor(
            JournalEntryLine::query()->where('supplier_id', $supplier->id),
            $from,
            $to,
        );
    }

    /**
     * @return Collection<int, array{date: \Carbon\Carbon, description: string, debit: float, credit: float, balance: float}>
     */
    public function accountLedger(Account $account, ?Carbon $from = null, ?Carbon $to = null): Collection
    {
        return $this->ledgerFor(
            JournalEntryLine::query()->where('account_id', $account->id),
            $from,
            $to,
        );
    }

    /**
     * $from/$to are used as exact instants — callers wanting a whole day
     * inclusive must pass startOfDay()/endOfDay() themselves, the same
     * convention used by FinancialReportService and SalesReportService.
     */
    private function ledgerFor(Builder $query, ?Carbon $from, ?Carbon $to): Collection
    {
        if ($from) {
            $query->where('entry_date', '>=', $from);
        }

        if ($to) {
            $query->where('entry_date', '<=', $to);
        }

        $lines = $query
            ->with('journalEntry')
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();

        $balance = 0;

        return $lines->map(function (JournalEntryLine $line) use (&$balance) {
            $delta = $line->type === DebitCredit::Debit ? (float) $line->amount : -(float) $line->amount;
            $balance += $delta;

            return [
                'date' => $line->entry_date,
                'description' => $line->memo ?? $line->journalEntry?->description ?? '',
                'debit' => $line->type === DebitCredit::Debit ? (float) $line->amount : 0.0,
                'credit' => $line->type === DebitCredit::Credit ? (float) $line->amount : 0.0,
                'balance' => round($balance, 2),
            ];
        });
    }
}
