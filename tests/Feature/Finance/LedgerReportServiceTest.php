<?php

namespace Tests\Feature\Finance;

use App\Actions\Finance\RecordPaymentAction;
use App\Actions\Finance\RecordSaleAction;
use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\Order;
use App\Services\LedgerReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\Concerns\SeedsChartOfAccounts;
use Tests\TestCase;

class LedgerReportServiceTest extends TestCase
{
    use RefreshDatabase, SeedsChartOfAccounts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedChartOfAccounts();
    }

    public function test_customer_ledger_running_balance_matches_expected_table_exactly(): void
    {
        $customer = Customer::create(['name' => 'Worked Example Customer']);

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

        $paymentAction = app(RecordPaymentAction::class);

        $paymentAction->handle(
            accountId: $this->cashAccount->id,
            amount: 200,
            paymentDate: now()->addDay(),
            paymentMethod: PaymentMethod::Cash,
            customerId: $customer->id,
            allocations: [['invoice_id' => $invoice->id, 'amount' => 200]],
        );

        $paymentAction->handle(
            accountId: $this->cashAccount->id,
            amount: 200,
            paymentDate: now()->addDays(2),
            paymentMethod: PaymentMethod::Cash,
            customerId: $customer->id,
            allocations: [['invoice_id' => $invoice->id, 'amount' => 200]],
        );

        $paymentAction->handle(
            accountId: $this->cashAccount->id,
            amount: 100,
            paymentDate: now()->addDays(3),
            paymentMethod: PaymentMethod::Cash,
            customerId: $customer->id,
            allocations: [['invoice_id' => $invoice->id, 'amount' => 100]],
        );

        $ledger = app(LedgerReportService::class)->customerLedger($customer);

        $balances = $ledger->pluck('balance')->all();

        $this->assertEquals([500, 300, 100, 0], $balances);
        $this->assertEquals(0, $invoice->fresh()->balance_cache);
    }

    public function test_a_to_bound_at_end_of_day_includes_transactions_later_that_same_day(): void
    {
        $customer = Customer::create(['name' => 'Same Day Customer']);

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
            amount: 500,
            paymentDate: now()->startOfDay()->addHours(23)->addMinutes(59),
            paymentMethod: PaymentMethod::Cash,
            customerId: $customer->id,
            allocations: [['invoice_id' => $invoice->id, 'amount' => 500]],
        );

        $ledger = app(LedgerReportService::class)->customerLedger(
            $customer,
            from: now()->startOfDay(),
            to: now()->endOfDay(),
        );

        $this->assertCount(2, $ledger);
        $this->assertEquals(0, $ledger->last()['balance']);
    }
}
