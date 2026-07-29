<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Models\Customer;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CustomerResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('phone')
                ->tel()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('email')
                ->email()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            Forms\Components\Textarea::make('address')
                ->maxLength(65535)
                ->columnSpanFull(),
            Forms\Components\Textarea::make('notes')
                ->maxLength(65535)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('phone'),
                Tables\Columns\TextColumn::make('email'),
                Tables\Columns\TextColumn::make('current_balance_cache')->label('Outstanding Balance')->money('EGP')->sortable(),
                Tables\Columns\TextColumn::make('orders_count')->counts('orders')->label('Orders'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                //
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
            'view' => Pages\ViewCustomer::route('/{record}'),
        ];
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
