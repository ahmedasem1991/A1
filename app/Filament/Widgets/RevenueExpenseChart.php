<?php

namespace App\Filament\Widgets;

use App\Models\DailyFinancialSummary;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\ChartWidget;

class RevenueExpenseChart extends ChartWidget
{
    use HasWidgetShield;

    protected static ?string $heading = 'Revenue vs Expenses (Last 30 Days)';

    protected function getData(): array
    {
        $summaries = DailyFinancialSummary::query()
            ->where('summary_date', '>=', now()->subDays(30)->toDateString())
            ->orderBy('summary_date')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Income',
                    'data' => $summaries->pluck('total_income')->all(),
                    'borderColor' => '#22c55e',
                    'backgroundColor' => '#22c55e',
                ],
                [
                    'label' => 'Expenses',
                    'data' => $summaries->pluck('total_expenses')->all(),
                    'borderColor' => '#ef4444',
                    'backgroundColor' => '#ef4444',
                ],
            ],
            'labels' => $summaries->pluck('summary_date')->map(fn ($date) => $date->format('M j'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
