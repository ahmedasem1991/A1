<?php

namespace Tests\Feature\Finance;

use App\Enums\AccountType;
use App\Enums\DebitCredit;
use App\Enums\JournalEntryType;
use App\Exceptions\ImmutableJournalEntryException;
use App\Exceptions\UnbalancedJournalEntryException;
use App\Models\Account;
use App\Services\JournalEntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalEntryServiceTest extends TestCase
{
    use RefreshDatabase;

    private JournalEntryService $service;

    private Account $cash;

    private Account $revenue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(JournalEntryService::class);

        $this->cash = Account::create([
            'name' => 'Cash Drawer',
            'account_type' => AccountType::Asset,
            'is_bank_account' => true,
            'is_active' => true,
        ]);

        $this->revenue = Account::create([
            'name' => 'Sales Revenue',
            'account_type' => AccountType::Revenue,
            'is_active' => true,
        ]);
    }

    public function test_posting_a_balanced_entry_updates_account_balance_caches(): void
    {
        $this->service->post(
            entryDate: now(),
            description: 'Cash sale',
            type: JournalEntryType::Sale,
            lines: [
                ['account_id' => $this->cash->id, 'type' => DebitCredit::Debit, 'amount' => 500],
                ['account_id' => $this->revenue->id, 'type' => DebitCredit::Credit, 'amount' => 500],
            ],
        );

        $this->assertEquals(500, $this->cash->fresh()->current_balance_cache);
        $this->assertEquals(500, $this->revenue->fresh()->current_balance_cache);
    }

    public function test_posting_an_unbalanced_entry_throws(): void
    {
        $this->expectException(UnbalancedJournalEntryException::class);

        $this->service->post(
            entryDate: now(),
            description: 'Bad entry',
            type: JournalEntryType::Adjustment,
            lines: [
                ['account_id' => $this->cash->id, 'type' => DebitCredit::Debit, 'amount' => 500],
                ['account_id' => $this->revenue->id, 'type' => DebitCredit::Credit, 'amount' => 400],
            ],
        );
    }

    public function test_posting_fewer_than_two_lines_throws(): void
    {
        $this->expectException(UnbalancedJournalEntryException::class);

        $this->service->post(
            entryDate: now(),
            description: 'Bad entry',
            type: JournalEntryType::Adjustment,
            lines: [
                ['account_id' => $this->cash->id, 'type' => DebitCredit::Debit, 'amount' => 500],
            ],
        );
    }

    public function test_posting_against_an_inactive_account_throws(): void
    {
        $inactiveAccount = Account::create([
            'name' => 'Closed Account',
            'account_type' => AccountType::Asset,
            'is_active' => false,
        ]);

        $this->expectException(UnbalancedJournalEntryException::class);

        $this->service->post(
            entryDate: now(),
            description: 'Should not post',
            type: JournalEntryType::Adjustment,
            lines: [
                ['account_id' => $inactiveAccount->id, 'type' => DebitCredit::Debit, 'amount' => 500],
                ['account_id' => $this->revenue->id, 'type' => DebitCredit::Credit, 'amount' => 500],
            ],
        );
    }

    public function test_posting_against_a_nonexistent_account_throws(): void
    {
        $this->expectException(UnbalancedJournalEntryException::class);

        $this->service->post(
            entryDate: now(),
            description: 'Should not post',
            type: JournalEntryType::Adjustment,
            lines: [
                ['account_id' => 999999, 'type' => DebitCredit::Debit, 'amount' => 500],
                ['account_id' => $this->revenue->id, 'type' => DebitCredit::Credit, 'amount' => 500],
            ],
        );
    }

    public function test_journal_entries_are_immutable_after_posting(): void
    {
        $entry = $this->service->post(
            entryDate: now(),
            description: 'Cash sale',
            type: JournalEntryType::Sale,
            lines: [
                ['account_id' => $this->cash->id, 'type' => DebitCredit::Debit, 'amount' => 500],
                ['account_id' => $this->revenue->id, 'type' => DebitCredit::Credit, 'amount' => 500],
            ],
        );

        $this->expectException(ImmutableJournalEntryException::class);

        $entry->description = 'Changed';
        $entry->save();
    }

    public function test_journal_entry_lines_are_immutable_after_posting(): void
    {
        $entry = $this->service->post(
            entryDate: now(),
            description: 'Cash sale',
            type: JournalEntryType::Sale,
            lines: [
                ['account_id' => $this->cash->id, 'type' => DebitCredit::Debit, 'amount' => 500],
                ['account_id' => $this->revenue->id, 'type' => DebitCredit::Credit, 'amount' => 500],
            ],
        );

        $line = $entry->lines->first();

        $this->expectException(ImmutableJournalEntryException::class);

        $line->amount = 999;
        $line->save();
    }
}
