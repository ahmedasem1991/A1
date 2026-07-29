<?php

namespace Tests\Feature\Finance;

use App\Actions\Finance\RecordPaymentAction;
use App\Actions\Finance\RecordSaleAction;
use App\Enums\AccountSubtype;
use App\Enums\AccountType;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Filament\Resources\InvoiceResource\Pages\ListInvoices;
use App\Filament\Resources\InvoiceResource\Pages\ViewInvoice;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InvoiceRefundActionUiTest extends TestCase
{
    use RefreshDatabase;

    private Account $cashAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->cashAccount = Account::create([
            'name' => 'Cash Drawer',
            'account_type' => AccountType::Asset,
            'account_subtype' => AccountSubtype::Cash,
            'is_bank_account' => true,
        ]);

        Account::create([
            'name' => 'Accounts Receivable',
            'account_type' => AccountType::Asset,
            'account_subtype' => AccountSubtype::AccountsReceivable,
        ]);

        Account::create([
            'name' => 'Sales Revenue',
            'account_type' => AccountType::Revenue,
            'account_subtype' => AccountSubtype::SalesRevenue,
        ]);
    }

    private function makePaidInvoice(float $total): Invoice
    {
        $customer = Customer::create(['name' => 'Refund UI Customer']);

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
            amount: $total,
            paymentDate: now(),
            paymentMethod: PaymentMethod::Cash,
            customerId: $customer->id,
            allocations: [['invoice_id' => $invoice->id, 'amount' => $total]],
        );

        return $invoice->fresh();
    }

    public function test_refund_action_is_hidden_on_an_unpaid_invoice(): void
    {
        $customer = Customer::create(['name' => 'Unpaid Customer']);
        $order = Order::create([
            'name' => $customer->name,
            'customer_id' => $customer->id,
            'subtotal' => 200,
            'discount' => 0,
            'total_price' => 200,
            'paid_amount' => 0,
            'remaining_amount' => 200,
            'status' => 'processing',
        ]);
        $invoice = app(RecordSaleAction::class)->handle($order);

        Livewire::test(ListInvoices::class)
            ->assertTableActionHidden('refund', $invoice);
    }

    public function test_refunding_from_the_invoice_list_reopens_the_balance(): void
    {
        $invoice = $this->makePaidInvoice(500);

        Livewire::test(ListInvoices::class)
            ->callTableAction('refund', $invoice, data: [
                'refund_amount' => 200,
                'payment_method' => 'cash',
                'account_id' => $this->cashAccount->id,
            ])
            ->assertHasNoTableActionErrors();

        $invoice->refresh();
        $this->assertEquals(300, $invoice->paid_amount_cache);
        $this->assertEquals(200, $invoice->balance_cache);
        $this->assertEquals(InvoiceStatus::PartiallyPaid, $invoice->status);
    }

    public function test_refund_amount_cannot_exceed_amount_paid(): void
    {
        $invoice = $this->makePaidInvoice(300);

        Livewire::test(ListInvoices::class)
            ->callTableAction('refund', $invoice, data: [
                'refund_amount' => 500,
                'payment_method' => 'cash',
                'account_id' => $this->cashAccount->id,
            ])
            ->assertHasTableActionErrors(['refund_amount' => 'max']);
    }

    public function test_refunding_from_the_invoice_view_page_works(): void
    {
        $invoice = $this->makePaidInvoice(400);

        Livewire::test(ViewInvoice::class, ['record' => $invoice->id])
            ->callAction('refund', data: [
                'refund_amount' => 100,
                'payment_method' => 'cash',
                'account_id' => $this->cashAccount->id,
            ])
            ->assertHasNoActionErrors();

        $this->assertEquals(300, $invoice->fresh()->paid_amount_cache);
    }
}
