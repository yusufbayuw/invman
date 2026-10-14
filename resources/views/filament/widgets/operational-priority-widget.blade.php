<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Prioritas Operasional V2
        </x-slot>

        <x-slot name="description">
            Status reservasi saat ini sesuai kewenangan Anda. Filter unit berlaku untuk pengelola fasilitas; filter periode dan status historis tidak menyembunyikan pinjaman terlambat.
        </x-slot>

        <div wire:poll.30s wire:loading.class.delay="opacity-60" class="space-y-6 transition-opacity">
            {{-- The category cards are filters, not independent navigation links. --}}
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
                @foreach ($categories as $key => $meta)
                    <button
                        type="button"
                        wire:click="selectCategory('{{ $key }}')"
                        wire:key="priority-filter-{{ $key }}"
                        @class([
                            'flex min-h-[112px] flex-col items-start justify-between gap-2 rounded-xl border p-4 text-start transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900',
                            'border-primary-500 bg-primary-50/70 ring-1 ring-primary-500/20 dark:bg-primary-500/10' => $activeCategory === $key,
                            'border-gray-200 bg-white hover:border-primary-300 hover:bg-gray-50 dark:border-white/10 dark:bg-gray-900 dark:hover:bg-white/5' => $activeCategory !== $key,
                        ])
                        aria-pressed="{{ $activeCategory === $key ? 'true' : 'false' }}"
                    >
                        <x-filament::icon :icon="$meta['icon']" class="h-5 w-5 shrink-0 text-gray-500 dark:text-gray-400" />
                        <span class="block">
                            <span class="block text-2xl font-bold leading-none tabular-nums text-gray-950 dark:text-white">
                                {{ number_format($snapshot['counts'][$key]) }}
                            </span>
                            <span class="mt-2 block text-xs font-medium leading-5 text-gray-600 dark:text-gray-300">
                                {{ $meta['label'] }}
                            </span>
                        </span>
                    </button>
                @endforeach
            </div>

            <div class="flex flex-col gap-3 border-b border-gray-200 pb-4 sm:flex-row sm:items-center sm:justify-between dark:border-white/10">
                <div class="flex flex-wrap items-center gap-3">
                    <x-filament::button
                        size="sm"
                        :color="$activeCategory === 'all' ? 'primary' : 'gray'"
                        wire:click="selectCategory('all')"
                        aria-pressed="{{ $activeCategory === 'all' ? 'true' : 'false' }}"
                    >
                        Semua Prioritas
                    </x-filament::button>
                    @if ($activeCategory !== 'all')
                        <span class="text-sm font-medium text-gray-800 dark:text-gray-200">
                            {{ $categories[$activeCategory]['label'] }}
                        </span>
                    @endif
                </div>
                <p class="text-xs leading-5 text-gray-500 dark:text-gray-400">
                    Diperbarui setiap 30 detik · kategori dapat beririsan
                </p>
            </div>

            @if (empty($snapshot['rows']))
                <div class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-gray-200 px-5 py-8 text-center dark:border-white/10">
                    <x-filament::icon icon="heroicon-o-check-circle" class="h-8 w-8 text-success-500" />
                    <div class="space-y-1">
                        <p class="text-sm font-semibold text-gray-950 dark:text-white">Tidak ada reservasi pada kategori ini</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Status diperbarui dari data reservasi saat ini.</p>
                    </div>
                </div>
            @else
                <div class="space-y-3">
                    {{-- Full-width table on desktop; stacked cards avoid horizontal scrolling on phones. --}}
                    <div class="hidden overflow-x-auto rounded-xl border border-gray-200 md:block dark:border-white/10">
                        <table class="w-full min-w-[740px] divide-y divide-gray-200 text-start dark:divide-white/10">
                            <thead class="bg-gray-50 dark:bg-white/5">
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-start text-xs font-semibold text-gray-700 dark:text-gray-200">Aset / kegiatan</th>
                                    <th scope="col" class="px-4 py-3 text-start text-xs font-semibold text-gray-700 dark:text-gray-200">Unit</th>
                                    <th scope="col" class="px-4 py-3 text-start text-xs font-semibold text-gray-700 dark:text-gray-200">Kondisi</th>
                                    <th scope="col" class="px-4 py-3 text-start text-xs font-semibold text-gray-700 dark:text-gray-200">Batas jadwal</th>
                                    <th scope="col" class="px-4 py-3 text-end text-xs font-semibold text-gray-700 dark:text-gray-200">Tindak lanjut</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                @foreach ($snapshot['rows'] as $row)
                                    <tr wire:key="operation-desktop-{{ $row['key'] }}" class="transition-colors hover:bg-gray-50 dark:hover:bg-white/5">
                                        <td class="px-4 py-3 align-middle">
                                            <p class="max-w-xs truncate text-sm font-semibold text-gray-950 dark:text-white" title="{{ $row['asset'] }}">{{ $row['asset'] }}</p>
                                            <p class="max-w-xs truncate text-xs text-gray-500 dark:text-gray-400" title="{{ $row['type_label'] }} · {{ $row['activity'] }}">{{ $row['type_label'] }} · {{ $row['activity'] }}</p>
                                        </td>
                                        <td class="px-4 py-3 align-middle text-sm text-gray-700 dark:text-gray-200">{{ $row['unit'] }}</td>
                                        <td class="px-4 py-3 align-middle">
                                            <x-filament::badge :color="$row['color']">{{ $row['category_label'] }}</x-filament::badge>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 align-middle text-xs text-gray-700 dark:text-gray-200">{{ $row['due'] }}</td>
                                        <td class="px-4 py-3 text-end align-middle">
                                            <a
                                                href="{{ $row['url'] }}"
                                                class="inline-flex min-h-9 items-center justify-center gap-1.5 whitespace-nowrap rounded-lg border border-primary-200 bg-primary-50 px-3 py-2 text-xs font-semibold text-primary-700 transition-colors hover:bg-primary-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:border-primary-500/30 dark:bg-primary-500/10 dark:text-primary-300 dark:hover:bg-primary-500/20"
                                            >
                                                {{ $row['action'] }} <span aria-hidden="true">→</span>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="grid gap-3 md:hidden">
                        @foreach ($snapshot['rows'] as $row)
                            <article wire:key="operation-mobile-{{ $row['key'] }}" class="space-y-3 rounded-xl border border-gray-200 p-4 dark:border-white/10">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div class="min-w-0 flex-1">
                                        <p class="break-words text-sm font-semibold text-gray-950 dark:text-white">{{ $row['asset'] }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $row['type_label'] }} · {{ $row['activity'] }}</p>
                                    </div>
                                    <x-filament::badge :color="$row['color']">{{ $row['category_label'] }}</x-filament::badge>
                                </div>
                                <dl class="grid grid-cols-2 gap-3 text-xs">
                                    <div>
                                        <dt class="text-gray-500 dark:text-gray-400">Unit</dt>
                                        <dd class="mt-1 font-medium text-gray-800 dark:text-gray-200">{{ $row['unit'] }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-gray-500 dark:text-gray-400">Batas jadwal</dt>
                                        <dd class="mt-1 font-medium text-gray-800 dark:text-gray-200">{{ $row['due'] }}</dd>
                                    </div>
                                </dl>
                                <a
                                    href="{{ $row['url'] }}"
                                    class="inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-lg bg-primary-50 px-3 py-2 text-sm font-semibold text-primary-700 hover:bg-primary-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:bg-primary-500/10 dark:text-primary-300 dark:hover:bg-primary-500/20"
                                >
                                    {{ $row['action'] }} <span aria-hidden="true">→</span>
                                </a>
                            </article>
                        @endforeach
                    </div>
                    <p class="text-xs leading-5 text-gray-500 dark:text-gray-400">
                        Menampilkan hingga 12 reservasi prioritas. Untuk daftar lengkap, buka Peminjaman Saya / Peminjaman Dikelola.
                    </p>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
