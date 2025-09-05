<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Models\Transaction;
use Filament\Support\RawJs;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\TransactionResource\Pages;
use Filament\Infolists\Infolist;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationGroup = 'Inventory Management';

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('type')
                    ->options([
                        'income' => 'Income',
                        'expense' => 'Expense',
                    ])
                    ->default('income')
                    ->required()
                    ->native(false),
                Forms\Components\TextInput::make('amount')
                    ->prefix('EGP')
                    ->mask(RawJs::make('$money($input)'))
                    ->stripCharacters(',')
                    ->required()
                    ->minValue(1)
                    ->numeric(),
                Forms\Components\DatePicker::make('transaction_date')
                    ->default(now())
                    ->required(),
                Forms\Components\Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->colors([
                        'success' => 'income',
                        'danger' => 'expense',
                    ]),

                Tables\Columns\TextColumn::make('amount')
                    ->money('EGP')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('transaction_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->sortable(),

                Tables\Columns\TextColumn::make('order.name')
                    ->sortable(),


            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options([
                    'income' => 'Income',
                    'expense' => 'Expense',
                ])
                    ->native(false),
                Tables\Filters\Filter::make('date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->closeOnDateSelection()
                            ->maxDate(now()->subDays(1))
                            ->reactive()
                            ->native(false),
                        Forms\Components\DatePicker::make('until')
                            ->closeOnDateSelection()
                            ->disabled(fn($get) => $get('from') === null)
                            ->minDate(fn($get) => $get('from') ? \Illuminate\Support\Carbon::parse($get('from'))->addDay() : null)
                            ->maxDate(now())
                            ->native(false)
                    ])
                    ->query(
                        fn($query, $data) => $query
                            ->when($data['from'], fn($q) => $q->whereDate('transaction_date', '>=', $data['from']))
                            ->when($data['until'], fn($q) => $q->whereDate('transaction_date', '<=', $data['until']))
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->slideOver(),
            ])
            ->bulkActions([
                //
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                \Filament\Infolists\Components\Section::make('Transaction Details')
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('transaction_date')
                            ->date()
                            ->label('Date'),
                        \Filament\Infolists\Components\TextEntry::make('type')
                            ->badge()
                            ->color(fn($state) => $state === 'income' ? 'success' : 'danger')
                            ->label('Type'),
                        \Filament\Infolists\Components\TextEntry::make('amount')
                            ->money('EGP')
                            ->label('Amount'),
                    ])
                    ->columns(2),

                \Filament\Infolists\Components\Section::make('User & Notes')
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('user.name')
                            ->label('User')
                            ->placeholder('—'),
                        \Filament\Infolists\Components\TextEntry::make('notes')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactions::route('/'),
        ];
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->orderBy('id', 'desc');
    }
}
