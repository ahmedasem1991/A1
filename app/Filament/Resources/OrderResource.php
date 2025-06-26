<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->required(),

            Forms\Components\HasManyRepeater::make('items')
                ->relationship('items')
                ->schema([
                    Forms\Components\Select::make('studio_image_id')
                        ->label('Studio Image')
                        ->relationship('studioImage', 'image_size')
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            static::updateItemPrice($set, $get);
                        }),

                    Forms\Components\Checkbox::make('is_instant')
                        ->label('Add Instant Delivery')
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            static::updateItemPrice($set, $get);
                        }),

                    Forms\Components\Checkbox::make('include_soft_copy')
                        ->label('Include Soft Copy')
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            static::updateItemPrice($set, $get);
                        }),

                    Forms\Components\TextInput::make('price')
                        ->label('Item Price')
                        ->required()
                        ->numeric()
                        ->disabled()
                        ->dehydrated(true),
                ])
                ->createItemButtonLabel('Add Order Item')
                ->columns(1)
                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                    static::updateOrderTotals($set, $get);
                })
                ->default([])
                ->mutateRelationshipDataBeforeCreateUsing(function (array $data) {
                    return isset($data['studio_image_id']) ? $data : null;
                }),

            Forms\Components\TextInput::make('subtotal')
                ->label('Subtotal (Before Discount)')
                ->numeric()
                ->disabled()
                ->dehydrated(true),

            Forms\Components\TextInput::make('discount')
                ->label('Discount')
                ->numeric()
                ->default(0)
                ->reactive()
                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                    static::updateOrderTotals($set, $get);
                }),

            Forms\Components\TextInput::make('total_price')
                ->label('Total After Discount')
                ->numeric()
                ->disabled()
                ->dehydrated(true),

            Forms\Components\TextInput::make('paid_amount')
                ->label('Paid Amount')
                ->numeric()
                ->default(0)
                ->reactive()
                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                    static::updateOrderTotals($set, $get);
                }),

            Forms\Components\TextInput::make('remaining_amount')
                ->label('Remaining Amount')
                ->numeric()
                ->disabled()
                ->dehydrated(true),
        ]);
    }

    public static function afterSave(Form $form): void
    {
        $form->getRecord()->calculateTotals();
    }

    protected static function updateItemPrice(callable $set, callable $get): void
    {
        $studioImageId = $get('studio_image_id');
        $studioImage = \App\Models\StudioImage::find($studioImageId);

        if (!$studioImage) {
            $set('price', 0);
            return;
        }

        $price = $studioImage->price;

        if ($get('is_instant')) {
            $price += $studioImage->instant_price ?? 0;
        }

        if ($get('include_soft_copy')) {
            $price += $studioImage->soft_copy_price ?? 0;
        }

        $set('price', number_format($price, 2, '.', ''));
    }

    protected static function updateOrderTotals(callable $set, callable $get): void
    {
        $items = $get('items') ?? [];

        $subtotal = collect($items)->sum('price');
        $discount = floatval($get('discount') ?? 0);
        $paid = floatval($get('paid_amount') ?? 0);

        $total = max(0, $subtotal - $discount);
        $remaining = max(0, $total - $paid);

        $set('subtotal', number_format($subtotal, 2, '.', ''));
        $set('total_price', number_format($total, 2, '.', ''));
        $set('remaining_amount', number_format($remaining, 2, '.', ''));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),

                Tables\Columns\TextColumn::make('subtotal')
                    ->label('Subtotal')
                    ->money('EGP'),

                Tables\Columns\TextColumn::make('discount')
                    ->label('Discount')
                    ->money('EGP'),

                Tables\Columns\TextColumn::make('total_price')
                    ->label('Total')
                    ->money('EGP'),

                Tables\Columns\TextColumn::make('paid_amount')
                    ->label('Paid')
                    ->money('EGP'),

                Tables\Columns\TextColumn::make('remaining_amount')
                    ->label('Remaining')
                    ->money('EGP'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}