<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StudioImageResource\Pages;
use App\Filament\Resources\StudioImageResource\RelationManagers;
use App\Models\StudioImage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class StudioImageResource extends Resource
{
    protected static ?string $model = StudioImage::class;

    protected static ?string $navigationIcon = 'heroicon-m-photo';
 // This is the parent category in the sidebar
 protected static ?string $navigationGroup = '⚙️ Studio Settings';

 // This is the resource label under that group
//  protected static ?string $navigationLabel = 'Studio';
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('image_size')
                ->required()
                ->label('Image Size'),
            Forms\Components\TextInput::make('image_count')
                ->required()
                ->numeric()
                ->label('Image Count'),
            Forms\Components\TextInput::make('price')
                ->required()
                ->numeric()
                ->label('Base Price'),
            Forms\Components\TextInput::make('instant_price')
                ->numeric()
                ->label('Instant Price')
                ->nullable(),
            Forms\Components\TextInput::make('soft_copy_price')
                ->numeric()
                ->label('Soft Copy Price')
                ->nullable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('image_size'),
        Tables\Columns\TextColumn::make('image_count'),
        Tables\Columns\TextColumn::make('price')->money('EGP'),
        Tables\Columns\TextColumn::make('instant_price')->money('EGP'),
        Tables\Columns\TextColumn::make('soft_copy_price')->money('EGP'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
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
            'index' => Pages\ListStudioImages::route('/'),
            'create' => Pages\CreateStudioImage::route('/create'),
            'edit' => Pages\EditStudioImage::route('/{record}/edit'),
        ];
    }
}
