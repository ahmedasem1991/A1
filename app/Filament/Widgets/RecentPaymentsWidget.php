<?php

namespace App\Filament\Widgets;

use App\Enums\PaymentDirection;
use App\Models\Payment;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentPaymentsWidget extends BaseWidget
{
    use HasWidgetShield;

    protected static ?string $heading = 'Recent Payments';

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(Payment::query()->latest('payment_date')->latest('id'))
            ->columns([
                Tables\Columns\TextColumn::make('payment_number'),
                Tables\Columns\TextColumn::make('direction')->badge()
                    ->colors([
                        'success' => PaymentDirection::Inbound->value,
                        'danger' => PaymentDirection::Outbound->value,
                    ])
                    ->formatStateUsing(fn ($state) => $state?->label()),
                Tables\Columns\TextColumn::make('amount')->money('EGP'),
                Tables\Columns\TextColumn::make('payment_date')->date(),
            ])
            ->paginated(false);
    }
}
