<?php

namespace App\Filament\Resources\SupplierBillResource\Pages;

use App\Enums\SupplierBillStatus;
use App\Filament\Resources\SupplierBillResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSupplierBill extends CreateRecord
{
    protected static string $resource = SupplierBillResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['paid_amount_cache'] = 0;
        $data['balance_cache'] = $data['total_amount'];
        $data['status'] = SupplierBillStatus::Unpaid->value;

        return $data;
    }
}
