<?php

namespace Tests\Feature\Finance;

use App\Actions\Finance\RecordSaleAction;
use App\Enums\AccountSubtype;
use App\Enums\AccountType;
use App\Enums\InvoiceStatus;
use App\Filament\Resources\InvoiceResource\Pages\ListInvoices;
use App\Filament\Resources\InvoiceResource\Pages\ViewInvoice;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InvoiceRecordPaymentActionTest extends TestCase
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
        ]);

        $this->bankAccount = Account::create([
            'name' => 'Main Bank',
            'account_type' => AccountType::Asset,
            'account_subtype' => AccountSubtype::Bank,
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

    private function makeInvoice(float $total): Invoice
    {
        $customer = Customer::create(['name' => 'Invoice Payment Customer']);

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

    public function test_recording_a_partial_payment_from_the_invoice_list_updates_balances(): void
    {
        $invoice = $this->makeInvoice(500);

        Livewire::test(ListInvoices::class)
            ->callTableAction('record_payment', $invoice, data: [
                'payment' => 200,
                'payment_method' => 'cash',
                'account_id' => $this->cashAccount->id,
            ])
            ->assertHasNoTableActionErrors();

        $invoice->refresh();

        $this->assertEquals(200, $invoice->paid_amount_cache);
        $this->assertEquals(300, $invoice->balance_cache);
        $this->assertEquals(InvoiceStatus::PartiallyPaid, $invoice->status);
        $this->assertDatabaseHas(Payment::class, ['amount' => 200, 'customer_id' => $invoice->customer_id]);
    }

    public function test_recording_full_payment_marks_invoice_paid(): void
    {
        $invoice = $this->makeInvoice(300);

        Livewire::test(ListInvoices::class)
            ->callTableAction('record_payment', $invoice, data: [
                'payment' => 300,
                'payment_method' => 'cash',
                'account_id' => $this->cashAccount->id,
            ])
            ->assertHasNoTableActionErrors();

        $invoice->refresh();

        $this->assertEquals(0, $invoice->balance_cache);
        $this->assertEquals(InvoiceStatus::Paid, $invoice->status);
    }

    public function test_record_payment_action_is_hidden_once_invoice_is_fully_paid(): void
    {
        $invoice = $this->makeInvoice(100);
        $invoice->update(['paid_amount_cache' => 100, 'balance_cache' => 0, 'status' => InvoiceStatus::Paid]);

        Livewire::test(ListInvoices::class)
            ->assertTableActionHidden('record_payment', $invoice);
    }

    public function test_payment_amount_cannot_exceed_remaining_balance(): void
    {
        $invoice = $this->makeInvoice(200);

        Livewire::test(ListInvoices::class)
            ->callTableAction('record_payment', $invoice, data: [
                'payment' => 500,
                'payment_method' => 'cash',
                'account_id' => $this->cashAccount->id,
            ])
            ->assertHasTableActionErrors(['payment' => 'max']);
    }

    public function test_recording_a_payment_from_the_invoice_view_page_works(): void
    {
        $invoice = $this->makeInvoice(400);

        Livewire::test(ViewInvoice::class, ['record' => $invoice->id])
            ->callAction('record_payment', data: [
                'payment' => 150,
                'payment_method' => 'cash',
                'account_id' => $this->cashAccount->id,
            ])
            ->assertHasNoActionErrors();

        $this->assertEquals(150, $invoice->fresh()->paid_amount_cache);
    }

    public function test_account_field_defaults_to_cash_drawer_when_payment_method_is_cash(): void
    {
        $invoice = $this->makeInvoice(500);

        Livewire::test(ListInvoices::class)
            ->mountTableAction('record_payment', $invoice)
            ->setTableActionData(['payment_method' => 'cash'])
            ->assertTableActionDataSet(['account_id' => $this->cashAccount->id]);
    }

    public function test_account_field_switches_to_main_bank_when_payment_method_is_instapay(): void
    {
        $invoice = $this->makeInvoice(500);

        Livewire::test(ListInvoices::class)
            ->mountTableAction('record_payment', $invoice)
            ->setTableActionData(['payment_method' => 'instapay'])
            ->assertTableActionDataSet(['account_id' => $this->bankAccount->id]);
    }

    public function test_recording_an_instapay_payment_posts_against_main_bank(): void
    {
        $invoice = $this->makeInvoice(500);

        Livewire::test(ListInvoices::class)
            ->callTableAction('record_payment', $invoice, data: [
                'payment' => 500,
                'payment_method' => 'instapay',
                'account_id' => $this->bankAccount->id,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas(Payment::class, [
            'amount' => 500,
            'payment_method' => 'instapay',
            'account_id' => $this->bankAccount->id,
        ]);
        $this->assertEquals(500, $this->bankAccount->fresh()->current_balance_cache);
        $this->assertEquals(0, $this->cashAccount->fresh()->current_balance_cache);
    }
}
