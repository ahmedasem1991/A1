<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Transaction;
use Filament\Resources\Pages\CreateRecord;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected function afterCreate(): void
    {
        if (data_get($this->data, 'paid_amount', 0) > 0) {
            Transaction::create([
                'type' => 'income',
                'amount' => data_get($this->data, 'paid_amount', 0),
                'order_id' => $this->record->id,
                'user_id' => auth()->user()->id,
                'notes' => 'Prepaid amount',
                'transaction_date' => now(),
            ]);
        }
    }
}
