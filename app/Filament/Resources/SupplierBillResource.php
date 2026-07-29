<?php

namespace App\Filament\Resources;

use App\Actions\Finance\RecordSupplierPaymentAction;
use App\Enums\AccountSubtype;
use App\Enums\AccountType;
use App\Enums\PaymentMethod;
use App\Enums\SupplierBillStatus;
use App\Filament\Resources\SupplierBillResource\Pages;
use App\Models\Account;
use App\Models\SupplierBill;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Actions\Action as HeaderAction;
use Filament\Forms;
use Filament\Forms\Components\Component;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SupplierBillResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = SupplierBill::class;

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('bill_number')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),

            Forms\Components\Select::make('supplier_id')
                ->relationship('supplier', 'name')
                ->required()
                ->searchable()
                ->preload(),

            Forms\Components\DatePicker::make('bill_date')
                ->default(now())
                ->required()
                ->native(false)
                ->maxDate(now()),
            Forms\Components\DatePicker::make('due_date')
                ->default(fn () => now()->addDays(config('finance.supplier_bill_due_days')))
                ->native(false)
                ->afterOrEqual('bill_date'),

            Forms\Components\TextInput::make('total_amount')
                ->numeric()
                ->required()
                ->minValue(0.01)
                ->disabled(fn (?Model $record) => (bool) $record)
                ->dehydrated(),

            Forms\Components\Select::make('expense_account_id')
                ->label('Expense Category')
                ->options(fn () => Account::query()->where('account_type', AccountType::Expense)->pluck('name', 'id'))
                ->searchable(),

            Forms\Components\Textarea::make('notes')
                ->maxLength(65535)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('bill_number')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('supplier.name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('bill_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('total_amount')->money('EGP')->sortable(),
                Tables\Columns\TextColumn::make('paid_amount_cache')->label('Paid')->money('EGP'),
                Tables\Columns\TextColumn::make('balance_cache')->label('Balance')->money('EGP'),
                Tables\Columns\TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => $state?->label()),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(SupplierBillStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                    ->native(false),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (SupplierBill $record) => (float) $record->paid_amount_cache === 0.0),
                static::recordPaymentAction(),
            ])
            ->bulkActions([
                //
            ])
            ->defaultSort('bill_date', 'desc');
    }

    public static function recordPaymentAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('record_payment')
            ->label('Record Payment')
            ->icon('heroicon-s-banknotes')
            ->color('success')
            ->form(static::recordPaymentFormSchema())
            ->action(fn (SupplierBill $record, array $data) => static::submitPayment($record, $data))
            ->visible(fn (SupplierBill $record) => (float) $record->balance_cache > 0);
    }

    public static function recordPaymentHeaderAction(): HeaderAction
    {
        return HeaderAction::make('record_payment')
            ->label('Record Payment')
            ->icon('heroicon-s-banknotes')
            ->color('success')
            ->form(static::recordPaymentFormSchema())
            ->action(fn (SupplierBill $record, array $data) => static::submitPayment($record, $data))
            ->visible(fn (SupplierBill $record) => (float) $record->balance_cache > 0);
    }

    /**
     * @return array<int, Component>
     */
    protected static function recordPaymentFormSchema(): array
    {
        return [
            Forms\Components\Grid::make(3)->schema([
                Forms\Components\TextInput::make('total_amount')
                    ->label('Bill Total')
                    ->disabled()
                    ->default(fn (SupplierBill $record) => $record->total_amount),

                Forms\Components\TextInput::make('paid_amount_cache')
                    ->label('Paid So Far')
                    ->disabled()
                    ->default(fn (SupplierBill $record) => $record->paid_amount_cache),

                Forms\Components\TextInput::make('balance_cache')
                    ->label('Remaining Balance')
                    ->disabled()
                    ->default(fn (SupplierBill $record) => $record->balance_cache),

                Forms\Components\TextInput::make('payment')
                    ->label('Payment Amount')
                    ->numeric()
                    ->required()
                    ->default(fn (SupplierBill $record) => $record->balance_cache)
                    ->rules(fn (SupplierBill $record) => [
                        'min:0.01',
                        'max:'.$record->balance_cache,
                    ])
                    ->helperText(fn (SupplierBill $record) => "Enter amount between 0.01 and {$record->balance_cache}"),

                Forms\Components\Select::make('payment_method')
                    ->label('Payment Method')
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

    protected static function submitPayment(SupplierBill $record, array $data): void
    {
        app(RecordSupplierPaymentAction::class)->handle(
            accountId: (int) $data['account_id'],
            amount: (float) $data['payment'],
            paymentDate: now(),
            paymentMethod: PaymentMethod::from($data['payment_method']),
            supplierId: $record->supplier_id,
            allocations: [
                ['supplier_bill_id' => $record->id, 'amount' => (float) $data['payment']],
            ],
            notes: $data['notes'] ?? null,
            recordedByUserId: auth()->id(),
        );

        Notification::make()
            ->title('Payment recorded')
            ->success()
            ->send();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupplierBills::route('/'),
            'create' => Pages\CreateSupplierBill::route('/create'),
            'edit' => Pages\EditSupplierBill::route('/{record}/edit'),
            'view' => Pages\ViewSupplierBill::route('/{record}'),
        ];
    }

    public static function canDelete(Model $record): bool
    {
        return (float) $record->paid_amount_cache === 0.0;
    }

    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'edit',
            'delete',
            'delete_any',
        ];
    }
}
