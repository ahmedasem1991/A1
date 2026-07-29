<?php

namespace App\Filament\Resources;

use App\Actions\Finance\RecordPaymentAction;
use App\Actions\Finance\RecordRefundAction;
use App\Enums\AccountSubtype;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Filament\Resources\InvoiceResource\Pages;
use App\Models\Account;
use App\Models\Invoice;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Actions\Action as HeaderAction;
use Filament\Forms;
use Filament\Forms\Components\Component;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class InvoiceResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('customer.name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('invoice_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('total_amount')->money('EGP')->sortable(),
                Tables\Columns\TextColumn::make('paid_amount_cache')->label('Paid')->money('EGP'),
                Tables\Columns\TextColumn::make('balance_cache')->label('Balance')->money('EGP'),
                Tables\Columns\TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => $state?->label())
                    ->colors([
                        'success' => InvoiceStatus::Paid->value,
                        'warning' => InvoiceStatus::PartiallyPaid->value,
                        'danger' => InvoiceStatus::Overdue->value,
                        'gray' => InvoiceStatus::Unpaid->value,
                    ]),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(InvoiceStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                    ->native(false),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                static::recordPaymentAction(),
                static::refundAction(),
            ])
            ->bulkActions([
                //
            ])
            ->defaultSort('invoice_date', 'desc');
    }

    public static function recordPaymentAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('record_payment')
            ->label('Record Payment')
            ->icon('heroicon-s-banknotes')
            ->color('success')
            ->form(static::recordPaymentFormSchema())
            ->action(fn (Invoice $record, array $data) => static::submitPayment($record, $data))
            ->visible(fn (Invoice $record) => (float) $record->balance_cache > 0);
    }

    public static function recordPaymentHeaderAction(): HeaderAction
    {
        return HeaderAction::make('record_payment')
            ->label('Record Payment')
            ->icon('heroicon-s-banknotes')
            ->color('success')
            ->form(static::recordPaymentFormSchema())
            ->action(fn (Invoice $record, array $data) => static::submitPayment($record, $data))
            ->visible(fn (Invoice $record) => (float) $record->balance_cache > 0);
    }

    /**
     * @return array<int, Component>
     */
    protected static function recordPaymentFormSchema(): array
    {
        return [
            Forms\Components\Grid::make(3)->schema([
                Forms\Components\TextInput::make('total_amount')
                    ->label('Invoice Total')
                    ->disabled()
                    ->default(fn (Invoice $record) => $record->total_amount),

                Forms\Components\TextInput::make('paid_amount_cache')
                    ->label('Paid So Far')
                    ->disabled()
                    ->default(fn (Invoice $record) => $record->paid_amount_cache),

                Forms\Components\TextInput::make('balance_cache')
                    ->label('Remaining Balance')
                    ->disabled()
                    ->default(fn (Invoice $record) => $record->balance_cache),

                Forms\Components\TextInput::make('payment')
                    ->label('Payment Amount')
                    ->numeric()
                    ->required()
                    ->default(fn (Invoice $record) => $record->balance_cache)
                    ->rules(fn (Invoice $record) => [
                        'min:0.01',
                        'max:'.$record->balance_cache,
                    ])
                    ->helperText(fn (Invoice $record) => "Enter amount between 0.01 and {$record->balance_cache}"),

                Forms\Components\Select::make('payment_method')
                    ->label('Payment Method')
                    ->options(collect(PaymentMethod::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                    ->default(PaymentMethod::Cash->value)
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (callable $set, $state) => $set('account_id', static::accountIdForPaymentMethod($state)))
                    ->native(false),

                Forms\Components\Select::make('account_id')
                    ->label('Received Into')
                    ->options(fn () => Account::query()->where('is_bank_account', true)->pluck('name', 'id'))
                    ->default(fn () => static::accountIdForPaymentMethod(PaymentMethod::Cash->value))
                    ->disabled()
                    ->dehydrated(true)
                    ->required()
                    ->native(false),

                Forms\Components\Textarea::make('notes')
                    ->maxLength(65535)
                    ->columnSpanFull(),
            ]),
        ];
    }

    protected static function accountIdForPaymentMethod(?string $paymentMethod): ?int
    {
        return Account::query()
            ->where('account_subtype', PaymentMethod::tryFrom($paymentMethod)?->accountSubtype() ?? AccountSubtype::Cash)
            ->value('id');
    }

    protected static function submitPayment(Invoice $record, array $data): void
    {
        app(RecordPaymentAction::class)->handle(
            accountId: (int) $data['account_id'],
            amount: (float) $data['payment'],
            paymentDate: now(),
            paymentMethod: PaymentMethod::from($data['payment_method']),
            customerId: $record->customer_id,
            allocations: [
                ['invoice_id' => $record->id, 'amount' => (float) $data['payment']],
            ],
            notes: $data['notes'] ?? null,
            recordedByUserId: auth()->id(),
        );

        Notification::make()
            ->title('Payment recorded')
            ->success()
            ->send();
    }

    public static function refundAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('refund')
            ->label('Refund')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('This records money being returned to the customer and reopens part of the invoice balance.')
            ->form(static::refundFormSchema())
            ->action(fn (Invoice $record, array $data) => static::submitRefund($record, $data))
            ->visible(fn (Invoice $record) => (float) $record->paid_amount_cache > 0);
    }

    public static function refundHeaderAction(): HeaderAction
    {
        return HeaderAction::make('refund')
            ->label('Refund')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('This records money being returned to the customer and reopens part of the invoice balance.')
            ->form(static::refundFormSchema())
            ->action(fn (Invoice $record, array $data) => static::submitRefund($record, $data))
            ->visible(fn (Invoice $record) => (float) $record->paid_amount_cache > 0);
    }

    /**
     * @return array<int, Component>
     */
    protected static function refundFormSchema(): array
    {
        return [
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('paid_amount_cache')
                    ->label('Amount Paid So Far')
                    ->disabled()
                    ->default(fn (Invoice $record) => $record->paid_amount_cache),

                Forms\Components\TextInput::make('refund_amount')
                    ->label('Refund Amount')
                    ->numeric()
                    ->required()
                    ->default(fn (Invoice $record) => $record->paid_amount_cache)
                    ->rules(fn (Invoice $record) => [
                        'min:0.01',
                        'max:'.$record->paid_amount_cache,
                    ])
                    ->helperText(fn (Invoice $record) => "Enter amount between 0.01 and {$record->paid_amount_cache}"),

                Forms\Components\Select::make('payment_method')
                    ->label('Refund Method')
                    ->options(collect(PaymentMethod::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                    ->default(PaymentMethod::Cash->value)
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (callable $set, $state) => $set('account_id', static::accountIdForPaymentMethod($state)))
                    ->native(false),

                Forms\Components\Select::make('account_id')
                    ->label('Paid From')
                    ->options(fn () => Account::query()->where('is_bank_account', true)->pluck('name', 'id'))
                    ->default(fn () => static::accountIdForPaymentMethod(PaymentMethod::Cash->value))
                    ->disabled()
                    ->dehydrated(true)
                    ->required()
                    ->native(false),

                Forms\Components\Textarea::make('notes')
                    ->label('Reason for refund')
                    ->maxLength(65535)
                    ->columnSpanFull(),
            ]),
        ];
    }

    protected static function submitRefund(Invoice $record, array $data): void
    {
        app(RecordRefundAction::class)->handle(
            invoice: $record,
            accountId: (int) $data['account_id'],
            amount: (float) $data['refund_amount'],
            refundDate: now(),
            paymentMethod: PaymentMethod::from($data['payment_method']),
            notes: $data['notes'] ?? null,
            recordedByUserId: auth()->id(),
        );

        Notification::make()
            ->title('Refund recorded')
            ->success()
            ->send();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'view' => Pages\ViewInvoice::route('/{record}'),
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
