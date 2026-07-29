<?php

namespace App\Filament\Pages;

use App\Services\SalesReportService;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Carbon\Carbon;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

class SalesReport extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Reports';

    protected static ?string $navigationLabel = 'Sales Report';

    protected static string $view = 'filament.pages.sales-report';

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
     *     products: \Illuminate\Support\Collection<int, array{name: string, times_sold: int, total_quantity: int, total_revenue: float}>,
     *     studio_images: \Illuminate\Support\Collection<int, array{name: string, times_sold: int, total_quantity: int, total_revenue: float, is_instant: bool, is_with_name: bool, include_soft_copy: bool}>,
     *     image_cards: \Illuminate\Support\Collection<int, array{name: string, times_sold: int, total_quantity: int, total_revenue: float}>,
     *     total_items_sold: int,
     *     total_revenue: float,
     *     products_revenue: float,
     *     studio_images_revenue: float,
     *     image_cards_revenue: float,
     * }
     */
    public function getSummary(): array
    {
        $from = Carbon::parse($this->from ?? now()->startOfDay());
        $to = Carbon::parse($this->to ?? now()->endOfDay());

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        return app(SalesReportService::class)->summarizeRange($from, $to);
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

    public function updatedFrom(): void
    {
        $this->activePreset = null;
    }

    public function updatedTo(): void
    {
        $this->activePreset = null;
    }
}
