<?php

namespace App\Filament\Resources;

use App\Actions\Finance\ReverseJournalEntryAction;
use App\Enums\JournalEntryType;
use App\Filament\Resources\JournalEntryResource\Pages;
use App\Models\Account;
use App\Models\JournalEntry;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Actions\Action as HeaderAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class JournalEntryResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = JournalEntry::class;

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'General Ledger';

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('entry_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('entry_type')->badge()->formatStateUsing(fn ($state) => $state?->label()),
                Tables\Columns\TextColumn::make('description')->searchable(),
                Tables\Columns\TextColumn::make('total_amount')->money('EGP')->sortable(),
                Tables\Columns\IconColumn::make('is_reversed')->boolean()->label('Reversed'),
                Tables\Columns\TextColumn::make('postedByUser.name')->label('Posted By')->placeholder('System'),
            ])
            ->filters([
                SelectFilter::make('entry_type')
                    ->options(collect(JournalEntryType::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                    ->native(false),
                Filter::make('account')
                    ->form([
                        Select::make('account_id')
                            ->label('Account')
                            ->options(fn () => Account::query()->pluck('name', 'id'))
                            ->searchable(),
                    ])
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['account_id'] ?? null,
                        fn (Builder $q, $accountId) => $q->whereHas('lines', fn (Builder $lineQuery) => $lineQuery->where('account_id', $accountId))
                    )),
                Filter::make('entry_date')
                    ->form([
                        DatePicker::make('from')->native(false),
                        DatePicker::make('until')->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('entry_date', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('entry_date', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                //
            ])
            ->defaultSort(fn (Builder $query) => $query->orderByDesc('entry_date')->orderByDesc('id'));
    }

    public static function reverseAction(): HeaderAction
    {
        return HeaderAction::make('reverse')
            ->label('Reverse Entry')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('This posts a new entry that exactly cancels this one out. The original stays visible but is flagged as reversed. This cannot be undone.')
            ->form([
                Textarea::make('reason')
                    ->label('Reason for reversal')
                    ->required()
                    ->minLength(5)
                    ->maxLength(200),
            ])
            ->action(function (JournalEntry $record, array $data) {
                app(ReverseJournalEntryAction::class)->handle($record, $data['reason']);

                Notification::make()
                    ->title('Journal entry reversed')
                    ->success()
                    ->send();
            })
            ->visible(fn (JournalEntry $record) => ! $record->is_reversed
                && $record->entry_type !== JournalEntryType::Reversal
                && auth()->user()?->can('reverse_journal::entry'));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJournalEntries::route('/'),
            'view' => Pages\ViewJournalEntry::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'reverse',
        ];
    }
}
