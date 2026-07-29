<?php

namespace App\Actions\Finance;

use App\Enums\AccountSubtype;
use App\Enums\DebitCredit;
use App\Enums\InvoiceStatus;
use App\Enums\JournalEntryType;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\Order;
use App\Services\JournalEntryService;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class RecordSaleAction
{
    public function __construct(private JournalEntryService $journalEntryService) {}

    public function handle(Order $order, ?DateTimeInterface $dueDate = null): Invoice
    {
        if ($order->invoice()->exists()) {
            throw new RuntimeException("Order #{$order->id} already has an invoice.");
        }

        return DB::transaction(function () use ($order, $dueDate) {
            $receivableAccount = Account::query()->where('account_subtype', AccountSubtype::AccountsReceivable)->firstOrFail();
            $revenueAccount = Account::query()->where('account_subtype', AccountSubtype::SalesRevenue)->firstOrFail();

            $saleTimestamp = $order->created_at ?? now();
            $invoiceDate = $saleTimestamp->toDateString();

            $invoice = Invoice::create([
                'invoice_number' => 'INV-'.Str::padLeft((string) (DB::table('invoices')->lockForUpdate()->max('id') + 1), 6, '0'),
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'invoice_date' => $invoiceDate,
                'due_date' => $dueDate?->format('Y-m-d')
                    ?? Carbon::parse($invoiceDate)->addDays(config('finance.invoice_due_days'))->toDateString(),
                'subtotal' => $order->subtotal,
                'discount' => $order->discount,
                'total_amount' => $order->total_price,
                'paid_amount_cache' => 0,
                'balance_cache' => $order->total_price,
                'status' => InvoiceStatus::Unpaid,
            ]);

            $this->journalEntryService->post(
                entryDate: $saleTimestamp,
                description: "Sale — Invoice {$invoice->invoice_number}",
                type: JournalEntryType::Sale,
                lines: [
                    [
                        'account_id' => $receivableAccount->id,
                        'type' => DebitCredit::Debit,
                        'amount' => (float) $invoice->total_amount,
                        'customer_id' => $invoice->customer_id,
                    ],
                    [
                        'account_id' => $revenueAccount->id,
                        'type' => DebitCredit::Credit,
                        'amount' => (float) $invoice->total_amount,
                    ],
                ],
                sourceable: $invoice,
            );

            return $invoice;
        });
    }
}
