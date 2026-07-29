<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StudioImageResource\Pages;
use App\Models\StudioImage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

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
                ->maxLength(255)
                ->unique(ignoreRecord: true)
                ->label('Image Size'),
            Forms\Components\TextInput::make('image_count')
                ->required()
                ->numeric()
                ->minValue(1)
                ->label('Image Count'),
            Forms\Components\TextInput::make('price')
                ->required()
                ->numeric()
                ->prefix('EGP')
                ->minValue(0)
                ->label('Base Price'),
            Forms\Components\TextInput::make('instant_price')
                ->numeric()
                ->prefix('EGP')
                ->minValue(0)
                ->label('Instant Price'),
            Forms\Components\TextInput::make('soft_copy_price')
                ->numeric()
                ->minValue(0)
                ->prefix('EGP')
                ->label('Soft Copy Price'),
            Forms\Components\TextInput::make('name_price')
                ->numeric()
                ->minValue(0)
                ->default(5.00)
                ->prefix('EGP')
                ->label('+Name Price'),
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
                Tables\Columns\TextColumn::make('name_price')->money('EGP'),
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
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function (Tables\Actions\DeleteBulkAction $action, Collection $records) {
                            if ($records->contains(fn (StudioImage $record) => $record->orderItems()->exists())) {
                                Notification::make()
                                    ->title('Cannot delete: one or more studio images have order history')
                                    ->danger()
                                    ->send();

                                $action->cancel();
                            }
                        }),
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
