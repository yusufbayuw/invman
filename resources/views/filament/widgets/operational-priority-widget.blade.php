<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Prioritas Operasional V2
        </x-slot>
        <x-slot name="description">
            Status reservasi saat ini sesuai kewenangan Anda. Filter unit berlaku untuk pengelola fasilitas; filter periode dan status historis tidak menyembunyikan pinjaman terlambat.
        </x-slot>

        <div wire:poll.30s wire:loading.class.delay="opacity-60" class="space-y-5 transition-opacity">
            <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
                @foreach ($categories as $key => $meta)
                    <button
                        type="button"
                        wire:click="selectCategory('{{ $key }}')"
                        wire:key="priority-filter-{{ $key }}"
                        @class([
                            'rounded-xl border p-3 text-start transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500',
                            'border-primary-500 bg-primary-50 dark:bg-primary-500/10' => $activeCategory === $key,
                            'border-gray-200 bg-white hover:bg-gray-50 dark:border-white/10 dark:bg-gray-900 dark:hover:bg-white/5' => $activeCategory !== $key,
                        ])
                        aria-pressed="{{ $activeCategory === $key ? 'true' : 'false' }}"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <x-filament::icon :icon="$meta['icon']" class="h-5 w-5 text-gray-500 dark:text-gray-400"/>
                            @if($snapshot['counts'][$key] > 0)
                                <x-filament::badge :color="$meta['color']">{{ $snapshot['counts'][$key] }}</x-filament::badge>
                            @endif
                        </div>
                        <p class="mt-3 text-2xl font-bold tabular-nums text-gray-950 dark:text-white">{{ number_format($snapshot['counts'][$key]) }}</p>
                        <p class="text-xs font-medium leading-5 text-gray-600 dark:text-gray-300">{{ $meta['label'] }}</p>
                    </button>
                @endforeach
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-3">
                    <x-filament::button
                        size="sm"
                        :color="$activeCategory === 'all' ? 'primary' : 'gray'"
                        wire:click="selectCategory('all')"
                        aria-pressed="{{ $activeCategory === 'all' ? 'true' : 'false' }}"
                    >
                        Semua Prioritas
                    </x-filament::button>
                    @if($activeCategory !== 'all')
                        <p class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ $categories[$activeCategory]['label'] }}</p>
                    @endif
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Diperbarui otomatis setiap 30 detik · kategori dapat beririsan
                </p>
            </div>

            @if(empty($snapshot['rows']))
                <div class="flex flex-col items-center gap-2 rounded-xl border border-dashed border-gray-200 px-5 py-8 text-center dark:border-white/10">
                    <x-filament::icon icon="heroicon-o-check-circle" class="h-8 w-8 text-success-500"/>
                    <p class="text-sm font-medium text-gray-950 dark:text-white">Tidak ada reservasi pada kategori ini</p>
                    <p class="text-xs text-gray-500">Status diperbarui dari data reservasi saat ini.</p>
                </div>
            @else
                <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
                    <table class="w-full min-w-[620px] divide-y divide-gray-200 text-start dark:divide-white/10">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="px-4 py-3 text-start text-xs font-semibold text-gray-700 dark:text-gray-200">Aset / kegiatan</th>
                                <th class="px-4 py-3 text-start text-xs font-semibold text-gray-700 dark:text-gray-200">Unit</th>
                                <th class="px-4 py-3 text-start text-xs font-semibold text-gray-700 dark:text-gray-200">Kondisi</th>
                                <th class="px-4 py-3 text-start text-xs font-semibold text-gray-700 dark:text-gray-200">Batas jadwal</th>
                                <th class="px-4 py-3 text-end text-xs font-semibold text-gray-700 dark:text-gray-200">Tindak lanjut</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach($snapshot['rows'] as $row)
                                <tr wire:key="operation-{{ $row['category'] }}-{{ $row['key'] }}" class="hover:bg-gray-50 dark:hover:bg-white/5">
                                    <td class="px-4 py-3">
                                        <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $row['asset'] }}</p>
                                        <p class="max-w-xs truncate text-xs text-gray-500">{{ $row['type_label'] }} · {{ $row['activity'] }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200">{{ $row['unit'] }}</td>
                                    <td class="px-4 py-3">
                                        <x-filament::badge :color="$row['color']">{{ $row['category_label'] }}</x-filament::badge>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-700 dark:text-gray-200">{{ $row['due'] }}</td>
                                    <td class="px-4 py-3 text-end">
                                        <a href="{{ $row['url'] }}" class="text-sm font-semibold text-primary-600 hover:underline dark:text-primary-400">
                                            {{ $row['action'] }} →
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Menampilkan hingga 12 reservasi prioritas. Untuk daftar lengkap, buka Peminjaman Saya / Peminjaman Dikelola.
                </p>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
