<?php

namespace Tests\Feature\Finance;

use App\Actions\Finance\RecordExpenseAction;
use App\Actions\Finance\RecordPaymentAction;
use App\Actions\Finance\RecordSaleAction;
use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\DailyFinancialSummary;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\Concerns\SeedsChartOfAccounts;
use Tests\TestCase;

class RefreshDailyFinancialSummaryCommandTest extends TestCase
{
    use RefreshDatabase, SeedsChartOfAccounts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedChartOfAccounts();
    }

    public function test_it_produces_correct_aggregates_for_a_fixture_day(): void
    {
        $fixtureDate = now();

        $customer = Customer::create(['name' => 'Fixture Customer']);

        $order = Order::create([
            'name' => $customer->name,
            'customer_id' => $customer->id,
            'subtotal' => 500,
            'discount' => 0,
            'total_price' => 500,
            'paid_amount' => 0,
            'remaining_amount' => 500,
            'status' => 'processing',
        ]);

        $invoice = app(RecordSaleAction::class)->handle($order);

        app(RecordPaymentAction::class)->handle(
            accountId: $this->cashAccount->id,
            amount: 200,
            paymentDate: $fixtureDate,
            paymentMethod: PaymentMethod::Cash,
            customerId: $customer->id,
            allocations: [['invoice_id' => $invoice->id, 'amount' => 200]],
        );

        app(RecordExpenseAction::class)->handle(
            expenseAccountId: $this->expenseAccount->id,
            paidFromAccountId: $this->cashAccount->id,
            amount: 50,
            expenseDate: $fixtureDate,
            description: 'Fixture expense',
        );

        $this->artisan('finance:refresh-daily-summary', ['date' => $fixtureDate->toDateString()])
            ->assertSuccessful();

        $summary = DailyFinancialSummary::query()->where('summary_date', $fixtureDate->toDateString())->firstOrFail();

        $this->assertEquals(500, $summary->total_sales);
        $this->assertEquals(500, $summary->total_income);
        $this->assertEquals(200, $summary->payments_received);
        $this->assertEquals(50, $summary->total_expenses);
        $this->assertEquals(50, $summary->payments_made);
        $this->assertEquals(450, $summary->net_profit);
    }
}
