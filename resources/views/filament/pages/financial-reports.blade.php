<x-filament-panels::page>
    <div class="flex flex-wrap items-end gap-4">
        <form wire:submit.prevent class="flex-1 min-w-[20rem]">
            {{ $this->form }}
        </form>

        <div class="flex flex-wrap gap-2 pb-1">
            <x-filament::button size="sm" :color="$activePreset === 'today' ? 'primary' : 'gray'" wire:click="setPreset('today')">Today</x-filament::button>
            <x-filament::button size="sm" :color="$activePreset === 'yesterday' ? 'primary' : 'gray'" wire:click="setPreset('yesterday')">Yesterday</x-filament::button>
            <x-filament::button size="sm" :color="$activePreset === 'this_week' ? 'primary' : 'gray'" wire:click="setPreset('this_week')">This Week</x-filament::button>
            <x-filament::button size="sm" :color="$activePreset === 'this_month' ? 'primary' : 'gray'" wire:click="setPreset('this_month')">This Month</x-filament::button>
            <x-filament::button size="sm" :color="$activePreset === 'last_month' ? 'primary' : 'gray'" wire:click="setPreset('last_month')">Last Month</x-filament::button>
            <x-filament::button size="sm" :color="$activePreset === 'this_year' ? 'primary' : 'gray'" wire:click="setPreset('this_year')">This Year</x-filament::button>
        </div>
    </div>

    @php($summary = $this->getSummary())

    <div class="grid grid-cols-1 gap-4 mt-6 sm:grid-cols-2 lg:grid-cols-4">
        <x-filament::section>
            <x-slot name="heading">Total Sales</x-slot>
            <span class="text-2xl font-semibold">EGP {{ number_format($summary['total_sales'], 2) }}</span>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Total Income</x-slot>
            <span class="text-2xl font-semibold text-success-600">EGP {{ number_format($summary['total_income'], 2) }}</span>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Total Expenses</x-slot>
            <span class="text-2xl font-semibold text-danger-600">EGP {{ number_format($summary['total_expenses'], 2) }}</span>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Net Profit</x-slot>
            <span @class([
                'text-2xl font-semibold',
                'text-success-600' => $summary['net_profit'] >= 0,
                'text-danger-600' => $summary['net_profit'] < 0,
            ])>EGP {{ number_format($summary['net_profit'], 2) }}</span>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Payments Received</x-slot>
            <span class="text-2xl font-semibold">EGP {{ number_format($summary['payments_received'], 2) }}</span>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Payments Made</x-slot>
            <span class="text-2xl font-semibold">EGP {{ number_format($summary['payments_made'], 2) }}</span>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Refunds Issued</x-slot>
            <span class="text-2xl font-semibold">EGP {{ number_format($summary['refunds_issued'], 2) }}</span>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Cash Balance (Current)</x-slot>
            <span class="text-2xl font-semibold">EGP {{ number_format($summary['cash_balance'], 2) }}</span>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Outstanding Receivables (Current)</x-slot>
            <span class="text-2xl font-semibold">EGP {{ number_format($summary['outstanding_receivables'], 2) }}</span>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Outstanding Payables (Current)</x-slot>
            <span class="text-2xl font-semibold">EGP {{ number_format($summary['outstanding_payables'], 2) }}</span>
        </x-filament::section>
    </div>
</x-filament-panels::page>
