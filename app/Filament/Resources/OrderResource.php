<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Category;
use App\Models\ImageCard;
use App\Models\Order;
use App\Models\Product;
use App\Models\StudioImage;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use IbrahimBougaoua\FilaProgress\Tables\Columns\CircleProgress;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationGroup = 'Orders';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationIcon = 'heroicon-m-shopping-bag';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required(),

            Forms\Components\Repeater::make('orderItems')
                ->label('Order Items')
                ->relationship('orderItems')
                ->columns(1)
                ->columnSpan('full')
                ->default([])
                ->collapsed(false)
                ->itemLabel(fn (array $state) => static::getItemLabel($state))
                ->schema([
                    Forms\Components\Grid::make(4)->schema([
                        Forms\Components\TextInput::make('status')
                            ->label('Status')
                            ->default('creation')
                            ->hiddenOn(['create', 'edit'])
                            ->disabled(),
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

                        Forms\Components\Select::make('product_category_id')
                            ->label('Product Category')
                            ->options(Category::all()->pluck('name', 'id'))
                            ->reactive()
                            ->searchable()
                            ->preload()
                            ->visible(fn ($get) => $get('category') === 'product')
                            ->dehydrated(false) // 🚀 won't be saved to DB

                            ->afterStateUpdated(fn ($set) => $set('product_id', null)),

                        Forms\Components\Select::make('product_id')
                            ->label('Product')
                            ->options(function (callable $get) {
                                $categoryId = $get('product_category_id');
                                if (! $categoryId) {
                                    return [];
                                }

                                return Product::where('category_id', $categoryId)->get()->mapWithKeys(function ($product) {
                                    $totalStock = $product->inventories->sum('pivot.stock_quantity');
                                    $displayName = $product->sku.' - '.$product->name.' ('.$totalStock.' in stock)';

                                    return [$product->id => $displayName];
                                });
                            })
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
                    ]),
                ]),

            Forms\Components\TextInput::make('subtotal')->numeric()->disabled()->dehydrated(true),
            Forms\Components\TextInput::make('discount')->numeric()->default(0)->reactive()
                ->afterStateUpdated(fn ($state, $set, $get) => static::updateOrderTotals($set, $get)),
            Forms\Components\TextInput::make('total_price')->numeric()->disabled()->dehydrated(true),
            Forms\Components\TextInput::make('paid_amount')->numeric()->default(0)->reactive()
                ->afterStateUpdated(fn ($state, $set, $get) => static::updateOrderTotals($set, $get)),
            Forms\Components\TextInput::make('remaining_amount')->numeric()->disabled()->dehydrated(true),
        ]);
    }

    protected static function resetItemFields(callable $set, callable $get): void
    {
        $set('studio_image_id', null);
        $set('image_card_id', null);
        $set('product_category_id', null);
        $set('product_id', null);
        $set('is_instant', false);
        $set('include_soft_copy', false);
        $set('is_with_name', false);
        $set('price', 0);

        static::updateOrderTotalsFromItem($set, $get);
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
        static::updateOrderTotalsFromItem($set, $get);
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

    protected static function getItemLabel(array $state): string
    {
        $category = $state['category'] ?? null;
        $label = match ($category) {
            'studio_image' => 'Studio Image',
            'image_card' => 'Image Card',
            'product' => 'Product',
            default => 'Item',
        };

        if ($category === 'studio_image' && isset($state['studio_image_id'])) {
            $item = StudioImage::find($state['studio_image_id']);
            if ($item) {
                $label .= ' - '.$item->image_size;
            }
        } elseif ($category === 'image_card' && isset($state['image_card_id'])) {
            $item = ImageCard::find($state['image_card_id']);
            if ($item) {
                $label .= ' - '.$item->card_size;
            }
        } elseif ($category === 'product' && isset($state['product_id'])) {
            $item = Product::find($state['product_id']);
            if ($item) {
                $label .= ' - '.$item->name;
            }
        }

        return $label;
    }

    protected static function updateOrderTotalsFromItem(callable $set, callable $get): void
    {
        $items = $get('../../orderItems') ?? [];
        $subtotal = collect($items)->sum(fn ($item) => floatval($item['price'] ?? 0));
        $discount = floatval($get('../../discount') ?? 0);
        $paid = floatval($get('../../paid_amount') ?? 0);

        $total = max(0, $subtotal - $discount);
        $remaining = max(0, $total - $paid);

        $set('../../subtotal', number_format($subtotal, 2, '.', ''));
        $set('../../total_price', number_format($total, 2, '.', ''));
        $set('../../remaining_amount', number_format($remaining, 2, '.', ''));
    }

    protected static function updateOrderTotals(callable $set, callable $get): void
    {
        $items = $get('orderItems') ?? [];
        $subtotal = collect($items)->sum(fn ($item) => floatval($item['price'] ?? 0));
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
                Tables\Columns\TextColumn::make('id')->searchable(),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'warning' => 'processing',
                        'success' => 'completed',
                    ]),
                Tables\Columns\TextColumn::make('subtotal')->label('Subtotal')->money('EGP'),
                Tables\Columns\TextColumn::make('discount')->label('Discount')->money('EGP'),
                Tables\Columns\TextColumn::make('total_price')->label('Total')->money('EGP'),
                Tables\Columns\TextColumn::make('paid_amount')->label('Paid')->money('EGP'),
                Tables\Columns\TextColumn::make('remaining_amount')->label('Remaining')->money('EGP'),
                CircleProgress::make('Items')
                    ->getStateUsing(function ($record) {
                        $total = $record->orderItems()->count();
                        $progress = $record->orderItems()->where('status', 'completed')->count();

                        return [
                            'total' => $total,
                            'progress' => $progress,
                        ];
                    }),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'processing' => 'Processing',
                        'completed' => 'Completed',
                    ])
                    ->default('processing')
                    ->native(false),
            ])

            ->actions([
                Tables\Actions\Action::make('complete_order')
                    ->label('Complete')
                    ->icon('heroicon-s-check-circle')
                    ->form([
                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\TextInput::make('subtotal')
                                ->label('Subtotal')
                                ->disabled()
                                ->default(fn ($record) => $record->subtotal),

                            Forms\Components\TextInput::make('discount')
                                ->label('Discount')
                                ->disabled()
                                ->default(fn ($record) => $record->discount),

                            Forms\Components\TextInput::make('total_price')
                                ->label('Total Price')
                                ->disabled()
                                ->default(fn ($record) => $record->total_price),

                            Forms\Components\TextInput::make('paid_amount')
                                ->label('Paid Amount')
                                ->disabled()
                                ->default(fn ($record) => $record->paid_amount),

                            Forms\Components\TextInput::make('remaining_amount')
                                ->label('Remaining Amount')
                                ->disabled()
                                ->default(fn ($record) => $record->remaining_amount),

                            Forms\Components\TextInput::make('payment')
                                ->label('Payment Amount')
                                ->numeric()
                                ->required()
                                ->default(fn ($record) => $record->remaining_amount)
                                ->rules(fn ($record) => [
                                    'min:1',
                                    'max:'.$record->remaining_amount,
                                ])
                                ->helperText(fn ($record) => "Enter amount between 1 and {$record->remaining_amount}"),
                        ]),
                    ])
                    ->action(function ($record, array $data) {
                        $payment = floatval($data['payment']);
                        $record->paid_amount += $payment;
                        $record->remaining_amount = max(0, $record->total_price - $record->paid_amount);

                        if ($record->remaining_amount == 0) {
                            $record->status = 'completed';
                        }
                        Transaction::create([
                            'type' => 'income',
                            'amount' => $payment,
                            'order_id' => $record->id,
                            'user_id' => auth()->user()->id,
                            'notes' => 'Order Payment',
                            'transaction_date' => now(),
                        ]);

                        $record->save();
                    })
                    ->visible(fn ($record) => $record->remaining_amount > 0 && $record->status == 'processing' && $record->orderItems()->where('status', 'completed')->count() == $record->orderItems()->count()),
                Tables\Actions\ViewAction::make()->slideOver(),
            ])
            ->bulkActions([
                //
            ])
            ->recordUrl(null)
            ->recordAction('view');
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

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->orderBy('id', 'desc');
    }
}
