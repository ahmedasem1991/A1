<?php

namespace App\Filament\Pages;

use App\Services\FinancialReportService;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Carbon\Carbon;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

class FinancialReports extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Reports';

    protected static string $view = 'filament.pages.financial-reports';

    public ?string $from = null;

    public ?string $to = null;

    public ?string $activePreset = 'today';

    public function mount(): void
    {
        $this->from = now()->startOfDay()->toDateTimeString();
        $this->to = now()->endOfDay()->toDateTimeString();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                DateTimePicker::make('from')->required()->seconds(false)->live()->native(false),
                DateTimePicker::make('to')->required()->seconds(false)->live()->native(false),
            ]);
    }

    /**
     * @return array{
     *     total_sales: float, total_income: float, total_expenses: float, net_profit: float,
     *     payments_received: float, payments_made: float, refunds_issued: float,
     *     outstanding_receivables: float, outstanding_payables: float, cash_balance: float,
     * }
     */
    public function getSummary(): array
    {
        $from = Carbon::parse($this->from ?? now()->startOfDay());
        $to = Carbon::parse($this->to ?? now()->endOfDay());

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        return app(FinancialReportService::class)->summarizeRange($from, $to);
    }

    public function setPreset(string $preset): void
    {
        [$from, $to] = match ($preset) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            'yesterday' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            'this_week' => [now()->startOfWeek(), now()->endOfDay()],
            'this_month' => [now()->startOfMonth(), now()->endOfDay()],
            'last_month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            'this_year' => [now()->startOfYear(), now()->endOfDay()],
            default => [Carbon::parse($this->from ?? now()), Carbon::parse($this->to ?? now())],
        };

        $this->activePreset = $preset;

        $this->from = $from->toDateTimeString();
        $this->to = $to->toDateTimeString();
    }
}
