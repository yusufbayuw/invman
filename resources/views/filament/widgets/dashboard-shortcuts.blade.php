<x-filament-widgets::widget>
    @php
        $links = collect($this->shortcuts());
        $groups = [
            'Tindakan Cepat' => $links->where('featured', true)->values(),
            'Pantau & Tindak Lanjut' => $links->where('featured', false)->values(),
        ];
    @endphp
    <nav aria-label="Pekerjaan sehari-hari" class="space-y-5">
        @foreach ($groups as $heading => $items)
            @if ($items->isNotEmpty())
                <section aria-label="{{ $heading }}">
                    <h2 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $heading }}</h2>
                    <div @class([
                        'grid grid-cols-1 gap-3',
                        'sm:grid-cols-2' => $heading === 'Tindakan Cepat' && $items->count() === 2,
                        'sm:grid-cols-3' => $heading === 'Tindakan Cepat' && $items->count() >= 3,
                        'sm:grid-cols-2 xl:grid-cols-4' => $heading !== 'Tindakan Cepat',
                    ])>
                        @foreach ($items as $link)
                            <a href="{{ $link['url'] }}" @class([
                                'flex min-w-0 items-start gap-3 rounded-xl p-4 shadow-sm ring-1 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500',
                                'min-h-28 bg-primary-600 text-white ring-primary-600 hover:bg-primary-500' => $link['featured'],
                                'bg-white text-gray-950 ring-gray-950/10 hover:bg-gray-50 dark:bg-gray-900 dark:text-white dark:ring-white/10' => ! $link['featured'],
                            ])>
                                <x-filament::icon :icon="$link['icon']" class="mt-0.5 h-6 w-6 shrink-0" />
                                <span class="min-w-0">
                                    <span class="block text-base font-semibold">{{ $link['label'] }}</span>
                                    <span @class(['mt-1 block text-sm leading-5', 'text-primary-100' => $link['featured'], 'text-gray-500 dark:text-gray-400' => ! $link['featured']])>{{ $link['description'] }}</span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
        @endforeach
    </nav>
</x-filament-widgets::widget>
