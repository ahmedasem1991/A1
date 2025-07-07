<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationGroup = 'Inventory Management';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required(),
            Forms\Components\TextInput::make('sku')->unique(ignoreRecord: true),
            Forms\Components\Textarea::make('description')->rows(3),
            Forms\Components\TextInput::make('price')->numeric()->required(),
            Forms\Components\TextInput::make('base_price')->numeric()->nullable(),
            Forms\Components\Toggle::make('is_active')->label('Active')->default(true),

            Forms\Components\Repeater::make('images')
                ->label('Product Gallery')
                ->relationship('images')
                ->schema([
                    Forms\Components\FileUpload::make('image_path')
                        ->label('Image')
                        ->image()
                        ->directory('product-images')
                        ->required()
                        ->preserveFilenames()
                ])
                ->columns(1)
                ->columnSpan('full')
                ->createItemButtonLabel('Add Image'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
                ImageColumn::make('first_image')
                    ->label('Image')
                    ->getStateUsing(function ($record) {
                        // $record is Product model instance
                        return $record->images->first()?->image_path; // or image_url depending on your column
                    })
                    ->rounded(),   // optional styling
                    // ->square(),   // optional styling
                Tables\Columns\TextColumn::make('name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('sku')->sortable(),
                Tables\Columns\TextColumn::make('price')->money('EGP'),
                Tables\Columns\TextColumn::make('base_price')->money('EGP'),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
                Tables\Columns\TextColumn::make('created_at')->dateTime(),
            ])
            ->filters([])
            ->actions([
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
