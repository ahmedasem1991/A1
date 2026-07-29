<?php

namespace App\Actions\Finance;

use App\Enums\JournalEntryType;
use App\Exceptions\ImmutableJournalEntryException;
use App\Models\JournalEntry;
use App\Services\JournalEntryService;
use Illuminate\Support\Facades\DB;

class ReverseJournalEntryAction
{
    public function __construct(private JournalEntryService $journalEntryService) {}

    public function handle(JournalEntry $original, string $reason): JournalEntry
    {
        return DB::transaction(function () use ($original, $reason) {
            $original = JournalEntry::query()->lockForUpdate()->findOrFail($original->id);

            if ($original->is_reversed) {
                throw new ImmutableJournalEntryException("Journal entry #{$original->id} has already been reversed.");
            }

            $lines = $original->lines->map(fn ($line) => [
                'account_id' => $line->account_id,
                'type' => $line->type->opposite(),
                'amount' => (float) $line->amount,
                'customer_id' => $line->customer_id,
                'supplier_id' => $line->supplier_id,
                'memo' => $line->memo,
            ])->all();

            $reversal = $this->journalEntryService->post(
                entryDate: now(),
                description: "Reversal of #{$original->id}: {$reason}",
                type: JournalEntryType::Reversal,
                lines: $lines,
                sourceable: $original->sourceable,
            );

            DB::table('journal_entries')->where('id', $reversal->id)->update(['reverses_journal_entry_id' => $original->id]);
            DB::table('journal_entries')->where('id', $original->id)->update(['is_reversed' => true]);

            return $reversal->fresh('lines');
        });
    }
}
