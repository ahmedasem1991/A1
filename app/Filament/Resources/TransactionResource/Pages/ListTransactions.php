<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\TransactionResource;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use App\Filament\Resources\TransactionResource\Widgets\FinanceOverview;
use App\Filament\Resources\TransactionResource\Widgets\TransactionsOverview;

class ListTransactions extends ListRecords
{
    use ExposesTableToWidgets;
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->slideOver(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            FinanceOverview::class
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            TransactionsOverview::class,
        ];
    }
}
