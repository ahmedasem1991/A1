<?php

namespace Tests\Feature\Finance;

use App\Actions\Finance\RecordPaymentAction;
use App\Actions\Finance\RecordRefundAction;
use App\Actions\Finance\RecordSaleAction;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Feature\Finance\Concerns\SeedsChartOfAccounts;
use Tests\TestCase;

class RecordRefundActionTest extends TestCase
{
    use RefreshDatabase, SeedsChartOfAccounts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedChartOfAccounts();
    }

    private function makePaidInvoice(float $total, float $paid): Invoice
    {
        $customer = Customer::create(['name' => 'Refund Customer']);

        $order = Order::create([
            'name' => $customer->name,
            'customer_id' => $customer->id,
            'subtotal' => $total,
            'discount' => 0,
            'total_price' => $total,
            'paid_amount' => 0,
            'remaining_amount' => $total,
            'status' => 'processing',
        ]);

        $invoice = app(RecordSaleAction::class)->handle($order);

        app(RecordPaymentAction::class)->handle(
            accountId: $this->cashAccount->id,
            amount: $paid,
            paymentDate: now(),
            paymentMethod: PaymentMethod::Cash,
            customerId: $customer->id,
            allocations: [['invoice_id' => $invoice->id, 'amount' => $paid]],
        );

        return $invoice->fresh();
    }

    public function test_a_partial_refund_reopens_the_invoice_balance(): void
    {
        $invoice = $this->makePaidInvoice(500, 500);
        $this->assertEquals(InvoiceStatus::Paid, $invoice->status);

        app(RecordRefundAction::class)->handle(
            invoice: $invoice,
            accountId: $this->cashAccount->id,
            amount: 100,
            refundDate: now(),
            paymentMethod: PaymentMethod::Cash,
        );

        $invoice->refresh();
        $this->assertEquals(400, $invoice->paid_amount_cache);
        $this->assertEquals(100, $invoice->balance_cache);
        $this->assertEquals(InvoiceStatus::PartiallyPaid, $invoice->status);
    }

    public function test_a_full_refund_returns_the_invoice_to_unpaid(): void
    {
        $invoice = $this->makePaidInvoice(300, 300);

        app(RecordRefundAction::class)->handle(
            invoice: $invoice,
            accountId: $this->cashAccount->id,
            amount: 300,
            refundDate: now(),
            paymentMethod: PaymentMethod::Cash,
        );

        $invoice->refresh();
        $this->assertEquals(0, $invoice->paid_amount_cache);
        $this->assertEquals(300, $invoice->balance_cache);
        $this->assertEquals(InvoiceStatus::Unpaid, $invoice->status);
    }

    public function test_refund_creates_an_outbound_refund_payment(): void
    {
        $invoice = $this->makePaidInvoice(500, 500);

        app(RecordRefundAction::class)->handle(
            invoice: $invoice,
            accountId: $this->cashAccount->id,
            amount: 150,
            refundDate: now(),
            paymentMethod: PaymentMethod::Cash,
        );

        $this->assertDatabaseHas(Payment::class, [
            'amount' => 150,
            'direction' => PaymentDirection::Outbound->value,
            'type' => PaymentType::Refund->value,
            'customer_id' => $invoice->customer_id,
        ]);
    }

    public function test_refund_correctly_debits_ar_and_credits_cash(): void
    {
        $invoice = $this->makePaidInvoice(500, 500);
        $cashBefore = $this->cashAccount->fresh()->current_balance_cache;
        $arBefore = $this->receivableAccount->fresh()->current_balance_cache;

        app(RecordRefundAction::class)->handle(
            invoice: $invoice,
            accountId: $this->cashAccount->id,
            amount: 200,
            refundDate: now(),
            paymentMethod: PaymentMethod::Cash,
        );

        $this->assertEquals((float) $cashBefore - 200, (float) $this->cashAccount->fresh()->current_balance_cache);
        $this->assertEquals((float) $arBefore + 200, (float) $this->receivableAccount->fresh()->current_balance_cache);
    }

    public function test_refund_cannot_exceed_amount_paid(): void
    {
        $invoice = $this->makePaidInvoice(500, 200);

        $this->expectException(RuntimeException::class);

        app(RecordRefundAction::class)->handle(
            invoice: $invoice,
            accountId: $this->cashAccount->id,
            amount: 300,
            refundDate: now(),
            paymentMethod: PaymentMethod::Cash,
        );
    }

    public function test_refund_updates_customer_outstanding_balance(): void
    {
        $invoice = $this->makePaidInvoice(500, 500);

        app(RecordRefundAction::class)->handle(
            invoice: $invoice,
            accountId: $this->cashAccount->id,
            amount: 100,
            refundDate: now(),
            paymentMethod: PaymentMethod::Cash,
        );

        $this->assertEquals(100, $invoice->customer->fresh()->current_balance_cache);
    }

    public function test_refund_syncs_the_linked_order(): void
    {
        $invoice = $this->makePaidInvoice(500, 500);
        $order = $invoice->order;
        $this->assertEquals(OrderStatus::Completed, $order->fresh()->status);

        app(RecordRefundAction::class)->handle(
            invoice: $invoice,
            accountId: $this->cashAccount->id,
            amount: 100,
            refundDate: now(),
            paymentMethod: PaymentMethod::Cash,
        );

        $order->refresh();
        $this->assertEquals(400, $order->paid_amount);
        $this->assertEquals(100, $order->remaining_amount);
        $this->assertEquals(OrderStatus::Processing, $order->status);
    }
}
