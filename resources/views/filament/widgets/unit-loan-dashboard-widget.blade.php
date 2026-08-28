<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex flex-col gap-5">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-xl font-bold tracking-tight text-gray-950">Peminjaman Sarpras</h2>
                    <p class="mt-1 text-sm text-gray-600">Ajukan seluruh barang, ruangan / tempat, dan kendaraan dalam satu formulir.</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <x-filament::button tag="a" :href="$submitUrl" size="lg" icon="heroicon-o-plus-circle">
                        Ajukan Peminjaman
                    </x-filament::button>
                    <x-filament::button tag="a" :href="$mineUrl" color="gray" size="lg" icon="heroicon-o-clipboard-document-list">
                        Peminjaman Saya
                    </x-filament::button>
                    <x-filament::button tag="a" :href="$reportUrl" color="gray" size="lg" icon="heroicon-o-chart-bar-square">
                        Lihat Rekapan
                    </x-filament::button>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
                @foreach ([
                    ['label' => 'Menunggu', 'value' => $counts['submitted'], 'class' => 'bg-amber-50 text-amber-800 ring-amber-200'],
                    ['label' => 'Disetujui', 'value' => $counts['approved'], 'class' => 'bg-emerald-50 text-emerald-800 ring-emerald-200'],
                    ['label' => 'Sedang Dipakai', 'value' => $counts['checked_out'], 'class' => 'bg-blue-50 text-blue-800 ring-blue-200'],
                    ['label' => 'Selesai', 'value' => $counts['returned'], 'class' => 'bg-indigo-50 text-indigo-800 ring-indigo-200'],
                    ['label' => 'Kedaluwarsa', 'value' => $counts['expired'], 'class' => 'bg-gray-50 text-gray-700 ring-gray-200'],
                ] as $stat)
                    <a href="{{ $mineUrl }}" class="rounded-xl p-4 ring-1 transition hover:-translate-y-0.5 hover:shadow-sm {{ $stat['class'] }}">
                        <div class="text-2xl font-bold">{{ $stat['value'] }}</div>
                        <div class="mt-1 text-sm font-medium">{{ $stat['label'] }}</div>
                    </a>
                @endforeach
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
