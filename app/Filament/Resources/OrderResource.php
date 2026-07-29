<?php

namespace App\Filament\Resources;

use App\Actions\Finance\RecordPaymentAction;
use App\Actions\Finance\RecordSaleAction;
use App\Enums\AccountSubtype;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Filament\Resources\OrderResource\Pages;
use App\Models\Account;
use App\Models\Category;
use App\Models\Customer;
use App\Models\ImageCard;
use App\Models\Order;
use App\Models\Product;
use App\Models\StudioImage;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use IbrahimBougaoua\FilaProgress\Tables\Columns\CircleProgress;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OrderResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationGroup = 'Orders';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationIcon = 'heroicon-m-shopping-bag';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('customer_id')
                ->label('Customer')
                ->relationship('customer', 'name')
                ->searchable()
                ->preload()
                ->required()
                ->createOptionForm([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('phone')
                        ->tel()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true, table: 'customers'),
                    Forms\Components\TextInput::make('email')
                        ->email()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true, table: 'customers'),
                ])
                ->afterStateUpdated(function ($state, callable $set) {
                    $set('name', $state ? Customer::find($state)?->name : null);
                })
                ->live(),

            Forms\Components\Hidden::make('name'),

            Forms\Components\Repeater::make('orderItems')
                ->label('Order Items')
                ->relationship('orderItems')
                ->columns(1)
                ->columnSpan('full')
                ->default([])
                ->collapsed(false)
                ->itemLabel(fn (array $state) => static::getItemLabel($state))
                ->deleteAction(fn ($action) => $action->requiresConfirmation())
                ->schema([
                    Forms\Components\Grid::make(4)
                        ->schema([
                            Forms\Components\TextInput::make('status')
                                ->label('Status')
                                ->default('creation')
                                ->hiddenOn('create')
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

                            Forms\Components\TextInput::make('quantity')
                                ->label('Quantity')
                                ->numeric()
                                ->default(1)
                                ->minValue(1)
                                ->reactive()
                                ->required()
                                ->rules([fn ($get) => static::stockAvailabilityRule($get)])
                                ->afterStateUpdated(fn ($set, $get) => static::updateItemData($set, $get)),

                            Forms\Components\TextInput::make('price')
                                ->label('Total Price')
                                ->numeric()
                                ->disabled()
                                ->dehydrated(true)
                                ->columnSpanFull(),
                        ])
                        ->disabled(fn ($get) => $get('status') !== null && $get('status') !== 'creation'),
                ]),

            Forms\Components\TextInput::make('subtotal')->numeric()->disabled()->dehydrated(true),
            Forms\Components\TextInput::make('discount')->numeric()->default(0)->minValue(0)
                ->maxValue(fn (callable $get) => $get('subtotal') ?? 0)
                ->reactive()
                ->afterStateUpdated(fn ($state, $set, $get) => static::updateOrderTotals($set, $get)),
            Forms\Components\TextInput::make('total_price')->numeric()->disabled()->dehydrated(true),
            Forms\Components\TextInput::make('paid_amount')->numeric()->default(0)->minValue(0)
                ->maxValue(fn (callable $get) => $get('total_price') ?? 0)
                ->reactive()
                ->afterStateUpdated(fn ($state, $set, $get) => static::updateOrderTotals($set, $get)),
            Forms\Components\TextInput::make('remaining_amount')->numeric()->disabled()->dehydrated(true),
        ]);
    }

    protected static function stockAvailabilityRule(callable $get): \Closure
    {
        return function (string $attribute, $value, callable $fail) use ($get) {
            if ($get('category') !== 'product') {
                return;
            }

            $productId = $get('product_id');

            if (! $productId) {
                return;
            }

            $product = Product::find($productId);

            if (! $product) {
                return;
            }

            $available = $product->total_stock;

            if ((int) $value > $available) {
                $fail("Only {$available} unit(s) of {$product->name} are in stock.");
            }
        };
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
        $set('quantity', 1);
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

        $quantity = max(1, intval($get('quantity') ?? 1));
        $set('price', number_format($price * $quantity, 2, '.', ''));
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

    protected static function accountIdForPaymentMethod(?string $paymentMethod): ?int
    {
        return Account::query()
            ->where('account_subtype', PaymentMethod::tryFrom($paymentMethod)?->accountSubtype() ?? AccountSubtype::Cash)
            ->value('id');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->searchable(),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->label())
                    ->colors([
                        'warning' => OrderStatus::Processing->value,
                        'success' => OrderStatus::Completed->value,
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
                    ->options(collect(OrderStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                    ->default(OrderStatus::Processing->value)
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
                                    'min:0.01',
                                    'max:'.$record->remaining_amount,
                                ])
                                ->helperText(fn ($record) => "Enter amount between 1 and {$record->remaining_amount}"),

                            Forms\Components\Select::make('payment_method')
                                ->label('Payment Method')
                                ->options(collect(PaymentMethod::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                                ->default(PaymentMethod::Cash->value)
                                ->required()
                                ->live()
                                ->afterStateUpdated(fn (callable $set, $state) => $set('account_id', static::accountIdForPaymentMethod($state)))
                                ->native(false),

                            Forms\Components\Select::make('account_id')
                                ->label('Received Into')
                                ->options(fn () => Account::query()->where('is_bank_account', true)->pluck('name', 'id'))
                                ->default(fn () => static::accountIdForPaymentMethod(PaymentMethod::Cash->value))
                                ->disabled()
                                ->dehydrated(true)
                                ->required()
                                ->native(false),
                        ]),
                    ])
                    ->action(function (Order $record, array $data) {
                        $invoice = $record->invoice ?? app(RecordSaleAction::class)->handle($record);

                        app(RecordPaymentAction::class)->handle(
                            accountId: (int) $data['account_id'],
                            amount: (float) $data['payment'],
                            paymentDate: now(),
                            paymentMethod: PaymentMethod::from($data['payment_method']),
                            customerId: $record->customer_id,
                            allocations: [
                                ['invoice_id' => $invoice->id, 'amount' => (float) $data['payment']],
                            ],
                            recordedByUserId: auth()->id(),
                        );
                    })
                    ->visible(fn ($record) => (float) $record->remaining_amount > 0 && $record->status === OrderStatus::Processing && $record->orderItems()->where('status', 'completed')->count() == $record->orderItems()->count()),
                Tables\Actions\ViewAction::make()->slideOver(),
                Tables\Actions\EditAction::make()
                    ->visible(fn () => auth()->user()->can('edit_order')),
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
        return auth()->user()->can('edit_order');
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'edit',
            'delete',
            'delete_any',
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->orderBy('id', 'desc');
    }
}
