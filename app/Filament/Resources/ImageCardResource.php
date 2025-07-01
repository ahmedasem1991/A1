<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ImageCardResource\Pages;
use App\Filament\Resources\ImageCardResource\RelationManagers;
use App\Models\ImageCard;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ImageCardResource extends Resource
{
    protected static ?string $model = ImageCard::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

     // This is the parent category in the sidebar
     protected static ?string $navigationGroup = '⚙️ Studio Settings';

 // This is the resource label under that group
//  protected static ?string $navigationLabel = 'Studio';
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('card_size')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('price')
                    ->required()
                    ->numeric()
                    ->prefix('EGP'),
                Forms\Components\TextInput::make('instant_price')
                ->numeric()
                ->label('Instant Price')
                ->prefix('EGP')
                ->nullable(), 
        
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('card_size')
                    ->searchable(),
                Tables\Columns\TextColumn::make('price')
                    ->money()
                    ->sortable(),
                Tables\Columns\TextColumn::make('instant_price')->money('EGP'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            'index' => Pages\ListImageCards::route('/'),
            'create' => Pages\CreateImageCard::route('/create'),
            'edit' => Pages\EditImageCard::route('/{record}/edit'),
        ];
    }
}
