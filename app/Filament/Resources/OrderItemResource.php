<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderItemResource\Pages;
use App\Models\ImageCard;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StudioImage;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Forms;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrderItemResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = OrderItem::class;

    protected static ?string $navigationGroup = 'Orders';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationIcon = 'heroicon-m-shopping-cart';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Wizard::make([
                static::stepCreation(),
                static::stepProcessing(),
                static::stepRevision(),
                static::stepPrinting(),
                static::stepCompleted(),
            ])
                ->previousAction(fn (\Filament\Forms\Components\Actions\Action $action) => $action->disabled()->extraAttributes(['x-show' => 'false']))
                ->startOnStep(fn ($record) => array_search($record->status, OrderItem::$workflow) + 1)
                ->columnSpan('full'),
        ]);
    }

    protected static function updatePrice(callable $set, callable $get): void
    {
        $studioImage = \App\Models\StudioImage::find($get('studio_image_id'));

        if (! $studioImage) {
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
                Tables\Columns\TextColumn::make('status')
                    ->label('Status'),
                Tables\Columns\TextColumn::make('category')
                    ->label('Category')
                    ->formatStateUsing(fn ($state) => ucfirst(str_replace('_', ' ', $state))),
                Tables\Columns\TextColumn::make('item_name')
                    ->label('Item')
                    ->getStateUsing(function ($record) {
                        if ($record->category === 'studio_image' && $record->studioImage) {
                            return $record->studioImage->image_size;
                        } elseif ($record->category === 'image_card' && $record->imageCard) {
                            return $record->imageCard->card_size;
                        } elseif ($record->category === 'product' && $record->product) {
                            return $record->product->name;
                        }

                        return '-';
                    }),
                Tables\Columns\IconColumn::make('is_instant')->boolean()->label('Fawry'),
                Tables\Columns\IconColumn::make('include_soft_copy')->boolean()->label('Soft Copy'),
                Tables\Columns\IconColumn::make('is_with_name')->boolean()->label('+Name'),
                Tables\Columns\TextColumn::make('price')->money('EGP'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->native(false)
                    ->options(array_combine(OrderItem::$workflow, OrderItem::$workflow)),
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
            'edit' => Pages\EditOrderItem::route('/{record}/edit'),
        ];
    }

    protected static function stepCreation(): Wizard\Step
    {
        return Wizard\Step::make('Creation')
            ->icon('heroicon-m-shopping-bag')
            ->schema([
                Forms\Components\Grid::make(4)->schema([
                    Forms\Components\Select::make('category')
                        ->label('Category')
                        ->options([
                            'studio_image' => 'Studio Image',
                            'image_card' => 'Image Card',
                            'product' => 'Product',
                        ])
                        ->reactive()
                        ->required()
                        ->afterStateUpdated(fn ($set, $get) => static::resetItemFields($set, $get)),
                    Forms\Components\Select::make('product_id')
                        ->label('Product')
                        ->relationship('product', 'name')
                        ->reactive()
                        ->required()
                        ->searchable()
                        ->visible(fn ($get) => $get('category') === 'product')
                        ->afterStateUpdated(fn ($set, $get) => static::updateItemData($set, $get)),

                    Forms\Components\Select::make('studio_image_id')
                        ->label('Studio Image')
                        ->options(StudioImage::all()->pluck('image_size', 'id'))
                        ->reactive()
                        ->required()
                        ->visible(fn ($get) => $get('category') === 'studio_image')
                        ->afterStateUpdated(fn ($set, $get) => static::updateItemData($set, $get)),

                    Forms\Components\Select::make('image_card_id')
                        ->label('Image Card')
                        ->options(ImageCard::all()->pluck('card_size', 'id'))
                        ->reactive()
                        ->required()
                        ->visible(fn ($get) => $get('category') === 'image_card')
                        ->afterStateUpdated(fn ($set, $get) => static::updateItemData($set, $get)),

                    Forms\Components\Checkbox::make('is_instant')
                        ->label('Fawry')
                        ->reactive()
                        ->visible(fn ($get) => static::shouldShowInstant($get))
                        ->afterStateUpdated(fn ($set, $get) => static::updateItemData($set, $get)),

                    Forms\Components\Checkbox::make('include_soft_copy')
                        ->label('Soft Copy')
                        ->reactive()
                        ->visible(fn ($get) => static::shouldShowSoftCopy($get))
                        ->afterStateUpdated(fn ($set, $get) => static::updateItemData($set, $get)),

                    Forms\Components\Checkbox::make('is_with_name')
                        ->label('+Name')
                        ->reactive()
                        ->visible(fn ($get) => static::shouldShowWithName($get))
                        ->afterStateUpdated(fn ($set, $get) => static::updateItemData($set, $get)),

                    Forms\Components\TextInput::make('price')
                        ->label('Item Price')
                        ->numeric()
                        ->disabled()
                        ->dehydrated(true)
                        ->columnSpanFull(),
                ])->disabled(),
            ])
            ->afterValidation(function ($record) {
                $record->advanceStatus();

                if (auth()->user()->can('view_processing_form_order::item')) {
                    return redirect()->route('filament.admin.resources.order-items.edit', ['record' => $record->id]);
                }

                return redirect()->route('filament.admin.resources.order-items.index');
            });
    }

    protected static function stepProcessing(): Wizard\Step
    {
        return Wizard\Step::make('Processing')
            ->icon('heroicon-m-cube')
            ->schema([
                SpatieMediaLibraryFileUpload::make('original_image')
                    ->label('Image')
                    ->collection('original_image')
                    ->required(),
            ])
            ->afterValidation(function ($record, Get $get) {
                $file = $get('original_image');
                $record->addMedia(reset($file))->toMediaCollection('original_image');
                $record->advanceStatus();

                if (auth()->user()->can('view_revision_form_order::item')) {
                    return redirect()->route('filament.admin.resources.order-items.edit', ['record' => $record->id]);
                }

                return redirect()->route('filament.admin.resources.order-items.index');
            })
            ->visible(fn ($record) => $record->category !== 'product');
    }

    protected static function stepRevision(): Wizard\Step
    {
        return Wizard\Step::make('Revision')
            ->icon('heroicon-m-pencil')

            ->schema([
                Forms\Components\Grid::make(2)->schema([
                    SpatieMediaLibraryFileUpload::make('original_image')
                        ->label('Image')
                        ->collection('original_image')
                        ->downloadable()
                        ->disabled(),
                    SpatieMediaLibraryFileUpload::make('enhanced_image')
                        ->label('Enhanced Image')
                        ->collection('enhanced_image'),
                ]),
            ])
            ->afterValidation(function ($record, Get $get) {
                if ($get('enhanced_image')) {
                    $file = $get('enhanced_image');
                    $record->addMedia(reset($file))->toMediaCollection('enhanced_image');
                }
                $record->advanceStatus();

                if (auth()->user()->can('view_printing_form_order::item')) {
                    return redirect()->route('filament.admin.resources.order-items.edit', ['record' => $record->id]);
                }

                return redirect()->route('filament.admin.resources.order-items.index');
            })
            ->visible(fn ($record) => $record->category !== 'product');
    }

    protected static function stepPrinting(): Wizard\Step
    {
        return
            Wizard\Step::make('Printing')
                ->icon('heroicon-m-printer')
                ->schema([
                    Forms\Components\Grid::make(2)->schema([
                        SpatieMediaLibraryFileUpload::make('original_image')
                            ->label('Image')
                            ->collection('original_image')
                            ->downloadable()
                            ->disabled(),
                        SpatieMediaLibraryFileUpload::make('enhanced_image')
                            ->label('Enhanced Image')
                            ->collection('enhanced_image')
                            ->downloadable()
                            ->disabled(),
                    ]),
                ])
                ->afterValidation(function ($record) {
                    $record->advanceStatus();

                    if (auth()->user()->can('view_completed_form_order::item')) {
                        return redirect()->route('filament.admin.resources.order-items.edit', ['record' => $record->id]);
                    }

                    return redirect()->route('filament.admin.resources.order-items.index');
                })
                ->visible(fn ($record) => $record->category !== 'product');
    }

    protected static function stepCompleted(): Wizard\Step
    {
        return Wizard\Step::make('Completed')
            ->icon('heroicon-m-check-circle')
            ->schema([
                Forms\Components\TextInput::make('status')
                    ->label('Status')
                    ->default('completed')
                    ->disabled(),
            ]);
    }

    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'delete',
            'delete_any',
            'view_creation_form',
            'view_processing_form',
            'view_revision_form',
            'view_printing_form',
            'view_completed_form',
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();
        $allowedStatuses = [];

        if ($user->can('view_creation_form_order::item')) {
            $allowedStatuses[] = OrderItem::$workflow[0];
        }

        if ($user->can('view_processing_form_order::item')) {
            $allowedStatuses[] = OrderItem::$workflow[1];
        }

        if ($user->can('view_revision_form_order::item')) {
            $allowedStatuses[] = OrderItem::$workflow[2];
        }
        if ($user->can('view_printing_form_order::item')) {
            $allowedStatuses[] = OrderItem::$workflow[3];
        }

        if ($user->can('view_completed_form_order::item')) {
            $allowedStatuses[] = OrderItem::$workflow[4];
        }
        if (empty($allowedStatuses)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('status', $allowedStatuses);
    }

    protected static function shouldShowInstant(callable $get): bool
    {
        $category = $get('category');
        if ($category === 'studio_image') {
            return optional(StudioImage::find($get('studio_image_id')))->instant_price > 0;
        }
        if ($category === 'image_card') {
            return optional(ImageCard::find($get('image_card_id')))->instant_price > 0;
        }

        return false;
    }

    protected static function shouldShowSoftCopy(callable $get): bool
    {
        if ($get('category') !== 'studio_image') {
            return false;
        }

        return optional(StudioImage::find($get('studio_image_id')))->soft_copy_price > 0;
    }

    protected static function shouldShowWithName(callable $get): bool
    {
        if ($get('category') !== 'studio_image') {
            return false;
        }

        return optional(StudioImage::find($get('studio_image_id')))->name_price > 0;
    }

    protected static function resetItemFields(callable $set, callable $get): void
    {
        $set('studio_image_id', null);
        $set('image_card_id', null);
        $set('product_id', null);
        $set('is_instant', false);
        $set('include_soft_copy', false);
        $set('is_with_name', false);
        $set('price', 0);
    }

    protected static function updateItemData(callable $set, callable $get): void
    {
        $category = $get('category');
        $price = 0;

        if ($category === 'studio_image' && $id = $get('studio_image_id')) {
            $item = StudioImage::find($id);
            if ($item) {
                $price += $item->price;
                if ($get('is_instant')) {
                    $price += $item->instant_price ?? 0;
                }
                if ($get('include_soft_copy')) {
                    $price += $item->soft_copy_price ?? 0;
                }
                if ($get('is_with_name')) {
                    $price += $item->name_price ?? 0;
                }
            }
        } elseif ($category === 'image_card' && $id = $get('image_card_id')) {
            $item = ImageCard::find($id);
            if ($item) {
                $price += $item->price;
                if ($get('is_instant')) {
                    $price += $item->instant_price ?? 0;
                }
            }
        } elseif ($category === 'product' && $id = $get('product_id')) {
            $item = Product::find($id);
            if ($item) {
                $price += $item->price;
            }
        }

        $set('price', number_format($price, 2, '.', ''));
    }
}
