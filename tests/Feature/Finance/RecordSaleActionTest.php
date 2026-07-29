<?php

namespace Tests\Feature\Finance;

use App\Actions\Finance\RecordSaleAction;
use App\Enums\DebitCredit;
use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Feature\Finance\Concerns\SeedsChartOfAccounts;
use Tests\TestCase;

class RecordSaleActionTest extends TestCase
{
    use RefreshDatabase, SeedsChartOfAccounts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedChartOfAccounts();
    }

    public function test_it_creates_an_invoice_with_correct_ar_and_revenue_lines(): void
    {
        $customer = Customer::create(['name' => 'Jane Doe']);

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

        $this->assertEquals(500, $invoice->total_amount);
        $this->assertEquals(InvoiceStatus::Unpaid, $invoice->status);
        $this->assertEquals($customer->id, $invoice->customer_id);

        $this->assertDatabaseHas('journal_entry_lines', [
            'account_id' => $this->receivableAccount->id,
            'type' => DebitCredit::Debit->value,
            'amount' => 500,
        ]);
        $this->assertDatabaseHas('journal_entry_lines', [
            'account_id' => $this->revenueAccount->id,
            'type' => DebitCredit::Credit->value,
            'amount' => 500,
        ]);

        $this->assertEquals(500, $this->receivableAccount->fresh()->current_balance_cache);
        $this->assertEquals(500, $this->revenueAccount->fresh()->current_balance_cache);
    }

    public function test_recording_a_sale_twice_for_the_same_order_throws_instead_of_crashing(): void
    {
        $customer = Customer::create(['name' => 'Duplicate Sale Customer']);

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

        app(RecordSaleAction::class)->handle($order);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Order #{$order->id} already has an invoice.");

        app(RecordSaleAction::class)->handle($order);
    }
}
