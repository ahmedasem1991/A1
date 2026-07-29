<?php

namespace Tests\Feature\Finance;

use App\Enums\AccountSubtype;
use App\Enums\AccountType;
use App\Filament\Resources\AccountResource\Pages\ListAccounts;
use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TransferFundsActionUiTest extends TestCase
{
    use RefreshDatabase;

    private Account $cashAccount;

    private Account $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->cashAccount = Account::create([
            'name' => 'Cash Drawer',
            'account_type' => AccountType::Asset,
            'account_subtype' => AccountSubtype::Cash,
            'is_bank_account' => true,
            'is_active' => true,
            'current_balance_cache' => 1000,
        ]);

        $this->bankAccount = Account::create([
            'name' => 'Main Bank',
            'account_type' => AccountType::Asset,
            'account_subtype' => AccountSubtype::Bank,
            'is_bank_account' => true,
            'is_active' => true,
        ]);
    }

    public function test_transferring_funds_moves_balance_between_accounts(): void
    {
        Livewire::test(ListAccounts::class)
            ->callAction('transfer_funds', data: [
                'from_account_id' => $this->cashAccount->id,
                'to_account_id' => $this->bankAccount->id,
                'amount' => 400,
                'transfer_date' => now()->toDateString(),
            ])
            ->assertHasNoActionErrors();

        $this->assertEquals(600, $this->cashAccount->fresh()->current_balance_cache);
        $this->assertEquals(400, $this->bankAccount->fresh()->current_balance_cache);
    }

    public function test_transfer_requires_different_source_and_destination(): void
    {
        Livewire::test(ListAccounts::class)
            ->callAction('transfer_funds', data: [
                'from_account_id' => $this->cashAccount->id,
                'to_account_id' => $this->cashAccount->id,
                'amount' => 100,
                'transfer_date' => now()->toDateString(),
            ])
            ->assertHasActionErrors(['to_account_id' => 'different']);

        $this->assertEquals(1000, $this->cashAccount->fresh()->current_balance_cache);
    }
}
