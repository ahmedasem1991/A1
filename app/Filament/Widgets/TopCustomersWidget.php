<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TopCustomersWidget extends BaseWidget
{
    use HasWidgetShield;

    protected static ?string $heading = 'Top Customers';

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Customer::query()
                    ->withSum('invoices as total_purchases', 'total_amount')
                    ->orderByDesc('total_purchases')
            )
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('total_purchases')->money('EGP'),
                Tables\Columns\TextColumn::make('current_balance_cache')->label('Outstanding')->money('EGP'),
            ])
            ->paginated(false)
            ->defaultSort('total_purchases', 'desc');
    }
}
