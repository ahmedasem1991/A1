<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderItemResource\Pages;
use App\Filament\Resources\OrderItemResource\RelationManagers;
use App\Models\OrderItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class OrderItemResource extends Resource
{
    protected static ?string $model = OrderItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('studio_image_id')
                ->label('Studio Image')
                ->relationship('studioImage', 'image_size')
                ->required()
                ->reactive()
                ->afterStateUpdated(fn ($state, callable $set, callable $get) => static::updatePrice($set, $get)),
    
            Forms\Components\Checkbox::make('is_instant')
                ->label('Add Instant Delivery')
                ->reactive()
                ->afterStateUpdated(fn ($state, callable $set, callable $get) => static::updatePrice($set, $get)),
    
            Forms\Components\Checkbox::make('include_soft_copy')
                ->label('Include Soft Copy')
                ->reactive()
                ->afterStateUpdated(fn ($state, callable $set, callable $get) => static::updatePrice($set, $get)),
    
            Forms\Components\TextInput::make('price')
                ->label('Total Price')
                ->numeric()
                ->disabled()
                ->dehydrated(true) // store the value
                ->required(),
        ]);
    }

    protected static function updatePrice(callable $set, callable $get): void
{
    $studioImage = \App\Models\StudioImage::find($get('studio_image_id'));

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
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_id')
                    ->label('Order')
                    ->formatStateUsing(fn ($state) => "Order #$state")
                    ->url(fn ($record) => OrderResource::getUrl('edit', ['record' => $record->order_id]))
                    ->openUrlInNewTab() // Optional: opens in new tab
                    ->searchable(),
                Tables\Columns\TextColumn::make('studioImage.image_size')->label('Studio Image'),
                Tables\Columns\IconColumn::make('is_instant')->boolean(),
                Tables\Columns\IconColumn::make('include_soft_copy')->boolean(),
                Tables\Columns\TextColumn::make('price')->money('USD'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListOrderItems::route('/'),
            'create' => Pages\CreateOrderItem::route('/create'),
            'edit' => Pages\EditOrderItem::route('/{record}/edit'),
        ];
    }
}
