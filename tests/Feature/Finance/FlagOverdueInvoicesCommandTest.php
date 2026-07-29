<?php

namespace Tests\Feature\Finance;

use App\Actions\Finance\RecordSaleAction;
use App\Enums\InvoiceStatus;
use App\Enums\SupplierBillStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Supplier;
use App\Models\SupplierBill;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\Concerns\SeedsChartOfAccounts;
use Tests\TestCase;

class FlagOverdueInvoicesCommandTest extends TestCase
{
    use RefreshDatabase, SeedsChartOfAccounts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedChartOfAccounts();
    }

    private function makeInvoice(float $total, DateTimeInterface $dueDate): Invoice
    {
        $customer = Customer::create(['name' => 'Overdue Customer']);

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

        return app(RecordSaleAction::class)->handle($order, $dueDate);
    }

    public function test_it_flags_unpaid_invoices_past_due_date_as_overdue(): void
    {
        $invoice = $this->makeInvoice(500, now()->subDays(3));

        $this->artisan('finance:flag-overdue')->assertSuccessful();

        $this->assertEquals(InvoiceStatus::Overdue, $invoice->fresh()->status);
    }

    public function test_it_does_not_flag_invoices_not_yet_due(): void
    {
        $invoice = $this->makeInvoice(500, now()->addDays(3));

        $this->artisan('finance:flag-overdue')->assertSuccessful();

        $this->assertEquals(InvoiceStatus::Unpaid, $invoice->fresh()->status);
    }

    public function test_it_does_not_flag_fully_paid_invoices(): void
    {
        $invoice = $this->makeInvoice(500, now()->subDays(3));
        $invoice->update(['balance_cache' => 0, 'status' => InvoiceStatus::Paid]);

        $this->artisan('finance:flag-overdue')->assertSuccessful();

        $this->assertEquals(InvoiceStatus::Paid, $invoice->fresh()->status);
    }

    public function test_it_flags_overdue_supplier_bills(): void
    {
        $supplier = Supplier::create(['name' => 'Overdue Supplier']);

        $bill = SupplierBill::create([
            'bill_number' => 'BILL-000001',
            'supplier_id' => $supplier->id,
            'bill_date' => now()->subDays(20),
            'due_date' => now()->subDays(5),
            'total_amount' => 300,
            'paid_amount_cache' => 0,
            'balance_cache' => 300,
            'status' => SupplierBillStatus::Unpaid,
        ]);

        $this->artisan('finance:flag-overdue')->assertSuccessful();

        $this->assertEquals(SupplierBillStatus::Overdue, $bill->fresh()->status);
    }

    public function test_it_reverts_a_bill_to_partially_paid_when_due_date_is_pushed_out(): void
    {
        $supplier = Supplier::create(['name' => 'Recovered Supplier']);

        $bill = SupplierBill::create([
            'bill_number' => 'BILL-000002',
            'supplier_id' => $supplier->id,
            'bill_date' => now()->subDays(20),
            'due_date' => now()->addDays(5),
            'total_amount' => 300,
            'paid_amount_cache' => 100,
            'balance_cache' => 200,
            'status' => SupplierBillStatus::Overdue,
        ]);

        $this->artisan('finance:flag-overdue')->assertSuccessful();

        $this->assertEquals(SupplierBillStatus::PartiallyPaid, $bill->fresh()->status);
    }

    public function test_it_reverts_an_invoice_to_partially_paid_when_due_date_is_pushed_out(): void
    {
        $invoice = $this->makeInvoice(500, now()->subDays(3));
        $invoice->update([
            'due_date' => now()->addDays(5),
            'paid_amount_cache' => 200,
            'balance_cache' => 300,
            'status' => InvoiceStatus::Overdue,
        ]);

        $this->artisan('finance:flag-overdue')->assertSuccessful();

        $this->assertEquals(InvoiceStatus::PartiallyPaid, $invoice->fresh()->status);
    }

    public function test_it_reverts_an_invoice_to_unpaid_when_due_date_is_pushed_out_and_nothing_was_paid(): void
    {
        $invoice = $this->makeInvoice(500, now()->subDays(3));
        $invoice->update([
            'due_date' => now()->addDays(5),
            'status' => InvoiceStatus::Overdue,
        ]);

        $this->artisan('finance:flag-overdue')->assertSuccessful();

        $this->assertEquals(InvoiceStatus::Unpaid, $invoice->fresh()->status);
    }
}
