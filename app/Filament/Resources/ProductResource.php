<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\BelongsToManyRepeater;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationGroup = 'Inventory Management';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')->required(),
            TextInput::make('sku')->unique(ignoreRecord: true),
            Textarea::make('description')->rows(3),
            TextInput::make('price')->numeric()->required(),
            TextInput::make('base_price')->numeric()->nullable(),
            Select::make('category_id')
                ->label('Product Category')
                ->options(Category::all()->pluck('name', 'id'))
                ->relationship('category', 'name')
                ->searchable()
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
                        ->required()
                        ->preserveFilenames()
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
                        ->options(Inventory::all()->pluck('name', 'id'))  // Get list of inventories
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
            Tables\Actions\DeleteAction::make(),
        ])
        ->bulkActions([
            Tables\Actions\DeleteBulkAction::make(),
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
