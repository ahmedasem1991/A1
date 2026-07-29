<?php

namespace App\Filament\Resources;

use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Payment;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PaymentResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = Payment::class;

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('payment_number')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('direction')->badge()
                    ->colors([
                        'success' => PaymentDirection::Inbound->value,
                        'danger' => PaymentDirection::Outbound->value,
                    ])
                    ->formatStateUsing(fn ($state) => $state?->label()),
                Tables\Columns\TextColumn::make('payment_method')->formatStateUsing(fn ($state) => $state?->label()),
                Tables\Columns\TextColumn::make('amount')->money('EGP')->sortable(),
                Tables\Columns\TextColumn::make('payment_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('account.name')->label('Account'),
                Tables\Columns\TextColumn::make('customer.name')->label('Customer')->placeholder('—'),
                Tables\Columns\TextColumn::make('supplier.name')->label('Supplier')->placeholder('—'),
                Tables\Columns\TextColumn::make('recordedByUser.name')->label('Recorded By'),
            ])
            ->filters([
                SelectFilter::make('direction')
                    ->options(collect(PaymentDirection::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                    ->native(false),
                SelectFilter::make('payment_method')
                    ->options(collect(PaymentMethod::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                    ->native(false),
                Filter::make('payment_date')
                    ->form([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('payment_date', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('payment_date', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                //
            ])
            ->defaultSort('payment_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'view' => Pages\ViewPayment::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
        ];
    }
}
