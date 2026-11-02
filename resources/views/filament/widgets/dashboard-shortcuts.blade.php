<x-filament-widgets::widget>
    <nav aria-label="Pekerjaan sehari-hari" class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($this->shortcuts() as $link)
            <a href="{{ $link['url'] }}" @class([
                'flex min-w-0 items-start gap-3 rounded-xl p-4 shadow-sm ring-1 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500',
                'bg-primary-600 text-white ring-primary-600 hover:bg-primary-500' => $loop->first,
                'bg-white text-gray-950 ring-gray-950/10 hover:bg-gray-50 dark:bg-gray-900 dark:text-white dark:ring-white/10' => ! $loop->first,
            ])>
                <x-filament::icon :icon="$link['icon']" class="mt-0.5 h-6 w-6 shrink-0" />
                <span class="min-w-0">
                    <span class="block text-sm font-semibold">{{ $link['label'] }}</span>
                    <span @class(['mt-1 block text-xs leading-5', 'text-primary-100' => $loop->first, 'text-gray-500 dark:text-gray-400' => ! $loop->first])>{{ $link['description'] }}</span>
                </span>
            </a>
        @endforeach
    </nav>
</x-filament-widgets::widget>
