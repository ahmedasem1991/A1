<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-6 md:grid-cols-4">
        <x-filament::section>
            <x-slot name="heading">Name</x-slot>
            {{ $record->name }}
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Phone</x-slot>
            {{ $record->phone ?? '—' }}
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Email</x-slot>
            {{ $record->email ?? '—' }}
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Outstanding Balance</x-slot>
            <span class="text-lg font-semibold">EGP {{ number_format($record->current_balance_cache, 2) }}</span>
        </x-filament::section>
    </div>

    <x-filament::section class="mt-6">
        <x-slot name="heading">Customer Ledger</x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left border-b border-gray-200 dark:border-gray-700">
                        <th class="py-2 pr-4">Date</th>
                        <th class="py-2 pr-4">Description</th>
                        <th class="py-2 pr-4 text-right">Debit</th>
                        <th class="py-2 pr-4 text-right">Credit</th>
                        <th class="py-2 pr-4 text-right">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->getLedger() as $row)
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="py-2 pr-4">{{ $row['date']->format('Y-m-d') }}</td>
                            <td class="py-2 pr-4">{{ $row['description'] }}</td>
                            <td class="py-2 pr-4 text-right">{{ $row['debit'] > 0 ? number_format($row['debit'], 2) : '' }}</td>
                            <td class="py-2 pr-4 text-right">{{ $row['credit'] > 0 ? number_format($row['credit'], 2) : '' }}</td>
                            <td class="py-2 pr-4 text-right font-medium">{{ number_format($row['balance'], 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-4 text-center text-gray-500">No ledger activity yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
