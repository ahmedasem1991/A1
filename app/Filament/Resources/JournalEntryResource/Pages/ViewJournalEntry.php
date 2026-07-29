<?php

namespace App\Filament\Resources\JournalEntryResource\Pages;

use App\Filament\Resources\JournalEntryResource;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewJournalEntry extends ViewRecord
{
    protected static string $resource = JournalEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            JournalEntryResource::reverseAction(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Journal Entry')
                ->columns(3)
                ->schema([
                    TextEntry::make('entry_date')->date(),
                    TextEntry::make('entry_type')->badge()->formatStateUsing(fn ($state) => $state?->label()),
                    TextEntry::make('total_amount')->money('EGP'),
                    TextEntry::make('description')->columnSpanFull(),
                    TextEntry::make('postedByUser.name')->label('Posted By')->placeholder('System'),
                    TextEntry::make('is_reversed')->label('Reversed')->formatStateUsing(fn ($state) => $state ? 'Yes' : 'No'),
                ]),

            Section::make('Lines')
                ->schema([
                    RepeatableEntry::make('lines')
                        ->label('')
                        ->schema([
                            TextEntry::make('account.name')->label('Account'),
                            TextEntry::make('type')->badge()->formatStateUsing(fn ($state) => $state?->label()),
                            TextEntry::make('amount')->money('EGP'),
                            TextEntry::make('memo')->placeholder('—'),
                        ])
                        ->columns(4),
                ]),
        ]);
    }
}
