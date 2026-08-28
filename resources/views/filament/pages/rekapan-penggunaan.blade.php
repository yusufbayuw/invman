<x-filament-panels::page>
    @php($stats = $this->getUsageStats())

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($stats as $stat)
            <x-filament::section compact>
                <div class="flex items-start gap-4">
                    <div @class([
                        'rounded-lg p-2.5 ring-1 ring-inset',
                        'bg-primary-50 text-primary-600 ring-primary-200 dark:bg-primary-400/10 dark:text-primary-400 dark:ring-primary-400/20' => $stat['color'] === 'primary',
                        'bg-success-50 text-success-600 ring-success-200 dark:bg-success-400/10 dark:text-success-400 dark:ring-success-400/20' => $stat['color'] === 'success',
                        'bg-info-50 text-info-600 ring-info-200 dark:bg-info-400/10 dark:text-info-400 dark:ring-info-400/20' => $stat['color'] === 'info',
                        'bg-warning-50 text-warning-600 ring-warning-200 dark:bg-warning-400/10 dark:text-warning-400 dark:ring-warning-400/20' => $stat['color'] === 'warning',
                    ])>
                        <x-filament::icon :icon="$stat['icon']" class="h-6 w-6" />
                    </div>

                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</p>
                        <p class="mt-1 text-2xl font-bold tracking-tight text-gray-950 dark:text-white">{{ $stat['value'] }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $stat['description'] }}</p>
                    </div>
                </div>
            </x-filament::section>
        @endforeach
    </div>

    {{ $this->table }}
</x-filament-panels::page>
