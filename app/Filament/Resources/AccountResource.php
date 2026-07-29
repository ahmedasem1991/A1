<?php

namespace App\Filament\Resources;

use App\Actions\Finance\TransferBetweenAccountsAction;
use App\Enums\AccountSubtype;
use App\Enums\AccountType;
use App\Filament\Resources\AccountResource\Pages;
use App\Models\Account;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Carbon\Carbon;
use Filament\Actions\Action as HeaderAction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AccountResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = Account::class;

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->disabled(fn (?Model $record) => (bool) $record?->is_system),

            Forms\Components\TextInput::make('code')
                ->maxLength(255)
                ->unique(ignoreRecord: true),

            Forms\Components\Select::make('account_type')
                ->options(collect(AccountType::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                ->required()
                ->native(false)
                ->disabled(fn (?Model $record) => (bool) $record?->is_system || (bool) $record?->journalEntryLines()->exists()),

            Forms\Components\Select::make('account_subtype')
                ->options(collect(AccountSubtype::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                ->native(false)
                ->disabled(fn (?Model $record) => (bool) $record?->is_system),

            Forms\Components\Toggle::make('is_bank_account')
                ->label('Usable as a payment money-location')
                ->disabled(fn (?Model $record) => (bool) $record?->is_system)
                ->helperText('Only enable for Cash/Bank accounts. This flags the account as selectable when recording payments.'),

            Forms\Components\Toggle::make('is_active')
                ->default(true),

            Forms\Components\Textarea::make('description')
                ->maxLength(65535)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('code')->searchable(),
                Tables\Columns\TextColumn::make('account_type')->badge()->formatStateUsing(fn ($state) => $state?->label()),
                Tables\Columns\TextColumn::make('account_subtype')->formatStateUsing(fn ($state) => $state?->label()),
                Tables\Columns\IconColumn::make('is_bank_account')->boolean(),
                Tables\Columns\IconColumn::make('is_system')->boolean()->label('System'),
                Tables\Columns\TextColumn::make('current_balance_cache')->label('Balance')->money('EGP')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('account_type')
                    ->options(collect(AccountType::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                    ->native(false),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (Account $record) => ! $record->is_system),
            ])
            ->bulkActions([
                //
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccounts::route('/'),
            'create' => Pages\CreateAccount::route('/create'),
            'edit' => Pages\EditAccount::route('/{record}/edit'),
        ];
    }

    public static function transferFundsAction(): HeaderAction
    {
        return HeaderAction::make('transfer_funds')
            ->label('Transfer Funds')
            ->icon('heroicon-o-arrows-right-left')
            ->color('gray')
            ->form([
                Forms\Components\Select::make('from_account_id')
                    ->label('From')
                    ->options(fn () => Account::query()->where('is_bank_account', true)->where('is_active', true)->pluck('name', 'id'))
                    ->required()
                    ->native(false)
                    ->live(),

                Forms\Components\Select::make('to_account_id')
                    ->label('To')
                    ->options(fn (callable $get) => Account::query()
                        ->where('is_bank_account', true)
                        ->where('is_active', true)
                        ->where('id', '!=', $get('from_account_id'))
                        ->pluck('name', 'id'))
                    ->required()
                    ->native(false)
                    ->different('from_account_id'),

                Forms\Components\TextInput::make('amount')
                    ->numeric()
                    ->required()
                    ->minValue(0.01)
                    ->maxValue(fn (callable $get) => Account::find($get('from_account_id'))?->current_balance_cache ?? 0)
                    ->helperText(fn (callable $get) => ($account = Account::find($get('from_account_id')))
                        ? 'Available balance: '.number_format($account->current_balance_cache, 2)
                        : null),

                Forms\Components\DatePicker::make('transfer_date')
                    ->default(now())
                    ->required()
                    ->native(false)
                    ->maxDate(now()),

                Forms\Components\TextInput::make('memo')
                    ->label('Memo (optional)')
                    ->maxLength(255),
            ])
            ->action(function (array $data) {
                $from = Account::query()->findOrFail($data['from_account_id']);
                $to = Account::query()->findOrFail($data['to_account_id']);

                app(TransferBetweenAccountsAction::class)->handle(
                    from: $from,
                    to: $to,
                    amount: (float) $data['amount'],
                    transferDate: Carbon::parse($data['transfer_date']),
                    memo: $data['memo'] ?? null,
                );

                Notification::make()
                    ->title('Transfer recorded')
                    ->success()
                    ->send();
            });
    }

    public static function canDelete(Model $record): bool
    {
        return ! $record->is_system;
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
