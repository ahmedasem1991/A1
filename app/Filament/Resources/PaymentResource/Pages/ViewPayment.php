<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Payment Details')
                ->columns(3)
                ->schema([
                    TextEntry::make('payment_number'),
                    TextEntry::make('direction')->badge()->formatStateUsing(fn ($state) => $state?->label()),
                    TextEntry::make('payment_method')->formatStateUsing(fn ($state) => $state?->label()),
                    TextEntry::make('amount')->money('EGP'),
                    TextEntry::make('payment_date')->date(),
                    TextEntry::make('account.name')->label('Account'),
                    TextEntry::make('customer.name')->label('Customer')->placeholder('—'),
                    TextEntry::make('supplier.name')->label('Supplier')->placeholder('—'),
                    TextEntry::make('reference')->placeholder('—'),
                    TextEntry::make('recordedByUser.name')->label('Recorded By')->placeholder('—'),
                ]),

            Section::make('Allocations')
                ->schema([
                    RepeatableEntry::make('allocations')
                        ->schema([
                            TextEntry::make('allocatable_type')->label('Applied To')->formatStateUsing(fn ($state) => class_basename($state)),
                            TextEntry::make('allocatable_id')->label('Reference #'),
                            TextEntry::make('amount')->money('EGP'),
                        ])
                        ->columns(3),
                ]),

            Section::make('Notes')
                ->schema([
                    TextEntry::make('notes')->placeholder('—')->columnSpanFull(),
                ]),
        ]);
    }
}
