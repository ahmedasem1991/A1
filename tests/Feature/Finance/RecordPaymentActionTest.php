<?php

namespace Tests\Feature\Finance;

use App\Actions\Finance\RecordPaymentAction;
use App\Actions\Finance\RecordSaleAction;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Feature\Finance\Concerns\SeedsChartOfAccounts;
use Tests\TestCase;

class RecordPaymentActionTest extends TestCase
{
    use RefreshDatabase, SeedsChartOfAccounts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedChartOfAccounts();
    }

    private function makeInvoice(Customer $customer, float $total): Invoice
    {
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

        return app(RecordSaleAction::class)->handle($order);
    }

    public function test_single_payment_allocated_across_two_invoices_splits_correctly(): void
    {
        $customer = Customer::create(['name' => 'Jane Doe']);

        $invoiceA = $this->makeInvoice($customer, 400);
        $invoiceB = $this->makeInvoice($customer, 600);

        app(RecordPaymentAction::class)->handle(
            accountId: $this->cashAccount->id,
            amount: 1000,
            paymentDate: now(),
            paymentMethod: PaymentMethod::Cash,
            customerId: $customer->id,
            allocations: [
                ['invoice_id' => $invoiceA->id, 'amount' => 400],
                ['invoice_id' => $invoiceB->id, 'amount' => 600],
            ],
        );

        $this->assertEquals(0, $invoiceA->fresh()->balance_cache);
        $this->assertEquals(InvoiceStatus::Paid, $invoiceA->fresh()->status);
        $this->assertEquals(0, $invoiceB->fresh()->balance_cache);
        $this->assertEquals(InvoiceStatus::Paid, $invoiceB->fresh()->status);
        $this->assertEquals(1000, $this->cashAccount->fresh()->current_balance_cache);
    }

    public function test_partial_payment_across_three_installments_leaves_invoice_fully_paid_and_remaining_zero(): void
    {
        $customer = Customer::create(['name' => 'John Smith']);
        $invoice = $this->makeInvoice($customer, 1000);
        $order = $invoice->order;

        $action = app(RecordPaymentAction::class);

        $action->handle(
            accountId: $this->cashAccount->id,
            amount: 400,
            paymentDate: now(),
            paymentMethod: PaymentMethod::Cash,
            customerId: $customer->id,
            allocations: [['invoice_id' => $invoice->id, 'amount' => 400]],
        );

        $this->assertEquals(600, $invoice->fresh()->balance_cache);
        $this->assertEquals(InvoiceStatus::PartiallyPaid, $invoice->fresh()->status);
        $this->assertEquals(600, $order->fresh()->remaining_amount);

        $action->handle(
            accountId: $this->cashAccount->id,
            amount: 300,
            paymentDate: now(),
            paymentMethod: PaymentMethod::Cash,
            customerId: $customer->id,
            allocations: [['invoice_id' => $invoice->id, 'amount' => 300]],
        );

        $this->assertEquals(300, $invoice->fresh()->balance_cache);

        $action->handle(
            accountId: $this->cashAccount->id,
            amount: 300,
            paymentDate: now(),
            paymentMethod: PaymentMethod::Cash,
            customerId: $customer->id,
            allocations: [['invoice_id' => $invoice->id, 'amount' => 300]],
        );

        $this->assertEquals(0, $invoice->fresh()->balance_cache);
        $this->assertEquals(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertEquals(0, $order->fresh()->remaining_amount);
        $this->assertEquals(1000, $order->fresh()->paid_amount);
        $this->assertEquals(OrderStatus::Completed, $order->fresh()->status);
    }

    public function test_updates_order_paid_amount_and_remaining_amount_cache(): void
    {
        $customer = Customer::create(['name' => 'Amr Ali']);
        $invoice = $this->makeInvoice($customer, 500);
        $order = $invoice->order;

        app(RecordPaymentAction::class)->handle(
            accountId: $this->cashAccount->id,
            amount: 200,
            paymentDate: now(),
            paymentMethod: PaymentMethod::Cash,
            customerId: $customer->id,
            allocations: [['invoice_id' => $invoice->id, 'amount' => 200]],
        );

        $this->assertEquals(200, $order->fresh()->paid_amount);
        $this->assertEquals(300, $order->fresh()->remaining_amount);
        $this->assertEquals(OrderStatus::Processing, $order->fresh()->status);
    }

    public function test_allocating_more_than_payment_amount_throws(): void
    {
        $customer = Customer::create(['name' => 'Sara']);
        $invoice = $this->makeInvoice($customer, 500);

        $this->expectException(RuntimeException::class);

        app(RecordPaymentAction::class)->handle(
            accountId: $this->cashAccount->id,
            amount: 100,
            paymentDate: now(),
            paymentMethod: PaymentMethod::Cash,
            customerId: $customer->id,
            allocations: [['invoice_id' => $invoice->id, 'amount' => 500]],
        );
    }

    public function test_allocation_exceeding_invoice_balance_throws_even_when_within_payment_amount(): void
    {
        $customer = Customer::create(['name' => 'Overallocation Customer']);
        $invoice = $this->makeInvoice($customer, 500);

        app(RecordPaymentAction::class)->handle(
            accountId: $this->cashAccount->id,
            amount: 300,
            paymentDate: now(),
            paymentMethod: PaymentMethod::Cash,
            customerId: $customer->id,
            allocations: [['invoice_id' => $invoice->id, 'amount' => 300]],
        );

        $this->assertEquals(200, $invoice->fresh()->balance_cache);

        $this->expectException(RuntimeException::class);

        app(RecordPaymentAction::class)->handle(
            accountId: $this->cashAccount->id,
            amount: 300,
            paymentDate: now(),
            paymentMethod: PaymentMethod::Cash,
            customerId: $customer->id,
            allocations: [['invoice_id' => $invoice->id, 'amount' => 300]],
        );
    }

    public function test_customer_balance_cache_reflects_outstanding_invoices(): void
    {
        $customer = Customer::create(['name' => 'Mona']);
        $invoice = $this->makeInvoice($customer, 500);

        app(RecordPaymentAction::class)->handle(
            accountId: $this->cashAccount->id,
            amount: 200,
            paymentDate: now(),
            paymentMethod: PaymentMethod::Cash,
            customerId: $customer->id,
            allocations: [['invoice_id' => $invoice->id, 'amount' => 200]],
        );

        $this->assertEquals(300, $customer->fresh()->current_balance_cache);
    }
}
