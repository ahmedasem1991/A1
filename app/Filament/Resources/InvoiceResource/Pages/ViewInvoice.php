<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            InvoiceResource::recordPaymentHeaderAction(),
            InvoiceResource::refundHeaderAction(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Invoice Details')
                ->columns(3)
                ->schema([
                    TextEntry::make('invoice_number'),
                    TextEntry::make('customer.name')->label('Customer'),
                    TextEntry::make('status')->badge()->formatStateUsing(fn ($state) => $state?->label()),
                    TextEntry::make('invoice_date')->date(),
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
