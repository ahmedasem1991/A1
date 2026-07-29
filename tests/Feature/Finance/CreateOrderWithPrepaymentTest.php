<?php

namespace Tests\Feature\Finance;

use App\Actions\Finance\RecordPaymentAction;
use App\Actions\Finance\RecordSaleAction;
use App\Enums\AccountSubtype;
use App\Enums\AccountType;
use App\Enums\PaymentMethod;
use App\Filament\Resources\OrderResource\Pages\CreateOrder;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CreateOrderWithPrepaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['is_admin' => true]));

        Account::create([
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

    public function test_order_form_requires_a_customer_to_be_selected(): void
    {
        Livewire::test(CreateOrder::class)
            ->fillForm(['customer_id' => null])
            ->call('create')
            ->assertHasFormErrors(['customer_id' => 'required']);
    }

    public function test_selecting_a_customer_populates_the_order_name_and_customer_id(): void
    {
        $customer = Customer::create(['name' => 'Regression Customer']);

        Livewire::test(CreateOrder::class)
            ->fillForm([
                'customer_id' => $customer->id,
                'subtotal' => 0,
                'total_price' => 0,
                'remaining_amount' => 0,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $order = Order::query()->where('customer_id', $customer->id)->firstOrFail();

        $this->assertEquals('Regression Customer', $order->name);
    }

    public function test_recording_a_sale_no_longer_crashes_now_that_orders_always_have_a_customer(): void
    {
        // This reproduces the exact path that used to fail with
        // "Column 'customer_id' cannot be null" before the Order form
        // gained a required Customer select: CreateOrder::afterCreate()
        // calling RecordSaleAction on a freshly created, customer-linked Order.
        $customer = Customer::create(['name' => 'Prepaid Customer']);

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

        $this->assertDatabaseHas(Invoice::class, ['customer_id' => $customer->id, 'total_amount' => 500]);

        $cashAccount = Account::query()->where('account_subtype', AccountSubtype::Cash)->firstOrFail();

        app(RecordPaymentAction::class)->handle(
            accountId: $cashAccount->id,
            amount: 200,
            paymentDate: now(),
            paymentMethod: PaymentMethod::Cash,
            customerId: $customer->id,
            allocations: [['invoice_id' => $invoice->id, 'amount' => 200]],
        );

        $this->assertDatabaseHas(Payment::class, ['customer_id' => $customer->id, 'amount' => 200]);
    }
}
