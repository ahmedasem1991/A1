<?php

namespace Tests\Feature\Finance;

use App\Enums\AccountSubtype;
use App\Enums\AccountType;
use App\Enums\DebitCredit;
use App\Enums\JournalEntryType;
use App\Filament\Resources\JournalEntryResource\Pages\ViewJournalEntry;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\JournalEntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class JournalEntryReversalUiTest extends TestCase
{
    use RefreshDatabase;

    private Account $cashAccount;

    private Account $revenueAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->cashAccount = Account::create([
            'name' => 'Cash Drawer',
            'account_type' => AccountType::Asset,
            'account_subtype' => AccountSubtype::Cash,
            'is_bank_account' => true,
        ]);

        $this->revenueAccount = Account::create([
            'name' => 'Sales Revenue',
            'account_type' => AccountType::Revenue,
            'account_subtype' => AccountSubtype::SalesRevenue,
        ]);
    }

    private function makeEntry(): JournalEntry
    {
        return app(JournalEntryService::class)->post(
            entryDate: now(),
            description: 'Test sale',
            type: JournalEntryType::Sale,
            lines: [
                ['account_id' => $this->cashAccount->id, 'type' => DebitCredit::Debit, 'amount' => 500],
                ['account_id' => $this->revenueAccount->id, 'type' => DebitCredit::Credit, 'amount' => 500],
            ],
        );
    }

    public function test_reversing_an_entry_from_the_view_page_flags_the_original_and_posts_a_mirrored_entry(): void
    {
        $entry = $this->makeEntry();

        Livewire::test(ViewJournalEntry::class, ['record' => $entry->id])
            ->callAction('reverse', data: ['reason' => 'Recorded in error'])
            ->assertHasNoActionErrors();

        $this->assertTrue($entry->fresh()->is_reversed);
        $this->assertEquals(0, $this->cashAccount->fresh()->current_balance_cache);
        $this->assertEquals(0, $this->revenueAccount->fresh()->current_balance_cache);
    }

    public function test_reversal_requires_a_reason(): void
    {
        $entry = $this->makeEntry();

        Livewire::test(ViewJournalEntry::class, ['record' => $entry->id])
            ->callAction('reverse', data: ['reason' => ''])
            ->assertHasActionErrors(['reason' => 'required']);

        $this->assertFalse($entry->fresh()->is_reversed);
    }

    public function test_reverse_action_is_hidden_once_already_reversed(): void
    {
        $entry = $this->makeEntry();
        DB::table('journal_entries')->where('id', $entry->id)->update(['is_reversed' => true]);

        Livewire::test(ViewJournalEntry::class, ['record' => $entry->id])
            ->assertActionHidden('reverse');
    }
}
