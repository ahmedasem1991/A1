<?php

namespace App\Actions\Finance;

use App\Enums\AccountSubtype;
use App\Enums\DebitCredit;
use App\Enums\JournalEntryType;
use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Models\Account;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Services\JournalEntryService;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class RecordSupplierPaymentAction
{
    public function __construct(private JournalEntryService $journalEntryService) {}

    /**
     * Record an outbound payment made to a supplier, optionally allocated across one or more bills.
     *
     * @param  array<int, array{supplier_bill_id: int, amount: float}>  $allocations
     */
    public function handle(
        int $accountId,
        float $amount,
        DateTimeInterface $paymentDate,
        PaymentMethod $paymentMethod,
        int $supplierId,
        array $allocations,
        ?string $reference = null,
        ?string $notes = null,
        ?int $recordedByUserId = null,
    ): Payment {
        $allocatedTotal = array_sum(array_column($allocations, 'amount'));

        if ($allocatedTotal > $amount) {
            throw new RuntimeException('Total allocated amount cannot exceed the payment amount.');
        }

        return DB::transaction(function () use (
            $accountId, $amount, $paymentDate, $paymentMethod, $supplierId,
            $allocations, $reference, $notes, $recordedByUserId,
        ) {
            $account = Account::query()->findOrFail($accountId);

            $payment = Payment::create([
                'payment_number' => 'PAY-'.Str::padLeft((string) (DB::table('payments')->lockForUpdate()->max('id') + 1), 6, '0'),
                'direction' => PaymentDirection::Outbound,
                'type' => PaymentType::Standard,
                'payment_method' => $paymentMethod,
                'amount' => $amount,
                'payment_date' => $paymentDate,
                'account_id' => $account->id,
                'supplier_id' => $supplierId,
                'reference' => $reference,
                'notes' => $notes,
                'recorded_by_user_id' => $recordedByUserId,
            ]);

            $payableAccount = Account::query()->where('account_subtype', AccountSubtype::AccountsPayable)->firstOrFail();

            $journalLines = [
                [
                    'account_id' => $account->id,
                    'type' => DebitCredit::Credit,
                    'amount' => $amount,
                ],
            ];

            foreach ($allocations as $allocation) {
                $bill = SupplierBill::query()->lockForUpdate()->findOrFail($allocation['supplier_bill_id']);

                if ($allocation['amount'] > (float) $bill->balance_cache) {
                    throw new RuntimeException("Allocation of {$allocation['amount']} exceeds Bill {$bill->bill_number}'s outstanding balance of {$bill->balance_cache}.");
                }

                $payment->allocations()->create([
                    'allocatable_type' => SupplierBill::class,
                    'allocatable_id' => $bill->id,
                    'amount' => $allocation['amount'],
                ]);

                $journalLines[] = [
                    'account_id' => $payableAccount->id,
                    'type' => DebitCredit::Debit,
                    'amount' => $allocation['amount'],
                    'supplier_id' => $bill->supplier_id,
                    'memo' => "Payment against Bill {$bill->bill_number}",
                ];
            }

            $journalEntry = $this->journalEntryService->post(
                entryDate: $paymentDate,
                description: "Payment made — {$payment->payment_number}",
                type: JournalEntryType::PaymentMade,
                lines: $journalLines,
                sourceable: $payment,
            );

            $payment->update(['journal_entry_id' => $journalEntry->id]);

            $this->refreshSupplierBalance($supplierId);

            return $payment->fresh(['allocations']);
        });
    }

    private function refreshSupplierBalance(int $supplierId): void
    {
        $totalOutstanding = SupplierBill::query()
            ->where('supplier_id', $supplierId)
            ->sum('balance_cache');

        Supplier::query()->whereKey($supplierId)->update([
            'current_balance_cache' => $totalOutstanding,
        ]);
    }
}
