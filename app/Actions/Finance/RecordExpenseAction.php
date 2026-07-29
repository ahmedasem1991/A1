<?php

namespace App\Actions\Finance;

use App\Enums\DebitCredit;
use App\Enums\JournalEntryType;
use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Models\Account;
use App\Models\Expense;
use App\Models\Payment;
use App\Services\JournalEntryService;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecordExpenseAction
{
    public function __construct(private JournalEntryService $journalEntryService) {}

    public function handle(
        int $expenseAccountId,
        int $paidFromAccountId,
        float $amount,
        DateTimeInterface $expenseDate,
        string $description,
        PaymentMethod $paymentMethod = PaymentMethod::Cash,
        ?int $supplierId = null,
        ?int $recordedByUserId = null,
    ): Expense {
        return DB::transaction(function () use (
            $expenseAccountId, $paidFromAccountId, $amount, $expenseDate,
            $description, $paymentMethod, $supplierId, $recordedByUserId,
        ) {
            $expenseAccount = Account::query()->findOrFail($expenseAccountId);
            $paidFromAccount = Account::query()->findOrFail($paidFromAccountId);

            $payment = Payment::create([
                'payment_number' => 'PAY-'.Str::padLeft((string) (DB::table('payments')->lockForUpdate()->max('id') + 1), 6, '0'),
                'direction' => PaymentDirection::Outbound,
                'type' => PaymentType::Standard,
                'payment_method' => $paymentMethod,
                'amount' => $amount,
                'payment_date' => $expenseDate,
                'account_id' => $paidFromAccount->id,
                'supplier_id' => $supplierId,
                'notes' => $description,
                'recorded_by_user_id' => $recordedByUserId,
            ]);

            $journalEntry = $this->journalEntryService->post(
                entryDate: $expenseDate,
                description: $description,
                type: JournalEntryType::Expense,
                lines: [
                    [
                        'account_id' => $expenseAccount->id,
                        'type' => DebitCredit::Debit,
                        'amount' => $amount,
                    ],
                    [
                        'account_id' => $paidFromAccount->id,
                        'type' => DebitCredit::Credit,
                        'amount' => $amount,
                    ],
                ],
                sourceable: $payment,
            );

            $payment->update(['journal_entry_id' => $journalEntry->id]);

            return Expense::create([
                'expense_number' => 'EXP-'.Str::padLeft((string) (DB::table('expenses')->lockForUpdate()->max('id') + 1), 6, '0'),
                'supplier_id' => $supplierId,
                'expense_account_id' => $expenseAccount->id,
                'amount' => $amount,
                'expense_date' => $expenseDate,
                'payment_id' => $payment->id,
                'description' => $description,
                'recorded_by_user_id' => $recordedByUserId,
            ]);
        });
    }
}
