<?php

namespace App\Filament\Resources\SupplierBillResource\Pages;

use App\Filament\Resources\SupplierBillResource;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewSupplierBill extends ViewRecord
{
    protected static string $resource = SupplierBillResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SupplierBillResource::recordPaymentHeaderAction(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Bill Details')
                ->columns(3)
                ->schema([
                    TextEntry::make('bill_number'),
                    TextEntry::make('supplier.name')->label('Supplier'),
                    TextEntry::make('status')->badge()->formatStateUsing(fn ($state) => $state?->label()),
                    TextEntry::make('bill_date')->date(),
                    TextEntry::make('due_date')->date()->placeholder('—'),
                    TextEntry::make('total_amount')->money('EGP'),
                    TextEntry::make('paid_amount_cache')->label('Paid')->money('EGP'),
                    TextEntry::make('balance_cache')->label('Balance')->money('EGP'),
                ]),

            Section::make('Notes')
                ->schema([
                    TextEntry::make('notes')->placeholder('—')->columnSpanFull(),
                ]),
        ]);
    }
}
