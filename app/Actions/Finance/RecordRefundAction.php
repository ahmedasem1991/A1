<?php

namespace App\Actions\Finance;

use App\Enums\AccountSubtype;
use App\Enums\DebitCredit;
use App\Enums\JournalEntryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\JournalEntryService;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class RecordRefundAction
{
    public function __construct(private JournalEntryService $journalEntryService) {}

    /**
     * Refund part or all of what a customer previously paid toward an invoice.
     * Posts the exact reverse of RecordPaymentAction (Debit AR, Credit
     * Cash/Bank) and un-allocates the refunded amount so the invoice's
     * paid/balance figures and status correctly reopen.
     */
    public function handle(
        Invoice $invoice,
        int $accountId,
        float $amount,
        DateTimeInterface $refundDate,
        PaymentMethod $paymentMethod,
        ?string $notes = null,
        ?int $recordedByUserId = null,
    ): Payment {
        return DB::transaction(function () use (
            $invoice, $accountId, $amount, $refundDate, $paymentMethod, $notes, $recordedByUserId,
        ) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if ($amount > (float) $invoice->paid_amount_cache) {
                throw new RuntimeException('Refund amount cannot exceed the amount paid on this invoice.');
            }

            $account = Account::query()->findOrFail($accountId);
            $receivableAccount = Account::query()->where('account_subtype', AccountSubtype::AccountsReceivable)->firstOrFail();

            $payment = Payment::create([
                'payment_number' => 'PAY-'.Str::padLeft((string) (DB::table('payments')->lockForUpdate()->max('id') + 1), 6, '0'),
                'direction' => PaymentDirection::Outbound,
                'type' => PaymentType::Refund,
                'payment_method' => $paymentMethod,
                'amount' => $amount,
                'payment_date' => $refundDate,
                'account_id' => $account->id,
                'customer_id' => $invoice->customer_id,
                'notes' => $notes,
                'recorded_by_user_id' => $recordedByUserId,
            ]);

            $payment->allocations()->create([
                'allocatable_type' => Invoice::class,
                'allocatable_id' => $invoice->id,
                'amount' => -$amount,
            ]);

            $journalEntry = $this->journalEntryService->post(
                entryDate: $refundDate,
                description: "Refund — {$payment->payment_number} against Invoice {$invoice->invoice_number}",
                type: JournalEntryType::PaymentMade,
                lines: [
                    [
                        'account_id' => $receivableAccount->id,
                        'type' => DebitCredit::Debit,
                        'amount' => $amount,
                        'customer_id' => $invoice->customer_id,
                        'memo' => "Refund against Invoice {$invoice->invoice_number}",
                    ],
                    [
                        'account_id' => $account->id,
                        'type' => DebitCredit::Credit,
                        'amount' => $amount,
                        'customer_id' => $invoice->customer_id,
                    ],
                ],
                sourceable: $payment,
            );

            $payment->update(['journal_entry_id' => $journalEntry->id]);

            // The PaymentAllocation observer already recomputed the invoice's
            // paid_amount_cache / balance_cache / status from the allocation
            // just created above; refresh our in-memory copy before syncing
            // the linked Order, which the observer does not touch.
            $invoice->refresh();

            if ($invoice->order) {
                $invoice->order->update([
                    'paid_amount' => $invoice->paid_amount_cache,
                    'remaining_amount' => $invoice->balance_cache,
                    'status' => (float) $invoice->balance_cache <= 0 ? OrderStatus::Completed : OrderStatus::Processing,
                ]);
            }

            if ($invoice->customer_id) {
                $this->refreshCustomerBalance($invoice->customer_id);
            }

            return $payment->fresh(['allocations']);
        });
    }

    private function refreshCustomerBalance(int $customerId): void
    {
        $totalOutstanding = Invoice::query()
            ->where('customer_id', $customerId)
            ->sum('balance_cache');

        Customer::query()->whereKey($customerId)->update([
            'current_balance_cache' => $totalOutstanding,
        ]);
    }
}
