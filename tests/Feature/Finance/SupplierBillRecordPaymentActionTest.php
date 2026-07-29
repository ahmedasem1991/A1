<?php

namespace Tests\Feature\Finance;

use App\Enums\AccountSubtype;
use App\Enums\AccountType;
use App\Enums\SupplierBillStatus;
use App\Filament\Resources\SupplierBillResource\Pages\ListSupplierBills;
use App\Filament\Resources\SupplierBillResource\Pages\ViewSupplierBill;
use App\Models\Account;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SupplierBillRecordPaymentActionTest extends TestCase
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
            'name' => 'Accounts Payable',
            'account_type' => AccountType::Liability,
            'account_subtype' => AccountSubtype::AccountsPayable,
        ]);
    }

    private function makeBill(float $total): SupplierBill
    {
        $supplier = Supplier::create(['name' => 'Bill Payment Supplier']);

        return SupplierBill::create([
            'bill_number' => 'BILL-'.uniqid(),
            'supplier_id' => $supplier->id,
            'bill_date' => now(),
            'total_amount' => $total,
            'paid_amount_cache' => 0,
            'balance_cache' => $total,
            'status' => SupplierBillStatus::Unpaid,
        ]);
    }

    public function test_recording_a_partial_payment_from_the_bill_list_updates_balances(): void
    {
        $bill = $this->makeBill(500);

        Livewire::test(ListSupplierBills::class)
            ->callTableAction('record_payment', $bill, data: [
                'payment' => 200,
                'payment_method' => 'cash',
                'account_id' => $this->cashAccount->id,
            ])
            ->assertHasNoTableActionErrors();

        $bill->refresh();

        $this->assertEquals(200, $bill->paid_amount_cache);
        $this->assertEquals(300, $bill->balance_cache);
        $this->assertEquals(SupplierBillStatus::PartiallyPaid, $bill->status);
        $this->assertDatabaseHas(Payment::class, ['amount' => 200, 'supplier_id' => $bill->supplier_id, 'direction' => 'outbound']);
    }

    public function test_recording_full_payment_marks_bill_paid(): void
    {
        $bill = $this->makeBill(300);

        Livewire::test(ListSupplierBills::class)
            ->callTableAction('record_payment', $bill, data: [
                'payment' => 300,
                'payment_method' => 'cash',
                'account_id' => $this->cashAccount->id,
            ])
            ->assertHasNoTableActionErrors();

        $bill->refresh();

        $this->assertEquals(0, $bill->balance_cache);
        $this->assertEquals(SupplierBillStatus::Paid, $bill->status);
        $this->assertEquals(0, $bill->supplier->fresh()->current_balance_cache);
    }

    public function test_record_payment_action_is_hidden_once_bill_is_fully_paid(): void
    {
        $bill = $this->makeBill(100);
        $bill->update(['paid_amount_cache' => 100, 'balance_cache' => 0, 'status' => SupplierBillStatus::Paid]);

        Livewire::test(ListSupplierBills::class)
            ->assertTableActionHidden('record_payment', $bill);
    }

    public function test_payment_amount_cannot_exceed_remaining_balance(): void
    {
        $bill = $this->makeBill(200);

        Livewire::test(ListSupplierBills::class)
            ->callTableAction('record_payment', $bill, data: [
                'payment' => 500,
                'payment_method' => 'cash',
                'account_id' => $this->cashAccount->id,
            ])
            ->assertHasTableActionErrors(['payment' => 'max']);
    }

    public function test_recording_a_payment_from_the_bill_view_page_works(): void
    {
        $bill = $this->makeBill(400);

        Livewire::test(ViewSupplierBill::class, ['record' => $bill->id])
            ->callAction('record_payment', data: [
                'payment' => 150,
                'payment_method' => 'cash',
                'account_id' => $this->cashAccount->id,
            ])
            ->assertHasNoActionErrors();

        $this->assertEquals(150, $bill->fresh()->paid_amount_cache);
    }
}
