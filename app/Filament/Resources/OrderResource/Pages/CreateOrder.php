<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Actions\Finance\RecordPaymentAction;
use App\Actions\Finance\RecordSaleAction;
use App\Enums\AccountSubtype;
use App\Enums\PaymentMethod;
use App\Filament\Resources\OrderItemResource;
use App\Filament\Resources\OrderResource;
use App\Models\Account;
use Filament\Resources\Pages\CreateRecord;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected function getRedirectUrl(): string
    {
        return OrderItemResource::getUrl('index', [
            'tableFilters' => [
                'order_id' => ['value' => $this->record->id],
            ],
        ]);
    }

    protected function afterCreate(): void
    {
        $this->record->orderItems()
            ->where('category', 'product')
            ->update(['status' => 'completed']);

        $prepaidAmount = (float) data_get($this->data, 'paid_amount', 0);

        if ($prepaidAmount <= 0 || (float) $this->record->total_price <= 0) {
            return;
        }

        $invoice = app(RecordSaleAction::class)->handle($this->record);

        $cashDrawer = Account::query()->where('account_subtype', AccountSubtype::Cash)->firstOrFail();

        app(RecordPaymentAction::class)->handle(
            accountId: $cashDrawer->id,
            amount: $prepaidAmount,
            paymentDate: now(),
            paymentMethod: PaymentMethod::Cash,
            customerId: $this->record->customer_id,
            allocations: [['invoice_id' => $invoice->id, 'amount' => $prepaidAmount]],
            notes: 'Prepaid amount',
            recordedByUserId: auth()->id(),
        );
    }
}
