<?php

namespace Tests\Feature\Finance;

use App\Actions\Finance\RecordSupplierPaymentAction;
use App\Enums\PaymentMethod;
use App\Enums\SupplierBillStatus;
use App\Models\Supplier;
use App\Models\SupplierBill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Feature\Finance\Concerns\SeedsChartOfAccounts;
use Tests\TestCase;

class RecordSupplierPaymentActionTest extends TestCase
{
    use RefreshDatabase, SeedsChartOfAccounts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedChartOfAccounts();
    }

    private function makeBill(Supplier $supplier, float $total): SupplierBill
    {
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

    public function test_single_payment_allocated_across_two_bills_splits_correctly(): void
    {
        $supplier = Supplier::create(['name' => 'Multi Bill Supplier']);

        $billA = $this->makeBill($supplier, 400);
        $billB = $this->makeBill($supplier, 600);

        app(RecordSupplierPaymentAction::class)->handle(
            accountId: $this->cashAccount->id,
            amount: 1000,
            paymentDate: now(),
            paymentMethod: PaymentMethod::Cash,
            supplierId: $supplier->id,
            allocations: [
                ['supplier_bill_id' => $billA->id, 'amount' => 400],
                ['supplier_bill_id' => $billB->id, 'amount' => 600],
            ],
        );

        $this->assertEquals(0, $billA->fresh()->balance_cache);
        $this->assertEquals(SupplierBillStatus::Paid, $billA->fresh()->status);
        $this->assertEquals(0, $billB->fresh()->balance_cache);
        $this->assertEquals(SupplierBillStatus::Paid, $billB->fresh()->status);
        $this->assertEquals(-1000, $this->cashAccount->fresh()->current_balance_cache);
        $this->assertEquals(0, $supplier->fresh()->current_balance_cache);
    }

    public function test_allocation_exceeding_bill_balance_throws_even_when_within_payment_amount(): void
    {
        $supplier = Supplier::create(['name' => 'Overallocation Supplier']);
        $bill = $this->makeBill($supplier, 500);

        app(RecordSupplierPaymentAction::class)->handle(
            accountId: $this->cashAccount->id,
            amount: 300,
            paymentDate: now(),
            paymentMethod: PaymentMethod::Cash,
            supplierId: $supplier->id,
            allocations: [['supplier_bill_id' => $bill->id, 'amount' => 300]],
        );

        $this->assertEquals(200, $bill->fresh()->balance_cache);

        $this->expectException(RuntimeException::class);

        app(RecordSupplierPaymentAction::class)->handle(
            accountId: $this->cashAccount->id,
            amount: 300,
            paymentDate: now(),
            paymentMethod: PaymentMethod::Cash,
            supplierId: $supplier->id,
            allocations: [['supplier_bill_id' => $bill->id, 'amount' => 300]],
        );
    }
}
