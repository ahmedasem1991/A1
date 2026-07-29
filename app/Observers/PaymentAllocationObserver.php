<?php

namespace App\Observers;

use App\Enums\InvoiceStatus;
use App\Enums\SupplierBillStatus;
use App\Models\Invoice;
use App\Models\PaymentAllocation;
use App\Models\SupplierBill;

class PaymentAllocationObserver
{
    public function created(PaymentAllocation $allocation): void
    {
        $this->recompute($allocation);
    }

    public function deleted(PaymentAllocation $allocation): void
    {
        $this->recompute($allocation);
    }

    private function recompute(PaymentAllocation $allocation): void
    {
        $allocatable = $allocation->allocatable;

        if ($allocatable instanceof Invoice) {
            $paid = (float) $allocatable->paymentAllocations()->sum('amount');
            $balance = max(0, round((float) $allocatable->total_amount - $paid, 2));

            $allocatable->update([
                'paid_amount_cache' => $paid,
                'balance_cache' => $balance,
                'status' => match (true) {
                    $balance <= 0 => InvoiceStatus::Paid,
                    $paid > 0 => InvoiceStatus::PartiallyPaid,
                    default => InvoiceStatus::Unpaid,
                },
            ]);
        }

        if ($allocatable instanceof SupplierBill) {
            $paid = (float) $allocatable->paymentAllocations()->sum('amount');
            $balance = max(0, round((float) $allocatable->total_amount - $paid, 2));

            $allocatable->update([
                'paid_amount_cache' => $paid,
                'balance_cache' => $balance,
                'status' => match (true) {
                    $balance <= 0 => SupplierBillStatus::Paid,
                    $paid > 0 => SupplierBillStatus::PartiallyPaid,
                    default => SupplierBillStatus::Unpaid,
                },
            ]);
        }
    }
}
