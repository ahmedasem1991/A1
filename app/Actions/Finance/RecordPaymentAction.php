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

class RecordPaymentAction
{
    public function __construct(private JournalEntryService $journalEntryService) {}

    /**
     * Record an inbound customer payment, optionally allocated across one or more invoices.
     *
     * @param  array<int, array{invoice_id: int, amount: float}>  $allocations
     */
    public function handle(
        int $accountId,
        float $amount,
        DateTimeInterface $paymentDate,
        PaymentMethod $paymentMethod,
        ?int $customerId,
        array $allocations,
        PaymentType $type = PaymentType::Standard,
        ?string $reference = null,
        ?string $notes = null,
        ?int $recordedByUserId = null,
    ): Payment {
        $allocatedTotal = array_sum(array_column($allocations, 'amount'));

        if ($allocatedTotal > $amount) {
            throw new RuntimeException('Total allocated amount cannot exceed the payment amount.');
        }

        return DB::transaction(function () use (
            $accountId, $amount, $paymentDate, $paymentMethod, $customerId,
            $allocations, $type, $reference, $notes, $recordedByUserId,
        ) {
            $account = Account::query()->findOrFail($accountId);

            $payment = Payment::create([
                'payment_number' => 'PAY-'.Str::padLeft((string) (DB::table('payments')->lockForUpdate()->max('id') + 1), 6, '0'),
                'direction' => PaymentDirection::Inbound,
                'type' => $type,
                'payment_method' => $paymentMethod,
                'amount' => $amount,
                'payment_date' => $paymentDate,
                'account_id' => $account->id,
                'customer_id' => $customerId,
                'reference' => $reference,
                'notes' => $notes,
                'recorded_by_user_id' => $recordedByUserId,
            ]);

            $receivableAccount = Account::query()->where('account_subtype', AccountSubtype::AccountsReceivable)->firstOrFail();

            $journalLines = [];

            foreach ($allocations as $allocation) {
                $invoice = Invoice::query()->lockForUpdate()->findOrFail($allocation['invoice_id']);

                if ($allocation['amount'] > (float) $invoice->balance_cache) {
                    throw new RuntimeException("Allocation of {$allocation['amount']} exceeds Invoice {$invoice->invoice_number}'s outstanding balance of {$invoice->balance_cache}.");
                }

                $payment->allocations()->create([
                    'allocatable_type' => Invoice::class,
                    'allocatable_id' => $invoice->id,
                    'amount' => $allocation['amount'],
                ]);

                $journalLines[] = [
                    'account_id' => $receivableAccount->id,
                    'type' => DebitCredit::Credit,
                    'amount' => $allocation['amount'],
                    'customer_id' => $invoice->customer_id,
                    'memo' => "Payment against Invoice {$invoice->invoice_number}",
                ];

                $this->syncOrderForInvoice($invoice);
            }

            $journalLines[] = [
                'account_id' => $account->id,
                'type' => DebitCredit::Debit,
                'amount' => $amount,
            ];

            $journalEntry = $this->journalEntryService->post(
                entryDate: $paymentDate,
                description: "Payment received — {$payment->payment_number}",
                type: JournalEntryType::PaymentReceived,
                lines: $journalLines,
                sourceable: $payment,
            );

            $payment->update(['journal_entry_id' => $journalEntry->id]);

            if ($customerId) {
                $this->refreshCustomerBalance($customerId);
            }

            return $payment->fresh(['allocations']);
        });
    }

    /**
     * The PaymentAllocation observer already recomputed the invoice's
     * paid_amount_cache / balance_cache / status; sync the linked Order,
     * which the observer does not touch.
     */
    private function syncOrderForInvoice(Invoice $invoice): void
    {
        $invoice->refresh();

        if ($invoice->order) {
            $invoice->order->update([
                'paid_amount' => $invoice->paid_amount_cache,
                'remaining_amount' => $invoice->balance_cache,
                'status' => (float) $invoice->balance_cache <= 0 ? OrderStatus::Completed : $invoice->order->status,
            ]);
        }
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
