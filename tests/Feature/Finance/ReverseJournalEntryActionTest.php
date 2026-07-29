<?php

namespace Tests\Feature\Finance;

use App\Actions\Finance\ReverseJournalEntryAction;
use App\Enums\DebitCredit;
use App\Enums\JournalEntryType;
use App\Services\JournalEntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\Concerns\SeedsChartOfAccounts;
use Tests\TestCase;

class ReverseJournalEntryActionTest extends TestCase
{
    use RefreshDatabase, SeedsChartOfAccounts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedChartOfAccounts();
    }

    public function test_reversal_produces_mirrored_balanced_entry_and_flags_original(): void
    {
        $original = app(JournalEntryService::class)->post(
            entryDate: now(),
            description: 'Cash sale',
            type: JournalEntryType::Sale,
            lines: [
                ['account_id' => $this->cashAccount->id, 'type' => DebitCredit::Debit, 'amount' => 500],
                ['account_id' => $this->revenueAccount->id, 'type' => DebitCredit::Credit, 'amount' => 500],
            ],
        );

        $this->assertEquals(500, $this->cashAccount->fresh()->current_balance_cache);

        $reversal = app(ReverseJournalEntryAction::class)->handle($original, 'Sale mistakenly recorded');

        $this->assertTrue($original->fresh()->is_reversed);
        $this->assertEquals($original->id, $reversal->reverses_journal_entry_id);
        $this->assertEquals(JournalEntryType::Reversal, $reversal->entry_type);

        $this->assertEquals(0, $this->cashAccount->fresh()->current_balance_cache);
        $this->assertEquals(0, $this->revenueAccount->fresh()->current_balance_cache);

        $debitSum = $reversal->lines->where('type', DebitCredit::Debit)->sum('amount');
        $creditSum = $reversal->lines->where('type', DebitCredit::Credit)->sum('amount');
        $this->assertEquals($debitSum, $creditSum);
    }
}
