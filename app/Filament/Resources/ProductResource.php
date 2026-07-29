<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Inventory;
use App\Models\Product;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Inventory Management';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            TextInput::make('sku')
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            Textarea::make('description')
                ->rows(3)
                ->maxLength(65535),
            TextInput::make('price')
                ->numeric()
                ->required()
                ->minValue(0)
                ->prefix('EGP'),
            TextInput::make('base_price')
                ->numeric()
                ->minValue(0)
                ->prefix('EGP'),
            Select::make('category_id')
                ->label('Product Category')
                ->relationship('category', 'name')
                ->searchable()
                ->preload()
                ->required(),
            Toggle::make('is_active')->label('Active')->default(true),

            Repeater::make('images')
                ->label('Product Gallery')
                ->relationship('images')
                ->schema([
                    FileUpload::make('image_path')
                        ->label('Image')
                        ->image()
                        ->directory('product-images')
                        ->required(),
                ])
                ->columns(1)
                ->columnSpan('full')
                ->createItemButtonLabel('Add Image'),
            // Repeater for adding inventories and stock quantities
            Repeater::make('inventoryProduct')
                ->relationship()  // Use the 'inventories' relationship defined in the model
                ->label('Inventory Stock')
                ->schema([
                    Select::make('inventory_id')  // Select Inventory
                        ->options(fn (callable $get) => Inventory::query()
                            ->whereNotIn('id', collect($get('../../inventoryProduct'))->pluck('inventory_id')->filter())
                            ->pluck('name', 'id'))
                        ->searchable()
                        ->required()
                        ->label('Inventory'),

                    TextInput::make('stock_quantity')  // Specify stock quantity for each inventory
                        ->label('Stock Quantity')
                        ->numeric()
                        ->minValue(0)
                        ->required(),
                ])
                ->columns(2)
                ->defaultItems(1)
                ->createItemButtonLabel('Add Inventory Stock'),

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            ImageColumn::make('first_image')
                ->label('Image')
                ->getStateUsing(fn ($record) => $record->images->first()?->image_path)
                ->rounded(),

            TextColumn::make('name')->sortable()->searchable(),
            TextColumn::make('sku')->sortable(),
            TextColumn::make('price')->money('EGP'),
            TextColumn::make('base_price')->money('EGP'),

            IconColumn::make('is_active')->boolean()->label('Active'),

            TextColumn::make('total_stock')
                ->label('Total Stock')
                ->getStateUsing(fn ($record) => $record->inventories->sum('pivot.stock_quantity')),

            TextColumn::make('created_at')->dateTime(),
        ])
            ->filters([])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (Tables\Actions\DeleteAction $action, Product $record) {
                        if ($record->orderItems()->exists()) {
                            Notification::make()
                                ->title('Cannot delete: product has order history')
                                ->danger()
                                ->send();

                            $action->cancel();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->before(function (Tables\Actions\DeleteBulkAction $action, Collection $records) {
                        if ($records->contains(fn (Product $record) => $record->orderItems()->exists())) {
                            Notification::make()
                                ->title('Cannot delete: one or more products have order history')
                                ->danger()
                                ->send();

                            $action->cancel();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
