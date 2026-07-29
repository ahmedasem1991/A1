<?php

namespace App\Filament\Resources\SupplierBillResource\Pages;

use App\Filament\Resources\SupplierBillResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSupplierBill extends EditRecord
{
    protected static string $resource = SupplierBillResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
