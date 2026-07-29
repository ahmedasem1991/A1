<x-filament-panels::page>
    <x-filament::section>
        <div class="flex flex-wrap items-end justify-between gap-4">
            <form wire:submit.prevent class="flex-1 min-w-[20rem]">
                {{ $this->form }}
            </form>

            <div class="flex flex-wrap gap-2 pb-1" wire:loading.class="opacity-50 pointer-events-none" wire:target="setPreset">
                @foreach ([
                    'today' => 'Today',
                    'yesterday' => 'Yesterday',
                    'this_week' => 'This Week',
                    'this_month' => 'This Month',
                    'last_month' => 'Last Month',
                    'this_year' => 'This Year',
                ] as $preset => $label)
                    <x-filament::button
                        size="sm"
                        :color="$activePreset === $preset ? 'primary' : 'gray'"
                        wire:click="setPreset('{{ $preset }}')"
                    >
                        {{ $label }}
                    </x-filament::button>
                @endforeach
            </div>
        </div>
    </x-filament::section>

    @php($summary = $this->getSummary())

    <div wire:loading.class="opacity-50" wire:target="setPreset,form">
        <div class="grid grid-cols-1 gap-4 mt-6 sm:grid-cols-6">
            <x-filament::section class="sm:col-span-3">
                <div class="flex items-center gap-4">
                    <span class="flex items-center justify-center rounded-xl size-12 shrink-0 bg-success-50 text-success-600 dark:bg-success-500/10 dark:text-success-400">
                        <x-filament::icon icon="heroicon-o-banknotes" class="size-6 shrink-0" style="width: 1.5rem; height: 1.5rem;" />
                    </span>
                    <div>
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Revenue</div>
                        <div class="text-3xl font-bold tracking-tight text-success-600 dark:text-success-400">
                            EGP {{ number_format($summary['total_revenue'], 2) }}
                        </div>
                    </div>
                    <div class="hidden ms-auto sm:flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400">
                        <x-filament::icon icon="heroicon-o-shopping-bag" class="size-4 shrink-0" style="width: 1rem; height: 1rem;" />
                        {{ $summary['total_items_sold'] }} orders{{ $summary['total_items_sold'] === 1 ? '' : 's' }} sold
                    </div>
                </div>
            </x-filament::section>
        </div>

        <div class="grid grid-cols-1 gap-4 mt-6 lg:grid-cols-3">
            @foreach ([
                [
                    'key' => 'products',
                    'title' => 'Products',
                    'icon' => 'heroicon-o-cube',
                    'revenue' => $summary['products_revenue'],
                    'items' => $summary['products'],
                    'empty' => 'No products sold in this range.',
                ],
                [
                    'key' => 'studio_images',
                    'title' => 'Studio Images',
                    'icon' => 'heroicon-o-camera',
                    'revenue' => $summary['studio_images_revenue'],
                    'items' => $summary['studio_images'],
                    'empty' => 'No studio images sold in this range.',
                ],
                [
                    'key' => 'image_cards',
                    'title' => 'Image Cards',
                    'icon' => 'heroicon-o-identification',
                    'revenue' => $summary['image_cards_revenue'],
                    'items' => $summary['image_cards'],
                    'empty' => 'No image cards sold in this range.',
                ],
            ] as $category)
                <x-filament::section>
                    <x-slot name="heading">
                        <div class="flex items-center gap-2">
                            <x-filament::icon :icon="$category['icon']" class="size-5 shrink-0 text-gray-400 dark:text-gray-500" style="width: 1.25rem; height: 1.25rem;" />
                            {{ $category['title'] }}
                        </div>
                    </x-slot>

                    <x-slot name="description">
                        EGP {{ number_format($category['revenue'], 2) }}
                        @if ($summary['total_revenue'] > 0)
                            &middot; {{ number_format(($category['revenue'] / $summary['total_revenue']) * 100, 1) }}% of total
                        @endif
                    </x-slot>

                    @forelse ($category['items'] as $item)
                        @php($share = $category['revenue'] > 0 ? ($item['total_revenue'] / $category['revenue']) * 100 : 0)
                        <div class="flex items-center gap-3 py-2.5 border-b border-gray-100 dark:border-white/5 last:border-0">
                            <span class="flex items-center justify-center text-xs font-semibold text-gray-400 rounded-full size-6 shrink-0 bg-gray-50 dark:bg-white/5 dark:text-gray-500">
                                {{ $loop->iteration }}
                            </span>

                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-medium truncate" title="{{ $item['name'] }}">{{ $item['name'] }}</span>
                                    <span class="font-semibold whitespace-nowrap">EGP {{ number_format($item['total_revenue'], 2) }}</span>
                                </div>

                                <div class="flex items-center justify-between gap-2 mt-1">
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        Sold {{ $item['times_sold'] }} time{{ $item['times_sold'] === 1 ? '' : 's' }} &middot; Qty {{ $item['total_quantity'] }}
                                    </div>
                                </div>

                                <div class="mt-1.5 h-1.5 w-full rounded-full bg-gray-100 dark:bg-white/5 overflow-hidden">
                                    <div class="h-full rounded-full bg-primary-500" style="width: {{ max($share, 2) }}%"></div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="flex flex-col items-center gap-2 py-6 text-center">
                            <x-filament::icon :icon="$category['icon']" class="size-8 shrink-0 text-gray-300 dark:text-gray-600" style="width: 2rem; height: 2rem;" />
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $category['empty'] }}</p>
                        </div>
                    @endforelse
                </x-filament::section>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
