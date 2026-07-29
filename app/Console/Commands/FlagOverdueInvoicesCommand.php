<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Enums\SupplierBillStatus;
use App\Models\Invoice;
use App\Models\SupplierBill;
use Illuminate\Console\Command;

class FlagOverdueInvoicesCommand extends Command
{
    protected $signature = 'finance:flag-overdue';

    protected $description = 'Flag invoices and supplier bills past their due date with an outstanding balance as overdue';

    public function handle(): int
    {
        $overdueInvoices = Invoice::query()
            ->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::PartiallyPaid])
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->toDateString())
            ->where('balance_cache', '>', 0)
            ->update(['status' => InvoiceStatus::Overdue]);

        $overdueBills = SupplierBill::query()
            ->whereIn('status', [SupplierBillStatus::Unpaid, SupplierBillStatus::PartiallyPaid])
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->toDateString())
            ->where('balance_cache', '>', 0)
            ->update(['status' => SupplierBillStatus::Overdue]);

        // A due date can be edited on either an Invoice or a SupplierBill
        // while unpaid, so an Overdue record can legitimately move back to
        // Unpaid/Partially Paid if its due date is pushed out.
        $recoveredInvoices = 0;

        Invoice::query()
            ->where('status', InvoiceStatus::Overdue)
            ->where('due_date', '>=', now()->toDateString())
            ->each(function (Invoice $invoice) use (&$recoveredInvoices) {
                $invoice->update([
                    'status' => (float) $invoice->paid_amount_cache > 0
                        ? InvoiceStatus::PartiallyPaid
                        : InvoiceStatus::Unpaid,
                ]);
                $recoveredInvoices++;
            });

        $recoveredBills = 0;

        SupplierBill::query()
            ->where('status', SupplierBillStatus::Overdue)
            ->where('due_date', '>=', now()->toDateString())
            ->each(function (SupplierBill $bill) use (&$recoveredBills) {
                $bill->update([
                    'status' => (float) $bill->paid_amount_cache > 0
                        ? SupplierBillStatus::PartiallyPaid
                        : SupplierBillStatus::Unpaid,
                ]);
                $recoveredBills++;
            });

        $this->info("Flagged {$overdueInvoices} invoice(s) and {$overdueBills} supplier bill(s) as overdue.");

        if ($recoveredInvoices > 0) {
            $this->info("Reverted {$recoveredInvoices} invoice(s) whose due date is no longer in the past.");
        }

        if ($recoveredBills > 0) {
            $this->info("Reverted {$recoveredBills} supplier bill(s) whose due date is no longer in the past.");
        }

        return self::SUCCESS;
    }
}
