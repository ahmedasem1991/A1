<?php

namespace App\Filament\Resources\TransactionResource\Widgets;

use App\Filament\Resources\TransactionResource\Pages\ListTransactions;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageTable;

class TransactionsOverview extends ChartWidget
{
    use InteractsWithPageTable;

    protected static ?string $heading = 'Transactions Overview';

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $days = collect(range(0, 9))->map(function ($i) {
            return Carbon::now()->subDays($i)->format('Y-m-d');
        });

        $incomeData = $days->map(function ($day) {
            return $this->getPageTableQuery()
                ->where('type', 'income')
                ->whereDate('transaction_date', $day)
                ->sum('amount');
        });

        $expenseData = $days->map(function ($day) {
            return $this->getPageTableQuery()
                ->where('type', 'expense')
                ->whereDate('transaction_date', $day)
                ->sum('amount');
        });

        return [
            'datasets' => [
                [
                    'label' => 'Income',
                    'data' => $incomeData,
                    'backgroundColor' => '#22c55e',
                    'borderColor' => '#22c55e',
                ],
                [
                    'label' => 'Expense',
                    'data' => $expenseData,
                    'backgroundColor' => '#ef4444',
                    'borderColor' => '#ef4444',
                ],
            ],
            'labels' => $days->map(fn ($day) => Carbon::parse($day)->format('D, M j')),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getTablePage(): string
    {
        return ListTransactions::class;
    }
}
