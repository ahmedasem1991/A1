<?php

namespace Tests\Feature\Finance;

use App\Actions\Finance\TransferBetweenAccountsAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\Concerns\SeedsChartOfAccounts;
use Tests\TestCase;

class TransferBetweenAccountsActionTest extends TestCase
{
    use RefreshDatabase, SeedsChartOfAccounts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedChartOfAccounts();

        $this->cashAccount->update(['current_balance_cache' => 1000]);
    }

    public function test_transfer_debits_destination_credits_source_and_leaves_total_cash_unchanged(): void
    {
        app(TransferBetweenAccountsAction::class)->handle(
            from: $this->cashAccount,
            to: $this->bankAccount,
            amount: 400,
            transferDate: now(),
        );

        $this->assertEquals(600, $this->cashAccount->fresh()->current_balance_cache);
        $this->assertEquals(400, $this->bankAccount->fresh()->current_balance_cache);

        $totalCash = $this->cashAccount->fresh()->current_balance_cache + $this->bankAccount->fresh()->current_balance_cache;
        $this->assertEquals(1000, $totalCash);
    }
}
