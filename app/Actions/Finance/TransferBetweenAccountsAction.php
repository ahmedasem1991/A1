<?php

namespace App\Actions\Finance;

use App\Enums\DebitCredit;
use App\Enums\JournalEntryType;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Services\JournalEntryService;
use DateTimeInterface;

class TransferBetweenAccountsAction
{
    public function __construct(private JournalEntryService $journalEntryService) {}

    public function handle(
        Account $from,
        Account $to,
        float $amount,
        DateTimeInterface $transferDate,
        ?string $memo = null,
    ): JournalEntry {
        return $this->journalEntryService->post(
            entryDate: $transferDate,
            description: $memo ?? "Transfer from {$from->name} to {$to->name}",
            type: JournalEntryType::Transfer,
            lines: [
                ['account_id' => $to->id, 'type' => DebitCredit::Debit, 'amount' => $amount],
                ['account_id' => $from->id, 'type' => DebitCredit::Credit, 'amount' => $amount],
            ],
        );
    }
}
