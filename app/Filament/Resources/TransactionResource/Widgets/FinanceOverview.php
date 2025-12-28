<?php

namespace App\Filament\Resources\TransactionResource\Widgets;

use App\Filament\Resources\TransactionResource\Pages\ListTransactions;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FinanceOverview extends BaseWidget
{
    use InteractsWithPageTable;

    protected function getStats(): array
    {
        $transactions = $this->getPageTableQuery()
            ->when(
                $this->filters['type'] ?? null,
                fn ($query, $type) => $query->where('type', $type)
            )
            ->when(
                $this->filters['date'] ?? null,
                fn ($query, $data) => $query
                    ->when($data['from'], fn ($q) => $q->whereDate('transaction_date', '>=', $data['from']))
                    ->when($data['until'], fn ($q) => $q->whereDate('transaction_date', '<=', $data['until']))
            )
            ->get();

        $totalIncome = $transactions->where('type', 'income')->sum('amount');
        $totalExpense = $transactions->where('type', 'expense')->sum('amount');
        $balance = $totalIncome - $totalExpense;

        return [
            Stat::make('Total Income', number_format($totalIncome, 2))
                ->description('Total Income')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->extraAttributes([
                    'class' => 'font-serif font-extrabold',
                ]),
            Stat::make('Total Expense', number_format($totalExpense, 2))
                ->description('Total Expense')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger')
                ->extraAttributes([
                    'class' => 'font-serif font-extrabold',
                ]),
            Stat::make('Balance', number_format($balance, 2))
                ->description('Balance')
                ->descriptionIcon('heroicon-m-currency-pound')
                ->color($balance >= 0 ? 'success' : 'danger')
                ->extraAttributes([
                    'class' => 'font-serif font-extrabold',
                ]),
        ];
    }

    protected function getTablePage(): string
    {
        return ListTransactions::class;
    }
}
