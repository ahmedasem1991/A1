<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use App\Models\OrderItem;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Wizard;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\OrderItemResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\OrderItemResource\RelationManagers;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;

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
                static::stepDelivery(),
                static::stepCompleted(),
            ])
                ->previousAction(fn(\Filament\Forms\Components\Actions\Action $action) => $action->disabled()->extraAttributes(['x-show' => 'false']))
                ->startOnStep(fn($record) => array_search($record->status, OrderItem::$workflow) + 1)
                ->columnSpan('full')
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
                Tables\Columns\TextColumn::make('status')
                    ->label('Status'),
                Tables\Columns\TextColumn::make('studioImage.image_size')->label('Studio Image'),
                Tables\Columns\IconColumn::make('is_instant')->boolean(),
                Tables\Columns\IconColumn::make('include_soft_copy')->boolean(),
                Tables\Columns\TextColumn::make('price')->money('USD'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
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
                Forms\Components\Select::make('studio_image_id')
                    ->label('Studio Image')
                    ->relationship('studioImage', 'image_size')
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(fn($state, callable $set, callable $get) => static::updatePrice($set, $get)),

                Forms\Components\Checkbox::make('is_instant')
                    ->label('Add Instant Delivery')
                    ->reactive()
                    ->afterStateUpdated(fn($state, callable $set, callable $get) => static::updatePrice($set, $get)),

                Forms\Components\Checkbox::make('include_soft_copy')
                    ->label('Include Soft Copy')
                    ->reactive()
                    ->afterStateUpdated(fn($state, callable $set, callable $get) => static::updatePrice($set, $get)),

                Forms\Components\TextInput::make('price')
                    ->label('Total Price')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(true)
                    ->required(),
            ])
            ->afterValidation(function ($record) {
                $record->advanceStatus();
                return redirect()->route('filament.admin.resources.order-items.index');
            });
    }

    protected static function stepProcessing(): Wizard\Step
    {
        return Wizard\Step::make('Processing')
            ->icon('heroicon-m-cube')
            ->schema([
                Forms\Components\TextInput::make('status')
                    ->label('Status')
                    ->default('processing')
                    ->disabled(),
            ])
            ->afterValidation(function ($record) {
                $record->advanceStatus();
                return redirect()->route('filament.admin.resources.order-items.index');
            })
            ->visible(fn($record) => $record->category !== 'product');
    }

    protected static function stepRevision(): Wizard\Step
    {
        return     Wizard\Step::make('Revision')
            ->icon('heroicon-m-pencil')
            ->schema([
                Forms\Components\Textarea::make('revision_notes')
                    ->label('Revision Notes')
                    ->nullable(),
            ])
            ->afterValidation(function ($record) {
                $record->advanceStatus();
                return redirect()->route('filament.admin.resources.order-items.index');
            })
            ->visible(fn($record) => $record->category !== 'product');
    }

    protected static function stepPrinting(): Wizard\Step
    {
        return
            Wizard\Step::make('Printing')
            ->icon('heroicon-m-printer')
            ->schema([
                Forms\Components\TextInput::make('printing_info')
                    ->label('Printing Info')
                    ->nullable(),
            ])
            ->afterValidation(function ($record) {
                $record->advanceStatus();
                return redirect()->route('filament.admin.resources.order-items.index');
            })
            ->visible(fn($record) => $record->category !== 'product');
    }

    protected static function stepDelivery(): Wizard\Step
    {
        return Wizard\Step::make('Delivery')
            ->icon('heroicon-m-truck')
            ->schema([
                Forms\Components\TextInput::make('delivery_info')
                    ->label('Delivery Info')
                    ->nullable(),
            ])
            ->afterValidation(function ($record) {
                $record->advanceStatus();
                return redirect()->route('filament.admin.resources.order-items.index');
            });
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
            'view_delivery_form',
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

        if ($user->can('view_delivery_form_order::item')) {
            $allowedStatuses[] = OrderItem::$workflow[4];
        }

        if ($user->can('view_completed_form_order::item')) {
            $allowedStatuses[] = OrderItem::$workflow[5];
        }
        if (empty($allowedStatuses)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('status', $allowedStatuses);
    }
}
