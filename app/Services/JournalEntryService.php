<?php

namespace App\Services;

use App\Enums\DebitCredit;
use App\Enums\JournalEntryType;
use App\Exceptions\UnbalancedJournalEntryException;
use App\Models\Account;
use App\Models\JournalEntry;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class JournalEntryService
{
    /**
     * Post a balanced double-entry journal entry. This is the only code path
     * allowed to write to journal_entries / journal_entry_lines.
     *
     * @param  array<int, array{
     *     account_id: int,
     *     type: \App\Enums\DebitCredit,
     *     amount: float,
     *     customer_id?: int|null,
     *     supplier_id?: int|null,
     *     memo?: string|null,
     * }>  $lines
     */
    public function post(
        DateTimeInterface $entryDate,
        string $description,
        JournalEntryType $type,
        array $lines,
        ?Model $sourceable = null,
        ?int $postedByUserId = null,
    ): JournalEntry {
        if (count($lines) < 2) {
            throw new UnbalancedJournalEntryException('A journal entry requires at least two lines.');
        }

        $debitCents = 0;
        $creditCents = 0;

        foreach ($lines as $line) {
            if ($line['amount'] <= 0) {
                throw new UnbalancedJournalEntryException('Journal entry line amounts must be greater than zero.');
            }

            $cents = (int) round($line['amount'] * 100);

            if ($line['type'] === DebitCredit::Debit) {
                $debitCents += $cents;
            } else {
                $creditCents += $cents;
            }
        }

        if ($debitCents !== $creditCents) {
            throw new UnbalancedJournalEntryException(
                "Journal entry does not balance: debits={$debitCents}, credits={$creditCents} (in cents)."
            );
        }

        return DB::transaction(function () use ($entryDate, $description, $type, $lines, $sourceable, $postedByUserId, $debitCents) {
            $accounts = Account::query()
                ->whereIn('id', array_column($lines, 'account_id'))
                ->get()
                ->keyBy('id');

            foreach ($lines as $line) {
                $account = $accounts->get($line['account_id']);

                if (! $account) {
                    throw new UnbalancedJournalEntryException("Account [{$line['account_id']}] does not exist.");
                }

                if (! $account->is_active) {
                    throw new UnbalancedJournalEntryException("Account [{$account->name}] is not active.");
                }
            }

            $journalEntry = JournalEntry::create([
                'entry_date' => $entryDate,
                'entry_type' => $type,
                'description' => $description,
                'sourceable_type' => $sourceable?->getMorphClass(),
                'sourceable_id' => $sourceable?->getKey(),
                'posted_by_user_id' => $postedByUserId,
                'is_reversed' => false,
                'total_amount' => $debitCents / 100,
            ]);

            foreach ($lines as $line) {
                $journalEntry->lines()->create([
                    'account_id' => $line['account_id'],
                    'type' => $line['type'],
                    'amount' => $line['amount'],
                    'entry_date' => $entryDate,
                    'customer_id' => $line['customer_id'] ?? null,
                    'supplier_id' => $line['supplier_id'] ?? null,
                    'memo' => $line['memo'] ?? null,
                ]);

                $account = $accounts->get($line['account_id']);
                $normalBalance = $account->account_type->normalBalance();
                $delta = $line['type'] === $normalBalance ? $line['amount'] : -$line['amount'];

                $account->increment('current_balance_cache', $delta);
            }

            return $journalEntry->fresh('lines');
        });
    }
}
