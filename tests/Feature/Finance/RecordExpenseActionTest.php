<?php

namespace Tests\Feature\Finance;

use App\Actions\Finance\RecordExpenseAction;
use App\Enums\DebitCredit;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\Concerns\SeedsChartOfAccounts;
use Tests\TestCase;

class RecordExpenseActionTest extends TestCase
{
    use RefreshDatabase, SeedsChartOfAccounts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedChartOfAccounts();
    }

    public function test_it_posts_debit_expense_credit_cash(): void
    {
        $expense = app(RecordExpenseAction::class)->handle(
            expenseAccountId: $this->expenseAccount->id,
            paidFromAccountId: $this->cashAccount->id,
            amount: 150,
            expenseDate: now(),
            description: 'Office Supplies',
        );

        $this->assertEquals(150, $expense->amount);

        $this->assertDatabaseHas('journal_entry_lines', [
            'account_id' => $this->expenseAccount->id,
            'type' => DebitCredit::Debit->value,
            'amount' => 150,
        ]);
        $this->assertDatabaseHas('journal_entry_lines', [
            'account_id' => $this->cashAccount->id,
            'type' => DebitCredit::Credit->value,
            'amount' => 150,
        ]);

        $this->assertEquals(150, $this->expenseAccount->fresh()->current_balance_cache);
        $this->assertEquals(-150, $this->cashAccount->fresh()->current_balance_cache);
    }

    public function test_an_expense_with_a_supplier_does_not_affect_the_supplier_outstanding_balance(): void
    {
        $supplier = Supplier::create(['name' => 'Cash-Basis Supplier']);

        app(RecordExpenseAction::class)->handle(
            expenseAccountId: $this->expenseAccount->id,
            paidFromAccountId: $this->cashAccount->id,
            amount: 75,
            expenseDate: now(),
            description: 'One-off cash purchase',
            supplierId: $supplier->id,
        );

        $this->assertEquals(0, $supplier->fresh()->current_balance_cache);
    }
}
